# Mobile App Screens with API Endpoints

Each screen lists the API endpoints needed to populate the screen data.

---

## 1. Splash Screen

**API Calls:**
- None (offline screen)
- Optionally check authentication token validity in background

---

## 2. Language Selection Screen

**API Calls:**
- None (offline screen, saves preference locally)

---

## 3. Phone Login Screen

**API Endpoints:**

### Send OTP
- **Endpoint:** `POST /api/v1/auth/send-otp`
- **Request:** [auth/send-otp-request.json](../api-examples/auth/send-otp-request.json)
- **Response:** [auth/send-otp-response.json](../api-examples/auth/send-otp-response.json)

```
POST /api/v1/auth/send-otp
Body: {
  "phone_number": "96812345678"
}
```

---

## 4. OTP Verification Screen

**API Endpoints:**

### Verify OTP
- **Endpoint:** `POST /api/v1/auth/verify-otp`
- **Request:** [auth/verify-otp-request.json](../api-examples/auth/verify-otp-request.json)
- **Response:** [auth/verify-otp-response.json](../api-examples/auth/verify-otp-response.json)

```
POST /api/v1/auth/verify-otp
Body: {
  "phone_number": "96812345678",
  "otp_code": "123456",
  "device_token": "optional_firebase_token"
}
Response: Returns user data + auth token
```

### Resend OTP
- **Endpoint:** `POST /api/v1/auth/send-otp` (same as login screen)

---

## 5. Home Screen

**API Endpoints:**

### Featured Events Banner
- **Endpoint:** `GET /api/v1/events/featured`
- **Response:** [events/list-events-response.json](../api-examples/events/list-events-response.json)

```
GET /api/v1/events/featured
Returns: Featured events for carousel
```

### Today's Events
- **Endpoint:** `GET /api/v1/events/today`

```
GET /api/v1/events/today
Returns: Events active today (horizontal scroll)
```

### Announcements
- **Endpoint:** `GET /api/v1/announcements/active`
- **Response:** [announcements/list-response.json](../api-examples/announcements/list-response.json)

```
GET /api/v1/announcements/active
Returns: Pinned and active announcements
```

### App Settings
- **Endpoint:** `GET /api/v1/app-settings`
- **Response:** [app-settings/settings-response.json](../api-examples/app-settings/settings-response.json)

```
GET /api/v1/app-settings
Returns: App configuration, maintenance mode, feature flags
```

### Unread Notifications Count (if logged in)
- **Endpoint:** `GET /api/v1/notifications/unread-count` 🔒

```
GET /api/v1/notifications/unread-count
Headers: Authorization: Bearer {token}
Returns: { "unread_count": 3 }
```

---

## 6. Events List Screen

**API Endpoints:**

### List All Events
- **Endpoint:** `GET /api/v1/events`
- **Response:** [events/list-events-response.json](../api-examples/events/list-events-response.json)

```
GET /api/v1/events?page=1&search=heritage&status=active&date=2025-10-01
Query Parameters:
  - page: Pagination page number
  - search: Search term
  - status: active|inactive
  - date: Filter by specific date (YYYY-MM-DD)
Returns: Paginated event list
```

### Filter by Category
- **Endpoint:** `GET /api/v1/events/category/{categoryId}`

```
GET /api/v1/events/category/1
Returns: Events in specific category (1=Cultural, 2=Music, etc.)
```

### Get My Favorites (if logged in)
- **Endpoint:** `GET /api/v1/events/favorites` 🔒

```
GET /api/v1/events/favorites
Headers: Authorization: Bearer {token}
Returns: User's favorited events
```

---

## 7. Event Detail Screen

**API Endpoints:**

### Get Event Details
- **Endpoint:** `GET /api/v1/events/{id}`

```
GET /api/v1/events/1
Returns: Full event details including category, tags, tickets
```

### Toggle Favorite (if logged in)
- **Endpoint:** `POST /api/v1/events/{id}/favorite` 🔒

```
POST /api/v1/events/1/favorite
Headers: Authorization: Bearer {token}
Returns: { "is_favorite": true, "message": "Added to favorites" }
```

### Get Similar Events
- **Endpoint:** `GET /api/v1/events/category/{categoryId}?limit=5`

```
GET /api/v1/events/category/1?limit=5
Returns: Similar events in same category (excluding current)
```

