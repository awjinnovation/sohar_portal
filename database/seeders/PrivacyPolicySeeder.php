<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PrivacyPolicy;

class PrivacyPolicySeeder extends Seeder
{
    public const TITLE = 'Privacy Policy';

    public const TITLE_AR = 'سياسة الخصوصية';

    /**
     * Generic legal template filled from the tenant's settings (name,
     * organization, support e-mail/phone). Idempotent: an existing page of
     * the tenant (possibly edited in the admin) is left untouched.
     */
    public function run(): void
    {
        $tenant = app(\App\Tenancy\TenantContext::class)->get();

        PrivacyPolicy::firstOrCreate(['title' => self::TITLE], [
            'title_ar' => self::TITLE_AR,
            'content' => LegalTemplate::fill($this->getEnglishContent(), $tenant),
            'content_ar' => LegalTemplate::fill(self::arabicTemplate(), $tenant),
            'is_active' => true,
        ]);
    }

    private function getEnglishContent(): string
    {
        return <<<HTML
<h2>Privacy Policy for {app_en}</h2>
<p><strong>Managed by {org_en}</strong></p>
<p><em>Last Updated: October 2026</em></p>

<h3>1. Introduction</h3>
<p>Welcome to {app_en}. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our mobile application and services. {app_en} is managed by the {org_en} ({org_ar}).</p>

<h3>2. Information We Collect</h3>
<h4>2.1 Personal Information</h4>
<ul>
    <li>Phone number (for OTP authentication)</li>
    <li>Name and email address (optional)</li>
    <li>Profile information you provide</li>
</ul>

<h4>2.2 Usage Information</h4>
<ul>
    <li>Events you view and favorite</li>
    <li>Tickets you purchase</li>
</ul>
<p>When you allow it, the app uses your location on your device only, to show where you are on the map. It is not sent to our servers.</p>

<h3>3. How We Use Your Information</h3>
<p>We use the collected information to:</p>
<ul>
    <li>Provide and maintain festival services</li>
    <li>Process ticket purchases and bookings</li>
    <li>Send important notifications about events and updates</li>
    <li>Improve app functionality and user experience</li>
    <li>Ensure security and prevent fraud</li>
    <li>Comply with legal obligations</li>
</ul>

<h3>4. Information Sharing</h3>
<p>We do not sell your personal information. We may share information with:</p>
<ul>
    <li>Government entities as required by law</li>
    <li>Service providers who assist in operations</li>
    <li>Event organizers for ticket validation</li>
    <li>OpenAI, our AI service provider, only when you use the AI assistant (see section 5)</li>
</ul>

<h3>5. AI Assistant</h3>
<p>The AI assistant and "Plan my visit" are optional. When you use them, we send the following to OpenAI, our AI service provider, so it can prepare the reply:</p>
<ul>
    <li>The messages you type and the earlier messages in the same conversation</li>
    <li>The app language and the event or screen you asked from</li>
    <li>If you are signed in and ask about them: the event, date, status and quantity of your tickets in this app, and your wallet balance and points</li>
    <li>For "Plan my visit": the date and times, the number of adults and children and the children's ages, and your interests, budget and notes</li>
</ul>
<p>We do not send your name, phone number or email address. Each request carries a coded identifier that does not reveal who you are, which OpenAI uses only to detect abuse. We keep your conversations in our system so you can return to them. Please do not type sensitive personal information, such as ID or bank card numbers or health details. If you do not want this data sent, do not use these two features; the rest of the app works without them.</p>

<h3>6. Data Security</h3>
<p>We implement appropriate security measures to protect your information. However, no electronic transmission over the internet is 100% secure.</p>

<h3>7. Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access your personal information</li>
    <li>Request correction of inaccurate data</li>
    <li>Request deletion of your account</li>
    <li>Opt-out of marketing communications</li>
</ul>

<h3>8. Children's Privacy</h3>
<p>The app is intended for users aged 13 and above. We do not knowingly collect information from children under 13.</p>

<h3>9. Changes to Privacy Policy</h3>
<p>We may update this Privacy Policy periodically. Continued use of the app after changes constitutes acceptance of the updated policy.</p>

<h3>10. Contact Us</h3>
<p>For questions about this Privacy Policy, please contact:</p>
<p><strong>{org_en}</strong><br>
{app_en} Management<br>
Email: {email}</p>
HTML;
    }

