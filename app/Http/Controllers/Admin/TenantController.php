<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use App\Models\AuditLog;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tenants (platform super-admins) and the current tenant's branding &
 * settings (tenant admins with `manage settings`).
 *
 * The form posts settings as `s[<dotted.setting.key>]`; only values that
 * differ from config('tenancy.defaults') are stored in $tenant->settings
 * (overrides), keys the form does not manage are left untouched.
 */
class TenantController extends Controller
{
    public const OPTIONAL_COLORS = ['tertiary', 'info'];

    public const GATEWAY_FIELDS = ['bank_muscat' => ['merchant_id', 'access_code', 'working_key', 'gateway_url']];

    public const ADVANCED_THEME_KEYS = ['header_style', 'header_gradient', 'header_image_url', 'card_pattern_url', 'font_family_numeric'];

    public const HEADER_STYLES = ['gradient', 'solid', 'image'];

    public const HOME_BLOCKS = ['wallet_card', 'quick_actions', 'announcements', 'featured_events', 'happening_today',
        'points_progress', 'vendors', 'map_teaser', 'categories'];

    public const QUICK_ACTIONS = ['topup', 'pay', 'points', 'tickets', 'map', 'refund', 'deposits', 'calendar', 'vendors'];

    private const HEX = 'regex:/^#[0-9A-Fa-f]{6}$/';

    private const FONT = 'regex:/^[\pL\pN \-]+$/u';

    public function __construct(private TenantContext $context)
    {
    }

    // ------------------------------------------------------------------
    // Platform: tenants CRUD (super-admin, `manage tenants`)
    // ------------------------------------------------------------------

    public function index()
    {
        $tenants = Tenant::orderBy('id')->paginate(20);

        return view('admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return $this->form(new Tenant(['status' => Tenant::STATUS_ACTIVE]), 'create');
    }

    public function store(Request $request)
    {
        $request->validate(array_merge(
            [
                'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
                'status' => ['required', 'in:active,inactive'],
                'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
                'admin_email' => ['nullable', 'email', 'max:255'],
                'admin_name' => ['nullable', 'string', 'max:255'],
                'admin_password' => ['nullable', 'string', 'min:8', 'max:255'],
            ],
            $this->nameRules(),
            $this->settingsRules(),
            $this->gatewayRules(),
        ));

        $adminUser = null;
        if ($email = $request->input('admin_email')) {
            $adminUser = User::where('email', $email)->first();
            if (! $adminUser && ! $request->filled('admin_password')) {
                throw ValidationException::withMessages(['admin_password' => 'كلمة المرور مطلوبة لإنشاء مستخدم جديد.']);
            }
        }

        $tenant = new Tenant();
        $tenant->slug = $request->input('slug');
        $values = $this->collectSettings($request, $tenant);

        $tenant = DB::transaction(function () use ($request, $tenant, $values, $adminUser, $email) {
            $tenant->status = $request->input('status');
            $tenant->domain = $request->input('domain');
            $tenant->name = $this->nameInput($request);
            $tenant->settings = [];
            $this->applySettings($tenant, $values);
            $this->applyGateway($request, $tenant);
            $tenant->save();

            if ($email) {
                $adminUser ??= User::create([
                    'name' => $request->input('admin_name') ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => $request->input('admin_password'),
                ]);
                $this->context->run($tenant, fn () => $adminUser->hasRole('admin') || $adminUser->assignRole('admin'));
            }

            AuditLog::record('tenant.created', $tenant, ['slug' => $tenant->slug, 'admin_user_id' => $adminUser?->id]);

            return $tenant;
        });

        return redirect()->route('admin.tenants.index')->with('success', 'تم إنشاء المنظمة بنجاح');
    }

    public function edit(Tenant $tenant)
    {
        return $this->form($tenant, 'edit');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $request->validate(array_merge(
            [
                'status' => ['required', 'in:active,inactive'],
                'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($tenant->id)],
            ],
            $this->nameRules(),
            $this->settingsRules(),
            $this->gatewayRules(),
        ));

        $values = $this->collectSettings($request, $tenant);

        $tenant->status = $request->input('status');
        $tenant->domain = $request->input('domain');
        $tenant->name = $this->nameInput($request, $tenant);
        $this->applySettings($tenant, $values);
        $this->applyGateway($request, $tenant);
        $tenant->save();

        AuditLog::record('tenant.updated', $tenant, ['keys' => array_keys($values)]);

        return redirect()->route('admin.tenants.edit', $tenant)->with('success', 'تم حفظ إعدادات المنظمة بنجاح');
    }

    /** Switch the admin panel to another tenant the user may access. */
    public function switch(Request $request)
    {
        $request->validate(['tenant_id' => ['required', 'integer']]);
        $tenantId = (int) $request->input('tenant_id');

        $allowed = collect(view()->shared('adminTenants', []))->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($tenantId, $allowed, true)) {
            return redirect()->back()->with('error', 'لا تملك صلاحية الوصول إلى هذه المنظمة');
        }

        $request->session()->put(AdminMiddleware::SESSION_KEY, $tenantId);
        $tenant = Tenant::find($tenantId);

        return redirect()->route('admin.dashboard')
            ->with('success', 'تم التبديل إلى '.($tenant?->localizedName('ar') ?? ''));
    }

