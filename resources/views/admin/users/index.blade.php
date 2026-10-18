@extends('layouts.admin')

@section('title', 'إدارة المستخدمين')
@section('page-title', 'المستخدمون')
@section('page-subtitle', 'فريق لوحة الإدارة وأدوارهم في هذه المنظمة')
@php
    $pageIcon = 'people';
    $breadcrumbs = ['المنظمة' => null, 'المستخدمون' => null];
    $isSuperActor = auth()->user()->isSuperAdmin();
@endphp
@section('page-actions')
    @can('manage users')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> {{ __('إضافة مستخدم جديد') }}
        </a>
    @endcan
@endsection

@section('content')
    @include('admin.partials.alerts')

    <x-admin.data-table :title="($scope ?? 'tenant') === 'all' ? 'جميع مستخدمي المنصة' : 'قائمة المستخدمين'" icon="people"
        :count="$users->total()" search="search" search-placeholder="بحث بالاسم أو البريد أو الهاتف" :paginator="$users">
        @if($isSuperActor)
            <x-slot:filters>
                <select name="scope" class="form-select" aria-label="{{ __('نطاق المستخدمين') }}">
                    <option value="tenant" @selected(($scope ?? 'tenant') === 'tenant')>{{ __('مستخدمو الجهة الحالية') }}</option>
                    <option value="all" @selected(($scope ?? '') === 'all')>{{ __('جميع مستخدمي المنصة') }}</option>
                </select>
            </x-slot:filters>
        @endif

        <x-slot:head>
            <th style="width: 56px">#</th>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('البريد الإلكتروني / الهاتف') }}</th>
            <th>{{ __('الأدوار') }}</th>
            <th>{{ __('تاريخ التسجيل') }}</th>
            <th class="text-end">{{ __('الإجراءات') }}</th>
        </x-slot:head>

        @forelse($users as $user)
            <tr>
                <td class="num text-muted">{{ $user->id }}</td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                        <div style="min-width: 0">
                            <div class="cell-title text-nowrap">{{ $user->name }}</div>
                            @if($user->isSuperAdmin())
                                <span class="pill pill-accent pill-sm mt-1"><i class="bi bi-stars"></i>{{ __('مدير المنصة') }}</span>
                            @elseif($user->is(auth()->user()))
                                <div class="cell-sub">{{ __('حسابك') }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    @if($user->email)
                        <div><span class="ltr">{{ $user->email }}</span></div>
                    @endif
                    @if($user->phone_number)
                        <div class="{{ $user->email ? 'cell-sub' : '' }}"><span class="ltr num">{{ $user->phone_number }}</span></div>
                    @endif
                    @if(! $user->email && ! $user->phone_number)
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        @forelse($user->roles as $role)
                            <span class="pill pill-brand pill-sm">{{ $role->name }}</span>
                        @empty
                            <span class="text-muted small">{{ __('بدون دور') }}</span>
                        @endforelse
                    </div>
                </td>
                <td class="num text-muted">{{ $user->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    @can('manage users')
                        <div class="row-actions">
                            <a href="{{ route('admin.users.show', $user) }}" class="icon-btn" title="{{ __('عرض') }}"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="icon-btn brand" title="{{ __('تعديل') }}"><i class="bi bi-pencil"></i></a>
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                      data-confirm="{{ $isSuperActor ? __('سيتم حذف المستخدم «:name» نهائيًا من المنصة.', ['name' => $user->name]) : __('سيتم إزالة صلاحيات المستخدم «:name» من هذه الجهة.', ['name' => $user->name]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="{{ $isSuperActor ? __('حذف') : __('إزالة من الجهة') }}">
                                        <i class="bi bi-{{ $isSuperActor ? 'trash' : 'person-dash' }}"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </td>
            </tr>
        @empty
            <x-admin.empty-row :colspan="6" icon="people" title="لا يوجد مستخدمون"
                :text="request('search') ? 'لا توجد نتائج مطابقة للبحث.' : 'أضف أعضاء الفريق ليتمكنوا من الدخول إلى لوحة الإدارة.'">
                @can('manage users')
                    <a href="{{ route('admin.users.create') }}" class="btn btn-tonal"><i class="bi bi-person-plus"></i> {{ __('إضافة مستخدم') }}</a>
                @endcan
            </x-admin.empty-row>
        @endforelse
    </x-admin.data-table>
@endsection
