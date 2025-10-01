# UX Best Practices for Heritage Festival App

## Design Principles for Arabic/Bilingual Apps

### 1. Language & Localization

#### Bidirectional (BiDi) Design
- **Mirror all layouts** for RTL (Arabic) mode
- **Icons that must flip**: arrows, chevrons, navigation, back buttons, forward actions
- **Icons that stay**: symmetric symbols (star, heart, settings, search, plus, minus)
- **Text direction**: Always right-aligned for Arabic, left-aligned for English
- **Mixed content**: Keep numbers and Latin text left-to-right even in Arabic mode

#### Typography Guidelines
| Element | English | Arabic | Notes |
|---------|---------|--------|-------|
| Headers | 24-28px | 28-32px | Arabic needs 15% larger |
| Body | 16px | 18px | Increase for readability |
| Captions | 14px | 16px | Minimum readable size |
| Line Height | 1.4 | 1.6-1.8 | More breathing room |
| Font Weight | 400, 600, 700 | 400, 500, 700 | Different weight mapping |

**Recommended Fonts:**
- **Arabic**: Cairo, Tajawal, IBM Plex Sans Arabic, Dubai
- **English**: Roboto, Inter, SF Pro (iOS), Roboto (Android)

#### Cultural Considerations
1. **Date Formats**:
   - Gregorian: Oct 1, 2025
   - Hijri option: 8 Rabi' al-Awwal 1447
   - Both calendars side-by-side option

2. **Time Format**:
   - Support 12-hour (7:00 PM) and 24-hour (19:00)
   - Prayer times integration option

3. **Number Formats**:
   - Western: 0123456789
   - Arabic-Indic: ٠١٢٣٤٥٦٧٨٩
   - Allow user preference

4. **Currency**:
   - English: OMR 5.000
   - Arabic: ٥٫٠٠٠ ر.ع
   - Always show 3 decimal places for OMR

5. **Names**:
   - Support full Arabic names (can be very long)
   - No first/last name split (use full name field)
   - Allow special Arabic characters

---

## 2. Navigation Patterns

### Bottom Tab Navigation (Recommended)
**Pros:**
- One-thumb operation
- Always visible
- Standard pattern on iOS/Android
- Easy language switching (tabs stay in place)

**Layout:**
```
[Home] [Events] [Map] [Tickets] [More]
```

**RTL:**
```
[More] [Tickets] [Map] [Events] [Home]
```

### Side Drawer (Alternative)
**Use when:**
- More than 5 main sections
- Secondary features need access
- User profile is prominent

**Important:** Drawer opens from RIGHT in RTL mode

### Navigation Best Practices
1. **Breadcrumbs**: Show clear path in deep navigation
2. **Back Button**: Always visible, properly positioned (top-left EN, top-right AR)
3. **Gesture Navigation**: Support swipe back (from left in EN, right in AR)
4. **Deep Links**: Handle ticket URLs, event links, map coordinates

---

## 3. Heritage & Cultural Events Specific UX

### Event Discovery
1. **Multiple Discovery Methods**:
   - Browse by date (calendar view)
   - Browse by category (Cultural, Music, Food, etc.)
   - Browse by location (Heritage villages)
   - Today's events (quick access)
   - Personalized recommendations

2. **Smart Filters**:
   - Date range picker with Hijri option
   - Time of day (morning, afternoon, evening)
   - Price range (Free, Under 5 OMR, 5-10 OMR, etc.)
   - Distance from user
   - Family-friendly filter
   - Accessibility options

3. **Event Cards Information Hierarchy**:
   ```
   Priority 1: Image + Title
   Priority 2: Date & Time
   Priority 3: Location
   Priority 4: Price
   Priority 5: Availability
   ```

### Heritage Village Navigation
1. **Virtual Tours**:
   - 360° panoramic views
   - Hotspots for attractions
   - Audio guide option (AR/EN)
   - Offline download option

2. **Wayfinding**:
   - "You Are Here" markers
   - Turn-by-turn directions
   - Landmark references (next to main gate, etc.)
   - Estimated walking time

3. **Interactive Map Features**:
   - Layer toggles (events, food, facilities, parking)
   - Real-time crowd levels
   - Accessibility routes
   - Photo spot markers with example photos

