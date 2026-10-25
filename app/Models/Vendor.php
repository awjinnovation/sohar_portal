<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned seller (food stall, shop...). Vendor staff charge wallets.
 */
class Vendor extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = ['name', 'description', 'logo_path', 'category', 'restaurant_id', 'is_active'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsToMany(User::class, 'vendor_user')->withPivot('role')->withTimestamps();
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = $this->name ?? [];

        return $name[$locale] ?? $name['en'] ?? $name['ar'] ?? ('#'.$this->id);
    }

    public function logoUrl(): ?string
    {
        return Tenant::assetUrl($this->logo_path);
    }
}
