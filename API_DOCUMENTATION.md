# Sohar Festival API Documentation

## Base URL
```
Production: https://api.soharfestival.om/api/v1
Development: http://localhost:8000/api/v1
```

## Authentication
The API uses OTP-based authentication with Laravel Sanctum tokens.

### Headers
For authenticated requests, include:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## Authentication Endpoints

### 1. Send OTP
**POST** `/auth/send-otp`

Request:
```json
{
    "phone_number": "92345678"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "OTP sent successfully",
    "expires_in": 300,
    "otp": "123456"  // Only in development mode
}
```

### 2. Verify OTP & Login
**POST** `/auth/verify-otp`

Request:
```json
{
    "phone_number": "92345678",
    "otp_code": "123456",
    "device_token": "firebase_token_here"  // Optional
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "User 5678",
        "phone_number": "92345678",
        "email": "92345678@soharfestival.om",
        "preferred_language": "en"
    },
    "token": "1|laravel_sanctum_token_here"
}
```

### 3. Get Profile
**GET** `/auth/profile` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "user": {
        "id": 1,
        "name": "User Name",
        "phone_number": "92345678",
        "email": "user@example.com",
        "preferred_language": "en",
        "created_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

### 4. Update Profile
**POST** `/auth/update-profile` (Authenticated)

Request:
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "preferred_language": "ar",
    "device_token": "new_firebase_token"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Profile updated successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "phone_number": "92345678",
        "email": "john@example.com",
        "preferred_language": "ar"
    }
}
```

### 5. Logout
**POST** `/auth/logout` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

### 6. Refresh Token
**POST** `/auth/refresh-token` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "token": "2|new_laravel_sanctum_token"
}
```

---

## Event Endpoints

### 1. List Events
**GET** `/events`

Query Parameters:
- `date`: Filter by date (YYYY-MM-DD)
- `status`: Filter by status (active, inactive)
- `search`: Search in title and description
- `page`: Page number for pagination

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Traditional Music Performance",
            "description": "Experience authentic Omani music",
            "event_date": "2024-02-15",
            "event_time": "19:00:00",
            "duration": 120,
            "location": "Main Stage",
            "max_attendees": 500,
            "current_attendees": 150,
            "status": "active",
            "image_url": "https://example.com/image.jpg",
            "category": {
                "id": 1,
                "name": "Music",
                "icon": "music-note"
            },
            "tags": [
                {"id": 1, "name": "Traditional"},
                {"id": 2, "name": "Family Friendly"}
            ]
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### 2. Get Event Details
**GET** `/events/{id}`

Response (200 OK):
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Traditional Music Performance",
        "description": "Full description here...",
        "event_date": "2024-02-15",
        "event_time": "19:00:00",
        "duration": 120,
        "location": "Main Stage",
        "latitude": 24.3456,
        "longitude": 56.7890,
        "max_attendees": 500,
        "current_attendees": 150,
        "status": "active",
        "image_url": "https://example.com/image.jpg",
        "category": {
            "id": 1,
            "name": "Music"
        },
        "tags": [
            {"id": 1, "name": "Traditional"}
        ]
    }
}
```

### 3. Get Upcoming Events
**GET** `/events/upcoming`

Response: Same as List Events but limited to 10 upcoming events

### 4. Get Today's Events
**GET** `/events/today`

Response: Same as List Events but only today's events

### 5. Toggle Event Favorite
**POST** `/events/{id}/favorite` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "is_favorite": true,
    "message": "Added to favorites"
}
```

### 6. Get Favorite Events
**GET** `/events/favorites` (Authenticated)

Response: Same as List Events but only favorited events

---

## Heritage Village Endpoints

### 1. List Heritage Villages
**GET** `/heritage-villages`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Al Hazm Village",
            "description": "Traditional Omani village showcase",
            "opening_hours": "09:00 - 22:00",
            "location": "Festival Grounds East",
            "latitude": 24.3456,
            "longitude": 56.7890,
            "status": "active",
            "images": [
                {"id": 1, "image_url": "https://example.com/img1.jpg"},
                {"id": 2, "image_url": "https://example.com/img2.jpg"}
            ],
            "attractions": [
                {
                    "id": 1,
                    "name": "Traditional House",
                    "description": "Authentic Omani architecture"
                }
            ]
        }
    ]
}
```

### 2. Get Heritage Village Details
**GET** `/heritage-villages/{id}`

Response: Same as single item from list

---

## Restaurant Endpoints

### 1. List Restaurants
**GET** `/restaurants`

Query Parameters:
- `cuisine`: Filter by cuisine type
- `is_vegetarian`: Filter vegetarian options (true/false)
- `is_family_friendly`: Filter family friendly (true/false)

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Al Mandi House",
            "description": "Traditional Omani cuisine",
            "cuisine_type": "Omani",
            "location": "Food Court A",
            "latitude": 24.3456,
            "longitude": 56.7890,
            "phone": "+968 9234 5678",
            "average_price": 15.000,
            "is_vegetarian": true,
            "is_family_friendly": true,
            "status": "active",
            "images": [
                {"id": 1, "image_url": "https://example.com/rest1.jpg"}
            ],
            "features": [
                {"id": 1, "name": "Outdoor Seating"},
                {"id": 2, "name": "WiFi"}
            ],
            "opening_hours": [
                {
                    "day": "Monday",
                    "open_time": "10:00",
                    "close_time": "23:00"
                }
            ]
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 45
    }
}
```

