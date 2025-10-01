# Sohar Festival Mobile App Structure

## App Architecture

### Technology Stack Recommendations
- **Framework**: React Native / Flutter (for cross-platform)
- **State Management**: Redux / Provider
- **Navigation**: React Navigation / Flutter Navigator
- **RTL Support**: Built-in RTL for Arabic language
- **Maps**: Google Maps / Apple Maps
- **Payment**: Integration with Omani payment gateways
- **Push Notifications**: Firebase Cloud Messaging

---

## Navigation Structure

```
├── Splash Screen
├── Onboarding (First time users)
├── Language Selection (AR/EN)
└── Main App
    ├── Bottom Tab Navigation
    │   ├── Home
    │   ├── Events
    │   ├── Map
    │   ├── Tickets
    │   └── More
    └── Side Drawer (Alternative)
        ├── Profile
        ├── My Tickets
        ├── Favorites
        ├── Notifications
        ├── Emergency Contacts
        ├── Language Settings
        └── About
```

---

## Screen Flow & Layout

### 1. Authentication Flow
```
Language Selection Screen
    ↓
Phone Number Entry Screen
    ↓
OTP Verification Screen
    ↓
Profile Completion Screen (Optional)
    ↓
Home Screen
```

### 2. Home Tab
**Layout Components:**
- **Header**: Festival logo, notification bell (badge), language toggle
- **Hero Banner**: Carousel of featured events/announcements
- **Quick Actions Grid** (2x2):
  - Buy Tickets
  - Heritage Villages
  - Restaurants
  - Emergency
- **Today's Events** (Horizontal scroll)
- **Announcements** (Pinned items)
- **Heritage Highlights** (Card slider)
- **Quick Links**: Map, Schedule, Workshops

### 3. Events Tab
**Layout Components:**
- **Search Bar**: With voice search icon
- **Filter Chips** (Horizontal scroll):
  - All
  - Today
  - Upcoming
  - Cultural
  - Music
  - Food
  - Sports
  - Kids
  - Arts
- **Date Picker**: Calendar icon to select specific date
- **Event Cards** (Vertical list):
  - Large image
  - Category badge
  - Title (AR/EN)
  - Date & Time with Arabic calendar support
  - Location with map pin
  - Price
  - Favorite heart icon
  - Available tickets indicator

**Event Detail Screen:**
- Hero image with back button & share button
- Title (large, bilingual ready)
- Category & tags chips
- Date/Time with calendar add button
- Location with "Get Directions" button
- Interactive map preview
- Description (expandable)
- Ticket types & pricing
- "Buy Tickets" CTA button (sticky at bottom)
- Similar events section

### 4. Map Tab
**Layout Components:**
- **Interactive Map** (Full screen):
  - Custom markers for different categories
  - User location
  - Heritage villages
  - Restaurants
  - Photo spots
  - Parking
  - Facilities (restrooms, prayer rooms)
  - Emergency points
- **Bottom Sheet** (Draggable):
  - Category filters
  - Location details on marker tap
  - Get directions button
- **Search Bar** (Top overlay)
- **My Location Button** (Floating)
- **Layer Toggle**: Satellite/Map view

### 5. Tickets Tab
**Layout Components:**
- **Tabs**:
  - Available Tickets
  - My Tickets
  - Past Tickets
- **My Tickets View**:
  - Active ticket cards with QR code preview
  - Ticket details (event, date, type)
  - "View Full Ticket" button
  - Filter by upcoming/past
- **Ticket Detail Screen**:
  - Large QR code (scannable)
  - Ticket number
  - Event details
  - Date & time
  - Location
  - Instructions (AR/EN)
  - Add to calendar
  - Download/Share options

### 6. More Tab
**Layout Components:**
- **Profile Card** (Top):
  - Avatar/photo
  - Name
  - Phone number
  - Edit profile button
- **Menu List**:
  - My Favorites (heart icon)
  - Notifications (bell icon with badge)
  - Heritage Villages (building icon)
  - Workshops & Activities (workshop icon)
  - Restaurants (food icon)
  - Photo Spots (camera icon)
  - Emergency Contacts (phone icon)
  - Payment History (wallet icon)
  - Language (globe icon)
  - App Settings (gear icon)
  - Help & Support (question icon)
  - About Festival (info icon)
  - Logout (exit icon)

### 7. Heritage Villages Section
**Heritage Villages List Screen:**
- Card layout with:
  - Cover image
  - Village name (AR/EN)
  - Type badge (Maritime, Desert, Mountain)
  - Opening hours
  - "Explore" button
  - Virtual tour icon

