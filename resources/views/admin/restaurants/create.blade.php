@extends('layouts.admin')

@section('title', 'إضافة مطعم جديد')
@section('page-title', 'إضافة مطعم جديد')
@php
    $pageIcon = 'cup-hot';
    $breadcrumbs = ['المحتوى' => null, 'المطاعم والأكشاك' => route('admin.restaurants.index'), 'إضافة مطعم' => null];
    $priceOptions = ['$' => '$ - اقتصادي', '$$' => '$$ - متوسط', '$$$' => '$$$ - مرتفع', '$$$$' => '$$$$ - فاخر'];
@endphp
@section('page-actions')
    <a href="{{ route('admin.restaurants.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.restaurants.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="معلومات المطعم" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي بحسب لغة الزائر">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#arabic-content" type="button" role="tab">{{ __('العربية') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#english-content" type="button" role="tab">English</button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3">
                        <div class="tab-pane fade show active" id="arabic-content" role="tabpanel">
                            <x-admin.form-field name="name_ar" id="name_ar" label="اسم المطعم" :value="old('name_ar')" required />
                            <x-admin.form-field name="description_ar" id="description_ar" label="الوصف" type="textarea" rows="3" :value="old('description_ar')" required />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="cuisine_ar" id="cuisine_ar" label="نوع المطبخ" :value="old('cuisine_ar')" required />
                                <x-admin.form-field col="col-md-6" name="location_ar" id="location_ar" label="الموقع" :value="old('location_ar')" required />
                            </div>
                        </div>
                        <div class="tab-pane fade" id="english-content" role="tabpanel" dir="ltr">
                            <x-admin.form-field name="name" id="name" label="Restaurant Name" :value="old('name')" required />
                            <x-admin.form-field name="description" id="description" label="Description" type="textarea" rows="3" :value="old('description')" required />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="cuisine" id="cuisine" label="Cuisine Type" :value="old('cuisine')" required />
                                <x-admin.form-field col="col-md-6" name="location" id="location" label="Location" :value="old('location')" required />
                            </div>
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="التواصل والموقع" icon="telephone">
                    <div class="row">
                        <x-admin.form-field col="col-md-6" name="phone" id="phone" label="الهاتف" dir="ltr" :value="old('phone')" />
                        <x-admin.form-field col="col-md-6" name="website" id="website" label="الموقع الإلكتروني" type="url" dir="ltr" placeholder="https://" :value="old('website')" />
                        <x-admin.form-field col="col-md-6" name="latitude" id="latitude" label="خط العرض" type="number" step="0.00000001" min="-90" max="90" dir="ltr" :value="old('latitude')" />
                        <x-admin.form-field col="col-md-6" name="longitude" id="longitude" label="خط الطول" type="number" step="0.00000001" min="-180" max="180" dir="ltr" :value="old('longitude')" />
                    </div>
                    <x-admin.form-field name="image_url" id="image_url" label="رابط الصورة" type="url" dir="ltr" placeholder="https://" :value="old('image_url')"
                        help="رابط صورة الغلاف التي تظهر في قائمة المطاعم." />
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="الإعدادات" icon="sliders">
                    <x-admin.form-field name="price_range" id="price_range" label="مستوى السعر" type="select" placeholder="اختر المستوى" :options="$priceOptions" :value="old('price_range')" required />
                    <x-admin.toggle name="is_active" id="is_active" label="نشط" description="يظهر للزوار في التطبيق" :checked="old('is_active', true)" />
                    <x-admin.toggle name="is_featured" id="is_featured" label="مميز" description="يظهر في أعلى القائمة" :checked="old('is_featured')" />
                    <x-admin.toggle name="is_open" id="is_open" label="مفتوح الآن" :checked="old('is_open', true)" />
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ المطعم') }}</button>
                            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
