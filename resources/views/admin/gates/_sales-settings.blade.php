{{-- Gate box office settings of a tenant (docs/gate_sales_contract.md §1) inside admin/tenants/form. Which gates sell is set per gate (Gates → edit → «بيع التذاكر»). --}}
<x-admin.card id="sec-gate-sales" title="مبيعات البوابات" icon="cart-check" subtitle="يبيع موظفو البوابات التذاكر ويرسلون إلى الزائر رابط دفع برمز QR أو رسالة نصية">
    <div class="row">
        @include('admin.tenants._toggle', ['key' => 'gate_sales.enabled', 'label' => 'تفعيل البيع عند البوابات', 'col' => 'col-md-6', 'description' => 'يظهر خيار بيع التذاكر في تطبيق البوابة للبوابات المسموح لها'])
        @include('admin.tenants._toggle', ['key' => 'gate_sales.send_sms', 'label' => 'إرسال الرابط برسالة نصية', 'col' => 'col-md-6', 'description' => 'القيمة الافتراضية عند إنشاء الطلب، ويمكن للموظف تغييرها'])
        @include('admin.tenants._field', ['key' => 'gate_sales.link_ttl_minutes', 'label' => 'صلاحية رابط الدفع بالدقائق', 'type' => 'number', 'col' => 'col-md-4', 'ltr' => true, 'help' => 'تبقى التذاكر محجوزة للزائر خلال هذه المدة'])
        @include('admin.tenants._field', ['key' => 'gate_sales.max_quantity', 'label' => 'أقصى عدد للتذاكر في الطلب', 'type' => 'number', 'col' => 'col-md-4', 'ltr' => true])
        @include('admin.tenants._toggle', ['key' => 'gate_sales.allow_future_dates', 'label' => 'البيع لأيام لاحقة', 'col' => 'col-md-4', 'description' => 'يبيع الموظف تذاكر لأيام قادمة من الفعالية، لا لليوم فقط'])
        <div class="col-12"><h3 class="h6 fw-bold mt-2 mb-0">{{ __('الدفع بجهاز البطاقة') }}</h3></div>
        @include('admin.tenants._toggle', ['key' => 'gate_sales.pos_enabled', 'label' => 'الدفع بجهاز البطاقة عند البوابة', 'col' => 'col-md-6', 'description' => 'يخصم الموظف المبلغ على جهاز نقاط البيع، ثم يصوّر الإيصال في تطبيق البوابة، فيقرأ النظام الإيصال ويطابقه مع الطلب ويتحقق من عدم تكراره قبل إصدار التذاكر.'])
        @include('admin.tenants._toggle', ['key' => 'gate_sales.pos_accept_unverified', 'label' => 'قبول الإيصال الذي يتعذّر فحصه كاملًا', 'col' => 'col-md-6', 'description' => 'إذا تعذّرت قراءة بعض بيانات الإيصال أو تعذّر الفحص الآلي، تصدر التذاكر ويُعلَّم الطلب للمراجعة. عند إيقافه ينتظر الطلب اعتماد المشرف من لوحة التحكم. يُرفض دائمًا الإيصال المكرر أو الذي يخالف مبلغه مبلغ الطلب.'])
        @include('admin.tenants._field', ['key' => 'gate_sales.pos_terminal_ids', 'label' => 'أرقام أجهزة البطاقة', 'col' => 'col-12', 'ltr' => true, 'help' => 'رقم الجهاز مطبوع على الإيصال بجوار TID. افصل بين الأرقام بفاصلة. يُرفض الإيصال الصادر من جهاز غير مدرج. اترك الحقل فارغًا لقبول أي جهاز.'])
        @include('admin.tenants._field', ['key' => 'messages.box_office_sms.ar', 'label' => 'نص رسالة الدفع بالعربية', 'col' => 'col-12', 'help' => 'المتغيرات :app و:tickets و:event و:amount و:minutes و:url و:reference'])
        @include('admin.tenants._field', ['key' => 'messages.box_office_sms.en', 'label' => 'نص رسالة الدفع بالإنجليزية', 'col' => 'col-12', 'ltr' => true])
    </div>
</x-admin.card>
