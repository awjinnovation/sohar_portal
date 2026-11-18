<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\OpsRoomMetrics;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin → Operations room: a full-screen page for the wall screen of the
 * technical operations room (tickets, SMS queue, ticket orders queue,
 * busiest gates, worker and scheduler). The page polls data every 10 s.
 * `manage gates` or `manage tickets`.
 */
class OpsRoomController extends Controller
{
    public const PERMISSIONS = ['manage gates', 'manage tickets'];

    /** Several screens open at once share one set of queries. */
    private const CACHE_SECONDS = 5;

    public function __construct(private TenantContext $context, private OpsRoomMetrics $metrics) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->canAny(self::PERMISSIONS), 403);

        return view('admin.ops-room', ['payload' => $this->payload()]);
    }

    public function data(Request $request)
    {
        abort_unless($request->user()?->canAny(self::PERMISSIONS), 403);

        return response()->json(['success' => true, 'data' => $this->payload()])
            ->header('Cache-Control', 'no-store, private');
    }

    private function payload(): array
    {
        $tenant = $this->context->get();
        abort_unless($tenant, 404);

        return Cache::remember('ops-room:'.$tenant->id.':'.app()->getLocale(), self::CACHE_SECONDS, fn () => $this->metrics->payload($tenant));
    }
}
