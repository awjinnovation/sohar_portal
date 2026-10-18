@php
    $categories = \App\Http\Controllers\Admin\VendorController::CATEGORIES;
    $restaurantOptions = $restaurants->mapWithKeys(fn ($r) => [$r->id => $r->name_ar ?: $r->name])->all();
@endphp
<div class="row gx-4">
    <div class="col-xl-8">
        <x-admin.card title="الاسم والوصف" icon="translate" subtitle="يظهر الاسم العربي أو الإنجليزي حسب لغة الزائر">
            <div class="row">
                <x-admin.form-field col="col-md-6" name="name_ar" label="الاسم بالعربية" :value="old('name_ar', $vendor->name['ar'] ?? '')" required />
                <x-admin.form-field col="col-md-6" name="name_en" label="الاسم بالإنجليزية" dir="ltr" :value="old('name_en', $vendor->name['en'] ?? '')" required />
                <x-admin.form-field col="col-md-6" name="description_ar" label="الوصف بالعربية" type="textarea" rows="3" :value="old('description_ar', $vendor->description['ar'] ?? '')" />
                <x-admin.form-field col="col-md-6" name="description_en" label="الوصف بالإنجليزية" type="textarea" rows="3" dir="ltr" :value="old('description_en', $vendor->description['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.card title="الشعار" icon="image">
            <x-admin.file-upload name="logo" :current="$vendor->logo_path ? $vendor->logoUrl() : null" accept="image/png,image/jpeg,image/webp" icon="shop"
                help="PNG أو JPG أو WEBP بحد أقصى 2 ميغابايت — يفضّل شعار مربع بخلفية شفافة" />
            @if($vendor->logo_path)
                <x-admin.toggle name="remove_logo" id="remove_logo" label="إزالة الشعار" description="يُحذف الشعار الحالي عند الحفظ (ما لم يتم رفع شعار جديد)" />
            @endif
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="الإعدادات" icon="sliders">
            <x-admin.form-field name="category" label="التصنيف" :value="old('category', $vendor->category)" list="vendorCategories" help="اختر من القائمة أو اكتب تصنيفًا جديدًا">
                <datalist id="vendorCategories">
                    @foreach($categories as $cv => $cl)
                        <option value="{{ $cv }}">{{ __($cl) }}</option>
                    @endforeach
                </datalist>
            </x-admin.form-field>
            <x-admin.form-field name="restaurant_id" label="ربط بمطعم (اختياري)" type="select" placeholder="— بدون —"
                :value="(string) old('restaurant_id', $vendor->restaurant_id)" :options="$restaurantOptions" />
            <x-admin.toggle name="is_active" id="is_active" label="نشط" description="يمكن للموظفين استقبال الدفع" :hidden="true" :checked="old('is_active', $vendor->is_active ?? true)" />
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ') }}</button>
                    <a href="{{ $vendor->exists ? route('admin.vendors.show', $vendor) : route('admin.vendors.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</div>
