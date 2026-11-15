<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Services\Payments\Gateway\AmwalConfig;
use App\Services\Payments\Gateway\ThawaniConfig;
use App\Services\Payments\GatewayCheckouts;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentRecorder;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin → Payments → Gateway test (platform admins): both card gateways'
 * settings at a glance, and a small real charge through the one chosen —
 * whether or not it is switched on for visitors (payments.gateways) — then
 * check / void / refund it
 * here. A test payment (payment_type gateway_test) issues nothing and is not
 * revenue; it goes through the same pages, webhooks, reconciler and refund
 * code as a real one.
 */
class GatewayTestController extends Controller
{
    public const GATEWAYS = ['amwal', 'thawani'];

    public const MAX_MINOR = 5000; // 5 OMR

    public function __construct(private TenantContext $context) {}

    private function gateways(): array
    {
        return app()->environment('production') ? self::GATEWAYS : [...self::GATEWAYS, 'fake'];
    }

    public function index(Request $request, PaymentGatewayManager $manager)
    {
        $tenant = $this->context->get();
        abort_unless($tenant, 404);
        $amwal = AmwalConfig::forTenant($tenant);
        $thawani = ThawaniConfig::forTenant($tenant);

        $status = [
            'amwal' => [
                'configured' => $amwal->isConfigured(),
                'mode' => $amwal->mode,
                'own' => filled(data_get($tenant->gateway_credentials ?? [], 'amwal.secure_key')),
                'details' => $amwal->isConfigured() ? 'MID '.$amwal->merchantId.' · TID '.$amwal->terminalId : null,
                'webhook' => route('payments.webhooks.amwal', ['tenant' => $tenant->slug]),
            ],
            'thawani' => [
                'configured' => $thawani->isConfigured(),
                'mode' => $thawani->mode,
                'own' => filled(data_get($tenant->gateway_credentials ?? [], 'thawani.secret_key')),
                'details' => $thawani->isConfigured() ? 'pk …'.substr($thawani->publishableKey, -4) : null,
                'webhook' => route('payments.webhooks.thawani', ['tenant' => $tenant->slug]),
            ],
        ];

        $payments = Payment::where('payment_type', Payment::TYPE_GATEWAY_TEST)->latest('id')->limit(15)->get();
        $sessions = PaymentGatewaySession::whereIn('payment_id', $payments->pluck('id'))->latest('id')->get()->groupBy('payment_id');
        $refunds = PaymentRefund::whereIn('payment_id', $payments->pluck('id'))->get()->keyBy('payment_id');
        $focus = $request->integer('payment') ? $payments->firstWhere('id', $request->integer('payment')) : null;
        if ($focus && $focus->isPending()) {
            // Just back from the gateway: ask it now (throttled) so the result shows.
            $focus = GatewayCheckouts::checkIfStale($focus, 'redirect');
            $payments = $payments->map(fn ($p) => $p->id === $focus->id ? $focus : $p);
        }

        try {
            $enabled = $manager->enabled($tenant);
        } catch (\RuntimeException) {
            $enabled = [];
        }

        return view('admin.payments.gateway-test', [
            'tenant' => $tenant,
            // Switched on for visitors, the default first (a test payment does not depend on it).
            'enabled' => $enabled,
            'gateways' => $this->gateways(),
            'status' => $status,
            'payments' => $payments,
            'sessions' => $sessions,
            'refunds' => $refunds,
            'focus' => $focus,
        ]);
    }

    /** POST: create a test payment on the chosen gateway and open its payment page. */
    public function store(Request $request, PaymentGatewayManager $manager, PaymentRecorder $recorder)
    {
        $tenant = $this->context->get();
        abort_unless($tenant, 404);
        $data = $request->validate([
            'gateway' => ['required', Rule::in($this->gateways())],
            'amount' => ['required', 'numeric', 'min:0.1', 'max:'.(self::MAX_MINOR / 1000)],
            'lang' => ['nullable', Rule::in(['ar', 'en'])],
        ]);
        $configured = match ($data['gateway']) {
            'amwal' => AmwalConfig::forTenant($tenant)->isConfigured(),
            'thawani' => ThawaniConfig::forTenant($tenant)->isConfigured(),
            default => true,
        };
        if (! $configured) {
            return back()->withInput()->withErrors(['gateway' => __('أدخل بيانات هذه البوابة في إعدادات المنظمة أولًا.')]);
        }
        $minor = (int) round(((float) $data['amount']) * 10 ** $tenant->currencyDecimals());

        $payment = new Payment([
            'user_id' => $request->user()->id,
            'transaction_id' => 'TST'.strtoupper(Str::random(12)),
            'payment_type' => Payment::TYPE_GATEWAY_TEST,
            'method' => Payment::METHOD_GATEWAY,
            // The chosen gateway, on for visitors or not: test before switching it on.
            'gateway' => $data['gateway'],
            'amount' => Money::format($minor, $tenant->currencyDecimals()),
            'amount_minor' => $minor,
            'currency' => $tenant->currency(),
            'status' => 'pending',
            'metadata' => ['gateway_test' => true, 'currency_decimals' => $tenant->currencyDecimals(), 'quantity' => 1],
        ]);
        $payment->tenant_id = $tenant->id;
        $payment->save();
        $recorder->record($payment, 'created', 'admin', ['type' => Payment::TYPE_GATEWAY_TEST, 'gateway' => $payment->gateway, 'amount_minor' => $minor], null, 'pending');
        AuditLog::record('payment.gateway_test', $payment, ['gateway' => $payment->gateway, 'amount_minor' => $minor]);

        return redirect()->away($manager->paymentUrl($payment, array_filter(['lang' => $data['lang'] ?? null])));
    }
}