### 2. Search Restaurants
**GET** `/restaurants/search`

Query Parameters:
- `query`: Search term (required, min 2 characters)

Response: Same as List Restaurants

---

## Ticket Endpoints

### 1. Get Available Tickets
**GET** `/tickets/available` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "ticket_type": "general",
            "name": "General Admission",
            "description": "Access to all public areas",
            "price": 5.000,
            "currency": "OMR",
            "start_date": "2024-02-01",
            "end_date": "2024-02-28",
            "is_available": true
        },
        {
            "id": 2,
            "ticket_type": "vip",
            "name": "VIP Pass",
            "description": "VIP access with special benefits",
            "price": 25.000,
            "currency": "OMR",
            "start_date": "2024-02-01",
            "end_date": "2024-02-28",
            "is_available": true
        }
    ]
}
```

### 2. Check Ticket Availability
**POST** `/tickets/check-availability` (Authenticated)

Request:
```json
{
    "ticket_type": "general",
    "quantity": 2,
    "date": "2024-02-15"
}
```

Response (200 OK):
```json
{
    "success": true,
    "available": true,
    "price_per_ticket": 5.000,
    "total_price": 10.000
}
```

### 3. Get My Tickets
**GET** `/tickets/my-tickets` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "ticket_type": "general",
            "ticket_number": "TKT12345678",
            "qr_code": "uuid-string-here",
            "purchase_date": "2024-02-01T10:00:00.000000Z",
            "status": "active",
            "payment_id": 1
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 2
    }
}
```

### 4. Get Ticket QR Code
**GET** `/tickets/{id}/qr-code` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANS...",
    "ticket_number": "TKT12345678"
}
```

---

## Payment Endpoints (Thawani Integration)

### 1. Initialize Payment
**POST** `/payments/initialize` (Authenticated)

Request:
```json
{
    "payment_type": "ticket",
    "payable_id": 1,
    "quantity": 2,
    "amount": 10.000
}
```

Response (200 OK):
```json
{
    "success": true,
    "payment_id": 1,
    "session_id": "thawani_session_id_here",
    "checkout_url": "https://uatcheckout.thawani.om/checkout/session_id?key=publishable_key",
    "expires_at": "2024-02-01T11:00:00.000000Z"
}
```

### 2. Confirm Payment
**POST** `/payments/confirm` (Authenticated)

Request:
```json
{
    "session_id": "thawani_session_id_here"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Payment successful",
    "payment_id": 1,
    "transaction_id": "TXN1234567890"
}
```

### 3. Check Payment Status
**GET** `/payments/status/{sessionId}` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "payment_status": "paid",
    "session_data": {
        "session_id": "thawani_session_id",
        "payment_status": "paid",
        "amount": 10000,
        "currency": "OMR"
    }
}
```

### 4. Get My Transactions
**GET** `/payments/my-transactions` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "transaction_id": "TXN1234567890",
            "payment_type": "ticket",
            "amount": "10.000",
            "currency": "OMR",
            "status": "completed",
            "payment_method": "card",
            "paid_at": "2024-02-01T10:30:00.000000Z",
            "created_at": "2024-02-01T10:28:00.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 5
    }
}
```

### 5. Webhook (For Thawani Server)
**POST** `/payments/webhook`

This endpoint is called by Thawani servers to notify payment status changes.

---

## Cultural Workshop Endpoints

### 1. List Workshops
**GET** `/cultural-workshops`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Traditional Pottery Making",
            "description": "Learn the art of Omani pottery",
            "instructor_name": "Ahmed Al Rashdi",
            "workshop_date": "2024-02-15",
            "workshop_time": "10:00:00",
            "duration": 120,
            "location": "Workshop Hall A",
            "max_participants": 20,
            "current_participants": 12,
            "price": 15.000,
            "status": "active",
            "requirements": "No prior experience needed",
            "image_url": "https://example.com/workshop.jpg"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 20,
        "total": 25
    }
}
```

### 2. Get Available Workshops
**GET** `/cultural-workshops/available`

Response: Same as List Workshops but only shows workshops with available spots

### 3. Register for Workshop
**POST** `/cultural-workshops/{id}/register` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "message": "Successfully registered for workshop"
}
```

### 4. Cancel Workshop Registration
**DELETE** `/cultural-workshops/{id}/cancel` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "message": "Registration cancelled successfully"
}
```

