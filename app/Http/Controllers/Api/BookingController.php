<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\VisitorBookingController;
use App\Http\Middleware\ResolveBookingEvent;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Gate\BoxOfficeService;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\GatewayCheckouts;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use App\View\PublicPage;
use App\View\TenantTheme;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * API of the event booking site (booking-web/, its own domain and Docker
 * image), the same flow as the page on this server
 * (Web\VisitorBookingController): phone + code, then card checkout of the
 * event's app-channel ticket types. The tenant comes from the event
 * (ResolveBookingEvent), the sign-in is a Sanctum token kept in the browser.
 *
 * GET  /api/v1/booking/events/{event}           public, CDN-cacheable: event, ticket types,
 *                                               days, tenant branding (?lang=ar|en)
 * POST /api/v1/booking/events/{event}/otp       text a code (phone as typed: 9123 4567, +968…)
 * POST /api/v1/booking/events/{event}/verify    check it → token (VISITOR_SESSION_DAYS)
 * GET  /api/v1/booking/events/{event}/tickets   the visitor's tickets for this event   (token)
 * POST /api/v1/booking/events/{event}/checkout  order → payment_url, or completed if free (token)
 * POST /api/v1/booking/events/{event}/logout    revoke the token                      (token)
 * POST /api/v1/booking/events/{event}/guest/checkout  order without signing in → payment_url | completed,
 *                                               transaction_id + guest_token (no phone, no SMS)
 * GET  /api/v1/booking/events/{event}/guest/tickets   ?transaction_id&token → status + that order's tickets
 *                                               (booking.guest_view_hours after the order; the site keeps the
 *                                               key in the browser window only, so the visitor saves a PDF)
 *
 * Errors: {success:false, code, message, data?}. Codes the site shows a
 * message for: INVALID_PHONE, OTP_INVALID (data.attempts_remaining),
 * OTP_EXPIRED, OTP_ATTEMPTS_EXCEEDED, TOO_MANY_REQUESTS, ITEMS_REQUIRED,
 * DATE_REQUIRED, DATE_INVALID, SOLD_OUT (data.available), PER_ORDER_LIMIT
 * (data.per_order_max), SALES_CLOSED, TYPE_NOT_AVAILABLE, UNAVAILABLE,
 * GATEWAY_NOT_AVAILABLE (checkout `gateway` not on; data.available),
 * GUEST_CHECKOUT_OFF, GUEST_TICKETS_EXPIRED, NOT_FOUND.
 *
 * Gateways: GET → `payments.gateways` [{id, name}] (names in ?lang), empty
 * when only one gateway is on; checkout accepts `gateway` (one of those ids,
 * absent = the default).
 */
class BookingController extends Controller
{
    public function __construct(private TenantContext $context, private OtpService $otp) {}

