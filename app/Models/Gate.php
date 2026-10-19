<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A physical entrance/exit of the venue (contract §B1).
 *
 * @property array $name {en, ar}
 * @property string $direction entry|exit|both
 * @property array|null $ticket_types null = every type
 */
class Gate extends Model
{
    use \App\Tenancy\BelongsToTenant;

    public const DIRECTIONS = ['entry', 'exit', 'both'];

    protected $fillable = ['name', 'code', 'event_id', 'direction', 'allow_reentry', 'ticket_types', 'active', 'sort_order'];

    protected $casts = [
        'name' => 'array',
        'ticket_types' => 'array',
        'allow_reentry' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function devices()
    {
        return $this->hasMany(GateDevice::class);
    }

    public function scans()
    {
        return $this->hasMany(GateScan::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = $this->name ?? [];

        return (string) ($name[$locale] ?? $name['en'] ?? $name['ar'] ?? $this->code);
    }

    /** Direction a scan at this gate is counted in, given what the device asked for. */
    public function resolveDirection(?string $requested): string
    {
        return match ($this->direction) {
            'exit' => 'out',
            'entry' => 'in',
            default => in_array($requested, ['out', 'exit'], true) ? 'out' : 'in',
        };
    }

    public function acceptsTicketType(?string $type): bool
    {
        $types = array_values(array_filter((array) ($this->ticket_types ?? [])));

        return $types === [] || in_array((string) $type, $types, true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => ['en' => $this->name['en'] ?? '', 'ar' => $this->name['ar'] ?? ''],
            'code' => $this->code,
            'event_id' => $this->event_id,
            'direction' => $this->direction,
            'allow_reentry' => (bool) $this->allow_reentry,
            'ticket_types' => $this->ticket_types ?: null,
            'active' => (bool) $this->active,
        ];
    }
}
