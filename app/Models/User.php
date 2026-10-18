<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'otp_secret',
        'phone_verified_at',
        'device_token',
        'preferred_language'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_secret'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
    ];

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Wallet in the current (or given) tenant; null if never created. */
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'vendor_user')->withPivot('role')->withTimestamps();
    }

    /**
     * Tenants in which this user holds at least one role (admin panel access).
     */
    public function tenantIdsWithRoles(): array
    {
        return \Illuminate\Support\Facades\DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', static::class)
            ->where(config('permission.column_names.model_morph_key'), $this->id)
            ->distinct()
            ->pluck(config('permission.column_names.team_foreign_key'))
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Tenants where the user holds a role granting admin-panel access. */
    public function panelTenantIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', static::class)
            ->where('model_has_roles.model_id', $this->id)
            ->whereNotIn('roles.name', \App\Http\Middleware\AdminMiddleware::NON_PANEL_ROLES)
            ->distinct()
            ->orderBy('model_has_roles.team_id')
            ->pluck('model_has_roles.team_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Display name safe to show to vendor staff (first name + last initial). */
    public function maskedDisplayName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name)) ?: [];
        if (count($parts) <= 1) {
            return $this->name ?: ('#'.$this->id);
        }

        return $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }

    public function interests()
    {
        return $this->hasMany(UserInterest::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }
}