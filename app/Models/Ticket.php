<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = [
        'ticket_number', 'event_id', 'user_id', 'transaction_id', 'booking_date', 'quantity', 'ticket_type', 'ticket_type_id', 'status', 'price', 'currency',
        'holder_name', 'seat_number', 'qr_code', 'purchase_date', 'used_at', 'validated_by', 'check_in_count',
        'max_check_ins', 'validation_location', 'validation_notes'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'price' => 'decimal:3',
        'purchase_date' => 'datetime',
        'used_at' => 'datetime',
        'check_in_count' => 'integer',
        'max_check_ins' => 'integer',
        'quantity' => 'integer'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** docs/ticket_types_contract.md — `ticket_type` keeps the type key. */
    public function ticketType()
    {
        return $this->belongsTo(EventTicketType::class, 'ticket_type_id')->withoutGlobalScopes();
    }

    /** People admitted by this ticket (multi-entry, stored as max_check_ins). */
    public function admits(): int
    {
        return max(1, (int) ($this->max_check_ins ?: 1));
    }

    /** {id, key, name_text, category, color, admits} for ticket resources. */
    public function ticketTypeApi(): ?array
    {
        $type = $this->ticketType;
        if (! $type) {
            return $this->ticket_type ? ['id' => null, 'key' => $this->ticket_type, 'name_text' => $this->ticket_type,
                'category' => 'general', 'color' => EventTicketType::CATEGORY_COLORS['general'], 'admits' => $this->admits()] : null;
        }

        return ['id' => $type->id, 'key' => $type->key, 'name_text' => $type->nameText(), 'category' => $type->category,
            'color' => $type->colorValue(), 'admits' => $this->admits()];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'transaction_id', 'transaction_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'active')
                    ->whereColumn('check_in_count', '<', 'max_check_ins');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    /**
     * Check if ticket is valid for entry
     */
    public function isValid(): bool
    {
        // Check if ticket is active
        if ($this->status !== 'active') {
            return false;
        }

        // Check if ticket hasn't exceeded max check-ins
        if ($this->check_in_count >= $this->max_check_ins) {
            return false;
        }

        // Check if booking date hasn't passed (if set)
        if ($this->booking_date && $this->booking_date < now()->startOfDay()) {
            return false;
        }

        // Check if event hasn't ended
        if ($this->event && $this->event->end_time < now()) {
            return false;
        }

        return true;
    }

    /**
     * Get validation status with reason
     */
    public function getValidationStatus(): array
    {
        if ($this->status === 'cancelled') {
            return [
                'is_valid' => false,
                'reason' => 'TICKET_CANCELLED',
                'message' => 'التذكرة ملغاة / Ticket cancelled'
            ];
        }

        if ($this->status === 'expired') {
            return [
                'is_valid' => false,
                'reason' => 'TICKET_EXPIRED',
                'message' => 'انتهت صلاحية التذكرة / Ticket expired'
            ];
        }

        if ($this->check_in_count >= $this->max_check_ins) {
            return [
                'is_valid' => false,
                'reason' => 'TICKET_ALREADY_USED',
                'message' => 'التذكرة مستخدمة من قبل / Ticket already used',
                'data' => [
                    'validated_at' => $this->used_at?->toIso8601String(),
                    'validated_by' => $this->validated_by
                ]
            ];
        }

        if ($this->booking_date && $this->booking_date < now()->startOfDay()) {
            return [
                'is_valid' => false,
                'reason' => 'TICKET_EXPIRED',
                'message' => 'انتهت صلاحية التذكرة / Ticket expired',
                'data' => [
                    'expiry_date' => $this->booking_date->toIso8601String()
                ]
            ];
        }

        if ($this->event && $this->event->end_time < now()) {
            return [
                'is_valid' => false,
                'reason' => 'EVENT_ENDED',
                'message' => 'انتهت الفعالية / Event has ended'
            ];
        }

        return [
            'is_valid' => true,
            'reason' => 'VALID',
            'message' => 'تذكرة صالحة / Valid ticket'
        ];
    }

    /**
     * Mark ticket as used/validated
     */
    public function markAsUsed(string $validatedBy = null, string $location = null, string $notes = null): bool
    {
        $this->check_in_count++;
        $this->validated_by = $validatedBy;
        $this->validation_location = $location;
        $this->validation_notes = $notes;

        // If this is the first check-in, set used_at
        if ($this->check_in_count === 1) {
            $this->used_at = now();
        }

        // If reached max check-ins, mark as used
        if ($this->check_in_count >= $this->max_check_ins) {
            $this->status = 'used';
        }

        return $this->save();
    }

    /**
     * TKT-YYYY-XXXXXXXX: 8 random Crockford base32 characters (no I, L, O,
     * U, so staff can type it from a screen). Nothing is read first, so
     * simultaneous checkouts never compute the same number and there is no
     * yearly ceiling; a rare duplicate hits the unique index and
     * TicketIssuer retries with a new one. Unique platform-wide.
     */
    public static function generateTicketNumber(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, 31)];
        }

        return sprintf('TKT-%d-%s', now()->year, $code);
    }

    /**
     * Boot method to auto-generate ticket number and QR code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateTicketNumber();
            }

            // QR payload = ticket number + random suffix: human readable for
            // gate staff, but not guessable/forgeable from the sequence.
            if (empty($ticket->qr_code)) {
                $ticket->qr_code = $ticket->ticket_number.'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(12));
            }
        });
    }
}