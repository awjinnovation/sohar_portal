<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gate & ops insights (contract §A6): aggregates of `gate_scans` (schema
 * §B1) for one tenant-local day — per gate, per hour, results, peaks and
 * anomalies — plus a short AI summary with concrete recommendations.
 * Only aggregates are sent to the model (no ticket ids, codes, staff or
 * visitor data). Works (without AI) when the gate tables are missing.
 * Arabic output goes through ArabicTextFixer.
 */
class OpsInsightsService
{
    public const ADMIT = ['valid', 'reentry_ok'];

    public function __construct(private OpenAiClient $client) {}

    public function available(): bool
    {
        return Schema::hasTable('gate_scans');
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds of a tenant-local day */
    private function bounds(Tenant $tenant, string $date): array
    {
        $start = Carbon::parse($date, TenantTime::zone($tenant))->startOfDay();

        return [$start->copy()->setTimezone(config('app.timezone')), $start->copy()->addDay()->setTimezone(config('app.timezone'))];
    }

    public function aggregates(Tenant $tenant, string $date): array
    {
        $empty = [
            'totals' => ['scans' => 0, 'admitted' => 0, 'rejected' => 0, 'entries' => 0, 'exits' => 0, 'offline' => 0, 'devices' => 0],
            'by_result' => (object) [],
            'per_gate' => [],
            'by_hour' => [],
            'peaks' => [],
            'reject_reasons' => (object) [],
            'anomalies' => ['repeated_invalid_codes' => 0, 'max_repeats_of_one_invalid_code' => 0, 'tickets_admitted_at_two_gates' => 0, 'offline_conflicts' => 0],
        ];
        if (! $this->available()) {
            return $empty;
        }

        [$from, $to] = $this->bounds($tenant, $date);
        $tz = TenantTime::zone($tenant);
        $columns = Schema::getColumnListing('gate_scans');
        $has = fn (string $c) => in_array($c, $columns, true);

        $gates = [];
        if (Schema::hasTable('gates')) {
            foreach (DB::table('gates')->where('tenant_id', $tenant->id)->get() as $g) {
                $name = is_string($g->name ?? null) ? (json_decode($g->name, true) ?? $g->name) : ($g->name ?? null);
                $label = is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : (string) $name;
                $gates[$g->id] = trim(($g->code ?? '') !== '' ? 'Gate '.$g->code.($label !== '' ? ' ('.$label.')' : '') : ($label ?: '#'.$g->id));
            }
        }

        $select = array_values(array_filter(['gate_id', 'result', 'scanned_at', $has('direction') ? 'direction' : null, $has('offline') ? 'offline' : null,
            $has('conflict') ? 'conflict' : null, $has('code_hash') ? 'code_hash' : null, $has('ticket_id') ? 'ticket_id' : null, $has('device_id') ? 'device_id' : null]));

        $totals = $empty['totals'];
        $byResult = [];
        $perGate = [];
        $byHour = [];
        $gateHour = [];
        $invalidCodes = [];
        $ticketGates = [];
        $devices = [];
        $conflicts = 0;

        foreach (DB::table('gate_scans')->where('tenant_id', $tenant->id)->whereBetween('scanned_at', [$from, $to])->select($select)->orderBy('scanned_at')->lazy(2000) as $s) {
            $result = (string) $s->result;
            $admit = in_array($result, self::ADMIT, true);
            $exit = in_array($s->direction ?? null, ['out', 'exit'], true);  // gate_scans.direction: in | out
            $hour = Carbon::parse($s->scanned_at, config('app.timezone'))->setTimezone($tz)->format('H');
            $gate = $s->gate_id !== null ? (int) $s->gate_id : 0;

            $totals['scans']++;
            $admit ? $totals['admitted']++ : $totals['rejected']++;
            $exit ? $totals['exits']++ : ($admit ? $totals['entries']++ : null);
            if (! empty($s->offline)) {
                $totals['offline']++;
            }
            if (! empty($s->conflict)) {
                $conflicts++;
            }
            if (isset($s->device_id)) {
                $devices[$s->device_id] = true;
            }
            $byResult[$result] = ($byResult[$result] ?? 0) + 1;

            $perGate[$gate] ??= ['gate' => $gates[$gate] ?? ($gate ? '#'.$gate : 'unassigned'), 'scans' => 0, 'admitted' => 0, 'rejected' => 0, 'exits' => 0];
            $perGate[$gate]['scans']++;
            $admit ? $perGate[$gate]['admitted']++ : $perGate[$gate]['rejected']++;
            if ($exit) {
                $perGate[$gate]['exits']++;
            }

            $byHour[$hour] ??= ['hour' => $hour.':00', 'in' => 0, 'out' => 0, 'rejects' => 0];
            if ($exit) {
                $byHour[$hour]['out']++;
            } elseif ($admit) {
                $byHour[$hour]['in']++;
            }
            if (! $admit) {
                $byHour[$hour]['rejects']++;
            }
            $gateHour[$gate.'|'.$hour] = ($gateHour[$gate.'|'.$hour] ?? 0) + 1;

            if ($result === 'invalid' && ! empty($s->code_hash)) {
                $invalidCodes[$s->code_hash] = ($invalidCodes[$s->code_hash] ?? 0) + 1;
            }
            if ($admit && ! $exit && ! empty($s->ticket_id)) {
                $ticketGates[$s->ticket_id][$gate] = true;
            }
        }

        if ($totals['scans'] === 0) {
            return $empty;
        }

        arsort($gateHour);
        $peaks = [];
        foreach (array_slice($gateHour, 0, 3, true) as $key => $count) {
            [$g, $h] = explode('|', $key);
            $peaks[] = ['gate' => $perGate[(int) $g]['gate'] ?? $g, 'window' => $h.':00–'.sprintf('%02d', ((int) $h + 1) % 24).':00', 'scans' => $count];
        }
        foreach ($perGate as $g => &$row) {
            $best = null;
            foreach ($gateHour as $key => $count) {
                if (str_starts_with($key, $g.'|')) {
                    $best = [explode('|', $key)[1], $count];
                    break;
                }
            }
            $row['peak_hour'] = $best ? $best[0].':00' : null;
            $row['peak_hour_scans'] = $best[1] ?? 0;
            $row['reject_rate_percent'] = $row['scans'] ? round($row['rejected'] * 100 / $row['scans'], 1) : 0;
        }
        unset($row);
        ksort($byHour);
        $rejects = array_diff_key($byResult, array_flip(self::ADMIT));
        arsort($rejects);
        $repeated = array_filter($invalidCodes, fn ($n) => $n >= 3);
        $totals['devices'] = count($devices);

        return [
            'totals' => $totals,
            'by_result' => $byResult,
            'per_gate' => array_values($perGate),
            'by_hour' => array_values($byHour),
            'peaks' => $peaks,
            'reject_reasons' => $rejects ?: (object) [],
            'anomalies' => [
                'repeated_invalid_codes' => count($repeated),
                'max_repeats_of_one_invalid_code' => $repeated ? max($repeated) : 0,
                'tickets_admitted_at_two_gates' => count(array_filter($ticketGates, fn ($g) => count($g) > 1)),
                'offline_conflicts' => $conflicts,
            ],
        ];
    }

    /**
     * Response of GET /admin/gates/insights (documented in the contract §A6).
     */
    public function insights(Tenant $tenant, ?User $user, string $date, string $locale, bool $refresh = false): array
    {
        $key = 'ai_ops_insights:'.$tenant->id.':'.$date.':'.$locale;
        if (! $refresh && ($hit = Cache::get($key))) {
            return array_merge($hit, ['cached' => true]);
        }

        $aggregates = $this->aggregates($tenant, $date);
        $result = [
            'date' => $date,
            'available' => $this->available(),
            'generated_at' => TenantTime::iso(now(), $tenant),
            'summary' => null,
            'recommendations' => [],
            'anomalies' => [],
            'aggregates' => $aggregates,
            'ai' => false,
        ];

        if ($aggregates['totals']['scans'] === 0) {
            $result['summary'] = __($this->available() ? 'ai.ops.no_scans' : 'ai.ops.not_available', [], $locale);
        } else {
            $call = new AiCall($tenant, 'ops_insights', $user);
            $schema = [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'anomalies' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['summary', 'recommendations', 'anomalies'],
                'additionalProperties' => false,
            ];
            $payload = $this->client->payload('fast', [
                'instructions' => Prompts::opsInsights($tenant, $locale),
                'input' => [['role' => 'user', 'content' => 'DATA: '.json_encode(['date' => $date] + $aggregates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'gate_insights', 'schema' => $schema, 'strict' => true]],
                'max_output_tokens' => 900,
            ]);
            $out = $this->client->create($payload, $call)->json() ?? [];
            $fix = fn (string $s) => $locale === 'ar' ? ArabicTextFixer::fix($s) : $s;
            $clean = fn ($list, $n) => array_values(array_slice(array_filter(array_map(fn ($s) => is_string($s) ? $fix(mb_substr(trim(strip_tags($s)), 0, 240)) : '', (array) $list)), 0, $n));
            $result['summary'] = $fix(mb_substr(trim(strip_tags((string) ($out['summary'] ?? ''))), 0, 600)) ?: null;
            $result['recommendations'] = $clean($out['recommendations'] ?? [], 4);
            $result['anomalies'] = $clean($out['anomalies'] ?? [], 4);
            $result['ai'] = true;
        }

        $isToday = $date === TenantTime::today($tenant);
        Cache::put($key, $result, $isToday ? now()->addMinutes(10) : now()->addDay());

        return array_merge($result, ['cached' => false]);
    }
}