### 5. Get My Workshop Registrations
**GET** `/cultural-workshops/my-registrations` (Authenticated)

Response: Same as List Workshops but only user's registered workshops

---

## Traditional Activity Endpoints

### 1. List Traditional Activities
**GET** `/traditional-activities`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Al Razha Dance",
            "description": "Traditional Omani warrior dance",
            "activity_time": "20:00:00",
            "duration": 30,
            "location": "Cultural Stage",
            "performers": "Al Wahiba Troupe",
            "is_active": true,
            "image_url": "https://example.com/razha.jpg"
        }
    ]
}
```

---

## Craft Demonstration Endpoints

### 1. List Craft Demonstrations
**GET** `/craft-demonstrations`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "craft_name": "Silver Making",
            "craftsman_name": "Mohammed Al Balushi",
            "description": "Traditional Omani silver jewelry crafting",
            "demonstration_time": "11:00:00",
            "duration": 45,
            "location": "Craft Village",
            "is_active": true,
            "image_url": "https://example.com/silver.jpg"
        }
    ]
}
```

---

## Photo Spot Endpoints

### 1. List Photo Spots
**GET** `/photo-spots`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Traditional Gate",
            "description": "Beautiful traditional Omani gate perfect for photos",
            "location": "Main Entrance",
            "latitude": 24.3456,
            "longitude": 56.7890,
            "best_time": "Golden hour (6-7 PM)",
            "tips": "Great lighting in the evening",
            "is_active": true,
            "image_url": "https://example.com/spot.jpg"
        }
    ]
}
```

---

## Emergency Contact Endpoints

### 1. List Emergency Contacts
**GET** `/emergency-contacts`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "service_name": "Medical Emergency",
            "contact_number": "9999",
            "location": "Medical Tent - Main Gate",
            "available_hours": "24/7",
            "priority": 1,
            "is_active": true
        },
        {
            "id": 2,
            "service_name": "Security",
            "contact_number": "9123 4567",
            "location": "Security Office",
            "available_hours": "24/7",
            "priority": 2,
            "is_active": true
        }
    ]
}
```

---

## Map Location Endpoints

### 1. List All Map Locations
**GET** `/map-locations`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Main Stage",
            "category": "entertainment",
            "description": "Main performance stage",
            "latitude": 24.3456,
            "longitude": 56.7890,
            "icon": "stage",
            "is_active": true
        },
        {
            "id": 2,
            "name": "Food Court A",
            "category": "food",
            "description": "Various food stalls",
            "latitude": 24.3457,
            "longitude": 56.7891,
            "icon": "restaurant",
            "is_active": true
        }
    ]
}
```

### 2. Get Locations by Category
**GET** `/map-locations/category/{category}`

Categories: entertainment, food, facility, parking, entrance, emergency

Response: Same as List All Map Locations but filtered by category

---

## Notification Endpoints

### 1. Get Public Notifications
**GET** `/notifications`

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Festival Opening Ceremony",
            "message": "Join us for the grand opening at 6 PM",
            "type": "announcement",
            "priority": "high",
            "created_at": "2024-02-01T09:00:00.000000Z"
        }
    ]
}
```

### 2. Get My Notifications
**GET** `/notifications/my-notifications` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Ticket Purchase Confirmed",
            "message": "Your ticket purchase was successful",
            "type": "purchase",
            "priority": "normal",
            "is_read": false,
            "created_at": "2024-02-01T10:30:00.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 5
    }
}
```

### 3. Mark Notification as Read
**POST** `/notifications/{id}/mark-read` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "message": "Notification marked as read"
}
```

### 4. Mark All Notifications as Read
**POST** `/notifications/mark-all-read` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "message": "All notifications marked as read"
}
```

### 5. Get Unread Count
**GET** `/notifications/unread-count` (Authenticated)

Response (200 OK):
```json
{
    "success": true,
    "unread_count": 3
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "phone_number": ["The phone number field is required."]
    }
}
```

### 401 Unauthorized
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```

### 404 Not Found
```json
{
    "success": false,
    "message": "Resource not found"
}
```

### 500 Internal Server Error
```json
{
    "success": false,
    "message": "Server error occurred"
}
```

---

## Rate Limiting

- Authentication endpoints: 5 requests per minute
- Public endpoints: 60 requests per minute
- Authenticated endpoints: 120 requests per minute

---

## Environment Variables

Add these to your `.env` file:

```env
# Thawani Payment Gateway
THAWANI_URL=https://uatcheckout.thawani.om
THAWANI_SECRET_KEY=your_secret_key_here
THAWANI_PUBLISHABLE_KEY=your_publishable_key_here

# SMS Gateway (for OTP)
SMS_GATEWAY_URL=https://sms-provider.com/api
SMS_GATEWAY_KEY=your_sms_api_key
SMS_GATEWAY_SENDER=SOHAR_FEST
```