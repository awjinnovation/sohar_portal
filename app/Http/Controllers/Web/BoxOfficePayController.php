<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BoxOfficeOrder;
use App\Models\Event;
use App\Models\Tenant;
use App\Services\Gate\BoxOfficeService;
use App\Services\Payments\PaymentGatewayManager;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Public payment page of a gate box office order (docs/gate_sales_contract.md
 * §4), no login: the unguessable public_token is the secret.
 *
 * GET  /pay/{token}           pending (pay button + countdown) | paid
 *                             (tickets with QR codes) | expired | cancelled
 * POST /pay/{token}/checkout  → the order's gateway payment → the existing
 *                             signed /payment/redirect flow (Bank Muscat, or
 *                             the fake gateway locally). Webhook / return
 *                             pages stay the idempotent ones; they send the
 *                             visitor back here.
 */
class BoxOfficePayController extends Controller
{
    public function __construct(private TenantContext $context, private BoxOfficeService $boxOffice) {}

    public function show(Request $request, string $token)
    {
        $order = $this->boxOffice->expireIfDue($this->order($token));
        $tenant = $this->context->get();
        $lang = $this->lang($request, $order, $tenant);
        $order->load(['lines', 'event.mapLocation']);

        $view = match ($order->status) {
            BoxOfficeOrder::STATUS_PAID => 'payment.box-office.paid',
            BoxOfficeOrder::STATUS_EXPIRED => 'payment.box-office.expired',
            BoxOfficeOrder::STATUS_CANCELLED => 'payment.box-office.cancelled',
            BoxOfficeOrder::STATUS_PAID_UNFULFILLED, BoxOfficeOrder::STATUS_REFUNDED => 'payment.box-office.refund',
            default => 'payment.box-office.pending',
        };
        $tickets = $order->status === BoxOfficeOrder::STATUS_PAID ? $order->issuedTickets()->load('ticketType') : collect();

        return response()->view($view, [
            'order' => $order,
            'event' => $order->event ?? Event::withoutTenantScope()->find($order->event_id),
            'lang' => $lang,
            'tickets' => $tickets,
            'maskedPhone' => $order->maskedPhone($tenant),
            'notice' => in_array($request->query('payment'), ['cancelled', 'failed'], true) ? $request->query('payment') : null,
            'downloadUrl' => $tickets->isNotEmpty() && ($tx = $order->payment?->transaction_id)
                ? URL::temporarySignedRoute('payment.download', now()->addDays(30), ['transactionId' => $tx])
                : null,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function checkout(Request $request, string $token, PaymentGatewayManager $gateways)
    {
        $order = $this->boxOffice->expireIfDue($this->order($token));
        $lang = $this->lang($request, $order, $this->context->get());
        $payment = $order->isPending() ? $this->boxOffice->startPayment($order, $lang) : null;

        if (! $payment) {
            return redirect()->route('box-office.pay', ['token' => $token, 'lang' => $lang]);
        }

        return redirect()->away($gateways->paymentUrl($payment, ['lang' => $lang]))->header('Referrer-Policy', 'no-referrer');
    }

    private function order(string $token): BoxOfficeOrder
    {
        $order = BoxOfficeOrder::withoutTenantScope()->where('public_token', $token)->firstOrFail();
        $tenant = Tenant::findOrFail($order->tenant_id);
        $this->context->set($tenant);
        view()->share('brand', $tenant);

        return $order;
    }

    /** ?lang=, else the language chosen for the buyer at the gate, else the tenant default. */
    private function lang(Request $request, BoxOfficeOrder $order, Tenant $tenant): string
    {
        $locales = array_values(array_intersect(['ar', 'en'], (array) $tenant->setting('tenant.locales', ['ar', 'en']))) ?: ['ar', 'en'];
        $requested = strtolower((string) $request->query('lang', $request->input('lang', '')));
        $lang = in_array($requested, ['ar', 'en'], true) ? $requested
            : (in_array($order->locale, $locales, true) ? $order->locale : (string) $tenant->setting('tenant.default_locale', $locales[0]));
        $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
        app()->setLocale($lang);

        return $lang;
    }
}
