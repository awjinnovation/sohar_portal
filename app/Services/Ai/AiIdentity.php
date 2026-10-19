<?php

namespace App\Services\Ai;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Caller of the visitor AI endpoints: a Sanctum user (optional auth) or a
 * guest identified by the app-generated opaque `X-Ai-Guest` header, stored
 * only as sha256 (with the app key) so the raw value never hits the DB.
 */
final class AiIdentity
{
    public const GUEST_HEADER = 'X-Ai-Guest';

    public function __construct(
        public readonly ?User $user,
        public readonly ?string $guestHash,
        public readonly string $ip,
    ) {}

    public static function from(Request $request, bool $requireGuestKey = true): self
    {
        $user = $request->user('sanctum');
        $guest = trim((string) $request->header(self::GUEST_HEADER, ''));
        $hash = null;
        if (! $user) {
            if ($guest === '' || ! preg_match('/^[A-Za-z0-9_\-:.]{8,128}$/', $guest)) {
                if ($requireGuestKey) {
                    throw new ApiException('VALIDATION_ERROR', __('ai.errors.guest_key_required'), 422, ['header' => self::GUEST_HEADER]);
                }
            } else {
                $hash = hash('sha256', config('app.key').'|ai-guest|'.$guest);
            }
        }

        return new self($user instanceof User ? $user : null, $hash, (string) $request->ip());
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }
}
