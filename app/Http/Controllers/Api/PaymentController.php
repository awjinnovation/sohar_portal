<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketPricing;
use App\Services\BankMuscatPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'payment_type' => 'required|in:ticket',
            'payable_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
            'ticket_type' => 'nullable|string',
            'amount' => 'required|numeric|min:0.1',
            'booking_date' => 'required|date|date_format:Y-m-d'
        ]);

        $user = $request->user();
        $quantity = $request->quantity ?? 1;
        $ticketType = $request->ticket_type ?? 'standard';

        // Guard against overselling before we ever send the user to the gateway.
        $pricing = $this->resolvePricing($request->payable_id, $ticketType);
        if (!$pricing || $pricing->available_quantity < $quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough tickets available for the selected event',
                'available' => $pricing->available_quantity ?? 0,
            ], 422);
        }

        $transactionId = 'TXN' . strtoupper(Str::random(10));

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'payment_type' => $request->payment_type,
            'payable_id' => $request->payable_id,
            'payable_type' => 'App\\Models\\Ticket',
            'amount' => $request->amount,
            'currency' => 'OMR',
            'status' => 'pending',
            'metadata' => [
                'quantity' => $quantity,
                'ticket_type' => $ticketType,
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
        // Bank Muscat / the app identify the payment by our transaction id
        // (also accepted as order_id, which mirrors the transaction id).
        $transactionId = $request->query('transaction_id')
            ?? $request->input('transaction_id')
            ?? $request->input('order_id');

        if (!$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction ID is required'
            ], 400);
        }

        // Find payment by transaction id; only pending payments can be cancelled.
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if ($payment && $payment->status === 'pending') {
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
            // Defense-in-depth: if an IP allowlist is configured, reject callers
            // that are not on it. The encrypted payload is still the primary
            // integrity check, but this blocks casual probing of the endpoint.
            $allowedIps = config('services.bank_muscat.webhook_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
                Log::warning('Rejected payment webhook from unlisted IP', ['ip' => $request->ip()]);
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

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

            // Capture whether this payment had already been completed BEFORE we
            // overwrite the record, so we only fulfil tickets once (idempotency).
            $wasCompleted = $payment->status === 'completed';

            // Update payment record
            $payment->update([
                'status' => $status,
                'bank_muscat_response' => $responseData,
                'payment_method' => $responseData['payment_mode'] ?? 'card',
                'paid_at' => $status === 'completed' ? now() : null
            ]);

            // Handle post-payment logic if successful (first completion only)
            if ($status === 'completed' && !$wasCompleted && $payment->payment_type === 'ticket') {
                $this->createTickets($payment);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Bank Muscat webhook error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a single payment/transaction belonging to the authenticated user.
     */
    public function show(Request $request, $id)
    {
        $payment = Payment::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
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
     * Create tickets after successful payment.
     *
     * Runs in a transaction so that ticket creation and the inventory
     * decrement succeed or fail together, and locks the pricing row to
     * prevent two concurrent payments from overselling the same stock.
     */
    private function createTickets($payment)
    {
        $metadata = $payment->metadata;
        $quantity = $metadata['quantity'] ?? 1;
        $ticketType = $metadata['ticket_type'] ?? 'standard';
        $bookingDate = $metadata['booking_date'] ?? null;

        DB::transaction(function () use ($payment, $quantity, $ticketType, $bookingDate) {
            // Idempotency: never create tickets twice for the same payment.
            $existingTickets = Ticket::where('transaction_id', $payment->transaction_id)->count();
            if ($existingTickets > 0) {
                return;
            }

            // Decrement inventory under a row lock. Payment has already been
            // taken, so we always honour the tickets, but we floor at zero and
            // log if we detect the event was oversold.
            $pricing = $this->resolvePricing($payment->payable_id, $ticketType, true);
            if ($pricing) {
                if ($pricing->available_quantity < $quantity) {
                    Log::warning('Ticket inventory oversold', [
                        'transaction_id' => $payment->transaction_id,
                        'event_id' => $payment->payable_id,
                        'requested' => $quantity,
                        'available' => $pricing->available_quantity,
                    ]);
                }
                $pricing->available_quantity = max(0, $pricing->available_quantity - $quantity);
                $pricing->save();
            }

            for ($i = 0; $i < $quantity; $i++) {
                // qr_code is intentionally left empty so the Ticket model sets
                // it to the human-readable ticket_number (TKT-YYYY-NNNNNN),
                // which is the format the scanner/validator expects.
                Ticket::create([
                    'user_id' => $payment->user_id,
                    'event_id' => $payment->payable_id,
                    'transaction_id' => $payment->transaction_id,
                    'booking_date' => $bookingDate,
                    'ticket_type' => $ticketType,
                    'status' => 'active',
                    'price' => $payment->amount / $quantity,
                    'currency' => $payment->currency,
                    'holder_name' => $payment->user->name,
                    'purchase_date' => now()
                ]);
            }
        });
    }

    /**
     * Resolve the TicketPricing row for an event + ticket type.
     *
     * Falls back to the first pricing row for the event when the requested
     * type is not found. Pass $lock = true to acquire a FOR UPDATE row lock
     * (only valid inside a transaction).
     */
    private function resolvePricing(int $eventId, string $ticketType, bool $lock = false): ?TicketPricing
    {
        $base = TicketPricing::where('event_id', $eventId);

        $typed = (clone $base)->where('ticket_type', $ticketType);
        if ($lock) {
            $typed->lockForUpdate();
            $base->lockForUpdate();
        }

        return $typed->first() ?? $base->first();
    }

}