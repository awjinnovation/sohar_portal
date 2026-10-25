<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Ticket;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → event → Ticket types (docs/ticket_types_contract.md §3). JSON for
 * the event form's editor (session + CSRF, permission `manage events`).
 * Changing a price affects new orders only; a type with sales can be
 * deactivated but not deleted; an event keeps at least one type.
 */
class EventTicketTypeController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Event $event): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->list($event)]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $type = new EventTicketType($this->validated($request, $event) + [
            'event_id' => $event->id,
            'sort' => (int) EventTicketType::where('event_id', $event->id)->max('sort') + 1,
        ]);
        $type->save();
        AuditLog::record('ticket_type.created', $type, ['event_id' => $event->id, 'key' => $type->key]);

        return response()->json(['success' => true, 'message' => __('أُضيف نوع التذكرة'), 'data' => $this->list($event), 'id' => $type->id], 201);
    }

    public function update(Request $request, Event $event, EventTicketType $ticketType): JsonResponse
    {
        abort_unless((int) $ticketType->event_id === (int) $event->id, 404);
        $data = $this->validated($request, $event, $ticketType);
        if ($ticketType->hasSales() && $data['key'] !== $ticketType->key) {
            return $this->refuse(__('لا يمكن تغيير رمز نوع بيعت منه تذاكر.'), 422);
        }
        $ticketType->update($data);
        AuditLog::record('ticket_type.updated', $ticketType, ['event_id' => $event->id, 'key' => $ticketType->key]);

        return response()->json(['success' => true, 'message' => __('حُفظ نوع التذكرة'), 'data' => $this->list($event)]);
    }

    public function destroy(Event $event, EventTicketType $ticketType): JsonResponse
    {
        abort_unless((int) $ticketType->event_id === (int) $event->id, 404);
        if ($ticketType->hasSales()) {
            return $this->refuse(__('بيعت تذاكر من هذا النوع، فيمكن إيقافه ولا يمكن حذفه.'), 409);
        }
        if (EventTicketType::where('event_id', $event->id)->count() <= 1) {
            return $this->refuse(__('تحتاج الفعالية إلى نوع تذكرة واحد على الأقل.'), 422);
        }
        AuditLog::record('ticket_type.deleted', $ticketType, ['event_id' => $event->id, 'key' => $ticketType->key]);
        $ticketType->delete();

        return response()->json(['success' => true, 'message' => __('حُذف نوع التذكرة'), 'data' => $this->list($event)]);
    }

    public function reorder(Request $request, Event $event): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']])['ids'];
        $types = EventTicketType::where('event_id', $event->id)->get()->keyBy('id');
        foreach (array_values($ids) as $i => $id) {
            if ($types->has($id)) {
                $types[$id]->forceFill(['sort' => $i])->saveQuietly();
            }
        }
        $event->syncPriceFromTypes();

        return response()->json(['success' => true, 'message' => __('حُفظ الترتيب'), 'data' => $this->list($event)]);
    }

    /** Types with their sales summary (sold, revenue, remaining for the next day the event runs). */
    public function list(Event $event): array
    {
        $tenant = $this->context->get();
        $date = $event->nextSaleDate();
        $eventAvailable = $date ? $event->getAvailableTicketsForDate($date) : null;
        $sales = Ticket::where('event_id', $event->id)->whereIn('status', ['active', 'used'])
            ->selectRaw('ticket_type_id, count(*) as sold, sum(price) as revenue')->groupBy('ticket_type_id')->get()->keyBy('ticket_type_id');

        return EventTicketType::where('event_id', $event->id)->ordered()->get()->map(function (EventTicketType $t) use ($event, $tenant, $date, $eventAvailable, $sales) {
            $t->setRelation('event', $event);
            $row = $sales->get($t->id);

            return $t->toApi($date, $tenant, $eventAvailable) + [
                'name' => ['en' => (string) ($t->name['en'] ?? ''), 'ar' => (string) ($t->name['ar'] ?? '')],
                'description' => ['en' => (string) ($t->description['en'] ?? ''), 'ar' => (string) ($t->description['ar'] ?? '')],
                'capacity' => $t->capacity,
                'is_active' => (bool) $t->is_active,
                'own_color' => $t->color,
                'sales_start_local' => $t->sales_start?->format('Y-m-d\TH:i'),
                'sales_end_local' => $t->sales_end?->format('Y-m-d\TH:i'),
                'price_input' => \App\Support\Money::format((int) $t->price_minor, $tenant->currencyDecimals()),
                'sold' => (int) ($row->sold ?? 0),
                'revenue_minor' => $tenant->toMinor((string) ($row->revenue ?? 0)),
                'revenue' => $tenant->formatMoney($tenant->toMinor((string) ($row->revenue ?? 0))),
                'has_sales' => $row !== null || $t->hasSales(),
                'remaining_date' => $date,
            ];
        })->values()->all();
    }

    private function validated(Request $request, Event $event, ?EventTicketType $type = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('event_ticket_types', 'key')->where('event_id', $event->id)->ignore($type?->id)],
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'description_ar' => ['nullable', 'string', 'max:300'],
            'description_en' => ['nullable', 'string', 'max:300'],
            'category' => ['required', Rule::in(EventTicketType::CATEGORIES)],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'admits' => ['required', 'integer', 'between:1,50'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'per_order_max' => ['required', 'integer', 'between:1,100'],
            'min_age' => ['nullable', 'integer', 'between:0,120'],
            'max_age' => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'requires_id' => ['nullable', 'boolean'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => ['nullable', 'date', 'after:sales_start'],
            'visibility' => ['required', Rule::in(EventTicketType::VISIBILITIES)],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'key' => __('الرمز'), 'name_ar' => __('الاسم بالعربية'), 'name_en' => __('الاسم بالإنجليزية'), 'price' => __('السعر'),
            'admits' => __('عدد الأشخاص'), 'per_order_max' => __('الحد في الطلب'), 'max_age' => __('أقصى عمر'), 'sales_end' => __('نهاية البيع'),
        ]);
        $tenant = $this->context->get();
        $local = fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d H:i:s') : null;

        return [
            'key' => $data['key'],
            'name' => ['ar' => trim($data['name_ar']), 'en' => trim($data['name_en'])],
            'description' => (filled($data['description_ar'] ?? null) || filled($data['description_en'] ?? null))
                ? ['ar' => trim((string) ($data['description_ar'] ?? '')), 'en' => trim((string) ($data['description_en'] ?? ''))] : null,
            'category' => $data['category'],
            'price_minor' => $tenant->toMinor((string) $data['price']),
            'admits' => (int) $data['admits'],
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
            'per_order_max' => (int) $data['per_order_max'],
            'min_age' => isset($data['min_age']) ? (int) $data['min_age'] : null,
            'max_age' => isset($data['max_age']) ? (int) $data['max_age'] : null,
            'requires_id' => $request->boolean('requires_id'),
            'sales_start' => $local($data['sales_start'] ?? null),
            'sales_end' => $local($data['sales_end'] ?? null),
            'visibility' => $data['visibility'],
            'color' => ! empty($data['color']) ? strtoupper($data['color']) : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];
    }

    private function refuse(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
