<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TermsAndCondition;

class TermsAndConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermsAndCondition::create([
            'title' => 'Terms and Conditions',
            'title_ar' => 'الشروط والأحكام',
            'content' => $this->getEnglishContent(),
            'content_ar' => $this->getArabicContent(),
            'is_active' => true,
        ]);
    }

    private function getEnglishContent(): string
    {
        return <<<HTML
<h2>Terms and Conditions for Sohar Festival</h2>
<p><strong>Managed by North Batinah Governor Office</strong></p>
<p><em>Last Updated: October 2025</em></p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing and using the Sohar Festival mobile application and services, you accept and agree to be bound by the terms and provision of this agreement. The Sohar Festival is managed by the North Batinah Governor Office (مكتب محافظ شمال الباطنة).</p>

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
<p>All content, features, and functionality are owned by the North Batinah Governor Office and are protected by international copyright, trademark, and other intellectual property laws.</p>

<h3>6. Event Rules and Regulations</h3>
<ul>
    <li>Attendees must follow all event-specific rules and safety guidelines</li>
    <li>Photography and recording may be restricted at certain events</li>
    <li>Food and beverages may be restricted at certain venues</li>
    <li>Management reserves the right to remove anyone who violates event rules</li>
</ul>

<h3>7. Limitation of Liability</h3>
<p>The North Batinah Governor Office and Sohar Festival shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of the app or services.</p>

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
<p><strong>North Batinah Governor Office</strong><br>
Sohar Festival Management<br>
Email: info@soharfestival.om<br>
Phone: +968 XXXXXXXX</p>
HTML;
    }

    private function getArabicContent(): string
    {
        return <<<HTML
<h2>الشروط والأحكام لمهرجان صحار</h2>
<p><strong>تحت إدارة مكتب محافظ شمال الباطنة</strong></p>
<p><em>آخر تحديث: أكتوبر 2025</em></p>

<h3>1. قبول الشروط</h3>
<p>من خلال الوصول إلى تطبيق وخدمات مهرجان صحار واستخدامها، فإنك تقبل وتوافق على الالتزام بشروط وأحكام هذه الاتفاقية. يدار مهرجان صحار من قبل مكتب محافظ شمال الباطنة.</p>

<h3>2. استخدام الخدمات</h3>
<h4>2.1 الأهلية</h4>
<p>يجب أن يكون عمرك 13 عاماً على الأقل لاستخدام هذا التطبيق. باستخدام التطبيق، فإنك تقر بأنك تستوفي هذا الشرط.</p>

<h4>2.2 تسجيل الحساب</h4>
<ul>
    <li>يجب عليك تقديم معلومات دقيقة وكاملة عند التسجيل</li>
    <li>أنت مسؤول عن الحفاظ على سرية حسابك</li>
    <li>يجب عليك إخطارنا فوراً بأي استخدام غير مصرح به لحسابك</li>
</ul>

<h3>3. التذاكر والحجوزات</h3>
<h4>3.1 شراء التذاكر</h4>
<ul>
    <li>جميع مبيعات التذاكر نهائية ما لم يتم إلغاء الحدث</li>
    <li>التذاكر غير قابلة للتحويل ما لم ينص على خلاف ذلك</li>
    <li>يجب عليك تقديم هوية صالحة وتأكيد التذكرة في الحدث</li>
    <li>تحتفظ إدارة المهرجان بالحق في رفض الدخول</li>
</ul>

<h4>3.2 سياسة الاسترداد</h4>
<ul>
    <li>تتوفر المبالغ المستردة فقط إذا تم إلغاء الحدث من قبل المنظمين</li>
    <li>لا توجد مبالغ مستردة بسبب تضارب الجداول الشخصية أو تغيير الرأي</li>
    <li>وقت معالجة المبالغ المستردة هو 7-14 يوم عمل</li>
</ul>

<h3>4. سلوك المستخدم</h3>
<p>أنت توافق على عدم:</p>
<ul>
    <li>استخدام التطبيق لأي غرض غير قانوني</li>
    <li>محاولة الوصول غير المصرح به إلى أي جزء من التطبيق</li>
    <li>التداخل أو تعطيل وظائف التطبيق</li>
    <li>تحميل رمز ضار أو الانخراط في أنشطة ضارة</li>
    <li>انتحال شخصية أي شخص أو كيان</li>
</ul>

<h3>5. الملكية الفكرية</h3>
<p>جميع المحتويات والميزات والوظائف مملوكة لمكتب محافظ شمال الباطنة ومحمية بموجب قوانين حقوق النشر والعلامات التجارية والملكية الفكرية الأخرى الدولية.</p>

<h3>6. قواعد ولوائح الفعاليات</h3>
<ul>
    <li>يجب على الحضور اتباع جميع قواعد وإرشادات السلامة الخاصة بالفعالية</li>
    <li>قد يتم تقييد التصوير الفوتوغرافي والتسجيل في بعض الفعاليات</li>
    <li>قد يتم تقييد الطعام والمشروبات في بعض الأماكن</li>
    <li>تحتفظ الإدارة بالحق في إزالة أي شخص ينتهك قواعد الفعالية</li>
</ul>

<h3>7. حدود المسؤولية</h3>
<p>لن يكون مكتب محافظ شمال الباطنة ومهرجان صحار مسؤولين عن أي أضرار غير مباشرة أو عرضية أو خاصة أو تبعية أو عقابية ناتجة عن استخدامك للتطبيق أو الخدمات.</p>

<h3>8. التغييرات في الفعاليات</h3>
<p>تفاصيل الفعالية بما في ذلك التواريخ والأوقات والأماكن والفنانين عرضة للتغيير. سنخطرك بأي تغييرات مهمة من خلال التطبيق أو معلومات الاتصال المسجلة.</p>

<h3>9. الإنهاء</h3>
<p>نحتفظ بالحق في إنهاء أو تعليق حسابك حسب تقديرنا، دون إشعار مسبق، لسلوك نعتقد أنه ينتهك هذه الشروط والأحكام.</p>

<h3>10. القانون الحاكم</h3>
<p>تخضع هذه الشروط والأحكام وتفسر وفقاً لقوانين سلطنة عمان.</p>

<h3>11. التغييرات في الشروط</h3>
<p>نحتفظ بالحق في تعديل هذه الشروط في أي وقت. يشكل الاستمرار في استخدام التطبيق بعد التغييرات قبولاً للشروط المعدلة.</p>

<h3>12. معلومات الاتصال</h3>
<p>للأسئلة أو المخاوف بشأن هذه الشروط والأحكام، يرجى الاتصال بـ:</p>
<p><strong>مكتب محافظ شمال الباطنة</strong><br>
إدارة مهرجان صحار<br>
البريد الإلكتروني: info@soharfestival.om<br>
الهاتف: +968 XXXXXXXX</p>
HTML;
    }
}
