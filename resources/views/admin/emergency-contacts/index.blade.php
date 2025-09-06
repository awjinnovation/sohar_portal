@extends('layouts.admin')

@section('title', 'أرقام الطوارئ')
@section('page-title', 'إدارة أرقام الطوارئ')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">جميع أرقام الطوارئ</h5>
                        <a href="{{ route('admin.emergency-contacts.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i>
                            إضافة رقم جديد
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>اسم الخدمة</th>
                                    <th>النوع</th>
                                    <th>رقم الهاتف</th>
                                    <th>الموقع</th>
                                    <th>24 ساعة</th>
                                    <th>الحالة</th>
                                    <th width="150">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($contacts as $contact)
                                <tr>
                                    <td>{{ $contact->id }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $contact->service_name_ar }}</strong>
                                            <small class="text-muted d-block">{{ $contact->service_name }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @switch($contact->type)
                                            @case('police')
                                                <span class="badge bg-primary">شرطة</span>
                                                @break
                                            @case('ambulance')
                                                <span class="badge bg-danger">إسعاف</span>
                                                @break
                                            @case('fire')
                                                <span class="badge bg-warning">إطفاء</span>
                                                @break
                                            @case('first_aid')
                                                <span class="badge bg-info">إسعاف أولي</span>
                                                @break
                                            @case('security')
                                                <span class="badge bg-dark">أمن</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">أخرى</span>
                                        @endswitch
                                    </td>
                                    <td dir="ltr">{{ $contact->phone_number }}</td>
                                    <td>{{ $contact->location_ar ?? 'غير محدد' }}</td>
                                    <td>
                                        @if($contact->is_24_hours)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                        @endif
                                    </td>
                                    <td>
                                        @if($contact->is_active)
                                        <span class="badge bg-success">نشط</span>
                                        @else
                                        <span class="badge bg-secondary">غير نشط</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.emergency-contacts.show', $contact) }}" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.emergency-contacts.edit', $contact) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('admin.emergency-contacts.destroy', $contact) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox display-4"></i>
                                            <p class="mt-2">لا توجد أرقام طوارئ حتى الآن</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($contacts->hasPages())
                    <div class="mt-3">
                        {{ $contacts->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