---

## 8. Map Screen

**API Endpoints:**

### Get All Map Locations
- **Endpoint:** `GET /api/v1/map-locations`

```
GET /api/v1/map-locations
Returns: All POIs with coordinates (heritage villages, restaurants, facilities, parking)
```

### Filter by Category
- **Endpoint:** `GET /api/v1/map-locations/category/{category}`

```
GET /api/v1/map-locations/category/entertainment
GET /api/v1/map-locations/category/food
GET /api/v1/map-locations/category/facilities
GET /api/v1/map-locations/category/parking
Returns: Filtered locations by type
```

### Get Heritage Villages (for village markers)
- **Endpoint:** `GET /api/v1/heritage-villages`

```
GET /api/v1/heritage-villages
Returns: Heritage villages with coordinates
```

### Get Restaurants (for restaurant markers)
- **Endpoint:** `GET /api/v1/restaurants`
- **Response:** [restaurants/list-restaurants-response.json](../api-examples/restaurants/list-restaurants-response.json)

```
GET /api/v1/restaurants
Returns: All restaurants with coordinates
```

### Get Photo Spots (for photo markers)
- **Endpoint:** `GET /api/v1/photo-spots`

```
GET /api/v1/photo-spots
Returns: Photo spots with coordinates
```

### Get Emergency Contacts (for emergency markers)
- **Endpoint:** `GET /api/v1/emergency-contacts`
- **Response:** [emergency-contacts/list-response.json](../api-examples/emergency-contacts/list-response.json)

```
GET /api/v1/emergency-contacts
Returns: Emergency services and first aid stations
```

---

## 9. My Tickets Screen

**API Endpoints:**

### Get My Tickets 🔒
- **Endpoint:** `GET /api/v1/tickets/my-tickets`

```
GET /api/v1/tickets/my-tickets
Headers: Authorization: Bearer {token}
Query Parameters:
  - status: active|past (optional)
Returns: User's purchased tickets with event details
```

### Get Ticket Details 🔒
- **Endpoint:** `GET /api/v1/tickets/{id}`

```
GET /api/v1/tickets/123
Headers: Authorization: Bearer {token}
Returns: Single ticket details with QR data
```

---

## 10. Full Ticket View (Scannable)

**API Endpoints:**

### Get Ticket Details 🔒
- **Endpoint:** `GET /api/v1/tickets/{id}`

```
GET /api/v1/tickets/123
Headers: Authorization: Bearer {token}
Returns: Complete ticket info + QR code data
```

### Get Ticket QR Code 🔒
- **Endpoint:** `GET /api/v1/tickets/{id}/qr-code`

```
GET /api/v1/tickets/123/qr-code
Headers: Authorization: Bearer {token}
Returns: QR code image or data
```

---

## 11. Heritage Villages List

**API Endpoints:**

### List All Heritage Villages
- **Endpoint:** `GET /api/v1/heritage-villages`

```
GET /api/v1/heritage-villages
Query Parameters:
  - type: maritime|desert|mountain (optional filter)
Returns: All heritage villages with details
```

---

## 12. Heritage Village Detail

**API Endpoints:**

### Get Village Details
- **Endpoint:** `GET /api/v1/heritage-villages/{id}`

```
GET /api/v1/heritage-villages/1
Returns: Complete village information
```

### Get Village Attractions
- **Endpoint:** `GET /api/v1/heritage-villages/{id}/attractions`

```
GET /api/v1/heritage-villages/1/attractions
Returns: Attractions in this village
```

### Get Village Activities
- **Endpoint:** `GET /api/v1/traditional-activities?heritage_village_id={id}`

```
GET /api/v1/traditional-activities?heritage_village_id=1
Returns: Traditional activities in this village
```

### Get Village Workshops
- **Endpoint:** `GET /api/v1/cultural-workshops?heritage_village_id={id}`

```
GET /api/v1/cultural-workshops?heritage_village_id=1
Returns: Workshops available in this village
```

### Get Village Craft Demonstrations
- **Endpoint:** `GET /api/v1/craft-demonstrations?heritage_village_id={id}`

```
GET /api/v1/craft-demonstrations?heritage_village_id=1
Returns: Craft demonstrations in this village
```

### Get Village Photo Spots
- **Endpoint:** `GET /api/v1/photo-spots?heritage_village_id={id}`

