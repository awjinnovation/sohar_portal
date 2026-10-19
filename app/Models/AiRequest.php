<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Usage log of one OpenAI call (tokens, latency, status). Never holds
 * prompts, answers or personal data.
 */
class AiRequest extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'feature', 'model', 'input_tokens', 'output_tokens', 'cached_tokens',
        'reasoning_tokens', 'latency_ms', 'status', 'error_code', 'http_status',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cached_tokens' => 'integer',
        'reasoning_tokens' => 'integer',
        'latency_ms' => 'integer',
        'http_status' => 'integer',
    ];
}
