<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\SetAdminLocale;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login', [
            'tenants' => Tenant::where('status', Tenant::STATUS_ACTIVE)->orderBy('id')->get(),
        ]);
    }

    /**
     * Email/password login. The tenant is chosen at login (optional
     * `tenant` slug); tenant staff are bound to tenants where they hold a
     * panel role, platform super-admins can enter (and later switch to) any.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'tenant' => 'nullable|string',
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('بيانات الدخول غير صحيحة.')])->onlyInput('email');
        }

        $user = Auth::user();
        $allowed = $user->isSuperAdmin() ? Tenant::pluck('id')->all() : $user->panelTenantIds();

        if (! $allowed) {
            Auth::logout();

            return back()->withErrors(['email' => __('ليست لديك صلاحية الدخول إلى لوحة التحكم.')])->onlyInput('email');
        }

        $chosen = ! empty($credentials['tenant']) ? Tenant::where('slug', $credentials['tenant'])->value('id') : null;
        if ($chosen && ! in_array($chosen, $allowed, true)) {
            Auth::logout();

            return back()->withErrors(['email' => __('ليست لديك صلاحية الوصول إلى هذه المنظمة.')])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put(AdminMiddleware::SESSION_KEY, $chosen ?? $allowed[0]);

        return redirect()->intended('/admin/dashboard');
    }

    /** EN/AR switch of the admin panel (kept in the session, survives login). */
    public function switchLocale(Request $request)
    {
        $data = $request->validate(['locale' => 'required|in:'.implode(',', SetAdminLocale::LOCALES)]);
        $request->session()->put(SetAdminLocale::SESSION_KEY, $data['locale']);

        return back();
    }

    public function logout(Request $request)
    {
        $locale = $request->session()->get(SetAdminLocale::SESSION_KEY);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($locale) {
            $request->session()->put(SetAdminLocale::SESSION_KEY, $locale);
        }

        return redirect('/admin/login');
    }
}
