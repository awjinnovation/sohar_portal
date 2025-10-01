<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مهرجان صحار - الصفحة الرئيسية</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 100px 0;
            border-radius: 0 0 50px 50px;
        }

        .event-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .event-card .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .category-badge {
            background: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .stats-box {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin: 10px 0;
            position: relative;
            z-index: 1;
        }

        .stats-box i {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .stats-number {
            font-size: 36px;
            font-weight: bold;
            color: var(--secondary-color);
        }

        .section-title {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100px;
            height: 3px;
            background: var(--secondary-color);
        }

        .restaurant-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .restaurant-card:hover {
            border-color: var(--secondary-color);
            transform: translateX(-10px);
        }

        .price-tag {
            background: var(--secondary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
        }

        /* Make buttons more obviously clickable */
        .btn-light {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-light:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        a {
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--primary-color);">
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
                        <a class="nav-link active" href="/">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/events">الفعاليات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurants">المطاعم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/locations">المواقع</a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard">لوحة التحكم</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/login">تسجيل الدخول</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-3 mb-4">مرحباً بكم في مهرجان صحار</h1>
            <p class="lead mb-5">اكتشف أفضل الفعاليات والأنشطة في مدينة صحار</p>

            <!-- Quick Action Buttons -->
            <div class="mb-5" style="position: relative; z-index: 10;">
                <a href="/events" class="btn btn-light btn-lg mx-2" role="button">
                    <i class="bi bi-calendar-event"></i> جميع الفعاليات
                </a>
                <a href="/restaurants" class="btn btn-light btn-lg mx-2" role="button">
                    <i class="bi bi-shop"></i> المطاعم
                </a>
                <a href="/locations" class="btn btn-light btn-lg mx-2" role="button">
                    <i class="bi bi-geo-alt"></i> المواقع والخدمات
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-3">
                    <div class="stats-box">
                        <i class="bi bi-calendar-event"></i>
                        <div class="stats-number">{{ $stats['total_events'] }}</div>
                        <p>فعالية</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-box">
                        <i class="bi bi-shop"></i>
                        <div class="stats-number">{{ $stats['total_restaurants'] }}</div>
                        <p>مطعم</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-box">
                        <i class="bi bi-geo-alt"></i>
                        <div class="stats-number">{{ $stats['service_locations'] }}</div>
                        <p>موقع خدمات</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-box">
                        <i class="bi bi-shield-check"></i>
                        <div class="stats-number">{{ $stats['emergency_locations'] }}</div>
                        <p>نقطة طوارئ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Events -->
    @if($featuredEvents->count() > 0)
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">الفعاليات المميزة</h2>
            <div class="row">
                @foreach($featuredEvents as $event)
                <div class="col-md-4 mb-4">
                    <div class="card event-card">
                        @if($event->image_url)
                            <img src="{{ $event->image_url }}" class="card-img-top" alt="{{ $event->title }}">
                        @else
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                <i class="bi bi-calendar-event" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        @endif
                        @if($event->category)
                            <span class="category-badge">{{ $event->category->name_ar }}</span>
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">{{ $event->title_ar ?? $event->title }}</h5>
                            <p class="card-text text-muted">{{ Str::limit($event->description_ar ?? $event->description, 100) }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price-tag">
                                    @if($event->price > 0)
                                        {{ $event->price }} {{ $event->currency }}
                                    @else
                                        مجاني
                                    @endif
                                </span>
                                <a href="/events/{{ $event->id }}" class="btn btn-primary btn-sm">
                                    التفاصيل <i class="bi bi-arrow-left"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Upcoming Events -->
    @if($upcomingEvents->count() > 0)
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">الفعاليات القادمة</h2>
            <div class="row">
                @foreach($upcomingEvents as $event)
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card event-card">
                        @if($event->image_url)
                            <img src="{{ $event->image_url }}" class="card-img-top" alt="{{ $event->title }}">
                        @else
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                <i class="bi bi-calendar-event" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        @endif
                        <div class="card-body">
                            <h6 class="card-title">{{ $event->title_ar ?? $event->title }}</h6>
                            <p class="small text-muted mb-2">
                                <i class="bi bi-calendar"></i> {{ $event->start_time->format('Y-m-d H:i') }}
                            </p>
                            <p class="small text-muted mb-3">
                                <i class="bi bi-geo-alt"></i> {{ $event->location_ar ?? $event->location }}
                            </p>
                            <a href="/events/{{ $event->id }}" class="btn btn-outline-primary btn-sm w-100">
                                عرض التفاصيل
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-3">
                <a href="/events" class="btn btn-primary btn-lg">
                    عرض جميع الفعاليات <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Featured Restaurants -->
    @if($restaurants->count() > 0)
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">المطاعم المميزة</h2>
            <div class="row">
                @foreach($restaurants as $restaurant)
                <div class="col-md-6">
                    <div class="restaurant-card">
                        <div class="row align-items-center">
                            <div class="col-4">
                                @if($restaurant->image_url)
                                    <img src="{{ $restaurant->image_url }}" class="img-fluid rounded" alt="{{ $restaurant->name }}">
                                @else
                                    <div class="bg-light rounded p-4 text-center">
                                        <i class="bi bi-shop" style="font-size: 48px; color: #ccc;"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col-8">
                                <h5>{{ $restaurant->name_ar ?? $restaurant->name }}</h5>
                                <p class="text-muted small mb-2">{{ $restaurant->cuisine_ar ?? $restaurant->cuisine }}</p>
                                <div class="mb-2">
                                    @if($restaurant->rating)
                                        <span class="text-warning">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $restaurant->rating)
                                                    <i class="bi bi-star-fill"></i>
                                                @else
                                                    <i class="bi bi-star"></i>
                                                @endif
                                            @endfor
                                        </span>
                                        <span class="small text-muted">({{ $restaurant->total_ratings }} تقييم)</span>
                                    @endif
                                </div>
                                <div>
                                    @if($restaurant->has_delivery)
                                        <span class="badge bg-success me-2">توصيل</span>
                                    @endif
                                    @if($restaurant->has_parking)
                                        <span class="badge bg-info me-2">موقف سيارات</span>
                                    @endif
                                    @if($restaurant->has_wifi)
                                        <span class="badge bg-primary">واي فاي</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-3">
                <a href="/restaurants" class="btn btn-secondary btn-lg">
                    جميع المطاعم <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </section>
    @endif


    <!-- Footer -->
    <footer class="py-4 text-white" style="background: var(--primary-color);">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 مهرجان صحار. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>