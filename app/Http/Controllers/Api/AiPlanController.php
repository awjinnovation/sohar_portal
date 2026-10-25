<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Services\Ai\AiCall;
use App\Services\Ai\AiException;
use App\Services\Ai\AiIdentity;
use App\Services\Ai\AiUsage;
use App\Services\Ai\PlanService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Plan my visit (docs/ai_and_gate_contract.md §A4). Auth optional
 * (Sanctum or X-Ai-Guest); plans are shareable by id inside the tenant.
 */
class AiPlanController extends Controller
{
    public function __construct(private TenantContext $context, private AiUsage $usage, private PlanService $plans) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'party' => ['required', 'array'],
            'party.adults' => ['required', 'integer', 'min:0', 'max:20'],
            'party.kids' => ['nullable', 'integer', 'min:0', 'max:20'],
            'party.kids_ages' => ['nullable', 'array', 'max:20'],
            'party.kids_ages.*' => ['integer', 'min:0', 'max:17'],
            'interests' => ['nullable', 'array', 'max:12'],
            'interests.*' => ['string', 'max:40'],
            'budget_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'locale' => ['nullable', 'in:ar,en'],
        ]);
        if (((int) $data['party']['adults'] + (int) ($data['party']['kids'] ?? 0)) < 1) {
            throw new ApiException('VALIDATION_ERROR', __('ai.errors.party_required'), 422, ['errors' => ['party.adults' => [__('ai.errors.party_required')]]]);
        }
        $tenant = $this->context->get();
        $data['locale'] = $data['locale'] ?? (in_array(app()->getLocale(), ['ar', 'en'], true) ? app()->getLocale() : 'ar');
        app()->setLocale($data['locale']);

        $this->usage->ensureModule($tenant, 'ai_plan');
        $identity = AiIdentity::from($request);
        $call = new AiCall($tenant, 'plan', $identity->user, $identity->guestHash);
        $limits = (array) config('ai.plan.rate', []);
        try {
            if ($identity->user) {
                $this->usage->throttle("plan:u:{$tenant->id}:".$identity->user->id, $limits['user'] ?? ['max' => 6, 'minutes' => 10]);
            } else {
                $this->usage->throttle("plan:g:{$tenant->id}:".$identity->guestHash, $limits['guest'] ?? ['max' => 3, 'minutes' => 10]);
                $this->usage->throttle("plan:ip:{$tenant->id}:".$identity->ip, $limits['guest_ip'] ?? ['max' => 30, 'minutes' => 10]);
            }
            $this->usage->ensureBudget($tenant);
        } catch (AiException $e) {
            $this->usage->recordBlocked($call, $e);
            throw $e;
        }

        $plan = $this->plans->create($tenant, $identity->user, $identity->guestHash, $data);

        return response()->json(['success' => true, 'data' => ['plan' => $this->plans->present($plan)]]);
    }

    public function show(string $id)
    {
        $tenant = $this->context->get();
        $this->usage->ensureModule($tenant, 'ai_plan');
        $plan = preg_match('/^[0-9a-f-]{36}$/i', $id) ? AiPlan::query()->where('tenant_id', $tenant->id)->find($id) : null;
        if (! $plan) {
            throw new ApiException('NOT_FOUND', __('ai.errors.plan_not_found'), 404);
        }

        return response()->json(['success' => true, 'data' => ['plan' => $this->plans->present($plan)]]);
    }
}
