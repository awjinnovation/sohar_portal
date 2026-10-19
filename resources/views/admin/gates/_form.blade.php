@php
    $L = \App\Services\Gate\GateLabels::class;
    $eventOptions = $events->mapWithKeys(fn ($e) => [$e->id => (app()->getLocale() === 'ar' ? ($e->title_ar ?: $e->title) : $e->title).' · '.$e->start_time?->format('Y-m-d')])->all();
@endphp
<div class="row gx-4">
    <div class="col-xl-8">
        <x-admin.card title="البوابة" icon="door-open" subtitle="يظهر الاسم على شاشة جهاز المسح وفي لوحة البوابات">
            <div class="row">
                <x-admin.form-field col="col-md-6" name="name_ar" label="الاسم بالعربية" :value="old('name_ar', $gate->name['ar'] ?? '')" required />
                <x-admin.form-field col="col-md-6" name="name_en" label="الاسم بالإنجليزية" dir="ltr" :value="old('name_en', $gate->name['en'] ?? '')" required />
                <x-admin.form-field col="col-md-4" name="code" label="الرمز" dir="ltr" :value="old('code', $gate->code)" required help="حروف وأرقام، مثل A أو EXIT1" />
                <x-admin.form-field col="col-md-4" name="direction" label="الاتجاه" type="select" :value="old('direction', $gate->direction ?? 'entry')"
                    :options="$L::GATE_DIRECTIONS" required />
                <x-admin.form-field col="col-md-4" name="sort_order" label="الترتيب" type="number" min="0" :value="old('sort_order', $gate->sort_order ?? 0)" />
            </div>
        </x-admin.card>

        <x-admin.card title="قواعد الدخول" icon="shield-check" subtitle="تُطبّق عند كل مسح، متصلًا أو دون اتصال">
            <div class="row">
                <x-admin.form-field col="col-md-6" name="event_id" label="الفعالية" type="select" placeholder="كل فعاليات اليوم"
                    :value="(string) old('event_id', $gate->event_id)" :options="$eventOptions" help="بوابة مخصصة لفعالية واحدة ترفض تذاكر الفعاليات الأخرى" />
                <x-admin.form-field col="col-md-6" name="ticket_types" label="أنواع التذاكر المقبولة" dir="ltr"
                    :value="old('ticket_types', implode(', ', (array) ($gate->ticket_types ?? [])))" placeholder="vip, family" help="اتركه فارغًا لقبول كل الأنواع، أو اكتب مثلًا vip لبوابة كبار الشخصيات" />
            </div>
            <x-admin.toggle name="allow_reentry" id="allow_reentry" label="السماح بإعادة الدخول" description="تذكرة استُخدمت اليوم يمكنها الدخول مجددًا من هذه البوابة" :checked="old('allow_reentry', $gate->allow_reentry)" />
        </x-admin.card>
    </div>

    <div class="col-xl-4">
        <x-admin.card title="الحالة" icon="sliders">
            <x-admin.toggle name="active" id="active" label="البوابة مفتوحة" description="البوابة المغلقة ترفض المسح من أجهزتها" :checked="old('active', $gate->active ?? true)" />
            <x-slot:footer>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ') }}</button>
                    <a href="{{ route('admin.gates.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</div>