```
GET /api/v1/photo-spots?heritage_village_id=1
Returns: Photo spots in this village
```

---

## 13. Restaurants List

**API Endpoints:**

### List All Restaurants
- **Endpoint:** `GET /api/v1/restaurants`
- **Response:** [restaurants/list-restaurants-response.json](../api-examples/restaurants/list-restaurants-response.json)

```
GET /api/v1/restaurants?page=1
Query Parameters:
  - cuisine: Filter by cuisine type
  - price_range: $, $$, $$$
  - is_featured: true|false
  - sort_by: rating|distance|price
Returns: Paginated restaurant list
```

### Get Restaurants Open Now
- **Endpoint:** `GET /api/v1/restaurants/open-now`

```
GET /api/v1/restaurants/open-now
Returns: Currently open restaurants
```

### Search Restaurants
- **Endpoint:** `GET /api/v1/restaurants/search?q={query}`

```
GET /api/v1/restaurants/search?q=omani
Returns: Search results
```

### Get My Favorite Restaurants (if logged in) 🔒
- **Endpoint:** `GET /api/v1/restaurants/favorites`

```
GET /api/v1/restaurants/favorites
Headers: Authorization: Bearer {token}
Returns: User's favorite restaurants
```

---

## 14. Restaurant Detail

**API Endpoints:**

### Get Restaurant Details
- **Endpoint:** `GET /api/v1/restaurants/{id}`

```
GET /api/v1/restaurants/1
Returns: Complete restaurant info including:
  - Basic info (name, description, cuisine)
  - Opening hours
  - Location & coordinates
  - Rating & reviews
  - Images
  - Features
```

### Toggle Favorite (if logged in) 🔒
- **Endpoint:** `POST /api/v1/restaurants/{id}/favorite`

```
POST /api/v1/restaurants/1/favorite
Headers: Authorization: Bearer {token}
Returns: { "is_favorite": true }
```

---

## 15. Workshops & Activities

**API Endpoints:**

### List All Workshops
- **Endpoint:** `GET /api/v1/cultural-workshops`

```
GET /api/v1/cultural-workshops?page=1
Query Parameters:
  - skill_level: beginner|intermediate|advanced
  - heritage_village_id: Filter by village
Returns: Paginated workshop list
```

### Get Available Workshops
- **Endpoint:** `GET /api/v1/cultural-workshops/available`

```
GET /api/v1/cultural-workshops/available
Returns: Workshops with available spots
```

### List Traditional Activities
- **Endpoint:** `GET /api/v1/traditional-activities`

```
GET /api/v1/traditional-activities
Returns: All traditional activities
```

### Get Interactive Activities
- **Endpoint:** `GET /api/v1/traditional-activities/interactive`

```
GET /api/v1/traditional-activities/interactive
Returns: Activities marked as interactive
```

### List Craft Demonstrations
- **Endpoint:** `GET /api/v1/craft-demonstrations`

```
GET /api/v1/craft-demonstrations
Returns: All craft demonstrations
```

### Get Live Demonstrations
- **Endpoint:** `GET /api/v1/craft-demonstrations/live`

```
GET /api/v1/craft-demonstrations/live
Returns: Currently ongoing demonstrations
```

---

## 16. Workshop Detail & Registration

**API Endpoints:**

### Get Workshop Details
- **Endpoint:** `GET /api/v1/cultural-workshops/{id}`

```
GET /api/v1/cultural-workshops/1
Returns: Complete workshop details including:
  - Title, description
  - Instructor info
  - Duration, skill level
  - Price, max participants
  - Available sessions/schedules
  - Reviews/ratings
```

### Register for Workshop 🔒
- **Endpoint:** `POST /api/v1/cultural-workshops/{id}/register`

```
POST /api/v1/cultural-workshops/1/register
Headers: Authorization: Bearer {token}
Body: {
  "session_id": 123,  // or "schedule_id"
  "participant_count": 1
}
Returns: Registration confirmation
```

### Cancel Registration 🔒
- **Endpoint:** `DELETE /api/v1/cultural-workshops/{id}/cancel`

```
DELETE /api/v1/cultural-workshops/1/cancel
Headers: Authorization: Bearer {token}
Returns: Cancellation confirmation
```

