<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Issued tickets (read-only + cancel/reactivate). Tickets are only created
 * by the checkout (App\Services\Payments\TicketIssuer); prices live on the
 * event (price / pricing JSON).
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::with(['event', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->where(fn ($w) => $w->where('ticket_number', 'like', $term)
                    ->orWhere('holder_name', 'like', $term)
                    ->orWhere('transaction_id', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $events = Event::orderBy('title')->get(['id', 'title', 'title_ar']);

        return view('admin.tickets.index', compact('tickets', 'events'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['event', 'user', 'payment']);

        return view('admin.tickets.show', compact('ticket'));
    }

    /** Cancel / reactivate a ticket (status only). */
    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['status' => 'required|in:active,cancelled']);

        if ($ticket->status === 'used') {
            return back()->with('error', 'لا يمكن تعديل تذكرة مستخدمة');
        }

        $old = $ticket->status;
        $ticket->update(['status' => $data['status']]);
        AuditLog::record('ticket.status', $ticket, ['from' => $old, 'to' => $data['status']]);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'حُدِّثت حالة التذكرة');
    }
}
