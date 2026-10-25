@extends('layouts.admin')
@php
    $isEdit = $place->exists;
    $pageIcon = 'pin-map';
    $breadcrumbs = ['المحتوى' => null, 'الأماكن' => route('admin.places.index'), ($isEdit ? 'تعديل مكان' : 'إضافة مكان') => null];
    $v = fn (string $field, string $lang) => old($field.'.'.$lang, $place->{$field}[$lang] ?? '');
    $tenantDecimals = ($currentTenant ?? $brand)?->currencyDecimals() ?? 3;
    $fee = old('entry_fee', $place->entry_fee_minor === null ? '' : \App\Support\Money::format($place->entry_fee_minor, $tenantDecimals));
    $cover = \App\Support\MediaPresenter::image($place->getFirstMedia('cover'));
    $selectedCategory = $categories->firstWhere('id', (int) old('category_id', $place->category_id));
    $linked = collect(old('events', $place->exists ? $place->events->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp
@section('title', $isEdit ? 'تعديل مكان' : 'إضافة مكان')
@section('page-title', $isEdit ? 'تعديل مكان' : 'إضافة مكان')
@section('page-subtitle', $isEdit ? $place->t('name') : 'مكان للزيارة يظهر في قسم اكتشف وعلى خريطة التطبيق')
@section('page-actions')
    @if($isEdit && $place->status === 'published')
        <a href="{{ url('/discover/places/'.$place->slug).'?tenant='.($currentTenant->slug ?? '') }}" class="btn btn-tonal" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('صفحة المشاركة') }}</a>
    @endif
    <a href="{{ route('admin.places.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')
    @include('admin.discover._module-notice', ['module' => 'places'])

    <form action="{{ $isEdit ? route('admin.places.update', $place) : route('admin.places.store') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="المحتوى" icon="translate" subtitle="يظهر النص بلغة الزائر، ويُعرض النص العربي إذا لم تتوفر الترجمة">
                    <x-admin.lang-tabs id="place">
                        <x-slot:ar>
                            <x-admin.form-field name="name[ar]" id="name_ar" label="الاسم" :value="$v('name', 'ar')" placeholder="مثل قلعة صحار" />
                            <x-admin.form-field name="summary[ar]" id="summary_ar" label="نبذة قصيرة" type="textarea" rows="2" :value="$v('summary', 'ar')" maxlength="200" help="جملة واحدة حتى 200 حرف تظهر في البطاقات" />
                            <x-admin.rich-text name="description[ar]" id="description_ar" label="الوصف" :value="$v('description', 'ar')" dir="rtl" help="فقرات قصيرة، واكتب ما تعرفه من معلومات موثقة فقط" />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="wilayat[ar]" id="wilayat_ar" label="الولاية" :value="$v('wilayat', 'ar')" placeholder="مثل صحار" />
                                <x-admin.form-field col="col-md-6" name="address[ar]" id="address_ar" label="العنوان" :value="$v('address', 'ar')" />
                                <x-admin.form-field col="col-md-6" name="opening_hours[ar]" id="opening_hours_ar" label="مواعيد الزيارة" :value="$v('opening_hours', 'ar')" placeholder="مثل يوميًا من 8:00 إلى 18:00" help="اتركه فارغًا إذا لم تكن المواعيد معروفة" />
                                <x-admin.form-field col="col-md-6" name="link_label[ar]" id="link_label_ar" label="نص زر الرابط" :value="$v('link_label', 'ar')" placeholder="مزيد من المعلومات" />
                            </div>
                        </x-slot:ar>
                        <x-slot:en>
                            <x-admin.form-field name="name[en]" id="name" label="Name" :value="$v('name', 'en')" placeholder="e.g. Sohar Fort" />
                            <x-admin.form-field name="summary[en]" id="summary" label="Short summary" type="textarea" rows="2" :value="$v('summary', 'en')" maxlength="200" />
                            <x-admin.rich-text name="description[en]" id="description" label="Description" :value="$v('description', 'en')" dir="ltr" />
                            <div class="row">
                                <x-admin.form-field col="col-md-6" name="wilayat[en]" id="wilayat" label="Wilayat" :value="$v('wilayat', 'en')" placeholder="e.g. Sohar" />
                                <x-admin.form-field col="col-md-6" name="address[en]" id="address" label="Address" :value="$v('address', 'en')" />
                                <x-admin.form-field col="col-md-6" name="opening_hours[en]" id="opening_hours" label="Opening hours" :value="$v('opening_hours', 'en')" placeholder="e.g. Daily 8:00–18:00" />
                                <x-admin.form-field col="col-md-6" name="link_label[en]" id="link_label" label="Link button text" :value="$v('link_label', 'en')" placeholder="More info" />
                            </div>
                        </x-slot:en>
                    </x-admin.lang-tabs>
                </x-admin.card>

                <x-admin.card title="الموقع على الخريطة" icon="geo-alt" subtitle="انقر على الخريطة أو اسحب الدبوس، وتظهر الأماكن الأخرى بلون باهت">
                    <x-admin.location-picker :lat="$place->latitude" :lng="$place->longitude" :center="$center" :others="$others" :color="$selectedCategory?->color" />
                </x-admin.card>

                <x-admin.card title="الصور" icon="images" subtitle="صورة الغلاف تظهر في البطاقات وأعلى صفحة المكان">
                    <div data-image-field>
                        <x-admin.file-upload name="cover" label="صورة الغلاف" :current="$cover['thumb_url'] ?? null" accept="image/jpeg,image/png,image/webp,image/gif" help="JPG أو PNG أو WebP، حتى 8 ميغابايت، ويُفضّل العرض الأفقي" icon="image" />
                        @if($cover)
                            <div class="form-check mt-n2 mb-3">
                                <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="remove_cover" data-remove-image>
                                <label class="form-check-label small" for="remove_cover">{{ __('حذف صورة الغلاف') }}</label>
                            </div>
                        @endif
                    </div>
                    <x-admin.media-gallery :media="$place->exists ? $place->getMedia('gallery') : collect()" />
                </x-admin.card>

                <x-admin.card title="الفعاليات في هذا المكان" icon="calendar-event" subtitle="تظهر الفعاليات القادمة منها في صفحة المكان بالتطبيق">
                    <label for="events" class="form-label">{{ __('الفعاليات المرتبطة') }}</label>
                    <select name="events[]" id="events" class="form-select" multiple size="8">
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" @selected(in_array($event->id, $linked, true))>{{ app()->getLocale() === 'en' ? ($event->title ?: $event->title_ar) : ($event->title_ar ?: $event->title) }} · {{ $event->start_time?->format('Y-m-d') }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('اضغط Ctrl أو Cmd لاختيار أكثر من فعالية.') }}</div>
                </x-admin.card>
            </div>

            <div class="col-xl-4">
                <x-admin.card title="النشر" icon="broadcast">
                    <x-admin.status-select :value="$place->status" />
                    <x-admin.toggle name="featured" label="مكان مميز" description="يظهر في شريط الأماكن المميزة في الرئيسية وقسم اكتشف" :checked="old('featured', $place->featured)" />
                    <div class="row">
                        <x-admin.form-field col="col-6" name="sort" label="الترتيب" type="number" min="0" :value="old('sort', $place->sort)" dir="ltr" />
                        <x-admin.form-field col="col-6" name="slug" label="المعرّف في الرابط" :value="old('slug', $place->slug)" dir="ltr" placeholder="sohar-fort" />
                    </div>
                    @if($isEdit && $place->published_at)
                        <div class="form-text">{{ __('تاريخ النشر') }}: <span class="num">{{ $place->published_at->format('Y-m-d H:i') }}</span></div>
                    @endif
                    <x-slot:footer>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ المكان') }}</button>
                            <a href="{{ route('admin.places.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                        </div>
                    </x-slot:footer>
                </x-admin.card>

                <x-admin.card title="الفئة والأيقونة" icon="tags">
                    <div class="field">
                        <label for="category_id" class="form-label">{{ __('الفئة') }}</label>
                        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" data-category-select>
                            <option value="" data-icon="place" data-color="#2F8E80">{{ __('بلا فئة') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" data-icon="{{ $cat->icon }}" data-color="{{ $cat->color }}" @selected((int) old('category_id', $place->category_id) === $cat->id)>{{ $cat->t('name') }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($categories->isEmpty())<div class="form-text"><a href="{{ route('admin.place-categories.create') }}">{{ __('أضف فئة أولًا') }}</a></div>@endif
                    </div>
                    <x-admin.icon-picker name="icon" :value="old('icon', $place->icon)" :fallback="$selectedCategory?->icon ?? 'place'" :color="$selectedCategory?->color" label="أيقونة خاصة" help="اتركها فارغة لاستخدام أيقونة الفئة" />
                </x-admin.card>

                <x-admin.card title="التفاصيل" icon="info-circle">
                    <x-admin.form-field name="link_url" label="رابط خارجي" type="url" dir="ltr" :value="old('link_url', $place->link_url)" placeholder="https://" />
                    <x-admin.form-field name="phone" label="الهاتف" dir="ltr" :value="old('phone', $place->phone)" placeholder="+968 …" />
                    <x-admin.form-field name="entry_fee" label="رسوم الدخول" type="number" step="0.001" min="0" dir="ltr" :value="$fee" help="بالريال العماني، واكتب 0 للدخول المجاني، واتركه فارغًا إذا لم تكن الرسوم معروفة" />
                </x-admin.card>

                <x-admin.card title="معاينة البطاقة" icon="phone">
                    <div class="dc-card" data-preview-card style="--pc: {{ $selectedCategory?->color ?? '#2F8E80' }}"
                         data-preview-map='{"title":["name_ar","name"],"sub":["summary_ar","summary"],"chip":["wilayat_ar","wilayat"]}'>
                        <div class="dc-media">
                            <span class="msr" data-pv-icon>{{ $place->displayIcon() }}</span>
                            <img src="{{ $cover['thumb_url'] ?? '' }}" alt="" data-pv-img class="{{ $cover ? '' : 'd-none' }}">
                        </div>
                        <div class="dc-body">
                            <span class="dc-chip" data-pv="chip"></span>
                            <div class="dc-title" data-pv="title"></div>
                            <div class="dc-sub" data-pv="sub"></div>
                        </div>
                    </div>
                </x-admin.card>

                @include('admin.discover._copilot', [
                    'kind' => 'place', 'permission' => 'manage places',
                    'fields' => ['name', 'summary', 'description', 'wilayat', 'address', 'opening_hours', 'link_label'],
                    'describe' => ['title' => 'name', 'title_ar' => 'name_ar', 'category' => '#category_id option:checked', 'wilayat' => 'wilayat_ar', 'address' => 'address_ar', 'opening_hours' => 'opening_hours_ar', 'notes' => 'description_ar'],
                    'out' => ['summary_ar' => 'summary_ar', 'summary' => 'summary', 'description_ar' => 'description_ar', 'description' => 'description'],
                ])
            </div>
        </div>
    </form>
@endsection

@include('admin.discover._assets')
