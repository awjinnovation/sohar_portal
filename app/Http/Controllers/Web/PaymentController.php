<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BankMuscatPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    private BankMuscatPaymentService $bankMuscatService;

    public function __construct(BankMuscatPaymentService $bankMuscatService)
    {
        $this->bankMuscatService = $bankMuscatService;
    }

    /**
     * Payment success page - User is redirected here after successful payment from Bank Muscat
     */
    public function success(Request $request)
    {
        try {
            // Bank Muscat sends encrypted response
            $encResponse = $request->input('encResp');

            if (!$encResponse) {
                // If no encrypted response, check for transaction_id in query string
                $transactionId = $request->query('transaction_id');

                if ($transactionId) {
                    $payment = Payment::where('transaction_id', $transactionId)->first();

                    if ($payment && $payment->status === 'completed') {
                        return view('payment.success', [
                            'payment' => $payment,
                            'sessionData' => $payment->bank_muscat_response ?? [],
                            'transactionId' => $payment->transaction_id
                        ]);
                    }
                }

                return view('payment.error', [
                    'message' => 'No payment response received'
                ]);
            }

            // Decrypt the response from Bank Muscat
            $responseData = $this->bankMuscatService->decryptResponse($encResponse);

            // Find payment by order_id (transaction_id)
            $payment = Payment::where('transaction_id', $responseData['order_id'] ?? '')->first();

            if (!$payment) {
                return view('payment.error', [
                    'message' => 'Payment not found'
                ]);
            }

            // Validate the response
            if (!$this->bankMuscatService->validateResponse($responseData, $payment)) {
                return view('payment.error', [
                    'message' => 'Security Error. Illegal access detected'
                ]);
            }

            // Get payment status
            $status = $this->bankMuscatService->getPaymentStatus($responseData);

            // Update payment record
            $payment->update([
                'status' => $status,
                'bank_muscat_response' => $responseData,
                'payment_method' => $responseData['payment_mode'] ?? 'card',
                'paid_at' => $status === 'completed' ? now() : null
            ]);

            // Handle different statuses
            if ($status === 'completed') {
                // Create tickets if needed
                $this->processPayment($payment);

                return view('payment.success', [
                    'payment' => $payment,
                    'sessionData' => $responseData,
                    'transactionId' => $payment->transaction_id
                ]);
            } elseif ($status === 'cancelled') {
                return view('payment.cancel', [
                    'sessionId' => null,
                    'payment' => $payment,
                    'message' => 'Payment has been aborted'
                ]);
            } else {
                return view('payment.error', [
                    'message' => 'Payment has failed. Please try again.',
                    'status' => $responseData['order_status'] ?? 'unknown'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Payment success page error: ' . $e->getMessage());

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
        try {
            // Bank Muscat sends encrypted response even for cancellation
            $encResponse = $request->input('encResp');

            if ($encResponse) {
                // Decrypt the response
                $responseData = $this->bankMuscatService->decryptResponse($encResponse);

                // Find payment by order_id
                $payment = Payment::where('transaction_id', $responseData['order_id'] ?? '')->first();

                if ($payment) {
                    $status = $this->bankMuscatService->getPaymentStatus($responseData);

                    $payment->update([
                        'status' => $status,
                        'bank_muscat_response' => $responseData
                    ]);

                    return view('payment.cancel', [
                        'sessionId' => null,
                        'payment' => $payment,
                        'message' => $responseData['status_message'] ?? 'Payment cancelled'
                    ]);
                }
            }

            // Fallback to query parameter
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
                'sessionId' => null,
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            \Log::error('Payment cancel page error: ' . $e->getMessage());

            return view('payment.cancel', [
                'sessionId' => null,
                'payment' => null,
                'message' => 'Payment cancelled'
            ]);
        }
    }

    /**
     * Download ticket page
     */
    public function downloadTicket($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)
            ->where('status', 'completed')
            ->firstOrFail();

        $tickets = \App\Models\Ticket::where('transaction_id', $transactionId)->get();

        return view('payment.download', [
            'payment' => $payment,
            'tickets' => $tickets
        ]);
    }

    /**
     * Download single ticket
     */
    public function downloadSingleTicket($qrCode)
    {
        $ticket = \App\Models\Ticket::with(['event.mapLocation', 'user', 'payment'])
            ->where('qr_code', $qrCode)
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
            $existingTickets = \App\Models\Ticket::where('transaction_id', $payment->transaction_id)->count();

            if ($existingTickets === 0) {
                $bookingDate = $metadata['booking_date'] ?? null;

                for ($i = 0; $i < $quantity; $i++) {
                    \App\Models\Ticket::create([
                        'user_id' => $payment->user_id,
                        'event_id' => $payment->payable_id,
                        'transaction_id' => $payment->transaction_id,
                        'booking_date' => $bookingDate,
                        'ticket_type' => 'standard',
                        'status' => 'active',
                        'price' => $payment->amount / $quantity,
                        'currency' => $payment->currency,
                        'holder_name' => $payment->user->name,
                        'qr_code' => \Str::uuid(),
                        'purchase_date' => now()
                    ]);
                }
            }
        }
    }
}
