<?php

namespace App\Services\Payments\Gateway;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Amwal Pay: SmartBox checkout + merchant API (docs/payments_amwal.md).
 * Amounts at Amwal are in OMR major units ("1.025"); ours are baisa.
 *
 * Secure hash (every request, callback and notification): the fields
 * sorted by name, joined as `key=value&key=value` (no encoding; null,
 * "null" and "undefined" are empty), HMAC-SHA256 with the hex-decoded
 * merchant secure key, upper-case hex. Field names and formats follow
 * Amwal's own WooCommerce plugin (amwalpay-for-woocommerce 1.0.23).
 *
 *   SmartBox (browser)                      Amount, CurrencyId, MerchantId, MerchantReference, RequestDateTime (yyyyMMddHHmmss), SessionToken, TerminalId
 *   completeCallback (browser → us)         amount, currencyId, customerId, customerTokenId, merchantId, merchantReference, responseCode, terminalId, transactionId, transactionTime
 *   Cloud notification (Amwal → us)         Amount, AuthorizationDateTime, CurrencyId, DateTimeLocalTrxn, MerchantId, MerchantReference, Message, PaidThrough, ResponseCode, SystemReference, TerminalId, TxnType
 *   POST Transaction/GetByMerchantReference merchantId, merchantReference, requestDateTime
 *   POST Execute/Refund | Execute/Void      amount, currencyCode, merchantId, requestDateTime (ISO), terminalId, transactionId (new GUID), transactionIdentifierType (2 = GUID), transactionIdentifierValue
 *
 * Response code "00" = approved.
 */
class AmwalGateway
{
    public const NAME = 'amwal';

    public const CURRENCY_OMR = 512;

    public const APPROVED = '00';

    public const CALLBACK_FIELDS = ['amount', 'currencyId', 'customerId', 'customerTokenId', 'merchantId', 'merchantReference', 'responseCode', 'terminalId', 'transactionId', 'transactionTime'];

    public const NOTIFICATION_FIELDS = ['Amount', 'AuthorizationDateTime', 'CurrencyId', 'DateTimeLocalTrxn', 'MerchantId', 'MerchantReference', 'Message', 'PaidThrough', 'ResponseCode', 'SystemReference', 'TerminalId', 'TxnType'];

    public function __construct(private GatewayHttp $http) {}

    // ---------------------------------------------------------------- hashing

    /** The secure hash of a set of fields (see class doc). */
    public static function sign(array $fields, string $hexKey): string
    {
        ksort($fields, SORT_STRING);
        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.self::hashValue($value);
        }