### Get My Workshop Registrations 🔒
- **Endpoint:** `GET /api/v1/cultural-workshops/my-registrations`

```
GET /api/v1/cultural-workshops/my-registrations
Headers: Authorization: Bearer {token}
Returns: User's workshop registrations
```

---

## 17. More/Profile Tab

**API Endpoints:**

### Get User Profile 🔒
- **Endpoint:** `GET /api/v1/auth/profile`
- **Response:** [auth/profile-response.json](../api-examples/auth/profile-response.json)

```
GET /api/v1/auth/profile
Headers: Authorization: Bearer {token}
Returns: User profile data
```

### Get Unread Notification Count 🔒
- **Endpoint:** `GET /api/v1/notifications/unread-count`

```
GET /api/v1/notifications/unread-count
Headers: Authorization: Bearer {token}
Returns: { "unread_count": 3 }
```

### Update Profile 🔒
- **Endpoint:** `POST /api/v1/auth/update-profile`
- **Request:** [auth/update-profile-request.json](../api-examples/auth/update-profile-request.json)
- **Response:** [auth/update-profile-response.json](../api-examples/auth/update-profile-response.json)

```
POST /api/v1/auth/update-profile
Headers: Authorization: Bearer {token}
Body: {
  "name": "New Name",
  "email": "new@email.com",
  "preferred_language": "ar"
}
```

### Logout 🔒
- **Endpoint:** `POST /api/v1/auth/logout`

```
POST /api/v1/auth/logout
Headers: Authorization: Bearer {token}
Returns: { "success": true, "message": "Logged out successfully" }
```

---

## 18. Notifications Screen

**API Endpoints:**

### Get My Notifications 🔒
- **Endpoint:** `GET /api/v1/notifications/my-notifications`
- **Response:** [notifications/my-notifications-response.json](../api-examples/notifications/my-notifications-response.json)

```
GET /api/v1/notifications/my-notifications?page=1
Headers: Authorization: Bearer {token}
Query Parameters:
  - type: announcement|reminder|update (optional filter)
  - is_read: true|false (optional)
Returns: Paginated notification list
```

### Get Public Notifications
- **Endpoint:** `GET /api/v1/notifications/public`

```
GET /api/v1/notifications/public
Returns: Public announcements (no auth required)
```

### Mark Notification as Read 🔒
- **Endpoint:** `POST /api/v1/notifications/{id}/mark-read`

```
POST /api/v1/notifications/123/mark-read
Headers: Authorization: Bearer {token}
Returns: { "success": true }
```

### Mark All as Read 🔒
- **Endpoint:** `POST /api/v1/notifications/mark-all-read`

```
POST /api/v1/notifications/mark-all-read
Headers: Authorization: Bearer {token}
Returns: { "success": true }
```

---

## 19. Emergency Contacts Screen

**API Endpoints:**

### Get Emergency Contacts
- **Endpoint:** `GET /api/v1/emergency-contacts`
- **Response:** [emergency-contacts/list-response.json](../api-examples/emergency-contacts/list-response.json)

```
GET /api/v1/emergency-contacts
Returns: All emergency services and contacts
```

### Get First Aid Stations (Map Locations)
- **Endpoint:** `GET /api/v1/map-locations/facilities`

```
GET /api/v1/map-locations/facilities
Returns: Facilities including first aid stations with coordinates
```

---

## 20. Payment/Checkout Screen

**API Endpoints:**

### Check Ticket Availability 🔒
- **Endpoint:** `POST /api/v1/tickets/check-availability`

```
POST /api/v1/tickets/check-availability
Headers: Authorization: Bearer {token}
Body: {
  "ticket_type": "standard",
  "quantity": 2,
  "date": "2025-10-15"
}
Returns: Availability status and pricing
```

### Purchase Tickets 🔒
- **Endpoint:** `POST /api/v1/tickets/purchase`
- **Request:** [tickets/purchase-request.json](../api-examples/tickets/purchase-request.json)

```
POST /api/v1/tickets/purchase
Headers: Authorization: Bearer {token}
Body: {
  "event_id": 1,
  "ticket_type": "standard",
  "quantity": 2,
  "payment_method": "card"
}
Returns: Redirect to payment or confirmation
```

### Initialize Payment 🔒
- **Endpoint:** `POST /api/v1/payments/initialize`
- **Request:** [payments/initialize-request.json](../api-examples/payments/initialize-request.json)

