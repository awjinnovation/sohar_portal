<?php

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Daily briefing card on the admin dashboard (contract §A5): aggregates of
 * yesterday + today (sales, top events, top-ups, pending refunds, vendor
 * totals, gate flow) summarised by the fast model. Cached per tenant, day
 * and admin language; "Regenerate" forces a fresh one. Only aggregates
 * (no names of visitors, phones or e-mails) are sent. Arabic briefings
 * go through ArabicTextFixer.
 */
class BriefingService
{
    public function __construct(private OpenAiClient $client) {}

    public function cacheKey(Tenant $tenant, string $locale): string
    {
        return 'ai_briefing:'.$tenant->id.':'.TenantTime::today($tenant).':'.$locale;
    }

    public function cached(Tenant $tenant, string $locale): ?array
    {
        return Cache::get($this->cacheKey($tenant, $locale));
    }

    public function get(Tenant $tenant, User $user, string $locale, bool $refresh = false): array
    {
        $key = $this->cacheKey($tenant, $locale);
        if (! $refresh && ($hit = Cache::get($key))) {
            return $hit + ['cached' => true];
        }

        $data = $this->aggregates($tenant);
        $data['organizer'] = $tenant->localizedName($locale);
        $call = new AiCall($tenant, 'briefing', $user);
        $schema = [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'bullets' => ['type' => 'array', 'items' => ['type' => 'string']],
                'actions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['headline', 'bullets', 'actions'],
            'additionalProperties' => false,
        ];
        $payload = $this->client->payload('fast', [
            'instructions' => Prompts::briefing($tenant, $locale),
            'input' => [['role' => 'user', 'content' => 'DATA: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'daily_briefing', 'schema' => $schema, 'strict' => true]],
            'max_output_tokens' => 900,
        ]);
        $out = $this->client->create($payload, $call)->json();
        if (! $out) {
            throw AiException::unavailable('briefing_invalid_json');
        }

        $fix = fn (string $s) => $locale === 'ar' ? ArabicTextFixer::fix($s) : $s;
        $clean = fn ($list, $n) => array_values(array_slice(array_filter(array_map(fn ($s) => is_string($s) ? $fix(mb_substr(trim(strip_tags($s)), 0, 240)) : '', (array) $list)), 0, $n));
        $result = [
            'date' => TenantTime::today($tenant),
            'headline' => $fix(mb_substr(trim(strip_tags((string) ($out['headline'] ?? ''))), 0, 240)),
            'bullets' => $clean($out['bullets'] ?? [], 6),
            'actions' => $clean($out['actions'] ?? [], 3),
            'figures' => $data,
            'generated_at' => TenantTime::iso(now(), $tenant),
        ];
        Cache::put($key, $result, Carbon::now(TenantTime::zone($tenant))->endOfDay()->addHour());

        return $result + ['cached' => false];
    }

    /** Aggregates for yesterday and today in the tenant time zone. */
    public function aggregates(Tenant $tenant): array
    {
        $tz = TenantTime::zone($tenant);
        $appTz = config('app.timezone');
        $todayStart = Carbon::now($tz)->startOfDay();
        $periods = [
            'yesterday' => [$todayStart->copy()->subDay()->setTimezone($appTz), $todayStart->copy()->setTimezone($appTz)],
            'today_so_far' => [$todayStart->copy()->setTimezone($appTz), Carbon::now($tz)->addMinute()->setTimezone($appTz)],
        ];
        $money = fn (int $m) => $tenant->currency().' '.$tenant->formatMoney($m);
        $safe = function (callable $fn, $fallback = null) {
            try {
                return $fn();
            } catch (Throwable $e) {
                report($e);

                return $fallback;
            }
        };

        $out = ['organizer' => $tenant->localizedName('en'), 'today' => TenantTime::today($tenant), 'weekday' => Carbon::now($tz)->englishDayOfWeek];
        foreach ($periods as $name => [$from, $to]) {
            $p = [];
            $p['ticket_sales'] = $safe(fn () => $money((int) Payment::where('tenant_id', $tenant->id)->where('status', 'completed')->where('payment_type', Payment::TYPE_TICKET)->whereBetween('created_at', [$from, $to])->sum('amount_minor')), '0');
            $p['tickets_sold'] = $safe(fn () => (int) Ticket::where('tenant_id', $tenant->id)->whereBetween('created_at', [$from, $to])->sum('quantity'), 0);
            $p['top_events'] = $safe(fn () => Ticket::where('tenant_id', $tenant->id)->whereBetween('created_at', [$from, $to])
                ->selectRaw('event_id, SUM(quantity) as q')->groupBy('event_id')->orderByDesc('q')->limit(3)->get()
                ->map(fn ($r) => ['event' => Event::withoutTenantScope()->where('tenant_id', $tenant->id)->find($r->event_id)?->title ?? ('#'.$r->event_id), 'tickets' => (int) $r->q])->all(), []);
            $p['topups'] = $safe(fn () => [
                'count' => WalletTransaction::where('tenant_id', $tenant->id)->where('type', 'topup')->where('direction', 'credit')->where('status', 'completed')->whereBetween('created_at', [$from, $to])->count(),
                'total' => $money((int) WalletTransaction::where('tenant_id', $tenant->id)->where('type', 'topup')->where('direction', 'credit')->where('status', 'completed')->whereBetween('created_at', [$from, $to])->sum('amount_minor')),
            ], []);
            $p['vendor_sales'] = $safe(function () use ($tenant, $from, $to, $money) {
                if (! Schema::hasColumn('wallet_transactions', 'vendor_id')) {
                    return [];
                }

                return WalletTransaction::where('tenant_id', $tenant->id)->where('type', 'vendor_payment')->where('direction', 'debit')->whereBetween('created_at', [$from, $to])
                    ->selectRaw('vendor_id, SUM(amount_minor) as s, COUNT(*) as n')->groupBy('vendor_id')->orderByDesc('s')->limit(5)->get()
                    ->map(fn ($r) => ['vendor' => Vendor::withoutTenantScope()->find($r->vendor_id)?->localizedName('en') ?? ('#'.$r->vendor_id), 'sales' => $money((int) $r->s), 'payments' => (int) $r->n])->all();
            }, []);
            $p['gate'] = $safe(fn () => $this->gateFlow($tenant, $from, $to), null);
            $out[$name] = $p;
        }
        $out['refunds_pending'] = $safe(fn () => [
            'count' => RefundRequest::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'total' => $money((int) RefundRequest::where('tenant_id', $tenant->id)->where('status', 'pending')->sum('amount_minor')),
        ], []);
        $out['events_running_today'] = $safe(fn () => Event::where('tenant_id', $tenant->id)->where('is_active', true)
            ->where('start_time', '<=', TenantTime::today($tenant).' 23:59:59')->where('end_time', '>=', TenantTime::today($tenant).' 00:00:00')->count(), 0);

        return $out;
    }

    private function gateFlow(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        if (Schema::hasTable('gate_scans')) {
            $rows = DB::table('gate_scans')->where('tenant_id', $tenant->id)->whereBetween('scanned_at', [$from, $to])
                ->selectRaw('result, COUNT(*) as c')->groupBy('result')->pluck('c', 'result')->all();

            return ['admitted' => (int) (($rows['valid'] ?? 0) + ($rows['reentry_ok'] ?? 0)), 'rejected' => (int) (array_sum($rows) - ($rows['valid'] ?? 0) - ($rows['reentry_ok'] ?? 0))];
        }

        return ['checked_in' => Ticket::where('tenant_id', $tenant->id)->whereBetween('used_at', [$from, $to])->count()];
    }
}
