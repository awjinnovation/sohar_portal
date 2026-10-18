<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PrivacyPolicy;

class PrivacyPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /**
     * Generic legal template filled from the tenant's settings (name,
     * organization, support e-mail/phone). Idempotent: an existing page of
     * the tenant (possibly edited in the admin) is left untouched.
     */
    public function run(): void
    {
        $tenant = app(\App\Tenancy\TenantContext::class)->get();

        PrivacyPolicy::firstOrCreate(['title' => 'Privacy Policy'], [
            'title_ar' => 'سياسة الخصوصية',
            'content' => LegalTemplate::fill($this->getEnglishContent(), $tenant),
            'content_ar' => LegalTemplate::fill($this->getArabicContent(), $tenant),
            'is_active' => true,
        ]);
    }

    private function getEnglishContent(): string
    {
        return <<<HTML
<h2>Privacy Policy for {app_en}</h2>
<p><strong>Managed by {org_en}</strong></p>
<p><em>Last Updated: October 2025</em></p>

<h3>1. Introduction</h3>
<p>Welcome to {app_en}. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our mobile application and services. {app_en} is managed by the {org_en} ({org_ar}).</p>

<h3>2. Information We Collect</h3>
<h4>2.1 Personal Information</h4>
<ul>
    <li>Phone number (for OTP authentication)</li>
    <li>Name and email address (optional)</li>
    <li>Profile information you provide</li>
    <li>Device tokens for push notifications</li>
</ul>

<h4>2.2 Usage Information</h4>
<ul>
    <li>Events you view and favorite</li>
    <li>Tickets you purchase</li>
    <li>Location data (only when you use location-based features)</li>
    <li>App usage analytics</li>
</ul>

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
</ul>

<h3>5. Data Security</h3>
<p>We implement appropriate security measures to protect your information. However, no electronic transmission over the internet is 100% secure.</p>

<h3>6. Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access your personal information</li>
    <li>Request correction of inaccurate data</li>
    <li>Request deletion of your account</li>
    <li>Opt-out of marketing communications</li>
</ul>

<h3>7. Children's Privacy</h3>
<p>The app is intended for users aged 13 and above. We do not knowingly collect information from children under 13.</p>

<h3>8. Changes to Privacy Policy</h3>
<p>We may update this Privacy Policy periodically. Continued use of the app after changes constitutes acceptance of the updated policy.</p>

<h3>9. Contact Us</h3>
<p>For questions about this Privacy Policy, please contact:</p>
<p><strong>{org_en}</strong><br>
{app_en} Management<br>
Email: {email}</p>
HTML;
    }

    private function getArabicContent(): string
    {
        return <<<HTML
<h2>سياسة الخصوصية ل{app_ar}</h2>
<p><strong>تحت إدارة {org_ar}</strong></p>
<p><em>آخر تحديث: أكتوبر 2025</em></p>

<h3>1. المقدمة</h3>
<p>مرحباً بكم في {app_ar}. توضح سياسة الخصوصية هذه كيفية جمع واستخدام والإفصاح عن معلوماتك وحمايتها عند استخدام تطبيق الهاتف المحمول والخدمات الخاصة بنا. يدار {app_ar} من قبل {org_ar}.</p>

<h3>2. المعلومات التي نجمعها</h3>
<h4>2.1 المعلومات الشخصية</h4>
<ul>
    <li>رقم الهاتف (للمصادقة عبر OTP)</li>
    <li>الاسم وعنوان البريد الإلكتروني (اختياري)</li>
    <li>معلومات الملف الشخصي التي تقدمها</li>
    <li>رموز الجهاز للإشعارات الفورية</li>
</ul>

<h4>2.2 معلومات الاستخدام</h4>
<ul>
    <li>الفعاليات التي تشاهدها وتضيفها للمفضلة</li>
    <li>التذاكر التي تشتريها</li>
    <li>بيانات الموقع (فقط عند استخدام الميزات المعتمدة على الموقع)</li>
    <li>تحليلات استخدام التطبيق</li>
</ul>

<h3>3. كيفية استخدام معلوماتك</h3>
<p>نستخدم المعلومات المجمعة من أجل:</p>
<ul>
    <li>توفير وصيانة خدمات المهرجان</li>
    <li>معالجة شراء التذاكر والحجوزات</li>
    <li>إرسال إشعارات مهمة حول الفعاليات والتحديثات</li>
    <li>تحسين وظائف التطبيق وتجربة المستخدم</li>
    <li>ضمان الأمان ومنع الاحتيال</li>
    <li>الامتثال للالتزامات القانونية</li>
</ul>

<h3>4. مشاركة المعلومات</h3>
<p>نحن لا نبيع معلوماتك الشخصية. قد نشارك المعلومات مع:</p>
<ul>
    <li>الجهات الحكومية كما يقتضي القانون</li>
    <li>مقدمي الخدمات الذين يساعدون في العمليات</li>
    <li>منظمي الفعاليات للتحقق من صحة التذاكر</li>
</ul>

<h3>5. أمن البيانات</h3>
<p>نطبق تدابير أمنية مناسبة لحماية معلوماتك. ومع ذلك، لا يوجد نقل إلكتروني عبر الإنترنت آمن بنسبة 100٪.</p>

<h3>6. حقوقك</h3>
<p>لديك الحق في:</p>
<ul>
    <li>الوصول إلى معلوماتك الشخصية</li>
    <li>طلب تصحيح البيانات غير الدقيقة</li>
    <li>طلب حذف حسابك</li>
    <li>إلغاء الاشتراك في الاتصالات التسويقية</li>
</ul>

<h3>7. خصوصية الأطفال</h3>
<p>التطبيق مخصص للمستخدمين من عمر 13 عاماً فما فوق. نحن لا نجمع عن علم معلومات من الأطفال دون سن 13 عاماً.</p>

<h3>8. التغييرات على سياسة الخصوصية</h3>
<p>قد نقوم بتحديث سياسة الخصوصية هذه بشكل دوري. يشكل الاستمرار في استخدام التطبيق بعد التغييرات قبولاً للسياسة المحدثة.</p>

<h3>9. اتصل بنا</h3>
<p>للاستفسارات حول سياسة الخصوصية هذه، يرجى الاتصال بـ:</p>
<p><strong>{org_ar}</strong><br>
إدارة {app_ar}<br>
البريد الإلكتروني: {email}</p>
HTML;
    }
}
