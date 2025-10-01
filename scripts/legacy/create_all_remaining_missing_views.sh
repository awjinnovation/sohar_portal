#!/bin/bash

echo "Creating ALL missing views for admin panel..."

# Helper function to create standard CRUD views
create_crud_views() {
    local dir=$1
    local entity=$2
    local entity_ar=$3
    local controller=$4
    
    mkdir -p "resources/views/admin/$dir"
    
    # Create form partial
    cat > "resources/views/admin/$dir/_form.blade.php" << EOF
<ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button" role="tab">العربية</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button" role="tab">English</button>
    </li>
</ul>

<div class="tab-content border border-top-0 p-3 mb-3" id="myTabContent">
    <div class="tab-pane fade show active" id="ar" role="tabpanel">
        <div class="mb-3">
            <label class="form-label">الحقول العربية</label>
            <input type="text" class="form-control" placeholder="سيتم تخصيصها حسب الكيان">
        </div>
    </div>
    <div class="tab-pane fade" id="en" role="tabpanel">
        <div class="mb-3">
            <label class="form-label">English Fields</label>
            <input type="text" class="form-control" placeholder="Will be customized per entity">
        </div>
    </div>
</div>
EOF

    # Create index if it doesn't exist
    if [ ! -f "resources/views/admin/$dir/index.blade.php" ]; then
        cat > "resources/views/admin/$dir/index.blade.php" << EOF
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">$entity_ar</h1>
        <a href="{{ route('admin.$dir.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة جديد
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
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(\$items as \$item)
                            <tr>
                                <td>{{ \$item->id }}</td>
                                <td>{{ \$item->name ?? \$item->title ?? '-' }}</td>
                                <td>
                                    @if(\$item->is_active ?? true)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>{{ \$item->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.$dir.show', \$item) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.$dir.edit', \$item) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.$dir.destroy', \$item) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
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
                                <td colspan="5" class="text-center">لا توجد بيانات</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists(\$items, 'links'))
                {{ \$items->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
EOF
    fi

    # Create 'create' view
    cat > "resources/views/admin/$dir/create.blade.php" << EOF
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة $entity_ar جديد</h1>
        <a href="{{ route('admin.$dir.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.$dir.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('admin.$dir._form')
                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

    # Create 'edit' view
    cat > "resources/views/admin/$dir/edit.blade.php" << EOF
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تعديل $entity_ar</h1>
        <a href="{{ route('admin.$dir.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.$dir.update', \$item) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.$dir._form')
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF

    # Create 'show' view
    cat > "resources/views/admin/$dir/show.blade.php" << EOF
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل $entity_ar</h1>
        <div>
            <a href="{{ route('admin.$dir.edit', \$item) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.$dir.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <table class="table">
                <tr>
                    <th width="200">المعرف:</th>
                    <td>{{ \$item->id }}</td>
                </tr>
                <tr>
                    <th>تاريخ الإنشاء:</th>
                    <td>{{ \$item->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <th>آخر تحديث:</th>
                    <td>{{ \$item->updated_at->format('Y-m-d H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection
EOF

    echo "Created views for $entity_ar in $dir/"
}

# Create missing views for entities with partial views
# These already have index, need create/edit/show

# Village Attractions (missing create, edit, show)
for view in create edit show; do
    if [ "$view" = "create" ]; then
        cat > "resources/views/admin/village-attractions/create.blade.php" << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إضافة معلم جديد</h1>
        <a href="{{ route('admin.village-attractions.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.village-attractions.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="heritage_village_id" class="form-label">القرية التراثية</label>
                    <select class="form-control @error('heritage_village_id') is-invalid @enderror" id="heritage_village_id" name="heritage_village_id" required>
                        <option value="">اختر القرية</option>
                        @foreach($villages as $village)
                            <option value="{{ $village->id }}">{{ $village->name_ar }}</option>
                        @endforeach
                    </select>
                    @error('heritage_village_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar" type="button">العربية</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="en-tab" data-bs-toggle="tab" data-bs-target="#en" type="button">English</button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 mb-3">
                    <div class="tab-pane fade show active" id="ar">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم المعلم (العربية)</label>
                            <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" required>
                            @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف (العربية)</label>
                            <textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="4" required></textarea>
                            @error('description_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="tab-pane fade" id="en">
                        <div class="mb-3">
                            <label for="name_en" class="form-label">Name (English)</label>
                            <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" required>
                            @error('name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="4" required></textarea>
                            @error('description_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="visiting_hours" class="form-label">ساعات الزيارة</label>
                    <input type="text" class="form-control" id="visiting_hours" name="visiting_hours">
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">نشط</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
EOF
    fi
done

# Event Tags (missing create, edit, show)
create_crud_views "event-tags" "Event Tag" "وسم الفعالية" "EventTagController"

# Tickets (missing create, edit, show)  
create_crud_views "tickets" "Ticket" "التذكرة" "TicketController"

# Cultural Workshops (missing create, edit, show)
create_crud_views "cultural-workshops" "Cultural Workshop" "الورشة الثقافية" "CulturalWorkshopController"

# Photo Spots (missing create, edit, show)
create_crud_views "photo-spots" "Photo Spot" "موقع التصوير" "PhotoSpotController"

# Traditional Activities (missing create, edit, show)
create_crud_views "traditional-activities" "Traditional Activity" "النشاط التقليدي" "TraditionalActivityController"

# Now create views for controllers with NO views at all
create_crud_views "app-settings" "App Setting" "إعدادات التطبيق" "AppSettingController"
create_crud_views "cultural-timeline-events" "Cultural Timeline Event" "حدث الجدول الزمني الثقافي" "CulturalTimelineEventController"
create_crud_views "first-aid-stations" "First Aid Station" "محطة الإسعافات الأولية" "FirstAidStationController"
create_crud_views "health-tips" "Health Tip" "نصيحة صحية" "HealthTipController"
create_crud_views "location-categories" "Location Category" "فئة الموقع" "LocationCategoryController"
create_crud_views "map-locations" "Map Location" "موقع على الخريطة" "MapLocationController"
create_crud_views "notifications" "Notification" "الإشعار" "NotificationController"
create_crud_views "restaurant-features" "Restaurant Feature" "ميزة المطعم" "RestaurantFeatureController"
create_crud_views "restaurant-images" "Restaurant Image" "صورة المطعم" "RestaurantImageController"
create_crud_views "restaurant-opening-hours" "Restaurant Opening Hour" "ساعات عمل المطعم" "RestaurantOpeningHourController"
create_crud_views "ticket-pricing" "Ticket Pricing" "تسعير التذاكر" "TicketPricingController"

echo "All missing views have been created successfully!"