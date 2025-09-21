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

    <!-- Festival Management -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-calendar3"></i>
            إدارة المهرجان
        </h2>
        <div class="stats-grid">
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
            <div class="stat-item {{ !in_array('TicketPricing', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-cash stat-icon"></i>
                <div class="stat-label">أسعار التذاكر</div>
                <div class="stat-count">{{ number_format($stats['festival']['ticket_pricing']) }}</div>
                @if(!in_array('TicketPricing', $enabledControllers))
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
            <div class="stat-item {{ !in_array('RestaurantFeature', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-star stat-icon"></i>
                <div class="stat-label">ميزات المطاعم</div>
                <div class="stat-count">{{ number_format($stats['festival']['restaurant_features']) }}</div>
                @if(!in_array('RestaurantFeature', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('RestaurantImage', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-image stat-icon"></i>
                <div class="stat-label">صور المطاعم</div>
                <div class="stat-count">{{ number_format($stats['festival']['restaurant_images']) }}</div>
                @if(!in_array('RestaurantImage', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('RestaurantOpeningHour', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-clock stat-icon"></i>
                <div class="stat-label">ساعات عمل المطاعم</div>
                <div class="stat-count">{{ number_format($stats['festival']['restaurant_opening_hours']) }}</div>
                @if(!in_array('RestaurantOpeningHour', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Heritage & Culture -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-house-heart"></i>
            التراث والثقافة
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('HeritageVillage', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-houses stat-icon"></i>
                <div class="stat-label">القرى التراثية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['heritage_villages']) }}</div>
                @if(!in_array('HeritageVillage', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('VillageImage', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-images stat-icon"></i>
                <div class="stat-label">صور القرى</div>
                <div class="stat-count">{{ number_format($stats['heritage']['village_images']) }}</div>
                @if(!in_array('VillageImage', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('VillageAttraction', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-geo-alt stat-icon"></i>
                <div class="stat-label">معالم القرى</div>
                <div class="stat-count">{{ number_format($stats['heritage']['village_attractions']) }}</div>
                @if(!in_array('VillageAttraction', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('CraftDemonstration', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-palette stat-icon"></i>
                <div class="stat-label">العروض الحرفية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['craft_demonstrations']) }}</div>
                @if(!in_array('CraftDemonstration', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('CraftDemonstrationSchedule', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-calendar-week stat-icon"></i>
                <div class="stat-label">جدول العروض الحرفية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['craft_demonstration_schedules']) }}</div>
                @if(!in_array('CraftDemonstrationSchedule', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('TraditionalActivity', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-activity stat-icon"></i>
                <div class="stat-label">الأنشطة التقليدية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['traditional_activities']) }}</div>
                @if(!in_array('TraditionalActivity', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('CulturalWorkshop', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-mortarboard stat-icon"></i>
                <div class="stat-label">الورش الثقافية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['cultural_workshops']) }}</div>
                @if(!in_array('CulturalWorkshop', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('WorkshopRegistration', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-person-check stat-icon"></i>
                <div class="stat-label">تسجيلات الورش</div>
                <div class="stat-count">{{ number_format($stats['heritage']['workshop_registrations']) }}</div>
                @if(!in_array('WorkshopRegistration', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('WorkshopSchedule', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-calendar2-week stat-icon"></i>
                <div class="stat-label">جدول الورش</div>
                <div class="stat-count">{{ number_format($stats['heritage']['workshop_schedules']) }}</div>
                @if(!in_array('WorkshopSchedule', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('CulturalTimelineEvent', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-clock-history stat-icon"></i>
                <div class="stat-label">الأحداث الثقافية الزمنية</div>
                <div class="stat-count">{{ number_format($stats['heritage']['cultural_timeline_events']) }}</div>
                @if(!in_array('CulturalTimelineEvent', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('PhotoSpot', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-camera stat-icon"></i>
                <div class="stat-label">مواقع التصوير</div>
                <div class="stat-count">{{ number_format($stats['heritage']['photo_spots']) }}</div>
                @if(!in_array('PhotoSpot', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('PhotographyTip', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-lightbulb stat-icon"></i>
                <div class="stat-label">نصائح التصوير</div>
                <div class="stat-count">{{ number_format($stats['heritage']['photography_tips']) }}</div>
                @if(!in_array('PhotographyTip', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Communication -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-megaphone"></i>
            التواصل والإعلانات
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('Announcement', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-megaphone stat-icon"></i>
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
            <div class="stat-item {{ !in_array('EmergencyContact', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-telephone-plus stat-icon"></i>
                <div class="stat-label">أرقام الطوارئ</div>
                <div class="stat-count">{{ number_format($stats['communication']['emergency_contacts']) }}</div>
                @if(!in_array('EmergencyContact', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Locations & Safety -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-map"></i>
            المواقع والسلامة
        </h2>
        <div class="stats-grid">
            <div class="stat-item {{ !in_array('MapLocation', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-pin-map stat-icon"></i>
                <div class="stat-label">مواقع الخريطة</div>
                <div class="stat-count">{{ number_format($stats['locations']['map_locations']) }}</div>
                @if(!in_array('MapLocation', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('LocationCategory', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-bookmark stat-icon"></i>
                <div class="stat-label">فئات المواقع</div>
                <div class="stat-count">{{ number_format($stats['locations']['location_categories']) }}</div>
                @if(!in_array('LocationCategory', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('FirstAidStation', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-hospital stat-icon"></i>
                <div class="stat-label">محطات الإسعاف الأولي</div>
                <div class="stat-count">{{ number_format($stats['locations']['first_aid_stations']) }}</div>
                @if(!in_array('FirstAidStation', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
            <div class="stat-item {{ !in_array('HealthTip', $enabledControllers) ? 'disabled' : '' }}">
                <i class="bi bi-heart-pulse stat-icon"></i>
                <div class="stat-label">نصائح صحية</div>
                <div class="stat-count">{{ number_format($stats['locations']['health_tips']) }}</div>
                @if(!in_array('HealthTip', $enabledControllers))
                    <span class="controller-badge disabled">غير مفعل</span>
                @endif
            </div>
        </div>
    </div>

    <!-- User Data -->
    <div class="stats-card">
        <h2 class="section-title">
            <i class="bi bi-person-badge"></i>
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
                <i class="bi bi-shield-check stat-icon"></i>
                <div class="stat-label">التحقق بالرمز</div>
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
            <i class="bi bi-gear"></i>
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