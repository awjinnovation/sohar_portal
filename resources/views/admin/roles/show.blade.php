@extends('layouts.admin')

@section('title', 'عرض الدور')
@section('page-title', 'تفاصيل الدور')
@section('page-subtitle', $role->name)
@php
    $pageIcon = 'shield-lock';
    $breadcrumbs = ['المنظمة' => null, 'الأدوار والصلاحيات' => route('admin.roles.index'), $role->name => null];
    $isProtected = in_array($role->name, ['super-admin', 'admin'], true);
    $isSuperActor = auth()->user()->isSuperAdmin();
    $groupedPermissions = $role->permissions->groupBy(function ($permission) {
        $parts = explode(' ', $permission->name);

        return end($parts);
    });
@endphp
@section('page-actions')
    @if(! $isProtected && $isSuperActor)
        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    @endif
    <a href="{{ route('admin.roles.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card title="الصلاحيات المخصصة لهذا الدور" icon="ui-checks-grid" subtitle="مشتركة بين جميع المنظمات التي تستعمل هذا الدور">
                @if($groupedPermissions->isNotEmpty())
                    @include('admin.roles._permissions', ['permissions' => $groupedPermissions, 'readonly' => true])
                @else
                    <x-admin.empty-state icon="shield-x" title="لا توجد صلاحيات" text="لا توجد صلاحيات محددة لهذا الدور" />
                @endif
            </x-admin.card>

            <x-admin.card title="المستخدمون بهذا الدور" icon="people" subtitle="أعضاء هذه المنظمة فقط" :flush="true">
                <x-slot:actions>
                    <span class="pill pill-neutral pill-sm num">{{ $tenantUsers->count() }}</span>
                </x-slot:actions>
                @if($tenantUsers->count() > 0)
                    <div class="x-table-wrap table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('الاسم') }}</th>
                                    <th>{{ __('البريد الإلكتروني') }}</th>
                                    <th>{{ __('تاريخ التسجيل') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tenantUsers->take(10) as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                                                <span class="cell-title">{{ $user->name }}</span>
                                            </div>
                                        </td>
                                        <td><span class="ltr">{{ $user->email ?? $user->phone_number ?? '-' }}</span></td>
                                        <td class="num text-muted">{{ $user->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($tenantUsers->count() > 10)
                        <p class="text-muted small px-3 py-2 mb-0">{{ __('وغيرهم (عدد المستخدمين :count)', ['count' => $tenantUsers->count()]) }}</p>
                    @endif
                @else
                    <x-admin.empty-state icon="people" title="لا يوجد مستخدمون" text="لم يُسند هذا الدور إلى أي مستخدم في هذه المنظمة بعد." />
                @endif
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="معلومات الدور" icon="info-circle">
                <div class="kv-grid mb-3">
                    <div class="kv">
                        <div class="k">{{ __('عدد المستخدمين') }}</div>
                        <div class="v num">{{ $tenantUsers->count() }}</div>
                    </div>
                    <div class="kv">
                        <div class="k">{{ __('عدد الصلاحيات') }}</div>
                        <div class="v num">{{ $role->permissions->count() }}</div>
                    </div>
                </div>
                <dl class="dl">
                    <dt>{{ __('اسم الدور') }}</dt><dd><span class="pill pill-brand"><span class="ltr">{{ $role->name }}</span></span></dd>
                    <dt>{{ __('النوع') }}</dt>
                    <dd>
                        @if($isProtected)
                            <x-admin.status-pill variant="neutral" label="محمي من النظام" icon="lock-fill" />
                        @else
                            <x-admin.status-pill variant="info" label="{{ $role->team_id ? 'خاص بهذه المنظمة' : 'مشترك بين المنظمات' }}" />
                        @endif
                    </dd>
                    <dt>{{ __('تاريخ الإنشاء') }}</dt><dd class="num">{{ $role->created_at->format('Y-m-d') }}</dd>
                </dl>
                @if(! $isProtected && $isSuperActor && $tenantUsers->count() == 0)
                    <x-slot:footer>
                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" data-confirm="{{ __('سيُحذف الدور «:name» نهائيًا من جميع المنظمات.', ['name' => $role->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> {{ __('حذف الدور') }}</button>
                        </form>
                    </x-slot:footer>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
