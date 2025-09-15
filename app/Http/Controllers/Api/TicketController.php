<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    public function available()
    {
        $tickets = TicketPricing::where('is_available', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    public function pricing()
    {
        $pricing = TicketPricing::all();

        return response()->json([
            'success' => true,
            'data' => $pricing
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'ticket_type' => 'required|string',
            'quantity' => 'required|integer|min:1|max:10',
            'date' => 'required|date'
        ]);

        $pricing = TicketPricing::where('ticket_type', $request->ticket_type)
            ->where('is_available', true)
            ->first();

        if (!$pricing) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'price_per_ticket' => $pricing->price,
            'total_price' => $pricing->price * $request->quantity
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
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
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
            'data' => $ticket
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
}
