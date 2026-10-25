<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Visitor concierge conversation. Owned by a signed-in user or by a guest
 * (`guest_key` = sha256 of the app's X-Ai-Guest header). Expires 30 days
 * after the last message (`ai:prune`).
 */
class AiConversation extends Model
{
    use \App\Tenancy\BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'guest_key', 'locale', 'title'];

    protected $hidden = ['guest_key'];

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }

    /** Owned by this identity (user wins over guest key). */
    public function ownedBy(?User $user, ?string $guestHash): bool
    {
        if ($user) {
            return (int) $this->user_id === (int) $user->id;
        }

        return $this->user_id === null && $guestHash !== null && hash_equals((string) $this->guest_key, $guestHash);
    }

    public function scopeOwnedBy($query, ?User $user, ?string $guestHash)
    {
        if ($user) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereNull('user_id')->where('guest_key', $guestHash ?? '__none__');
    }
}
