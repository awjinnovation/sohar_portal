<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private $thawaniUrl;
    private $secretKey;
    private $publishableKey;

    public function __construct()
    {
        $this->thawaniUrl = config('services.thawani.url', 'https://uatcheckout.thawani.om');
        $this->secretKey = config('services.thawani.secret_key');
        $this->publishableKey = config('services.thawani.publishable_key');
    }

    /**
     * Initialize payment session with Thawani
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'payment_type' => 'required|in:ticket,workshop',
            'payable_id' => 'required|integer',
            'quantity' => 'required_if:payment_type,ticket|integer|min:1|max:10',
            'amount' => 'required|numeric|min:0.1'
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
                'user_phone' => $user->phone_number
            ]
        ]);

        // Get or create Thawani customer
        $customerId = $this->getOrCreateCustomer($user);

        // Calculate unit price (if amount is total, divide by quantity to get unit price)
        $quantity = $request->quantity ?? 1;
        $unitAmount = (int)(($request->amount / $quantity) * 1000); // Convert to baisa

        // Prepare Thawani session data
        $sessionData = [
            'client_reference_id' => $transactionId,
            'mode' => 'payment',
            'products' => [
                [
                    'name' => $request->payment_type === 'ticket' ? 'Festival Ticket' : 'Workshop Registration',
                    'unit_amount' => $unitAmount,
                    'quantity' => $quantity
                ]
            ],
            'success_url' => config('app.url') . '/api/v1/payments/confirm',
            'cancel_url' => config('app.url') . '/api/v1/payments/cancel',
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id
            ]
        ];

        // Only add customer_id if we successfully created/found one
        if ($customerId) {
            $sessionData['customer_id'] = $customerId;
        }

        try {
            // Create Thawani checkout session
            $response = Http::withHeaders([
                'thawani-api-key' => $this->secretKey,
                'Content-Type' => 'application/json'
            ])->post($this->thawaniUrl . '/api/v1/checkout/session', $sessionData);

            if ($response->successful()) {
                $sessionInfo = $response->json();

                // Update payment with Thawani session ID
                $payment->update([
                    'thawani_session_id' => $sessionInfo['data']['session_id'],
                    'thawani_response' => $sessionInfo
                ]);

                return response()->json([
                    'success' => true,
                    'payment_id' => $payment->id,
                    'session_id' => $sessionInfo['data']['session_id'],
                    'checkout_url' => $this->thawaniUrl . '/checkout/' . $sessionInfo['data']['session_id'] . '?key=' . $this->publishableKey,
                    'expires_at' => $sessionInfo['data']['expires_at'] ?? null
                ]);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'thawani_response' => $response->json()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initialize payment session',
                    'error' => $response->json()
                ], 400);
            }
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
     * Confirm payment after redirect from Thawani
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            // Get session details from Thawani
            $response = Http::withHeaders([
                'thawani-api-key' => $this->secretKey
            ])->get($this->thawaniUrl . '/api/v1/checkout/session/' . $request->session_id);

            if ($response->successful()) {
                $sessionData = $response->json()['data'];

                // Find payment by session ID
                $payment = Payment::where('thawani_session_id', $request->session_id)->firstOrFail();

                if ($sessionData['payment_status'] === 'paid') {
                    // Update payment status
                    $payment->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'thawani_response' => $sessionData,
                        'payment_method' => $sessionData['payment_method'] ?? 'card'
                    ]);

                    // Handle post-payment logic based on payment type
                    if ($payment->payment_type === 'ticket') {
                        $this->createTickets($payment);
                    } elseif ($payment->payment_type === 'workshop') {
                        $this->registerWorkshop($payment);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment successful',
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id
                    ]);
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'thawani_response' => $sessionData
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment was not completed',
                        'status' => $sessionData['payment_status']
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment session'
                ], 400);
            }
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
    public function checkStatus($sessionId)
    {
        try {
            $response = Http::withHeaders([
                'thawani-api-key' => $this->secretKey
            ])->get($this->thawaniUrl . '/api/v1/checkout/session/' . $sessionId);

            if ($response->successful()) {
                $sessionData = $response->json()['data'];

                return response()->json([
                    'success' => true,
                    'payment_status' => $sessionData['payment_status'],
                    'session_data' => $sessionData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check payment status'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Status check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Thawani webhook
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature if provided by Thawani
        // TODO: Implement webhook signature verification

        $data = $request->all();

        if (isset($data['session_id']) && isset($data['payment_status'])) {
            $payment = Payment::where('thawani_session_id', $data['session_id'])->first();

            if ($payment) {
                if ($data['payment_status'] === 'paid' && $payment->status !== 'completed') {
                    $payment->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'thawani_response' => $data
                    ]);

                    // Handle post-payment logic
                    if ($payment->payment_type === 'ticket') {
                        $this->createTickets($payment);
                    } elseif ($payment->payment_type === 'workshop') {
                        $this->registerWorkshop($payment);
                    }
                } elseif (in_array($data['payment_status'], ['cancelled', 'failed'])) {
                    $payment->update([
                        'status' => $data['payment_status'],
                        'thawani_response' => $data
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
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

        for ($i = 0; $i < $quantity; $i++) {
            Ticket::create([
                'user_id' => $payment->user_id,
                'ticket_type' => 'general',
                'ticket_number' => 'TKT' . strtoupper(Str::random(8)),
                'qr_code' => Str::uuid(),
                'purchase_date' => now(),
                'status' => 'active',
                'payment_id' => $payment->id
            ]);
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

    /**
     * Get or create Thawani customer
     */
    private function getOrCreateCustomer($user)
    {
        // Check if user has thawani_customer_id stored
        if ($user->thawani_customer_id) {
            return $user->thawani_customer_id;
        }

        try {
            // Create customer in Thawani
            $customerData = [
                'client_customer_id' => (string)$user->id,
                'phone' => $user->phone_number ?? '',
                'email' => $user->email ?? '',
            ];

            $response = Http::withHeaders([
                'thawani-api-key' => $this->secretKey,
                'Content-Type' => 'application/json'
            ])->post($this->thawaniUrl . '/api/v1/customers', $customerData);

            if ($response->successful()) {
                $customerId = $response->json()['data']['id'];

                // Store customer ID in user record
                $user->update(['thawani_customer_id' => $customerId]);

                return $customerId;
            }
        } catch (\Exception $e) {
            // Log error but continue without customer_id
            \Log::warning('Failed to create Thawani customer: ' . $e->getMessage());
        }

        return null;
    }
}