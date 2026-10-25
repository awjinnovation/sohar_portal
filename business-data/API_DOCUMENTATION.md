# Sohar Festival API Documentation

> **Platform update (white-label):** every request is now scoped to a tenant via the `X-Tenant: <slug>` header (default `north-batinah`).
> Payments no longer accept a client `amount`. New endpoints — tenant config, wallet, top-ups, points, merchant, quote — are documented in
> [`PLATFORM_API.md`](PLATFORM_API.md) and the shared contract `../../docs/platform_contract.md`.


Base URL: `https://your-domain.com/api/v1`
Development: `http://localhost:8000/api/v1`

## Table of Contents
- [Authentication](#authentication)
- [Events](#events)
- [Tickets](#tickets)
- [Heritage Villages](#heritage-villages)
- [Restaurants](#restaurants)
- [Cultural Workshops](#cultural-workshops)
- [Traditional Activities](#traditional-activities)
- [Craft Demonstrations](#craft-demonstrations)
- [Photo Spots](#photo-spots)
- [Map Locations](#map-locations)
- [Notifications](#notifications)
- [Payments](#payments)
- [Emergency Contacts](#emergency-contacts)
- [Announcements](#announcements)
- [App Settings](#app-settings)

---

## Authentication

The API uses OTP-based authentication with Laravel Sanctum tokens.

### Headers
For authenticated endpoints (marked with 🔒), include:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Send OTP
**POST** `/auth/send-otp`

Sends an OTP code to the provided phone number.

**Request Body:**
```json
{
  "phone_number": "96812345678"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "expires_in": 300,
  "otp": 123456  // Only in development mode
}
```

### Verify OTP
**POST** `/auth/verify-otp`

Verifies the OTP and returns an authentication token.

**Request Body:**
```json
{
  "phone_number": "96812345678",
  "otp_code": "123456",  // Can also use "otp" field
  "device_token": "optional_firebase_token"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "User Name",
    "phone_number": "96812345678",
    "email": "user@soharfestival.om",
    "preferred_language": "en"
  },
  "token": "1|authentication_token_here"
}
```

### Get Profile 🔒
**GET** `/auth/profile`

**Response (200 OK):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "User Name",
    "phone_number": "96812345678",
    "email": "user@example.com",
    "preferred_language": "en",
    "created_at": "2025-09-19T10:00:00Z"
  }
}
```

### Update Profile 🔒
**POST** `/auth/update-profile`

**Request Body:**
```json
{
  "name": "New Name",
  "email": "newemail@example.com",
  "preferred_language": "ar",
  "device_token": "new_device_token"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "user": {
    "id": 1,
    "name": "New Name",
    "phone_number": "96812345678",
    "email": "newemail@example.com",
    "preferred_language": "ar"
  }
}
```

### Logout 🔒
**POST** `/auth/logout`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Refresh Token 🔒
**POST** `/auth/refresh-token`

**Response (200 OK):**
```json
{
  "success": true,
  "token": "new_authentication_token"
}
```

---

## Events

### List Events
**GET** `/events`

Returns all events with support for filtering.

**Query Parameters:**
- `date` (YYYY-MM-DD): Get events active on this specific date (checks if date falls within event's date range)
- `status` (active|inactive): Filter by active status
- `search` (string): Search in title and description
- `page` (integer): Page number for pagination

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Sohar Heritage Festival 2025",
      "title_ar": "مهرجان صحار التراثي 2025",
      "description": "Experience the rich cultural heritage...",
      "description_ar": "استمتع بالتراث الثقافي الغني...",
      "category_id": 1,
      "start_time": "2025-09-19T10:00:00.000000Z",
      "end_time": "2025-12-11T22:00:00.000000Z",
      "location": "Sohar Heritage Village",
      "location_ar": "قرية صحار التراثية",
      "map_location": {
        "id": 1,
        "name": "Sohar Heritage Village",
        "name_ar": "قرية صحار التراثية",
        "latitude": 24.3570,
        "longitude": 56.7520
      },
      "image_url": "https://picsum.photos/seed/heritage2025/800/600",
      "price": "5.000",
      "currency": "OMR",
      "available_tickets": 100,
      "total_tickets": 100,
      "organizer_name": null,
      "organizer_name_ar": null,
      "is_featured": true,
      "is_active": true,
      "category": {
        "id": 1,
        "name": "Cultural",
        "name_ar": "ثقافي"
      },
      "tags": []
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 6
  }
}
```

### Get Event Details
**GET** `/events/{id}`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Sohar Heritage Festival 2025",
    "title_ar": "مهرجان صحار التراثي 2025",
    "description": "Full description...",
    "category": {...},
    "tags": [...]
  }
}
```

### Today's Events
**GET** `/events/today`

Returns events that are active today (checks if today falls within event's date range).

### Upcoming Events
**GET** `/events/upcoming`

Returns events that will start in the future.

### Featured Events
**GET** `/events/featured`

Returns events marked as featured.

### Events by Category
**GET** `/events/category/{categoryId}`

Category IDs:
- 1: Cultural
- 2: Music
- 3: Food
- 4: Sports
- 5: Kids
- 6: Arts

### Toggle Favorite 🔒
**POST** `/events/{id}/favorite`

**Response (200 OK):**
```json
{
  "success": true,
  "is_favorite": true,
  "message": "Added to favorites"
}
```

### Get Favorite Events 🔒
**GET** `/events/favorites`

Returns user's favorited events.

---

## Tickets

### Ticket Pricing (Public)
**GET** `/tickets/pricing`

Returns all ticket pricing options for active events.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "event_id": 1,
      "ticket_type": "standard",
      "price": "5.000",
      "available_quantity": 100,
      "benefits": "General admission",
      "benefits_ar": "دخول عام",
      "event": {
        "id": 1,
        "title": "Sohar Heritage Festival 2025"
      }
    }
  ]
}
```

### Available Tickets 🔒
**GET** `/tickets/available`

Returns tickets available for purchase for events within their active date range.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "event_id": 1,
      "ticket_type": "standard",
      "price": "5.000",
      "available_quantity": 100,
      "benefits": "General admission",
      "benefits_ar": "دخول عام",
      "event": {
        "id": 1,
        "title": "Sohar Heritage Festival 2025",
        "start_time": "2025-09-19T10:00:00.000000Z",
        "end_time": "2025-12-11T22:00:00.000000Z"
      }
    }
  ]
}
```

### Check Availability 🔒
**POST** `/tickets/check-availability`

**Request Body:**
```json
{
  "ticket_type": "standard",
  "quantity": 2,
  "date": "2025-10-15"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "available": true,
  "price_per_ticket": "5.000",
  "total_price": "10.000"
}
```

### Purchase Tickets 🔒
**POST** `/tickets/purchase`

**Request Body:**
```json
{
  "event_id": 1,
  "ticket_type": "standard",
  "quantity": 2,
  "payment_method": "card"
}
```

### My Tickets 🔒
**GET** `/tickets/my-tickets`

### Get Ticket Details 🔒
**GET** `/tickets/{id}`

### Get Ticket QR Code 🔒
**GET** `/tickets/{id}/qr-code`

---

## Heritage Villages

### List Heritage Villages
**GET** `/heritage-villages`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name_en": "Sohar Heritage Village",
      "name_ar": "قرية صحار التراثية",
      "description_en": "Experience the rich cultural heritage...",
      "description_ar": "استمتع بالتراث الثقافي الغني...",
      "type": "maritime",
      "cover_image": "https://example.com/image.jpg",
      "opening_hours": "10:00 AM - 10:00 PM",
      "virtual_tour_url": "https://example.com/tour",
      "is_active": true
    }
  ]
}
```

### Get Heritage Village Details
**GET** `/heritage-villages/{id}`

---

## Restaurants

### List Restaurants
**GET** `/restaurants`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Traditional Omani Kitchen",
      "name_ar": "المطبخ العماني التقليدي",
      "description": "Authentic Omani cuisine",
      "description_ar": "المأكولات العمانية الأصيلة",
      "cuisine": "Omani",
      "cuisine_ar": "عماني",
      "location": "Food Court Area A",
      "location_ar": "منطقة الطعام أ",
      "latitude": 24.4541,
      "longitude": 56.6240,
      "phone": "+968 12345678",
      "website": "https://soharfestival.om/restaurants/omani",
      "price_range": "$$",
      "rating": 4.5,
      "total_ratings": 120,
      "is_open": true,
      "is_featured": true,
      "is_active": true
    }
  ],
  "pagination": {...}
}
```

### Open Now
**GET** `/restaurants/open-now`

### Search Restaurants
**GET** `/restaurants/search`

**Query Parameters:**
- `q` (string): Search query

### Get Restaurant Details
**GET** `/restaurants/{id}`

### Toggle Favorite 🔒
**POST** `/restaurants/{id}/favorite`

### Get Favorite Restaurants 🔒
**GET** `/restaurants/favorites`

---

## Cultural Workshops

### List Workshops
**GET** `/cultural-workshops`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "heritage_village_id": 1,
      "title_en": "Pottery Workshop",
      "title_ar": "ورشة الفخار",
      "description_en": "Learn pottery making",
      "description_ar": "تعلم صناعة الفخار",
      "instructor_name": "Ahmed Ali",
      "image_url": "https://example.com/pottery.jpg",
      "duration_minutes": 120,
      "max_participants": 20,
      "price_omr": "15.00",
      "skill_level": "beginner",
      "is_active": true
    }
  ]
}
```

### Available Workshops
**GET** `/cultural-workshops/available`

### Get Workshop Details
**GET** `/cultural-workshops/{id}`

### Register for Workshop 🔒
**POST** `/cultural-workshops/{id}/register`

### Cancel Registration 🔒
**DELETE** `/cultural-workshops/{id}/cancel`

### My Registrations 🔒
**GET** `/cultural-workshops/my-registrations`

---

## Traditional Activities

### List Activities
**GET** `/traditional-activities`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "heritage_village_id": 1,
      "activity_name_en": "Traditional Dance",
      "activity_name_ar": "الرقص التقليدي",
      "description_en": "Watch traditional Omani dances",
      "description_ar": "شاهد الرقصات العمانية التقليدية",
      "image_url": "https://example.com/dance.jpg",
      "is_interactive": true,
      "age_recommendation": "All ages",
      "timing": "7:00 PM - 8:00 PM",
      "is_active": true
    }
  ]
}
```

### Interactive Activities
**GET** `/traditional-activities/interactive`

### Get Activity Details
**GET** `/traditional-activities/{id}`

---

## Craft Demonstrations

### List Demonstrations
**GET** `/craft-demonstrations`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "heritage_village_id": 1,
      "craft_name_en": "Silver Making",
      "craft_name_ar": "صناعة الفضة",
      "artisan_name": "Mohammed Al-Salmi",
      "description_en": "Traditional silver jewelry making",
      "description_ar": "صناعة المجوهرات الفضية التقليدية",
      "demonstration_times": "10:00 AM - 12:00 PM",
      "duration_minutes": 120,
      "can_try_hands_on": true,
      "is_active": true
    }
  ]
}
```

### Live Demonstrations
**GET** `/craft-demonstrations/live`

### Get Demonstration Details
**GET** `/craft-demonstrations/{id}`

---

## Photo Spots

### List Photo Spots
**GET** `/photo-spots`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "heritage_village_id": 1,
      "name_en": "Main Gate Photo Spot",
      "name_ar": "نقطة تصوير البوابة الرئيسية",
      "description_en": "Perfect spot for entrance photos",
      "description_ar": "مكان مثالي لصور المدخل",
      "image_url": "https://example.com/gate-photo.jpg",
      "best_time_for_photos": "Sunset - 6:00 PM to 7:00 PM",
      "is_active": true
    }
  ]
}
```

### Get Photo Spot Details
**GET** `/photo-spots/{id}`

---

## Map Locations

### List All Locations
**GET** `/map-locations`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "entertainment",
      "name": "Main Stage",
      "name_ar": "المسرح الرئيسي",
      "description": "Main performance area",
      "description_ar": "منطقة العروض الرئيسية",
      "latitude": 24.4539,
      "longitude": 56.6238,
      "icon": "stage",
      "color": "#FF5722",
      "is_active": true
    }
  ]
}
```

