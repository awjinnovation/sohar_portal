# Sohar Festival API Documentation

## Base URL
```
https://api.soharfestival.om/api/v1
```

## Authentication
Most endpoints require authentication using Bearer token (Laravel Sanctum).

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
Accept-Language: ar (optional, for Arabic responses)
```

---

## Public Endpoints (No Authentication Required)

### Authentication

#### Send OTP
```
POST /auth/send-otp
```
Request: `auth/send-otp-request.json`
Response: `auth/send-otp-response.json`

#### Verify OTP
```
POST /auth/verify-otp
```
Request: `auth/verify-otp-request.json`
Response: `auth/verify-otp-response.json`

---

### Events

#### List All Events
```
GET /events?page=1&per_page=20&sort_by=start_time
```
Response: `events/list-events-response.json`

#### Search Events
```
GET /events/search?query=music&category_id=1&start_date=2025-10-15
```
Request: `events/search-request.json`

#### Get Upcoming Events
```
GET /events/upcoming
```

#### Get Today's Events
```
GET /events/today
```

#### Get Featured Events
```
GET /events/featured
```

#### Get Events by Category
```
GET /events/category/{categoryId}
```

#### Get Single Event
```
GET /events/{id}
```

---

### Categories

#### List All Categories
```
GET /categories?active_only=1&sort_by=name
```
Response: `categories/list-response.json`

#### Get Single Category with Events
```
GET /categories/{id}
```
Response: `categories/show-response.json`

---

### Restaurants

#### List All Restaurants
```
GET /restaurants?page=1&per_page=20
```
Response: `restaurants/list-restaurants-response.json`

#### Get Featured Restaurants
```
GET /restaurants/featured
```

#### Get Open Restaurants
```
GET /restaurants/open-now
```

#### Search Restaurants
```
GET /restaurants/search?query=pizza&cuisine=italian
```

#### Get Restaurants by Cuisine
```
GET /restaurants/by-cuisine?cuisine=omani
```

#### Get Single Restaurant
```
GET /restaurants/{id}
```

---

### Locations

#### List All Locations
```
GET /locations?type=emergency&per_page=20
```
Response: `locations/list-response.json`

#### Get Locations by Type
```
GET /locations/type/{type}
```
Types: `emergency`, `first_aid`, `parking`, `restroom`, `restaurant`, `activity`, `service`

#### Get Emergency Locations
```
GET /locations/emergency
```

#### Get First Aid Stations
```
GET /locations/first-aid
```

#### Get Parking Locations
```
GET /locations/parking
```

#### Get Restroom Locations
```
GET /locations/restrooms
```

#### Get Nearby Locations
```
GET /locations/nearby?latitude=24.3667&longitude=56.7500&radius=2
```
Request: `locations/nearby-request.json`
Response: `locations/nearby-response.json`

#### Get Single Location
```
GET /locations/{id}
```

---

### Announcements

#### List All Announcements
```
GET /announcements
```
Response: `announcements/list-response.json`

#### Get Active Announcements
```
GET /announcements/active
```

#### Get Priority Announcements
```
GET /announcements/priority
```

#### Get Single Announcement
```
GET /announcements/{id}
```

---

### Tickets

#### Get Ticket Pricing
```
GET /tickets/pricing
```
Response: `tickets/pricing-response.json`

#### Get Available Tickets
```
GET /tickets/available
```

---

### App Settings

#### Get All Settings
```
GET /app-settings
```
Response: `app-settings/settings-response.json`

#### Get Specific Setting
```
GET /app-settings/{key}
```

---

### App Configuration

#### Get All Config
```
GET /config
```

#### Get Theme Config
```
GET /config/theme
```

#### Get Branding Config
```
GET /config/branding
```

#### Get Features Config
```
GET /config/features
```

#### Get Status Config
```
GET /config/status
```

---

### Notifications (Public)

#### Get Public Notifications
```
GET /notifications/public
```

---

## Protected Endpoints (Authentication Required)

### User Profile

#### Get Profile
```
GET /auth/profile
```
Response: `auth/profile-response.json`

#### Update Profile
```
POST /auth/update-profile
```
Request: `auth/update-profile-request.json`
Response: `auth/update-profile-response.json`

#### Logout
```
POST /auth/logout
```

#### Refresh Token
```
POST /auth/refresh-token
```

#### Delete Account
```
DELETE /auth/delete-account
```

---

### Tickets

#### Check Availability
```
POST /tickets/check-availability
```

#### Purchase Ticket
```
POST /tickets/purchase
```
Request: `tickets/purchase-request.json`

#### Get My Tickets
```
GET /tickets/my-tickets
```

#### Get Single Ticket
```
GET /tickets/{id}
```

#### Get QR Code
```
GET /tickets/{id}/qr-code
```

#### Transfer Ticket
```
POST /tickets/{id}/transfer
```

---

### Notifications

#### List My Notifications
```
GET /notifications
```
Response: `notifications/my-notifications-response.json`

#### Get Unread Notifications
```
GET /notifications/unread
```

#### Mark as Read
```
POST /notifications/{id}/mark-read
```

#### Mark All as Read
```
POST /notifications/mark-all-read
```

#### Get Unread Count
```
GET /notifications/unread-count
```

#### Delete Notification
```
DELETE /notifications/{id}
```

---

### Payments

#### Initialize Payment
```
POST /payments/initialize
```
Request: `payments/initialize-request.json`

#### Confirm Payment
```
POST /payments/confirm
```

#### Check Payment Status
```
GET /payments/status/{sessionId}
```

#### Get Payment History
```
GET /payments/history
```

#### Get Single Payment
```
GET /payments/{id}
```

---

### Favorites

#### List All Favorites
```
GET /favorites
```
Response: `favorites/list-response.json`

#### Get Favorite Events
```
GET /favorites/events
```

#### Get Favorite Restaurants
```
GET /favorites/restaurants
```

#### Toggle Favorite
```
POST /favorites/toggle
```
Request: `favorites/toggle-request.json`
Add Response: `favorites/toggle-add-response.json`
Remove Response: `favorites/toggle-remove-response.json`

#### Remove Favorite
```
DELETE /favorites/{id}
```

---

## Response Format

All API responses follow this standard format:

### Success Response
```json
{
  "success": true,
  "data": {...},
  "message": "Operation successful"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {...} // Optional validation errors
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  },
  "message": "Data retrieved successfully"
}
```

---

## Error Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Rate Limiting

API requests are limited to:
- Public endpoints: 60 requests per minute
- Authenticated endpoints: 120 requests per minute

---

## Notes

1. All dates are in ISO 8601 format: `YYYY-MM-DD HH:MM:SS`
2. All prices are in Omani Rial (OMR) unless specified otherwise
3. Distance calculations use kilometers (km)
4. Coordinates use decimal degrees format
5. Phone numbers include country code (+968 for Oman)
6. Images are returned as full URLs

---

## Deprecated Endpoints

The following endpoints have been removed as of the latest update:
- `/heritage-villages/*` - Content migrated to events
- `/village-attractions/*` - Content migrated to events
- `/craft-demonstrations/*` - Content migrated to events
- `/traditional-activities/*` - Content migrated to events
- `/cultural-workshops/*` - Content migrated to events
- `/photo-spots/*` - Content migrated to locations
- `/emergency-contacts` - Use `/locations/emergency` instead
- `/map-locations/*` - Use `/locations/*` instead