        return strtoupper(hash_hmac('sha256', implode('&', $pairs), (string) hex2bin($hexKey)));
    }

    private static function hashValue(mixed $value): string
    {
        if ($value === null || $value === 'null' || $value === 'undefined') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /** "1.025" from 1025 baisa. */
    public static function amount(int $minor, int $decimals = 3): string
    {
        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    /** Baisa from Amwal's major-unit amount ("1.025", 1.025, "1.0250"); null if not a number. */
    public static function minor(mixed $amount, int $decimals = 3): ?int
    {
        if (! is_numeric($amount)) {
            return null;
        }

        return (int) round(((float) $amount) * (10 ** $decimals));
    }

    /**
     * The SmartBox.Checkout.configure object for our payment page (the
     * callbacks are added by the page). $dateTime = UTC.
     */
    public static function smartBoxConfig(AmwalConfig $c, string $reference, int $amountMinor, string $locale, ?Carbon $dateTime = null): array
    {
        $amount = self::amount($amountMinor);
        $requestDateTime = ($dateTime ?? now())->copy()->utc()->format('YmdHis');
        $sessionToken = '';

        return [
            'MID' => $c->merchantId,
            'TID' => $c->terminalId,
            'CurrencyId' => self::CURRENCY_OMR,
            'AmountTrxn' => $amount,
            'MerchantReference' => $reference,
            'LanguageId' => $locale === 'ar' ? 'ar' : 'en',
            'PaymentViewType' => in_array((int) config('payments.amwal.view_type', 2), [1, 2], true) ? (int) config('payments.amwal.view_type', 2) : 2,
            'TrxDateTime' => $requestDateTime,
            'SessionToken' => $sessionToken,
            'ContactInfoType' => (string) (int) config('payments.amwal.contact_info_type', 4),
            'CheckoutSiteMode' => 'onsite',
            'IgnoreReceipt' => true,
            'SecureHash' => self::sign([
                'Amount' => $amount,
                'CurrencyId' => self::CURRENCY_OMR,
                'MerchantId' => $c->merchantId,
                'MerchantReference' => $reference,
                'RequestDateTime' => $requestDateTime,
                'SessionToken' => $sessionToken,
                'TerminalId' => $c->terminalId,
            ], $c->secureKey),
        ];
    }

    /**
     * A completeCallback relayed by the browser. Merchant / terminal are
     * ours (the callback does not carry them). true / false = checked.
     */
    public static function verifyCallback(AmwalConfig $c, array $params): bool
    {
        $received = (string) ($params['secureHashValue'] ?? '');
        if ($received === '' || ! $c->isConfigured()) {
            return false;
        }
        $fields = [];
        foreach (self::CALLBACK_FIELDS as $f) {
            $fields[$f] = $params[$f] ?? '';
        }
        $fields['merchantId'] = $c->merchantId;
        $fields['terminalId'] = $c->terminalId;

        return hash_equals(self::sign($fields, $c->secureKey), strtoupper(trim($received)));
    }

    /** A cloud notification body (decoded JSON). */
    public static function verifyNotification(AmwalConfig $c, array $body): bool
    {
        $received = (string) ($body['SecureHash'] ?? '');
        if ($received === '' || ! $c->isConfigured()) {
            return false;
        }
        $fields = [];
        foreach (self::NOTIFICATION_FIELDS as $f) {
            $fields[$f] = $body[$f] ?? '';
        }

        return hash_equals(self::sign($fields, $c->secureKey), strtoupper(trim($received)));
    }

    // ---------------------------------------------------------------- API

    private function request(AmwalConfig $c, string $operation, string $path, array $fields, array $ctx, bool $idempotent = true, array $unprocessed = [425, 429, 503]): GatewayRequest
    {
        if (! $c->isConfigured()) {
            throw GatewayException::notConfigured('Amwal merchant ID, terminal ID and secure key are not set for this organizer');
        }

        return new GatewayRequest(
            gateway: self::NAME,
            mode: $c->mode,
            operation: $operation,
            method: 'POST',
            url: $c->apiUrl($path),
            body: $fields + ['secureHashValue' => self::sign($fields, $c->secureKey)],
            tenantId: $ctx['tenant_id'] ?? null,
            paymentId: $ctx['payment_id'] ?? null,
            idempotent: $idempotent,
            unprocessedStatuses: $unprocessed,
        );
    }

    /**
     * Purchase transactions of a merchant reference, newest first (each: id
     * (GUID), idN, amount, currencyId, responseCode, responseCodeName,
     * isRefunded, isCaptured, gatewayTransactionReference, transactionActions…).
     *
     * @return array<int, array>
     */
    public function transactionsByReference(AmwalConfig $c, string $reference, array $ctx = [], string $profile = 'background'): array
    {
        $data = $this->http->send($this->request($c, 'get_transactions', 'Transaction/GetByMerchantReference', [
            'merchantId' => $this->numeric($c->merchantId),
            'merchantReference' => $reference,
            'requestDateTime' => now()->utc()->format('YmdHis'),
        ], $ctx), $profile)->data();

        return array_values(array_filter(array_is_list($data) ? $data : [$data], 'is_array'));
    }

    /**
     * Full void (same day, before settlement) or refund of a transaction
     * (by its GUID). Money out: only 425 / 429 count as "not processed".
     *
     * @param  string  $operation  refund | void
     */
    public function reverse(AmwalConfig $c, string $operation, string $transactionGuid, int $amountMinor, array $ctx = []): array
    {
        $path = $operation === 'void' ? 'Execute/Void' : 'Execute/Refund';

        return $this->http->send($this->request($c, $operation === 'void' ? 'void' : 'create_refund', $path, [
            'amount' => self::amount($amountMinor),
            'currencyCode' => self::CURRENCY_OMR,
            'merchantId' => $this->numeric($c->merchantId),
            'requestDateTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'terminalId' => $this->numeric($c->terminalId),
            'transactionId' => (string) Str::uuid(),
            'transactionIdentifierType' => 2,
            'transactionIdentifierValue' => $transactionGuid,
        ], $ctx, idempotent: false, unprocessed: [425, 429]), 'background')->data();
    }

    /** Admin "Test connection": an inquiry for a reference that does not exist (a signed, read-only call). */
    public function ping(AmwalConfig $c, array $ctx = []): array
    {
        try {
            $this->transactionsByReference($c, 'connection-check-'.now()->format('YmdHis'), $ctx, 'interactive');

            return ['ok' => true, 'status' => 200, 'message' => 'OK'];
        } catch (GatewayException $e) {
            return ['ok' => false, 'status' => $e->status, 'message' => $e->getMessage(), 'kind' => $e->kind];
        }
    }

    /** Merchant / terminal ids are numbers in Amwal's JSON (the hash string is the same either way). */
    private function numeric(string $id): int|string
    {
        return ctype_digit($id) && strlen($id) < 19 ? (int) $id : $id;
    }

    /** The approved transaction among an inquiry's results (null if none). */
    public static function approved(array $transactions): ?array
    {
        foreach ($transactions as $t) {
            if ((string) ($t['responseCode'] ?? '') === self::APPROVED) {
                return $t;
            }
        }

        return null;
    }
}
