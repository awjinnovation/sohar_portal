<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SatisfactionResponse;
use App\Services\Satisfaction\RadiSettings;
use App\Services\Satisfaction\SatisfactionReport;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Admin → «راضي» visitor satisfaction report and CSV export
 * (docs/radi_satisfaction_contract.md §4). Permission `view satisfaction`.
 */
class SatisfactionController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $report = SatisfactionReport::fromRequest($request, $tenant);

        $comments = $report->rows()->whereNotNull('comment')
            ->with(['event:id,title,title_ar', 'user:id,name,phone_number'])
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.satisfaction.index', [
            'report' => $report,
            'kpis' => $report->kpis(),
            'trend' => $report->trend(),
            'distribution' => $report->distribution(),
            'aspects' => $report->aspects(),
            'perEvent' => $report->perEvent(),
            'events' => $report->eventOptions(),
            'comments' => $comments,
            'aspectLabels' => collect(RadiSettings::aspects($tenant))->mapWithKeys(fn ($a) => [$a['key'] => \App\Support\Localized::text($a['label']) ?? $a['key']])->all(),
            'moduleOn' => $tenant->moduleEnabled('radi'),
            'tz' => TenantTime::zone($tenant),
        ]);
    }

    /** GET satisfaction/export: every response of the filters as CSV (UTF-8 with BOM for Excel). */
    public function export(Request $request)
    {
        $tenant = $this->context->get();
        $report = SatisfactionReport::fromRequest($request, $tenant);
        $keys = array_column($report->aspects(), 'key');
        $labels = array_column($report->aspects(), 'label', 'key');
        $tz = TenantTime::zone($tenant);

        AuditLog::record('satisfaction.exported', $tenant, $report->query());

        $headers = array_merge(
            [__('رقم الإجابة'), __('التاريخ والوقت'), __('الفعالية'), __('التقييم العام')],
            array_map(fn ($k) => $labels[$k] ?? $k, $keys),
            [__('التعليق'), __('يقبل التواصل'), __('الاسم'), __('الهاتف'), __('اللغة'), __('المنصة'), __('إصدار التطبيق')],
        );

        return response()->streamDownload(function () use ($report, $keys, $tz, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $report->rows()->with(['event:id,title,title_ar', 'user:id,name,phone_number'])
                ->orderBy('id')->chunkById(500, function ($chunk) use ($out, $keys, $tz) {
                    foreach ($chunk as $r) {
                        /** @var SatisfactionResponse $r */
                        $aspects = (array) $r->aspects;
                        // Name and phone only for visitors who agreed to be contacted.
                        $contact = $r->contact_ok && $r->user;
                        fputcsv($out, array_map([self::class, 'cell'], array_merge(
                            [$r->id, $r->created_at?->copy()->setTimezone($tz)->format('Y-m-d H:i'), SatisfactionReport::eventTitle($r->event) ?? '', $r->score],
                            array_map(fn ($k) => $aspects[$k] ?? '', $keys),
                            [$r->comment ?? '', $r->contact_ok ? __('نعم') : __('لا'), $contact ? ($r->user->name ?? '') : '', $contact ? ($r->user->phone_number ?? '') : '',
                                $r->locale, $r->platform ?? '', $r->app_version ?? ''],
                        )));
                    }
                });
            fclose($out);
        }, 'radi-'.$tenant->slug.'-'.$report->from.'-'.$report->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralizes spreadsheet formulas in free text (CSV injection). */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