### Ticket Experience
1. **Purchase Flow** (Minimize steps):
   ```
   Event Detail → Select Tickets → Review → Payment → Confirmation
   (3 taps maximum)
   ```

2. **Ticket Display**:
   - QR code scannable from any brightness
   - Offline access (cached locally)
   - Auto-brightness boost when viewing
   - Landscape mode for easier scanning
   - Multiple tickets in wallet view

3. **Smart Features**:
   - Add to Apple Wallet / Google Pay
   - Calendar integration with reminders
   - Directions link on ticket
   - Weather forecast for event day
   - Parking suggestions

### Restaurant Discovery
1. **Contextual Sorting**:
   - Near me (when on map)
   - Open now (during festival hours)
   - Highest rated (by default)
   - Price: low to high

2. **Quick Actions**:
   - One-tap call
   - One-tap directions
   - Save to favorites
   - Share with friends

3. **Filtering**:
   - Cuisine type (Omani, Arabic, Asian, Sweets, etc.)
   - Dietary options (Vegetarian, Halal certified, etc.)
   - Features (Outdoor seating, WiFi, Family section)
   - Price range

---

## 4. Accessibility (ذوي الاحتياجات الخاصة)

### Visual Accessibility
1. **Text Scaling**:
   - Support iOS Dynamic Type
   - Support Android Scaled Text
   - Test up to 200% scaling

2. **Color Contrast**:
   - WCAG AA minimum (4.5:1 for normal text)
   - WCAG AAA preferred (7:1)
   - Don't rely on color alone for information

3. **High Contrast Mode**:
   - Darker borders
   - Bolder text
   - Remove subtle gradients

### Screen Reader Support
1. **VoiceOver (iOS) / TalkBack (Android)**:
   - Meaningful labels for all buttons
   - Image alt text in both languages
   - Form field labels and hints
   - Proper heading hierarchy

2. **Reading Order**:
   - Logical tab order
   - RTL reading order in Arabic
   - Group related items

### Motor Accessibility
1. **Touch Targets**:
   - Minimum 44x44pt (iOS) / 48x48dp (Android)
   - Adequate spacing between targets (8pt/dp minimum)

2. **Gesture Alternatives**:
   - Swipe actions also available via buttons
   - Voice control support
   - Shake to undo option

### Cognitive Accessibility
1. **Simple Language**:
   - Clear, concise labels
   - Avoid jargon
   - Consistent terminology

2. **Progressive Disclosure**:
   - Show essential info first
   - "Read more" for details
   - Collapsible sections

3. **Error Prevention**:
   - Confirm before deleting
   - Undo options
   - Clear error messages with solutions

---

## 5. Performance & Loading

### Image Optimization
1. **Lazy Loading**:
   - Load images as user scrolls
   - Blur-up technique (tiny blur → full image)
   - Skeleton screens during load

2. **Image Formats**:
   - WebP with JPEG fallback
   - Appropriate resolutions for device
   - Thumbnail → Full image progression

3. **Caching Strategy**:
   ```
   Priority 1: User's tickets (offline)
   Priority 2: Today's events
   Priority 3: Favorited items
   Priority 4: Recently viewed
   ```

### API Performance
1. **Pagination**:
   - Load 20 items per page
   - Infinite scroll with "Load more" button
   - Cache previous pages

2. **Prefetching**:
   - Prefetch next page on scroll
   - Prefetch likely next screens
   - Prefetch images for visible cards

3. **Offline Support**:
   - Queue actions when offline
   - Sync when connected
   - Show offline indicator clearly

### Loading States
1. **Skeleton Screens** (preferred):
   - Show layout structure
   - Animated shimmer effect
   - Better perceived performance

2. **Spinners** (for quick loads):
   - Use sparingly
   - Show within 100ms
   - Hide after action completes

3. **Progress Indicators** (for long tasks):
   - Show percentage when possible
   - Provide status updates
   - Allow cancellation

---

## 6. Interaction Design

### Micro-interactions
1. **Button States**:
   - Default
   - Pressed (scale down slightly, darken)
   - Disabled (reduced opacity, no interaction)
   - Loading (spinner + disabled)

2. **Favorite Toggle**:
   - Heart outline → Filled heart
   - Scale up + color change animation
   - Haptic feedback on toggle

