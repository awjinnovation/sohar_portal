<?php

namespace App\Services\Gate;

use App\Models\GateScan;
use App\Models\Ticket;

/** Result of TicketScanService::scan(). */
final class ScanOutcome
{
    public function __construct(
        public string $result,
        public string $direction = 'in',
        public ?Ticket $ticket = null,
        /** Finer reason: past|future|expired|event_ended|used_at|opens_at|gate_types|... */
        public ?string $detail = null,
        public array $params = [],
        public ?GateScan $scan = null,
        public bool $conflict = false,
        /** Same idempotency key seen before: nothing was changed. */
        public bool $replay = false,
        /** First check-in of this ticket happened in this scan. */
        public bool $firstCheckIn = false,
    ) {}

    public function admitted(): bool
    {
        return ScanResult::isAdmitted($this->result);
    }

    /** @return array{en: string, ar: string} */
    public function message(): array
    {
        return ScanResult::message($this->result, $this->detail, $this->params, $this->direction);
    }
}
