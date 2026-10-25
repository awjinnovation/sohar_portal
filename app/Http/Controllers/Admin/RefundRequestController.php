<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Services\Wallet\RefundRequestService;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public const STATUSES = ['pending', 'approved', 'paid', 'rejected'];

    public function __construct(private RefundRequestService $refunds)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        if ($status !== 'all' && ! in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $requests = RefundRequest::with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = RefundRequest::selectRaw('status, count(*) as c, sum(amount_minor) as total')
            ->groupBy('status')->get()->keyBy('status');

        return view('admin.refund-requests.index', compact('requests', 'status', 'counts'));
    }

    public function show(RefundRequest $refundRequest)
    {
        $refundRequest->load(['user', 'wallet', 'decider']);

        return view('admin.refund-requests.show', ['refund' => $refundRequest]);
    }

    public function approve(Request $request, RefundRequest $refundRequest)
    {
        return $this->transition($request, $refundRequest, 'approve', 'قُبل طلب الاسترداد');
    }

    public function reject(Request $request, RefundRequest $refundRequest)
    {
        return $this->transition($request, $refundRequest, 'reject', 'رُفض الطلب وأُعيد المبلغ إلى المحفظة');
    }

    public function markPaid(Request $request, RefundRequest $refundRequest)
    {
        return $this->transition($request, $refundRequest, 'markPaid', 'سُجِّل الطلب مدفوعًا');
    }

    private function transition(Request $request, RefundRequest $refundRequest, string $action, string $message)
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->refunds->{$action}($refundRequest, $request->user(), $data['admin_note'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.refund-requests.show', $refundRequest)->with('success', $message);
    }
}
