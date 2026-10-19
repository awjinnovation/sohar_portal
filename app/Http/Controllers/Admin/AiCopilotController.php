<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MapLocation;
use App\Models\Tenant;
use App\Services\Ai\AiCall;
use App\Services\Ai\AiException;
use App\Services\Ai\AiUsage;
use App\Services\Ai\BriefingService;
use App\Services\Ai\CopilotService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Organizer copilot JSON endpoints (contract §A5), called from the event /
 * announcement forms and the dashboard via fetch (CSRF). Every endpoint is
 * permission-gated; outputs are suggestions — nothing is saved here.
 */
class AiCopilotController extends Controller
{
    public function __construct(private TenantContext $context, private AiUsage $usage) {}

    private function guard(Request $request, string $feature, bool $regenerate = false): Tenant
    {
        $tenant = $this->context->get();
        $call = new AiCall($tenant, 'copilot.'.$feature, $request->user());
        $this->usage->ensureCopilot($tenant);
        try {
            $this->usage->throttle('copilot:u:'.$tenant->id.':'.$request->user()->id, (array) config('ai.copilot.rate', ['max' => 30, 'minutes' => 10]));
            if ($regenerate) {
                $this->usage->throttle('copilot-regen:'.$tenant->id.':'.$request->user()->id, (array) config('ai.copilot.regenerate_rate', ['max' => 6, 'minutes' => 60]));
            }
            $this->usage->ensureBudget($tenant);
        } catch (AiException $e) {
            $this->usage->recordBlocked($call, $e);
            throw $e;
        }

        return $tenant;
    }

