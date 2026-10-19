<?php

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\MapLocation;
use App\Models\Tenant;
use App\Support\TenantTime;
use Illuminate\Support\Str;

/**
 * Compact, localized facts about tenant data for the model (tool results)
 * and the app (cards). Prices / times only ever come from here.
 */
class EventFacts
{
    public function __construct(private Tenant $tenant, private string $locale) {}

    public function locale(): string
    {
        return $this->locale;
    }

    public function tr(object $model, string $field): ?string
    {
        $ar = $model->{$field.'_ar'} ?? null;
        $en = $model->{$field} ?? null;

        return $this->locale === 'ar' ? ($ar ?: $en) : ($en ?: $ar);
    }

    public function priceMinor(Event $event): int
    {
        return $event->unitPriceMinor(null, $this->tenant->currencyDecimals());
    }

    public function isFree(Event $event): bool
    {
        if ($this->priceMinor($event) > 0) {
            return false;
        }
        foreach ((array) ($event->pricing ?? []) as $tier) {
            if (\App\Support\Money::toMinor((string) ($tier['price'] ?? 0), $this->tenant->currencyDecimals()) > 0) {
                return false;
            }
        }

        return true;
    }

    public function money(int $minor): string
    {
        return $this->tenant->formatMoney($minor);
    }

    /** Amount with the currency code first, the product standard: "OMR 12.450". */
    public function amount(int $minor): string
    {
        return $this->tenant->currency().' '.$this->money($minor);
    }

    public function venue(Event $event): ?string
    {
        $venue = $this->tr($event, 'location');
        if (! $venue && $event->relationLoaded('mapLocation') && $event->mapLocation) {
            $venue = $this->tr($event->mapLocation, 'name');
        }

        return $venue ?: null;
    }

    public function isMultiDay(Event $event): bool
    {
        return $event->start_time && $event->end_time
            && $event->start_time->toDateString() !== $event->end_time->toDateString();
    }

    /**
     * Opening window of an event on a date (tenant wall clock, "H:i"), or
     * null when the event does not run that day. Multi-day events are
     * taken to run daily from their start time to their end time.
     *
     * @return array{0:string,1:string}|null
     */
    public function windowOn(Event $event, string $date): ?array
    {
        if (! $event->start_time || ! $event->end_time) {
            return null;
        }
        $first = $event->start_time->toDateString();
        $last = $event->end_time->toDateString();
        if ($date < $first || $date > $last) {
            return null;
        }
        $open = $event->start_time->format('H:i');
        $close = $event->end_time->format('H:i');
        if ($first !== $last && $close <= $open) {
            $close = '23:59';
        }

        return [$open, $close];
    }

    /** Tool-result facts (what the model may quote). */
    public function event(Event $event, bool $detailed = false): array
    {
        $price = $this->priceMinor($event);
        $facts = [
            'id' => $event->id,
            'title' => $this->tr($event, 'title'),
            'category' => $event->category ? $this->tr($event->category, 'name') : null,
            'venue' => $this->venue($event),
            'start' => TenantTime::iso($event->start_time, $this->tenant),
            'end' => TenantTime::iso($event->end_time, $this->tenant),
            'multi_day' => $this->isMultiDay($event),
            'is_free' => $this->isFree($event),
            'price_minor' => $price,
            'price' => $this->amount($price),
            'featured' => (bool) $event->is_featured,
            'description' => Str::limit(trim(strip_tags((string) $this->tr($event, 'description'))), $detailed ? 1200 : 220),
        ];
        if ($facts['multi_day']) {
            $facts['daily_hours'] = $event->start_time->format('H:i').'–'.($event->end_time->format('H:i') > $event->start_time->format('H:i') ? $event->end_time->format('H:i') : '23:59');
        }
        if (is_array($event->pricing) && $event->pricing) {
            $facts['ticket_types'] = array_values(array_map(fn ($t) => [
                'type' => (string) ($t['type'] ?? ''),
                'price' => $this->amount(\App\Support\Money::toMinor((string) ($t['price'] ?? 0), $this->tenant->currencyDecimals())),
            ], array_filter($event->pricing, 'is_array')));
        }
        if ($detailed) {
            $facts['organizer'] = $this->tr($event, 'organizer_name');
            $facts['tickets_per_day'] = $event->total_tickets === -1 ? 'unlimited' : $event->total_tickets;
            if ((int) $event->deposit_minor > 0) {
                $facts['deposit'] = $this->amount((int) $event->deposit_minor).' ('.($event->deposit_type ?: 'deposit').', refundable)';
            }
            if ($event->mapLocation) {
                $facts['map_location_id'] = $event->mapLocation->id;
            }
        }

        return $facts;
    }

    /** Contract card: {type, id, title, start, end, venue, price_minor, price, image_url, is_free}. */
    public function eventCard(Event $event): array
    {
        $price = $this->priceMinor($event);

        return [
            'type' => 'event',
            'id' => $event->id,
            'title' => $this->tr($event, 'title'),
            'start' => TenantTime::iso($event->start_time, $this->tenant),
            'end' => TenantTime::iso($event->end_time, $this->tenant),
            'venue' => $this->venue($event),
            'price_minor' => $price,
            'price' => $this->money($price),
            'image_url' => Tenant::assetUrl($event->image_url),
            'is_free' => $this->isFree($event),
        ];
    }

    public function location(MapLocation $loc): array
    {
        return [
            'id' => $loc->id,
            'name' => $this->tr($loc, 'name'),
            'category' => $loc->type,
            'category_label' => $loc->getTypeTranslation($this->locale),
            'description' => Str::limit(trim(strip_tags((string) $this->tr($loc, 'description'))), 200),
            'lat' => $loc->latitude !== null ? (float) $loc->latitude : null,
            'lng' => $loc->longitude !== null ? (float) $loc->longitude : null,
        ];
    }

    /** Contract card: {type, id, name, lat, lng, category}. */
    public function locationCard(MapLocation $loc): array
    {
        return [
            'type' => 'location',
            'id' => $loc->id,
            'name' => $this->tr($loc, 'name'),
            'lat' => $loc->latitude !== null ? (float) $loc->latitude : null,
            'lng' => $loc->longitude !== null ? (float) $loc->longitude : null,
            'category' => $loc->type,
        ];
    }
}
