<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم') - مهرجان صحار</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #1E3A8A;
            --secondary-color: #F59E0B;
            --bg-light: #F8FAFC;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --sidebar-width: 260px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 20px;
            background: white;
            text-align: center;
            border-bottom: 1px solid #E5E7EB;
        }

        .sidebar-logo {
            width: 120px;
            height: auto;
            margin-bottom: 10px;
        }

        .sidebar-brand {
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
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
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .menu-item {
            list-style: none;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-link:hover {
            background: var(--bg-light);
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        .menu-link.active {
            background: rgba(30, 58, 138, 0.1);
            color: var(--primary-color);
            position: relative;
        }

        .menu-link.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 3px 0 0 3px;
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
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
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
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
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
                            <a href="{{ route('admin.restaurants.index') }}" class="menu-link {{ request()->routeIs('admin.restaurants.*') ? 'active' : '' }}">
                                <i class="bi bi-shop menu-icon"></i>
                                المطاعم
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">التراث والثقافة</div>
                    <ul class="menu-list">
                        <li class="menu-item">
                            <a href="{{ route('admin.heritage-villages.index') }}" class="menu-link {{ request()->routeIs('admin.heritage-villages.*') ? 'active' : '' }}">
                                <i class="bi bi-houses menu-icon"></i>
                                القرى التراثية
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
                        <li class="menu-item">
                            <a href="{{ route('admin.emergency-contacts.index') }}" class="menu-link {{ request()->routeIs('admin.emergency-contacts.*') ? 'active' : '' }}">
                                <i class="bi bi-telephone-plus menu-icon"></i>
                                أرقام الطوارئ
                            </a>
                        </li>
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