    // ------------------------------------------------------------------
    // Current tenant branding & settings (`manage settings`)
    // ------------------------------------------------------------------

    public function editCurrent()
    {
        return $this->form($this->currentTenant(), 'current');
    }

    public function updateCurrent(Request $request)
    {
        $tenant = $this->currentTenant();
        $request->validate(array_merge($this->nameRules(), $this->settingsRules()));

        $values = $this->collectSettings($request, $tenant);
        $tenant->name = $this->nameInput($request, $tenant);
        $this->applySettings($tenant, $values);
        $tenant->save();

        AuditLog::record('tenant.branding_updated', $tenant, ['keys' => array_keys($values)]);

        return redirect()->route('admin.branding.edit')->with('success', 'تم حفظ الهوية والإعدادات بنجاح');
    }

    // ------------------------------------------------------------------
    // Form helpers
    // ------------------------------------------------------------------

    private function currentTenant(): Tenant
    {
        $tenant = $this->context->get();
        abort_unless($tenant, 404);

        return $tenant;
    }

    /**
     * @param  'create'|'edit'|'current'  $mode
     */
    private function form(Tenant $tenant, string $mode)
    {
        [$action, $method] = match ($mode) {
            'create' => [route('admin.tenants.store'), 'POST'],
            'edit' => [route('admin.tenants.update', $tenant), 'PUT'],
            'current' => [route('admin.branding.update'), 'PUT'],
        };

        return view('admin.tenants.form', [
            'tenant' => $tenant,
            'mode' => $mode,
            'action' => $action,
            'method' => $method,
            'platform' => $mode !== 'current',
            'colorKeys' => array_merge(array_keys(config('tenancy.defaults.theme.colors', [])), self::OPTIONAL_COLORS),
            'moduleKeys' => array_keys(config('tenancy.defaults.modules', [])),
            'socialKeys' => array_keys(config('tenancy.defaults.tenant.social', [])),
            'refundMethods' => RefundRequest::METHODS,
            'gatewayFields' => self::GATEWAY_FIELDS,
            'advancedJson' => old('advanced', $this->advancedDocument($tenant)),
        ]);
    }

