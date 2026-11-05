import type { Lang } from './api';

/**
 * Interface text. Arabic follows docs/arabic_style_rules.md (formal MSA); most
 * lines are the same as the page on the backend
 * (resources/views/visitor/event.blade.php), so both read alike.
 */
const ar = {
  bookYourTicket: 'احجز تذكرتك',
  event: 'فعالية',
  otherLang: 'English',

  // notices
  otp_sent: (masked: string) => `أرسلنا رمز التحقق إلى الرقم ${masked}.`,
  signed_in: 'تم تسجيل الدخول. اختر تذاكرك.',
  payment_success: 'اكتمل الدفع. تذاكرك بالأسفل.',
  payment_cancelled: 'لم يكتمل الدفع ولم يُخصم أي مبلغ.',
  payment_failed: 'لم ينجح الدفع ولم يُخصم أي مبلغ. أعد المحاولة أو استعمل بطاقة أخرى.',
  // errors
  invalid_phone: 'أدخل رقم هاتف عُماني صحيحًا من 8 أرقام.',
  otp_invalid: 'الرمز غير صحيح.',
  attemptsLeft: 'المحاولات المتبقية: ',
  otp_expired: 'انتهت صلاحية الرمز. اطلب رمزًا جديدًا.',
  otp_attempts: 'محاولات خاطئة كثيرة. اطلب رمزًا جديدًا.',
  throttled: 'محاولات كثيرة. انتظر بضع دقائق ثم أعد المحاولة.',
  items_required: 'اختر تذكرة واحدة على الأقل.',
  date_required: 'اختر يوم الزيارة.',
  date_invalid: 'هذا اليوم غير متاح.',
  sold_out: 'لا تكفي التذاكر المتبقية لهذا الطلب.',
  left: 'المتبقي: ',
  per_order_limit: 'عدد التذاكر أكبر من المسموح في الطلب الواحد',
  max: 'الحد ',
  sales_closed: 'البيع مغلق لهذا النوع من التذاكر.',
  type_not_available: 'هذا النوع من التذاكر غير متاح.',
  unavailable: 'الحجز غير متاح الآن. أعد المحاولة لاحقًا.',
  network: 'تعذّر الاتصال بالخادم. تحقق من اتصالك بالإنترنت ثم أعد المحاولة.',
  session_ended: 'انتهت مدة تسجيل الدخول. سجّل الدخول برقم هاتفك مرة أخرى.',

  // states
  notFoundTitle: 'الفعالية غير متاحة',
  notFound: 'لم نجد هذه الفعالية، أو أن الحجز عبر الإنترنت غير متاح لها.',
  homeTitle: 'احجز تذكرتك',
  home: 'امسح رمز QR الخاص بالفعالية أو افتح رابطها للحجز.',
  loadFailed: 'تعذّر تحميل الصفحة. تحقق من اتصالك بالإنترنت ثم أعد المحاولة.',
  retry: 'أعد المحاولة',
  ended: 'انتهت هذه الفعالية.',
  closed: 'لا تُباع تذاكر هذه الفعالية عبر الإنترنت حاليًا.',

  // tickets
  yourTickets: 'تذاكرك',
  entryQr: 'رمز QR للدخول',
  admits: 'يدخل بها',
  idRequired: 'تُطلب الهوية',
  saveTicket: 'حفظ التذكرة صورةً',
  status: { active: 'صالحة', used: 'مستخدمة', expired: 'منتهية', cancelled: 'ملغاة', refunded: 'مستردة' } as Record<string, string>,

  // sign in
  yourPhone: 'رقم هاتفك',
  phoneLead: 'نرسل لك رمزًا للتحقق. لا حاجة لإنشاء حساب.',
  phoneNumber: 'رقم الهاتف',
  sendCode: 'أرسل الرمز',
  codeTitle: 'رمز التحقق',
  codeLead: 'أدخل الرمز المكون من 6 أرقام المرسل إلى',
  verify: 'تحقق وتابع',
  resend: 'أعد إرسال الرمز',
  changeNumber: 'غيّر الرقم',

  // booking
  chooseTickets: 'اختر تذاكرك',
  bookMore: 'احجز تذاكر أخرى',
  visitDay: 'يوم الزيارة',
  today: 'اليوم',
  tomorrow: 'غدًا',
  soldOutDay: 'نفدت',
  free: 'مجانية',
  salesClosed: 'البيع مغلق',
  less: 'أقل',
  more: 'أكثر',
  quantity: 'عدد التذاكر',
  total: 'الإجمالي',
  payByCard: 'ادفع بالبطاقة',
  getTickets: 'احصل على التذاكر',
  secure: 'يتم الدفع عبر بوابة الدفع الآمنة. تجد تذاكرك هنا وفي التطبيق عند الدخول برقم هاتفك.',
  signedInAs: 'مسجّل برقم',
  signOut: 'تسجيل الخروج',

  // footer
  managedBy: 'تحت إدارة',
  privacy: 'سياسة الخصوصية',
  terms: 'الشروط والأحكام',
};

