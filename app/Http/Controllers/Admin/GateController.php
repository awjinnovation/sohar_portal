<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Gate;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateStatsService;
use App\Support\DbLike;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Gates: CRUD of the venue's entrances / exits (contract §B3). */
class GateController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request, GateStatsService $stats)
    {
        $q = trim((string) $request->input('q'));
        $gates = Gate::with('event:id,title,title_ar')
            ->withCount(['devices', 'devices as active_devices_count' => fn ($d) => $d->where('status', 'active')])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                // Case-insensitive on MySQL too (JSON paths compare as binary there).
                $like = DbLike::contains($q);
                DbLike::orWhere($w, 'name->ar', $like);
                DbLike::orWhere($w, 'name->en', $like);
                $w->orWhere('code', 'like', $like);
            }))
            ->orderBy('sort_order')->orderBy('id')
            ->paginate(25)->withQueryString();

        $tenant = $this->context->get();
        $today = collect($stats->dashboard($tenant, GateDay::today($tenant))['gates'])->keyBy('id');

        return view('admin.gates.index', compact('gates', 'q', 'today'));
    }

    public function create()
    {
        return view('admin.gates.create', ['gate' => new Gate(['direction' => 'entry', 'active' => true]), 'events' => $this->events()]);
    }

    public function store(Request $request)
    {
        $gate = Gate::create($this->validated($request));
        AuditLog::record('gate.created', $gate);

        return redirect()->route('admin.gates.index')->with('success', 'أُضيفت البوابة');
    }

    public function edit(Gate $gate)
    {
        return view('admin.gates.edit', ['gate' => $gate, 'events' => $this->events()]);
    }

    public function update(Request $request, Gate $gate)
    {
        $gate->update($this->validated($request, $gate));
        AuditLog::record('gate.updated', $gate);

        return redirect()->route('admin.gates.index')->with('success', 'حُدِّثت البوابة');
    }

    public function destroy(Gate $gate)
    {
        if ($gate->scans()->exists()) {
            $gate->update(['active' => false]);

            return redirect()->route('admin.gates.index')->with('warning', 'للبوابة سجل مسح، فعُطِّلت بدل حذفها');
        }
        AuditLog::record('gate.deleted', $gate, ['code' => $gate->code]);
        $gate->devices()->update(['gate_id' => null]);
        $gate->delete();

        return redirect()->route('admin.gates.index')->with('success', 'حُذفت البوابة');
    }

    private function validated(Request $request, ?Gate $gate = null): array
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('gates', 'code')->where('tenant_id', $tenantId)->ignore($gate?->id)],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->where('tenant_id', $tenantId)],
            'direction' => ['required', Rule::in(Gate::DIRECTIONS)],
            'ticket_types' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], [
            'name_ar' => __('الاسم بالعربية'), 'name_en' => __('الاسم بالإنجليزية'), 'code' => __('الرمز'),
        ]);

        $types = collect(preg_split('/[\s,\x{060C}]+/u', (string) ($data['ticket_types'] ?? '')))->map(fn ($t) => trim($t))->filter()->unique()->values()->all();

        return [
            'name' => ['ar' => $data['name_ar'], 'en' => $data['name_en']],
            'code' => strtoupper($data['code']),
            'event_id' => $data['event_id'] ?? null,
            'direction' => $data['direction'],
            'allow_reentry' => $request->boolean('allow_reentry'),
            'can_sell' => $request->boolean('can_sell'),
            'ticket_types' => $types ?: null,
            'active' => $request->boolean('active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function events()
    {
        return Event::orderByDesc('start_time')->get(['id', 'title', 'title_ar', 'start_time', 'tenant_id']);
    }
}
