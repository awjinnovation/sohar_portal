<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PointsTransaction;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\BankMuscatPaymentService;
use App\Services\Payments\BankMuscatCallbackHandler;
use App\Services\Payments\Gateway\GatewayException;
use App\Services\Payments\Gateway\ThawaniGateway;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Payments\ThawaniCheckout;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Browser-facing payment pages (opened by the app's web view).
 *
 * - /payment/redirect/{tx}  signed link: redirects to a Thawani session,
 *                           auto-posts to Bank Muscat, or shows the fake
 *                           gateway page (driver fake).
 * - /payment/success|cancel gateway returns here; state only changes when an
 *                           encrypted gateway response verifies (Bank Muscat)
 *                           or Thawani's API confirms it (the return URL
 *                           carries a token; ThawaniCheckout::returnToken()).
 */
class PaymentController extends Controller
{
    /** Error codes of the payment result pages (see payment/error.blade.php). */
    public const ERR_NOT_PENDING = 'not_pending';

    public const ERR_NOT_COMPLETED = 'not_completed';

    public const ERR_NO_RESPONSE = 'no_response';

    public const ERR_VERIFICATION_FAILED = 'verification_failed';

    public const ERR_PAYMENT_FAILED = 'payment_failed';

    public const ERR_SERVER_ERROR = 'server_error';

    public const ERR_GATEWAY_UNAVAILABLE = 'gateway_unavailable';

    public const ERR_GATEWAY_NOT_CONFIGURED = 'gateway_not_configured';

    public function __construct(private TenantContext $context)
    {
    }

    private function paymentByTransaction(string $transactionId): Payment
    {
        $payment = Payment::withoutTenantScope()->where('transaction_id', $transactionId)->firstOrFail();
        $this->context->set(Tenant::findOrFail($payment->tenant_id));

        return $payment;
    }

    private function thawaniPayment(Request $request): ?Payment
    {
        $transactionId = (string) $request->query('transaction_id', '');
        $payment = $transactionId !== '' ? Payment::withoutTenantScope()->where('transaction_id', $transactionId)->first() : null;
        if (! $payment || $payment->gateway !== ThawaniGateway::NAME) {
            return null;
        }
        $this->context->set(Tenant::find($payment->tenant_id));

        return $payment;
    }

    public function redirectToGateway(Request $request, string $transactionId)
    {
        $payment = $this->paymentByTransaction($transactionId);

        // Gate box office: never hand an expired / cancelled order to the
        // gateway, even from a signed link opened before it expired.
        if ($orderId = (int) ($payment->metadata['box_office_order_id'] ?? 0)) {
            $order = \App\Models\BoxOfficeOrder::withoutTenantScope()->find($orderId);
            if ($order && ! app(\App\Services\Gate\BoxOfficeService::class)->expireIfDue($order)->isPending()) {
                return $this->boxOfficeRedirect($payment->fresh());
            }
        }

        if ($payment->status !== 'pending') {
            return $payment->status === 'completed'
                ? redirect()->route('payment.success', ['transaction_id' => $payment->transaction_id])
                : $this->errorView(self::ERR_NOT_PENDING, $payment);
        }

        if ($payment->gateway === ThawaniGateway::NAME) {
            try {
                return redirect()->away(app(ThawaniCheckout::class)->checkoutUrl($payment, 'redirect'));
            } catch (GatewayException|LockTimeoutException $e) {
                Log::warning('Thawani checkout could not start', ['transaction_id' => $payment->transaction_id, 'error' => $e->getMessage()]);
                $notConfigured = $e instanceof GatewayException && $e->kind === 'config';

                return $this->errorView($notConfigured ? self::ERR_GATEWAY_NOT_CONFIGURED : self::ERR_GATEWAY_UNAVAILABLE, $payment, [
                    'retryUrl' => $notConfigured ? null : $request->fullUrl(),
                ]);
            }
        }

        if ($payment->gateway === 'fake') {
            return view('payment.fake', [
                'payment' => $payment,
                'completeUrl' => URL::temporarySignedRoute('payment.fake.complete', now()->addMinutes((int) config('payments.link_ttl_minutes', 60)), [
                    'transaction_id' => $payment->transaction_id,
                ]),
            ]);
        }

        $service = BankMuscatPaymentService::forTenant($this->context->get());
        $paymentData = $service->preparePaymentData($payment);
        $encryptedData = $service->generateEncryptedRequest($paymentData);

        if (! $payment->bank_muscat_tid) {
            $payment->update([
                'bank_muscat_tid' => $paymentData['tid'],
                'bank_muscat_order_id' => $paymentData['order_id'],
            ]);
        }

        return response()->view('payment.redirect', [
            'gatewayUrl' => $service->getGatewayUrl(),
            'encRequest' => $encryptedData,
            'accessCode' => $service->getAccessCode(),
        ]);
    }

