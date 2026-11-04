<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SatisfactionResponse;
use App\Models\User;
use App\Services\Satisfaction\RadiSettings;
use App\Services\Satisfaction\SatisfactionService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Visitor satisfaction survey «راضي» (docs/radi_satisfaction_contract.md §3).
 * Module `radi` (route middleware). Auth optional: a Sanctum user, or a
 * guest identified by the app's per-install `device_id` (stored hashed).
 */
class SatisfactionController extends Controller
{
    private const DEVICE_RULE = ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-:.]{8,128}$/'];

    public function __construct(private TenantContext $context, private SatisfactionService $service) {}

    /** GET /satisfaction/survey?event_id=&device_id= */
    public function survey(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
            'device_id' => self::DEVICE_RULE,
        ]);
        $tenant = $this->context->get();
        $user = $this->user($request);
        $device = SatisfactionResponse::hashDevice($data['device_id'] ?? null);
        $event = isset($data['event_id']) ? Event::query()->find($data['event_id']) : null;

        $answered = $this->service->answeredToday($tenant, $user, $device, $event?->id);
        $last = $this->service->lastResponse($tenant, $user, $device);

        return response()->json(['success' => true, 'data' => array_merge(RadiSettings::present($tenant), [
            'scale' => ['min' => 1, 'max' => 5],
            'comment_max' => RadiSettings::COMMENT_MAX,
            'event' => $event ? ['id' => $event->id, 'title' => $this->eventTitle($event)] : null,
            'answered' => $answered !== null,
            'answered_at' => TenantTime::iso($answered?->created_at, $tenant),
            'last_response_at' => TenantTime::iso($last?->created_at, $tenant),
            'signed_in' => $user !== null,
        ])]);
    }

    /** POST /satisfaction/responses */
    public function store(Request $request)
    {
        $tenant = $this->context->get();
        $keys = RadiSettings::aspectKeys($tenant);
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'aspects' => ['nullable', 'array', 'max:'.RadiSettings::MAX_ASPECTS],
            'aspects.*' => ['nullable', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.RadiSettings::COMMENT_MAX],
            'contact_ok' => ['nullable', 'boolean'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'ticket_id' => ['nullable', 'integer', 'min:1'],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'platform' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9_\-]+$/i'],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.+\-]+$/'],
            'device_id' => self::DEVICE_RULE,
        ]);
        foreach (array_keys((array) ($data['aspects'] ?? [])) as $key) {
            if (! in_array($key, $keys, true)) {
                throw ValidationException::withMessages(['aspects' => __('satisfaction.errors.unknown_aspect', ['key' => mb_substr((string) $key, 0, 40)])]);
            }
        }

        $user = $this->user($request);
        $device = SatisfactionResponse::hashDevice($data['device_id'] ?? null);
        if (! $user && ! $device) {
            throw ValidationException::withMessages(['device_id' => __('satisfaction.errors.device_required')]);
        }

        $response = $this->service->submit($tenant, $user, $device, $data);

        return response()->json([
            'success' => true,
            'message' => __('satisfaction.received'),
            'data' => [
                'id' => $response->id,
                'score' => $response->score,
                'event_id' => $response->event_id,
                'created_at' => TenantTime::iso($response->created_at, $tenant),
            ],
        ], 201);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }

    private function eventTitle(Event $event): string
    {
        return app()->getLocale() === 'ar' ? ($event->title_ar ?: $event->title) : ($event->title ?: $event->title_ar);
    }
}