    /** Current §5a presentation keys as pretty JSON (textarea prefill). */
    private function advancedDocument(Tenant $tenant): string
    {
        $theme = [];
        foreach (self::ADVANCED_THEME_KEYS as $key) {
            $theme[$key] = $tenant->setting("theme.$key");
        }

        return json_encode([
            'theme' => $theme,
            'home_blocks' => $tenant->setting('home_blocks', []),
            'quick_actions' => $tenant->setting('quick_actions', []),
            'merchant' => (array) $tenant->setting('merchant', []),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function nameRules(): array
    {
        return [
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
        ];
    }

    private function nameInput(Request $request, ?Tenant $tenant = null): array
    {
        return array_merge($tenant?->name ?? [], [
            'en' => trim((string) $request->input('name.en')),
            'ar' => trim((string) $request->input('name.ar')),
        ]);
    }

    private function gatewayRules(): array
    {
        $rules = [];
        foreach (self::GATEWAY_FIELDS as $provider => $fields) {
            foreach ($fields as $field) {
                $rules["gateway.$provider.$field"] = ['nullable', 'string', 'max:1000'];
            }
        }

        return $rules;
    }

    /** Write-only credentials: blank inputs keep the stored value. */
    private function applyGateway(Request $request, Tenant $tenant): void
    {
        $credentials = $tenant->gateway_credentials ?? [];
        $changed = false;
        foreach (self::GATEWAY_FIELDS as $provider => $fields) {
            foreach ($fields as $field) {
                $value = $request->input("gateway.$provider.$field");
                if ($value !== null && trim((string) $value) !== '') {
                    $credentials[$provider][$field] = trim((string) $value);
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $tenant->gateway_credentials = $credentials;
        }
    }

    /**
     * Managed settings: dotted key => [cast type, validation rules].
     *
     * @return array<string, array{0:string, 1:string}>
     */
    private function fields(): array
    {
        $f = [];
        $text = 'nullable|string|max:255';
        $url = 'nullable|string|max:500';

        foreach (['en', 'ar'] as $l) {
            $f["tenant.tagline.$l"] = ['string', $text];
        }
        $f['tenant.default_locale'] = ['string', 'required|in:ar,en'];
        $f['tenant.locales'] = ['csv', 'required|string|max:100'];
        $f['tenant.currency'] = ['upper', 'required|string|size:3|alpha'];
        $f['tenant.currency_decimals'] = ['int', 'required|integer|between:0,4'];
        $f['tenant.timezone'] = ['string', 'required|timezone'];
        $f['tenant.support.email'] = ['string', 'nullable|email|max:255'];
        foreach (['phone', 'whatsapp', 'website'] as $k) {
            $f["tenant.support.$k"] = ['string', $text];
        }
        foreach (array_keys(config('tenancy.defaults.tenant.social', [])) as $k) {
            $f["tenant.social.$k"] = ['string', $text];
        }
        foreach (['terms_url', 'privacy_url'] as $k) {
            $f["tenant.legal.$k"] = ['string', $url];
        }
        foreach (['en', 'ar'] as $l) {
            $f["tenant.organization.$l"] = ['string', $text];
        }

        foreach (['logo_url', 'logo_on_primary_url', 'icon_url', 'splash_url'] as $k) {
            $f["branding.$k"] = ['string', $url];
        }
        $f['branding.logo_needs_chip'] = ['bool_nullable', 'nullable|in:0,1,true,false'];

        foreach (array_keys(config('tenancy.defaults.theme.colors', [])) as $k) {
            $f["theme.colors.$k"] = ['color', 'required|'.self::HEX];
        }
        foreach (self::OPTIONAL_COLORS as $k) {
            $f["theme.colors.$k"] = ['color_optional', 'nullable|'.self::HEX];
        }
        $f['theme.font_family_ar'] = ['string', 'required|string|max:100|'.self::FONT];
        $f['theme.font_family_en'] = ['string', 'required|string|max:100|'.self::FONT];
        $f['theme.corner_radius'] = ['int', 'required|integer|between:0,64'];
        $f['theme.mode'] = ['string', 'required|in:system,light,dark'];
        $f['theme.density'] = ['string', 'required|in:comfortable,compact'];

        foreach (array_keys(config('tenancy.defaults.modules', [])) as $k) {
            $f["modules.$k"] = ['bool', 'boolean'];
        }
        $f['navigation'] = ['csv', 'required|string|max:255'];

        $f['map.center.lat'] = ['number', 'required|numeric|between:-90,90'];
        $f['map.center.lng'] = ['number', 'required|numeric|between:-180,180'];
        $f['map.zoom'] = ['number', 'required|numeric|between:1,22'];
        foreach (['en', 'ar'] as $l) {
            $f["map.venue.$l"] = ['string', $text];
        }

        $f['wallet.topup_presets_minor'] = ['csv_int', 'nullable|string|max:255|regex:/^[\d\s,]*$/'];
        foreach (['min_topup_minor', 'max_topup_minor', 'max_balance_minor'] as $k) {
            $f["wallet.$k"] = ['int', 'required|integer|min:0'];
        }
        $f['wallet.promo_expiry_days'] = ['int_nullable', 'nullable|integer|min:1|max:3650'];
        $f['wallet.topup_bonus'] = ['json', 'nullable|json'];
        $f['wallet.refunds.enabled'] = ['bool', 'boolean'];
        foreach (['window_days', 'fee_minor', 'min_minor'] as $k) {
            $f["wallet.refunds.$k"] = ['int', 'required|integer|min:0'];
        }
        $f['wallet.refunds.methods'] = ['list', 'nullable|array'];

        foreach (['en', 'ar'] as $l) {
            $f["points.label.$l"] = ['string', 'nullable|string|max:50'];
        }
        $f['points.earn_per_currency_unit'] = ['number', 'required|numeric|min:0'];
        foreach (['redeem_value_minor_per_point', 'min_redeem_points', 'expiry_days', 'checkin_points'] as $k) {
            $f["points.$k"] = ['int', 'required|integer|min:0'];
        }
        $f['points.earn_on_checkin'] = ['bool', 'boolean'];
        $f['points.tiers'] = ['json', 'nullable|json'];

        $f['app.min_version'] = ['string', 'required|string|max:20|regex:/^\d+(\.\d+){0,3}$/'];
        $f['app.latest_version'] = ['string', 'required|string|max:20|regex:/^\d+(\.\d+){0,3}$/'];
        $f['app.force_update'] = ['bool', 'boolean'];
        $f['app.maintenance_mode'] = ['bool', 'boolean'];
        foreach (['en', 'ar'] as $l) {
            $f["app.maintenance_message.$l"] = ['string', 'nullable|string|max:500'];
        }
        foreach (['ios', 'android'] as $k) {
            $f["app.store_urls.$k"] = ['string', $url];
        }

        foreach (['address', 'city', 'state', 'zip', 'country'] as $k) {
            $f["payments.billing.$k"] = ['string', $text];
        }
        foreach (['en', 'ar'] as $l) {
            $f["messages.otp_sms.$l"] = ['string', 'nullable|string|max:500'];
        }

        return $f;
    }

    private function settingsRules(): array
    {
        $rules = [
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'advanced' => ['nullable', 'string', 'max:20000'],
            's.wallet.refunds.methods.*' => ['nullable', Rule::in(RefundRequest::METHODS)],
        ];
        foreach ($this->fields() as $key => [, $rule]) {
            $rules["s.$key"] = 'sometimes|'.$rule;
        }

        return $rules;
    }

    /**
     * Cast the posted settings (only keys present in the request), run the
     * structural checks and handle the logo upload + advanced JSON.
     *
     * @return array<string, mixed> dotted key => value
     */
    private function collectSettings(Request $request, Tenant $tenant): array
    {
        $values = [];
        foreach ($this->fields() as $key => [$type]) {
            if ($request->has("s.$key")) {
                $values[$key] = $this->cast($type, $request->input("s.$key"));
            }
        }

        $errors = $this->checkStructure($values);
        foreach ($this->advancedValues($request->input('advanced'), $errors) as $key => $value) {
            $values[$key] = $value;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->hasFile('logo')) {
            $old = (string) $tenant->setting('branding.logo_url');
            $values['branding.logo_url'] = $request->file('logo')->store("tenants/{$tenant->slug}", 'public');
            if ($old !== '' && str_starts_with($old, "tenants/{$tenant->slug}/") && $old !== $values['branding.logo_url']) {
                Storage::disk('public')->delete($old);
            }
        }

        return $values;
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'string' => trim((string) ($value ?? '')),
            'upper' => strtoupper(trim((string) $value)),
            'int' => (int) $value,
            'number' => is_numeric($value) && ! str_contains((string) $value, '.') ? (int) $value : (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'bool_nullable' => ($value === null || $value === '') ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int_nullable' => ($value === null || $value === '') ? null : (int) $value,
            'color' => strtoupper((string) $value),
            'color_optional' => $value ? strtoupper((string) $value) : null,
            'csv' => array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen')),
            'csv_int' => array_map('intval', array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'))),
            'list' => array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== '')),
            'json' => ($value === null || trim((string) $value) === '') ? [] : json_decode((string) $value, true),
            default => $value,
        };
    }

    /** Structural checks the plain validation rules cannot express. */
    private function checkStructure(array $values): array
    {
        $errors = [];

        if (isset($values['tenant.locales'])) {
            foreach ($values['tenant.locales'] as $locale) {
                if (! preg_match('/^[a-z]{2}$/', $locale)) {
                    $errors['s.tenant.locales'] = 'اللغات يجب أن تكون رموزًا من حرفين مفصولة بفواصل (مثال: ar,en).';
                }
            }
            $default = $values['tenant.default_locale'] ?? null;
            if ($default && ! in_array($default, $values['tenant.locales'], true)) {
                $errors['s.tenant.default_locale'] = 'اللغة الافتراضية يجب أن تكون ضمن اللغات المتاحة.';
            }
        }

        if (isset($values['navigation'])) {
            foreach ($values['navigation'] as $item) {
                if (! preg_match('/^[a-z_]+$/', $item)) {
                    $errors['s.navigation'] = 'عناصر التنقل يجب أن تكون كلمات إنجليزية صغيرة مفصولة بفواصل.';
                }
            }
        }

        if (array_key_exists('wallet.topup_bonus', $values)) {
            $bonus = $values['wallet.topup_bonus'];
            $valid = is_array($bonus) && array_is_list($bonus);
            foreach ($valid ? $bonus : [] as $row) {
                if (! is_array($row) || ! is_int($row['min_minor'] ?? null) || $row['min_minor'] < 0
                    || ! is_numeric($row['bonus_percent'] ?? null) || $row['bonus_percent'] < 0 || $row['bonus_percent'] > 100) {
                    $valid = false;
                }
            }
            if (! $valid) {
                $errors['s.wallet.topup_bonus'] = 'مكافأة الشحن يجب أن تكون قائمة JSON بالشكل [{"min_minor": 10000, "bonus_percent": 5}].';
            }
        }

        if (array_key_exists('points.tiers', $values)) {
            $tiers = $values['points.tiers'];
            $valid = is_array($tiers) && array_is_list($tiers);
            foreach ($valid ? $tiers : [] as $tier) {
                if (! is_array($tier) || ! is_string($tier['code'] ?? null) || ! is_int($tier['min_points'] ?? null)
                    || (isset($tier['multiplier']) && ! is_numeric($tier['multiplier']))
                    || (isset($tier['name']) && ! is_array($tier['name']))
                    || (isset($tier['color']) && ! preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $tier['color']))) {
                    $valid = false;
                }
            }
            if (! $valid) {
                $errors['s.points.tiers'] = 'المستويات يجب أن تكون قائمة JSON، لكل مستوى code و min_points (و name/multiplier/color اختياريًا).';
            }
        }

        foreach (['min_topup_minor', 'max_topup_minor'] as $k) {
            if (isset($values["wallet.$k"]) && $values["wallet.$k"] < 0) {
                $errors["s.wallet.$k"] = 'القيمة يجب ألا تكون سالبة.';
            }
        }
        if (isset($values['wallet.min_topup_minor'], $values['wallet.max_topup_minor'])
            && $values['wallet.max_topup_minor'] < $values['wallet.min_topup_minor']) {
            $errors['s.wallet.max_topup_minor'] = 'الحد الأعلى للشحن يجب أن يكون أكبر من أو يساوي الحد الأدنى.';
        }

        return $errors;
    }

    /**
     * Parse the "advanced" JSON (§5a presentation keys).
     *
     * @return array<string, mixed>
     */
    private function advancedValues(?string $json, array &$errors): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            $errors['advanced'] = 'الإعدادات المتقدمة يجب أن تكون كائن JSON صالحًا.';

            return [];
        }

        $unknown = array_diff(array_keys($data), ['theme', 'home_blocks', 'quick_actions', 'merchant']);
        if ($unknown) {
            $errors['advanced'] = 'مفاتيح غير مدعومة في الإعدادات المتقدمة: '.implode(', ', $unknown);

            return [];
        }

        $out = [];
        $fail = function (string $message) use (&$errors) {
            $errors['advanced'] = $message;
        };

        if (array_key_exists('theme', $data)) {
            $theme = $data['theme'];
            if (! is_array($theme) || ($theme !== [] && array_is_list($theme))) {
                $fail('theme يجب أن يكون كائنًا.');
            } else {
                $bad = array_diff(array_keys($theme), self::ADVANCED_THEME_KEYS);
                if ($bad) {
                    $fail('مفاتيح theme المسموحة: '.implode(', ', self::ADVANCED_THEME_KEYS));
                }
                foreach (array_intersect_key($theme, array_flip(self::ADVANCED_THEME_KEYS)) as $key => $value) {
                    $ok = match ($key) {
                        'header_style' => in_array($value, self::HEADER_STYLES, true),
                        'header_gradient' => is_array($value) && array_is_list($value)
                            && collect($value)->every(fn ($c) => is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c)),
                        'header_image_url', 'card_pattern_url' => $value === null || (is_string($value) && strlen($value) <= 500),
                        'font_family_numeric' => is_string($value) && preg_match('/^[\pL\pN \-]{1,100}$/u', $value),
                    };
                    if (! $ok) {
                        $fail("قيمة غير صالحة للمفتاح theme.$key");
                    } else {
                        $out["theme.$key"] = $value === '' ? null : $value;
                    }
                }
            }
        }

        foreach (['home_blocks' => self::HOME_BLOCKS, 'quick_actions' => self::QUICK_ACTIONS] as $key => $allowed) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $list = $data[$key];
            if (! is_array($list) || ! array_is_list($list) || array_diff($list, $allowed)) {
                $fail("$key يجب أن تكون قائمة من: ".implode(', ', $allowed));
            } else {
                $out[$key] = array_values(array_unique($list));
            }
        }