    /**
     * Fake driver: "Simulate success / failure" (signed URL, non-production only).
     */
    public function fakeComplete(Request $request, string $transactionId, PaymentFulfillmentService $fulfillment)
    {
        abort_if(app()->environment('production'), 404);
        $payment = $this->paymentByTransaction($transactionId);
        abort_unless($payment->gateway === 'fake', 404);

        $result = $request->input('result', 'success');
        $response = [
            'order_id' => $payment->transaction_id,
            'order_status' => $result === 'success' ? 'Success' : 'Failure',
            'amount' => $this->context->get()->formatMoney((int) $payment->amount_minor),
            'currency' => $payment->currency,
            'payment_mode' => 'fake',
            'tracking_id' => 'FAKE'.now()->timestamp,
        ];

        if ($result === 'success') {
            $fulfillment->complete($payment, $response, 'fake');

            return redirect()->route('payment.success', ['transaction_id' => $payment->transaction_id]);
        }

        $fulfillment->fail($payment, $result === 'cancel' ? 'cancelled' : 'failed', $response);

        return redirect()->route('payment.cancel', ['transaction_id' => $payment->transaction_id]);
    }

    public function success(Request $request, BankMuscatCallbackHandler $handler)
    {
        try {
            $encResponse = (string) $request->input('encResp', '');

            if ($encResponse === '' && ($payment = $this->thawaniPayment($request))) {
                return $this->thawaniResult($request, $payment);
            }

            if ($encResponse === '') {
                // Plain revisit (e.g. app polling page): display only, never mutate.
                $transactionId = (string) $request->query('transaction_id', '');
                $payment = $transactionId ? Payment::withoutTenantScope()->where('transaction_id', $transactionId)->first() : null;
                if ($payment) {
                    $this->context->set(Tenant::find($payment->tenant_id));
                }
                if ($payment && $payment->status === 'completed') {
                    return $this->successView($payment);
                }

                return $this->errorView($payment ? self::ERR_NOT_COMPLETED : self::ERR_NO_RESPONSE, $payment);
            }

            $result = $handler->handle($encResponse, $request->query('transaction_id'));
            if (! $result) {
                return $this->errorView(self::ERR_VERIFICATION_FAILED);
            }

            $payment = $result['payment'];
            $this->context->set(Tenant::find($payment->tenant_id));

            return match ($result['status']) {
                'completed' => $this->successView($payment),
                'cancelled' => $this->boxOfficeRedirect($payment, ['payment' => 'cancelled']) ?? view('payment.cancel', ['payment' => $payment, 'code' => 'cancelled']),
                default => $this->boxOfficeRedirect($payment, ['payment' => 'failed'])
                    ?? $this->errorView(self::ERR_PAYMENT_FAILED, $payment, ['status' => $result['response']['order_status'] ?? 'unknown']),
            };
        } catch (\Throwable $e) {
            Log::error('Payment success page error: '.$e->getMessage());

            return $this->errorView(self::ERR_SERVER_ERROR, null, [
                'error' => config('app.debug') ? $e->getMessage() : null,
            ]);
        }
    }

    /** Thawani return page: ask Thawani (throttled per payment), then show the result. */
    private function thawaniResult(Request $request, Payment $payment)
    {
        $payment = $this->thawaniCheck($payment);

        return match (true) {
            $payment->status === 'completed' => $this->successView($payment),
            $payment->isPending() => $this->errorView(self::ERR_NOT_COMPLETED, $payment, ['retryUrl' => $request->fullUrl()]),
            $payment->status === 'cancelled' => $this->boxOfficeRedirect($payment, ['payment' => 'cancelled']) ?? view('payment.cancel', ['payment' => $payment, 'code' => 'cancelled']),
            default => $this->boxOfficeRedirect($payment, ['payment' => 'failed']) ?? $this->errorView(self::ERR_PAYMENT_FAILED, $payment),
        };
    }

    /** Throttled per payment (repeated reloads don't multiply gateway calls); never throws. */
    private function thawaniCheck(Payment $payment): Payment
    {
        return app(ThawaniCheckout::class)->checkIfStale($payment, 'redirect');
    }

    private function thawaniCancel(Payment $payment): Payment
    {
        try {
            return app(ThawaniCheckout::class)->cancel($payment, 'redirect', 'visitor_cancelled');
        } catch (LockTimeoutException) {
            return $payment->fresh();
        }
    }

