#!/bin/bash

# Smoke Tests for Scan2Sheet Receipt API
# This script runs comprehensive smoke tests after deployment

set -e

# Configuration
SERVICE_URL="${1:-http://localhost:8080}"
TIMEOUT=30
RETRIES=3

echo "🧪 Running smoke tests for Scan2Sheet..."
echo "Service URL: $SERVICE_URL"
echo "Timeout: ${TIMEOUT}s"
echo "Retries: $RETRIES"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to test an endpoint
test_endpoint() {
    local url="$1"
    local expected_status="$2"
    local description="$3"
    local method="${4:-GET}"
    
    echo -n "Testing $description: $url ... "
    
    for attempt in $(seq 1 $RETRIES); do
        if [ $attempt -gt 1 ]; then
            echo -n "(attempt $attempt) ... "
        fi
        
        # Make the request
        if [ "$method" = "GET" ]; then
            status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT "$url" || echo "000")
        else
            status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT -X "$method" "$url" || echo "000")
        fi
        
        if [ "$status_code" = "$expected_status" ]; then
            echo -e "${GREEN}✅ $status_code${NC}"
            return 0
        fi
        
        if [ $attempt -lt $RETRIES ]; then
            echo -e "${YELLOW}⏳ $status_code${NC} (retrying in 2s...)"
            sleep 2
        fi
    done
    
    echo -e "${RED}❌ Failed after $RETRIES attempts (got $status_code, expected $expected_status)${NC}"
    return 1
}

# Function to test JSON endpoint
test_json_endpoint() {
    local url="$1"
    local description="$2"
    local expected_field="$3"
    
    echo -n "Testing JSON $description: $url ... "
    
    for attempt in $(seq 1 $RETRIES); do
        if [ $attempt -gt 1 ]; then
            echo -n "(attempt $attempt) ... "
        fi
        
        response=$(curl -s --max-time $TIMEOUT "$url" 2>/dev/null || echo "")
        status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT "$url" 2>/dev/null || echo "000")
        
        if [ "$status_code" = "200" ] && echo "$response" | grep -q "$expected_field"; then
            echo -e "${GREEN}✅ Valid JSON${NC}"
            return 0
        fi
        
        if [ $attempt -lt $RETRIES ]; then
            echo -e "${YELLOW}⏳ $status_code${NC} (retrying in 2s...)"
            sleep 2
        fi
    done
    
    echo -e "${RED}❌ Failed after $RETRIES attempts (got $status_code)${NC}"
    return 1
}

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Run smoke tests
echo "🌐 Testing main endpoints..."

# Test 1: Home page
if test_endpoint "$SERVICE_URL/" "200" "Home page"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 2: API config endpoint
if test_json_endpoint "$SERVICE_URL/api/config" "Config endpoint" "project_id\|spreadsheet_id"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 3: API ready endpoint
if test_endpoint "$SERVICE_URL/api/ready" "200" "Ready endpoint"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 4: API health endpoint
if test_endpoint "$SERVICE_URL/api/health" "200" "Health endpoint"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 5: API auth endpoint (should return 401 or 200)
if test_endpoint "$SERVICE_URL/api/auth/me" "401" "Auth endpoint (unauthorized)"; then
    ((TESTS_PASSED++))
else
    # Try with 200 status as well (in case user is already authenticated)
    if test_endpoint "$SERVICE_URL/api/auth/me" "200" "Auth endpoint (authorized)"; then
        ((TESTS_PASSED++))
    else
        ((TESTS_FAILED++))
    fi
fi

# Test 6: Favicon
if test_endpoint "$SERVICE_URL/assets/icons/favicon.svg" "200" "Favicon"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 7: Manifest
if test_endpoint "$SERVICE_URL/manifest.json" "200" "PWA Manifest"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 8: Static assets
if test_endpoint "$SERVICE_URL/assets/css/app.css" "200" "CSS assets"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 9: JavaScript assets
if test_endpoint "$SERVICE_URL/assets/js/app.js" "200" "JavaScript assets"; then
    ((TESTS_PASSED++))
else
    ((TESTS_FAILED++))
fi

# Test 10: Check for Scan2Sheet branding
echo -n "Testing Scan2Sheet branding ... "
response=$(curl -s --max-time $TIMEOUT "$SERVICE_URL/" 2>/dev/null || echo "")
if echo "$response" | grep -qi "Scan2Sheet"; then
    echo -e "${GREEN}✅ Found${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${RED}❌ Not found${NC}"
    ((TESTS_FAILED++))
fi

# Performance test
echo ""
echo "⚡ Testing response times..."
start_time=$(date +%s%N)
curl -s --max-time $TIMEOUT "$SERVICE_URL/" > /dev/null
end_time=$(date +%s%N)
response_time=$(( (end_time - start_time) / 1000000 ))

if [ $response_time -lt 2000 ]; then
    echo -e "${GREEN}✅ Response time: ${response_time}ms (good)${NC}"
else
    echo -e "${YELLOW}⚠️  Response time: ${response_time}ms (slow)${NC}"
fi

# Summary
echo ""
echo "📊 Smoke Test Summary:"
echo -e "  ${GREEN}✅ Passed: $TESTS_PASSED${NC}"
echo -e "  ${RED}❌ Failed: $TESTS_FAILED${NC}"
echo "  Total: $((TESTS_PASSED + TESTS_FAILED))"

if [ $TESTS_FAILED -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 All smoke tests passed!${NC}"
    echo "✅ Application is ready for use"
    exit 0
else
    echo ""
    echo -e "${RED}💥 Smoke tests failed!${NC}"
    echo "❌ Application has issues that need to be fixed"
    exit 1
fi