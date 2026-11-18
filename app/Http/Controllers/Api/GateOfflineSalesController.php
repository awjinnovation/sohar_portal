<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\GateDevice;
use App\Services\Gate\OfflineSaleService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /gate/sales/offline (docs/gate_sales_contract.md §10): a gate device
 * sends the card terminal sales it made with no connection. Each sale is
 * recorded once (device + idempotency_key); a sale that cannot be accepted
 * (code not signed for this device, unknown ticket type) is reported with its
 * error code and the others go on. Not behind EnsureGateSales: the money was
 * taken already, so a sale is recorded even if selling was switched off or
 * the staff member signed out since.
 */
class GateOfflineSalesController extends Controller
{
    public function __construct(private TenantContext $context, private OfflineSaleService $sales) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:50'],
            'sales.*.idempotency_key' => ['required', 'string', 'max:100', 'distinct'],
            'sales.*.sold_at' => ['required', 'date'],
            'sales.*.booking_date' => ['required', 'date_format:Y-m-d'],
            'sales.*.event_id' => ['required', 'integer'],
            'sales.*.staff_id' => ['nullable', 'integer'],
            'sales.*.approval_code' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]*$/'],
            'sales.*.total_minor' => ['required', 'integer', 'min:0'],
            'sales.*.lines' => ['required', 'array', 'min:1', 'max:20'],
            'sales.*.lines.*.ticket_type_id' => ['required', 'integer'],
            'sales.*.lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'sales.*.lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'sales.*.tickets' => ['required', 'array', 'min:1', 'max:100'],
            'sales.*.tickets.*.number' => ['required', 'string', 'max:60'],
            'sales.*.tickets.*.code' => ['required', 'string', 'max:120'],
            'sales.*.tickets.*.ticket_type_id' => ['required', 'integer'],
        ]);
        $tenant = $this->context->get();
        /** @var GateDevice $device */
        $device = $request->attributes->get('gate_device');

        $results = [];
        foreach ($data['sales'] as $sale) {
            try {
                ['order' => $order, 'created' => $created] = $this->sales->sync($tenant, $device, $sale);
                $results[] = [
                    'idempotency_key' => $sale['idempotency_key'],
                    'status' => 'synced',
                    'created' => $created,
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'needs_review' => (bool) $order->needs_review,
                ];
            } catch (ApiException $e) {
                $results[] = [
                    'idempotency_key' => $sale['idempotency_key'],
                    'status' => 'rejected',
                    'error' => $e->errorCode,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['success' => true, 'message' => 'OK', 'data' => ['results' => $results]]);
    }
}
