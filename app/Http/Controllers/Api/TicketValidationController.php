<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateQrCodeRequest;
use App\Http\Requests\MarkTicketUsedRequest;
use App\Http\Resources\TicketValidationResource;
use App\Http\Resources\ValidationHistoryResource;
use App\Models\Ticket;
use App\Services\Gate\ScanInput;
use App\Services\Gate\ScanOutcome;
use App\Services\Gate\ScanResult;
use App\Services\Gate\TicketScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketValidationController extends Controller
{
    /**
     * Validate a QR code and return ticket information (check only — the
     * ticket is marked used by mark-used). Decided by the gate module's
     * TicketScanService; rejections are logged in gate_scans.
     * POST /api/v1/tickets/validate
     */
    public function validateQrCode(ValidateQrCodeRequest $request, TicketScanService $scanner): JsonResponse
    {
        $outcome = $scanner->scan(new ScanInput(
            code: (string) $request->input('qr_code'),
            staff: $request->user(),
            source: 'camera',
            commit: false,
            record: ScanInput::RECORD_REJECTS,
        ));

        if ($outcome->result === ScanResult::INVALID) {
            return response()->json([
                'success' => false,
                'message' => 'رمز QR غير صالح / Invalid QR code',
                'error_code' => 'INVALID_QR_CODE',
                'result' => $outcome->result,
                'messages' => $outcome->message(),
            ], 404);
        }

        $ticket = $outcome->ticket->load(['event', 'user', 'payment']);

        if (! $outcome->admitted()) {
            [$code, $message, $data] = $this->legacyError($outcome, $ticket);

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => $code,
                'result' => $outcome->result,
                'messages' => $outcome->message(),
                'data' => $data,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'data' => new TicketValidationResource($ticket),
        ], 200);
    }

    /**
     * Mark a ticket as used/checked-in (one entry, row-locked, logged, with
     * the optional check-in points reward) via TicketScanService.
     * POST /api/v1/tickets/{ticket}/mark-used
     */
    public function markAsUsed(MarkTicketUsedRequest $request, string $ticketId, TicketScanService $scanner): JsonResponse
    {
        // Find ticket by ID or ticket_number
        $ticket = Ticket::where(fn ($q) => $q->where('id', $ticketId)->orWhere('ticket_number', $ticketId))
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
                'error_code' => 'TICKET_NOT_FOUND',
            ], 404);
        }

        // The validator is always the authenticated staff member (identified
        // by name + id) — never free-text client input — so the audit trail
        // cannot be spoofed.
        $staff = $request->user();
        $outcome = $scanner->scan(new ScanInput(
            ticket: $ticket,
            staff: $staff,
            source: 'camera',
            validatedBy: $staff ? sprintf('%s (#%d)', $staff->name, $staff->id) : 'unknown',
            location: $request->input('location'),
            notes: $request->input('validation_notes'),
        ));

        if ($outcome->result !== ScanResult::VALID) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark ticket as used',
                'error_code' => 'INVALID_OPERATION',
                'details' => $this->legacyError($outcome, $ticket)[1],
                'result' => $outcome->result,
                'messages' => $outcome->message(),
            ], 400);
        }

        $ticket = $outcome->ticket;

        return response()->json([
            'success' => true,
            'message' => 'Ticket marked as used successfully',
            'data' => [
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'validated_at' => \App\Support\TenantTime::iso($ticket->used_at),
                'validated_by' => $ticket->validated_by,
                'check_in_count' => $ticket->check_in_count,
            ],
        ], 200);
    }

    /**
     * Legacy error code / bilingual message / data for a rejected scan
     * (codes kept from the original staff screen API).
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function legacyError(ScanOutcome $outcome, Ticket $ticket): array
    {
        $m = $outcome->message();
        $both = fn (string $ar, string $en) => "$ar / $en";

        return match (true) {
            in_array($outcome->result, [ScanResult::CANCELLED, ScanResult::REFUNDED], true) => ['TICKET_CANCELLED', $outcome->result === ScanResult::REFUNDED ? $both($m['ar'], $m['en']) : 'التذكرة ملغاة / Ticket cancelled', ['ticket_number' => $ticket->ticket_number]],
            $outcome->result === ScanResult::ALREADY_USED => ['TICKET_ALREADY_USED', 'التذكرة مستخدمة من قبل / Ticket already used', [
                'validated_at' => \App\Support\TenantTime::iso($ticket->used_at),
                'validated_by' => $ticket->validated_by,
            ]],
            $outcome->result === ScanResult::WRONG_DATE && $outcome->detail === 'event_ended' => ['EVENT_ENDED', 'انتهت الفعالية / Event has ended', ['ticket_number' => $ticket->ticket_number]],
            $outcome->result === ScanResult::WRONG_DATE && (($outcome->params['when'] ?? null) === 'past' || $outcome->detail === 'expired') => ['TICKET_EXPIRED', 'انتهت صلاحية التذكرة / Ticket expired', [
                'expiry_date' => \App\Support\TenantTime::isoDate($ticket->booking_date),
            ]],
            $outcome->result === ScanResult::WRONG_DATE => ['WRONG_DATE', $both($m['ar'], $m['en']), ['ticket_number' => $ticket->ticket_number, 'valid_date' => $ticket->booking_date?->toDateString()]],
            $outcome->result === ScanResult::NOT_YET_OPEN => ['NOT_YET_OPEN', $both($m['ar'], $m['en']), ['ticket_number' => $ticket->ticket_number]],
            default => [strtoupper($outcome->result), $both($m['ar'], $m['en']), ['ticket_number' => $ticket->ticket_number]],
        };
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
