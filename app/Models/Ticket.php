<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number', 'event_id', 'user_id', 'transaction_id', 'booking_date', 'quantity', 'ticket_type', 'status', 'price', 'currency',
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
                'message' => 'التذكرة منتهية الصلاحية / Ticket expired'
            ];
        }

        if ($this->check_in_count >= $this->max_check_ins) {
            return [
                'is_valid' => false,
                'reason' => 'TICKET_ALREADY_USED',
                'message' => 'التذكرة مستخدمة بالفعل / Ticket already used',
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
                'message' => 'التذكرة منتهية الصلاحية / Ticket expired',
                'data' => [
                    'expiry_date' => $this->booking_date->toIso8601String()
                ]
            ];
        }

        if ($this->event && $this->event->end_time < now()) {
            return [
                'is_valid' => false,
                'reason' => 'EVENT_ENDED',
                'message' => 'الفعالية انتهت / Event has ended'
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
     * Generate unique ticket number
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT';
        $year = now()->year;

        // Get the last ticket number for this year
        $lastTicket = static::where('ticket_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('ticket_number', 'desc')
            ->first();

        if ($lastTicket) {
            // Extract the number and increment
            $lastNumber = (int) substr($lastTicket->ticket_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%d-%06d', $prefix, $year, $newNumber);
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

            if (empty($ticket->qr_code)) {
                $ticket->qr_code = $ticket->ticket_number;
            }
        });
    }
}