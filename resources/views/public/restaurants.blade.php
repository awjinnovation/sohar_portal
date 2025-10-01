<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المطاعم - مهرجان صحار</title>
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
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--accent-color) 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }

        .restaurant-card {
            background: white;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }

        .restaurant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .restaurant-image {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }

        .rating {
            color: #ffc107;
        }

        .price-range {
            color: var(--secondary-color);
            font-weight: bold;
        }

        .feature-badge {
            display: inline-block;
            padding: 4px 12px;
            margin: 2px;
            background: #e8f5e9;
            color: var(--primary-color);
            border-radius: 15px;
            font-size: 12px;
        }

        .search-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
                        <a class="nav-link active" href="/restaurants">المطاعم</a>
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
            <h1 class="display-4">المطاعم</h1>
            <p class="lead">اكتشف أفضل المطاعم في مهرجان صحار</p>
        </div>
    </div>

    <!-- Content -->
    <div class="container">
        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" action="/restaurants">
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control form-control-lg"
                               placeholder="البحث في المطاعم..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary btn-lg w-100" type="submit">
                            <i class="bi bi-search"></i> بحث
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Restaurants Grid -->
        <div class="row">
            @forelse($restaurants as $restaurant)
                <div class="col-lg-6">
                    <div class="card restaurant-card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                @if($restaurant->image_url)
                                    <img src="{{ $restaurant->image_url }}" class="restaurant-image h-100" alt="{{ $restaurant->name }}">
                                @else
                                    <div class="d-flex align-items-center justify-content-center bg-light h-100">
                                        <i class="bi bi-shop" style="font-size: 48px; color: #ccc;"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $restaurant->name_ar ?? $restaurant->name }}</h5>
                                    <p class="text-muted small mb-2">{{ $restaurant->cuisine_ar ?? $restaurant->cuisine }}</p>

                                    <!-- Rating -->
                                    @if($restaurant->rating)
                                        <div class="rating mb-2">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $restaurant->rating)
                                                    <i class="bi bi-star-fill"></i>
                                                @else
                                                    <i class="bi bi-star"></i>
                                                @endif
                                            @endfor
                                            <span class="text-muted small">({{ $restaurant->total_ratings }} تقييم)</span>
                                        </div>
                                    @endif

                                    <!-- Price Range -->
                                    @if($restaurant->price_range)
                                        <p class="price-range mb-2">
                                            @for($i = 1; $i <= 4; $i++)
                                                @if($i <= $restaurant->price_range)
                                                    <span>ر.ع</span>
                                                @else
                                                    <span class="text-muted">ر.ع</span>
                                                @endif
                                            @endfor
                                        </p>
                                    @endif

                                    <!-- Location -->
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-geo-alt"></i> {{ $restaurant->location_ar ?? $restaurant->location }}
                                    </p>

                                    <!-- Features -->
                                    <div class="mb-3">
                                        @if($restaurant->has_delivery)
                                            <span class="feature-badge">
                                                <i class="bi bi-truck"></i> توصيل
                                            </span>
                                        @endif
                                        @if($restaurant->has_parking)
                                            <span class="feature-badge">
                                                <i class="bi bi-p-circle"></i> موقف سيارات
                                            </span>
                                        @endif
                                        @if($restaurant->has_wifi)
                                            <span class="feature-badge">
                                                <i class="bi bi-wifi"></i> واي فاي
                                            </span>
                                        @endif
                                        @if($restaurant->is_open)
                                            <span class="feature-badge" style="background: #ffebee; color: #c62828;">
                                                <i class="bi bi-clock"></i> مفتوح الآن
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Action Button -->
                                    <a href="/restaurants/{{ $restaurant->id }}" class="btn btn-primary btn-sm">
                                        عرض التفاصيل <i class="bi bi-arrow-left"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> لا توجد مطاعم متاحة حالياً
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($restaurants->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $restaurants->links() }}
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