        if (array_key_exists('merchant', $data)) {
            $merchant = $data['merchant'];
            $threshold = is_array($merchant) ? ($merchant['confirm_threshold_minor'] ?? null) : null;
            if (! is_array($merchant) || array_diff(array_keys($merchant), ['confirm_threshold_minor'])
                || (array_key_exists('confirm_threshold_minor', $merchant) && (! is_int($threshold) || $threshold < 0))) {
                $fail('merchant يقبل فقط confirm_threshold_minor (عدد صحيح موجب).');
            } elseif (array_key_exists('confirm_threshold_minor', $merchant)) {
                $out['merchant.confirm_threshold_minor'] = $threshold;
            }
        }

        return $out;
    }

    /**
     * Store values in the tenant overrides; values equal to the platform
     * default are removed so later default changes still apply.
     */
    private function applySettings(Tenant $tenant, array $values): void
    {
        $defaults = config('tenancy.defaults', []);
        $settings = $tenant->settings ?? [];
        $missing = new \stdClass();

        foreach ($values as $key => $value) {
            $default = data_get($defaults, $key, $missing);
            // null defaults (optional keys) compare strictly so `false` / 0 are kept.
            $isDefault = ($default === $missing || $default === null) ? ($value === null || $value === '') : $default == $value;

            if ($isDefault) {
                $this->forget($settings, $key);
            } else {
                data_set($settings, $key, $value);
            }
        }

        $tenant->settings = $settings;
    }

    /** Forget a dotted key and prune the parents it leaves empty. */
    private function forget(array &$settings, string $key): void
    {
        if (! Arr::has($settings, $key)) {
            return;
        }
        Arr::forget($settings, $key);

        $parts = explode('.', $key);
        array_pop($parts);
        while ($parts) {
            $parent = implode('.', $parts);
            if (data_get($settings, $parent) !== []) {
                break;
            }
            Arr::forget($settings, $parent);
            array_pop($parts);
        }
    }
}
