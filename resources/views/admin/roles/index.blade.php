@extends('layouts.admin')

@section('title', 'إدارة الأدوار')
@section('page-title', 'الأدوار والصلاحيات')
@section('page-subtitle', 'تحدد الأدوار ما يمكن لكل عضو في الفريق الوصول إليه')
@php
    $pageIcon = 'shield-lock';
    $breadcrumbs = ['المنظمة' => null, 'الأدوار والصلاحيات' => null];
    $isSuperActor = auth()->user()->isSuperAdmin();
    $protectedRoles = ['super-admin', 'admin'];
@endphp
@section('page-actions')
    @if($isSuperActor)
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('إضافة دور جديد') }}
        </a>
    @endif
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table title="قائمة الأدوار" icon="shield-lock" :count="$roles->total()" :paginator="$roles">
        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('اسم الدور') }}</th>
            <th>{{ __('عدد الصلاحيات') }}</th>
            <th>{{ __('المستخدمون في هذه الجهة') }}</th>
            <th>{{ __('تاريخ الإنشاء') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($roles as $role)
            @php
                $isProtected = in_array($role->name, $protectedRoles, true);
                $memberCount = (int) ($tenantUserCounts[$role->id] ?? 0);
            @endphp
            <tr>
                <td class="num text-muted">{{ $role->id }}</td>
                <td>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="{{ route('admin.roles.show', $role) }}" class="pill pill-brand text-decoration-none"><span class="ltr">{{ $role->name }}</span></a>
                        @if($isProtected)
                            <span class="pill pill-neutral pill-sm" title="{{ __('دور محمي من النظام') }}"><i class="bi bi-lock-fill"></i>{{ __('محمي') }}</span>
                        @endif
                    </div>
                </td>
                <td>
                    <span class="num fw-semibold">{{ $role->permissions->count() }}</span>
                    <span class="cell-sub">{{ __('صلاحية') }}</span>
                </td>
                <td>
                    @if($memberCount > 0)
                        <span class="d-inline-flex align-items-center gap-1"><i class="bi bi-people text-muted"></i><span class="num fw-semibold">{{ $memberCount }}</span></span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="num text-muted">{{ $role->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    <div class="row-actions">
                        <a href="{{ route('admin.roles.show', $role) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                        @if($isProtected)
                            <span class="icon-btn" title="{{ __('لا يمكن تعديل هذا الدور') }}" aria-disabled="true"><i class="bi bi-lock"></i></span>
                        @elseif($isSuperActor)
                            <a href="{{ route('admin.roles.edit', $role) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                            @if($memberCount === 0)
                                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" data-confirm="{{ __('سيتم حذف الدور «:name» نهائيًا من جميع الجهات.', ['name' => $role->name]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="{{ __('حذف') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="shield-lock" title="لا توجد أدوار" text="أنشئ دورًا وحدد صلاحياته لتعيينه للمستخدمين.">
                @if($isSuperActor)
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة دور') }}</a>
                @endif
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>

    <x-admin.card title="معلومات مهمة حول الأدوار" icon="info-circle">
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <div class="d-flex gap-2">
                    <i class="bi bi-lock text-primary"></i>
                    <div class="small">{!! __('الأدوار :first و :second محمية ولا يمكن تعديلها أو حذفها', ['first' => '<code>super-admin</code>', 'second' => '<code>admin</code>']) !!}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="d-flex gap-2">
                    <i class="bi bi-people text-primary"></i>
                    <div class="small">{{ __('لا يمكن حذف دور مرتبط بمستخدمين نشطين') }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="d-flex gap-2">
                    <i class="bi bi-ui-checks-grid text-primary"></i>
                    <div class="small">{{ __('كل دور يمكن أن يحتوي على صلاحيات متعددة') }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="d-flex gap-2">
                    <i class="bi bi-person-badge text-primary"></i>
                    <div class="small">{{ __('المستخدم يمكن أن يمتلك أكثر من دور واحد') }}</div>
                </div>
            </div>
        </div>
    </x-admin.card>
@endsection
