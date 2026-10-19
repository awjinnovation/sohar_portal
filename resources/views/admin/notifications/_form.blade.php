@php
    $current = $notification ?? null;
    $userOptions = $users->mapWithKeys(fn ($u) => [$u->id => $u->name.' — '.($u->email ?: $u->phone_number ?: '#'.$u->id)])->all();
@endphp
<div class="row g-4">
    <div class="col-xl-8">
        <x-admin.card title="نص الإشعار" icon="translate" subtitle="يظهر النص العربي أو الإنجليزي بحسب لغة الزائر">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
                </li>
            </ul>
            <div class="tab-content border border-top-0 p-3">
                <div class="tab-pane fade show active" id="ar" role="tabpanel">
                    <x-admin.form-field name="title_ar" id="title_ar" label="العنوان" :value="old('title_ar', $current->title_ar ?? '')" required />
                    <x-admin.form-field name="body_ar" id="body_ar" label="نص الإشعار" type="textarea" rows="4" :value="old('body_ar', $current->body_ar ?? '')" required />
                </div>
                <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
                    <x-admin.form-field name="title" id="title" label="Title" dir="ltr" :value="old('title', $current->title ?? '')" required />
                    <x-admin.form-field name="body" id="body" label="Body" type="textarea" rows="4" dir="ltr" :value="old('body', $current->body ?? '')" required />
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="الإرسال" icon="send">
            <x-admin.form-field name="user_id" id="user_id" label="المستلم" type="select" placeholder="إشعار عام لجميع المستخدمين"
                :value="(string) old('user_id', $current->user_id ?? '')" :options="$userOptions"
                help="اتركه فارغًا ليصل الإشعار إلى جميع مستخدمي التطبيق." />
            <x-admin.form-field name="type" id="type" label="النوع" dir="ltr" maxlength="50" :value="old('type', $current->type ?? 'general')" />
            <x-admin.toggle name="is_public" id="is_public" label="إشعار عام" description="يظهر لجميع المستخدمين" :hidden="true" :checked="old('is_public', $current->is_public ?? false)" />
            <x-admin.toggle name="is_read" id="is_read" label="مقروء" description="تعليم الإشعار مقروءًا" :hidden="true" :checked="old('is_read', $current->is_read ?? false)" />
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __($submitLabel ?? 'حفظ') }}</button>
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</div>
