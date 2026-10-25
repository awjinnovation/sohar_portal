<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\BankMuscatPaymentService;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Bank Muscat encrypted response (webhook or browser redirect)
 * and applies it through PaymentFulfillmentService.
 *
 * The working key is per tenant, so the payment (and thus tenant) is found
 * from the transaction id we put in the redirect URL; without a hint every
 * configured key is tried. A response only counts if it decrypts with the
 * payment's tenant key AND its order_id / currency / amount match.
 */
class BankMuscatCallbackHandler
{
    public function __construct(private PaymentFulfillmentService $fulfillment) {}

    /**
     * @return array{payment: Payment, response: array, status: string}|null
     */
    public function handle(string $encResponse, ?string $transactionIdHint = null): ?array
    {
        $candidates = [];
        if ($transactionIdHint) {
            $hinted = Payment::withoutTenantScope()->where('transaction_id', $transactionIdHint)->first();
            if ($hinted) {
                $candidates[] = Tenant::find($hinted->tenant_id);
            }
        }
        if (! $candidates) {
            $candidates = Tenant::all()->all();
        }

        $triedKeys = [];
        foreach ($candidates as $tenant) {
            if (! $tenant) {
                continue;
            }
            $service = BankMuscatPaymentService::forTenant($tenant);
            $key = $service->getWorkingKey();
            if ($key === '' || isset($triedKeys[$key])) {
                continue; // tenants sharing the platform fallback key
            }
            $triedKeys[$key] = true;

            try {
                $data = $service->decryptResponse($encResponse);
            } catch (\Throwable) {
                continue;
            }

            // The payment's own tenant key must be the one that decrypted it.
            $payment = Payment::withoutTenantScope()->where('transaction_id', $data['order_id'] ?? '')->first();
            $paymentTenant = $payment ? Tenant::find($payment->tenant_id) : null;
            $service = $paymentTenant ? BankMuscatPaymentService::forTenant($paymentTenant) : $service;

            if (! $payment || ! hash_equals($service->getWorkingKey(), $key) || ! $service->validateResponse($data, $payment)) {
                Log::warning('Bank Muscat response failed validation', ['order_id' => $data['order_id'] ?? null, 'tenant' => $tenant->slug]);

                return null;
            }

            $status = $service->getPaymentStatus($data);
            $payment = match ($status) {
                'completed' => $this->fulfillment->complete($payment, $data, $data['payment_mode'] ?? 'card'),
                'failed', 'cancelled' => $this->fulfillment->fail($payment, $status, $data),
                default => $payment,
            };

            return ['payment' => $payment, 'response' => $data, 'status' => $status];
        }

        Log::warning('Bank Muscat response could not be decrypted with any tenant key');

        return null;
    }
}
