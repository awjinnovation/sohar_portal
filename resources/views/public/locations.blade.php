<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المواقع والخدمات - مهرجان صحار</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1B5E20;
            --secondary-color: #FF6F00;
            --accent-color: #B71C1C;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .navbar {
            background: var(--primary-color) !important;
        }

        .page-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }

        .location-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .location-card {
            background: #f8f9fa;
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .location-card:hover {
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(-5px);
        }

        .type-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 24px;
            color: white;
        }

        .type-emergency { background: #f44336; }
        .type-first_aid { background: #e91e63; }
        .type-service { background: #4caf50; }
        .type-parking { background: #2196f3; }
        .type-restroom { background: #ff9800; }
        .type-restaurant { background: #9c27b0; }
        .type-activity { background: #00bcd4; }

        .section-title {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 25px;
            border-bottom: 3px solid var(--primary-color);
        }

        .filter-tabs {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-tab {
            display: inline-block;
            padding: 10px 25px;
            margin: 5px;
            border: 2px solid #dee2e6;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-festival"></i>
                مهرجان صحار
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/events">الفعاليات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurants">المطاعم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/locations">المواقع</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="display-4">المواقع والخدمات</h1>
            <p class="lead">جميع المواقع والخدمات المتاحة في المهرجان</p>
        </div>
    </div>

    <!-- Content -->
    <div class="container">
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="/locations" class="filter-tab {{ !request('type') ? 'active' : '' }}">
                <i class="bi bi-grid-3x3-gap"></i> جميع المواقع
            </a>
            <a href="/locations?type=emergency" class="filter-tab {{ request('type') == 'emergency' ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle"></i> الطوارئ
            </a>
            <a href="/locations?type=first_aid" class="filter-tab {{ request('type') == 'first_aid' ? 'active' : '' }}">
                <i class="bi bi-hospital"></i> الإسعافات الأولية
            </a>
            <a href="/locations?type=service" class="filter-tab {{ request('type') == 'service' ? 'active' : '' }}">
                <i class="bi bi-gear"></i> الخدمات
            </a>
            <a href="/locations?type=parking" class="filter-tab {{ request('type') == 'parking' ? 'active' : '' }}">
                <i class="bi bi-p-circle"></i> مواقف السيارات
            </a>
            <a href="/locations?type=restroom" class="filter-tab {{ request('type') == 'restroom' ? 'active' : '' }}">
                <i class="bi bi-gender-ambiguous"></i> دورات المياه
            </a>
        </div>

        <!-- Locations by Type -->
        @forelse($locations as $type => $typeLocations)
            @php
                $typeNames = [
                    'emergency' => 'خدمات الطوارئ',
                    'first_aid' => 'الإسعافات الأولية',
                    'service' => 'الخدمات',
                    'parking' => 'مواقف السيارات',
                    'restroom' => 'دورات المياه',
                    'restaurant' => 'المطاعم',
                    'activity' => 'الأنشطة',
                ];
                $typeIcons = [
                    'emergency' => 'bi-exclamation-triangle-fill',
                    'first_aid' => 'bi-hospital',
                    'service' => 'bi-gear-fill',
                    'parking' => 'bi-p-circle-fill',
                    'restroom' => 'bi-gender-ambiguous',
                    'restaurant' => 'bi-shop',
                    'activity' => 'bi-flag-fill',
                ];
            @endphp

            <div class="location-section">
                <h3 class="section-title">
                    <i class="bi {{ $typeIcons[$type] ?? 'bi-geo-alt' }}"></i>
                    {{ $typeNames[$type] ?? $type }}
                    <span class="badge bg-primary ms-2">{{ $typeLocations->count() }}</span>
                </h3>

                <div class="row">
                    @foreach($typeLocations as $location)
                        <div class="col-lg-6">
                            <div class="location-card">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="type-icon type-{{ $type }}">
                                            <i class="bi {{ $typeIcons[$type] ?? 'bi-geo-alt' }}"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1">{{ $location->name_ar ?? $location->name }}</h5>
                                        @if($location->description || $location->description_ar)
                                            <p class="text-muted small mb-2">
                                                {{ $location->description_ar ?? $location->description }}
                                            </p>
                                        @endif
                                        @if($location->address || $location->address_ar)
                                            <p class="mb-1">
                                                <i class="bi bi-geo-alt text-primary"></i>
                                                {{ $location->address_ar ?? $location->address }}
                                            </p>
                                        @endif
                                        @if($location->contact_number)
                                            <p class="mb-0">
                                                <i class="bi bi-telephone text-success"></i>
                                                <a href="tel:{{ $location->contact_number }}" class="text-decoration-none">
                                                    {{ $location->contact_number }}
                                                </a>
                                            </p>
                                        @endif

                                        @if($location->additional_info)
                                            @php $info = json_decode($location->additional_info, true); @endphp
                                            @if($info)
                                                <div class="mt-2">
                                                    @if(isset($info['is_24_hours']) && $info['is_24_hours'])
                                                        <span class="badge bg-success">24/7</span>
                                                    @endif
                                                    @if(isset($info['operating_hours']))
                                                        <span class="badge bg-info">{{ $info['operating_hours'] }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    @if($location->latitude && $location->longitude)
                                        <div class="col-auto">
                                            <a href="https://maps.google.com/?q={{ $location->latitude }},{{ $location->longitude }}"
                                               target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-map"></i> الخريطة
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> لا توجد مواقع متاحة حالياً
            </div>
        @endforelse
    </div>

    <!-- Footer -->
    <footer class="py-4 mt-5 text-white" style="background: var(--primary-color);">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 مهرجان صحار. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>