### Entertainment Locations
**GET** `/map-locations/entertainment`

### Food Locations
**GET** `/map-locations/food`

### Facilities
**GET** `/map-locations/facilities`

### Parking Areas
**GET** `/map-locations/parking`

### Locations by Category
**GET** `/map-locations/category/{category}`

Categories: entertainment, food, facilities, parking

---

## Notifications

### Public Notifications
**GET** `/notifications/public`

### My Notifications 🔒
**GET** `/notifications/my-notifications`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Welcome to Festival",
      "title_ar": "مرحباً بكم في المهرجان",
      "body": "Enjoy your visit!",
      "body_ar": "استمتع بزيارتك!",
      "type": "announcement",
      "is_read": false,
      "created_at": "2025-09-19T10:00:00.000000Z"
    }
  ],
  "pagination": {...}
}
```

### Mark as Read 🔒
**POST** `/notifications/{id}/mark-read`

### Mark All as Read 🔒
**POST** `/notifications/mark-all-read`

### Unread Count 🔒
**GET** `/notifications/unread-count`

**Response (200 OK):**
```json
{
  "success": true,
  "unread_count": 3
}
```

---

## Payments

### Initialize Payment 🔒
**POST** `/payments/initialize`

**Request Body:**
```json
{
  "ticket_id": 1,
  "amount": 10.000,
  "payment_method": "card"
}
```

### Confirm Payment 🔒
**POST** `/payments/confirm`

**Request Body:**
```json
{
  "session_id": "payment_session_id",
  "payment_id": "payment_id_from_gateway"
}
```

### Check Payment Status 🔒
**GET** `/payments/status/{sessionId}`

### My Transactions 🔒
**GET** `/payments/my-transactions`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "transaction_id": "TXN1234567890",
      "amount": "10.000",
      "currency": "OMR",
      "status": "completed",
      "payment_method": "card",
      "created_at": "2025-09-19T10:00:00.000000Z"
    }
  ],
  "pagination": {...}
}
```

