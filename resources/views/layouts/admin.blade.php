<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم') - مهرجان صحار</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800&family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-light: #6BA5E8;
            --primary-dark: #3A7BC8;
            --secondary-color: #FFA726;
            --secondary-light: #FFB74D;
            --accent-color: #EF5350;
            --bg-light: #F8F9FA;
            --bg-lighter: #FFFFFF;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-color: #E0E6ED;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --sidebar-width: 280px;
            --header-height: 75px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', system-ui, -apple-system, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
            font-size: 15px;
            line-height: 1.7;
            letter-spacing: -0.01em;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: #FFFFFF;
            box-shadow: var(--shadow-md);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 1px solid var(--border-color);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        .sidebar-header {
            padding: 25px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        .sidebar-logo {
            width: 140px;
            height: auto;
            margin-bottom: 12px;
            filter: brightness(0) invert(1);
            position: relative;
            z-index: 1;
        }

        .sidebar-brand {
            color: white;
            font-size: 19px;
            font-weight: 700;
            text-decoration: none;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .menu-title {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
            padding: 0 4px;
        }

        .menu-item {
            list-style: none;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 13px 16px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 6px;
            font-size: 14.5px;
            font-weight: 600;
            position: relative;
        }

        .menu-link:hover {
            background: linear-gradient(90deg, rgba(74, 144, 226, 0.1) 0%, rgba(74, 144, 226, 0.05) 100%);
            color: var(--primary-color);
            transform: translateX(-5px);
            box-shadow: var(--shadow-sm);
        }

        .menu-link.active {
            background: linear-gradient(90deg, rgba(74, 144, 226, 0.15) 0%, rgba(74, 144, 226, 0.08) 100%);
            color: var(--primary-color);
            font-weight: 700;
        }

        .menu-link.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 28px;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 4px 0 0 4px;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.4);
        }

        .menu-icon {
            margin-right: 12px;
            margin-left: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-right: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-primary);
            cursor: pointer;
            margin-left: 20px;
            display: none;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-icon-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .header-icon-btn:hover {
            background: var(--bg-light);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--secondary-color);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: var(--bg-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: #E5E7EB;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .content {
            padding: 30px;
            flex: 1;
        }

        .submenu {
            list-style: none;
            padding-right: 32px;
            margin-top: 5px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .menu-item.has-submenu.open .submenu {
            max-height: 500px;
        }

        .submenu .menu-link {
            font-size: 13px;
            padding: 10px 15px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-right: 0;
            }

            .toggle-sidebar {
                display: block;
            }

            .content {
                padding: 20px;
            }
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 10px;
        }

        .dropdown-item {
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--bg-light);
            color: var(--primary-color);
        }

        .dropdown-divider {
            margin: 10px 0;
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="{{ asset('sohar_fastival_logo_no_bg.png') }}" alt="Sohar Festival" class="sidebar-logo">
                <div class="sidebar-brand">لوحة الإدارة</div>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-title">القائمة الرئيسية</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.dashboard') }}" class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <i class="bi bi-speedometer2 menu-icon"></i>
                                لوحة التحكم
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="menu-section">
                    <div class="menu-title">إدارة المهرجان</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.categories.index') }}" class="menu-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                <i class="bi bi-tags menu-icon"></i>
                                الفئات
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.events.index') }}" class="menu-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}">
                                <i class="bi bi-calendar-event menu-icon"></i>
                                الفعاليات
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.event-tags.index') }}" class="menu-link {{ request()->routeIs('admin.event-tags.*') ? 'active' : '' }}">
                                <i class="bi bi-tags-fill menu-icon"></i>
                                وسوم الفعاليات
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.tickets.index') }}" class="menu-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                                <i class="bi bi-ticket-perforated menu-icon"></i>
                                التذاكر
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="menu-section">
                    <div class="menu-title">المطاعم والخدمات</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.restaurants.index') }}" class="menu-link {{ request()->routeIs('admin.restaurants.*') ? 'active' : '' }}">
                                <i class="bi bi-shop menu-icon"></i>
                                المطاعم
                            </a>
                        </li>
                    </ul>
                </div>




                <div class="menu-section">
                    <div class="menu-title">التواصل والإعلانات</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.announcements.index') }}" class="menu-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                                <i class="bi bi-megaphone menu-icon"></i>
                                الإعلانات
                            </a>
                        </li>
                        @if(Route::has('admin.notifications.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.notifications.index') }}" class="menu-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
                                <i class="bi bi-bell menu-icon"></i>
                                الإشعارات
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <div class="menu-section">
                    <div class="menu-title">المواقع والخدمات</div>
                    <ul class="menu-list">
                        @if(Route::has('admin.locations.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.locations.index') }}" class="menu-link {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}">
                                <i class="bi bi-pin-map menu-icon"></i>
                                جميع المواقع
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <div class="menu-section">
                    <div class="menu-title">إدارة المستخدمين</div>
                    <ul class="menu-list">
                        <li class="menu-item has-submenu">
                            <a href="#" class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="bi bi-people menu-icon"></i>
                                المستخدمون
                            </a>
                            <ul class="submenu">
                                <li class="menu-item">
                                    <a href="{{ route('admin.users.index') }}" class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">جميع المستخدمين</a>
                                </li>
                                <li class="menu-item">
                                    <a href="{{ route('admin.users.create') }}" class="menu-link">إضافة مستخدم جديد</a>
                                </li>
                                <li class="menu-item">
                                    <a href="{{ route('admin.roles.index') }}" class="menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">أدوار المستخدمين</a>
                                </li>
                            </ul>
                        </li>
                        @if(Route::has('admin.user-favorites.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.user-favorites.index') }}" class="menu-link {{ request()->routeIs('admin.user-favorites.*') ? 'active' : '' }}">
                                <i class="bi bi-heart menu-icon"></i>
                                المفضلات
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.user-interests.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.user-interests.index') }}" class="menu-link {{ request()->routeIs('admin.user-interests.*') ? 'active' : '' }}">
                                <i class="bi bi-star menu-icon"></i>
                                الاهتمامات
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.otp-verifications.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.otp-verifications.index') }}" class="menu-link {{ request()->routeIs('admin.otp-verifications.*') ? 'active' : '' }}">
                                <i class="bi bi-shield-check menu-icon"></i>
                                التحقق بالرمز
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.payments.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.payments.index') }}" class="menu-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                                <i class="bi bi-credit-card menu-icon"></i>
                                المدفوعات
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <div class="menu-section">
                    <div class="menu-title">الإعدادات</div>
                    <ul class="menu-list">
                        @if(Route::has('admin.app-settings.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.app-settings.index') }}" class="menu-link {{ request()->routeIs('admin.app-settings.*') ? 'active' : '' }}">
                                <i class="bi bi-sliders menu-icon"></i>
                                إعدادات التطبيق
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="toggle-sidebar" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="page-title">@yield('page-title', 'لوحة التحكم')</h1>
                </div>
                
                <div class="header-right">
                    <button class="header-icon-btn">
                        <i class="bi bi-search"></i>
                    </button>
                    
                    <button class="header-icon-btn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    
                    <div class="dropdown">
                        <div class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                            </div>
                            <div class="user-info">
                                <span class="user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
                                <span class="user-role">{{ auth()->user()->roles->first()->name ?? 'مسؤول' }}</span>
                            </div>
                            <i class="bi bi-chevron-down" style="font-size: 12px; margin-left: 5px;"></i>
                        </div>
                        
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> الملف الشخصي</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> الإعدادات</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-left"></i> تسجيل الخروج
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            
            <div class="content">
                @yield('content')
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        function toggleSubmenu(element) {
            const menuItem = element.closest('.menu-item');
            menuItem.classList.toggle('open');
            event.preventDefault();
        }
    </script>
    
    @stack('scripts')
</body>
</html>