#!/bin/bash

# Comprehensive test for the blackjack game fixes
# Tests the critical scenario: login -> game -> leave -> return -> refresh

SERVER_URL="http://localhost:8003"
COOKIE_FILE="/tmp/blackjack_test_cookies.txt"

echo "=== Blackjack Game Session Test ==="
echo "Testing critical scenarios that were causing HTTP 500 errors"
echo ""

# Clean up previous test cookies
rm -f $COOKIE_FILE

# Test 1: Login
echo "1. Testing login..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE \
     -d "username=test&password=test123" \
     -X POST "$SERVER_URL/login.php" \
     -w "HTTP Status: %{http_code}\n" \
     -o /tmp/login_response.html

LOGIN_STATUS=$(tail -1 /tmp/login_response.html | grep -o "HTTP Status: [0-9]*" | cut -d' ' -f3)
if [ "$LOGIN_STATUS" = "302" ] || [ "$LOGIN_STATUS" = "200" ]; then
    echo "✓ Login successful (Status: $LOGIN_STATUS)"
else
    echo "✗ Login failed (Status: $LOGIN_STATUS)"
    exit 1
fi

# Test 2: Access game page (should work after login)
echo ""
echo "2. Testing game page access after login..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE \
     "$SERVER_URL/game.php" \
     -w "HTTP Status: %{http_code}\n" \
     -o /tmp/game_response.html

GAME_STATUS=$(tail -1 /tmp/game_response.html | grep -o "HTTP Status: [0-9]*" | cut -d' ' -f3)
if [ "$GAME_STATUS" = "200" ]; then
    echo "✓ Game page loaded successfully (Status: $GAME_STATUS)"
else
    echo "✗ Game page failed (Status: $GAME_STATUS)"
    echo "Response preview:"
    head -10 /tmp/game_response.html
    exit 1
fi

# Test 3: Start a game via AJAX to create game object in session
echo ""
echo "3. Testing game start (creates session object)..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE \
     -d "action=start_game&bet_amount=500&ajax=1" \
     -X POST "$SERVER_URL/game.php" \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -H "X-Requested-With: XMLHttpRequest" \
     -w "HTTP Status: %{http_code}\n" \
     -o /tmp/start_game_response.json

START_STATUS=$(tail -1 /tmp/start_game_response.json | grep -o "HTTP Status: [0-9]*" | cut -d' ' -f3)
if [ "$START_STATUS" = "200" ]; then
    echo "✓ Game started successfully (Status: $START_STATUS)"
    echo "Response preview:"
    head -3 /tmp/start_game_response.json
else
    echo "✗ Game start failed (Status: $START_STATUS)"
    echo "Response:"
    cat /tmp/start_game_response.json
fi

# Test 4: Leave and return to game page (the critical test - should trigger session deserialization)
echo ""
echo "4. Testing return to game page (session deserialization test)..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE \
     "$SERVER_URL/game.php" \
     -w "HTTP Status: %{http_code}\n" \
     -o /tmp/return_game_response.html

RETURN_STATUS=$(tail -1 /tmp/return_game_response.html | grep -o "HTTP Status: [0-9]*" | cut -d' ' -f3)
if [ "$RETURN_STATUS" = "200" ]; then
    echo "✓ Returned to game page successfully (Status: $RETURN_STATUS)"
    echo "✓ Session deserialization working!"
else
    echo "✗ Return to game page failed (Status: $RETURN_STATUS)"
    echo "✗ Session deserialization issue detected!"
    echo "Response preview:"
    head -10 /tmp/return_game_response.html
fi

# Test 5: Page refresh test
echo ""
echo "5. Testing page refresh (another session deserialization test)..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE \
     "$SERVER_URL/game.php" \
     -w "HTTP Status: %{http_code}\n" \
     -o /tmp/refresh_game_response.html

REFRESH_STATUS=$(tail -1 /tmp/refresh_game_response.html | grep -o "HTTP Status: [0-9]*" | cut -d' ' -f3)
if [ "$REFRESH_STATUS" = "200" ]; then
    echo "✓ Page refresh successful (Status: $REFRESH_STATUS)"
else
    echo "✗ Page refresh failed (Status: $REFRESH_STATUS)"
    echo "Response preview:"
    head -10 /tmp/refresh_game_response.html
fi

# Test 6: Check if player cards are visible (test the DOM fix)
echo ""
echo "6. Testing player card visibility in HTML..."
if grep -q "player-hands-container" /tmp/refresh_game_response.html; then
    echo "✓ Player hands container found in HTML"
    if grep -q "data-hand=" /tmp/refresh_game_response.html; then
        echo "✓ Player hand elements found"
    else
        echo "⚠ Player hands container exists but no hand elements"
    fi
else
    echo "✗ Player hands container not found"
fi

echo ""
echo "=== Test Summary ==="
echo "Login: $([ "$LOGIN_STATUS" = "302" ] || [ "$LOGIN_STATUS" = "200" ] && echo "✓" || echo "✗")"
echo "Game Access: $([ "$GAME_STATUS" = "200" ] && echo "✓" || echo "✗")"  
echo "Game Start: $([ "$START_STATUS" = "200" ] && echo "✓" || echo "✗")"
echo "Return to Game: $([ "$RETURN_STATUS" = "200" ] && echo "✓" || echo "✗")"
echo "Page Refresh: $([ "$REFRESH_STATUS" = "200" ] && echo "✓" || echo "✗")"

# Final status
if [ "$RETURN_STATUS" = "200" ] && [ "$REFRESH_STATUS" = "200" ]; then
    echo ""
    echo "🎉 All critical session tests PASSED! HTTP 500 errors appear to be fixed."
else
    echo ""
    echo "❌ Critical issues remain. HTTP 500 errors may still occur."
fi
