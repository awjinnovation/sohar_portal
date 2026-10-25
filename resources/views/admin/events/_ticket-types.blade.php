{{-- Ticket types of an event (docs/ticket_types_contract.md §3): list with drag sort, templates, modal editor, per-type sales.
     Lives inside the event form; it talks JSON to admin.events.ticket-types.* and never submits the form. Needs $event. --}}
@php
    $ttCurrency = $event->currency ?: \App\View\Fmt::currency();
    $ttCategories = [
        'general' => ['عامة', 'ticket-perforated'], 'family' => ['عائلية', 'people'], 'vip' => ['كبار الشخصيات', 'gem'],
        'child' => ['أطفال', 'balloon'], 'senior' => ['كبار السن', 'person-walking'], 'student' => ['طلاب', 'mortarboard'],
        'group' => ['مجموعات', 'people-fill'], 'other' => ['أخرى', 'tag'],
    ];
    $ttTemplates = [
        'general' => ['key' => 'general', 'name_ar' => 'تذكرة عامة', 'name_en' => 'General admission', 'category' => 'general', 'admits' => 1],
        'family' => ['key' => 'family', 'name_ar' => 'تذكرة عائلية', 'name_en' => 'Family', 'category' => 'family', 'admits' => 4, 'per_order_max' => 5,
            'description_ar' => 'تدخل بها عائلة واحدة حتى أربعة أشخاص.', 'description_en' => 'Admits up to 4 people of one family.'],
        'vip' => ['key' => 'vip', 'name_ar' => 'كبار الشخصيات', 'name_en' => 'VIP', 'category' => 'vip', 'admits' => 1, 'per_order_max' => 6],
        'child' => ['key' => 'child', 'name_ar' => 'تذكرة طفل', 'name_en' => 'Child', 'category' => 'child', 'admits' => 1, 'max_age' => 11,
            'description_ar' => 'للأطفال دون 12 سنة.', 'description_en' => 'For children under 12.'],
        'senior' => ['key' => 'senior', 'name_ar' => 'تذكرة كبار السن', 'name_en' => 'Senior', 'category' => 'senior', 'admits' => 1, 'min_age' => 60, 'requires_id' => true,
            'description_ar' => 'للزوار من عمر 60 سنة فأكثر، ويُطلب إبراز البطاقة الشخصية.', 'description_en' => 'For visitors aged 60 and over. Bring an ID card.'],
        'student' => ['key' => 'student', 'name_ar' => 'تذكرة طالب', 'name_en' => 'Student', 'category' => 'student', 'admits' => 1, 'requires_id' => true,
            'description_ar' => 'تُطلب بطاقة طالب سارية عند البوابة.', 'description_en' => 'A valid student ID is checked at the gate.'],
    ];
    $ttConfig = [
        'list' => route('admin.events.ticket-types.index', $event),
        'base' => route('admin.events.ticket-types.store', $event),
        'reorder' => route('admin.events.ticket-types.reorder', $event),
        'currency' => $ttCurrency,
        'locale' => app()->getLocale(),
        'categories' => collect($ttCategories)->map(fn ($c) => ['label' => __($c[0]), 'icon' => $c[1]]),
        'colors' => \App\Models\EventTicketType::CATEGORY_COLORS,
        'templates' => $ttTemplates,
        'types' => app(\App\Http\Controllers\Admin\EventTicketTypeController::class)->list($event),
        't' => [
            'admits' => __('يدخل بها :count'), 'boxOffice' => __('عند البوابة فقط'), 'inactive' => __('موقوف'), 'id' => __('تُطلب الهوية'),
            'ageMax' => __('حتى عمر :age'), 'ageMin' => __('من عمر :age'), 'sold' => __('المبيع'), 'left' => __('المتبقي'), 'revenue' => __('الإيرادات'),
            'unlimited' => __('غير محدود'), 'free' => __('مجانية'), 'edit' => __('تعديل'), 'delete' => __('حذف'), 'confirmDelete' => __('سيُحذف نوع التذكرة نهائيًا.'),
            'cannotDelete' => __('بيعت تذاكر من هذا النوع، فيمكن إيقافه ولا يمكن حذفه.'), 'newType' => __('نوع تذكرة جديد'), 'editType' => __('تعديل نوع التذكرة'),
            'saved' => __('حُفظ نوع التذكرة'), 'error' => __('تعذّر الحفظ. راجع الحقول وأعد المحاولة.'), 'salesClosed' => __('البيع مغلق الآن'), 'empty' => __('لا توجد أنواع تذاكر'),
            'perOrder' => __('الحد في الطلب :count'), 'capacity' => __('السعة :count'),
        ],
    ];
