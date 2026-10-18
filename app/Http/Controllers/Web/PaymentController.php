<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PointsTransaction;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\BankMuscatPaymentService;
use App\Services\Payments\BankMuscatCallbackHandler;
use App\Services\Payments\PaymentFulfillmentService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Browser-facing payment pages (opened by the app's web view).
 *
 * - /payment/redirect/{tx}  signed link: auto-posts to Bank Muscat, or shows
 *                           the fake gateway page (PAYMENT_DRIVER=fake).
 * - /payment/success|cancel gateway returns here; state only changes when an
 *                           encrypted gateway response verifies.
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

    public function __construct(private TenantContext $context)
    {
    }

    private function paymentByTransaction(string $transactionId): Payment
    {
        $payment = Payment::withoutTenantScope()->where('transaction_id', $transactionId)->firstOrFail();
        $this->context->set(Tenant::findOrFail($payment->tenant_id));

        return $payment;
    }

    public function redirectToGateway(string $transactionId)
    {
        $payment = $this->paymentByTransaction($transactionId);

        if ($payment->status !== 'pending') {
            return $payment->status === 'completed'
                ? redirect()->route('payment.success', ['transaction_id' => $payment->transaction_id])
                : $this->errorView(self::ERR_NOT_PENDING, $payment);
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
                'cancelled' => view('payment.cancel', ['payment' => $payment, 'code' => 'cancelled']),
                default => $this->errorView(self::ERR_PAYMENT_FAILED, $payment, ['status' => $result['response']['order_status'] ?? 'unknown']),
            };
        } catch (\Throwable $e) {
            Log::error('Payment success page error: '.$e->getMessage());

            return $this->errorView(self::ERR_SERVER_ERROR, null, [
                'error' => config('app.debug') ? $e->getMessage() : null,
            ]);
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

    private function successView(Payment $payment)
    {
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

        if ($encResponse !== '') {
            $result = $handler->handle($encResponse, $request->query('transaction_id'));
            $payment = $result['payment'] ?? null;
        } elseif ($transactionId = $request->query('transaction_id')) {
            $payment = Payment::withoutTenantScope()->where('transaction_id', $transactionId)->first();
        }

        if ($payment) {
            $this->context->set(Tenant::find($payment->tenant_id));
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
