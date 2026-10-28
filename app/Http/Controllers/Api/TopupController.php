<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\TopupResource;
use App\Models\WalletTopup;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\TopupService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function __construct(private TenantContext $context) {}

    /** POST /wallet/topups {amount_minor, idempotency_key} */
    public function store(Request $request, TopupService $topups, PaymentGatewayManager $gateways)
    {
        $data = $request->validate([
            'amount_minor' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|max:100',
        ]);

        $topup = $topups->initialize($request->user(), $this->context->get(), (int) $data['amount_minor'], $data['idempotency_key']);
        $topup->load('payment');

        $resource = new TopupResource($topup);
        if ($topup->status === 'pending' && $topup->payment) {
            $resource->paymentUrl = $gateways->paymentUrl($topup->payment);
        }

        return response()->json([
            'success' => true,
            'message' => 'Top-up initialized',
            'data' => $resource,
        ], $topup->wasRecentlyCreated ? 201 : 200);
    }

    /** GET /wallet/topups/{id} — poll after the gateway page closes. */
    public function show(Request $request, int $id)
    {
        $topup = WalletTopup::with('payment')->where('user_id', $request->user()->id)->findOrFail($id);
        if ($topup->status === 'pending' && $topup->payment) {
            // Thawani: ask the gateway now (throttled) instead of waiting for the webhook.
            $payment = app(\App\Services\Payments\ThawaniCheckout::class)->checkIfStale($topup->payment);
            if ($payment->status !== $topup->payment->status) {
                $topup->refresh()->load('payment');
            }
        }

        return response()->json(['success' => true, 'data' => new TopupResource($topup)]);
    }
}
