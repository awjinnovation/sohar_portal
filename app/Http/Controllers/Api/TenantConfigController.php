<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantConfigPresenter;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantConfigController extends Controller
{
    /**
     * GET /api/v1/tenant/config — public, cacheable, ETag (contract §5).
     */
    public function show(Request $request, TenantContext $context)
    {
        $tenant = $context->get();

        $payload = Cache::remember('tenant_config_'.$tenant->id, config('tenancy.config_cache_ttl', 300), function () use ($tenant) {
            $data = TenantConfigPresenter::present($tenant);

            return ['data' => $data, 'etag' => '"'.sha1(json_encode($data)).'"'];
        });

        $etag = $payload['etag'];
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=300',
            'Vary' => 'X-Tenant',
        ];

        $ifNoneMatch = array_map('trim', explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array($etag, $ifNoneMatch, true) || in_array('W/'.$etag, $ifNoneMatch, true)) {
            return response('', 304, $headers);
        }

        return response()->json(['success' => true, 'data' => $payload['data']], 200, $headers);
    }
}
