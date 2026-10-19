<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\Vendor;
use App\Services\Wallet\DepositService;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public const STATUSES = ['held', 'released', 'forfeited'];

    public function __construct(private DepositService $deposits)
    {
    }

    public function index(Request $request)
    {
        $filters = [
            'status' => in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : null,
            'type' => in_array($request->input('type'), Deposit::TYPES, true) ? $request->input('type') : null,
            'vendor_id' => $request->filled('vendor_id') ? (int) $request->input('vendor_id') : null,
        ];

        $deposits = Deposit::with(['user', 'vendor', 'event'])
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($filters['vendor_id'], fn ($q, $v) => $q->where('vendor_id', $v))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'held_sum' => (int) Deposit::where('status', 'held')->sum('amount_minor'),
            'held_count' => Deposit::where('status', 'held')->count(),
            'forfeited_sum' => (int) Deposit::where('status', 'forfeited')->sum('amount_minor'),
        ];

        $vendors = Vendor::orderBy('id')->get();

        return view('admin.deposits.index', compact('deposits', 'filters', 'summary', 'vendors'));
    }

    public function release(Request $request, Deposit $deposit)
    {
        $request->validate(['forfeit' => ['nullable', 'boolean']]);
        $forfeit = $request->boolean('forfeit');

        if ($deposit->status !== 'held') {
            return back()->with('error', 'هذا التأمين ليس محجوزًا');
        }

        try {
            $deposit = $this->deposits->release($deposit, $forfeit, $request->user()->id);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record($forfeit ? 'deposit.forfeit' : 'deposit.release', $deposit, ['amount_minor' => $deposit->amount_minor]);

        return back()->with('success', $forfeit ? 'صودر التأمين' : 'أُرجع التأمين إلى رصيد المستخدم');
    }
}
