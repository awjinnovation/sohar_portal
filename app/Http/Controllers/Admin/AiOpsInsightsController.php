<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiCall;
use App\Services\Ai\AiException;
use App\Services\Ai\AiUsage;
use App\Services\Ai\OpsInsightsService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * GET /admin/gates/insights?date=YYYY-MM-DD[&refresh=1] — JSON for the gate
 * dashboard's "AI insights" card (contract §A6; the card itself is rendered
 * by the gate dashboard). Aggregates are always returned; the AI summary
 * needs the copilot switch, budget and rate limit.
 */
class AiOpsInsightsController extends Controller
{
    /** Same permission as the gate dashboard (RolesAndPermissionsSeeder). */
    public const ABILITIES = ['manage gates'];

    public function __invoke(Request $request, TenantContext $context, AiUsage $usage, OpsInsightsService $insights)
    {
        $user = $request->user();
        abort_unless(collect(self::ABILITIES)->contains(fn ($a) => $user->can($a)), 403);
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'refresh' => ['nullable', 'boolean'],
        ]);
        $tenant = $context->get();
        $date = $data['date'] ?? TenantTime::today($tenant);
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $refresh = (bool) ($data['refresh'] ?? false);

        $call = new AiCall($tenant, 'ops_insights', $user);
        try {
            $usage->ensureCopilot($tenant);
            $usage->throttle('copilot:u:'.$tenant->id.':'.$user->id, (array) config('ai.copilot.rate', ['max' => 30, 'minutes' => 10]));
            if ($refresh) {
                $usage->throttle('copilot-regen:'.$tenant->id.':'.$user->id, (array) config('ai.copilot.regenerate_rate', ['max' => 6, 'minutes' => 60]));
            }
            $usage->ensureBudget($tenant);
        } catch (AiException $e) {
            if ($e->errorCode !== 'AI_DISABLED') {
                $usage->recordBlocked($call, $e);
            }

            // Still useful without AI: aggregates + the reason.
            return response()->json(['success' => true, 'data' => [
                'date' => $date,
                'available' => $insights->available(),
                'generated_at' => TenantTime::iso(now(), $tenant),
                'summary' => null,
                'recommendations' => [],
                'anomalies' => [],
                'aggregates' => $insights->aggregates($tenant, $date),
                'ai' => false,
                'ai_error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                'cached' => false,
            ]]);
        }

        try {
            return response()->json(['success' => true, 'data' => $insights->insights($tenant, $user, $date, $locale, $refresh)]);
        } catch (AiException $e) {
            return response()->json(['success' => true, 'data' => [
                'date' => $date,
                'available' => $insights->available(),
                'generated_at' => TenantTime::iso(now(), $tenant),
                'summary' => null,
                'recommendations' => [],
                'anomalies' => [],
                'aggregates' => $insights->aggregates($tenant, $date),
                'ai' => false,
                'ai_error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                'cached' => false,
            ]]);
        }
    }
}
