<?php

namespace App\Services\Satisfaction;

use App\Exceptions\ApiException;
use App\Models\Event;
use App\Models\SatisfactionResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantTime;
use Illuminate\Database\QueryException;

/**
 * Visitor side of the satisfaction survey (docs/radi_satisfaction_contract.md §3):
 * one response per user (signed in) or device (guest) per event per
 * tenant-local day.
 */
class SatisfactionService
{
    /** Latest response of this user / device today for the event (null event = the general survey). */
    public function answeredToday(Tenant $tenant, ?User $user, ?string $deviceHash, ?int $eventId): ?SatisfactionResponse
    {
        if (! $user && ! $deviceHash) {
            return null;
        }

        return SatisfactionResponse::query()
            ->where('tenant_id', $tenant->id)
            ->where('response_date', TenantTime::today($tenant))
            ->where(fn ($q) => $eventId ? $q->where('event_id', $eventId) : $q->whereNull('event_id'))
            ->where(fn ($q) => $this->whoScope($q, $user, $deviceHash))
            ->latest('id')
            ->first();
    }

    /** Latest response of this user / device for any event (Home prompt). */
    public function lastResponse(Tenant $tenant, ?User $user, ?string $deviceHash): ?SatisfactionResponse
    {
        if (! $user && ! $deviceHash) {
            return null;
        }

        return SatisfactionResponse::query()
            ->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $this->whoScope($q, $user, $deviceHash))
            ->latest('id')
            ->first();
    }

    /**
     * Stores a validated answer. Throws ALREADY_ANSWERED (409) for a second
     * answer the same day, TICKET_NOT_FOUND (422) for someone else's ticket.
     *
     * @param  array{score:int, aspects?:array, comment?:?string, contact_ok?:bool, event_id?:?int, ticket_id?:?int, locale?:?string, platform?:?string, app_version?:?string}  $data
     */
    public function submit(Tenant $tenant, ?User $user, ?string $deviceHash, array $data): SatisfactionResponse
    {
        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : null;
        $ticketId = isset($data['ticket_id']) ? (int) $data['ticket_id'] : null;

        if ($ticketId) {
            // A ticket links the answer only for its signed-in owner.
            $ticket = $user ? Ticket::query()->where('user_id', $user->id)->find($ticketId) : null;
            if (! $ticket) {
                throw new ApiException('TICKET_NOT_FOUND', __('satisfaction.errors.ticket_not_found'), 422);
            }
            $eventId ??= $ticket->event_id;
            if ($eventId !== (int) $ticket->event_id) {
                throw new ApiException('TICKET_NOT_FOUND', __('satisfaction.errors.ticket_not_found'), 422);
            }
        }
        if ($eventId && ! Event::query()->whereKey($eventId)->exists()) {
            throw new ApiException('EVENT_NOT_FOUND', __('satisfaction.errors.event_not_found'), 422);
        }

        if ($this->answeredToday($tenant, $user, $deviceHash, $eventId)) {
            throw $this->alreadyAnswered();
        }

        $today = TenantTime::today($tenant);
        $comment = trim((string) ($data['comment'] ?? ''));
        $comment = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $comment);
        $aspects = array_map('intval', array_filter((array) ($data['aspects'] ?? []), fn ($v) => $v !== null && $v !== ''));

        try {
            return SatisfactionResponse::create([
                'user_id' => $user?->id,
                'event_id' => $eventId,
                'ticket_id' => $ticketId,
                'score' => (int) $data['score'],
                'aspects' => $aspects ?: null,
                'comment' => $comment === '' ? null : $comment,
                // Consent to be contacted only means something for an account.
                'contact_ok' => $user ? (bool) ($data['contact_ok'] ?? false) : false,
                'locale' => in_array($data['locale'] ?? null, ['ar', 'en'], true) ? $data['locale'] : app()->getLocale(),
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'device_id' => $deviceHash,
                'response_date' => $today,
                'dedupe_key' => ($user ? 'u'.$user->id : 'd'.$deviceHash).'|e'.($eventId ?: 0).'|'.$today,
            ]);
        } catch (QueryException $e) {
            // Unique (tenant_id, dedupe_key): a parallel request won the race.
            if (str_contains(strtolower($e->getMessage()), 'unique') || ($e->errorInfo[1] ?? null) === 1062) {
                throw $this->alreadyAnswered();
            }
            throw $e;
        }
    }

    public function alreadyAnswered(): ApiException
    {
        return new ApiException('ALREADY_ANSWERED', __('satisfaction.errors.already_answered'), 409);
    }

    private function whoScope($q, ?User $user, ?string $deviceHash)
    {
        if ($user) {
            $q->where('user_id', $user->id);
            if ($deviceHash) {
                $q->orWhere(fn ($g) => $g->whereNull('user_id')->where('device_id', $deviceHash));
            }

            return $q;
        }

        return $q->where('device_id', $deviceHash);
    }
}
