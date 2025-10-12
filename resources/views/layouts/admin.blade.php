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
            padding: 30px 20px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            text-align: center;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            animation: pulse 6s ease-in-out infinite;
        }

        .sidebar-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
        }

        @keyframes pulse {
            0%, 100% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
        }

        .sidebar-logo {
            width: 150px;
            height: auto;
            margin-bottom: 15px;
            filter: brightness(0) invert(1) drop-shadow(0 4px 12px rgba(0, 0, 0, 0.2));
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .sidebar-logo:hover {
            transform: scale(1.05);
        }

        .sidebar-brand {
            color: white;
            font-size: 20px;
            font-weight: 800;
            text-decoration: none;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 20px 0;
            overflow-y: auto;
            overflow-x: hidden;
            max-height: calc(100vh - 120px);
        }

        /* Custom Scrollbar for Sidebar */
        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
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
            padding: 14px 18px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .menu-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(90deg, var(--primary-color) 0%, transparent 100%);
            transition: width 0.3s ease;
        }

        .menu-link:hover {
            background: linear-gradient(90deg, rgba(74, 144, 226, 0.15) 0%, rgba(74, 144, 226, 0.08) 100%);
            color: var(--primary-color);
            transform: translateX(-8px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.15);
        }

        .menu-link:hover::before {
            width: 4px;
        }

        .menu-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(74, 144, 226, 0.4);
            transform: translateX(-5px);
        }

        .menu-link.active::before {
            width: 0;
        }

        .menu-link.active::after {
            content: '';
            position: absolute;
            right: -2px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 40px;
            background: white;
            border-radius: 6px 0 0 6px;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
        }

        .menu-link.active .menu-icon {
            color: white;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(74, 144, 226, 0.1);
            backdrop-filter: blur(20px);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .toggle-sidebar {
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            border: none;
            width: 42px;
            height: 42px;
            font-size: 20px;
            color: white;
            cursor: pointer;
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toggle-sidebar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(74, 144, 226, 0.4);
        }

        .toggle-sidebar:active {
            transform: translateY(0);
        }

        .page-title {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.03em;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon-btn {
            background: white;
            border: 2px solid rgba(74, 144, 226, 0.1);
            width: 44px;
            height: 44px;
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .header-icon-btn:hover {
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }

        .header-icon-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.2);
        }

        /* Enhanced User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 18px;
            background: white;
            border: 2px solid rgba(74, 144, 226, 0.15);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .user-menu:hover {
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }

        .user-menu:hover .user-avatar {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .user-menu:hover .user-name,
        .user-menu:hover .user-role {
            color: white;
        }

        .user-menu:hover i {
            color: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4A90E2 0%, #3A7BC8 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            transition: all 0.3s ease;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
            line-height: 1.2;
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
            transition: color 0.3s ease;
            line-height: 1.2;
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

        /* Enhanced Dropdown Styles */
        .dropdown-menu {
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border-radius: 12px;
            padding: 8px;
            margin-top: 8px !important;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .dropdown-item {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .dropdown-item:hover {
            background: linear-gradient(90deg, rgba(74, 144, 226, 0.1) 0%, rgba(74, 144, 226, 0.05) 100%);
            color: var(--primary-color);
            transform: translateX(-3px);
        }

        .dropdown-item:active {
            background: linear-gradient(90deg, rgba(74, 144, 226, 0.15) 0%, rgba(74, 144, 226, 0.08) 100%);
        }

        .dropdown-item.text-danger:hover {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336 !important;
        }

        .dropdown-item i {
            font-size: 16px;
            width: 20px;
        }

        .dropdown-divider {
            margin: 8px 0;
            opacity: 0.1;
        }

        /* User Menu Button Fix */
        .user-menu.btn {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
        }

        .user-menu.btn:hover,
        .user-menu.btn:focus,
        .user-menu.btn:active {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            opacity: 0.8;
        }

        /* Dropdown Toggle Arrow */
        .dropdown-toggle::after {
            display: none !important;
        }

        /* Notification Badge Enhancement */
        .notification-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: linear-gradient(135deg, #F44336 0%, #E91E63 100%);
            color: white;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(244, 67, 54, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .user-info {
                display: none !important;
            }

            .dropdown-menu {
                min-width: 280px !important;
            }
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
                <!-- Dashboard -->
                <div class="menu-section">
                    <div class="menu-title">الرئيسية</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.dashboard') }}" class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <i class="bi bi-speedometer2 menu-icon"></i>
                                لوحة التحكم
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Events & Festival Management -->
                <div class="menu-section">
                    <div class="menu-title">الفعاليات والمهرجان</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.events.index') }}" class="menu-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}">
                                <i class="bi bi-calendar-event menu-icon"></i>
                                الفعاليات
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.categories.index') }}" class="menu-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                <i class="bi bi-folder menu-icon"></i>
                                فئات الفعاليات
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.event-tags.index') }}" class="menu-link {{ request()->routeIs('admin.event-tags.*') ? 'active' : '' }}">
                                <i class="bi bi-tags menu-icon"></i>
                                الوسوم
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tickets & Bookings -->
                <div class="menu-section">
                    <div class="menu-title">التذاكر والحجوزات</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.tickets.index') }}" class="menu-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                                <i class="bi bi-ticket-perforated menu-icon"></i>
                                التذاكر
                            </a>
                        </li>
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

                <!-- Locations & Services -->
                <div class="menu-section">
                    <div class="menu-title">المواقع والخدمات</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.restaurants.index') }}" class="menu-link {{ request()->routeIs('admin.restaurants.*') ? 'active' : '' }}">
                                <i class="bi bi-shop menu-icon"></i>
                                المطاعم
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.map-locations.index') }}" class="menu-link {{ request()->routeIs('admin.map-locations.*') ? 'active' : '' }}">
                                <i class="bi bi-map menu-icon"></i>
                                مواقع الخريطة
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Communications -->
                <div class="menu-section">
                    <div class="menu-title">الاتصالات</div>
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

                <!-- Users & Roles -->
                <div class="menu-section">
                    <div class="menu-title">المستخدمون والصلاحيات</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.users.index') }}" class="menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <i class="bi bi-people menu-icon"></i>
                                المستخدمون
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="{{ route('admin.roles.index') }}" class="menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                <i class="bi bi-shield-lock menu-icon"></i>
                                الأدوار والصلاحيات
                            </a>
                        </li>
                        @if(Route::has('admin.otp-verifications.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.otp-verifications.index') }}" class="menu-link {{ request()->routeIs('admin.otp-verifications.*') ? 'active' : '' }}">
                                <i class="bi bi-shield-check menu-icon"></i>
                                التحقق بالرمز
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>

                <!-- User Analytics (Optional) -->
                @if(Route::has('admin.user-favorites.index') || Route::has('admin.user-interests.index'))
                <div class="menu-section">
                    <div class="menu-title">تحليلات المستخدمين</div>
                    <ul class="menu-list">
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
                    </ul>
                </div>
                @endif

                <!-- Settings -->
                <div class="menu-section">
                    <div class="menu-title">الإعدادات</div>
                    <ul class="menu-list">
                        @if(Route::has('admin.app-settings.index'))
                        <li class="menu-item">
                            <a href="{{ route('admin.app-settings.index') }}" class="menu-link {{ request()->routeIs('admin.app-settings.*') ? 'active' : '' }}">
                                <i class="bi bi-gear menu-icon"></i>
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
                    <!-- Search Button -->
                    <button class="header-icon-btn" type="button" title="بحث">
                        <i class="bi bi-search"></i>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div class="dropdown">
                        <button class="header-icon-btn" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="الإشعارات">
                            <i class="bi bi-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationsDropdown" style="min-width: 320px;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold">الإشعارات</span>
                                <span class="badge bg-primary rounded-pill">3</span>
                            </li>
                            <li><hr class="dropdown-divider m-0"></li>
                            <li>
                                <a class="dropdown-item py-3" href="#">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-info-circle text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-semibold">فعالية جديدة</div>
                                            <div class="small text-muted">تم إضافة فعالية جديدة</div>
                                            <div class="small text-muted">منذ 5 دقائق</div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider m-0"></li>
                            <li><a class="dropdown-item text-center small py-2" href="#">عرض جميع الإشعارات</a></li>
                        </ul>
                    </div>

                    <!-- User Menu Dropdown -->
                    <div class="dropdown">
                        <button class="user-menu btn btn-link text-decoration-none p-0" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar">
                                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                                </div>
                                <div class="user-info d-none d-md-block">
                                    <span class="user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
                                    <span class="user-role">{{ auth()->user()->roles->first()->name ?? 'مسؤول' }}</span>
                                </div>
                                <i class="bi bi-chevron-down ms-2" style="font-size: 12px;"></i>
                            </div>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userMenuDropdown" style="min-width: 220px;">
                            <li class="dropdown-header">
                                <div class="text-center">
                                    <div class="fw-bold">{{ auth()->user()->name ?? 'Admin' }}</div>
                                    <div class="small text-muted">{{ auth()->user()->email ?? '' }}</div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2" href="#">
                                    <i class="bi bi-person me-2"></i> الملف الشخصي
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('admin.app-settings.index') }}">
                                    <i class="bi bi-gear me-2"></i> الإعدادات
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="#">
                                    <i class="bi bi-question-circle me-2"></i> المساعدة
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger py-2">
                                        <i class="bi bi-box-arrow-left me-2"></i> تسجيل الخروج
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
        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');

            // Close sidebar when clicking outside on mobile
            if (sidebar.classList.contains('active')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-sidebar');

            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function toggleSubmenu(element) {
            const menuItem = element.closest('.menu-item');
            menuItem.classList.toggle('open');
            event.preventDefault();
        }

        // Initialize Bootstrap dropdowns manually (ensures they work properly)
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl, {
                    boundary: 'viewport',
                    popperConfig: {
                        strategy: 'fixed'
                    }
                });
            });

            // Add smooth scrolling to sidebar
            const sidebar = document.querySelector('.sidebar-menu');
            if (sidebar) {
                sidebar.style.scrollBehavior = 'smooth';
            }

            // Close dropdowns on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    const dropdowns = document.querySelectorAll('.dropdown-menu.show');
                    dropdowns.forEach(dropdown => {
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                        if (bsDropdown) {
                            bsDropdown.hide();
                        }
                    });
                }
            });

            // Prevent dropdown from closing when clicking inside
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.addEventListener('click', function(e) {
                    if (!e.target.closest('a, button')) {
                        e.stopPropagation();
                    }
                });
            });
        });

        // Add active state to current menu item based on URL
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            document.querySelectorAll('.menu-link').forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>