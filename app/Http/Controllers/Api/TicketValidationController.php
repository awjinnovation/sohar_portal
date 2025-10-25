<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateQrCodeRequest;
use App\Http\Requests\MarkTicketUsedRequest;
use App\Http\Resources\TicketValidationResource;
use App\Http\Resources\ValidationHistoryResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketValidationController extends Controller
{
    /**
     * Validate a QR code and return ticket information
     * POST /api/v1/tickets/validate
     */
    public function validate(ValidateQrCodeRequest $request): JsonResponse
    {
        $qrCode = $request->input('qr_code');

        // Find ticket by QR code
        $ticket = Ticket::with(['event', 'user', 'payment'])
            ->where('qr_code', $qrCode)
            ->first();

        // Ticket not found
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'رمز QR غير صالح / Invalid QR code',
                'error_code' => 'INVALID_QR_CODE',
            ], 404);
        }

        // Get validation status
        $validationStatus = $ticket->getValidationStatus();

        // If ticket is not valid, return error with appropriate status code
        if (!$validationStatus['is_valid']) {
            $statusCode = match ($validationStatus['reason']) {
                'TICKET_CANCELLED', 'TICKET_EXPIRED', 'TICKET_ALREADY_USED', 'EVENT_ENDED' => 400,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $validationStatus['message'],
                'error_code' => $validationStatus['reason'],
                'data' => $validationStatus['data'] ?? [
                    'ticket_number' => $ticket->ticket_number,
                ],
            ], $statusCode);
        }

        // Ticket is valid, return success with ticket details
        return response()->json([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'data' => new TicketValidationResource($ticket),
        ], 200);
    }

    /**
     * Mark a ticket as used/checked-in
     * POST /api/v1/tickets/{ticket}/mark-used
     */
    public function markAsUsed(MarkTicketUsedRequest $request, string $ticketId): JsonResponse
    {
        // Find ticket by ID or ticket_number
        $ticket = Ticket::where('id', $ticketId)
            ->orWhere('ticket_number', $ticketId)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
                'error_code' => 'TICKET_NOT_FOUND',
            ], 404);
        }

        // Check if ticket is valid for marking as used
        $validationStatus = $ticket->getValidationStatus();
        if (!$validationStatus['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark ticket as used',
                'error_code' => 'INVALID_OPERATION',
                'details' => $validationStatus['message'],
            ], 400);
        }

        // Mark ticket as used
        $ticket->markAsUsed(
            $request->input('validated_by'),
            $request->input('location'),
            $request->input('validation_notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket marked as used successfully',
            'data' => [
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'validated_at' => $ticket->used_at?->toIso8601String(),
                'validated_by' => $ticket->validated_by,
                'check_in_count' => $ticket->check_in_count,
            ],
        ], 200);
    }

    /**
     * Get validation history with filtering and pagination
     * GET /api/v1/tickets/validation-history
     */
    public function validationHistory(Request $request): JsonResponse
    {
        $query = Ticket::with(['event', 'user'])
            ->whereNotNull('used_at');

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('used_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('used_at', '<=', $request->input('date_to'));
        }

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination
        $perPage = min($request->input('limit', 20), 100); // Max 100 per page
        $page = $request->input('page', 1);

        $validations = $query->orderBy('used_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Validation history retrieved successfully',
            'data' => [
                'validations' => ValidationHistoryResource::collection($validations->items()),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'total_pages' => $validations->lastPage(),
                    'total_records' => $validations->total(),
                    'per_page' => $validations->perPage(),
                ],
            ],
        ], 200);
    }
}
