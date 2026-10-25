<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A "Book a demo" lead from the public landing page. Platform-level: not
 * tenant-scoped (no BelongsToTenant), visible to super-admins only.
 */
class DemoRequest extends Model
{
    public const STATUSES = ['new', 'contacted', 'closed'];

    public const EVENT_TYPES = ['festival', 'expo', 'concerts', 'mall', 'governorate', 'sports', 'other'];

    public const VISITOR_RANGES = ['lt5k', '5k-25k', '25k-100k', 'gt100k'];

    public const LOCALES = ['ar', 'en'];

    protected $fillable = [
        'name', 'organization', 'event_type', 'expected_visitors', 'season',
        'contact', 'contact_email', 'notes', 'locale', 'ip_hash', 'user_agent',
        'status', 'mailed_at',
    ];

    protected $attributes = [
        'status' => 'new',
        'locale' => 'en',
    ];

    protected function casts(): array
    {
        return [
            'mailed_at' => 'datetime',
        ];
    }

    public static function hashIp(?string $ip): ?string
    {
        return $ip ? hash('sha256', $ip.'|'.config('app.key')) : null;
    }

    public function eventTypeLabel(?string $locale = null): string
    {
        return $this->event_type ? __('landing.event_types.'.$this->event_type, [], $locale ?? $this->locale) : '—';
    }

    public function visitorsLabel(?string $locale = null): string
    {
        return $this->expected_visitors ? __('landing.visitor_ranges.'.$this->expected_visitors, [], $locale ?? $this->locale) : '—';
    }

    /** Contact is a phone number (no email given). */
    public function contactPhone(): ?string
    {
        return $this->contact_email ? null : $this->contact;
    }

    /** E.164-ish digits for tel:/WhatsApp links. */
    public function contactPhoneDigits(): ?string
    {
        $phone = $this->contactPhone();

        return $phone ? preg_replace('/[^\d+]/', '', $phone) : null;
    }

    public function submittedAtLocal(): ?\Carbon\CarbonInterface
    {
        return $this->created_at?->copy()->setTimezone(config('landing.timezone', 'Asia/Muscat'));
    }

    /** Plain-text summary (form labels in the requester's language). */
    public function summary(?string $locale = null): string
    {
        $locale ??= $this->locale;
        $l = fn (string $k) => __('landing.fields.'.$k, [], $locale);
        $lines = [
            __('landing.summary_head', [], $locale), '',
            $l('name').': '.$this->name,
            $l('organization').': '.$this->organization,
            $l('event_type').': '.$this->eventTypeLabel($locale),
            $l('expected_visitors').': '.$this->visitorsLabel($locale),
        ];
        if ($this->season) {
            $lines[] = $l('season').': '.$this->season;
        }
        // Keep phone numbers left-to-right inside Arabic text (LRI … PDI).
        $lines[] = $l('contact').': '.($locale === 'ar' ? "\u{2066}".$this->contact."\u{2069}" : $this->contact);
        if ($this->notes) {
            $lines[] = $l('notes').': '.$this->notes;
        }

        return implode("\n", $lines);
    }
}
