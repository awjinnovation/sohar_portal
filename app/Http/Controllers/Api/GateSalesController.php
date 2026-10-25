<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\BoxOfficeOrder;
use App\Models\GateDevice;
use App\Models\User;
use App\Services\Gate\BoxOfficeService;
use App\Services\Gate\GateDay;
use App\Services\Gate\ScanPresenter;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gate box office API (docs/gate_sales_contract.md §3, ticket types:
 * docs/ticket_types_contract.md §2), /api/v1/gate/sales/*. Device token +
 * staff session + `sell tickets` (App\Http\Middleware\EnsureGateSales).
 */
class GateSalesController extends Controller
{
    public function __construct(private TenantContext $context, private BoxOfficeService $boxOffice) {}

    /** GET /catalog?date= */
    public function catalog(Request $request): JsonResponse
    {
        $tenant = $this->context->get();
        $date = $this->boxOffice->resolveDate($tenant, $request->query('date'), 'date');

        return $this->ok([
            'date' => $date,
            'currency' => $tenant->currency(),
            'events' => $this->boxOffice->catalog($tenant, $this->device($request)->gate, $date),
        ]);
    }

    /** POST /quote {event_id, items?: [{ticket_type_id, quantity}] | ticket_type? + quantity, booking_date?} */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate($this->itemRules());
        $tenant = $this->context->get();
        $date = $this->boxOffice->resolveDate($tenant, $data['booking_date'] ?? null);
        $q = $this->boxOffice->quote($tenant, $this->device($request)->gate, (int) $data['event_id'], $this->items($data), $date);

        return $this->ok($this->boxOffice->quotePayload($q, $tenant));
    }

    /** POST /orders */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->itemRules() + [
            'buyer_phone' => ['required', 'string', 'max:20'],
            'buyer_name' => ['nullable', 'string', 'max:120'],
            'send_sms' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'in:ar,en'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $data['items'] = $this->items($data);

        [$order, $created] = $this->boxOffice->create($this->context->get(), $this->device($request), $this->staff($request), $data);

        return $this->ok(['order' => $this->boxOffice->toApi($order)], $created ? 201 : 200);
    }

    /** GET /orders?date= — this device's orders and the signed-in staff member's own. */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->context->get();
        $date = GateDay::parse($request->query('date'), $tenant);
        [$from, $to] = GateDay::window($date, $tenant);

        $orders = $this->scoped($request)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn (BoxOfficeOrder $o) => $this->boxOffice->expireIfDue($o));

        $paid = $orders->where('status', BoxOfficeOrder::STATUS_PAID);
        $paidMinor = (int) $paid->sum('total_minor');

        return $this->ok([
            'date' => $date,
            'orders' => $orders->map(fn ($o) => $this->boxOffice->toApi($o, $tenant))->values(),
            'totals' => [
                'count' => $orders->count(),
                'paid_count' => $paid->count(),
                'paid_minor' => $paidMinor,
                'paid' => $tenant->formatMoney($paidMinor),
                'pending_count' => $orders->where('status', BoxOfficeOrder::STATUS_PENDING)->count(),
                'expired_count' => $orders->where('status', BoxOfficeOrder::STATUS_EXPIRED)->count(),
                'cancelled_count' => $orders->where('status', BoxOfficeOrder::STATUS_CANCELLED)->count(),
                'refund_due_count' => $orders->where('status', BoxOfficeOrder::STATUS_PAID_UNFULFILLED)->count(),
                'tickets_paid' => (int) $paid->sum('quantity'),
                'currency' => $tenant->currency(),
            ],
        ]);
    }

    /** GET /orders/{id} (polled every 3 s while pending) */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->ok(['order' => $this->boxOffice->toApi($this->boxOffice->expireIfDue($this->order($request, $id)))]);
    }

    /** POST /orders/{id}/sms */
    public function sms(Request $request, int $id): JsonResponse
    {
        $order = $this->boxOffice->resendSms($this->order($request, $id), $this->context->get());

        return $this->ok(['order' => $this->boxOffice->toApi($order)]);
    }

    /** POST /orders/{id}/cancel */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->boxOffice->cancel($this->order($request, $id), $this->staff($request), 'gate');

        return $this->ok(['order' => $this->boxOffice->toApi($order)]);
    }

    /** POST /orders/{id}/admit {ticket_ids?: [int], entries?: int, idempotency_key?} */
    public function admit(Request $request, int $id, ScanPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'ticket_ids' => ['nullable', 'array', 'max:100'],
            'ticket_ids.*' => ['integer'],
            'entries' => ['nullable', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $device = $this->device($request);
        $order = $this->order($request, $id);

        $results = $this->boxOffice->admit($order, $device, $this->staff($request), $data['ticket_ids'] ?? null, $data['entries'] ?? null, $data['idempotency_key'] ?? null);

        return $this->ok([
            'results' => array_map(fn ($r) => ['ticket_id' => $r['ticket_id'], 'entries_admitted' => $r['entries_admitted']]
                + $presenter->present($r['outcome'], $device, false), $results),
            'counts' => $presenter->counts($device),
            'order' => $this->boxOffice->toApi($order->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    private function itemRules(): array
    {
        return [
            'event_id' => ['required', 'integer'],
            'items' => ['nullable', 'array', 'min:1', 'max:20'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'ticket_type' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required_without:items', 'nullable', 'integer', 'min:1', 'max:100'],
            'booking_date' => ['nullable', 'string', 'max:10'],
        ];
    }

    private function items(array $data): array
    {
        return ! empty($data['items'])
            ? array_values($data['items'])
            : [['ticket_type' => $data['ticket_type'] ?? null, 'quantity' => (int) ($data['quantity'] ?? 0)]];
    }

    private function scoped(Request $request)
    {
        $device = $this->device($request);
        $staff = $this->staff($request);

        return BoxOfficeOrder::with(['event', 'lines', 'gate', 'staff'])
            ->where(fn ($q) => $q->where('device_id', $device->id)->orWhere('staff_user_id', $staff->id));
    }

    private function order(Request $request, int $id): BoxOfficeOrder
    {
        $order = $this->scoped($request)->find($id);
        if (! $order) {
            throw new ApiException('NOT_FOUND', __('platform.errors.not_found'), 404);
        }

        return $order;
    }

    private function device(Request $request): GateDevice
    {
        return $request->attributes->get('gate_device');
    }

    private function staff(Request $request): User
    {
        return $request->attributes->get('gate_staff');
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data], $status);
    }
}
