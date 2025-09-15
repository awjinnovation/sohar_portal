<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::first();

        if (!$settings) {
            // Return default settings
            $settings = [
                'app_name' => 'Sohar Festival',
                'app_name_ar' => 'مهرجان صحار',
                'app_version' => '1.0.0',
                'maintenance_mode' => false,
                'contact_email' => 'info@soharfestival.om',
                'contact_phone' => '+968 1234 5678',
                'social_media' => [
                    'facebook' => 'https://facebook.com/soharfestival',
                    'instagram' => 'https://instagram.com/soharfestival',
                    'twitter' => 'https://twitter.com/soharfestival'
                ],
                'terms_url' => 'https://soharfestival.om/terms',
                'privacy_url' => 'https://soharfestival.om/privacy',
                'about_url' => 'https://soharfestival.om/about'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }
}