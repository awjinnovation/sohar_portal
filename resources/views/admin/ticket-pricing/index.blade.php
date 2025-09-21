@extends('layouts.admin')

@section('title', 'أسعار التذاكر')
@section('page-title', 'أسعار التذاكر')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">أسعار التذاكر</h1>
        <a href="{{ route('admin.ticket-pricing.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> إضافة سعر جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">الفعالية</th>
                            <th width="20%">نوع التذكرة</th>
                            <th width="15%">السعر</th>
                            <th width="15%">الكمية المتاحة</th>
                            <th width="20%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pricings as $pricing)
                            <tr>
                                <td>{{ $pricing->id }}</td>
                                <td>{{ $pricing->event->name ?? 'غير محدد' }}</td>
                                <td>{{ $pricing->ticket_type }}</td>
                                <td>{{ number_format($pricing->price, 3) }} ر.ع</td>
                                <td>{{ $pricing->available_quantity }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.ticket-pricing.show', $pricing) }}"
                                           class="btn btn-sm btn-info" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.ticket-pricing.edit', $pricing) }}"
                                           class="btn btn-sm btn-warning" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.ticket-pricing.destroy', $pricing) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذا السعر؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">لا توجد أسعار مسجلة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pricings->links() }}
        </div>
    </div>
</div>
@endsection