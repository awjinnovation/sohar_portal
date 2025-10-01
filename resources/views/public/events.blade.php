<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الفعاليات - مهرجان صحار</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }

        .event-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            background: white;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .event-card img {
            height: 200px;
            object-fit: cover;
            width: 100%;
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

        .price-badge {
            background: var(--secondary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .category-filter {
            display: inline-block;
            padding: 8px 20px;
            margin: 5px;
            border: 2px solid #dee2e6;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .category-filter:hover,
        .category-filter.active {
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
                        <a class="nav-link active" href="/events">الفعاليات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurants">المطاعم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/locations">المواقع</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="display-4">جميع الفعاليات</h1>
            <p class="lead">اكتشف الفعاليات المميزة في مهرجان صحار</p>
        </div>
    </div>

    <!-- Content -->
    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <h5 class="mb-3">تصفية حسب الفئة:</h5>
            <div>
                <a href="/events" class="category-filter {{ !request('category') ? 'active' : '' }}">
                    جميع الفعاليات
                </a>
                @foreach($categories as $category)
                    <a href="/events?category={{ $category->id }}"
                       class="category-filter {{ request('category') == $category->id ? 'active' : '' }}">
                        {{ $category->name_ar ?? $category->name }}
                    </a>
                @endforeach
            </div>

            <!-- Search -->
            <div class="mt-4">
                <form method="GET" action="/events">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="البحث في الفعاليات..." value="{{ request('search') }}">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="row">
            @forelse($events as $event)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card event-card">
                        @if($event->image_url)
                            <img src="{{ $event->image_url }}" alt="{{ $event->title }}">
                        @else
                            <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                <i class="bi bi-calendar-event" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        @endif
                        @if($event->category)
                            <span class="category-badge">{{ $event->category->name_ar }}</span>
                        @endif
                        <div class="card-body">
                            <h5 class="card-title">{{ $event->title_ar ?? $event->title }}</h5>
                            <p class="card-text text-muted">{{ Str::limit($event->description_ar ?? $event->description, 100) }}</p>

                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> {{ $event->start_time->format('Y-m-d H:i') }}
                                </small><br>
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt"></i> {{ $event->location_ar ?? $event->location }}
                                </small>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price-badge">
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
            @empty
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> لا توجد فعاليات متاحة حالياً
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($events->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $events->links() }}
            </div>
        @endif
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