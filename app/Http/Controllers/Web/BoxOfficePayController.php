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
 * GET  /pay/{token}           pending (pay button + countdown; one button per
 *                             gateway when two are on) | paid (tickets with QR
 *                             codes) | expired | cancelled
 * POST /pay/{token}/checkout  {gateway?} → the order's gateway payment → the
 *                             existing signed /payment/redirect flow (Thawani,
 *                             Amwal SmartBox, Bank Muscat, or the fake gateway
 *                             locally). Webhook / return pages stay the
 *                             idempotent ones; they send the visitor back here.
 */
class BoxOfficePayController extends Controller
{
    public function __construct(private TenantContext $context, private BoxOfficeService $boxOffice) {}

    public function show(Request $request, string $token, PaymentGatewayManager $gateways)
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
            // Two or more gateways on: one pay button each; one: the single button as before.
            'gateways' => $order->status === BoxOfficeOrder::STATUS_PENDING ? $this->choices($gateways) : [],
            'notice' => in_array($request->query('payment'), ['cancelled', 'failed', 'unavailable'], true) ? $request->query('payment') : null,
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
        // Sent by the per-gateway buttons only; absent = the default, as before.
        $gateway = is_string($request->input('gateway')) && $request->input('gateway') !== '' ? substr($request->input('gateway'), 0, 40) : null;

        try {
            $payment = $order->isPending() ? $this->boxOffice->startPayment($order, $lang, $gateway) : null;
        } catch (\App\Exceptions\ApiException $e) {
            // The gateway was switched off after the page was opened: the page now shows the ones that are on.
            return redirect()->route('box-office.pay', ['token' => $token, 'lang' => $lang, 'payment' => 'unavailable']);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return redirect()->route('box-office.pay', ['token' => $token, 'lang' => $lang]);
        }

        if (! $payment) {
            return redirect()->route('box-office.pay', ['token' => $token, 'lang' => $lang]);
        }

        return redirect()->away($gateways->paymentUrl($payment, ['lang' => $lang]))->header('Referrer-Policy', 'no-referrer');
    }

    /** @return list<array{id: string, name: array{ar: string, en: string}}> the gateways to offer, or [] when there is no choice */
    private function choices(PaymentGatewayManager $gateways): array
    {
        try {
            $enabled = $gateways->enabled($this->context->get());
        } catch (\RuntimeException) {
            return [];
        }

        return count($enabled) > 1
            ? array_map(fn (string $id) => ['id' => $id, 'name' => PaymentGatewayManager::NAMES[$id] ?? ['ar' => $id, 'en' => $id]], $enabled)
            : [];
    }

    private function order(string $token): BoxOfficeOrder
    {
        $order = BoxOfficeOrder::withoutTenantScope()->where('public_token', $token)->firstOrFail();
        // Paid on the gate's card terminal (§9): there is no payment link.
        abort_if($order->isCardTerminal() && $order->total_minor > 0, 404);
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
