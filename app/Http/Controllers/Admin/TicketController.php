<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['event', 'pricingTiers'])->latest()->paginate(20);
        return view('admin.tickets.index', compact('tickets'));
    }

    public function create()
    {
        $events = Event::active()->get();
        return view('admin.tickets.create', compact('events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:1',
            'available_quantity' => 'required|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'max_purchase' => 'nullable|integer|min:1',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        Ticket::create($validated);

        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم إضافة التذكرة بنجاح');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['event', 'pricingTiers']);
        return view('admin.tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        $events = Event::active()->get();
        return view('admin.tickets.edit', compact('ticket', 'events'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:1',
            'available_quantity' => 'required|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'max_purchase' => 'nullable|integer|min:1',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $ticket->update($validated);

        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم تحديث التذكرة بنجاح');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم حذف التذكرة بنجاح');
    }
}
