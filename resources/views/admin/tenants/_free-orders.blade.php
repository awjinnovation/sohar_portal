{{-- Free ticket types (docs/ticket_types_contract.md §9) inside admin/tenants/form. Prices are set per type (Events → edit → «أنواع التذاكر»). --}}
<x-admin.card id="sec-free-orders" title="التذاكر المجانية" icon="ticket-perforated" subtitle="أنواع التذاكر التي سعرها 0 تصدر للزائر دون دفع">
    <div class="row">
        @include('admin.tenants._toggle', ['key' => 'tickets.free_orders', 'label' => 'السماح بالطلبات المجانية', 'col' => 'col-12', 'description' => 'عند التفعيل يطلب الزائر أنواع التذاكر المجانية من التطبيق وموقع الحجز، ويصدرها موظفو البوابات، فتصدر التذاكر فورًا دون دفع. عند الإيقاف لا تُباع إلا التذاكر المدفوعة، فتُخفى الأنواع المجانية ويُرفض طلبها، وتبقى التذاكر المجانية الصادرة من قبل صالحة.'])
    </div>
    <p class="form-text mb-0"><i class="bi bi-lightbulb"></i> {{ __('لجعل نوع مجانيًا يومًا واحدًا: اجعل سعره 0، واضبط فترة بيعه على ذلك اليوم، ثم فعّل هذا الخيار، وأوقفه بعد انقضاء اليوم.') }}</p>
</x-admin.card>
