@extends('layouts.admin')

@section('title', 'إضافة وسم جديد')
@section('page-title', 'إضافة وسم جديد')
@php
    $pageIcon = 'tags';
    $breadcrumbs = ['الفعاليات والتذاكر' => null, 'الوسوم' => route('admin.event-tags.index'), 'إضافة وسم' => null];
@endphp
@section('page-actions')
    <a href="{{ route('admin.event-tags.index') }}" class="btn"><i class="bi bi-arrow-right dir-icon"></i> {{ __('رجوع') }}</a>
@endsection

@section('content')
    @include('admin.partials.alerts')

    <form action="{{ route('admin.event-tags.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <x-admin.card title="بيانات الوسم" icon="tag" subtitle="يظهر الاسم العربي أو الإنجليزي بحسب لغة الزائر">
                    @include('admin.event-tags._form')
                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('admin.event-tags.index') }}" class="btn btn-secondary">{{ __('إلغاء') }}</a>
                            <button type="submit" class="btn btn-primary" data-busy><i class="bi bi-check-lg"></i> {{ __('حفظ') }}</button>
                        </div>
                    </x-slot:footer>
                </x-admin.card>
            </div>
            <div class="col-xl-4">
                <x-admin.card title="عن الوسوم" icon="lightbulb">
                    <p class="text-muted small mb-2">{{ __('الوسوم كلمات قصيرة تصف الفعالية، مثل عائلي ومجاني وفي الهواء الطلق، وتساعد الزوار على تصفية الفعاليات.') }}</p>
                    <p class="text-muted small mb-0">{{ __('يجب أن يكون اسم الوسم الإنجليزي فريدًا لكل فعالية.') }}</p>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