### Webhook (For Payment Gateway)
**POST** `/payments/webhook`

This endpoint is called by payment gateway servers to notify payment status changes.

---

## Emergency Contacts

### List Emergency Contacts
**GET** `/emergency-contacts`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "service_name": "Police",
      "service_name_ar": "الشرطة",
      "phone_number": "999",
      "description": "Emergency police services",
      "description_ar": "خدمات الشرطة الطارئة",
      "is_active": true
    }
  ]
}
```

---

## Announcements

### List Announcements
**GET** `/announcements`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Festival Schedule Update",
      "title_ar": "تحديث جدول المهرجان",
      "content": "New events added for the weekend",
      "content_ar": "فعاليات جديدة أضيفت لنهاية الأسبوع",
      "type": "info",
      "priority": 2,
      "is_pinned": true,
      "is_active": true,
      "start_datetime": "2025-09-19T00:00:00.000000Z",
      "end_datetime": "2025-09-26T00:00:00.000000Z"
    }
  ]
}
```

### Active Announcements
**GET** `/announcements/active`

Returns only currently active announcements.

---

## App Settings

### Get App Settings
**GET** `/app-settings`

Returns configuration settings for the mobile app.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "app_version": "1.0.0",
    "force_update": false,
    "maintenance_mode": false,
    "features": {
      "tickets_enabled": true,
      "workshops_enabled": true,
      "payments_enabled": true
    }
  }
}
```

---

## Response Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity (Validation Error)
- `500` - Internal Server Error

## Error Response Format

```json
{
  "success": false,
  "message": "Error message here",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Important Notes

### Date Range Events
Events in the Sohar Festival span multiple days (Sept 19 - Dec 11, 2025). When querying events:
- Use `date` parameter to get events active on a specific date
- The API checks if the requested date falls within the event's `start_time` and `end_time` range
- Each event supports hourly booking slots with capacity limits

### Ticket Availability
- The `available_tickets` field represents capacity per hour slot
- Tickets are available when the event is within its active date range
- Use `/tickets/check-availability` with a specific date to verify slot availability

### Authentication
- All endpoints marked with 🔒 require Bearer token authentication
- OTP codes expire after 5 minutes
- Tokens should be included in the `Authorization` header

### Multilingual Support
- Most content fields have Arabic translations with `_ar` suffix
- Use `preferred_language` in user profile to set default language

### Development vs Production
In development mode:
- OTP codes are returned in the response for testing
- Debug information may be included in error responses

In production mode:
- OTP codes are sent via SMS only
- Error responses contain minimal information

### Rate Limiting
- Public endpoints: 60 requests per minute
- Authenticated endpoints: 120 requests per minute

### Date Format
All dates use ISO 8601 format (YYYY-MM-DDTHH:mm:ss.000000Z)