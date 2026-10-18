<?php

namespace App\Http\Resources\Wallet;

use App\Tenancy\TenantContext;

trait Money
{
    protected function fmt(int $minor): string
    {
        return app(TenantContext::class)->get()?->formatMoney($minor) ?? \App\Support\Money::format($minor);
    }

    /** ['x_minor' => int, 'x' => "1.500"] */
    protected function money(string $name, int $minor): array
    {
        return [$name.'_minor' => $minor, $name => $this->fmt($minor)];
    }

    protected function localized(mixed $value): ?string
    {
        if (! is_array($value)) {
            return $value;
        }
        $locale = app()->getLocale();

        return $value[$locale] ?? $value['en'] ?? $value['ar'] ?? null;
    }
}
