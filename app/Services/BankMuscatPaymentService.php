<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Str;

class BankMuscatPaymentService
{
    private string $workingKey;
    private string $accessCode;
    private string $gatewayUrl;
    private string $merchantId;

    public function __construct()
    {
        $this->workingKey = config('services.bank_muscat.working_key');
        $this->accessCode = config('services.bank_muscat.access_code');
        $this->gatewayUrl = config('services.bank_muscat.gateway_url', 'https://services.bng.gov.om:8989/transaction.do?command=initiateTransaction');
        $this->merchantId = config('services.bank_muscat.merchant_id');
    }

    /**
     * Prepare payment data for Bank Muscat
     *
     * @param Payment $payment
     * @return array
     */
    public function preparePaymentData(Payment $payment): array
    {
        $metadata = $payment->metadata;
        $user = $payment->user;

        // Generate unique transaction ID
        $tid = now()->timestamp . rand(1000, 9999);

        $paymentData = [
            'tid' => $tid,
            'merchant_id' => $this->merchantId,
            'order_id' => $payment->transaction_id,
            'amount' => number_format($payment->amount, 3, '.', ''), // Format to 3 decimal places for OMR
            'currency' => $payment->currency,
            'redirect_url' => route('payment.success') . '?transaction_id=' . $payment->transaction_id,
            'cancel_url' => route('payment.cancel') . '?transaction_id=' . $payment->transaction_id,
            'language' => 'EN',

            // Billing information
            'billing_name' => $user->name ?? 'Guest',
            'billing_tel' => $metadata['user_phone'] ?? $user->phone_number ?? '',
            'billing_email' => $user->email ?? '',
            'billing_address' => 'Sohar',
            'billing_city' => 'Sohar',
            'billing_state' => 'Al Batinah North',
            'billing_zip' => '311',
            'billing_country' => 'Oman',

            // Delivery information (same as billing for tickets)
            'delivery_name' => $user->name ?? 'Guest',
            'delivery_tel' => $metadata['user_phone'] ?? $user->phone_number ?? '',
            'delivery_address' => 'Sohar',
            'delivery_city' => 'Sohar',
            'delivery_state' => 'Al Batinah North',
            'delivery_zip' => '311',
            'delivery_country' => 'Oman',

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
     *
     * @param array $paymentData
     * @return string
     */
    public function generateEncryptedRequest(array $paymentData): string
    {
        $merchantData = '';
        foreach ($paymentData as $key => $value) {
            $merchantData .= $key . '=' . urlencode($value) . '&';
        }

        // Remove trailing &
        $merchantData = rtrim($merchantData, '&');

        return BankMuscatCrypto::encrypt($merchantData, $this->workingKey);
    }

    /**
     * Decrypt and parse response from Bank Muscat
     *
     * @param string $encryptedResponse
     * @return array
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
     *
     * @param array $responseData
     * @param Payment $payment
     * @return bool
     */
    public function validateResponse(array $responseData, Payment $payment): bool
    {
        // Check if order_status exists
        if (!isset($responseData['order_status'])) {
            return false;
        }

        // Verify order_id matches
        if ($responseData['order_id'] !== $payment->transaction_id) {
            return false;
        }

        // Verify currency matches
        if ($responseData['currency'] !== $payment->currency) {
            return false;
        }

        // Verify amount matches (with small tolerance for floating point)
        $responseAmount = (float)$responseData['amount'];
        $paymentAmount = (float)$payment->amount;

        if (abs($responseAmount - $paymentAmount) > 0.01) {
            return false;
        }

        return true;
    }

    /**
     * Get payment status from response
     *
     * @param array $responseData
     * @return string
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
     *
     * @return string
     */
    public function getAccessCode(): string
    {
        return $this->accessCode;
    }

    /**
     * Get gateway URL
     *
     * @return string
     */
    public function getGatewayUrl(): string
    {
        return $this->gatewayUrl;
    }
}
