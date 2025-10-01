#!/bin/bash

# ========== VILLAGE ATTRACTIONS VIEWS ==========
mkdir -p resources/views/admin/village-attractions

# Index
cat > resources/views/admin/village-attractions/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">معالم القرى التراثية</h1>
        <a href="{{ route('admin.village-attractions.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة معلم جديد
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
                            <th>الاسم</th>
                            <th>القرية</th>
                            <th>ساعات الزيارة</th>
                            <th>المدة الموصى بها</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attractions as $attraction)
                            <tr>
                                <td>{{ $attraction->name_ar }}</td>
                                <td>{{ $attraction->village->name_ar ?? '-' }}</td>
                                <td>{{ $attraction->visiting_hours ?? '-' }}</td>
                                <td>{{ $attraction->recommended_duration ?? '-' }}</td>
                                <td>
                                    @if($attraction->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.village-attractions.show', $attraction) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.village-attractions.edit', $attraction) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.village-attractions.destroy', $attraction) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">لا توجد معالم</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $attractions->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# ========== CRAFT DEMONSTRATIONS VIEWS ==========
mkdir -p resources/views/admin/craft-demonstrations

cat > resources/views/admin/craft-demonstrations/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">العروض الحرفية</h1>
        <a href="{{ route('admin.craft-demonstrations.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة عرض حرفي جديد
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
                            <th>اسم الحرفة</th>
                            <th>القرية</th>
                            <th>الحرفي</th>
                            <th>المدة</th>
                            <th>تجربة عملية</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($demonstrations as $demo)
                            <tr>
                                <td>{{ $demo->craft_name_ar }}</td>
                                <td>{{ $demo->village->name_ar ?? '-' }}</td>
                                <td>{{ $demo->artisan_name ?? '-' }}</td>
                                <td>{{ $demo->duration_minutes ? $demo->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>
                                    @if($demo->can_try_hands_on)
                                        <span class="badge bg-success">متاح</span>
                                    @else
                                        <span class="badge bg-secondary">غير متاح</span>
                                    @endif
                                </td>
                                <td>
                                    @if($demo->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.craft-demonstrations.show', $demo) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.craft-demonstrations.edit', $demo) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.craft-demonstrations.destroy', $demo) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">لا توجد عروض حرفية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $demonstrations->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# ========== TRADITIONAL ACTIVITIES VIEWS ==========
mkdir -p resources/views/admin/traditional-activities

cat > resources/views/admin/traditional-activities/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">الأنشطة التقليدية</h1>
        <a href="{{ route('admin.traditional-activities.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة نشاط تقليدي جديد
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
                            <th>اسم النشاط</th>
                            <th>القرية</th>
                            <th>النوع</th>
                            <th>المدة</th>
                            <th>الحد الأقصى للمشاركين</th>
                            <th>يتطلب حجز</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            <tr>
                                <td>{{ $activity->activity_name_ar }}</td>
                                <td>{{ $activity->village->name_ar ?? '-' }}</td>
                                <td>{{ $activity->activity_type }}</td>
                                <td>{{ $activity->duration_minutes ? $activity->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>{{ $activity->max_participants ?? '-' }}</td>
                                <td>
                                    @if($activity->booking_required)
                                        <span class="badge bg-warning">مطلوب</span>
                                    @else
                                        <span class="badge bg-info">غير مطلوب</span>
                                    @endif
                                </td>
                                <td>
                                    @if($activity->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.traditional-activities.show', $activity) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.traditional-activities.edit', $activity) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.traditional-activities.destroy', $activity) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">لا توجد أنشطة تقليدية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# ========== CULTURAL WORKSHOPS VIEWS ==========
mkdir -p resources/views/admin/cultural-workshops

cat > resources/views/admin/cultural-workshops/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">الورش الثقافية</h1>
        <a href="{{ route('admin.cultural-workshops.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة ورشة ثقافية جديدة
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
                            <th>عنوان الورشة</th>
                            <th>القرية</th>
                            <th>المدرب</th>
                            <th>المدة</th>
                            <th>السعر</th>
                            <th>الحد الأقصى</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workshops as $workshop)
                            <tr>
                                <td>{{ $workshop->workshop_title_ar }}</td>
                                <td>{{ $workshop->village->name_ar ?? '-' }}</td>
                                <td>{{ $workshop->instructor_name ?? '-' }}</td>
                                <td>{{ $workshop->duration_minutes ? $workshop->duration_minutes . ' دقيقة' : '-' }}</td>
                                <td>{{ $workshop->price ? $workshop->price . ' ر.ع' : 'مجاني' }}</td>
                                <td>{{ $workshop->max_participants ?? '-' }}</td>
                                <td>
                                    @if($workshop->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.cultural-workshops.show', $workshop) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.cultural-workshops.edit', $workshop) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.cultural-workshops.destroy', $workshop) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">لا توجد ورش ثقافية</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $workshops->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# ========== PHOTO SPOTS VIEWS ==========
mkdir -p resources/views/admin/photo-spots

cat > resources/views/admin/photo-spots/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">مواقع التصوير</h1>
        <a href="{{ route('admin.photo-spots.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة موقع تصوير جديد
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
                            <th>اسم الموقع</th>
                            <th>القرية</th>
                            <th>أفضل وقت للتصوير</th>
                            <th>سهل الوصول</th>
                            <th>شائع</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($photoSpots as $spot)
                            <tr>
                                <td>{{ $spot->spot_name_ar }}</td>
                                <td>{{ $spot->village->name_ar ?? '-' }}</td>
                                <td>{{ $spot->best_time_for_photos ?? '-' }}</td>
                                <td>
                                    @if($spot->is_accessible)
                                        <span class="badge bg-success">نعم</span>
                                    @else
                                        <span class="badge bg-warning">لا</span>
                                    @endif
                                </td>
                                <td>
                                    @if($spot->is_popular)
                                        <span class="badge bg-info">شائع</span>
                                    @else
                                        <span class="badge bg-secondary">عادي</span>
                                    @endif
                                </td>
                                <td>
                                    @if($spot->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.photo-spots.show', $spot) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.photo-spots.edit', $spot) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.photo-spots.destroy', $spot) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">لا توجد مواقع تصوير</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $photoSpots->links() }}
        </div>
    </div>
</div>
@endsection
EOF

echo "All Heritage Village related views created successfully!"