    public function show(Request $request): JsonResponse
    {
        [$event, $tenant, $lang] = $this->scope($request);

        $types = $event->sellableTicketTypes(EventTicketType::CHANNEL_APP);
        $dates = $types->contains(fn (EventTicketType $t) => $t->isDated()) ? VisitorBookingController::bookableDates($event, $tenant) : [];
        $today = TenantTime::today($tenant);
        $tomorrow = Carbon::parse($today)->addDay()->toDateString();
        $start = $event->start_time?->copy()->locale($lang);
        $end = $event->end_time?->copy()->locale($lang);
        $sameDay = $start && $end && $end->toDateString() === $start->toDateString();
        $venue = $event->mapLocation ? PublicPage::pick($event->mapLocation, 'name', $lang) : PublicPage::pick($event, 'location', $lang);
        $other = $lang === 'ar' ? 'en' : 'ar';
        $legal = array_filter(['tenant' => $tenant->slug, 'lang' => $lang]);

        return response()->json([
            'success' => true,
            'data' => [
                'lang' => $lang,
                'tenant' => [
                    'slug' => $tenant->slug,
                    'name' => $tenant->localizedName($lang),
                    // Saved ticket images: {file_prefix}_ticket_{number}.png (English name, as on the ticket pages).
                    'file_prefix' => \Illuminate\Support\Str::slug($tenant->localizedName('en')) ?: 'event',
                    'locales' => array_values(array_intersect(['ar', 'en'], (array) $tenant->setting('tenant.locales', ['ar', 'en']))),
                    'organization' => $tenant->setting("tenant.organization.$lang") ?: ($tenant->setting("tenant.organization.$other") ?: null),
                    'support_email' => $tenant->setting('tenant.support.email') ?: null,
                    'support_phone' => $tenant->setting('tenant.support.phone') ?: null,
                    'privacy_url' => Route::has('public.privacy-policy') ? route('public.privacy-policy', $legal) : null,
                    'terms_url' => Route::has('public.terms-and-conditions') ? route('public.terms-and-conditions', $legal) : null,
                    'country_code' => (string) $tenant->setting('auth.country_code', '968'),
                    'phone_hint' => (string) $tenant->setting('auth.phone_hint', '9123 4567'),
                    'phone_length' => (int) $tenant->setting('auth.phone_length', 8),
                    'currency' => $tenant->currency(),
                    'currency_decimals' => $tenant->currencyDecimals(),
                    'theme' => TenantTheme::clientTokens($tenant),
                ],
                'event' => [
                    'id' => $event->id,
                    'title' => PublicPage::pick($event, 'title', $lang),
                    'date_label' => $start ? $start->translatedFormat('j F').($end && ! $sameDay ? ' – '.$end->translatedFormat('j F') : '') : null,
                    'time_label' => $start ? $start->format('H:i').($end ? ' – '.$end->format('H:i') : '') : null,
                    'venue' => $venue ?: null,
                    'image_url' => $event->image_url,
                    'ended' => $event->hasEnded(),
                    // For search engines (the booking site's title, description and
                    // schema.org Event): the description in this language, the event's
                    // and organizer's names in the other languages, the organizer's
                    // search words, and the times with their offset.
                    'description' => PublicPage::pick($event, 'description', $lang) ?: null,
                    'other_names' => $this->otherNames($event, $tenant, $lang),
                    'search_terms' => $event->searchTerms(),
                    'starts_at' => $event->start_time?->toIso8601String(),
                    'ends_at' => $event->end_time?->toIso8601String(),
                ],
                'ticket_types' => $types->map(fn (EventTicketType $t) => [
                    'id' => $t->id,
                    'name' => $t->nameText($lang),
                    'color' => $t->colorValue(),
                    'price_minor' => (int) $t->price_minor,
                    'dated' => $t->isDated(),
                    'validity_label' => $t->isDated() ? null : EventTicketType::validityLabel($t->validityValue(), $lang),
                    'admits' => max(1, (int) $t->admits),
                    'admits_label' => max(1, (int) $t->admits) > 1 ? trans_choice('gate.people', (int) $t->admits, ['count' => (int) $t->admits], $lang) : null,
                    'requires_id' => (bool) $t->requires_id,
                    // The type's own «الحد الأقصى في الطلب» (admin → event → ticket types; MSR-402).
                    'max_quantity' => $t->perOrderMax(),
                    'sales_open' => $t->salesOpen(),
                ])->values()->all(),
                'dates' => array_map(fn (array $d) => $d + [
                    'relative' => $d['date'] === $today ? 'today' : ($d['date'] === $tomorrow ? 'tomorrow' : null),
                    'day_label' => Carbon::parse($d['date'])->locale($lang)->translatedFormat('l'),
                    'num_label' => Carbon::parse($d['date'])->locale($lang)->translatedFormat('j M'),
                ], $dates),
                'today' => $today,
                // Two or more gateways on: the site shows one pay button each; [] = one button, the default gateway.
                'payments' => [
                    'gateways' => VisitorBookingController::gatewayChoices($tenant, $lang),
                    // Booking without signing in is offered (POST …/guest/checkout).
                    'guest' => (bool) config('booking.guest_checkout', true),
                ],
            ],
        ]);
    }

    public function sendOtp(Request $request, BoxOfficeService $boxOffice): JsonResponse
    {
        [, $tenant, $lang] = $this->scope($request);
        $request->validate(['phone_number' => ['required', 'string', 'max:20']]);
        $phone = $boxOffice->normalizePhone($tenant, (string) $request->input('phone_number'));

        $sent = $this->otp->send($phone, $tenant, $lang);

        return response()->json(['success' => true, 'data' => array_filter([
            'phone' => $phone,
            'phone_masked' => VisitorBookingController::maskedPhone($phone),
            'expires_in' => $sent['expires_in'],
            'otp' => config('auth.otp.debug') && ! app()->environment('production') ? $sent['code'] : null,
        ], fn ($v) => $v !== null)]);
    }

    public function verifyOtp(Request $request, BoxOfficeService $boxOffice): JsonResponse
    {
        [, $tenant] = $this->scope($request);
        $request->validate(['phone_number' => ['required', 'string', 'max:20'], 'otp' => ['required', 'string', 'max:12']]);
        $phone = $boxOffice->normalizePhone($tenant, (string) $request->input('phone_number'));

        $result = $this->otp->verify($phone, preg_replace('/\D/', '', (string) $request->input('otp')));
        if (! $result['ok']) {
            return match (true) {
                $result['code'] === OtpService::ATTEMPTS_EXCEEDED => $this->error('OTP_ATTEMPTS_EXCEEDED', 'Too many incorrect attempts. Please request a new code.', 429),
                isset($result['attempts_remaining']) => $this->error('OTP_INVALID', 'Invalid code', 422, ['attempts_remaining' => $result['attempts_remaining']]),
                default => $this->error('OTP_EXPIRED', 'The code has expired', 422),
            };
        }

        $user = $this->otp->userFor($phone);
        $expires = now()->addDays(max(1, (int) config('booking.token_days', 30)));
        $token = $user->createToken('booking-web', ['*'], $expires)->plainTextToken;

        return response()->json(['success' => true, 'data' => [
            'token' => $token,
            'expires_at' => $expires->toIso8601String(),
            'phone_masked' => VisitorBookingController::maskedPhone($user->phone_number),
        ]]);
    }