export type Strings = typeof ar;

const en: Strings = {
  bookYourTicket: 'Book your ticket',
  event: 'Event',
  otherLang: 'العربية',

  otp_sent: (masked: string) => `We texted a code to ${masked}.`,
  signed_in: 'You are signed in. Choose your tickets.',
  payment_success: 'Payment complete. Your tickets are below.',
  payment_cancelled: 'The payment was not completed and nothing was charged.',
  payment_failed: 'The payment did not go through and nothing was charged. Try again or use another card.',
  invalid_phone: 'Enter a valid 8-digit Omani mobile number.',
  otp_invalid: 'That code is not right.',
  attemptsLeft: 'Attempts left: ',
  otp_expired: 'The code has expired. Ask for a new one.',
  otp_attempts: 'Too many wrong codes. Ask for a new one.',
  throttled: 'Too many attempts. Wait a few minutes and try again.',
  items_required: 'Choose at least one ticket.',
  date_required: 'Choose the day of your visit.',
  date_invalid: 'That day is not available.',
  sold_out: 'Not enough tickets are left for this order.',
  left: 'Left: ',
  per_order_limit: 'Too many tickets in one order',
  max: 'max ',
  sales_closed: 'Sales are closed for this ticket type.',
  type_not_available: 'This ticket type is not available.',
  unavailable: 'Booking is not available right now. Try again later.',
  network: 'Could not reach the server. Check your internet connection and try again.',
  session_ended: 'Your sign-in has ended. Sign in again with your phone number.',

  notFoundTitle: 'Event not available',
  notFound: 'We could not find this event, or online booking is not available for it.',
  homeTitle: 'Book your ticket',
  home: "Scan the event's QR code or open its link to book.",
  loadFailed: 'The page could not load. Check your internet connection and try again.',
  retry: 'Try again',
  ended: 'This event has ended.',
  closed: 'Tickets for this event are not on sale online right now.',

  yourTickets: 'Your tickets',
  entryQr: 'Entry QR code',
  admits: 'Admits',
  idRequired: 'ID required',
  saveTicket: 'Save ticket as image',
  status: { active: 'Valid', used: 'Used', expired: 'Expired', cancelled: 'Cancelled', refunded: 'Refunded' },

  yourPhone: 'Your phone number',
  phoneLead: 'We will text you a code. No account needed.',
  phoneNumber: 'Phone number',
  sendCode: 'Send code',
  codeTitle: 'Verification code',
  codeLead: 'Enter the 6-digit code sent to',
  verify: 'Verify and continue',
  resend: 'Resend code',
  changeNumber: 'Change number',

  chooseTickets: 'Choose your tickets',
  bookMore: 'Book more tickets',
  visitDay: 'Day of your visit',
  today: 'Today',
  tomorrow: 'Tomorrow',
  soldOutDay: 'Sold out',
  free: 'Free',
  salesClosed: 'Sales closed',
  less: 'Less',
  more: 'More',
  quantity: 'Number of tickets',
  total: 'Total',
  payByCard: 'Pay by card',
  getTickets: 'Get the tickets',
  secure: 'You pay through the secure payment gateway. Your tickets show here, and in the app when you sign in with your number.',
  signedInAs: 'Signed in as',
  signOut: 'Sign out',

  managedBy: 'Managed by',
  privacy: 'Privacy Policy',
  terms: 'Terms & Conditions',
};

export const strings: Record<Lang, Strings> = { ar, en };

/** Language before the event loads: ?lang=, then the last one chosen here; null = the tenant's default (the API decides). */
export function initialLang(): Lang | null {
  const q = new URLSearchParams(location.search).get('lang');
  if (q === 'ar' || q === 'en') return q;
  try {
    const stored = localStorage.getItem('booking-lang');
    if (stored === 'ar' || stored === 'en') return stored;
  } catch {
    /* storage blocked */
  }
  return null;
}