@endphp
<x-admin.card id="ticket-types" title="أنواع التذاكر" icon="ticket-perforated" subtitle="لكل نوع سعره وقواعده، ويختار الزائر عدد التذاكر من كل نوع. يسري تغيير السعر على الطلبات الجديدة فقط.">
    <x-slot:actions>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i> {{ __('إضافة نوع') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">{{ __('من قالب') }}</h6></li>
                @foreach($ttTemplates as $tk => $tpl)
                    <li><button class="dropdown-item d-flex align-items-center gap-2" type="button" data-tt-template="{{ $tk }}">
                        <i class="bi bi-{{ $ttCategories[$tpl['category']][1] }}" style="color: {{ \App\Models\EventTicketType::CATEGORY_COLORS[$tpl['category']] }}"></i>{{ app()->getLocale() === 'ar' ? $tpl['name_ar'] : $tpl['name_en'] }}</button></li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item d-flex align-items-center gap-2" type="button" data-tt-template=""><i class="bi bi-file-earmark-plus"></i>{{ __('نوع فارغ') }}</button></li>
            </ul>
        </div>
    </x-slot:actions>
    <div class="tt-list" id="ttList" aria-live="polite"></div>
    <p class="form-text mb-0 mt-2"><i class="bi bi-arrows-move"></i> {{ __('اسحب الأنواع لترتيبها كما تظهر للزائر. يظهر أول نوع عام نشط سعرًا أساسيًا للفعالية.') }}</p>
    <script type="application/json" id="ttConfig">@json($ttConfig, JSON_UNESCAPED_UNICODE)</script>
</x-admin.card>

@push('styles')
<style>
    .tt-list { display: grid; gap: 10px; }
    .tt-item { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border: 1px solid var(--hairline, #E5E7EB); border-radius: 14px; background: var(--surface, #fff); }
    .tt-item.is-off { opacity: .6; }
    .tt-item.sortable-ghost { opacity: .35; }
    .tt-drag { cursor: grab; color: var(--muted, #6B7280); font-size: 18px; padding: 4px; }
    .tt-ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 19px; flex: none; }
    .tt-main { flex: 1; min-width: 0; }
    .tt-name { font-weight: 700; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .tt-name code { font-size: 11.5px; }
    .tt-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
    .tt-stats { display: grid; grid-template-columns: repeat(3, auto); gap: 2px 16px; text-align: center; font-size: 12px; color: var(--muted, #6B7280); }
    .tt-stats b { display: block; color: var(--text, #111827); font-size: 14px; }
    .tt-price { font-weight: 800; white-space: nowrap; min-width: 92px; text-align: end; }
    @media (max-width: 767px) { .tt-item { flex-wrap: wrap; } .tt-stats { order: 5; width: 100%; grid-template-columns: repeat(3, 1fr); } }
</style>
@endpush

@push('scripts')
<div class="modal fade" id="ttModal" tabindex="-1" aria-labelledby="ttModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="ttModalTitle">{{ __('نوع تذكرة جديد') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('إغلاق') }}"></button>
            </div>
            <div class="modal-body" id="ttForm">
                <div class="alert alert-danger small d-none" id="ttError"></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="tt_name_ar">{{ __('الاسم بالعربية') }}<span class="req">*</span></label><input class="form-control" id="tt_name_ar" data-f="name_ar" maxlength="80"></div>
                    <div class="col-md-6"><label class="form-label" for="tt_name_en">{{ __('الاسم بالإنجليزية') }}<span class="req">*</span></label><input class="form-control" id="tt_name_en" data-f="name_en" dir="ltr" maxlength="80"></div>
                    <div class="col-md-6"><label class="form-label" for="tt_description_ar">{{ __('الوصف بالعربية') }}</label><textarea class="form-control" id="tt_description_ar" data-f="description_ar" rows="2" maxlength="300"></textarea></div>
                    <div class="col-md-6"><label class="form-label" for="tt_description_en">{{ __('الوصف بالإنجليزية') }}</label><textarea class="form-control" id="tt_description_en" data-f="description_en" rows="2" dir="ltr" maxlength="300"></textarea></div>
                    <div class="col-md-4"><label class="form-label" for="tt_key">{{ __('الرمز') }}<span class="req">*</span></label><input class="form-control font-monospace" id="tt_key" data-f="key" dir="ltr" maxlength="40" placeholder="family">
                        <div class="form-text">{{ __('أحرف إنجليزية صغيرة وأرقام، وتستعمله البوابات لقبول الأنواع') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="tt_category">{{ __('الفئة') }}</label>
                        <select class="form-select" id="tt_category" data-f="category">
                            @foreach($ttCategories as $ck => [$cl])<option value="{{ $ck }}">{{ __($cl) }}</option>@endforeach
                        </select></div>
                    <div class="col-md-4"><label class="form-label" for="tt_price">{{ __('السعر') }} <span class="text-muted small ltr">{{ $ttCurrency }}</span><span class="req">*</span></label><input class="form-control" id="tt_price" data-f="price" type="number" min="0" step="0.001" dir="ltr">
                        <div class="form-text">{{ __('السعر 0 يجعل النوع مجانيًا') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="tt_admits">{{ __('عدد الأشخاص بالتذكرة') }}</label><input class="form-control" id="tt_admits" data-f="admits" type="number" min="1" max="50" dir="ltr">
                        <div class="form-text">{{ __('تُمسح التذكرة العائلية لأربعة أشخاص أربع مرات') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="tt_capacity">{{ __('السعة اليومية') }}</label><input class="form-control" id="tt_capacity" data-f="capacity" type="number" min="0" dir="ltr" placeholder="{{ __('سعة الفعالية') }}">
                        <div class="form-text">{{ __('اتركها فارغة ليشارك النوع سعة الفعالية') }}</div></div>
                    <div class="col-md-4"><label class="form-label" for="tt_per_order_max">{{ __('الحد في الطلب') }}</label><input class="form-control" id="tt_per_order_max" data-f="per_order_max" type="number" min="1" max="100" dir="ltr"></div>
                    <div class="col-md-3"><label class="form-label" for="tt_min_age">{{ __('أدنى عمر') }}</label><input class="form-control" id="tt_min_age" data-f="min_age" type="number" min="0" max="120" dir="ltr"></div>
                    <div class="col-md-3"><label class="form-label" for="tt_max_age">{{ __('أقصى عمر') }}</label><input class="form-control" id="tt_max_age" data-f="max_age" type="number" min="0" max="120" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label" for="tt_visibility">{{ __('مكان البيع') }}</label>
                        <select class="form-select" id="tt_visibility" data-f="visibility">
                            <option value="public">{{ __('التطبيق والبوابات') }}</option>
                            <option value="box_office">{{ __('عند البوابة فقط') }}</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label" for="tt_sales_start">{{ __('بداية البيع') }}</label><input class="form-control" id="tt_sales_start" data-f="sales_start" type="datetime-local" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label" for="tt_sales_end">{{ __('نهاية البيع') }}</label><input class="form-control" id="tt_sales_end" data-f="sales_end" type="datetime-local" dir="ltr"></div>
                    <div class="col-md-6"><label class="form-label" for="tt_color">{{ __('اللون') }}</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" class="form-control form-control-color" id="tt_color" data-f="color" aria-label="{{ __('اللون') }}">
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="tt_color_default"><label class="form-check-label small" for="tt_color_default">{{ __('لون الفئة') }}</label></div>
                        </div></div>
                    <div class="col-md-6">
                        <label class="toggle-row mb-2" for="tt_requires_id">
                            <span class="tr-text"><span class="tr-label d-block">{{ __('يتحقق موظف البوابة من الهوية أو العمر') }}</span></span>
                            <span class="switch"><input type="checkbox" role="switch" id="tt_requires_id" data-f="requires_id"><span class="track"></span></span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="toggle-row" for="tt_is_active">
                            <span class="tr-text"><span class="tr-label d-block">{{ __('النوع نشط ومتاح للبيع') }}</span></span>
                            <span class="switch"><input type="checkbox" role="switch" id="tt_is_active" data-f="is_active"><span class="track"></span></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('إلغاء') }}</button>
                <button type="button" class="btn btn-primary" id="ttSave"><i class="bi bi-check-lg"></i> {{ __('حفظ') }}</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('js/admin-ticket-types.js') }}?v={{ @filemtime(public_path('js/admin-ticket-types.js')) }}"></script>
@endpush
