# QR Validation API Implementation

## Implementation Summary

The QR validation API has been successfully implemented according to the mobile app requirements. This document provides an overview of the implementation and testing instructions.

---

## What Was Implemented

### 1. Database Changes

**Migration**: `2025_10_25_061751_add_qr_validation_fields_to_tickets_table.php`

Added the following fields to the `tickets` table:
- `validated_by` (string, nullable) - Staff member who validated the ticket
- `check_in_count` (integer, default: 0) - Number of times ticket has been scanned
- `max_check_ins` (integer, default: 1) - Maximum allowed check-ins
- `validation_location` (string, nullable) - Entry point/gate where validated
- `validation_notes` (text, nullable) - Additional notes about validation

**Note**: Fields `ticket_number`, `quantity`, `booking_date`, and `transaction_id` already existed in the table.

---

### 2. Model Updates

**File**: `app/Models/Ticket.php`

Added methods:
- `isValid()` - Check if ticket is valid for entry
- `getValidationStatus()` - Get detailed validation status with error messages
- `markAsUsed()` - Mark ticket as validated/used
- `generateTicketNumber()` - Auto-generate unique ticket numbers (format: TKT-YYYY-NNNNNN)
- Boot method to auto-generate ticket numbers and QR codes on creation

Added scopes:
- `scopeValid()` - Query only valid tickets
- `scopeUsed()` - Query only used tickets

---

### 3. API Endpoints

**Base URL**: `https://north-batinah-portal.awj.om/api/v1`

All endpoints require authentication via `Authorization: Bearer {token}`

#### Endpoint 1: Validate QR Code
```
POST /api/v1/tickets/validate
```

**Request Body**:
```json
{
  "qr_code": "TKT-2025-000001"
}
```

**Success Response (200)**:
```json
{
  "success": true,
  "message": "Ticket validated successfully",
  "data": {
    "ticket_id": "123",
    "ticket_number": "TKT-2025-000001",
    "qr_code": "TKT-2025-000001",
    "status": "active",
    "is_valid": true,
    "event_id": 42,
    "event_name": "Festival Opening Ceremony",
    "event_name_ar": "حفل افتتاح المهرجان",
    "event_name_en": "Festival Opening Ceremony",
    "event_date": "2025-10-30T19:00:00+04:00",
    "event_location": "Main Stage",
    "visitor_id": 1523,
    "visitor_name": "Ahmed Al-Balushi",
    "visitor_phone": "+96899123456",
    "visitor_email": "ahmed@example.com",
    "visit_date": "2025-10-30T00:00:00+04:00",
    "visit_time": null,
    "ticket_type": "standard",
    "number_of_people": 1,
    "validated_at": null,
    "validated_by": null,
    "check_in_count": 0,
    "max_check_ins": 1,
    "created_at": "2025-10-25T10:00:00+04:00",
    "metadata": {
      "booking_reference": "TKT-2025-000001",
      "payment_status": "paid",
      "price": 5.0,
      "currency": "OMR"
    }
  }
}
```

**Error Responses**:

Invalid QR Code (404):
```json
{
  "success": false,
  "message": "رمز QR غير صالح / Invalid QR code",
  "error_code": "INVALID_QR_CODE"
}
```

Already Used (400):
```json
{
  "success": false,
  "message": "التذكرة مستخدمة بالفعل / Ticket already used",
  "error_code": "TICKET_ALREADY_USED",
  "data": {
    "ticket_number": "TKT-2025-000001",
    "validated_at": "2025-10-25T14:30:00+04:00",
    "validated_by": "Staff Member Name"
  }
}
```

Expired (400):
```json
{
  "success": false,
  "message": "التذكرة منتهية الصلاحية / Ticket expired",
  "error_code": "TICKET_EXPIRED",
  "data": {
    "ticket_number": "TKT-2025-000001",
    "expiry_date": "2025-10-20T23:59:59+04:00"
  }
}
```

Cancelled (400):
```json
{
  "success": false,
  "message": "التذكرة ملغاة / Ticket cancelled",
  "error_code": "TICKET_CANCELLED"
}
```

---

#### Endpoint 2: Mark Ticket as Used
```
POST /api/v1/tickets/{ticket_id}/mark-used
```

**URL Parameters**:
- `ticket_id` - Can be either the numeric ID or the ticket_number (e.g., "123" or "TKT-2025-000001")