3. **Pull to Refresh**:
   - Standard on iOS/Android
   - Loading spinner
   - Success state briefly visible

4. **Add to Cart/Ticket Purchase**:
   - Button morphs to checkmark
   - Brief success animation
   - Navigate to cart/confirmation

### Gestures
1. **Standard Gestures**:
   - Swipe back (from edge)
   - Swipe to delete (on lists)
   - Pull to refresh
   - Pinch to zoom (maps, images)
   - Long press for context menu

2. **Custom Gestures** (optional):
   - Swipe between events (on detail)
   - Swipe between photos (galleries)
   - Shake to report issue

### Haptic Feedback
1. **When to Use**:
   - Toggle switches (light impact)
   - Confirmation actions (success notification)
   - Errors (error notification)
   - Selection in picker (selection)

2. **When NOT to Use**:
   - Every tap (annoying)
   - Navigation (unnecessary)
   - Typing (built-in)

---

## 7. Forms & Input

### Input Best Practices
1. **Keyboard Types**:
   - Phone number: number pad
   - Email: email keyboard
   - Search: search keyboard with "Search" button
   - URLs: URL keyboard

2. **Auto-complete**:
   - Phone numbers from contacts
   - Email addresses
   - Previous entries

3. **Validation**:
   - Real-time for format (phone, email)
   - On submit for required fields
   - Clear error messages below field
   - Mark invalid fields in red

4. **Arabic Input**:
   - Auto-switch keyboard to Arabic when in AR mode
   - Support Arabic name input
   - Proper Arabic autocorrect

### Phone Number Input
```
Best Practice:
┌──────────────────────────┐
│ Phone Number             │
│ ┌──────┐ ┌──────────────┐│
│ │ +968 ▼ │ 91234567     ││
│ └──────┘ └──────────────┘│
└──────────────────────────┘

Not:
[__________________]
(Forces user to remember country code)
```

### OTP Input
```
Best Practice:
┌───┐ ┌───┐ ┌───┐ ┌───┐
│ 1 │ │ 2 │ │ 3 │ │ 4 │
└───┘ └───┘ └───┘ └───┘
Auto-focus next field

Not:
[____]
(Hard to see what you typed)
```

---

## 8. Search & Discovery

### Search Best Practices
1. **Search Bar**:
   - Prominent placement (top of screen)
   - Placeholder text in user's language
   - Voice search icon (microphone)
   - Clear button (x) when typing

2. **Search Results**:
   - Show count: "23 results for 'heritage'"
   - Highlight matching text
   - Filter by type (Events, Villages, Restaurants)
   - Sort options

