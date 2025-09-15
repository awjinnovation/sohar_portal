<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function index()
    {
        $contacts = EmergencyContact::where('is_active', true)
            ->orderBy('priority')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }
}