**Request Body**:
```json
{
  "validated_by": "Ahmed Al-Rashdi",
  "validation_notes": "Checked at main entrance",
  "location": "Main Gate A"
}
```

**Success Response (200)**:
```json
{
  "success": true,
  "message": "Ticket marked as used successfully",
  "data": {
    "ticket_id": "123",
    "ticket_number": "TKT-2025-000001",
    "status": "used",
    "validated_at": "2025-10-25T14:30:00+04:00",
    "validated_by": "Ahmed Al-Rashdi",
    "check_in_count": 1
  }
}
```

**Error Responses**:

Not Found (404):
```json
{
  "success": false,
  "message": "Ticket not found",
  "error_code": "TICKET_NOT_FOUND"
}
```

Invalid Operation (400):
```json
{
  "success": false,
  "message": "Cannot mark ticket as used",
  "error_code": "INVALID_OPERATION",
  "details": "التذكرة مستخدمة بالفعل / Ticket already used"
}
```

---

#### Endpoint 3: Validation History
```
GET /api/v1/tickets/validation-history
```

**Query Parameters**:
- `page` (optional, default: 1) - Page number
- `limit` (optional, default: 20, max: 100) - Records per page
- `date_from` (optional) - Filter from date (YYYY-MM-DD)
- `date_to` (optional) - Filter to date (YYYY-MM-DD)
- `event_id` (optional) - Filter by event ID
- `status` (optional) - Filter by status (active, used, expired, cancelled)

**Success Response (200)**:
```json
{
  "success": true,
  "message": "Validation history retrieved successfully",
  "data": {
    "validations": [
      {
        "id": 123,
        "ticket_id": "123",
        "ticket_number": "TKT-2025-000001",
        "event_name": "Festival Opening Ceremony",
        "visitor_name": "Ahmed Al-Balushi",
        "status": "used",
        "validated_at": "2025-10-25T14:30:00+04:00",
        "validated_by": "Staff Member",
        "location": "Main Gate A"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 10,
      "total_records": 195,
      "per_page": 20
    }
  }
}
```

---

### 4. Request Validation

**Files Created**:
- `app/Http/Requests/ValidateQrCodeRequest.php` - Validates QR code input
- `app/Http/Requests/MarkTicketUsedRequest.php` - Validates mark-used request

---

### 5. API Resources

**Files Created**:
- `app/Http/Resources/TicketValidationResource.php` - Formats ticket validation response
- `app/Http/Resources/ValidationHistoryResource.php` - Formats validation history items

---

## QR Code Format

The system uses the ticket number as the QR code value:
- Format: `TKT-YYYY-NNNNNN`
- Example: `TKT-2025-000001`

When a ticket is created:
1. A unique ticket number is auto-generated
2. The QR code is set to the ticket number value
3. Both are stored in the database

---

## Authentication

All validation endpoints require authentication using Laravel Sanctum:

```
Authorization: Bearer {access_token}
```

Staff members should:
1. Authenticate via the `/api/v1/auth/send-otp` and `/api/v1/auth/verify-otp` endpoints
2. Receive a Bearer token
3. Include the token in all subsequent requests

---

## Business Rules

1. **One-time use by default**: `max_check_ins` defaults to 1
2. **Multi-day passes**: Set `max_check_ins` to the number of allowed entries
3. **Check-in tracking**: `check_in_count` increments on each validation
4. **Status changes**:
   - `active` → `used` when `check_in_count >= max_check_ins`
5. **Date validation**:
   - Tickets cannot be used before the event start time
   - Tickets cannot be used after the event end time
   - Tickets cannot be used after their booking_date has passed

---

## Testing Instructions

### 1. Test with Postman/cURL

**Step 1: Authenticate**
```bash
# Send OTP
curl -X POST https://north-batinah-portal.awj.om/api/v1/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "+96899123456"}'

# Verify OTP
curl -X POST https://north-batinah-portal.awj.om/api/v1/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "+96899123456", "otp": "123456"}'
```

**Step 2: Validate QR Code**
```bash
curl -X POST https://north-batinah-portal.awj.om/api/v1/tickets/validate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"qr_code": "TKT-2025-000001"}'
```

**Step 3: Mark as Used**
```bash
curl -X POST https://north-batinah-portal.awj.om/api/v1/tickets/TKT-2025-000001/mark-used \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "validated_by": "Ahmad Al-Rashdi",
    "location": "Main Gate A",
    "validation_notes": "Checked at entrance"
  }'
```

