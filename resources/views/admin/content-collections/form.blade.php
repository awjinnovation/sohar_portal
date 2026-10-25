@extends('layouts.admin')
@php
    $isEdit = $collection->exists;
    $pageIcon = 'collection';
    $breadcrumbs = ['المحتوى' => null, 'المجموعات' => route('admin.content-collections.index'), ($isEdit ? 'تعديل مجموعة' : 'إضافة مجموعة') => null];
    $layouts = [
        'list' => ['قائمة', 'view_list', 'صفوف بأيقونة وعنوان، مناسبة للخدمات'],
        'grid' => ['شبكة', 'grid_view', 'مربعات بصور، مناسبة للمعالم والأنشطة'],
        'logos' => ['شعارات', 'workspace_premium', 'شريط شعارات، مناسب للشركاء والرعاة'],
        'cards' => ['بطاقات', 'view_agenda', 'بطاقات بنص قصير، مناسبة للأدلة والنصائح'],
    ];
    $layoutVal = old('layout', $collection->layout ?: 'list');
@endphp
@section('title', $isEdit ? 'تعديل مجموعة' : 'إضافة مجموعة')
@section('page-title', $isEdit ? 'تعديل مجموعة' : 'إضافة مجموعة')
@section('page-actions')
    @if($isEdit)<a href="{{ route('admin.content-items.index', $collection) }}" class="btn btn-tonal"><i class="bi bi-list-ul"></i> {{ __('العناصر') }}</a>@endif
    <a href="{{ route('admin.content-collections.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    <form action="{{ $isEdit ? route('admin.content-collections.update', $collection) : route('admin.content-collections.store') }}" method="POST">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="المجموعة" icon="translate">
                    <x-admin.lang-tabs id="collection">
                        <x-slot:ar>
                            <x-admin.form-field name="name[ar]" id="name_ar" label="الاسم" :value="old('name.ar', $collection->name['ar'] ?? '')" placeholder="مثل خدمات الزوار" />
                            <x-admin.form-field name="description[ar]" id="description_ar" label="الوصف" type="textarea" rows="2" :value="old('description.ar', $collection->description['ar'] ?? '')" />
                        </x-slot:ar>
                        <x-slot:en>
                            <x-admin.form-field name="name[en]" id="name" label="Name" :value="old('name.en', $collection->name['en'] ?? '')" placeholder="e.g. Visitor services" />
                            <x-admin.form-field name="description[en]" id="description" label="Description" type="textarea" rows="2" :value="old('description.en', $collection->description['en'] ?? '')" />
                        </x-slot:en>
                    </x-admin.lang-tabs>
                </x-admin.card>
                <x-admin.card title="طريقة العرض في التطبيق" icon="layout-wtf">
                    <div class="row g-2" role="radiogroup" aria-label="{{ __('طريقة العرض في التطبيق') }}">
                        @foreach($layouts as $key => [$label, $icon, $hint])
                            <div class="col-6 col-md-3">
                                <input type="radio" class="btn-check" name="layout" id="layout_{{ $key }}" value="{{ $key }}" @checked($layoutVal === $key)>
                                <label class="layout-opt" for="layout_{{ $key }}"><span class="msr">{{ $icon }}</span><strong>{{ __($label) }}</strong><span class="d-block small text-muted">{{ __($hint) }}</span></label>
                            </div>
                        @endforeach
                    </div>
                    @error('layout')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                <x-admin.card title="الإعدادات" icon="sliders">
                    <x-admin.form-field name="key" label="المفتاح" dir="ltr" :value="old('key', $collection->key)" placeholder="services" help="يُستخدم في الرابط، وفي home_blocks بصيغة collection:services مثلًا" />
                    <x-admin.icon-picker name="icon" :value="old('icon', $collection->icon)" fallback="list" label="الأيقونة" />
                    <x-admin.form-field name="sort" label="الترتيب" type="number" min="0" dir="ltr" :value="old('sort', $collection->sort)" />
                    <x-admin.toggle name="show_in_discover" label="تظهر في قسم اكتشف" :checked="old('show_in_discover', $collection->show_in_discover)" />
                    <x-admin.toggle name="is_active" label="نشطة" description="المجموعة المتوقفة لا تظهر في التطبيق" :checked="old('is_active', $collection->is_active)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ المجموعة') }}</button>
                            <a href="{{ route('admin.content-collections.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection

@include('admin.discover._assets')
