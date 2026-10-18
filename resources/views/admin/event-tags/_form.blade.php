@php
    $tagEventOptions = $events->mapWithKeys(fn ($ev) => [$ev->id => $ev->title_ar ?: $ev->title])->all();
@endphp
<x-admin.form-field name="event_id" id="event_id" label="الفعالية" type="select" required placeholder="اختر الفعالية"
    :value="(string) old('event_id', $eventTag->event_id ?? ($selectedEventId ?? ''))" :options="$tagEventOptions"
    help="يظهر الوسم على بطاقة هذه الفعالية في التطبيق" />

<ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">{{ __('العربية') }}</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
    </li>
</ul>
<div class="tab-content border border-top-0 p-3" id="myTabContent">
    <div class="tab-pane fade show active" id="ar" role="tabpanel">
        <x-admin.form-field name="tag_ar" id="tag_ar" label="اسم الوسم (العربية)" maxlength="50" required
            :value="old('tag_ar', $eventTag->tag_ar ?? '')" help="50 حرفًا كحد أقصى" />
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel" dir="ltr">
        <x-admin.form-field name="tag" id="tag" label="Tag Name (English)" maxlength="50" dir="ltr" required
            :value="old('tag', $eventTag->tag ?? '')" help="Up to 50 characters, unique per event" />
    </div>
</div>
