{{-- Page header on the brand band: breadcrumbs, title, optional subtitle/icon, actions slot.
     Rendered by layouts/admin from @section('page-title'|'page-subtitle'|'page-actions') and $breadcrumbs = ['Label' => url|null]. --}}
@props(['title' => '', 'subtitle' => null, 'icon' => null, 'breadcrumbs' => []])
<div class="page-head">
    <div class="ph-text">
        @if(! empty($breadcrumbs))
            <ol class="crumbs" aria-label="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}"><i class="bi bi-house-door"></i><span class="visually-hidden">{{ __('الرئيسية') }}</span></a></li>
                @foreach($breadcrumbs as $crumbLabel => $crumbUrl)
                    <li class="sep" aria-hidden="true"><i class="bi bi-chevron-left dir-icon"></i></li>
                    <li>@if($crumbUrl && ! $loop->last)<a href="{{ $crumbUrl }}">{{ __((string) $crumbLabel) }}</a>@else<span aria-current="page">{{ __((string) $crumbLabel) }}</span>@endif</li>
                @endforeach
            </ol>
        @endif
        <h1>@if($icon)<span class="ph-icon"><i class="bi bi-{{ $icon }}"></i></span>@endif<span>{{ __((string) $title) }}</span></h1>
        @if($subtitle)<p class="ph-sub">{{ __($subtitle) }}</p>@endif
    </div>
    @isset($actions)
        <div class="ph-actions">{{ $actions }}</div>
    @endisset
</div>
