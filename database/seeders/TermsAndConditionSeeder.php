<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TermsAndCondition;

class TermsAndConditionSeeder extends Seeder
{
    public const TITLE = 'Terms and Conditions';

    public const TITLE_AR = 'الشروط والأحكام';

    /**
     * Generic legal template filled from the tenant's settings (name,
     * organization, support e-mail/phone). Idempotent: an existing page of
     * the tenant (possibly edited in the admin) is left untouched.
     */
    public function run(): void
    {
        $tenant = app(\App\Tenancy\TenantContext::class)->get();

        TermsAndCondition::firstOrCreate(['title' => self::TITLE], [
            'title_ar' => self::TITLE_AR,
            'content' => LegalTemplate::fill($this->getEnglishContent(), $tenant),
            'content_ar' => LegalTemplate::fill(self::arabicTemplate(), $tenant),
            'is_active' => true,
        ]);
    }

    private function getEnglishContent(): string
    {
        return <<<HTML
<h2>Terms and Conditions for {app_en}</h2>
<p><strong>Managed by {org_en}</strong></p>
<p><em>Last Updated: October 2025</em></p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing and using the {app_en} mobile application and services, you accept and agree to be bound by the terms and provision of this agreement. {app_en} is managed by the {org_en} ({org_ar}).</p>

<h3>2. Use of Services</h3>
<h4>2.1 Eligibility</h4>
<p>You must be at least 13 years old to use this application. By using the app, you represent that you meet this requirement.</p>

<h4>2.2 Account Registration</h4>
<ul>
    <li>You must provide accurate and complete information when registering</li>
    <li>You are responsible for maintaining the confidentiality of your account</li>
    <li>You must notify us immediately of any unauthorized use of your account</li>
</ul>

<h3>3. Tickets and Bookings</h3>
<h4>3.1 Ticket Purchase</h4>
<ul>
    <li>All ticket sales are final unless an event is cancelled</li>
    <li>Tickets are non-transferable unless otherwise stated</li>
    <li>You must present valid identification and ticket confirmation at the event</li>
    <li>Festival management reserves the right to refuse entry</li>
</ul>

<h4>3.2 Refund Policy</h4>
<ul>
    <li>Refunds are only available if an event is cancelled by the organizers</li>
    <li>No refunds for personal scheduling conflicts or change of mind</li>
    <li>Processing time for refunds is 7-14 business days</li>
</ul>

<h3>4. User Conduct</h3>
<p>You agree not to:</p>
<ul>
    <li>Use the app for any unlawful purpose</li>
    <li>Attempt to gain unauthorized access to any portion of the app</li>
    <li>Interfere with or disrupt the app's functionality</li>
    <li>Upload malicious code or engage in harmful activities</li>
    <li>Impersonate any person or entity</li>
</ul>

<h3>5. Intellectual Property</h3>
<p>All content, features, and functionality are owned by the {org_en} and are protected by international copyright, trademark, and other intellectual property laws.</p>

<h3>6. Event Rules and Regulations</h3>
<ul>
    <li>Attendees must follow all event-specific rules and safety guidelines</li>
    <li>Photography and recording may be restricted at certain events</li>
    <li>Food and beverages may be restricted at certain venues</li>
    <li>Management reserves the right to remove anyone who violates event rules</li>
</ul>

<h3>7. Limitation of Liability</h3>
<p>The {org_en} and {app_en} shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the app or services.</p>

<h3>8. Changes to Events</h3>
<p>Event details including dates, times, venues, and performers are subject to change. We will notify you of any significant changes through the app or registered contact information.</p>

<h3>9. Termination</h3>
<p>We reserve the right to terminate or suspend your account at our discretion, without notice, for conduct that we believe violates these Terms and Conditions.</p>

<h3>10. Governing Law</h3>
<p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Sultanate of Oman.</p>

<h3>11. Changes to Terms</h3>
<p>We reserve the right to modify these terms at any time. Continued use of the app after changes constitutes acceptance of the modified terms.</p>

<h3>12. Contact Information</h3>
<p>For questions or concerns about these Terms and Conditions, please contact:</p>
<p><strong>{org_en}</strong><br>
{app_en} Management<br>
Email: {email}<br>
Phone: {phone}</p>
HTML;
    }

