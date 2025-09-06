<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_public',
    ];
}
