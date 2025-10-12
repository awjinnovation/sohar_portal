<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    private $thawaniUrl;
    private $secretKey;

    public function __construct()
    {
        $this->thawaniUrl = config('services.thawani.url', 'https://uatcheckout.thawani.om');
        $this->secretKey = config('services.thawani.secret_key');
    }

    /**
     * Payment success page - User is redirected here after successful payment
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        $transactionId = $request->query('transaction_id');

        // Try to find payment by transaction_id first (from URL parameter)
        $payment = null;
        if ($transactionId) {
            $payment = Payment::where('transaction_id', $transactionId)->first();
        }

        // If session_id provided, verify with Thawani
        if ($sessionId) {
            try {
                // Verify payment with Thawani
                $response = Http::withHeaders([
                    'thawani-api-key' => $this->secretKey
                ])->get($this->thawaniUrl . '/api/v1/checkout/session/' . $sessionId);

                if ($response->successful()) {
                    $sessionData = $response->json()['data'];

                    // Find payment by session ID if not found by transaction_id
                    if (!$payment) {
                        $payment = Payment::where('thawani_session_id', $sessionId)->first();
                    }

                    if ($payment) {
                        // Update payment status if paid
                        if ($sessionData['payment_status'] === 'paid' && $payment->status !== 'completed') {
                            $payment->update([
                                'status' => 'completed',
                                'paid_at' => now(),
                                'thawani_response' => $sessionData
                            ]);

                            // Create tickets if needed
                            $this->processPayment($payment);
                        }

                        return view('payment.success', [
                            'payment' => $payment,
                            'sessionData' => $sessionData,
                            'transactionId' => $payment->transaction_id
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return view('payment.error', [
                    'message' => 'An error occurred while processing your payment',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // If payment found by transaction_id but no session verification
        if ($payment && $payment->status === 'completed') {
            return view('payment.success', [
                'payment' => $payment,
                'sessionData' => $payment->thawani_response ?? [],
                'transactionId' => $payment->transaction_id
            ]);
        }

        return view('payment.error', [
            'message' => 'Invalid payment session or payment not found'
        ]);
    }

    /**
     * Payment cancel page - User is redirected here when canceling payment
     */
    public function cancel(Request $request)
    {
        $sessionId = $request->query('session_id');
        $transactionId = $request->query('transaction_id');

        $payment = null;

        // Try to find by transaction_id first
        if ($transactionId) {
            $payment = Payment::where('transaction_id', $transactionId)->first();
        }

        // Then try to find by session_id
        if (!$payment && $sessionId) {
            $payment = Payment::where('thawani_session_id', $sessionId)->first();
        }

        // Update payment status to cancelled if it's still pending
        if ($payment && $payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
        }

        return view('payment.cancel', [
            'sessionId' => $sessionId,
            'payment' => $payment
        ]);
    }

    /**
     * Download ticket page
     */
    public function downloadTicket($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->where('status', 'completed')
            ->firstOrFail();

        $tickets = $payment->user->tickets()
            ->where('payment_id', $payment->id)
            ->get();

        return view('payment.download', [
            'payment' => $payment,
            'tickets' => $tickets
        ]);
    }

    /**
     * Download single ticket
     */
    public function downloadSingleTicket($ticketId)
    {
        $ticket = \App\Models\Ticket::with(['event.map_location', 'user', 'payment'])
            ->where('id', $ticketId)
            ->where('status', 'active')
            ->firstOrFail();

        return view('payment.single-ticket', [
            'ticket' => $ticket
        ]);
    }

    /**
     * Process payment and create tickets
     */
    private function processPayment($payment)
    {
        if ($payment->payment_type === 'ticket') {
            $metadata = $payment->metadata;
            $quantity = $metadata['quantity'] ?? 1;

            // Check if tickets already created
            $existingTickets = \App\Models\Ticket::where('payment_id', $payment->id)->count();

            if ($existingTickets === 0) {
                for ($i = 0; $i < $quantity; $i++) {
                    \App\Models\Ticket::create([
                        'user_id' => $payment->user_id,
                        'event_id' => $payment->payable_id,
                        'payment_id' => $payment->id,
                        'ticket_type' => 'general',
                        'status' => 'active',
                        'price' => $payment->amount / $quantity,
                        'currency' => $payment->currency,
                        'qr_code' => \Str::uuid(),
                        'purchase_date' => now()
                    ]);
                }
            }
        }
    }
}