    private function ok(array $data)
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /admin/ai/copilot/event-description */
    public function eventDescription(Request $request, CopilotService $copilot)
    {
        abort_unless($request->user()->can('manage events'), 403);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_ar' => ['nullable', 'string', 'max:255'],
            'map_location_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'string', 'max:30'],
            'end_time' => ['nullable', 'string', 'max:30'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if (blank($data['title'] ?? null) && blank($data['title_ar'] ?? null)) {
            return response()->json(['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => __('ai.admin.need_title')], 422);
        }
        $tenant = $this->guard($request, 'describe');
        $category = ! empty($data['category_id']) ? Category::find($data['category_id']) : null;
        $place = ! empty($data['map_location_id']) ? MapLocation::find($data['map_location_id']) : null;
        $facts = array_filter([
            'title_en' => $data['title'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'category' => $category ? trim($category->name.' / '.$category->name_ar, ' /') : null,
            'venue' => trim(implode(' / ', array_filter([$data['location'] ?? null, $data['location_ar'] ?? null, $place?->name, $place?->name_ar])), ' /') ?: null,
            'starts' => $data['start_time'] ?? null,
            'ends' => $data['end_time'] ?? null,
            'price' => isset($data['price']) && $data['price'] !== '' ? ((float) $data['price'] > 0 ? $tenant->currency().' '.$tenant->formatMoney($tenant->toMinor($data['price'])) : 'free') : null,
            'organizer' => $data['organizer_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $out = $copilot->describeEvent($tenant, $request->user(), $facts);

        return $this->ok(['description_ar' => (string) ($out['description_ar'] ?? ''), 'description' => (string) ($out['description_en'] ?? '')]);
    }

    /**
     * POST /admin/ai/copilot/content-description — summary + description of a
     * place to visit, a collection item or a page, in both languages, from
     * the form facts only (docs/places_content_contract.md §4).
     */
    public function contentDescription(Request $request, CopilotService $copilot)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:place,item,page'],
            'title' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'wilayat' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);
        $permission = $data['kind'] === 'place' ? 'manage places' : 'manage content';
        abort_unless($request->user()->can($permission), 403);
        if (blank($data['title'] ?? null) && blank($data['title_ar'] ?? null)) {
            return response()->json(['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => __('ai.admin.need_title')], 422);
        }
        $tenant = $this->guard($request, 'describe_content');
        $facts = array_filter([
            'kind' => ['place' => 'place to visit', 'item' => 'directory entry (service, partner or guide card)', 'page' => 'information page'][$data['kind']],
            'name_en' => $data['title'] ?? null,
            'name_ar' => $data['title_ar'] ?? null,
            'category' => $data['category'] ?? null,
            'collection' => $data['collection'] ?? null,
            'wilayat' => $data['wilayat'] ?? null,
            'address' => $data['address'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? null,
            'notes' => isset($data['notes']) ? \App\Support\HtmlSanitizer::toText($data['notes'], 1500) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $out = $copilot->describeContent($tenant, $request->user(), $facts);

        return $this->ok([
            'summary_ar' => (string) ($out['summary_ar'] ?? ''),
            'summary' => (string) ($out['summary_en'] ?? ''),
            'description_ar' => (string) ($out['description_ar'] ?? ''),
            'description' => (string) ($out['description_en'] ?? ''),
        ]);
    }

    /** POST /admin/ai/copilot/translate {fields: {title: "...", ...}, from, to} */
    public function translate(Request $request, CopilotService $copilot)
    {
        $user = $request->user();
        abort_unless($user->can('manage events') || $user->can('manage announcements') || $user->can('manage places') || $user->can('manage content'), 403);
        $data = $request->validate([
            'from' => ['required', 'in:ar,en'],
            'to' => ['required', 'in:ar,en', 'different:from'],
            'fields' => ['required', 'array', 'min:1', 'max:10'],
            'fields.*' => ['nullable', 'string', 'max:8000'],
        ]);
        $fields = [];
        foreach ($data['fields'] as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z_]{1,32}$/', $key) && filled($value)) {
                $fields[$key] = $value;
            }
        }
        if (! $fields) {
            return response()->json(['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => __('ai.admin.nothing_to_translate')], 422);
        }
        $tenant = $this->guard($request, 'translate');

        return $this->ok(['fields' => $copilot->translate($tenant, $request->user(), $fields, $data['from'], $data['to']), 'to' => $data['to']]);
    }

    /** POST /admin/ai/copilot/shorten {text, locale, max_chars?} */
    public function shorten(Request $request, CopilotService $copilot)
    {
        $user = $request->user();
        abort_unless($user->can('manage events') || $user->can('manage announcements') || $user->can('manage places') || $user->can('manage content'), 403);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:6000'],
            'locale' => ['required', 'in:ar,en'],
            'max_chars' => ['nullable', 'integer', 'min:60', 'max:2000'],
        ]);
        $tenant = $this->guard($request, 'shorten');
        $max = (int) ($data['max_chars'] ?? max(80, min(400, (int) (mb_strlen($data['text']) * 0.55))));
        $out = $copilot->shorten($tenant, $request->user(), $data['text'], $data['locale'], $max);

        return $this->ok(['text' => (string) ($out['text'] ?? ''), 'locale' => $data['locale']]);
    }

    /** POST /admin/ai/copilot/announcement {brief, type?} */
    public function announcement(Request $request, CopilotService $copilot)
    {
        abort_unless($request->user()->can('manage announcements'), 403);
        $data = $request->validate([
            'brief' => ['required', 'string', 'min:5', 'max:500'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);
        $tenant = $this->guard($request, 'announcement');
        $out = $copilot->draftAnnouncement($tenant, $request->user(), $data['brief'], $data['type'] ?? null);

        return $this->ok([
            'title_ar' => (string) ($out['title_ar'] ?? ''),
            'title' => (string) ($out['title_en'] ?? ''),
            'content_ar' => (string) ($out['content_ar'] ?? ''),
            'content' => (string) ($out['content_en'] ?? ''),
        ]);
    }

    /** GET /admin/ai/briefing (cached per tenant + day) · POST …/regenerate */
    public function briefing(Request $request, BriefingService $briefings)
    {
        abort_unless($request->user()->can('view payments'), 403);
        $tenant = $this->context->get();
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $refresh = $request->isMethod('post');
        if (! $refresh && ($hit = $briefings->cached($tenant, $locale))) {
            return $this->ok($hit + ['cached' => true]);
        }
        $this->guard($request, 'briefing', $refresh);
        $result = $briefings->get($tenant, $request->user(), $locale, $refresh);

        return $this->ok($result);
    }
}
