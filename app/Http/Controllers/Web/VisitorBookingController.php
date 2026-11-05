<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Gate\BoxOfficeService;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use App\View\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Event booking page, opened from the event's printed QR code (admin →
 * event → booking QR). No app and no account form: phone + OTP, then card
 * checkout of the event's app-channel ticket types.
 *
 * GET  /e/{event}           sign-in (phone, then code) | booking form + the
 *                           visitor's tickets for this event
 * POST /e/{event}/otp       text a code (OtpService, throttle:otp-send)
 * POST /e/{event}/verify    check it, sign in on the `visitor` guard
 *                           (remembered VISITOR_SESSION_DAYS, default 30)
 * POST /e/{event}/checkout  CheckoutService::initialize → gateway (free
 *                           orders complete at once); the payment pages
 *                           send the visitor back here
 * POST /e/{event}/logout
 *
 * The phone is normalised like the box office (968 + local number), so the
 * same account signs in to the app and finds the tickets there.
 */
class VisitorBookingController extends Controller
{
    /** Days of a dated event offered in the date picker, from today. */
    private const MAX_DAYS = 14;

    public function __construct(private TenantContext $context, private OtpService $otp) {}

    /** Absolute link printed in the event's QR: the booking site when BOOKING_WEB_URL is set, else this page. */
    public static function publicUrl(Event $event): string
    {
        return self::pageUrl($event->id);
    }

    /**
     * The event's booking page: {BOOKING_WEB_URL}/e/{id} (booking-web/, its own
     * domain) when configured, else /e/{id} on this server (APP_URL /
     * PAY_LINK_BASE_URL, like box office links). Empty query values are dropped.
     */
    public static function pageUrl(int $eventId, array $query = []): string
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
        $site = (string) config('booking.web_url');
        $path = $site !== ''
            ? $site.'/e/'.$eventId
            : rtrim(BoxOfficeService::publicBase(), '/').route('visitor.event', ['event' => $eventId], false);

