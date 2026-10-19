<?php

namespace App\Models\Concerns;

use App\Support\Localized;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Shared behaviour of places / content rows:
 * - JSON columns keep Arabic readable (JSON_UNESCAPED_UNICODE), so LIKE
 *   searches on the raw column and DB dumps work in both languages;
 * - t('name') resolves a {en, ar} field in the request locale;
 * - draft | published status (published_at set on first publish; a future
 *   published_at keeps the row hidden until then);
 * - unique slugs per tenant (or per parent for collection items).
 */
trait LocalizedContent
{
    public const STATUSES = ['draft', 'published'];

    public static function bootLocalizedContent(): void
    {
        static::saving(function ($model) {
            if (in_array('status', $model->getFillable(), true) && $model->status === 'published' && ! $model->published_at) {
                $model->published_at = now();
            }
        });
    }

    protected function asJson($value, $flags = 0)
    {
        return json_encode($value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function t(string $field, ?string $locale = null): ?string
    {
        return Localized::text($this->getAttribute($field), $locale);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'published')
            ->where(fn ($q) => $q->whereNull($this->qualifyColumn('published_at'))->orWhere($this->qualifyColumn('published_at'), '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && (! $this->published_at || $this->published_at->lte(now()));
    }

    /** Search the {en, ar} JSON columns (works on sqlite and mysql). */
    public function scopeSearchLocalized(Builder $query, string $term, array $fields): Builder
    {
        $terms = array_slice(array_filter(preg_split('/\s+/u', trim($term)) ?: []), 0, 5);
        if (! $terms) {
            return $query;
        }

        return $query->where(function ($w) use ($terms, $fields) {
            foreach ($terms as $t) {
                // Wildcards typed by users are dropped (sqlite LIKE has no default ESCAPE).
                $like = '%'.str_replace(['%', '_'], '', $t).'%';
                foreach ($fields as $field) {
                    $w->orWhere($this->qualifyColumn($field), 'like', $like);
                }
            }
        });
    }

    /**
     * A slug unique within $scope (column => value), from the English text,
     * else the Arabic text transliterated by Str::slug, else "<prefix>-<n>".
     */
    public static function uniqueSlug(?string $wanted, array $names, array $scope, ?int $ignoreId = null, string $prefix = 'item'): string
    {
        $base = Str::slug((string) $wanted);
        foreach ([$names['en'] ?? null, $names['ar'] ?? null] as $name) {
            if ($base === '' && $name) {
                $base = Str::slug($name);
            }
        }
        $base = Str::limit($base !== '' ? $base : $prefix, 100, '');
        $base = trim($base, '-') ?: $prefix;

        $slug = $base;
        $n = 2;
        while (static::withoutGlobalScopes()->where($scope)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
