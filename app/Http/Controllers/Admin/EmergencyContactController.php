<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function index()
    {
        $contacts = EmergencyContact::ordered()->paginate(10);
        return view("admin.emergency-contacts.index", compact("contacts"));
    }

    public function create()
    {
        return view("admin.emergency-contacts.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "service_name" => "required|max:255",
            "service_name_ar" => "required|max:255",
            "phone_number" => "required|max:20",
            "secondary_phone" => "nullable|max:20",
            "type" => "required|in:police,ambulance,fire,first_aid,security,other",
            "location" => "nullable|max:255",
            "location_ar" => "nullable|max:255",
            "is_24_hours" => "boolean",
            "display_order" => "nullable|integer",
            "is_active" => "boolean"
        ]);

        EmergencyContact::create($validated);

        return redirect()->route("admin.emergency-contacts.index")
            ->with("success", "تم إضافة جهة الاتصال بنجاح");
    }

    public function show(EmergencyContact $emergencyContact)
    {
        return view("admin.emergency-contacts.show", compact("emergencyContact"));
    }

    public function edit(EmergencyContact $emergencyContact)
    {
        return view("admin.emergency-contacts.edit", compact("emergencyContact"));
    }

    public function update(Request $request, EmergencyContact $emergencyContact)
    {
        $validated = $request->validate([
            "service_name" => "required|max:255",
            "service_name_ar" => "required|max:255",
            "phone_number" => "required|max:20",
            "secondary_phone" => "nullable|max:20",
            "type" => "required|in:police,ambulance,fire,first_aid,security,other",
            "location" => "nullable|max:255",
            "location_ar" => "nullable|max:255",
            "is_24_hours" => "boolean",
            "display_order" => "nullable|integer",
            "is_active" => "boolean"
        ]);

        $emergencyContact->update($validated);

        return redirect()->route("admin.emergency-contacts.index")
            ->with("success", "تم تحديث جهة الاتصال بنجاح");
    }

    public function destroy(EmergencyContact $emergencyContact)
    {
        $emergencyContact->delete();
        return redirect()->route("admin.emergency-contacts.index")
            ->with("success", "تم حذف جهة الاتصال بنجاح");
    }
}