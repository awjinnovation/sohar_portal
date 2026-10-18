{{-- Grouped permission checklist.
     @include('admin.roles._permissions', ['permissions' => $grouped, 'selected' => [ids], 'readonly' => false])
     $permissions: collection keyed by group (last word of the permission name). Posts permissions[] = id. --}}
@php
    $selected = array_map('intval', (array) ($selected ?? []));
    $readonly = (bool) ($readonly ?? false);
    $permGroupMeta = [
        'dashboard' => [__('لوحة التحكم'), 'grid-1x2'],
        'events' => [__('الفعاليات'), 'calendar-event'],
        'tickets' => [__('التذاكر'), 'ticket-perforated'],
        'payments' => [__('المدفوعات'), 'credit-card-2-front'],
        'wallets' => [__('المحافظ'), 'wallet2'],
        'refunds' => [__('طلبات الاسترداد'), 'arrow-counterclockwise'],
        'deposits' => [__('التأمينات'), 'safe2'],
        'vendors' => [__('التجار'), 'shop-window'],
        'restaurants' => [__('المطاعم والأكشاك'), 'cup-hot'],
        'locations' => [__('مواقع الخريطة'), 'geo-alt'],
        'announcements' => [__('الإعلانات'), 'megaphone'],
        'notifications' => [__('الإشعارات'), 'bell'],
        'users' => [__('المستخدمون'), 'people'],
        'roles' => [__('الأدوار والصلاحيات'), 'shield-lock'],
        'settings' => [__('الإعدادات'), 'sliders'],
        'tenants' => [__('المنظمات'), 'buildings'],
        'points' => [__('النقاط'), 'stars'],
    ];
    $permVerbs = [
        'view' => __('عرض'), 'manage' => __('إدارة'), 'validate' => __('التحقق من'), 'refund' => __('استرداد'),
        'charge' => __('شحن'), 'create' => __('إنشاء'), 'edit' => __('تعديل'), 'delete' => __('حذف'), 'export' => __('تصدير'),
    ];
@endphp
<div class="row g-3" @unless($readonly) data-perm-matrix @endunless>
    @foreach($permissions as $group => $groupPermissions)
        @php
            [$groupLabel, $groupIcon] = $permGroupMeta[$group] ?? [ucfirst(str_replace('_', ' ', $group)), 'shield-check'];
            $groupChecked = $groupPermissions->filter(fn ($p) => in_array((int) $p->id, $selected, true))->count();
        @endphp
        @php
            $permLabels = $groupPermissions->mapWithKeys(function ($permission) use ($permVerbs, $groupLabel) {
                $verb = strtok($permission->name, ' ');

                return [$permission->id => isset($permVerbs[$verb]) ? $permVerbs[$verb].' '.$groupLabel : $permission->name];
            });
        @endphp
        <div class="col-md-6">
            @if($readonly)
                <div class="kv h-100">
                    <div class="d-flex align-items-center gap-2 mb-2 fw-bold">
                        <i class="bi bi-{{ $groupIcon }} text-primary"></i>
                        <span>{{ $groupLabel }}</span>
                        <span class="pill pill-neutral pill-sm num ms-auto">{{ $groupPermissions->count() }}</span>
                    </div>
                    @foreach($groupPermissions as $permission)
                        <div class="d-flex align-items-center gap-2 py-1 small flex-wrap">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span class="fw-semibold">{{ $permLabels[$permission->id] }}</span>
                            <code class="ms-auto ltr">{{ $permission->name }}</code>
                        </div>
                    @endforeach
                </div>
            @else
                <section class="x-card h-100 mb-0" data-perm-group="{{ $group }}">
                    <header class="x-card-head">
                        <div class="t">
                            <h2><i class="bi bi-{{ $groupIcon }}"></i>{{ $groupLabel }}</h2>
                        </div>
                        <span class="pill pill-neutral pill-sm num" title="{{ __('المحدد من صلاحيات المجموعة') }}"><span class="ltr"><span data-perm-group-count>{{ $groupChecked }}</span>/{{ $groupPermissions->count() }}</span></span>
                        <span class="switch" title="{{ __('تحديد الكل') }}">
                            <input type="checkbox" role="switch" data-perm-group-toggle aria-label="{{ __('تحديد كل صلاحيات :group', ['group' => $groupLabel]) }}"
                                   @checked($groupChecked === $groupPermissions->count())>
                            <span class="track"></span>
                        </span>
                    </header>
                    <div class="x-card-body d-grid gap-2" style="padding: 12px">
                        @foreach($groupPermissions as $permission)
                            <label class="toggle-row" for="permission_{{ $permission->id }}">
                                <span class="tr-text">
                                    <span class="tr-label d-block">{{ $permLabels[$permission->id] }}</span>
                                    <code class="ltr">{{ $permission->name }}</code>
                                </span>
                                <span class="switch">
                                    <input class="permission-check" type="checkbox" role="switch"
                                           name="permissions[]" value="{{ $permission->id }}"
                                           id="permission_{{ $permission->id }}"
                                           data-group="{{ $group }}"
                                           @checked(in_array((int) $permission->id, $selected, true))>
                                    <span class="track"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endforeach
</div>

@unless($readonly)
    @push('scripts')
    <script>
    (function () {
        const matrix = document.querySelector('[data-perm-matrix]');
        if (!matrix) return;
        const all = () => Array.from(matrix.querySelectorAll('.permission-check'));
        const selectAll = document.getElementById('selectAll');
        const totals = document.querySelectorAll('[data-perm-total]');

        function sync() {
            matrix.querySelectorAll('[data-perm-group]').forEach(function (group) {
                const boxes = Array.from(group.querySelectorAll('.permission-check'));
                const checked = boxes.filter(b => b.checked).length;
                const toggle = group.querySelector('[data-perm-group-toggle]');
                const count = group.querySelector('[data-perm-group-count]');
                if (count) count.textContent = checked;
                if (toggle) {
                    toggle.checked = boxes.length > 0 && checked === boxes.length;
                    toggle.indeterminate = checked > 0 && checked < boxes.length;
                }
            });
            const boxes = all();
            const checked = boxes.filter(b => b.checked).length;
            totals.forEach(t => { t.textContent = checked; });
            if (selectAll) {
                selectAll.checked = boxes.length > 0 && checked === boxes.length;
                selectAll.indeterminate = checked > 0 && checked < boxes.length;
            }
        }

        matrix.addEventListener('change', function (e) {
            const toggle = e.target.closest('[data-perm-group-toggle]');
            if (toggle) {
                toggle.closest('[data-perm-group]').querySelectorAll('.permission-check').forEach(b => { b.checked = toggle.checked; });
            }
            sync();
        });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                all().forEach(b => { b.checked = selectAll.checked; });
                sync();
            });
        }
        sync();
    })();
    </script>
    @endpush
@endunless
