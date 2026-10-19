@extends('layouts.admin')
@php
    $isEdit = $item->exists;
    $pageIcon = 'collection';
    $isLogos = $collection->layout === 'logos';
    $breadcrumbs = ['المحتوى' => null, 'المجموعات' => route('admin.content-collections.index'), $collection->t('name') => route('admin.content-items.index', $collection), ($isEdit ? 'تعديل عنصر' : 'إضافة عنصر') => null];
    $v = fn (string $field, string $lang) => old($field.'.'.$lang, $item->{$field}[$lang] ?? '');
    $image = \App\Support\MediaPresenter::image($item->getFirstMedia('image'));
    $mode = old('location_mode', $item->place_id ? 'place' : ($item->latitude !== null ? 'own' : 'none'));
@endphp
@section('title', $isEdit ? 'تعديل عنصر' : 'إضافة عنصر')
@section('page-title', $isEdit ? 'تعديل عنصر' : 'إضافة عنصر')
@section('page-subtitle', $collection->t('name'))
@section('page-actions')
    <a href="{{ route('admin.content-items.index', $collection) }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    <form action="{{ $isEdit ? route('admin.content-items.update', [$collection, $item]) : route('admin.content-items.store', $collection) }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="المحتوى" icon="translate">
                    <x-admin.lang-tabs id="item">
                        <x-slot:ar>
                            <x-admin.form-field name="title[ar]" id="title_ar" label="العنوان" :value="$v('title', 'ar')" :placeholder="$isLogos ? 'اسم الشريك' : 'مثل مكتب التذاكر'" />
                            <x-admin.form-field name="subtitle[ar]" id="subtitle_ar" label="العنوان الفرعي" :value="$v('subtitle', 'ar')" />
                            <x-admin.rich-text name="body[ar]" id="body_ar" label="النص" :value="$v('body', 'ar')" dir="rtl" rows="6" />
                            <x-admin.form-field name="link_label[ar]" id="link_label_ar" label="نص زر الرابط" :value="$v('link_label', 'ar')" placeholder="مزيد من المعلومات" />
                        </x-slot:ar>
                        <x-slot:en>
                            <x-admin.form-field name="title[en]" id="title" label="Title" :value="$v('title', 'en')" />
                            <x-admin.form-field name="subtitle[en]" id="subtitle" label="Subtitle" :value="$v('subtitle', 'en')" />
                            <x-admin.rich-text name="body[en]" id="body" label="Text" :value="$v('body', 'en')" dir="ltr" rows="6" />
                            <x-admin.form-field name="link_label[en]" id="link_label" label="Link button text" :value="$v('link_label', 'en')" placeholder="More info" />
                        </x-slot:en>
                    </x-admin.lang-tabs>
                </x-admin.card>

                <x-admin.card title="الموقع" icon="geo-alt" subtitle="اربط العنصر بمكان من الأماكن، أو حدّد موقعه على الخريطة">
                    <div class="btn-group mb-3 flex-wrap" role="radiogroup" data-location-mode aria-label="{{ __('الموقع') }}">
                        <input type="radio" class="btn-check" name="location_mode" id="lm_none" value="none" @checked($mode === 'none')>
                        <label class="btn btn-outline-secondary" for="lm_none">{{ __('بلا موقع') }}</label>
                        <input type="radio" class="btn-check" name="location_mode" id="lm_place" value="place" @checked($mode === 'place')>
                        <label class="btn btn-outline-secondary" for="lm_place"><i class="bi bi-pin-map"></i> {{ __('مكان مرتبط') }}</label>
                        <input type="radio" class="btn-check" name="location_mode" id="lm_own" value="own" @checked($mode === 'own')>
                        <label class="btn btn-outline-secondary" for="lm_own"><i class="bi bi-geo-alt"></i> {{ __('موقع على الخريطة') }}</label>
                    </div>
                    <div data-mode-pane="place" class="{{ $mode === 'place' ? '' : 'd-none' }}">
                        <x-admin.form-field name="place_id" label="المكان" type="select" placeholder="اختر مكانًا" :options="$places->mapWithKeys(fn ($p) => [$p->id => $p->t('name').($p->status !== 'published' ? ' · '.__('مسودة') : '')])->all()" :value="old('place_id', $item->place_id)" help="يأخذ العنصر موقع المكان، ويظهر في التطبيق رابط إلى صفحته" />
                    </div>
                    <div data-mode-pane="own" class="{{ $mode === 'own' ? '' : 'd-none' }}">
                        <x-admin.location-picker :lat="$item->latitude" :lng="$item->longitude" :center="$center" :others="$others" />
                    </div>
                    <div data-mode-pane="none" class="{{ $mode === 'none' ? '' : 'd-none' }}"><p class="text-muted small mb-0">{{ __('لن يظهر للعنصر موقع في التطبيق.') }}</p></div>
                </x-admin.card>

                <x-admin.card title="الصور" icon="images">
                    <div data-image-field>
                        <x-admin.file-upload name="image" :label="$isLogos ? 'الشعار' : 'الصورة'" :current="$image['thumb_url'] ?? null" accept="image/jpeg,image/png,image/webp,image/gif" :help="$isLogos ? 'PNG أو WebP بخلفية شفافة، ولا يُقص الشعار' : 'JPG أو PNG أو WebP، حتى 8 ميغابايت'" icon="image" />
                        @if($image)
                            <div class="form-check mt-n2 mb-3">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image" data-remove-image>
                                <label class="form-check-label small" for="remove_image">{{ __('حذف الصورة') }}</label>
                            </div>
                        @endif
                    </div>
                    <x-admin.media-gallery :media="$item->exists ? $item->getMedia('gallery') : collect()" />
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="النشر" icon="broadcast">
                    <x-admin.status-select :value="$item->status" />
                    <x-admin.toggle name="featured" label="عنصر مميز" description="يظهر أولًا في المجموعة" :checked="old('featured', $item->featured)" />
                    <div class="row">
                        <x-admin.form-field col="col-6" name="sort" label="الترتيب" type="number" min="0" dir="ltr" :value="old('sort', $item->sort)" />
                        <x-admin.form-field col="col-6" name="slug" label="المعرّف في الرابط" dir="ltr" :value="old('slug', $item->slug)" />
                    </div>
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ العنصر') }}</button>
                            <a href="{{ route('admin.content-items.index', $collection) }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>

                <x-admin.card title="التواصل والروابط" icon="link-45deg">
                    <x-admin.icon-picker name="icon" :value="old('icon', $item->icon)" :fallback="$collection->icon ?: 'article'" label="الأيقونة" help="تظهر عندما لا توجد صورة" />
                    <x-admin.form-field name="link_url" label="الرابط" type="url" dir="ltr" :value="old('link_url', $item->link_url)" placeholder="https://" />
                    <x-admin.form-field name="phone" label="الهاتف" dir="ltr" :value="old('phone', $item->phone)" />
                    <x-admin.form-field name="email" label="البريد الإلكتروني" type="email" dir="ltr" :value="old('email', $item->email)" />
                    <x-admin.form-field name="tags" label="الوسوم" :value="old('tags', implode('، ', (array) $item->tags))" help="يُفصل بينها بفاصلة، ويصفّي بها التطبيق العناصر" />
                </x-admin.card>

                @include('admin.discover._copilot', [
                    'kind' => 'item', 'permission' => 'manage content',
                    'fields' => ['title', 'subtitle', 'body', 'link_label'],
                    'describe' => ['title' => 'title', 'title_ar' => 'title_ar', 'collection' => 'collection_name', 'subtitle' => 'subtitle_ar', 'notes' => 'body_ar'],
                    'out' => ['summary_ar' => 'subtitle_ar', 'summary' => 'subtitle', 'description_ar' => 'body_ar', 'description' => 'body'],
                ])
                <input type="hidden" id="collection_name" value="{{ $collection->t('name') }}">
            </div>
        </div>
    </form>
@endsection

@include('admin.discover._assets')
