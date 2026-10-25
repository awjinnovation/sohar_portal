<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public const CATEGORIES = ['food' => 'مأكولات', 'beverages' => 'مشروبات', 'merchandise' => 'منتجات', 'services' => 'خدمات', 'other' => 'أخرى'];

    public function __construct(private TenantContext $context)
    {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $vendors = Vendor::withCount('staff')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name->ar', 'like', "%$q%")
                ->orWhere('name->en', 'like', "%$q%")
                ->orWhere('category', 'like', "%$q%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vendors.index', compact('vendors', 'q'));
    }

    public function create()
    {
        return view('admin.vendors.create', [
            'vendor' => new Vendor(['is_active' => true]),
            'restaurants' => $this->restaurants(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $vendor = new Vendor($this->attributes($request, $data));
        if ($request->hasFile('logo')) {
            $vendor->logo_path = $this->storeLogo($request);
        }
        $vendor->save();

        AuditLog::record('vendor.created', $vendor);

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'أُضيف التاجر');
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['staff', 'restaurant']);

        $tz = (string) $this->context->get()?->setting('tenant.timezone', config('app.timezone'));
        $todayStart = Carbon::now($tz)->startOfDay()->setTimezone(config('app.timezone'));
        $sales = WalletTransaction::where('vendor_id', $vendor->id)->where('type', 'vendor_payment')->where('direction', 'debit');

        $stats = [
            'today' => (int) (clone $sales)->where('created_at', '>=', $todayStart)->sum('amount_minor'),
            'total' => (int) (clone $sales)->sum('amount_minor'),
            'count' => (clone $sales)->count(),
            'refunds' => (int) WalletTransaction::where('vendor_id', $vendor->id)->where('type', 'refund')->sum('amount_minor'),
        ];

        $transactions = WalletTransaction::with('user')
            ->where('vendor_id', $vendor->id)
            ->latest('id')
            ->paginate(20);

        return view('admin.vendors.show', compact('vendor', 'stats', 'transactions'));
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.vendors.edit', [
            'vendor' => $vendor,
            'restaurants' => $this->restaurants(),
        ]);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $this->validated($request);

        $vendor->fill($this->attributes($request, $data, $vendor));
        if ($request->hasFile('logo')) {
            $old = $vendor->logo_path;
            $vendor->logo_path = $this->storeLogo($request);
            $this->deleteLogo($old);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($vendor->logo_path);
            $vendor->logo_path = null;
        }
        $vendor->save();

        AuditLog::record('vendor.updated', $vendor);

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'حُدِّث التاجر');
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->transactions()->exists()) {
            return redirect()->route('admin.vendors.index')
                ->with('error', 'لا يمكن حذف تاجر له معاملات مالية، ويمكنك إيقافه.');
        }

        $vendor->staff()->detach();
        $this->deleteLogo($vendor->logo_path);
        AuditLog::record('vendor.deleted', $vendor, ['name' => $vendor->name]);
        $vendor->delete();

        return redirect()->route('admin.vendors.index')->with('success', 'حُذف التاجر');
    }

    public function attachStaff(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:staff,manager'],
        ]);

        $user = $this->findUser($data['identifier']);
        if (! $user) {
            return back()->withInput()->with('error', 'لا يوجد مستخدم بهذا البريد أو رقم الهاتف');
        }

        $vendor->staff()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
        $user->hasRole('vendor-staff') || $user->assignRole('vendor-staff');

        AuditLog::record('vendor.staff_attached', $vendor, ['user_id' => $user->id, 'role' => $data['role']]);

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'أُضيف '.$user->name.' إلى موظفي التاجر');
    }

    public function detachStaff(Vendor $vendor, User $user)
    {
        $vendor->staff()->detach($user->id);

        // No longer staff of any vendor of this tenant -> drop the app role.
        if (! $user->vendors()->exists() && $user->hasRole('vendor-staff')) {
            $user->removeRole('vendor-staff');
        }

        AuditLog::record('vendor.staff_detached', $vendor, ['user_id' => $user->id]);

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'أُزيل الموظف من فريق التاجر');
    }

    private function findUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        $digits = preg_replace('/\D/', '', $identifier);
        $candidates = array_values(array_unique(array_filter([$identifier, $digits, $digits ? '+'.$digits : null])));

        return User::whereIn('phone_number', $candidates)->first();
    }

    private function restaurants()
    {
        return Restaurant::orderBy('name')->get(['id', 'name', 'name_ar']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:50'],
            'restaurant_id' => ['nullable', 'integer',
                Rule::exists('restaurants', 'id')->where('tenant_id', $this->context->id())],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(Request $request, array $data, ?Vendor $vendor = null): array
    {
        return [
            'name' => array_merge($vendor?->name ?? [], ['en' => $data['name_en'], 'ar' => $data['name_ar']]),
            'description' => array_merge($vendor?->description ?? [], [
                'en' => $data['description_en'] ?? '',
                'ar' => $data['description_ar'] ?? '',
            ]),
            'category' => $data['category'] ?? null,
            'restaurant_id' => $data['restaurant_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function storeLogo(Request $request): string
    {
        $slug = $this->context->get()?->slug ?? 'default';

        return $request->file('logo')->store("tenants/$slug/vendors", 'public');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && ! preg_match('#^(https?:)?//#', $path) && ! str_starts_with($path, '/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
