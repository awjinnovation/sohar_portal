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
        $transactionId = $request->query('transaction_id');

        if (!$transactionId) {
            return view('payment.error', [
                'message' => 'Transaction ID is required'
            ]);
        }

        // Find payment by transaction_id
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return view('payment.error', [
                'message' => 'Payment not found'
            ]);
        }

        try {
            // If payment already completed, just show success page
            if ($payment->status === 'completed') {
                return view('payment.success', [
                    'payment' => $payment,
                    'sessionData' => $payment->thawani_response ?? [],
                    'transactionId' => $payment->transaction_id
                ]);
            }

            // Verify payment with Thawani using session_id from payment object
            if ($payment->thawani_session_id) {
                $response = Http::withHeaders([
                    'thawani-api-key' => $this->secretKey
                ])->get($this->thawaniUrl . '/api/v1/checkout/session/' . $payment->thawani_session_id);

                if ($response->successful()) {
                    $sessionData = $response->json()['data'];

                    // Update payment status if paid
                    if ($sessionData['payment_status'] === 'paid') {
                        $payment->update([
                            'status' => 'completed',
                            'paid_at' => now(),
                            'thawani_response' => $sessionData,
                            'payment_method' => $sessionData['payment_method'] ?? 'card'
                        ]);

                        // Create tickets if needed
                        $this->processPayment($payment);

                        return view('payment.success', [
                            'payment' => $payment,
                            'sessionData' => $sessionData,
                            'transactionId' => $payment->transaction_id
                        ]);
                    } else {
                        return view('payment.error', [
                            'message' => 'Payment was not completed. Status: ' . $sessionData['payment_status']
                        ]);
                    }
                }
            }

            return view('payment.error', [
                'message' => 'Unable to verify payment with Thawani'
            ]);

        } catch (\Exception $e) {
            return view('payment.error', [
                'message' => 'An error occurred while processing your payment',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Payment cancel page - User is redirected here when canceling payment
     */
    public function cancel(Request $request)
    {
        $transactionId = $request->query('transaction_id');

        if (!$transactionId) {
            return view('payment.cancel', [
                'sessionId' => null,
                'payment' => null
            ]);
        }

        // Find payment by transaction_id
        $payment = Payment::where('transaction_id', $transactionId)->first();

        // Update payment status to cancelled if it's still pending
        if ($payment && $payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
        }

        return view('payment.cancel', [
            'sessionId' => $payment->thawani_session_id ?? null,
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
