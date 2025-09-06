@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل رقم الطوارئ</h1>
        <div>
            <a href="{{ route('admin.emergency-contacts.edit', $emergencyContact) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.emergency-contacts.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="200">رقم الهاتف:</th>
                            <td><strong>{{ $emergencyContact->phone_number }}</strong></td>
                        </tr>
                        <tr>
                            <th>النوع:</th>
                            <td>
                                @switch($emergencyContact->type)
                                    @case('police')
                                        <span class="badge bg-primary">شرطة</span>
                                        @break
                                    @case('ambulance')
                                        <span class="badge bg-danger">إسعاف</span>
                                        @break
                                    @case('fire')
                                        <span class="badge bg-warning">إطفاء</span>
                                        @break
                                    @case('security')
                                        <span class="badge bg-info">أمن</span>
                                        @break
                                    @case('first_aid')
                                        <span class="badge bg-success">إسعافات أولية</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">أخرى</span>
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>متاح 24 ساعة:</th>
                            <td>
                                @if($emergencyContact->is_24_hours)
                                    <span class="badge bg-success">نعم</span>
                                @else
                                    <span class="badge bg-secondary">لا</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>الترتيب:</th>
                            <td>{{ $emergencyContact->display_order ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($emergencyContact->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">اسم الخدمة</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->service_name_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->service_name }}</p>
                    </div>
                    
                    @if($emergencyContact->description_ar || $emergencyContact->description)
                    <h5 class="font-weight-bold mt-4">الوصف</h5>
                    <div class="mb-3">
                        @if($emergencyContact->description_ar)
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->description_ar }}</p>
                        @endif
                        @if($emergencyContact->description)
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->description }}</p>
                        @endif
                    </div>
                    @endif
                    
                    @if($emergencyContact->location_ar || $emergencyContact->location)
                    <h5 class="font-weight-bold mt-4">الموقع</h5>
                    <div class="mb-3">
                        @if($emergencyContact->location_ar)
                        <strong>العربية:</strong>
                        <p>{{ $emergencyContact->location_ar }}</p>
                        @endif
                        @if($emergencyContact->location)
                        <strong>English:</strong>
                        <p>{{ $emergencyContact->location }}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