        return $query ? $path.'?'.http_build_query($query) : $path;
    }

    public function show(Request $request, int $event)
    {
        [$event, $tenant] = $this->event($event);
        $lang = $this->lang($request, $tenant);
        // The booking site replaces this page; QR codes printed earlier land here.
        if (config('booking.web_url') !== '') {
            return redirect()->away(self::pageUrl($event->id, [
                'lang' => $request->query('lang'),
                'payment' => in_array($request->query('payment'), ['success', 'cancelled', 'failed'], true) ? $request->query('payment') : null,
            ]));
        }
        $user = $this->visitor();

        $types = $event->sellableTicketTypes(EventTicketType::CHANNEL_APP);
        $dates = $types->contains(fn (EventTicketType $t) => $t->isDated()) ? self::bookableDates($event, $tenant) : [];
        $tickets = $user
            ? Ticket::with('ticketType')->where('user_id', $user->id)->where('event_id', $event->id)->orderByDesc('id')->get()
            : collect();
        $otpPhone = $user ? null : $request->session()->get('visitor_otp_phone');

        return response()->view('visitor.event', [
            'event' => $event,
            'lang' => $lang,
            'visitor' => $user,
            'visitorPhone' => $user ? self::maskedPhone($user->phone_number) : null,
            'otpPhone' => $otpPhone,
            'otpPhoneMasked' => $otpPhone ? self::maskedPhone($otpPhone) : null,
            'types' => $types,
            'dates' => $dates,
            'selectedDate' => old('booking_date', collect($dates)->firstWhere('available', '!=', 0)['date'] ?? null),
            'ended' => $event->hasEnded(),
            'tickets' => $tickets,
            'error' => $request->session()->get('visitor_error'),
            'errorData' => (array) $request->session()->get('visitor_error_data', []),
            'notice' => $request->session()->get('visitor_notice')
                ?? (in_array($request->query('payment'), ['success', 'cancelled', 'failed'], true) ? 'payment_'.$request->query('payment') : null),
            'formKey' => (string) Str::uuid(),
            'phoneHint' => (string) $tenant->setting('auth.phone_hint', '9123 4567'),
            'countryCode' => (string) $tenant->setting('auth.country_code', '968'),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function sendOtp(Request $request, int $event, BoxOfficeService $boxOffice)
    {
        [$event, $tenant] = $this->event($event);
        $lang = $this->lang($request, $tenant);
        $request->validate(['phone_number' => ['required', 'string', 'max:20']]);

        try {
            $phone = $boxOffice->normalizePhone($tenant, (string) $request->input('phone_number'));
        } catch (ApiException) {
            return $this->back($event, $lang, ['visitor_error' => 'invalid_phone'], true);
        }

        $this->otp->send($phone, $tenant, $lang);
        $request->session()->put('visitor_otp_phone', $phone);

        return $this->back($event, $lang, ['visitor_notice' => 'otp_sent']);
    }

    public function verifyOtp(Request $request, int $event)
    {
        [$event, $tenant] = $this->event($event);
        $lang = $this->lang($request, $tenant);
        $request->validate(['phone_number' => ['required', 'string'], 'otp' => ['required', 'string', 'max:12']]);

        // The code goes with the phone it was sent to (kept in the session);
        // the form repeats that phone so throttle:otp-verify counts guesses
        // against it, and a different one is refused.
        $phone = (string) $request->session()->get('visitor_otp_phone', '');
        if ($phone === '' || ! hash_equals($phone, preg_replace('/\D/', '', (string) $request->input('phone_number')))) {
            $request->session()->forget('visitor_otp_phone');

            return $this->back($event, $lang, ['visitor_error' => 'otp_expired']);
        }

        $result = $this->otp->verify($phone, preg_replace('/\D/', '', (string) $request->input('otp')));
        if (! $result['ok']) {
            $error = match (true) {
                $result['code'] === OtpService::ATTEMPTS_EXCEEDED => 'otp_attempts',
                isset($result['attempts_remaining']) => 'otp_invalid',
                default => 'otp_expired',
            };

            return $this->back($event, $lang, [
                'visitor_error' => $error,
                'visitor_error_data' => ['remaining' => $result['attempts_remaining'] ?? null],
            ]);
        }

        Auth::guard('visitor')->login($this->otp->userFor($phone), true);
        $request->session()->forget('visitor_otp_phone');

        return $this->back($event, $lang, ['visitor_notice' => 'signed_in']);
    }

    public function checkout(Request $request, int $event, CheckoutService $checkout, PaymentGatewayManager $gateways)
    {
        [$event, $tenant] = $this->event($event);
        $lang = $this->lang($request, $tenant);
        if (! $user = $this->visitor()) {
            return $this->back($event, $lang);
        }

        $data = $request->validate([
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:50'],
            'form_key' => ['nullable', 'string', 'max:64'],
        ]);
        $items = collect($data['items'] ?? [])
            ->map(fn ($qty, $typeId) => ['ticket_type_id' => (int) $typeId, 'quantity' => (int) $qty])
            ->filter(fn ($item) => $item['quantity'] > 0)
            ->values()->all();
        if (! $items) {
            return $this->back($event, $lang, ['visitor_error' => 'items_required'], true);
        }
        $date = $data['booking_date'] ?? null;
        if ($date !== null && $date < TenantTime::today($tenant)) {
            return $this->back($event, $lang, ['visitor_error' => 'date_invalid'], true);
        }

        try {
            $payment = $checkout->initialize($user, $tenant, [
                'event_id' => $event->id,
                'booking_date' => $date,
                'items' => $items,
                'method' => Payment::METHOD_GATEWAY,
                // A double tap of the same order reuses its payment; a changed order is a new one.
                'idempotency_key' => filled($data['form_key'] ?? null)
                    ? 'web:'.substr(hash('sha256', $data['form_key'].'|'.json_encode($items).'|'.$date), 0, 40)
                    : null,
            ], ['web_booking' => true, 'web_booking_lang' => $lang]);
        } catch (ApiException $e) {
            return $this->back($event, $lang, [
                'visitor_error' => strtolower($e->errorCode),
                'visitor_error_data' => array_intersect_key($e->extra, array_flip(['available', 'per_order_max'])),
            ], true);
        } catch (ValidationException $e) {
            return $this->back($event, $lang, ['visitor_error' => isset($e->errors()['booking_date']) ? 'date_required' : 'items_required'], true);
        }

        return match (true) {
            $payment->status === 'completed' => redirect()->route('visitor.event', ['event' => $event->id, 'lang' => $lang, 'payment' => 'success']),
            $payment->isPending() => redirect()->away($gateways->paymentUrl($payment, ['lang' => $lang]))->header('Referrer-Policy', 'no-referrer'),
            default => $this->back($event, $lang, ['visitor_error' => 'unavailable'], true),
        };
    }

    public function logout(Request $request, int $event)
    {
        [$event, $tenant] = $this->event($event);
        $lang = $this->lang($request, $tenant);
        Auth::guard('visitor')->logout();
        $request->session()->forget('visitor_otp_phone');
        $request->session()->regenerateToken();

        return $this->back($event, $lang);
    }

    /** @return array{0: Event, 1: Tenant} Active event of an active tenant with web booking on; sets the tenant context. */
    private function event(int $id): array
    {
        $event = Event::withoutTenantScope()->where('is_active', true)->findOrFail($id);
        $tenant = Tenant::findOrFail($event->tenant_id);
        abort_unless($tenant->isActive() && $tenant->moduleEnabled('web_booking') && $tenant->moduleEnabled('tickets'), 404);
        $this->context->set($tenant);
        view()->share('brand', $tenant);

        return [$event, $tenant];
    }

    private function visitor(): ?User
    {
        return Auth::guard('visitor')->user();
    }

    /** ?lang= or the form's lang, else the tenant default (PublicPage::locale). */
    private function lang(Request $request, Tenant $tenant): string
    {
        $posted = strtolower((string) $request->input('lang', ''));
        if ($request->isMethod('post') && in_array($posted, ['ar', 'en'], true)) {
            app()->setLocale($posted);

            return $posted;
        }

        return PublicPage::locale($tenant);
    }

    /** Back to the event page with flashed notice / error codes (visitor/event.blade.php maps them to text). */
    private function back(Event $event, string $lang, array $flash = [], bool $withInput = false)
    {
        $redirect = redirect()->route('visitor.event', ['event' => $event->id, 'lang' => $lang]);
        foreach ($flash as $key => $value) {
            $redirect->with($key, $value);
        }

        return $withInput ? $redirect->withInput() : $redirect;
    }

    /**
     * Bookable days of a dated event, from today (tenant time) to its last
     * day, at most MAX_DAYS. `available` is -1 for unlimited. Also used by
     * the booking site API (Api\BookingController).
     *
     * @return list<array{date:string, available:int}>
     */
    public static function bookableDates(Event $event, Tenant $tenant): array
    {
        if (! $event->firstDay() || ! $event->lastDay()) {
            return [];
        }
        $today = TenantTime::today($tenant);
        $day = \Carbon\Carbon::parse(max($today, $event->firstDay()));
        $last = $event->lastDay();
        $dates = [];
        while ($day->toDateString() <= $last && count($dates) < self::MAX_DAYS) {
            $dates[] = ['date' => $day->toDateString(), 'available' => $event->getAvailableTicketsForDate($day->toDateString())];
            $day->addDay();
        }

        return $dates;
    }

    /** 96891234567 → •••• 4567, isolated left-to-right so Arabic text keeps its order. */
    public static function maskedPhone(?string $phone): string
    {
        return "\u{2066}•••• ".substr((string) $phone, -4)."\u{2069}";
    }
}
