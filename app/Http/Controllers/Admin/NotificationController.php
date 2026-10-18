<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * In-app notifications of the current tenant: either addressed to one user
 * (a user known to this tenant) or a public broadcast (is_public).
 */
class NotificationController extends Controller
{
    use TenantValidation;

    public function index()
    {
        $notifications = Notification::latest()->paginate(10);
        // Notification has no user() relation; resolve recipients in one query.
        $recipients = User::whereIn('id', $notifications->pluck('user_id')->filter()->unique())->get()->keyBy('id');

        return view('admin.notifications.index', compact('notifications', 'recipients'));
    }

    public function create()
    {
        $users = $this->recipientsQuery()->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'phone_number']);

        return view('admin.notifications.create', compact('users'));
    }

    public function store(Request $request)
    {
        Notification::create($this->validated($request));

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم إضافة الإشعار بنجاح');
    }

    public function show(Notification $notification)
    {
        $recipient = $notification->user_id ? User::find($notification->user_id) : null;

        return view('admin.notifications.show', compact('notification', 'recipient'));
    }

    public function edit(Notification $notification)
    {
        $users = $this->recipientsQuery()->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'phone_number']);

        return view('admin.notifications.edit', compact('notification', 'users'));
    }

    public function update(Request $request, Notification $notification)
    {
        $notification->update($this->validated($request));

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم تحديث الإشعار بنجاح');
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم حذف الإشعار بنجاح');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! $this->recipientsQuery()->whereKey($value)->exists()) {
                    $fail('المستخدم المحدد غير موجود في هذه الجهة.');
                }
            }],
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'body' => 'required|string',
            'body_ar' => 'required|string',
            'type' => 'nullable|string|max:50',
            'is_read' => 'boolean',
            'is_public' => 'boolean',
        ]);

        $validated['type'] = $validated['type'] ?? 'general';
        $validated['is_read'] = $request->boolean('is_read');
        // No recipient = broadcast to every app user of the tenant.
        $validated['is_public'] = empty($validated['user_id']) ? true : $request->boolean('is_public');

        return $validated;
    }

    /**
     * Users known to the current tenant (role, ticket or wallet in it).
     * Users are global identities, so the list must not expose other
     * tenants' customers.
     */
    private function recipientsQuery()
    {
        $tenantId = $this->currentTenantId();

        return User::query()->where(function ($q) use ($tenantId) {
            $q->whereHas('tickets')
                ->orWhereHas('wallets')
                ->orWhereIn('id', DB::table(config('permission.table_names.model_has_roles'))
                    ->where('model_type', User::class)
                    ->where(config('permission.column_names.team_foreign_key'), $tenantId)
                    ->select('model_id'));
        });
    }
}
