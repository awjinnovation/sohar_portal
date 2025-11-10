<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\BankMuscatPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private BankMuscatPaymentService $bankMuscatService;

    public function __construct(BankMuscatPaymentService $bankMuscatService)
    {
        $this->bankMuscatService = $bankMuscatService;
    }

    /**
     * Initialize payment session with Bank Muscat
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'payment_type' => 'required|in:ticket,workshop',
            'payable_id' => 'required|integer',
            'quantity' => 'required_if:payment_type,ticket|integer|min:1|max:10',
            'amount' => 'required|numeric|min:0.1',
            'booking_date' => 'required_if:payment_type,ticket|date|date_format:Y-m-d'
        ]);

        $user = $request->user();
        $transactionId = 'TXN' . strtoupper(Str::random(10));

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'payment_type' => $request->payment_type,
            'payable_id' => $request->payable_id,
            'payable_type' => $request->payment_type === 'ticket' ? 'App\\Models\\Ticket' : 'App\\Models\\CulturalWorkshop',
            'amount' => $request->amount,
            'currency' => 'OMR',
            'status' => 'pending',
            'metadata' => [
                'quantity' => $request->quantity ?? 1,
                'booking_date' => $request->booking_date,
                'user_phone' => $user->phone_number
            ]
        ]);

        try {
            // Prepare payment data for Bank Muscat
            $paymentData = $this->bankMuscatService->preparePaymentData($payment);

            // Generate encrypted request
            $encryptedData = $this->bankMuscatService->generateEncryptedRequest($paymentData);

            // Store Bank Muscat TID in payment record
            $payment->update([
                'bank_muscat_tid' => $paymentData['tid'],
                'bank_muscat_order_id' => $paymentData['order_id']
            ]);

            // Generate a redirect URL for the payment form page
            $redirectUrl = route('payment.redirect', ['transaction_id' => $payment->transaction_id]);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'checkout_url' => $redirectUrl,
                'encrypted_data' => $encryptedData,
                'access_code' => $this->bankMuscatService->getAccessCode(),
                'gateway_url' => $this->bankMuscatService->getGatewayUrl(),
                'redirect_required' => true
            ]);

        } catch (\Exception $e) {
            $payment->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm payment after redirect from Bank Muscat
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|string'
        ]);

        try {
            // Find payment by transaction ID
            $payment = Payment::where('transaction_id', $request->transaction_id)->firstOrFail();

            // Return current payment status
            return response()->json([
                'success' => $payment->status === 'completed',
                'message' => $payment->status === 'completed' ? 'Payment successful' : 'Payment not completed',
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancel(Request $request)
    {
        // Get session ID from query parameter or request
        $sessionId = $request->query('session_id') ?? $request->input('session_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID is required'
            ], 400);
        }

        // Find payment by session ID
        $payment = Payment::where('thawani_session_id', $sessionId)->first();

        if ($payment) {
            $payment->update([
                'status' => 'cancelled'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled successfully'
        ]);
    }

    /**
     * Check payment status
     */
    public function checkStatus($transactionId)
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'payment_status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_data' => $payment->bank_muscat_response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Status check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Bank Muscat webhook/callback
     * This is called by Bank Muscat after payment processing
     */
    public function webhook(Request $request)
    {
        try {
            // Get encrypted response from Bank Muscat
            $encResponse = $request->input('encResp');

            if (!$encResponse) {
                return response()->json(['success' => false, 'message' => 'No response data'], 400);
            }

            // Decrypt the response
            $responseData = $this->bankMuscatService->decryptResponse($encResponse);

            // Find payment by order_id (transaction_id)
            $payment = Payment::where('transaction_id', $responseData['order_id'] ?? '')->first();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
            }

            // Validate the response
            if (!$this->bankMuscatService->validateResponse($responseData, $payment)) {
                return response()->json(['success' => false, 'message' => 'Invalid payment response'], 400);
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

            // Handle post-payment logic if successful
            if ($status === 'completed' && $payment->status !== 'completed') {
                if ($payment->payment_type === 'ticket') {
                    $this->createTickets($payment);
                } elseif ($payment->payment_type === 'workshop') {
                    $this->registerWorkshop($payment);
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('Bank Muscat webhook error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user's transaction history
     */
    public function myTransactions(Request $request)
    {
        $user = $request->user();

        $transactions = Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total()
            ]
        ]);
    }

    /**
     * Create tickets after successful payment
     */
    private function createTickets($payment)
    {
        $metadata = $payment->metadata;
        $quantity = $metadata['quantity'] ?? 1;

        // Check if tickets already created
        $existingTickets = Ticket::where('transaction_id', $payment->transaction_id)->count();

        if ($existingTickets === 0) {
            $bookingDate = $metadata['booking_date'] ?? null;

            for ($i = 0; $i < $quantity; $i++) {
                Ticket::create([
                    'user_id' => $payment->user_id,
                    'event_id' => $payment->payable_id,
                    'transaction_id' => $payment->transaction_id,
                    'booking_date' => $bookingDate,
                    'ticket_type' => 'standard',
                    'status' => 'active',
                    'price' => $payment->amount / $quantity,
                    'currency' => $payment->currency,
                    'holder_name' => $payment->user->name,
                    'qr_code' => Str::uuid(),
                    'purchase_date' => now()
                ]);
            }
        }
    }

    /**
     * Register for workshop after successful payment
     */
    private function registerWorkshop($payment)
    {
        // Create workshop registration
        \DB::table('workshop_registrations')->insert([
            'user_id' => $payment->user_id,
            'workshop_id' => $payment->payable_id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Increment participant count
        \App\Models\CulturalWorkshop::find($payment->payable_id)
            ->increment('current_participants');
    }

}