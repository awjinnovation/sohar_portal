#!/bin/bash

echo "=========================================="
echo "SOHAR FESTIVAL API COMPREHENSIVE TEST"
echo "=========================================="
echo ""

BASE_URL="http://localhost:8000/api/v1"
PHONE="92345678"
TOKEN=""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test function
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local auth=$4
    local description=$5

    echo -e "${YELLOW}Testing: $description${NC}"
    echo "Endpoint: $method $endpoint"

    if [ "$method" = "GET" ]; then
        if [ "$auth" = "true" ]; then
            response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL$endpoint" \
                -H "Accept: application/json" \
                -H "Authorization: Bearer $TOKEN")
        else
            response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL$endpoint" \
                -H "Accept: application/json")
        fi
    else
        if [ "$auth" = "true" ]; then
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -H "Authorization: Bearer $TOKEN" \
                -d "$data")
        else
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "$data")
        fi
    fi

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        echo -e "${GREEN}✓ Status: $http_code - SUCCESS${NC}"
        echo "Response: $(echo $body | python3 -c "import sys, json; data=json.load(sys.stdin); print(json.dumps(data, indent=2)[:200] + '...' if len(json.dumps(data)) > 200 else json.dumps(data, indent=2))" 2>/dev/null || echo $body | head -c 200)"
    else
        echo -e "${RED}✗ Status: $http_code - FAILED${NC}"
        echo "Response: $body"
    fi
    echo "----------------------------------------"
    echo ""
}

echo "=========================================="
echo "1. AUTHENTICATION TESTS"
echo "=========================================="

# Test OTP Send
test_endpoint "POST" "/auth/send-otp" "{\"phone_number\":\"$PHONE\"}" "false" "Send OTP"

# Extract OTP from response for testing (only in dev mode)
OTP=$(curl -s -X POST "$BASE_URL/auth/send-otp" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"phone_number\":\"$PHONE\"}" | python3 -c "import sys, json; print(json.load(sys.stdin).get('otp', ''))" 2>/dev/null)

echo "OTP received: $OTP"

# Test OTP Verify
verify_response=$(curl -s -X POST "$BASE_URL/auth/verify-otp" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"phone_number\":\"$PHONE\",\"otp_code\":\"$OTP\"}")

TOKEN=$(echo $verify_response | python3 -c "import sys, json; print(json.load(sys.stdin).get('token', ''))" 2>/dev/null)

test_endpoint "POST" "/auth/verify-otp" "{\"phone_number\":\"$PHONE\",\"otp_code\":\"$OTP\"}" "false" "Verify OTP"

echo "Token received: ${TOKEN:0:20}..."

# Test Profile
test_endpoint "GET" "/auth/profile" "" "true" "Get Profile (Authenticated)"

echo "=========================================="
echo "2. PUBLIC ENDPOINTS TESTS"
echo "=========================================="

# Test Events
test_endpoint "GET" "/events" "" "false" "List Events"
test_endpoint "GET" "/events/1" "" "false" "Get Event Details"
test_endpoint "GET" "/events/upcoming" "" "false" "Get Upcoming Events"
test_endpoint "GET" "/events/today" "" "false" "Get Today's Events"

# Test Heritage Villages
test_endpoint "GET" "/heritage-villages" "" "false" "List Heritage Villages"

# Test Restaurants
test_endpoint "GET" "/restaurants" "" "false" "List Restaurants"
test_endpoint "GET" "/restaurants/1" "" "false" "Get Restaurant Details"

# Test Photo Spots
test_endpoint "GET" "/photo-spots" "" "false" "List Photo Spots"

# Test Emergency Contacts
test_endpoint "GET" "/emergency-contacts" "" "false" "List Emergency Contacts"

# Test Map Locations
test_endpoint "GET" "/map-locations" "" "false" "List Map Locations"

# Test Notifications
test_endpoint "GET" "/notifications" "" "false" "Get Public Notifications"

echo "=========================================="
echo "3. AUTHENTICATED ENDPOINTS TESTS"
echo "=========================================="

# Test Tickets
test_endpoint "GET" "/tickets/available" "" "true" "Get Available Tickets"
test_endpoint "GET" "/tickets/pricing" "" "true" "Get Ticket Pricing"
test_endpoint "GET" "/tickets/my-tickets" "" "true" "Get My Tickets"

# Test Favorites
test_endpoint "POST" "/events/1/favorite" "" "true" "Toggle Event Favorite"
test_endpoint "GET" "/events/favorites" "" "true" "Get Favorite Events"

# Test Workshops
test_endpoint "GET" "/cultural-workshops" "" "false" "List Workshops"
test_endpoint "GET" "/cultural-workshops/available" "" "false" "Get Available Workshops"
test_endpoint "GET" "/cultural-workshops/my-registrations" "" "true" "Get My Workshop Registrations"

# Test Payment
test_endpoint "POST" "/payments/initialize" "{\"payment_type\":\"ticket\",\"payable_id\":1,\"quantity\":2,\"amount\":10.000}" "true" "Initialize Payment"
test_endpoint "GET" "/payments/my-transactions" "" "true" "Get My Transactions"

echo "=========================================="
echo "4. ERROR HANDLING TESTS"
echo "=========================================="

# Test Invalid OTP
test_endpoint "POST" "/auth/verify-otp" "{\"phone_number\":\"$PHONE\",\"otp_code\":\"000000\"}" "false" "Invalid OTP"

# Test Unauthenticated Access
TOKEN="invalid_token"
test_endpoint "GET" "/tickets/my-tickets" "" "true" "Unauthenticated Access"

echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="

# Count models and controllers
echo "Checking implementation status..."
echo ""

echo "API Controllers:"
ls app/Http/Controllers/Api/*.php 2>/dev/null | wc -l | xargs echo "- Total controllers:"

echo ""
echo "Models:"
ls app/Models/*.php 2>/dev/null | wc -l | xargs echo "- Total models:"

echo ""
echo "Migrations:"
ls database/migrations/*.php 2>/dev/null | wc -l | xargs echo "- Total migrations:"

echo ""
echo "Routes:"
php artisan route:list --path=api 2>/dev/null | grep -c "api/v1" | xargs echo "- Total API routes:"

echo ""
echo "=========================================="
echo "TEST COMPLETE!"
echo "=========================================="