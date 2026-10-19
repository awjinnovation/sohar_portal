<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Models\User;

/** Who/what an OpenAI call is for (tenant-scoped logging + budget). */
final class AiCall
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $feature,
        public readonly ?User $user = null,
        public readonly ?string $guestHash = null,
    ) {}

    /** Opaque, non-PII identifier passed to OpenAI for abuse detection. */
    public function safetyIdentifier(): string
    {
        $who = $this->user ? 'u'.$this->user->id : ($this->guestHash ? 'g'.$this->guestHash : 'anon');

        return substr(hash('sha256', config('app.key').'|'.$this->tenant->id.'|'.$who), 0, 32);
    }
}
