@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل العرض الحرفي</h1>
        <div>
            <a href="{{ route('admin.craft-demonstrations.edit', $craftDemonstration) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.craft-demonstrations.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="font-weight-bold">المعلومات الأساسية</h5>
                    <table class="table">
                        <tr>
                            <th>القرية التراثية:</th>
                            <td>
                                <a href="{{ route('admin.heritage-villages.show', $craftDemonstration->village) }}">
                                    {{ $craftDemonstration->village->name_ar ?? '-' }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>اسم الحرفة (العربية):</th>
                            <td>{{ $craftDemonstration->craft_name_ar }}</td>
                        </tr>
                        <tr>
                            <th>Craft Name (English):</th>
                            <td>{{ $craftDemonstration->craft_name_en }}</td>
                        </tr>
                        <tr>
                            <th>الحرفي:</th>
                            <td>{{ $craftDemonstration->artisan_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>أوقات العرض:</th>
                            <td>{{ $craftDemonstration->demonstration_times ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>المدة:</th>
                            <td>{{ $craftDemonstration->duration_minutes ? $craftDemonstration->duration_minutes . ' دقيقة' : '-' }}</td>
                        </tr>
                        <tr>
                            <th>تجربة عملية:</th>
                            <td>
                                @if($craftDemonstration->can_try_hands_on)
                                    <span class="badge bg-success">متاح</span>
                                @else
                                    <span class="badge bg-secondary">غير متاح</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($craftDemonstration->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">الوصف</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $craftDemonstration->description_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $craftDemonstration->description_en }}</p>
                    </div>
                    
                    @if($craftDemonstration->materials_used_ar || $craftDemonstration->materials_used_en)
                    <h5 class="font-weight-bold mt-4">المواد المستخدمة</h5>
                    <div class="mb-3">
                        @if($craftDemonstration->materials_used_ar)
                        <strong>العربية:</strong>
                        <p>{{ $craftDemonstration->materials_used_ar }}</p>
                        @endif
                        @if($craftDemonstration->materials_used_en)
                        <strong>English:</strong>
                        <p>{{ $craftDemonstration->materials_used_en }}</p>
                        @endif
                    </div>
                    @endif
                    
                    @if($craftDemonstration->historical_significance_ar || $craftDemonstration->historical_significance_en)
                    <h5 class="font-weight-bold mt-4">الأهمية التاريخية</h5>
                    <div class="mb-3">
                        @if($craftDemonstration->historical_significance_ar)
                        <strong>العربية:</strong>
                        <p>{{ $craftDemonstration->historical_significance_ar }}</p>
                        @endif
                        @if($craftDemonstration->historical_significance_en)
                        <strong>English:</strong>
                        <p>{{ $craftDemonstration->historical_significance_en }}</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
