#!/bin/bash

# API Testing Script for Sohar Festival
# This script tests all API endpoints to ensure they return data

BASE_URL="http://localhost:8000/api/v1"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "      SOHAR FESTIVAL API TEST SUITE      "
echo "=========================================="
echo ""

# Counter for passed/failed tests
PASSED=0
FAILED=0
EMPTY_RESPONSES=0

# Function to test an endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4

    echo -n "Testing: $description... "

    if [ "$method" = "GET" ]; then
        response=$(curl -s -X GET "${BASE_URL}${endpoint}" -H "Accept: application/json")
    elif [ "$method" = "POST" ]; then
        response=$(curl -s -X POST "${BASE_URL}${endpoint}" -H "Accept: application/json" -H "Content-Type: application/json" -d "$data")
    fi

    # Check if response is empty
    if [ -z "$response" ]; then
        echo -e "${RED}FAILED (No response)${NC}"
        ((FAILED++))
        return
    fi

    # Check if response contains error
    if echo "$response" | grep -q '"error"'; then
        echo -e "${RED}FAILED (Error in response)${NC}"
        echo "  Response: $(echo $response | head -c 100)..."
        ((FAILED++))
        return
    fi

    # Check if response has data
    if echo "$response" | grep -q '"data":\[\]'; then
        echo -e "${YELLOW}WARNING (Empty data array)${NC}"
        ((EMPTY_RESPONSES++))
        ((PASSED++))
        return
    fi

    # Check if response is successful
    if echo "$response" | grep -q '"success":true\|"data":\[{\|"data":{'; then
        echo -e "${GREEN}PASSED${NC}"
        ((PASSED++))
    else
        echo -e "${RED}FAILED${NC}"
        echo "  Response: $(echo $response | head -c 100)..."
        ((FAILED++))
    fi
}

echo "=== PUBLIC ENDPOINTS ==="
echo ""

# Events
test_endpoint "GET" "/events" "Get all events"
test_endpoint "GET" "/events/upcoming" "Get upcoming events"
test_endpoint "GET" "/events/today" "Get today's events"
test_endpoint "GET" "/events/featured" "Get featured events"
test_endpoint "GET" "/events/1" "Get specific event"

# Heritage Villages
test_endpoint "GET" "/heritage-villages" "Get all heritage villages"
test_endpoint "GET" "/heritage-villages/1" "Get specific village"

# Cultural Workshops
test_endpoint "GET" "/cultural-workshops" "Get all workshops"
test_endpoint "GET" "/cultural-workshops/available" "Get available workshops"
test_endpoint "GET" "/cultural-workshops/1" "Get specific workshop"

# Traditional Activities
test_endpoint "GET" "/traditional-activities" "Get all activities"
test_endpoint "GET" "/traditional-activities/interactive" "Get interactive activities"
test_endpoint "GET" "/traditional-activities/1" "Get specific activity"

# Craft Demonstrations
test_endpoint "GET" "/craft-demonstrations" "Get all demonstrations"
test_endpoint "GET" "/craft-demonstrations/live" "Get live demonstrations"
test_endpoint "GET" "/craft-demonstrations/1" "Get specific demonstration"

# Restaurants
test_endpoint "GET" "/restaurants" "Get all restaurants"
test_endpoint "GET" "/restaurants/open-now" "Get open restaurants"
test_endpoint "GET" "/restaurants/1" "Get specific restaurant"

# Photo Spots
test_endpoint "GET" "/photo-spots" "Get all photo spots"
test_endpoint "GET" "/photo-spots/1" "Get specific photo spot"

# Map Locations
test_endpoint "GET" "/map-locations" "Get all map locations"
test_endpoint "GET" "/map-locations/entertainment" "Get entertainment locations"
test_endpoint "GET" "/map-locations/food" "Get food locations"
test_endpoint "GET" "/map-locations/facilities" "Get facility locations"
test_endpoint "GET" "/map-locations/parking" "Get parking locations"

# Announcements
test_endpoint "GET" "/announcements" "Get all announcements"
test_endpoint "GET" "/announcements/active" "Get active announcements"

# Emergency Contacts
test_endpoint "GET" "/emergency-contacts" "Get emergency contacts"

# Notifications
test_endpoint "GET" "/notifications/public" "Get public notifications"

# Ticket Pricing
test_endpoint "GET" "/tickets/pricing" "Get ticket pricing"

# App Settings
test_endpoint "GET" "/app-settings" "Get app settings"

echo ""
echo "=== AUTHENTICATION ENDPOINTS ==="
echo ""

# Test OTP sending
test_endpoint "POST" "/auth/send-otp" "Send OTP" '{"phone_number":"96812345678"}'

# Test OTP verification (will fail without valid OTP but validates fields)
test_endpoint "POST" "/auth/verify-otp" "Verify OTP" '{"phone_number":"96812345678","otp":"123456"}'

echo ""
echo "=========================================="
echo "           TEST RESULTS SUMMARY           "
echo "=========================================="
echo ""
echo -e "Tests Passed: ${GREEN}$PASSED${NC}"
echo -e "Tests Failed: ${RED}$FAILED${NC}"
echo -e "Empty Responses: ${YELLOW}$EMPTY_RESPONSES${NC}"
echo ""

if [ $FAILED -eq 0 ] && [ $EMPTY_RESPONSES -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed successfully!${NC}"
    exit 0
elif [ $FAILED -eq 0 ]; then
    echo -e "${YELLOW}⚠ All endpoints working but some return empty data${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed. Please check the errors above.${NC}"
    exit 1
fi