    public function tickets(Request $request): JsonResponse
    {
        [$event, , $lang] = $this->scope($request);
        $user = $request->user();

        $tickets = Ticket::with('ticketType')->where('user_id', $user->id)->where('event_id', $event->id)
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['success' => true, 'data' => [
            'phone_masked' => VisitorBookingController::maskedPhone($user->phone_number),
            'tickets' => $this->ticketRows($tickets, $lang),
        ]]);
    }

    /** @param \Illuminate\Support\Collection<int, Ticket> $tickets */
    private function ticketRows($tickets, string $lang): array
    {
        return $tickets->map(function (Ticket $ticket) use ($lang) {
                $type = $ticket->ticketType;
                $admits = $ticket->admits();

                return [
                    'id' => $ticket->id,
                    'number' => $ticket->ticket_number ?: $ticket->qr_code,
                    'qr_code' => $ticket->scanCode(),
                    'status' => $ticket->status,
                    'type_name' => $type ? $type->nameText($lang) : $ticket->ticket_type,
                    'type_color' => $type?->colorValue() ?? EventTicketType::CATEGORY_COLORS['general'],
                    'when_label' => $ticket->booking_date
                        ? $ticket->booking_date->copy()->locale($lang)->translatedFormat('l j F Y')
                        : EventTicketType::validityLabel($ticket->validityValue(), $lang),
                    'admits' => $admits,
                    'admits_label' => $admits > 1 ? trans_choice('gate.people', $admits, ['count' => $admits], $lang) : null,
                    'requires_id' => (bool) $type?->requires_id,
                ];
            })->values()->all();
    }

    public function checkout(Request $request, CheckoutService $checkout, PaymentGatewayManager $gateways): JsonResponse
    {
        $payment = $this->placeOrder($request, $request->user(), $checkout);

        return $payment instanceof JsonResponse ? $payment : $this->orderResponse($payment, $gateways, $request);
    }

    /**
     * Booking without signing in: no phone, no code, no SMS. The order
     * belongs to the tenant's web guest account; the visitor sees its
     * tickets through guest_token in the browser window that booked them.
     */
    public function guestCheckout(Request $request, CheckoutService $checkout, PaymentGatewayManager $gateways): JsonResponse
    {
        [, $tenant] = $this->scope($request);
        if (! config('booking.guest_checkout', true)) {
            return $this->error('GUEST_CHECKOUT_OFF', 'Sign in with your phone number to book', 403);
        }
        // The idempotency key comes from the form key: each browser form has its own.
        $request->validate(['form_key' => ['required', 'string', 'min:16', 'max:64']]);

        $payment = $this->placeOrder($request, self::guestUser($tenant), $checkout, ['guest_booking' => true]);
        if ($payment instanceof JsonResponse) {
            return $payment;
        }

        return $this->orderResponse($payment, $gateways, $request, [
            'transaction_id' => $payment->transaction_id,
            'guest_token' => self::guestToken($payment->transaction_id),
        ]);
    }

    /** Status and tickets of one guest order, for the window holding its key. */
    public function guestTickets(Request $request): JsonResponse
    {
        [$event, , $lang] = $this->scope($request);
        $data = $request->validate(['transaction_id' => ['required', 'string', 'max:64'], 'token' => ['required', 'string', 'max:128']]);

        $payment = Payment::where('transaction_id', $data['transaction_id'])->first();
        if (! $payment || ! ($payment->metadata['guest_booking'] ?? false)
            || (int) ($payment->metadata['event_id'] ?? $payment->payable_id) !== (int) $event->id
            || ! hash_equals(self::guestToken($payment->transaction_id), (string) $data['token'])) {
            return $this->error('NOT_FOUND', 'Booking not found', 404);
        }
        if ($payment->created_at->lt(now()->subHours(max(1, (int) config('booking.guest_view_hours', 24))))) {
            return $this->error('GUEST_TICKETS_EXPIRED', 'These tickets can no longer be shown here', 410);
        }

        // Like the app's status poll: a pending card payment is checked with the gateway (throttled).
        $payment = GatewayCheckouts::checkIfStale($payment);
        $tickets = $payment->status === 'completed'
            ? Ticket::with('ticketType')->where('transaction_id', $payment->transaction_id)->where('event_id', $event->id)->orderBy('id')->get()
            : collect();

        return response()->json(['success' => true, 'data' => [
            'status' => $payment->status,
            'tickets' => $this->ticketRows($tickets, $lang),
        ]])->header('Cache-Control', 'no-store, private');
    }

    /** The tenant's account that owns orders made without signing in (nobody signs in to it). */
    public static function guestUser(Tenant $tenant): User
    {
        return User::firstOrCreate(
            ['email' => 'web-guest+'.$tenant->id.'@guest.invalid'],
            ['name' => 'Web guest', 'password' => Hash::make(Str::random(40))],
        );
    }

    /** Key of one guest order's tickets (kept by the booking window that made it). */
    public static function guestToken(string $transactionId): string
    {
        return substr(hash_hmac('sha256', 'booking-guest|'.$transactionId, (string) config('app.key')), 0, 40);
    }

    /** Validates and places the order; an error response when it cannot be placed. */
    private function placeOrder(Request $request, User $user, CheckoutService $checkout, array $extraMeta = []): Payment|JsonResponse
    {
        [$event, $tenant, $lang] = $this->scope($request);
        $data = $request->validate([
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:'.EventTicketType::PER_ORDER_LIMIT],
            'form_key' => ['nullable', 'string', 'max:64'],
            'gateway' => ['nullable', 'string', 'max:40'],
        ]);
        $items = collect($data['items'] ?? [])
            ->map(fn ($item) => ['ticket_type_id' => (int) $item['ticket_type_id'], 'quantity' => (int) $item['quantity']])
            ->filter(fn ($item) => $item['quantity'] > 0)
            ->values()->all();
        if (! $items) {
            return $this->error('ITEMS_REQUIRED', 'Choose at least one ticket', 422);
        }
        $date = $data['booking_date'] ?? null;
        if ($date !== null && $date < TenantTime::today($tenant)) {
            return $this->error('DATE_INVALID', 'That day is not available', 422);
        }

        try {
            $payment = $checkout->initialize($user, $tenant, [
                'event_id' => $event->id,
                'booking_date' => $date,
                'items' => $items,
                'method' => Payment::METHOD_GATEWAY,
                'gateway' => $data['gateway'] ?? null,
                // A double tap of the same order reuses its payment; a changed order (or gateway) is a new one (as on the page).
                'idempotency_key' => VisitorBookingController::webKey($data['form_key'] ?? null, $items, $date, $data['gateway'] ?? null),
            ], ['web_booking' => true, 'web_booking_lang' => $lang] + $extraMeta);
        } catch (ValidationException $e) {
            return isset($e->errors()['booking_date'])
                ? $this->error('DATE_REQUIRED', 'Choose the day of your visit', 422)
                : $this->error('ITEMS_REQUIRED', 'Choose at least one ticket', 422);
        }

        return $payment;
    }

    private function orderResponse(Payment $payment, PaymentGatewayManager $gateways, Request $request, array $extra = []): JsonResponse
    {
        [, , $lang] = $this->scope($request);

        return match (true) {
            $payment->status === 'completed' => response()->json(['success' => true, 'data' => ['status' => 'completed'] + $extra]),
            $payment->isPending() => response()->json(['success' => true, 'data' => [
                'status' => 'pending',
                'payment_url' => $gateways->paymentUrl($payment, ['lang' => $lang]),
            ] + $extra]),
            default => $this->error('UNAVAILABLE', 'Booking is not available right now', 409),
        };
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['success' => true]);
    }

    /** @return array{0: Event, 1: Tenant, 2: string} the event (ResolveBookingEvent), its tenant, the language. */
    /**
     * The event's and organizer's names other than the one shown in $lang
     * (e.g. "Suhar Festival" beside «مهرجان صحار»), without repeats.
     *
     * @return list<string>
     */
    private function otherNames(Event $event, Tenant $tenant, string $lang): array
    {
        $shown = mb_strtolower(trim(PublicPage::pick($event, 'title', $lang)));
        $names = [];
        foreach ([$event->title, $event->title_ar, $tenant->localizedName('ar'), $tenant->localizedName('en')] as $name) {
            $name = trim((string) $name);
            $key = mb_strtolower($name);
            if ($name !== '' && $key !== $shown && $key !== mb_strtolower($tenant->slug) && ! isset($names[$key])) {
                $names[$key] = $name;
            }
        }

        return array_values($names);
    }

    private function scope(Request $request): array
    {
        return [$request->attributes->get(ResolveBookingEvent::EVENT), $this->context->get(), app()->getLocale()];
    }

    private function error(string $code, string $message, int $status, array $data = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data ?: null,
        ], fn ($v) => $v !== null), $status);
    }
}