**Heritage Village Detail Screen:**
- **Tabs**:
  - Overview
  - Attractions
  - Activities
  - Workshops
  - Photo Spots
  - Crafts
- Image gallery/carousel
- Description
- Opening hours
- Virtual tour button
- Map location
- Related content sections

### 8. Restaurants Section
**Restaurant List Screen:**
- **Filter Bar**:
  - Cuisine type chips
  - Price range
  - Open now toggle
  - Sort by (rating, distance, price)
- **Restaurant Cards**:
  - Image
  - Name (AR/EN)
  - Cuisine type
  - Price range indicators (OMR symbols)
  - Rating stars + review count
  - Distance from user
  - Open/Closed status
  - Favorite icon

**Restaurant Detail Screen:**
- Hero image gallery
- Name & cuisine
- Rating & reviews
- Price range
- Opening hours (Today's hours highlighted)
- Phone number with call button
- Location with directions button
- Description
- Features (WiFi, outdoor seating, etc.)
- Map preview

### 9. Workshops & Activities
**Workshop List Screen:**
- **Filter Chips**:
  - All
  - Available
  - Beginner
  - Intermediate
  - Advanced
- **Workshop Cards**:
  - Image
  - Title (AR/EN)
  - Duration
  - Instructor name
  - Price
  - Skill level badge
  - Available spots
  - "Register" button

**Workshop Detail Screen:**
- Hero image
- Title
- Instructor bio with photo
- Description
- Duration
- Max participants
- Skill level
- Price
- Schedule/time slots
- Location
- What to bring/materials
- "Register Now" button
- Reviews/ratings

### 10. Notifications Screen
**Layout:**
- **Tabs**:
  - All
  - Announcements
  - Reminders
  - Updates
- Notification cards with:
  - Icon by type
  - Title
  - Message preview
  - Timestamp (relative in AR/EN)
  - Read/unread indicator
- "Mark all as read" button
- Pull to refresh

### 11. Emergency Screen
**Layout:**
- **Emergency Call Cards** (Large, prominent):
  - Police (999)
  - Ambulance
  - Fire
  - Festival Security
- Quick dial buttons
- Location sharing option
- **First Aid Stations** (Map view):
  - Nearest locations
  - Directions
- **Health Tips** (Accordion):
  - Heat safety
  - Hydration
  - Lost child procedure

---

## Arabic/RTL Considerations

### Design Guidelines for Arabic Support

1. **Layout Mirroring**:
   - All UI elements flip horizontally in RTL mode
   - Navigation arrows reverse
   - Text alignment: right for Arabic, left for English
   - Progress indicators move right-to-left

2. **Typography**:
   - **Arabic Fonts**:
     - Primary: Cairo, Tajawal, or IBM Plex Sans Arabic
     - Headers: Bold weight (700)
     - Body: Regular (400) and Medium (500)
   - **Font Sizes** (Arabic needs ~10% larger):
     - Headers: 24-32px
     - Subheaders: 18-22px
     - Body: 16-18px
     - Captions: 14px
   - Increased line height for Arabic (1.6-1.8)

3. **Icons**:
   - Use universal symbols
   - Directional icons must flip (arrows, chevrons)
   - Keep symmetric icons unchanged (star, heart, etc.)

4. **Numbers**:
   - Support both Arabic-Indic (٠١٢٣) and Western (0123) numerals
   - Currency formatting: "5.000 ر.ع" for Arabic
   - Date formats: Hijri calendar support option

5. **Spacing**:
   - Adequate padding for Arabic text (15-20% more)
   - Maintain visual hierarchy in both languages

---

## Color Scheme (Heritage Theme)

### Primary Colors
- **Primary**: `#1A5F7A` (Deep Ocean Blue - Maritime heritage)
- **Secondary**: `#D4AF37` (Gold - Omani heritage)
- **Accent**: `#C85C5C` (Terracotta - Traditional pottery)

### Supporting Colors
- **Background**: `#F8F9FA` (Light neutral)
- **Surface**: `#FFFFFF` (White)
- **Text Primary**: `#212529` (Dark)
- **Text Secondary**: `#6C757D` (Gray)
- **Success**: `#28A745` (Green)
- **Warning**: `#FFC107` (Amber)
- **Error**: `#DC3545` (Red)
- **Info**: `#17A2B8` (Cyan)

### Gradient Overlays
- Hero sections: Dark overlay (40% opacity) on images for text readability

---

## Component Patterns

### 1. Event Card Component
```
┌─────────────────────────────────┐
│   [Image 16:9]                  │
│   Category Badge                │
├─────────────────────────────────┤
│ Title (2 lines max)             │
│ 📅 Date & Time | 📍 Location    │
│ 💰 5.000 OMR    [♡ Favorite]    │
└─────────────────────────────────┘
```

### 2. Heritage Village Card
```
┌─────────────────────────────────┐
│   [Cover Image]                 │
│   Type Badge                    │
├─────────────────────────────────┤
│ Village Name                    │
│ 🕐 Opening Hours               │
│ [Explore] [🔄 Virtual Tour]    │
└─────────────────────────────────┘
```

### 3. Ticket Card (QR Code)
```
┌─────────────────────────────────┐
│  Event Title                    │
│  📅 Date | 🕐 Time              │
│  ┌──────────────┐               │
│  │  [QR CODE]   │               │
│  └──────────────┘               │
│  Ticket #123456                 │
│  [View Details]                 │
└─────────────────────────────────┘
```

### 4. Restaurant Card
```
┌─────────────────────────────────┐
│ [Image] Restaurant Name      [♡]│
│         Cuisine Type             │
│         ⭐⭐⭐⭐⭐ 4.5 (120)      │
│         💰💰 | 🕐 Open Now       │
└─────────────────────────────────┘
```

---

## UX Best Practices for Events & Heritage

### 1. **First-Time Experience**
- 3-slide onboarding with illustrations
- Language selection prominent
- Skip option available
- Auto-detect device language

### 2. **Progressive Information**
- Load essential info first
- Lazy load images
- Offline mode for tickets and schedules
- Cache frequently accessed data

### 3. **Contextual Actions**
- One-tap ticket purchase flow
- Quick add to calendar
- Share events easily
- Save favorites offline

### 4. **Cultural Sensitivity**
- Prayer time reminders option
- Hijri calendar option
- Modest imagery selection
- Family-friendly content filters

### 5. **Accessibility**
- High contrast mode
- Large text option
- Voice-over support (AR/EN)
- Haptic feedback
- Color-blind friendly

### 6. **Smart Features**
- Personalized event recommendations
- Weather-based suggestions
- Crowd level indicators
- Parking availability
- Queue time estimates

### 7. **Gamification** (Optional)
- Festival passport (visit all villages)
- Photo challenge badges
- Workshop completion certificates
- Share achievements on social

---

## Key User Flows

### Flow 1: Ticket Purchase
```
Home → Featured Event → Event Detail → Select Tickets →
Review Order → Payment → Confirmation → My Tickets (QR Code)
```

### Flow 2: Heritage Village Exploration
```
Map → Village Marker → Village Detail → Attractions Tab →
Activity Detail → Register for Workshop → Confirmation
```

### Flow 3: Restaurant Discovery
```
Home → Restaurants → Filter (Open Now) → Restaurant Detail →
Call / Get Directions → Add to Favorites
```

### Flow 4: Emergency Help
```
More → Emergency Contacts → Select Service →
[Call] or [Show First Aid Map]
```

---

## Performance Optimization

1. **Image Optimization**:
   - WebP format with fallbacks
   - Progressive loading
   - Thumbnail → Full image
   - Caching strategy

2. **Offline Capability**:
   - Downloaded tickets work offline
   - Cached maps
   - Saved favorites accessible offline
   - Sync when connection restored

3. **Battery Optimization**:
   - Location updates only when needed
   - Background task management
   - Dark mode option

---

## Platform-Specific Considerations

### iOS
- Apple Pay integration
- Face ID/Touch ID for auth
- Apple Wallet for tickets
- iMessage stickers (Festival theme)
- Siri shortcuts

### Android
- Google Pay integration
- Fingerprint auth
- Google Wallet for tickets
- Home screen widgets
- Share shortcuts

---

## Analytics & Tracking

### Key Metrics
- User engagement by section
- Popular events/villages
- Ticket conversion rate
- Average session duration
- Feature usage heatmap
- Language preference distribution
- Search queries
- Error tracking

---

## API Integration Reference

For detailed API endpoints mapped to each screen, see:
- [SCREENS_WITH_ENDPOINTS.md](SCREENS_WITH_ENDPOINTS.md) - Complete mapping of screens to API calls
- [../API_DOCUMENTATION.md](../API_DOCUMENTATION.md) - Full API reference
- [../api-examples/](../api-examples/) - Request/response examples