    /**
     * Result pages get an error *code*; resources/views/payment/error.blade.php
     * maps it to localized text (ar/en).
     */
    private function errorView(string $code, ?Payment $payment = null, array $extra = [])
    {
        return view('payment.error', array_merge(['code' => $code, 'payment' => $payment], $extra));
    }

    /** A payment made from a gate box office link returns to its /pay/{token} page. */
    private function boxOfficeRedirect(?Payment $payment, array $query = [])
    {
        $orderId = (int) ($payment?->metadata['box_office_order_id'] ?? 0);
        $order = $orderId ? \App\Models\BoxOfficeOrder::withoutTenantScope()->find($orderId) : null;
        if (! $order) {
            return null;
        }
        $lang = $payment->metadata['box_office_lang'] ?? $order->locale;

        return redirect()->route('box-office.pay', array_filter(['token' => $order->public_token, 'lang' => $lang] + $query));
    }

    private function successView(Payment $payment)
    {
        if ($redirect = $this->boxOfficeRedirect($payment)) {
            return $redirect;
        }
        $isTicket = $payment->payment_type === Payment::TYPE_TICKET;
        $tickets = $isTicket
            ? Ticket::with('event')->where('transaction_id', $payment->transaction_id)->orderBy('id')->get()
            : collect();
        $pointsEarned = (int) PointsTransaction::where('reference_type', $payment->getMorphClass())
            ->where('reference_id', $payment->getKey())
            ->where('type', 'earn')
            ->sum('points');

        return view('payment.success', [
            'payment' => $payment,
            'transactionId' => $payment->transaction_id,
            'tickets' => $tickets,
            'pointsEarned' => $pointsEarned,
            'downloadUrl' => $isTicket
                ? URL::temporarySignedRoute('payment.download', now()->addDays(30), ['transactionId' => $payment->transaction_id])
                : null,
        ]);
    }

    /**
     * Gateway cancel return. Only an encrypted, verified gateway response
     * changes the payment; a bare ?transaction_id= just renders the page
     * (the app cancels its own checkout via the authenticated API).
     */
    public function cancel(Request $request, BankMuscatCallbackHandler $handler)
    {
        $encResponse = (string) $request->input('encResp', '');
        $payment = null;

        if ($encResponse === '' && ($thawani = $this->thawaniPayment($request))) {
            // Only Thawani's own redirect (it carries the token) cancels:
            // Thawani is asked first, and its sessions are cancelled too.
            $payment = hash_equals(ThawaniCheckout::returnToken($thawani->transaction_id), (string) $request->query('t', ''))
                ? $this->thawaniCancel($thawani)
                : $this->thawaniCheck($thawani);
            if ($payment->status === 'completed') {
                return $this->successView($payment); // paid after all
            }
        } elseif ($encResponse !== '') {
            $result = $handler->handle($encResponse, $request->query('transaction_id'));
            $payment = $result['payment'] ?? null;
        } elseif ($transactionId = $request->query('transaction_id')) {
            $payment = Payment::withoutTenantScope()->where('transaction_id', $transactionId)->first();
        }

        if ($payment) {
            $this->context->set(Tenant::find($payment->tenant_id));
            if ($redirect = $this->boxOfficeRedirect($payment, ['payment' => $payment->status === 'failed' ? 'failed' : 'cancelled'])) {
                return $redirect;
            }
        }

        return view('payment.cancel', ['payment' => $payment, 'code' => 'cancelled']);
    }

    /** Signed link from the success page. */
    public function downloadTicket(string $transactionId)
    {
        $payment = $this->paymentByTransaction($transactionId);
        abort_unless($payment->status === 'completed', 404);

        $tickets = Ticket::with('event')->where('transaction_id', $transactionId)->get();

        return view('payment.download', ['payment' => $payment, 'tickets' => $tickets]);
    }

    /**
     * Public ticket page; the QR code itself is the (unguessable) bearer
     * secret. Used / cancelled tickets are shown too, with their status.
     */
    public function downloadSingleTicket(string $qrCode)
    {
        $ticket = Ticket::withoutTenantScope()
            ->where('qr_code', $qrCode)
            ->firstOrFail();
        $this->context->set(Tenant::find($ticket->tenant_id));
        $ticket->load(['event.mapLocation', 'user', 'payment']);

        return view('payment.single-ticket', [
            'ticket' => $ticket,
            'downloadUrl' => $ticket->transaction_id
                ? URL::temporarySignedRoute('payment.download', now()->addDays(30), ['transactionId' => $ticket->transaction_id])
                : null,
        ]);
    }
}
