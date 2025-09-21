<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketPricing;
use App\Models\Event;
use Illuminate\Http\Request;

class TicketPricingController extends Controller
{
    public function index()
    {
        $pricings = TicketPricing::with('event')->paginate(10);
        return view('admin.ticket-pricing.index', compact('pricings'));
    }

    public function create()
    {
        $events = Event::all();
        return view('admin.ticket-pricing.create', compact('events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'benefits' => 'nullable|string',
            'benefits_ar' => 'nullable|string'
        ]);

        TicketPricing::create($validated);

        return redirect()->route('admin.ticket-pricing.index')
            ->with('success', 'تم إضافة سعر التذكرة بنجاح');
    }

    public function show(TicketPricing $ticketPricing)
    {
        return view('admin.ticket-pricing.show', compact('ticketPricing'));
    }

    public function edit(TicketPricing $ticketPricing)
    {
        $events = Event::all();
        return view('admin.ticket-pricing.edit', compact('ticketPricing', 'events'));
    }

    public function update(Request $request, TicketPricing $ticketPricing)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'benefits' => 'nullable|string',
            'benefits_ar' => 'nullable|string'
        ]);

        $ticketPricing->update($validated);

        return redirect()->route('admin.ticket-pricing.index')
            ->with('success', 'تم تحديث سعر التذكرة بنجاح');
    }

    public function destroy(TicketPricing $ticketPricing)
    {
        $ticketPricing->delete();

        return redirect()->route('admin.ticket-pricing.index')
            ->with('success', 'تم حذف سعر التذكرة بنجاح');
    }
}