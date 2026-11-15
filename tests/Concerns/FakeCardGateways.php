<?php

namespace Tests\Concerns;

use App\Models\Payment;
use App\Services\Payments\AmwalCheckout;
use App\Services\Payments\Gateway\AmwalGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Thawani and Amwal (UAT) faked together, for tests where both gateways are
 * on: configured platform keys (made-up), one HTTP fake answering both APIs
 * from in-memory state. Call setUpCardGateways() in setUp().
 */
trait FakeCardGateways
{
    // Made-up keys; never real ones.
    protected const AMWAL_KEY = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';

    protected const AMWAL_MID = '153400';

    protected const AMWAL_TID = '502900';

    /** @var array<string, array<int, array>> Amwal transactions by merchant reference */
    protected array $amwalTxs = [];

    /** @var array<string, array> Thawani sessions by id */
    protected array $thawaniSessions = [];

    /** @var array<int, array{gateway: string, op: string, body: array}> */
    protected array $gatewayCalls = [];

    protected function setUpCardGateways(array $config = []): void
    {
        Sleep::fake();
        config($config + [
            'services.amwal.mode' => 'uat',
            'services.amwal.merchant_id' => self::AMWAL_MID,
            'services.amwal.terminal_id' => self::AMWAL_TID,
            'services.amwal.secure_key' => self::AMWAL_KEY,
            'services.thawani.mode' => 'uat',
            'services.thawani.secret_key' => 'sk_test_secret',
            'services.thawani.publishable_key' => 'pk_test_public',
            'services.thawani.webhook_secret' => 'whsec_test',
            'payments.circuit.threshold' => 50,
        ]);
        Http::fake(fn (Request $request) => $this->fakeGatewayAnswer($request));
    }

    private function fakeGatewayAnswer(Request $request)
    {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (str_starts_with($url, 'https://test.amwalpg.com:14443/')) {
            $body = $request->data();
            $fields = $body;
            unset($fields['secureHashValue']);
            $this->assertSame(AmwalGateway::sign($fields, self::AMWAL_KEY), $body['secureHashValue'] ?? null, 'Amwal request hash');
            $op = match (true) {
                str_ends_with($path, '/Transaction/GetByMerchantReference') => 'get_transactions',
                str_ends_with($path, '/Execute/Void') => 'void',
                str_ends_with($path, '/Execute/Refund') => 'refund',
                default => 'unknown',
            };
            $this->gatewayCalls[] = ['gateway' => 'amwal', 'op' => $op, 'body' => $body];

            return match ($op) {
                'get_transactions' => Http::response(['success' => true, 'responseCode' => '00', 'message' => 'Success', 'errorList' => [],
                    'data' => array_reverse($this->amwalTxs[$body['merchantReference']] ?? [])]),
                'void', 'refund' => Http::response(['success' => true, 'responseCode' => '00', 'message' => 'Success',
                    'data' => ['transactionId' => 'rev-'.count($this->gatewayCalls), 'amount' => $body['amount']]]),
                default => Http::response([], 500),
            };
        }

        if (str_starts_with($url, 'https://uatcheckout.thawani.om/api/v1/')) {
            $op = match (true) {
                $request->method() === 'POST' && str_ends_with($path, '/checkout/session') => 'create_session',
                $request->method() === 'POST' && str_ends_with($path, '/cancel') => 'cancel_session',
                $request->method() === 'GET' && str_contains($path, '/checkout/session/') => 'get_session',
                str_ends_with($path, '/payments') => 'get_payments',
                default => 'unknown',
            };
            $this->gatewayCalls[] = ['gateway' => 'thawani', 'op' => $op, 'body' => $request->data()];
            switch ($op) {
                case 'create_session':
                    $body = $request->data();
                    $id = 'checkout_'.(count($this->thawaniSessions) + 1);
                    $this->thawaniSessions[$id] = [
                        'session_id' => $id, 'client_reference_id' => $body['client_reference_id'], 'payment_status' => 'unpaid',
                        'total_amount' => array_sum(array_map(fn ($p) => $p['unit_amount'] * $p['quantity'], $body['products'])),
                        'currency' => 'OMR', 'invoice' => 'INV'.(count($this->thawaniSessions) + 1),
                        'expire_at' => now()->addDay()->toIso8601String(), 'metadata' => $body['metadata'],
                    ];

                    return Http::response(['success' => true, 'code' => 2004, 'data' => $this->thawaniSessions[$id]]);
                case 'get_session':
                    $id = basename($path);

                    return isset($this->thawaniSessions[$id])
                        ? Http::response(['success' => true, 'code' => 2000, 'data' => $this->thawaniSessions[$id]])
                        : Http::response(['success' => false, 'code' => 4004, 'description' => 'Session not found'], 404);
                case 'cancel_session':
                    $id = basename(dirname($path));
                    $this->thawaniSessions[$id]['payment_status'] = 'cancelled';

                    return Http::response(['success' => true, 'data' => $this->thawaniSessions[$id]]);
                case 'get_payments':
                    return Http::response(['success' => true, 'data' => [['payment_id' => 'pay_1', 'status' => 'successful']]]);
            }
        }

        return Http::response([], 500);
    }

    /** Amwal approves a purchase of $amount (OMR) for a merchant reference. */
    protected function amwalPays(string $reference, string $amount): string
    {
        $id = (string) Str::uuid();
        $this->amwalTxs[$reference][] = [
            'id' => $id, 'amount' => (float) $amount, 'currencyId' => 512, 'responseCode' => '00', 'responseCodeName' => 'Approved',
            'transactionType' => 'Purchase', 'merchantReference' => $reference, 'isRefunded' => false,
            'transactionActions' => ['canVoid' => true, 'canRefund' => false],
        ];

        return $id;
    }

    protected function thawaniPays(string $sessionId): void
    {
        $this->thawaniSessions[$sessionId]['payment_status'] = 'paid';
    }

    /** The /payment/success URL SmartBox's completeCallback leads to (signed like Amwal does). */
    protected function amwalReturnUrl(Payment $payment, string $reference, string $amount, string $code = '00'): string
    {
        $params = [
            'amount' => $amount, 'currencyId' => '512', 'customerId' => '', 'customerTokenId' => '',
            'merchantReference' => $reference, 'responseCode' => $code, 'transactionId' => (string) Str::uuid(),
            'transactionTime' => '2026-11-15T10:15:00.1234567Z',
        ];
        $params['secureHashValue'] = AmwalGateway::sign($params + ['merchantId' => self::AMWAL_MID, 'terminalId' => self::AMWAL_TID], self::AMWAL_KEY);
        $tx = $payment->transaction_id;

        return '/payment/success?'.http_build_query(['transaction_id' => $tx, 't' => AmwalCheckout::returnToken($tx)] + $params);
    }

    protected function gatewayCallCount(string $gateway, string $op): int
    {
        return count(array_filter($this->gatewayCalls, fn ($c) => $c['gateway'] === $gateway && $c['op'] === $op));
    }
}
