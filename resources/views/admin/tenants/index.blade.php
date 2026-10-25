@extends('layouts.admin')

@section('title', 'المنظمات')
@section('page-title', 'إدارة المنظمات')
@section('page-subtitle', 'لكل منظمة هويتها وإعداداتها ووحداتها')
@php
    $pageIcon = 'buildings';
    $breadcrumbs = ['المنصة' => null, 'المنظمات' => null];
    $activeTenants = \App\Models\Tenant::where('status', \App\Models\Tenant::STATUS_ACTIVE)->count();
    $allTenants = \App\Models\Tenant::count();
@endphp
@section('page-actions')
    <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('إضافة منظمة') }}
    </a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <div class="stat-grid">
        <x-admin.stat-tile label="جميع المنظمات" :value="$allTenants" icon="buildings" tone="brand" />
        <x-admin.stat-tile label="نشطة" :value="$activeTenants" icon="check-circle" tone="success" />
        <x-admin.stat-tile label="غير نشطة" :value="$allTenants - $activeTenants" icon="pause-circle" tone="neutral" />
    </div>

    @if($tenants->isEmpty())
        <x-admin.card>
            <x-admin.empty-state icon="buildings" title="لا توجد منظمات" text="أضف أول منظمة لتبدأ تخصيص هويتها ووحداتها.">
                <a href="{{ route('admin.tenants.create') }}" class="btn btn-tonal"><i class="bi bi-plus-lg"></i> {{ __('إضافة منظمة') }}</a>
            </x-admin.empty-state>
        </x-admin.card>
    @else
        <div class="row g-3">
            @foreach($tenants as $t)
                @php
                    $tTheme = \App\View\TenantTheme::for($t);
                    $tLogo = $tTheme['logo'] ?? $tTheme['icon'] ?? null;
                    $tModules = collect((array) $t->setting('modules', []));
                    $tModulesOn = $tModules->filter()->count();
                    $isCurrent = isset($currentTenant) && $currentTenant?->id === $t->id;
                    $tName = $t->name['ar'] ?? $t->localizedName('ar');
                    $tVars = '--header-start: '.$tTheme['shared']['header-start'].'; --header-end: '.$tTheme['shared']['header-end'].'; --on-header: '.$tTheme['shared']['on-header'].';';
                @endphp
                <div class="col-md-6 col-xl-4">
                    <section class="x-card h-100 mb-0 d-flex flex-column overflow-hidden" style="{{ $tVars }}">
                        <div class="d-flex align-items-center gap-3 p-3"
                             style="background: linear-gradient(225deg, var(--header-start), var(--header-end)); color: var(--on-header); min-height: 92px">
                            <span class="tenant-mark" style="width: 56px; height: 56px">
                                @if($tLogo)
                                    <img src="{{ $tLogo }}" alt="">
                                @else
                                    <span class="initial">{{ mb_substr($tName ?: $t->slug, 0, 1) }}</span>
                                @endif
                            </span>
                            <div class="flex-grow-1" style="min-width: 0">
                                <div class="fw-bold fs-6 text-truncate">{{ $tName ?: $t->slug }}</div>
                                @if(! empty($t->name['en']))
                                    <div class="small text-truncate" style="opacity: .85"><span class="ltr">{{ $t->name['en'] }}</span></div>
                                @endif
                            </div>
                            <span class="small num" style="opacity: .75">#{{ $t->id }}</span>
                        </div>

                        <div class="x-card-body flex-grow-1" style="padding-block: 8px">
                            <dl class="dl">
                                <dt>{{ __('معرّف الرابط') }}</dt><dd><code class="ltr">{{ $t->slug }}</code></dd>
                                <dt>{{ __('الحالة') }}</dt>
                                <dd class="d-flex flex-wrap gap-1">
                                    <x-admin.status-pill domain="tenant" :status="$t->status" size="sm" />
                                    @if($isCurrent)
                                        <span class="pill pill-info pill-sm"><i class="bi bi-check2"></i>{{ __('الحالية') }}</span>
                                    @endif
                                </dd>
                                <dt>{{ __('النطاق') }}</dt><dd>@if($t->domain)<span class="ltr">{{ $t->domain }}</span>@else<span class="text-muted">—</span>@endif</dd>
                                <dt>{{ __('الوحدات') }}</dt>
                                <dd><span class="num fw-semibold">{{ $tModulesOn }}</span> <span class="text-muted">{{ __('من :count مفعّلة', ['count' => $tModules->count()]) }}</span></dd>
                                <dt>{{ __('الألوان') }}</dt>
                                <dd class="d-flex align-items-center gap-1">
                                    @foreach([$tTheme['light']['brand'], $tTheme['light']['accent'], $tTheme['shared']['header-start'], $tTheme['shared']['header-end']] as $sw)
                                        <span class="d-inline-block rounded-circle border" style="width: 20px; height: 20px; background: {{ $sw }}" title="{{ $sw }}"></span>
                                    @endforeach
                                </dd>
                            </dl>
                        </div>

                        <footer class="x-card-foot d-flex gap-2">
                            <a href="{{ route('admin.tenants.edit', $t) }}" class="btn btn-tonal btn-sm flex-grow-1"><i class="bi bi-pencil"></i> {{ __('تعديل الهوية والإعدادات') }}</a>
                            @unless($isCurrent)
                                <form action="{{ route('admin.tenant.switch') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="tenant_id" value="{{ $t->id }}">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="{{ __('التبديل إليها') }}"><i class="bi bi-arrow-left-right"></i> {{ __('تبديل') }}</button>
                                </form>
                            @endunless
                        </footer>
                    </section>
                </div>
            @endforeach
        </div>

        @if($tenants->hasPages())
            <div class="mt-4">{{ $tenants->links() }}</div>
        @endif
    @endif
@endsection
