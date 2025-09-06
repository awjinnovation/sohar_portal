# Sohar Festival Database Schema for Laravel/MySQL

## Database Design Overview
This database schema is designed for the Sohar Festival application, supporting multi-language content (English/Arabic), user management, event ticketing, restaurant listings, and cultural heritage experiences.

## Tables

### 1. users
Primary user table for authentication and profile management.

default user table + phone otp 

### 2. user_interests
Many-to-many relationship for user interests in event categories.

```sql
CREATE TABLE user_interests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. categories
Event and content categories with multi-language support.

```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    icon_name VARCHAR(50) NOT NULL,
    color_value INT UNSIGNED NOT NULL COMMENT 'Color as integer value',
    image_url VARCHAR(500) NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. events
Main events table with ticketing support.

```sql
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    location_ar VARCHAR(255) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    image_url VARCHAR(500) NULL,
    price DECIMAL(10, 3) NOT NULL DEFAULT 0,
    currency CHAR(3) DEFAULT 'OMR',
    available_tickets INT NULL,
    total_tickets INT NULL,
    organizer_name VARCHAR(255) NULL,
    organizer_name_ar VARCHAR(255) NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_category (category_id),
    INDEX idx_start_time (start_time),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. event_tags
Tags for events with multi-language support.

```sql
CREATE TABLE event_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    tag VARCHAR(50) NOT NULL,
    tag_ar VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id),
    INDEX idx_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. restaurants
Restaurant listings with ratings and features.

```sql
CREATE TABLE restaurants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    cuisine VARCHAR(100) NOT NULL,
    cuisine_ar VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    location_ar VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    rating DECIMAL(2, 1) DEFAULT 0 CHECK (rating >= 0 AND rating <= 5),
    total_ratings INT DEFAULT 0,
    price_range ENUM('$', '$$', '$$$', '$$$$') NOT NULL,
    image_url VARCHAR(500) NULL,
    phone VARCHAR(20) NULL,
    website VARCHAR(255) NULL,
    is_open BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cuisine (cuisine),
    INDEX idx_rating (rating),
    INDEX idx_price_range (price_range),
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 7. restaurant_images
Gallery images for restaurants.

```sql
CREATE TABLE restaurant_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id BIGINT UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 8. restaurant_opening_hours
Opening hours for restaurants.