```
POST /api/v1/payments/initialize
Headers: Authorization: Bearer {token}
Body: {
  "ticket_id": 1,
  "amount": 10.000,
  "payment_method": "card"
}
Returns: Payment session ID for gateway
```

### Confirm Payment 🔒
- **Endpoint:** `POST /api/v1/payments/confirm`

```
POST /api/v1/payments/confirm
Headers: Authorization: Bearer {token}
Body: {
  "session_id": "payment_session_id",
  "payment_id": "payment_id_from_gateway"
}
Returns: Payment confirmation + ticket details
```

### Check Payment Status 🔒
- **Endpoint:** `GET /api/v1/payments/status/{sessionId}`

```
GET /api/v1/payments/status/session_123
Headers: Authorization: Bearer {token}
Returns: Current payment status
```

---

## 21. My Favorites Screen 🔒

**API Endpoints:**

### Get Favorite Events 🔒
- **Endpoint:** `GET /api/v1/events/favorites`

```
GET /api/v1/events/favorites
Headers: Authorization: Bearer {token}
Returns: User's favorited events
```

### Get Favorite Restaurants 🔒
- **Endpoint:** `GET /api/v1/restaurants/favorites`

```
GET /api/v1/restaurants/favorites
Headers: Authorization: Bearer {token}
Returns: User's favorited restaurants
```

---

## 22. Payment History Screen 🔒

**API Endpoints:**

### Get My Transactions 🔒
- **Endpoint:** `GET /api/v1/payments/my-transactions`

```
GET /api/v1/payments/my-transactions?page=1
Headers: Authorization: Bearer {token}
Returns: User's payment transaction history
```

---

## 23. Photo Spots Screen

**API Endpoints:**

### List All Photo Spots
- **Endpoint:** `GET /api/v1/photo-spots`

```
GET /api/v1/photo-spots
Query Parameters:
  - heritage_village_id: Filter by village (optional)
Returns: All photo spots with images and best timing
```

### Get Photo Spot Details
- **Endpoint:** `GET /api/v1/photo-spots/{id}`

```
GET /api/v1/photo-spots/1
Returns: Photo spot details with sample photos
```

---

## 24. Search Screen (Universal)

**API Endpoints:**

### Search Everything
Multiple endpoints called in parallel:

```
GET /api/v1/events?search={query}
GET /api/v1/restaurants/search?q={query}
GET /api/v1/heritage-villages?search={query}
GET /api/v1/cultural-workshops?search={query}

Returns: Combined results from all endpoints
```

---

## 25. Ticket Pricing (Public)

**API Endpoints:**

### Get All Ticket Pricing
- **Endpoint:** `GET /api/v1/tickets/pricing`
- **Response:** [tickets/pricing-response.json](../api-examples/tickets/pricing-response.json)

```
GET /api/v1/tickets/pricing
Returns: All ticket pricing options for active events
```

### Get Available Tickets 🔒
- **Endpoint:** `GET /api/v1/tickets/available`

```
GET /api/v1/tickets/available
Headers: Authorization: Bearer {token}
Returns: Tickets available for purchase with current availability
```

---

## API Authentication

🔒 = Requires authentication (Bearer token)

**Authentication Header:**
```
Authorization: Bearer {token_from_login}
Content-Type: application/json
Accept: application/json
```

**Base URL:**
- Development: `http://localhost:8000/api/v1`
- Production: `https://your-domain.com/api/v1`

---

## Common Query Parameters

Most list endpoints support:
- `page` - Pagination (default: 1)
- `per_page` - Items per page (default: 20)
- `search` - Search query string
- `sort_by` - Sort field
- `sort_order` - asc|desc

---

## Error Responses

All endpoints return errors in this format:
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": ["Validation error"]
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized (missing/invalid token)
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Offline Support

The following data should be cached for offline access:
- User's tickets (QR codes)
- User profile
- Last fetched events
- Heritage village details
- Map locations
- Emergency contacts

---

## Real-time Features (Optional)

Consider WebSocket/Pusher for:
- Live ticket availability updates
- Real-time event capacity warnings
- Instant notifications
- Live workshop availability

---

For complete API documentation, see [API_DOCUMENTATION.md](../API_DOCUMENTATION.md)