    /** Arabic template, also read by `content:sync-arabic`. */
    public static function arabicTemplate(): string
    {
        return <<<HTML
<h2>سياسة الخصوصية لتطبيق {app_ar}</h2>
<p><strong>بإدارة {org_ar}</strong></p>
<p><em>آخر تحديث في أكتوبر 2026</em></p>

<h3>1. مقدمة</h3>
<p>مرحبًا بكم في {app_ar}. توضح هذه السياسة كيف نجمع معلوماتك ونستخدمها ونفصح عنها ونحميها حين تستخدم تطبيقنا وخدماتنا. والجهة المسؤولة عن إدارة التطبيق هي {org_ar}.</p>

<h3>2. المعلومات التي نجمعها</h3>
<h4>2.1 المعلومات الشخصية</h4>
<ul>
    <li>رقم الهاتف لتسجيل الدخول برمز التحقق</li>
    <li>الاسم والبريد الإلكتروني، وتقديمهما اختياري</li>
    <li>بيانات الملف الشخصي التي تضيفها</li>
</ul>

<h4>2.2 معلومات الاستخدام</h4>
<ul>
    <li>الفعاليات التي تتصفحها وتضيفها إلى المفضلة</li>
    <li>التذاكر التي تشتريها</li>
</ul>
<p>وإذا أذنت للتطبيق باستخدام موقعك، فإنه يستخدمه على جهازك فقط ليعرض مكانك على الخريطة، ولا يرسله إلى خوادمنا.</p>

<h3>3. كيف نستخدم معلوماتك</h3>
<p>نستخدم المعلومات التي نجمعها في الأغراض الآتية:</p>
<ul>
    <li>تقديم خدمات المهرجان وصيانتها</li>
    <li>إتمام عمليات شراء التذاكر والحجوزات</li>
    <li>إرسال الإشعارات المهمة عن الفعاليات ومستجداتها</li>
    <li>تطوير وظائف التطبيق وتحسين تجربة استخدامه</li>
    <li>حماية الخدمة ومنع الاحتيال</li>
    <li>الوفاء بالالتزامات القانونية</li>
</ul>

<h3>4. مشاركة المعلومات</h3>
<p>لا نبيع معلوماتك الشخصية، وقد نشاركها مع الجهات الآتية:</p>
<ul>
    <li>الجهات الحكومية، بحسب ما يقتضيه القانون</li>
    <li>مزودي الخدمات الذين يعاونوننا في التشغيل</li>
    <li>منظمي الفعاليات، للتحقق من التذاكر</li>
    <li>شركة OpenAI، مزوّدة خدمة الذكاء الاصطناعي، حين تستخدم المساعد الذكي فقط، كما يبيّن البند 5</li>
</ul>

<h3>5. المساعد الذكي</h3>
<p>استخدام المساعد الذكي وميزة «خطّط زيارتي» اختياري، وحين تستخدمهما نرسل إلى شركة OpenAI، مزوّدة خدمة الذكاء الاصطناعي، البيانات اللازمة لإعداد الرد، وهي:</p>
<ul>
    <li>الرسائل التي تكتبها، والرسائل السابقة في المحادثة نفسها</li>
    <li>لغة التطبيق، والفعالية أو الصفحة التي سألت منها</li>
    <li>اسم الفعالية في كل تذكرة من تذاكرك وتاريخها وحالتها وعددها، ورصيد محفظتك ونقاطك، إذا سجّلت الدخول وسألت عنها</li>
    <li>التاريخ والوقت، وعدد البالغين والأطفال وأعمار الأطفال، واهتماماتك وميزانيتك وملاحظاتك، حين تستخدم «خطّط زيارتي»</li>
</ul>
<p>لا نرسل اسمك ولا رقم هاتفك ولا بريدك الإلكتروني، ويحمل كل طلب معرّفًا رمزيًا لا يكشف هويتك، تستخدمه OpenAI لرصد إساءة الاستخدام فقط. ونحفظ محادثاتك في نظامنا لتعود إليها لاحقًا. ونرجو ألا تكتب في رسائلك معلومات شخصية حساسة، كرقم الهوية أو رقم البطاقة المصرفية أو البيانات الصحية. وإن لم ترغب في إرسال هذه البيانات فلا تستخدم هاتين الميزتين، إذ يعمل سائر التطبيق دونهما.</p>

<h3>6. أمن البيانات</h3>
<p>نتخذ تدابير أمنية مناسبة لحماية معلوماتك، غير أن نقل البيانات عبر الإنترنت لا يكون آمنًا بنسبة 100%.</p>

<h3>7. حقوقك</h3>
<p>يحق لك ما يأتي:</p>
<ul>
    <li>الاطلاع على معلوماتك الشخصية</li>
    <li>طلب تصحيح البيانات غير الدقيقة</li>
    <li>طلب حذف حسابك</li>
    <li>إيقاف الرسائل التسويقية</li>
</ul>

<h3>8. خصوصية الأطفال</h3>
<p>التطبيق موجه لمن بلغوا 13 عامًا فأكثر، ولا نجمع عن علم معلومات من الأطفال دون 13 عامًا.</p>

<h3>9. تعديل سياسة الخصوصية</h3>
<p>قد نحدّث هذه السياسة دوريًا، ويُعد استمرارك في استخدام التطبيق بعد التحديث قبولًا للسياسة المحدّثة.</p>

<h3>10. التواصل معنا</h3>
<p>لأي استفسار عن هذه السياسة، تواصل مع:</p>
<p><strong>{org_ar}</strong><br>
إدارة {app_ar}<br>
البريد الإلكتروني: {email}</p>
HTML;
    }
}
