{{-- Satisfaction survey «راضي» settings of a tenant (docs/radi_satisfaction_contract.md §1) inside admin/tenants/form. The module switch (radi) is in the Modules card. --}}
<x-admin.card id="sec-radi" title="راضي لقياس رضا الزوار" icon="emoji-smile" subtitle="استبيان قصير في التطبيق يقيس رضا الزوار، وتظهر نتائجه في صفحة رضا الزوار">
    <div class="row">
        @include('admin.tenants._field', ['key' => 'radi.title.ar', 'label' => 'عنوان الاستبيان بالعربية', 'col' => 'col-md-6'])
        @include('admin.tenants._field', ['key' => 'radi.title.en', 'label' => 'عنوان الاستبيان بالإنجليزية', 'col' => 'col-md-6', 'ltr' => true])
        @include('admin.tenants._field', ['key' => 'radi.intro.ar', 'label' => 'المقدمة بالعربية', 'col' => 'col-md-6'])
        @include('admin.tenants._field', ['key' => 'radi.intro.en', 'label' => 'المقدمة بالإنجليزية', 'col' => 'col-md-6', 'ltr' => true])
        @include('admin.tenants._field', ['key' => 'radi.logo_url', 'label' => 'رابط شعار راضي أو مساره', 'col' => 'col-md-6', 'ltr' => true, 'help' => 'اتركه فارغًا ليعرض التطبيق أيقونة محايدة حتى يصل الشعار الرسمي.'])
        @include('admin.tenants._field', ['key' => 'radi.external_url', 'label' => 'رابط الاستبيان الرسمي', 'col' => 'col-md-6', 'ltr' => true, 'placeholder' => 'https://', 'help' => 'اختياري، ويظهر في التطبيق زرًا ثانويًا يفتح الاستبيان الرسمي في المتصفح.'])
        @include('admin.tenants._json', ['key' => 'radi.aspects', 'label' => 'الجوانب التي يقيّمها الزائر', 'rows' => 8, 'help' => '[{"key": "cleanliness", "label": {"en": "Cleanliness"}}]'])
    </div>
</x-admin.card>