    /** Arabic template, also read by `content:sync-arabic`. */
    public static function arabicTemplate(): string
    {
        return <<<HTML
<h2>الشروط والأحكام لتطبيق {app_ar}</h2>
<p><strong>بإدارة {org_ar}</strong></p>
<p><em>آخر تحديث في أكتوبر 2025</em></p>

<h3>1. قبول الشروط</h3>
<p>باستخدامك تطبيق {app_ar} وخدماته فإنك توافق على الالتزام بأحكام هذه الاتفاقية. والجهة المسؤولة عن إدارة التطبيق هي {org_ar}.</p>

<h3>2. استخدام الخدمات</h3>
<h4>2.1 الأهلية</h4>
<p>يُشترط لاستخدام التطبيق ألا يقل عمرك عن 13 عامًا، واستخدامك له إقرار منك باستيفاء هذا الشرط.</p>

<h4>2.2 تسجيل الحساب</h4>
<ul>
    <li>عليك أن تقدم معلومات دقيقة وكاملة عند التسجيل</li>
    <li>أنت مسؤول عن الحفاظ على سرية بيانات حسابك</li>
    <li>عليك إبلاغنا فورًا بأي استخدام غير مصرح به لحسابك</li>
</ul>

<h3>3. التذاكر والحجوزات</h3>
<h4>3.1 شراء التذاكر</h4>
<ul>
    <li>بيع التذاكر نهائي إلا إذا أُلغيت الفعالية</li>
    <li>لا يجوز نقل التذكرة إلى شخص آخر ما لم يُنص على غير ذلك</li>
    <li>عليك إبراز هوية سارية وتأكيد التذكرة عند حضور الفعالية</li>
    <li>لإدارة المهرجان الحق في رفض الدخول</li>
</ul>

<h4>3.2 سياسة الاسترداد</h4>
<ul>
    <li>لا يُقبل الاسترداد إلا إذا ألغى المنظمون الفعالية</li>
    <li>لا يُقبل الاسترداد بسبب تعارض المواعيد الشخصية أو العدول عن الحضور</li>
    <li>تستغرق معالجة الاسترداد من 7 إلى 14 يوم عمل</li>
</ul>

<h3>4. سلوك المستخدم</h3>
<p>تتعهد بالامتناع عما يأتي:</p>
<ul>
    <li>استخدام التطبيق في أي غرض مخالف للقانون</li>
    <li>محاولة الدخول إلى أي جزء من التطبيق دون تصريح</li>
    <li>التدخل في عمل التطبيق أو تعطيله</li>
    <li>رفع شيفرات خبيثة أو ممارسة أي نشاط ضار</li>
    <li>انتحال صفة أي شخص أو جهة</li>
</ul>

<h3>5. الملكية الفكرية</h3>
<p>محتوى التطبيق وميزاته ووظائفه كلها مملوكة ل{org_ar}، ومحمية بالقوانين الدولية لحقوق النشر والعلامات التجارية وسائر حقوق الملكية الفكرية.</p>

<h3>6. قواعد الفعاليات ولوائحها</h3>
<ul>
    <li>على الحضور التقيد بقواعد كل فعالية وإرشادات السلامة فيها</li>
    <li>قد يُقيَّد التصوير والتسجيل في بعض الفعاليات</li>
    <li>قد تُفرض قيود على الطعام والمشروبات في بعض المواقع</li>
    <li>للإدارة الحق في إخراج من يخالف قواعد الفعالية</li>
</ul>

<h3>7. حدود المسؤولية</h3>
<p>لا يتحمل كلٌّ من {org_ar} و{app_ar} المسؤولية عن أي أضرار غير مباشرة أو عرضية أو خاصة أو تبعية أو عقابية تنشأ عن استخدامك التطبيق أو خدماته.</p>

<h3>8. تغيير تفاصيل الفعاليات</h3>
<p>تفاصيل الفعاليات قابلة للتغيير، ومنها التواريخ والأوقات والأماكن والمؤدون. وسنبلغك بأي تغيير مهم عبر التطبيق أو بيانات الاتصال المسجلة لدينا.</p>

<h3>9. إنهاء الحساب</h3>
<p>يحق لنا تعليق حسابك أو إنهاؤه وفق تقديرنا ودون إشعار مسبق، إذا رأينا أن سلوكك يخالف هذه الشروط والأحكام.</p>

<h3>10. القانون الواجب التطبيق</h3>
<p>تخضع هذه الشروط والأحكام لقوانين سلطنة عُمان وتُفسَّر وفقًا لها.</p>

<h3>11. تعديل الشروط</h3>
<p>يحق لنا تعديل هذه الشروط في أي وقت، ويُعد استمرارك في استخدام التطبيق بعد التعديل قبولًا للشروط المعدلة.</p>

<h3>12. التواصل معنا</h3>
<p>لأي سؤال أو ملاحظة عن هذه الشروط والأحكام، تواصل مع:</p>
<p><strong>{org_ar}</strong><br>
إدارة {app_ar}<br>
البريد الإلكتروني: {email}<br>
الهاتف: {phone}</p>
HTML;
    }
}
