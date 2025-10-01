@extends('layouts.admin')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')

@section('content')
<style>
    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .section-title {
        color: var(--primary-color);
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .stat-item {
        background: var(--bg-light);
        border-radius: 8px;
        padding: 15px;
        transition: all 0.3s ease;
        border: 1px solid #E5E7EB;
        position: relative;
        overflow: hidden;
    }

    .stat-item:hover {
        background: white;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        transform: scale(1.02);
    }

    .stat-item.disabled {
        opacity: 0.5;
        background: #F3F4F6;
    }

    .stat-item.disabled .stat-count {
        color: #9CA3AF;
    }

    .stat-label {
        color: var(--text-secondary);
        font-size: 13px;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .stat-count {
        color: var(--primary-color);
        font-size: 24px;
        font-weight: 700;
    }

    .stat-icon {
        position: absolute;
        top: 15px;
        left: 15px;
        font-size: 20px;
        color: var(--secondary-color);
        opacity: 0.3;
    }

    .main-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .main-stat-card {
        background: linear-gradient(135deg, var(--primary-color) 0%, #2563EB 100%);
        color: white;
        border-radius: 12px;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }

    .main-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(0.8); opacity: 0.5; }
        50% { transform: scale(1.2); opacity: 0.3; }
    }

    .main-stat-card .stat-label {
        color: rgba(255,255,255,0.9);
        font-size: 14px;
        margin-bottom: 10px;
    }

    .main-stat-card .stat-count {
        color: white;
        font-size: 32px;
        font-weight: 700;
    }

    .main-stat-icon {
        position: absolute;
        top: 20px;
        left: 20px;
        font-size: 48px;
        color: rgba(255,255,255,0.2);
    }

    .controller-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        margin-right: 5px;
        background: #10B981;
        color: white;
    }

    .controller-badge.disabled {
        background: #EF4444;
    }
</style>

<div class="container-fluid p-0">
    <!-- Main Statistics -->
    <div class="main-stats">
        <div class="main-stat-card">
            <i class="bi bi-people-fill main-stat-icon"></i>
            <div class="stat-label">المستخدمين</div>
            <div class="stat-count">{{ number_format($stats['main']['users']) }}</div>
        </div>
        <div class="main-stat-card" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%);">
            <i class="bi bi-calendar-event-fill main-stat-icon"></i>
            <div class="stat-label">الفعاليات</div>
            <div class="stat-count">{{ number_format($stats['main']['events']) }}</div>
        </div>
        <div class="main-stat-card" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <i class="bi bi-ticket-perforated-fill main-stat-icon"></i>
            <div class="stat-label">التذاكر</div>
            <div class="stat-count">{{ number_format($stats['main']['tickets']) }}</div>
        </div>
        <div class="main-stat-card" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
            <i class="bi bi-credit-card-fill main-stat-icon"></i>
            <div class="stat-label">المدفوعات</div>
            <div class="stat-count">{{ number_format($stats['main']['payments']) }}</div>
        </div>
    </div>

    <!-- Festival & Events -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-calendar3"></i>
            الفعاليات والمهرجان
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('Event', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-calendar-event stat-icon"></i>
                <div class="stat-label">الفعاليات</div>
                <div class="stat-count">{{ number_format($stats['main']['events']) }}</div>
                @if(!in_array('Event', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('Category', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-tags stat-icon"></i>
                <div class="stat-label">الفئات</div>
                <div class="stat-count">{{ number_format($stats['festival']['categories']) }}</div>
                @if(!in_array('Category', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('EventTag', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-tag stat-icon"></i>
                <div class="stat-label">وسوم الفعاليات</div>
                <div class="stat-count">{{ number_format($stats['festival']['event_tags']) }}</div>
                @if(!in_array('EventTag', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('Restaurant', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-shop stat-icon"></i>
                <div class="stat-label">المطاعم</div>
                <div class="stat-count">{{ number_format($stats['festival']['restaurants']) }}</div>
                @if(!in_array('Restaurant', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Locations -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-geo-alt-fill"></i>
            المواقع والخدمات
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('Location', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-map stat-icon"></i>
                <div class="stat-label">جميع المواقع</div>
                <div class="stat-count">{{ number_format($stats['locations']['all_locations']) }}</div>
                @if(!in_array('Location', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item">
                <i class="bi bi-exclamation-triangle stat-icon"></i>
                <div class="stat-label">خدمات الطوارئ</div>
                <div class="stat-count">{{ number_format($stats['locations']['emergency']) }}</div>
            </div>
            <div class="stat-item">
                <i class="bi bi-gear stat-icon"></i>
                <div class="stat-label">الخدمات</div>
                <div class="stat-count">{{ number_format($stats['locations']['services']) }}</div>
            </div>
            <div class="stat-item">
                <i class="bi bi-heart-pulse stat-icon"></i>
                <div class="stat-label">الإسعافات الأولية</div>
                <div class="stat-count">{{ number_format($stats['locations']['first_aid']) }}</div>
            </div>
        </div>
    </div>


    <!-- Communications -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-megaphone"></i>
            الاتصالات
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('Announcement', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-broadcast stat-icon"></i>
                <div class="stat-label">الإعلانات</div>
                <div class="stat-count">{{ number_format($stats['communication']['announcements']) }}</div>
                @if(!in_array('Announcement', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('Notification', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-bell stat-icon"></i>
                <div class="stat-label">الإشعارات</div>
                <div class="stat-count">{{ number_format($stats['communication']['notifications']) }}</div>
                @if(!in_array('Notification', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- User Data -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-person-circle"></i>
            بيانات المستخدمين
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('UserFavorite', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-heart stat-icon"></i>
                <div class="stat-label">المفضلات</div>
                <div class="stat-count">{{ number_format($stats['user_data']['user_favorites']) }}</div>
                @if(!in_array('UserFavorite', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('UserInterest', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-star stat-icon"></i>
                <div class="stat-label">الاهتمامات</div>
                <div class="stat-count">{{ number_format($stats['user_data']['user_interests']) }}</div>
                @if(!in_array('UserInterest', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('OtpVerification', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-shield-lock stat-icon"></i>
                <div class="stat-label">رموز التحقق</div>
                <div class="stat-count">{{ number_format($stats['user_data']['otp_verifications']) }}</div>
                @if(!in_array('OtpVerification', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Settings -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-gear-fill"></i>
            الإعدادات
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('AppSetting', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-sliders stat-icon"></i>
                <div class="stat-label">إعدادات التطبيق</div>
                <div class="stat-count">{{ number_format($stats['settings']['app_settings']) }}</div>
                @if(!in_array('AppSetting', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection