<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform: "Book a demo" leads from the public landing page
 * (super-admin only, `manage demo requests`; not tenant-scoped).
 */
class DemoRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');
        if ($status !== 'all' && ! in_array($status, DemoRequest::STATUSES, true)) {
            $status = 'all';
        }
        $q = trim((string) $request->input('q', ''));

        $requests = DemoRequest::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('organization', 'like', $like)
                    ->orWhere('contact', 'like', $like)
                    ->orWhere('season', 'like', $like)
                    ->orWhere('notes', 'like', $like));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = DemoRequest::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.demo-requests.index', compact('requests', 'status', 'counts', 'q'));
    }

    public function show(DemoRequest $demoRequest)
    {
        return view('admin.demo-requests.show', ['demo' => $demoRequest]);
    }

    public function status(Request $request, DemoRequest $demoRequest)
    {
        $data = $request->validate(['status' => ['required', Rule::in(DemoRequest::STATUSES)]]);

        $demoRequest->update(['status' => $data['status']]);

        return redirect()->route('admin.demo-requests.show', $demoRequest)->with('success', __('حُدِّثت حالة الطلب'));
    }
}
