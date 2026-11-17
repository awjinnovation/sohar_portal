<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Photo of a card terminal receipt for a gate box office order
 * (docs/gate_sales_contract.md §9), with what the server read from it and
 * its verdict. The image lives on the private `local` disk. A `manual`
 * receipt has no photo: staff confirmed the payment by hand (the photo is
 * optional unless `gate_sales.pos_receipt_photo_required`), with the
 * approval code and card digits they typed, if any; it always needs review.
 *
 * @property string $verdict verified|review|rejected
 * @property string $source photo|manual
 */
class BoxOfficeReceipt extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const VERIFIED = 'verified';

    /** Could not be checked fully (unreadable field, AI unavailable): a person decides. */
    public const REVIEW = 'review';

    public const REJECTED = 'rejected';

    public const SOURCE_PHOTO = 'photo';

    /** Confirmed by staff without a photo. */
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'order_id', 'device_id', 'staff_user_id', 'idempotency_key', 'source', 'note', 'disk', 'path', 'sha256', 'mime', 'size',
        'verdict', 'reasons', 'ai_status', 'ai_model', 'extracted', 'amount_minor', 'receipt_date', 'terminal_id',
        'approval_code', 'rrn', 'card_last4', 'accepted', 'rrn_key', 'auth_key', 'duplicate_of_order_id',
        'decision', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'reasons' => 'array',
        'extracted' => 'array',
        'amount_minor' => 'integer',
        'size' => 'integer',
        'receipt_date' => 'date',
        'accepted' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function hasPhoto(): bool
    {
        return $this->source !== self::SOURCE_MANUAL && $this->path !== null && $this->path !== '';
    }

    public function order()
    {
        return $this->belongsTo(BoxOfficeOrder::class, 'order_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(BoxOfficeOrder::class, 'duplicate_of_order_id');
    }

    /** Receipt in the gate API (docs/gate_sales_contract.md §9), texts in the request language. */
    public function toApi(?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::find($this->tenant_id);
        $f = $this->extracted ?? [];

        return [
            'id' => $this->id,
            'verdict' => $this->verdict,
            'verdict_text' => __('box_office.receipt_verdicts.'.$this->verdict),
            'accepted' => (bool) $this->accepted,
            'source' => $this->source ?: self::SOURCE_PHOTO,
            'has_photo' => $this->hasPhoto(),
            'reasons' => array_map(fn ($r) => [
                'code' => (string) ($r['code'] ?? ''),
                'level' => (string) ($r['level'] ?? 'review'),
                'text' => __('box_office.receipt_reasons.'.($r['code'] ?? ''), (array) ($r['data'] ?? [])),
            ] + (isset($r['data']) ? ['data' => $r['data']] : []), $this->reasons ?? []),
            'amount_minor' => $this->amount_minor,
            'amount' => $this->amount_minor !== null && $tenant ? $tenant->formatMoney($this->amount_minor) : null,
            'currency' => $f['currency'] ?? null,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'receipt_time' => $f['time'] ?? null,
            'terminal_id' => $this->terminal_id,
            'approval_code' => $this->approval_code,
            'rrn' => $this->rrn,
            'card_last4' => $this->card_last4,
            'card_scheme' => $f['card_scheme'] ?? null,
            'duplicate_of' => $this->duplicate_of_order_id
                ? ['order_id' => $this->duplicate_of_order_id, 'reference' => BoxOfficeOrder::withoutTenantScope()->whereKey($this->duplicate_of_order_id)->value('reference')]
                : null,
            'ai' => $this->ai_status,
            'note' => $this->note,
            'decision' => $this->decision,
            'created_at' => \App\Support\TenantTime::iso($this->created_at, $tenant),
        ];
    }

    /** @return list<string> */
    public function reasonCodes(): array
    {
        return array_values(array_map(fn ($r) => (string) ($r['code'] ?? ''), $this->reasons ?? []));
    }
}
