@extends('layouts.admin')

@section('title', 'عرض المستخدم')
@section('page-title', 'تفاصيل المستخدم')
@php
    $pageIcon = 'person';
    $breadcrumbs = ['المنظمة' => null, 'المستخدمون' => route('admin.users.index'), $user->name => null];
    $isSuperActor = auth()->user()->isSuperAdmin();
    $userPermissions = $user->getAllPermissions();
    $groupedPermissions = $userPermissions->groupBy(function ($permission) {
        $parts = explode(' ', $permission->name);

        return end($parts);
    });
@endphp
@section('page-actions')
    @can('manage users')
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('تعديل') }}</a>
    @endcan
    <a href="{{ route('admin.users.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-admin.card>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="avatar" style="width: 64px; height: 64px; font-size: 26px">{{ mb_substr($user->name, 0, 1) }}</span>
                    <div class="flex-grow-1" style="min-width: 0">
                        <h2 class="h4 fw-bold mb-1">{{ $user->name }}</h2>
                        <div class="text-muted d-flex flex-wrap gap-3">
                            @if($user->email)
                                <span><i class="bi bi-envelope"></i> <span class="ltr">{{ $user->email }}</span></span>
                            @endif
                            @if($user->phone_number)
                                <span><i class="bi bi-telephone"></i> <span class="ltr num">{{ $user->phone_number }}</span></span>
                            @endif
                            @if(! $user->email && ! $user->phone_number)
                                <span>-</span>
                            @endif
                        </div>
                    </div>
                    @if($user->isSuperAdmin())
                        <span class="pill pill-accent"><i class="bi bi-stars"></i>{{ __('مدير المنصة') }}</span>
                    @endif
                </div>
            </x-admin.card>

            <x-admin.card title="الصلاحيات" icon="ui-checks-grid" subtitle="مجموع صلاحيات أدوار المستخدم في هذه الجهة">
                @if($groupedPermissions->isNotEmpty())
                    @include('admin.roles._permissions', ['permissions' => $groupedPermissions, 'readonly' => true])
                @else
                    <x-admin.empty-state icon="shield-x" title="لا توجد صلاحيات محددة" text="عيّن دورًا للمستخدم لمنحه صلاحيات في لوحة الإدارة." />
                @endif
            </x-admin.card>
        </div>

        <div class="col-xl-4">
            <x-admin.card title="معلومات المستخدم" icon="info-circle">
                <dl class="dl">
                    <dt>{{ __('الأدوار') }}</dt>
                    <dd>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($user->roles as $role)
                                <span class="pill pill-brand pill-sm">{{ $role->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </dd>
                    <dt>{{ __('حالة الحساب') }}</dt><dd><x-admin.status-pill domain="active" :status="true" /></dd>
                    <dt>{{ __('تاريخ التسجيل') }}</dt><dd><span class="num ltr">{{ $user->created_at->format('Y-m-d H:i') }}</span></dd>
                    <dt>{{ __('آخر تحديث') }}</dt><dd><span class="num ltr">{{ $user->updated_at->format('Y-m-d H:i') }}</span></dd>
                </dl>
                @can('manage users')
                    @if($user->id !== auth()->id())
                        <x-slot:footer>
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                  data-confirm="{{ $isSuperActor ? __('سيتم حذف المستخدم «:name» نهائيًا من المنصة.', ['name' => $user->name]) : __('سيتم إزالة صلاحيات المستخدم «:name» من هذه الجهة.', ['name' => $user->name]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-{{ $isSuperActor ? 'trash' : 'person-dash' }}"></i>
                                    {{ $isSuperActor ? __('حذف المستخدم') : __('إزالة من هذه الجهة') }}
                                </button>
                            </form>
                        </x-slot:footer>
                    @endif
                @endcan
            </x-admin.card>
        </div>
    </div>
@endsection