```sql
CREATE TABLE restaurant_opening_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id BIGINT UNSIGNED NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_restaurant_day (restaurant_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 9. restaurant_features
Features and amenities for restaurants.

```sql
CREATE TABLE restaurant_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id BIGINT UNSIGNED NOT NULL,
    feature VARCHAR(100) NOT NULL,
    feature_ar VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    INDEX idx_restaurant (restaurant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 10. tickets
Ticket purchases and management.

```sql
CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    ticket_type ENUM('standard', 'vip', 'premium', 'early_bird') NOT NULL,
    status ENUM('active', 'used', 'expired', 'cancelled') DEFAULT 'active',
    price DECIMAL(10, 3) NOT NULL,
    currency CHAR(3) DEFAULT 'OMR',
    holder_name VARCHAR(255) NOT NULL,
    seat_number VARCHAR(20) NULL,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    purchase_date DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_event (event_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_qr_code (qr_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 11. ticket_pricing
Dynamic pricing for different ticket types per event.

```sql
CREATE TABLE ticket_pricing (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    ticket_type ENUM('standard', 'vip', 'premium', 'early_bird') NOT NULL,
    price DECIMAL(10, 3) NOT NULL,
    available_quantity INT NOT NULL,
    benefits TEXT NULL,
    benefits_ar TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_type (event_id, ticket_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 12. map_locations
Points of interest on the festival map.

```sql
CREATE TABLE map_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL COMMENT 'entrance, parking, restroom, first_aid, stage, food_court, etc.',
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    color INT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_location (latitude, longitude),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 13. location_categories
Categories for map locations.

```sql
CREATE TABLE location_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    color INT UNSIGNED NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 14. heritage_villages
Cultural heritage village information.

```sql
CREATE TABLE heritage_villages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    type ENUM('maritime', 'agricultural', 'bedouin') NOT NULL,
    cover_image VARCHAR(500) NOT NULL,
    opening_hours VARCHAR(100) NOT NULL,
    virtual_tour_url VARCHAR(500) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 15. village_images
Image gallery for heritage villages.

```sql
CREATE TABLE village_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 16. village_attractions
Key attractions for each heritage village.

```sql
CREATE TABLE village_attractions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    attraction_en VARCHAR(255) NOT NULL,
    attraction_ar VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 17. craft_demonstrations
Traditional craft demonstrations in heritage villages.

```sql
CREATE TABLE craft_demonstrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    craftsperson_name VARCHAR(255) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    duration_minutes INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 18. craft_demonstration_schedule
Schedule times for craft demonstrations.

```sql
CREATE TABLE craft_demonstration_schedule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    demonstration_id BIGINT UNSIGNED NOT NULL,
    schedule_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demonstration_id) REFERENCES craft_demonstrations(id) ON DELETE CASCADE,
    INDEX idx_demonstration (demonstration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 19. traditional_activities
Interactive traditional activities in heritage villages.

```sql
CREATE TABLE traditional_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_interactive BOOLEAN DEFAULT FALSE,
    age_recommendation VARCHAR(50) NOT NULL,
    timing VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 20. cultural_workshops
Paid cultural workshops in heritage villages.

```sql
CREATE TABLE cultural_workshops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    instructor_name VARCHAR(255) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    duration_minutes INT NOT NULL,
    max_participants INT NOT NULL,
    price_omr DECIMAL(10, 3) NOT NULL,
    skill_level VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 21. workshop_schedule
Schedule times for cultural workshops.

```sql
CREATE TABLE workshop_schedule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workshop_id BIGINT UNSIGNED NOT NULL,
    schedule_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workshop_id) REFERENCES cultural_workshops(id) ON DELETE CASCADE,
    INDEX idx_workshop (workshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 22. photo_spots
Recommended photo locations in heritage villages.

```sql
CREATE TABLE photo_spots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    village_id BIGINT UNSIGNED NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    best_time_for_photos VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES heritage_villages(id) ON DELETE CASCADE,
    INDEX idx_village (village_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 23. photography_tips
Tips for each photo spot.

```sql
CREATE TABLE photography_tips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_spot_id BIGINT UNSIGNED NOT NULL,
    tip TEXT NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_spot_id) REFERENCES photo_spots(id) ON DELETE CASCADE,
    INDEX idx_photo_spot (photo_spot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 24. cultural_timeline_events
Historical timeline events for cultural education.

```sql
CREATE TABLE cultural_timeline_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year VARCHAR(20) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    description_en TEXT NOT NULL,
    description_ar TEXT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    category VARCHAR(50) NOT NULL,
    is_key_milestone BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_year (year),
    INDEX idx_category (category),
    INDEX idx_milestone (is_key_milestone),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 25. announcements
Festival announcements and live updates.

```sql
CREATE TABLE announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_ar TEXT NOT NULL,
    type ENUM('info', 'warning', 'emergency', 'celebration') DEFAULT 'info',
    priority INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type),
    INDEX idx_priority (priority),
    INDEX idx_pinned (is_pinned),
    INDEX idx_active (is_active),
    INDEX idx_datetime (start_datetime, end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 26. emergency_contacts
Emergency service contact information.

```sql
CREATE TABLE emergency_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    service_name_ar VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    secondary_phone VARCHAR(20) NULL,
    type ENUM('police', 'ambulance', 'fire', 'first_aid', 'security', 'other') NOT NULL,
    location VARCHAR(255) NULL,
    location_ar VARCHAR(255) NULL,
    is_24_hours BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 27. first_aid_stations
First aid station locations.

```sql
CREATE TABLE first_aid_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    location_ar VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    operating_hours VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NULL,
    services_available TEXT NULL,
    services_available_ar TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 28. health_tips
Health and safety tips for festival attendees.

```sql
CREATE TABLE health_tips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_ar TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 29. app_settings
Application configuration and settings.

```sql
CREATE TABLE app_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'decimal') DEFAULT 'string',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 30. notifications
Push notifications and in-app messages.

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_ar TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Relationships Summary

1. **Users** → User Interests → **Categories** (Many-to-Many)
2. **Events** → **Categories** (Many-to-One)
3. **Events** → Event Tags (One-to-Many)
4. **Tickets** → **Events** (Many-to-One)
5. **Tickets** → **Users** (Many-to-One)
6. **Ticket Pricing** → **Events** (Many-to-One)
7. **Restaurants** → Restaurant Images/Features/Opening Hours (One-to-Many)
8. **Heritage Villages** → Village Images/Attractions (One-to-Many)
9. **Heritage Villages** → Craft Demonstrations/Activities/Workshops/Photo Spots (One-to-Many)
10. **Announcements** → **Users** (created_by) (Many-to-One)
11. **Notifications** → **Users** (Many-to-One)

## Laravel Migration Notes

### Key Considerations for Laravel Implementation:

1. **Eloquent Models**: Create corresponding Eloquent models for each table with proper relationships
2. **Multilingual Support**: Use Laravel's localization features or a package like `spatie/laravel-translatable`
3. **API Resources**: Create API resources for proper JSON serialization
4. **Validation Rules**: Implement form requests with validation rules
5. **Scopes**: Add query scopes for common filters (active, featured, etc.)
6. **Observers**: Use model observers for updating counters and timestamps
7. **Policies**: Implement authorization policies for role-based access
8. **Queues**: Use queues for sending notifications and processing tickets
9. **Cache**: Cache frequently accessed data like categories and settings
10. **Full-text Search**: Consider adding full-text indexes for search functionality

### Recommended Laravel Packages:

- `laravel/sanctum` - API authentication
- `spatie/laravel-permission` - Advanced role management
- `intervention/image` - Image processing for uploads
- `simplesoftwareio/simple-qrcode` - QR code generation for tickets
- `maatwebsite/excel` - Export functionality
- `spatie/laravel-medialibrary` - Media management

### Security Considerations:

1. Implement rate limiting for API endpoints
2. Use UUID for public-facing IDs (tickets, QR codes)
3. Encrypt sensitive data (emirates_id)
4. Implement CORS properly for API access
5. Use prepared statements (Laravel does this by default)
6. Validate and sanitize all inputs
7. Implement proper authentication and authorization
8. Use HTTPS in production
9. Regular backups of the database
10. Audit logging for sensitive operations

## Sample Seed Data Categories

```sql
-- Sample categories
INSERT INTO categories (name, name_ar, description, description_ar, icon_name, color_value) VALUES
('Music & Concerts', 'الموسيقى والحفلات', 'Live music performances and concerts', 'العروض الموسيقية الحية والحفلات', 'music_note', 4280391411),
('Cultural Events', 'الفعاليات الثقافية', 'Traditional and cultural activities', 'الأنشطة التقليدية والثقافية', 'museum', 4288423856),
('Food & Dining', 'الطعام وتناول الطعام', 'Food festivals and dining experiences', 'مهرجانات الطعام وتجارب تناول الطعام', 'restaurant', 4286141768),
('Sports & Recreation', 'الرياضة والترفيه', 'Sports events and recreational activities', 'الأحداث الرياضية والأنشطة الترفيهية', 'sports_soccer', 4283215696),
('Arts & Crafts', 'الفنون والحرف', 'Art exhibitions and craft workshops', 'المعارض الفنية وورش الحرف', 'palette', 4291681337),
('Family & Kids', 'العائلة والأطفال', 'Family-friendly events and activities', 'الفعاليات والأنشطة المناسبة للعائلة', 'family_restroom', 4289920857);
```

This schema provides a comprehensive foundation for the Sohar Festival application with full support for multi-language content, user management, ticketing, and cultural experiences.