<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LocaleHelper;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantTime;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    /**
     * Shape a ticketable event into the "ticket" object the app expects.
     */
    private function eventToTicket(Event $event): array
    {
        $date = $event->start_time?->format('Y-m-d') ?? TenantTime::today();
        $available = $event->getAvailableTicketsForDate($date);

        return [
            'id' => $event->id,
            'event_id' => $event->id,
            'type' => 'standard',
            'title' => LocaleHelper::getLocalizedField($event, 'title'),
            'title_ar' => $event->title_ar,
            'description' => LocaleHelper::getLocalizedField($event, 'description'),
            'price' => (float) $event->price,
            'currency' => $event->currency,
            'quantity' => $event->total_tickets,
            'available_quantity' => $available === -1 ? 9999 : $available,
            'valid_from' => TenantTime::iso($event->start_time),
            'valid_to' => TenantTime::iso($event->end_time),
            'image' => $event->image_url,
            'is_active' => $event->is_active,
        ];
    }

    public function available()
    {
        // Active, ticketed events whose window hasn't ended yet.
        $events = Event::where('is_active', true)
            ->whereDate('end_time', '>=', TenantTime::today())
            ->orderBy('start_time')
            ->get();

        $tickets = $events
            ->map(fn ($event) => $this->eventToTicket($event))
            ->filter(fn ($t) => $t['available_quantity'] > 0)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    public function pricing()
    {
        $events = Event::where('is_active', true)
            ->orderBy('start_time')
            ->get()
            ->map(fn ($event) => $this->eventToTicket($event))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
            'date' => 'required|date'
        ]);

        $event = Event::find($request->event_id);
        if (!$event || !$event->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Event not available'
            ], 404);
        }

        $date = date('Y-m-d', strtotime($request->date));
        $available = $event->getAvailableTicketsForDate($date);

        if (!$event->canBookForDate($date, $request->quantity)) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough tickets available for the selected date',
                'available' => $available === -1 ? null : max(0, $available),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'price_per_ticket' => (float) $event->price,
            'total_price' => (float) $event->price * $request->quantity
        ]);
    }

    public function purchase(Request $request)
    {
        // This will be handled through PaymentController
        return response()->json([
            'success' => false,
            'message' => 'Please use /payments/initialize endpoint for ticket purchase'
        ]);
    }

    public function myTickets(Request $request)
    {
        $tickets = Ticket::with(['event', 'ticketType'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Shape each ticket to the app's UserTicket model (a nested `ticket`
        // object with title + validity, plus the ticket number and QR code).
        $data = collect($tickets->items())->map(function ($t) {
            $event = $t->event;
            return [
                'id' => $t->id,
                'ticket_code' => $t->ticket_number,
                'qr_code' => $t->qr_code,
                'status' => $t->status,
                'purchase_date' => $t->purchase_date,
                'used_at' => $t->used_at,
                // docs/ticket_types_contract.md §2
                'booking_date' => $t->booking_date?->format('Y-m-d'),
                'ticket_type' => $t->ticketTypeApi(),
                'admits' => $t->admits(),
                'entries_used' => (int) $t->check_in_count,
                'ticket' => [
                    'id' => $t->event_id,
                    'event_id' => $t->event_id,
                    'type' => $t->ticket_type,
                    'title' => $event ? LocaleHelper::getLocalizedField($event, 'title') : $t->ticket_type,
                    'price' => (float) $t->price,
                    'currency' => $t->currency,
                    'valid_from' => TenantTime::isoDate($t->booking_date) ?? TenantTime::iso($event?->start_time),
                    'valid_to' => TenantTime::iso($event?->end_time) ?? TenantTime::isoDate($t->booking_date),
                    'image' => $event->image_url ?? null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total()
            ]
        ]);
    }

    public function show($id)
    {
        $ticket = Ticket::where('user_id', request()->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            // ticket_type is the type object (docs/ticket_types_contract.md §2); the key stays in ticket_type_key.
            'data' => array_merge($ticket->toArray(), [
                'ticket_type' => $ticket->ticketTypeApi(),
                'ticket_type_key' => $ticket->ticket_type,
                'admits' => $ticket->admits(),
                'entries_used' => (int) $ticket->check_in_count,
            ]),
        ]);
    }

    public function getQrCode($id)
    {
        $ticket = Ticket::where('user_id', request()->user()->id)
            ->findOrFail($id);

        $qrCode = base64_encode(QrCode::format('png')->size(300)->generate($ticket->qr_code));

        return response()->json([
            'success' => true,
            'qr_code' => 'data:image/png;base64,' . $qrCode,
            'ticket_number' => $ticket->ticket_number
        ]);
    }

    /**
     * Transfer an active ticket to another registered user by phone number.
     */
    public function transfer(Request $request, $id)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'holder_name' => 'nullable|string|max:255',
        ]);

        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);

        // Only active, unused tickets can be transferred.
        if ($ticket->status !== 'active' || $ticket->check_in_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only unused, active tickets can be transferred',
            ], 400);
        }

        $recipient = User::where('phone_number', $request->phone_number)->first();
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'No registered user found for this phone number',
            ], 404);
        }

        if ($recipient->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'The ticket already belongs to you',
            ], 400);
        }

        $ticket->update([
            'user_id' => $recipient->id,
            'holder_name' => $request->holder_name ?? $recipient->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket transferred successfully',
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'transferred_to' => $recipient->name,
            ],
        ]);
    }
}
