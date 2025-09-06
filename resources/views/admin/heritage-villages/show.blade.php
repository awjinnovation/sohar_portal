@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل القرية التراثية</h1>
        <div>
            <a href="{{ route('admin.heritage-villages.edit', $heritageVillage) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.heritage-villages.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @if($heritageVillage->cover_image)
                    <img src="{{ $heritageVillage->cover_image }}" alt="صورة القرية" class="img-fluid mb-3">
                    @endif
                    
                    <table class="table">
                        <tr>
                            <th width="200">النوع:</th>
                            <td>
                                @switch($heritageVillage->type)
                                    @case('maritime')
                                        <span class="badge bg-primary">بحري</span>
                                        @break
                                    @case('agricultural')
                                        <span class="badge bg-success">زراعي</span>
                                        @break
                                    @case('desert')
                                        <span class="badge bg-warning">صحراوي</span>
                                        @break
                                    @case('mountain')
                                        <span class="badge bg-info">جبلي</span>
                                        @break
                                    @case('urban')
                                        <span class="badge bg-secondary">حضري</span>
                                        @break
                                    @default
                                        {{ $heritageVillage->type }}
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>ساعات العمل:</th>
                            <td>{{ $heritageVillage->opening_hours ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($heritageVillage->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ الإنشاء:</th>
                            <td>{{ $heritageVillage->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">اسم القرية</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $heritageVillage->name_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $heritageVillage->name_en }}</p>
                    </div>
                    
                    <h5 class="font-weight-bold mt-4">الوصف</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $heritageVillage->description_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $heritageVillage->description_en }}</p>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="font-weight-bold mb-3">المحتوى المرتبط</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->images()->count() }}</h3>
                            <p>صور</p>
                            <a href="{{ route('admin.village-images.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->attractions()->count() }}</h3>
                            <p>معالم</p>
                            <a href="{{ route('admin.village-attractions.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->craftDemonstrations()->count() }}</h3>
                            <p>عروض حرفية</p>
                            <a href="{{ route('admin.craft-demonstrations.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3>{{ $heritageVillage->workshops()->count() }}</h3>
                            <p>ورش ثقافية</p>
                            <a href="{{ route('admin.cultural-workshops.index', ['village' => $heritageVillage->id]) }}" class="btn btn-sm btn-primary">عرض</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
