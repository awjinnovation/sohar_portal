<?php

namespace App\Http\Resources\Discover;

use App\Support\HtmlSanitizer;
use App\Support\Localized;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base of the places / content resources (docs/places_content_contract.md
 * §3): every localized field is returned as {en, ar} plus `<field>_text`
 * resolved for the request language (Accept-Language → app locale, set by
 * ResolveTenant), falling back to the other language.
 */
abstract class LocalizedResource extends JsonResource
{
    /** ['name' => {en, ar}, 'name_text' => '…'] */
    public function loc(string $field, ?string $as = null, mixed $default = null): array
    {
        $as ??= $field;
        $value = $this->resource->getAttribute($field) ?? $default;

        return [$as => Localized::pair($value), $as.'_text' => Localized::text($value)];
    }

    /** Sanitized HTML {en, ar} + `_text` (sanitized again on output). */
    protected function html(string $field): array
    {
        $pair = array_map(fn ($v) => HtmlSanitizer::clean($v), Localized::pair($this->resource->getAttribute($field)));

        return [$field => $pair, $field.'_text' => Localized::text($pair)];
    }

    protected static function defaultLinkLabel(): array
    {
        return ['en' => 'More info', 'ar' => 'مزيد من المعلومات'];
    }
}
