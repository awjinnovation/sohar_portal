# Bank Muscat Payment Gateway Integration

## Overview
Successfully migrated from Thawani to Bank Muscat payment gateway using CCAvenue integration.

## Configuration

### Environment Variables (.env)
```bash
# Test/UAT Environment
BANK_MUSCAT_GATEWAY_URL=https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction

# Production Environment (switch to this when going live)
# BANK_MUSCAT_GATEWAY_URL=https://services.bng.gov.om:8989/transaction.do?command=initiateTransaction

BANK_MUSCAT_ACCESS_CODE=AVSH00MK65AS84HSSA
BANK_MUSCAT_WORKING_KEY=5EBA54489E7EA0A0CAAA6F166864CB7F
BANK_MUSCAT_MERCHANT_ID=4158
```

### Gateway URLs
- **Test/UAT**: `https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction`
- **Production**: `https://services.bng.gov.om:8989/transaction.do?command=initiateTransaction`

## Payment Flow

### 1. Initialize Payment (Mobile App → API)

**Endpoint:** `POST /api/v1/payments/initialize`

**Request:**
```json
{
  "payment_type": "ticket",
  "payable_id": 1,
  "quantity": 1,
  "amount": 5.000,
  "booking_date": "2025-11-10"
}
```

**Response:**
```json
{
  "success": true,
  "payment_id": 123,
  "transaction_id": "TXNE0K5WAJ95M",
  "checkout_url": "http://localhost/payment/redirect/TXNE0K5WAJ95M",
  "encrypted_data": "...",
  "access_code": "AVSH00MK65AS84HSSA",
  "gateway_url": "https://services.bng.gov.om:8989/transaction.do?command=initiateTransaction",
  "redirect_required": true
}
```

### 2. Flutter App Opens WebView
The Flutter app should open the `checkout_url` in a WebView or in-app browser.

### 3. Auto-Redirect to Bank Muscat
The checkout_url displays a loading page that automatically redirects to Bank Muscat with encrypted payment data.

### 4. User Completes Payment
User enters card details on Bank Muscat's secure payment page.

### 5. Callback to Your Server
Bank Muscat redirects back to:
- **Success:** `POST /payment/success` with encrypted response
- **Cancel:** `POST /payment/cancel` with encrypted response

### 6. Payment Processing
The server:
- Decrypts the response
- Validates payment details
- Updates payment status
- Creates tickets if successful

### 7. Check Payment Status (Flutter App)

**Endpoint:** `GET /api/v1/payments/status/{transactionId}`

**Response:**
```json
{
  "success": true,
  "payment_status": "completed",
  "transaction_id": "TXNE0K5WAJ95M",
  "amount": 5.000,
  "currency": "OMR",
  "payment_data": { ... }
}
```

## Flutter Integration Example

```dart
// 1. Initialize payment
final response = await http.post(
  Uri.parse('${apiUrl}/api/v1/payments/initialize'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: jsonEncode({
    'payment_type': 'ticket',
    'payable_id': ticketId,
    'quantity': quantity,
    'amount': amount,
    'booking_date': bookingDate,
  }),
);

final data = jsonDecode(response.body);
final checkoutUrl = data['checkout_url'];
final transactionId = data['transaction_id'];

// 2. Open WebView with checkout_url
await Navigator.push(
  context,
  MaterialPageRoute(
    builder: (context) => PaymentWebView(
      url: checkoutUrl,
      transactionId: transactionId,
    ),
  ),
);

// 3. After WebView closes, check payment status
final statusResponse = await http.get(
  Uri.parse('${apiUrl}/api/v1/payments/status/$transactionId'),
  headers: {'Authorization': 'Bearer $token'},
);

final statusData = jsonDecode(statusResponse.body);
if (statusData['payment_status'] == 'completed') {
  // Payment successful!
}
```

## Key Components

### Services
- **BankMuscatCrypto** - Handles AES-256-GCM encryption/decryption
- **BankMuscatPaymentService** - Main service for payment processing

### Controllers
- **Api/PaymentController** - API endpoints for mobile app
- **Web/PaymentController** - Web endpoints for payment redirects

### Views
- **payment.redirect** - Auto-submit form to Bank Muscat gateway
- **payment.success** - Payment success page
- **payment.cancel** - Payment cancellation page
- **payment.error** - Payment error page

### Routes
- `POST /api/v1/payments/initialize` - Initialize payment
- `POST /api/v1/payments/confirm` - Confirm payment status
- `GET /api/v1/payments/status/{transactionId}` - Check payment status
- `POST /api/v1/payments/webhook` - Webhook for Bank Muscat callbacks
- `GET /payment/redirect/{transaction_id}` - Redirect page with auto-submit form
- `POST /payment/success` - Success callback from Bank Muscat
- `POST /payment/cancel` - Cancel callback from Bank Muscat

## Database Changes

Added fields to `payments` table:
- `bank_muscat_order_id` - Bank Muscat order ID
- `bank_muscat_tid` - Bank Muscat transaction ID
- `bank_muscat_response` - Full response from Bank Muscat (JSON)

Migration file: `2025_11_09_113639_add_bank_muscat_fields_to_payments_table.php`

## Payment Status Values

- `pending` - Payment initiated but not completed
- `completed` - Payment successful
- `failed` - Payment failed
- `cancelled` - Payment cancelled by user

## Security Features

1. **AES-256-GCM Encryption** - All payment data encrypted before sending to Bank Muscat
2. **Response Validation** - Server validates:
   - Order ID matches
   - Currency matches
   - Amount matches
   - Order status is valid
3. **Webhook Protection** - Webhook endpoint accepts encrypted data only

## Testing

Test the payment flow:
```bash
# Test encryption service
php artisan tinker
$service = new App\Services\BankMuscatPaymentService();
$encrypted = $service->generateEncryptedRequest(['test' => 'data']);
echo $encrypted;

# Test payment initialization
# Use the test script in tinker or make an API call with Postman
```

## Production Checklist

- [ ] Test thoroughly in UAT environment (https://spayuattrns.bmtest.om)
- [ ] Update `BANK_MUSCAT_GATEWAY_URL` to production URL (https://services.bng.gov.om:8989)
- [ ] Verify `BANK_MUSCAT_MERCHANT_ID` is correct for production
- [ ] Update `APP_URL` in .env to production URL
- [ ] Whitelist your redirect URLs with Bank Muscat
- [ ] Request production credentials from Bank Muscat if different from UAT
- [ ] Test full payment flow in staging environment
- [ ] Set up monitoring for payment failures
- [ ] Configure proper error logging

## Support

For Bank Muscat integration issues, contact their support with:
- Merchant ID: 4158
- Access Code: AVSH00MK65AS84HSSA
- Integration Type: Non-Seamless (CCAvenue)