**Step 4: Get Validation History**
```bash
curl -X GET "https://north-batinah-portal.awj.om/api/v1/tickets/validation-history?page=1&limit=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 2. Create Test Data

You can create test tickets using Laravel Tinker:

```bash
php artisan tinker
```

```php
// Create a test event
$event = App\Models\Event::first(); // or create a new one

// Create a test user
$user = App\Models\User::first(); // or create a new one

// Create valid ticket
$ticket = App\Models\Ticket::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'ticket_type' => 'standard',
    'status' => 'active',
    'price' => 5.000,
    'currency' => 'OMR',
    'holder_name' => 'Ahmed Al-Balushi',
    'purchase_date' => now(),
    'booking_date' => now()->addDays(5),
    'quantity' => 1,
    'max_check_ins' => 1,
]);

echo "QR Code: " . $ticket->qr_code . "\n";
echo "Ticket Number: " . $ticket->ticket_number . "\n";

// Create already-used ticket
$usedTicket = App\Models\Ticket::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'ticket_type' => 'vip',
    'status' => 'used',
    'price' => 10.000,
    'currency' => 'OMR',
    'holder_name' => 'Sara Al-Balushi',
    'purchase_date' => now(),
    'booking_date' => now(),
    'quantity' => 1,
    'used_at' => now(),
    'check_in_count' => 1,
    'max_check_ins' => 1,
    'validated_by' => 'Test Staff',
]);

echo "Used Ticket QR: " . $usedTicket->qr_code . "\n";

// Create expired ticket
$expiredTicket = App\Models\Ticket::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'ticket_type' => 'standard',
    'status' => 'expired',
    'price' => 5.000,
    'currency' => 'OMR',
    'holder_name' => 'Mohammed Al-Lawati',
    'purchase_date' => now()->subDays(10),
    'booking_date' => now()->subDays(5),
    'quantity' => 1,
]);

echo "Expired Ticket QR: " . $expiredTicket->qr_code . "\n";

// Create cancelled ticket
$cancelledTicket = App\Models\Ticket::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'ticket_type' => 'standard',
    'status' => 'cancelled',
    'price' => 5.000,
    'currency' => 'OMR',
    'holder_name' => 'Fatima Al-Hinai',
    'purchase_date' => now(),
    'booking_date' => now()->addDays(5),
    'quantity' => 1,
]);

echo "Cancelled Ticket QR: " . $cancelledTicket->qr_code . "\n";
```

---

## Files Modified/Created

### Created Files:
1. `database/migrations/2025_10_25_061751_add_qr_validation_fields_to_tickets_table.php`
2. `app/Http/Controllers/Api/TicketValidationController.php`
3. `app/Http/Requests/ValidateQrCodeRequest.php`
4. `app/Http/Requests/MarkTicketUsedRequest.php`
5. `app/Http/Resources/TicketValidationResource.php`
6. `app/Http/Resources/ValidationHistoryResource.php`

### Modified Files:
1. `app/Models/Ticket.php` - Added validation methods and auto-generation logic
2. `routes/api.php` - Added QR validation routes

---

## Security Considerations

1. **Authentication Required**: All endpoints require valid Bearer token
2. **Rate Limiting**: Consider adding rate limiting to prevent abuse
3. **Audit Logging**: All validation attempts are tracked with:
   - Timestamp (`validated_at`)
   - Staff member (`validated_by`)
   - Location (`validation_location`)
   - Notes (`validation_notes`)

---

## Performance Optimization

1. **Eager Loading**: Controller uses `with(['event', 'user', 'payment'])` to prevent N+1 queries
2. **Database Indexes**: QR code field is indexed for fast lookups
3. **Response Time**: Target < 500ms for validation endpoint

---

## Next Steps / Recommendations

1. **Staff Permissions**: Consider implementing role-based access control (RBAC) to restrict validation endpoints to staff users only
2. **Rate Limiting**: Add rate limiting to prevent abuse (e.g., 100 requests/minute per user)
3. **Audit Dashboard**: Create an admin dashboard to view validation statistics
4. **Offline Support**: Consider implementing offline QR validation for poor connectivity scenarios
5. **QR Code Security**: Consider using signed tokens (JWT) for added security instead of plain ticket numbers

---

## Support

For questions or issues:
- Backend Team: [Contact Info]
- Mobile Team: [Contact Info]
- Documentation: This file

---

**Version**: 1.0
**Date**: October 25, 2025
**Author**: Backend Development Team