3. **Search Suggestions**:
   - Popular searches
   - Recent searches (user's history)
   - Autocomplete as typing
   - Trending searches

4. **Empty States**:
   - Friendly illustration
   - Helpful message
   - Suggestions: "Try searching for..."
   - Browse alternatives

### Filters
1. **Filter UI**:
   - Chips for active filters (dismissible)
   - Filter button with count badge
   - Bottom sheet for filter options
   - Apply/Reset buttons

2. **Filter Persistence**:
   - Remember filters in session
   - "Clear all" option
   - Show active count

---

## 9. Notifications & Alerts

### Push Notifications
1. **When to Send**:
   - Event starting in 1 hour (opt-in)
   - New events matching interests
   - Workshop registration confirmed
   - Ticket purchased successfully
   - Important announcements
   - Weather alerts for outdoor events

2. **When NOT to Send**:
   - Marketing every day (annoying)
   - During night hours (respect quiet time)
   - Repetitive reminders

3. **Notification Content**:
   - Bilingual (based on user preference)
   - Clear action (View Event, Show Ticket, etc.)
   - Rich content (image preview)
   - Deep link to relevant screen

### In-App Alerts
1. **Success Messages**:
   - Toast/Snackbar (brief, auto-dismiss)
   - Green color, checkmark icon
   - "Ticket purchased successfully"

2. **Error Messages**:
   - Modal for critical errors
   - Snackbar for non-critical
   - Red color, error icon
   - Specific solution: "Check your connection" not "Error 500"

3. **Confirmation Dialogs**:
   - Before destructive actions (delete, logout)
   - Clear choice (Cancel / Confirm)
   - Primary action on right (EN), left (AR)

---

## 10. Empty States & Error States

### Empty States
1. **No Content Yet**:
   ```
   [Illustration]

   No events in favorites
   Start exploring and save events you like

   [Browse Events Button]
   ```

2. **No Results**:
   ```
   [Illustration]

   No restaurants found nearby
   Try adjusting your filters or search area

   [Clear Filters] [Expand Search]
   ```

3. **First Time Use**:
   ```
   [Illustration]

   Welcome to Sohar Festival!
   Discover heritage, events, and culture

   [Get Started]
   ```

### Error States
1. **Network Error**:
   ```
   [Illustration]

   Connection Lost
   Check your internet connection

   [Retry]
   ```

2. **Server Error**:
   ```
   [Illustration]

   Something went wrong
   We're working to fix it

   [Try Again]
   ```

3. **Not Found**:
   ```
   [Illustration]

   Event not found
   This event may have ended

   [Browse Events]
   ```

---

## 11. Onboarding

### First Launch
1. **Splash Screen** (1-2 seconds):
   - App logo
   - Loading indicator
   - Check authentication

2. **Language Selection**:
   - Choice between English/Arabic
   - Large, clear buttons
   - Skip to system default option

3. **Onboarding Slides** (3 slides max):
   ```
   Slide 1: Discover Heritage
   [Illustration of heritage village]

   Slide 2: Book Tickets
   [Illustration of tickets]

   Slide 3: Explore Events
   [Illustration of festival]

   [Skip] [Next] → → [Get Started]
   ```

4. **Permissions Request** (contextual):
   - Location: "Find events near you"
   - Notifications: "Get event reminders"
   - Camera: "Scan QR codes" (when needed)

### Progressive Onboarding
- Don't ask for everything upfront
- Request permissions when feature is used
- Explain value before asking

---

## 12. Settings & Preferences

### Essential Settings
1. **Language**:
   - English / العربية toggle
   - Applies immediately to all text
   - Restarts app if needed

2. **Notifications**:
   - Event reminders (On/Off)
   - New events (On/Off)
   - Announcements (On/Off)
   - Workshop updates (On/Off)

3. **Calendar Preference**:
   - Gregorian (default)
   - Hijri
   - Both

4. **Number Format**:
   - Western numerals (123)
   - Arabic-Indic numerals (١٢٣)

5. **Theme**:
   - Light mode
   - Dark mode
   - System default

### Profile Settings
- Name
- Email
- Phone (read-only after verification)
- Preferred language
- Avatar/photo

---

## 13. Social Features (Optional)

### Sharing
1. **What to Share**:
   - Events (deep link)
   - Tickets (shareable link)
   - Heritage villages (link)
   - Photos from photo spots

2. **Share Sheet**:
   - Native iOS/Android share
   - Include image preview
   - Pre-filled text (bilingual)
   - Deep link for easy opening

### Social Proof
1. **Reviews & Ratings**:
   - Star ratings (1-5)
   - Text reviews (moderated)
   - Helpful vote system
   - Report inappropriate content

2. **Photo Gallery** (User-generated):
   - Upload photos at photo spots
   - Moderation queue
   - Credit to photographer
   - Photo contest (optional)

---

## 14. Gamification (Optional)

### Festival Passport
1. **Concept**:
   - Visit all heritage villages
   - Attend different event types
   - Try various workshops
   - Check in at photo spots

2. **Rewards**:
   - Digital badges
   - Discount codes
   - Priority booking
   - Certificate of completion

3. **Progress Tracking**:
   - Visual progress bar
   - Checklist of activities
   - Share achievements

### Challenges
- Photo challenge (take photos at all spots)
- Workshop master (complete 5 workshops)
- Heritage explorer (visit all villages)
- Foodie (eat at 10 restaurants)

---

## 15. Platform-Specific Considerations

### iOS Specific
1. **Design Language**:
   - SF Symbols for icons
   - iOS navigation patterns
   - Pull to refresh (standard)
   - Haptic feedback (Taptic Engine)

2. **Integrations**:
   - Apple Wallet (tickets)
   - Apple Pay (payments)
   - Face ID / Touch ID (auth)
   - Siri Shortcuts
   - iMessage stickers (optional)

3. **Widgets**:
   - Today's events widget
   - Next ticket widget
   - Festival schedule widget

### Android Specific
1. **Design Language**:
   - Material Design 3
   - Material You (dynamic theming)
   - Floating Action Button (where appropriate)

2. **Integrations**:
   - Google Wallet (tickets)
   - Google Pay (payments)
   - Fingerprint / Face unlock
   - Google Assistant actions

3. **Widgets**:
   - Home screen widgets
   - Lock screen widgets (Android 14+)

4. **Back Button**:
   - Respect system back button
   - Handle predictive back gesture (Android 14+)

---

## 16. Analytics & Optimization

### Key Metrics to Track
1. **User Engagement**:
   - Daily/Monthly active users
   - Session duration
   - Screens per session
   - Feature usage rates

2. **Conversion**:
   - Ticket purchase completion rate
   - Drop-off points in purchase flow
   - Workshop registration rate
   - Average order value

3. **Content Performance**:
   - Most viewed events
   - Most visited villages
   - Search queries
   - Popular filters

4. **Technical**:
   - Crash rate
   - API response times
   - Image load times
   - Battery usage

### A/B Testing Opportunities
- Onboarding flow variations
- CTA button text/colors
- Event card layouts
- Filter UI patterns
- Checkout flow steps

---

## 17. Security & Privacy

### Data Protection
1. **Personal Information**:
   - Encrypt sensitive data
   - Secure API communication (HTTPS)
   - Token-based authentication
   - Secure storage for tickets

2. **Payment Security**:
   - PCI DSS compliance
   - No card details stored locally
   - Payment gateway handles processing
   - Transaction encryption

3. **Privacy Policy**:
   - Clear, accessible policy
   - GDPR compliance (if applicable)
   - Data collection transparency
   - User consent

### User Control
1. **Data Management**:
   - Download my data
   - Delete my account
   - Clear cache/history
   - Opt-out of analytics

2. **Permissions**:
   - Request only when needed
   - Explain why needed
   - Work without (degraded experience)

---

## 18. Testing Checklist

### Functionality Testing
- [ ] All API endpoints working
- [ ] Payment flow end-to-end
- [ ] QR code scanning
- [ ] Offline ticket access
- [ ] Deep links working
- [ ] Push notifications received

### Language Testing
- [ ] All screens translated
- [ ] RTL layout correct
- [ ] Text not truncated
- [ ] Numbers formatted correctly
- [ ] Dates formatted correctly
- [ ] Mixed content displayed properly

### Device Testing
- [ ] iPhone SE (small screen)
- [ ] iPhone Pro Max (large screen)
- [ ] iPad (tablet layout)
- [ ] Android small (< 5")
- [ ] Android large (> 6")
- [ ] Foldable devices

### Accessibility Testing
- [ ] VoiceOver/TalkBack navigation
- [ ] Color contrast passes
- [ ] Text scaling up to 200%
- [ ] Touch targets minimum size
- [ ] Keyboard navigation (if applicable)

### Performance Testing
- [ ] App size < 50MB
- [ ] Cold start < 2 seconds
- [ ] Image loading smooth
- [ ] No memory leaks
- [ ] Battery drain acceptable
- [ ] Works on slow networks (3G)

### Edge Cases
- [ ] No internet connection
- [ ] Server error handling
- [ ] Empty states
- [ ] Very long names
- [ ] Special characters in input
- [ ] Expired tickets
- [ ] Sold out events
- [ ] Concurrent bookings

---

## Quick Reference: Do's and Don'ts

### ✅ DO
- Mirror layouts for RTL
- Use larger fonts for Arabic
- Provide offline access to tickets
- Show clear loading states
- Use familiar patterns
- Test on real devices
- Consider one-handed use
- Provide helpful empty states
- Explain errors clearly
- Respect user's language choice

### ❌ DON'T
- Don't just flip English text direction
- Don't use tiny touch targets
- Don't require internet for everything
- Don't show technical error codes
- Don't overwhelm with notifications
- Don't ask for all permissions upfront
- Don't hide important actions
- Don't make checkout too many steps
- Don't ignore edge cases
- Don't assume users know your app
