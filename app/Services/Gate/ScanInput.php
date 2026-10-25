<?php

namespace App\Services\Gate;

use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;

/** Everything TicketScanService::scan() needs to know about one scan. */
final class ScanInput
{
    public const RECORD_ALL = 'all';
    public const RECORD_REJECTS = 'rejects';
    public const RECORD_NONE = 'none';

    public function __construct(
        /** Raw scanned payload (qr_code; ticket_number for source=manual). */
        public ?string $code = null,
        /** Salted hash from an offline scan (sync) instead of the code. */
        public ?string $codeHash = null,
        /** Ticket already resolved by the caller (legacy mark-used). */
        public ?Ticket $ticket = null,
        public ?Gate $gate = null,
        public ?GateDevice $device = null,
        public ?User $staff = null,
        public string $source = 'camera',
        /** Requested direction (in/out/entry/exit) — the gate's direction wins. */
        public ?string $direction = null,
        /** Device time of the scan. */
        public ?CarbonInterface $scannedAt = null,
        public ?string $idempotencyKey = null,
        public bool $offline = false,
        /** What the device decided offline (sync only). */
        public ?string $resultLocal = null,
        /** false = check only, never mark the ticket used (legacy /tickets/validate). */
        public bool $commit = true,
        public string $record = self::RECORD_ALL,
        public ?string $validatedBy = null,
        public ?string $location = null,
        public ?string $notes = null,
    ) {}
}
