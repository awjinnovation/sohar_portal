<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

/**
 * Bank Muscat (CCAvenue non-seamless) integration.
 *
 * Credentials are per tenant (tenants.gateway_credentials.bank_muscat,
 * encrypted) and fall back to config/services.php. Billing address comes
 * from the tenant's payments.billing settings.
 */
class BankMuscatPaymentService
{
    private string $workingKey;

    private string $accessCode;

    private string $gatewayUrl;

    private string $merchantId;

    private ?Tenant $tenant;

    public function __construct(?Tenant $tenant = null)
    {
        $this->tenant = $tenant ?? app(TenantContext::class)->get();

        $cred = fn (string $key, $default = null) => $this->tenant
            ? ($this->tenant->gatewayCredential('bank_muscat', $key) ?? $default)
            : config("services.bank_muscat.$key", $default);

        $this->workingKey = (string) $cred('working_key', '');
        $this->accessCode = (string) $cred('access_code', '');
        $this->gatewayUrl = (string) $cred('gateway_url', 'https://services.bng.gov.om:8989/transaction.do?command=initiateTransaction');
        $this->merchantId = (string) $cred('merchant_id', '');
    }

    public static function forTenant(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function getWorkingKey(): string
    {
        return $this->workingKey;
    }

    /**
     * Prepare payment data for Bank Muscat
     */
    public function preparePaymentData(Payment $payment): array
    {
        $metadata = $payment->metadata;
        $user = $payment->user;

        // Generate unique transaction ID
        $tid = now()->timestamp.rand(1000, 9999);

        $billing = (array) ($this->tenant?->setting('payments.billing') ?? []);
        $decimals = $this->tenant?->currencyDecimals() ?? 3;
        $amountMinor = $payment->amount_minor ?? \App\Support\Money::toMinor((string) $payment->amount, $decimals);

        $paymentData = [
            'tid' => $tid,
            'merchant_id' => $this->merchantId,
            'order_id' => $payment->transaction_id,
            'amount' => \App\Support\Money::format($amountMinor, $decimals),
            'currency' => $payment->currency,
            'redirect_url' => route('payment.success', ['transaction_id' => $payment->transaction_id]),
            'cancel_url' => route('payment.cancel', ['transaction_id' => $payment->transaction_id]),
            'language' => 'EN',

            // Billing information
            'billing_name' => $user->name ?? 'Guest',
            'billing_tel' => $metadata['user_phone'] ?? $user->phone_number ?? '',
            'billing_email' => $user->email ?? '',
            'billing_address' => $billing['address'] ?? '',
            'billing_city' => $billing['city'] ?? '',
            'billing_state' => $billing['state'] ?? '',
            'billing_zip' => $billing['zip'] ?? '',
            'billing_country' => $billing['country'] ?? '',

            // Delivery information (same as billing for tickets)
            'delivery_name' => $user->name ?? 'Guest',
            'delivery_tel' => $metadata['user_phone'] ?? $user->phone_number ?? '',
            'delivery_address' => $billing['address'] ?? '',
            'delivery_city' => $billing['city'] ?? '',
            'delivery_state' => $billing['state'] ?? '',
            'delivery_zip' => $billing['zip'] ?? '',
            'delivery_country' => $billing['country'] ?? '',

            // Merchant parameters for storing additional info
            'merchant_param1' => $payment->payment_type,
            'merchant_param2' => $payment->payable_id,
            'merchant_param3' => $metadata['quantity'] ?? 1,
            'merchant_param4' => $metadata['booking_date'] ?? '',
            'merchant_param5' => $payment->id,
        ];

        return $paymentData;
    }

    /**
     * Generate encrypted request for Bank Muscat
     */
    public function generateEncryptedRequest(array $paymentData): string
    {
        $merchantData = '';
        foreach ($paymentData as $key => $value) {
            $merchantData .= $key.'='.urlencode($value).'&';
        }

        // Remove trailing &
        $merchantData = rtrim($merchantData, '&');

        return BankMuscatCrypto::encrypt($merchantData, $this->workingKey);
    }

    /**
     * Decrypt and parse response from Bank Muscat
     */
    public function decryptResponse(string $encryptedResponse): array
    {
        $decryptedString = BankMuscatCrypto::decrypt($encryptedResponse, $this->workingKey);

        if ($decryptedString === false) {
            throw new \Exception('Failed to decrypt payment response');
        }

        $dataArray = [];
        $decryptedValues = explode('&', $decryptedString);

        foreach ($decryptedValues as $value) {
            $orderData = explode('=', $value);
            if (count($orderData) === 2) {
                $dataArray[$orderData[0]] = urldecode($orderData[1]);
            }
        }

        return $dataArray;
    }

    /**
     * Validate payment response
     */
    public function validateResponse(array $responseData, Payment $payment): bool
    {
        // Check if order_status exists
        if (! isset($responseData['order_status'])) {
            return false;
        }

        // Verify order_id matches
        if (($responseData['order_id'] ?? null) !== $payment->transaction_id) {
            return false;
        }

        // Verify currency matches
        if (($responseData['currency'] ?? null) !== $payment->currency) {
            return false;
        }

        // Verify the amount matches exactly (integer minor units).
        $decimals = $this->tenant?->currencyDecimals() ?? 3;
        $expected = $payment->amount_minor ?? \App\Support\Money::toMinor((string) $payment->amount, $decimals);
        if (\App\Support\Money::toMinor((string) ($responseData['amount'] ?? ''), $decimals) !== (int) $expected) {
            return false;
        }

        return true;
    }

    /**
     * Get payment status from response
     */
    public function getPaymentStatus(array $responseData): string
    {
        $orderStatus = $responseData['order_status'] ?? '';

        return match (strtolower($orderStatus)) {
            'success' => 'completed',
            'failure' => 'failed',
            'aborted' => 'cancelled',
            'invalid' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Get access code
     */
    public function getAccessCode(): string
    {
        return $this->accessCode;
    }

    /**
     * Get gateway URL
     */
    public function getGatewayUrl(): string
    {
        return $this->gatewayUrl;
    }
}
