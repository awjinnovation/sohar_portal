<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\HtmlSanitizer;
use App\Support\Localized;
use App\Support\MaterialIcons;
use App\Support\MediaPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

/**
 * Shared helpers of the places / content admin forms: localized {en, ar}
 * inputs (`name[ar]`, `name[en]`), sanitized rich text, icon names and the
 * media fields (single image, gallery uploads, gallery order, removals).
 */
trait DiscoverForm
{
    use TenantValidation;

    public const IMAGE_RULE = 'image|mimes:jpeg,jpg,png,webp,gif|max:8192';

    /** Rules for a localized field; $required = at least one language. */
    protected function localizedRules(string $field, int $max, bool $required = false): array
    {
        $rules = fn (string $other) => array_merge($required ? ['required_without:'.$field.'.'.$other] : [], ['nullable', 'string', 'max:'.$max]);

        return [
            $field => [$required ? 'required' : 'nullable', 'array'],
            "$field.ar" => $rules('en'),
            "$field.en" => $rules('ar'),
        ];
    }

    protected function iconRules(string $field = 'icon'): array
    {
        return [$field => ['nullable', 'string', 'max:64', 'regex:'.MaterialIcons::PATTERN]];
    }

    protected function localized(Request $request, string $field): ?array
    {
        return Localized::fromInput($request->input($field));
    }

    protected function localizedHtml(Request $request, string $field): ?array
    {
        $pair = Localized::fromInput($request->input($field), fn ($v) => HtmlSanitizer::clean($v));

        return $pair && array_filter($pair) ? $pair : null;
    }

    /**
     * Apply the media inputs of a form:
     *  - "<single>" file → replaces the single-file collection; "remove_<single>" = 1 clears it;
     *  - "gallery[]" files → appended; "gallery_order[]" ids → order; "remove_media[]" ids → deleted.
     * Only media of this model are touched.
     */
    protected function syncMedia(Request $request, HasMedia&Model $model, ?string $single, bool $gallery = true): void
    {
        if ($single) {
            if ($request->boolean('remove_'.$single)) {
                $model->clearMediaCollection($single);
            }
            if ($request->hasFile($single)) {
                MediaPresenter::rememberSize($model->addMediaFromRequest($single)->toMediaCollection($single));
            }
        }

        if (! $gallery) {
            return;
        }

        $remove = array_map('intval', (array) $request->input('remove_media', []));
        if ($remove) {
            $model->getMedia('gallery')->whereIn('id', $remove)->each->delete();
        }

        foreach ((array) $request->file('gallery', []) as $file) {
            MediaPresenter::rememberSize($model->addMedia($file)->toMediaCollection('gallery'));
        }

        $order = array_values(array_filter(array_map('intval', (array) $request->input('gallery_order', []))));
        if ($order) {
            $own = $model->getMedia('gallery')->pluck('id')->all();
            $ids = array_values(array_intersect($order, $own));
            // Newly uploaded items (not in the posted order) keep coming after.
            $ids = array_merge($ids, array_values(array_diff($own, $ids)));
            \Spatie\MediaLibrary\MediaCollections\Models\Media::setNewOrder($ids);
        }
        $model->unsetRelation('media');
    }

    protected function galleryRules(): array
    {
        return [
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => ['file', ...explode('|', self::IMAGE_RULE)],
            'gallery_order' => ['nullable', 'array'],
            'gallery_order.*' => ['integer'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
        ];
    }

    /**
     * Rewrite the `sort` column of the posted ids in the posted order, reusing
     * their current sort values (so reordering one page of a long list keeps
     * its position relative to the other pages).
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function reorderRows(Request $request, string $modelClass, array $scope = [])
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $rows = $modelClass::query()->where($scope)->whereIn('id', $ids)->get()->keyBy('id');
        $slots = $rows->pluck('sort')->sort()->values()->all();
        if (count(array_unique($slots)) !== count($slots)) {
            $slots = range(1, count($slots)); // collisions (e.g. all 0): renumber
            $min = (int) ($rows->min('sort') ?? 0);
            $slots = array_map(fn ($n) => $min + $n, $slots);
        }
        $i = 0;
        foreach ($ids as $id) {
            if ($row = $rows->get($id)) {
                $row->forceFill(['sort' => $slots[$i++]])->saveQuietly();
            }
        }

        return response()->json(['success' => true, 'message' => __('حُفظ الترتيب'), 'data' => ['count' => $i]]);
    }
}
