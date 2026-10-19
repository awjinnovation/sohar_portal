<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'conversation_id', 'role', 'content', 'cards', 'suggestions', 'tokens',
        'feedback_rating', 'feedback_comment', 'feedback_at',
    ];

    protected $casts = [
        'cards' => 'array',
        'suggestions' => 'array',
        'tokens' => 'integer',
        'feedback_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
