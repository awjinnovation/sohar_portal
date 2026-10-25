<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['tenant_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'data', 'ip_address'];

    protected $casts = ['data' => 'array'];

    public static function record(string $action, ?Model $subject = null, array $data = []): self
    {
        return static::create([
            'tenant_id' => $subject?->tenant_id ?? app(\App\Tenancy\TenantContext::class)->id(),
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
