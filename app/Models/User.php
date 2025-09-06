<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'otp',
        'otp_expires_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'password' => 'hashed',
    ];

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