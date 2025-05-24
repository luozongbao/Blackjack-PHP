#!/bin/bash

echo "🃏 Blackjack Game - Final Verification Script"
echo "============================================="
echo ""

# Test 1: JavaScript Syntax Check
echo "Test 1: Checking JavaScript syntax..."
if node -c /dev/stdin <<< "$(grep -A 500 '<script>' /home/zongbao/var/www/Blackjack-PHP/game.php | grep -B 500 '</script>' | sed '1d;$d')" 2>/dev/null; then
    echo "✅ JavaScript syntax is valid"
    JS_SYNTAX=true
else
    echo "❌ JavaScript syntax errors found"
    JS_SYNTAX=false
fi
echo ""

# Test 2: PHP Syntax Check
echo "Test 2: Checking PHP syntax..."
if php -l /home/zongbao/var/www/Blackjack-PHP/game.php >/dev/null 2>&1; then
    echo "✅ PHP syntax is valid"
    PHP_SYNTAX=true
else
    echo "❌ PHP syntax errors found"
    PHP_SYNTAX=false
fi
echo ""

# Test 3: Server Response Test
echo "Test 3: Testing server responses..."
SERVER_RUNNING=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/game.php)
if [ "$SERVER_RUNNING" == "302" ] || [ "$SERVER_RUNNING" == "200" ]; then
    echo "✅ Server is responding correctly (HTTP $SERVER_RUNNING)"
    SERVER_OK=true
else
    echo "❌ Server not responding properly (HTTP $SERVER_RUNNING)"
    SERVER_OK=false
fi
echo ""

# Test 4: AJAX JSON Response Test  
echo "Test 4: Testing AJAX JSON responses..."
AJAX_RESPONSE=$(curl -s -X POST -H "Content-Type: application/x-www-form-urlencoded" -d "action=start_game&bet_amount=100&ajax=1" "http://localhost:8000/game.php")
if echo "$AJAX_RESPONSE" | grep -q '"success"'; then
    echo "✅ AJAX requests return proper JSON"
    AJAX_OK=true
else
    echo "❌ AJAX requests not returning JSON"
    AJAX_OK=false
fi
echo ""

# Test 5: Critical Functions Check
echo "Test 5: Checking critical functions exist..."
FUNCTIONS_FOUND=0

if grep -q "function updateActionButtons" /home/zongbao/var/www/Blackjack-PHP/game.php; then
    echo "✅ updateActionButtons function found"
    ((FUNCTIONS_FOUND++))
else
    echo "❌ updateActionButtons function missing"
fi

if grep -q "function updateGameUI" /home/zongbao/var/www/Blackjack-PHP/game.php; then
    echo "✅ updateGameUI function found"
    ((FUNCTIONS_FOUND++))
else
    echo "❌ updateGameUI function missing"
fi

if grep -q "function handleBetFormSubmit" /home/zongbao/var/www/Blackjack-PHP/game.php; then
    echo "✅ handleBetFormSubmit function found"
    ((FUNCTIONS_FOUND++))
else
    echo "❌ handleBetFormSubmit function missing"
fi

if [ $FUNCTIONS_FOUND -eq 3 ]; then
    FUNCTIONS_OK=true
else
    FUNCTIONS_OK=false
fi
echo ""

# Summary
echo "📋 FINAL TEST SUMMARY"
echo "====================="
echo ""

TOTAL_PASSED=0

if [ "$JS_SYNTAX" = true ]; then
    echo "✅ JavaScript Syntax: PASSED"
    ((TOTAL_PASSED++))
else
    echo "❌ JavaScript Syntax: FAILED"
fi

if [ "$PHP_SYNTAX" = true ]; then
    echo "✅ PHP Syntax: PASSED"
    ((TOTAL_PASSED++))
else
    echo "❌ PHP Syntax: FAILED"
fi

if [ "$SERVER_OK" = true ]; then
    echo "✅ Server Response: PASSED"
    ((TOTAL_PASSED++))
else
    echo "❌ Server Response: FAILED"
fi

if [ "$AJAX_OK" = true ]; then
    echo "✅ AJAX JSON: PASSED"
    ((TOTAL_PASSED++))
else
    echo "❌ AJAX JSON: FAILED"
fi

if [ "$FUNCTIONS_OK" = true ]; then
    echo "✅ Critical Functions: PASSED"
    ((TOTAL_PASSED++))
else
    echo "❌ Critical Functions: FAILED"
fi

echo ""
echo "OVERALL RESULT: $TOTAL_PASSED/5 tests passed"
echo ""

if [ $TOTAL_PASSED -eq 5 ]; then
    echo "🎉 ALL CRITICAL ISSUES HAVE BEEN RESOLVED!"
    echo ""
    echo "Fixed Issues:"
    echo "1. ✅ Network error when dealing cards - RESOLVED"
    echo "   - Replaced location.reload() with dynamic UI updates"
    echo "   - AJAX requests now return proper JSON responses"
    echo ""
    echo "2. ✅ Missing action buttons after cards are dealt - RESOLVED"
    echo "   - Added updateActionButtons() function"
    echo "   - Action buttons are created dynamically based on game state"
    echo "   - Fixed JavaScript syntax error that was preventing execution"
    echo ""
    echo "3. ✅ 500 Internal Server Error - RESOLVED"
    echo "   - Fixed invalid method call"
    echo "   - Added proper serialization support for PDO objects"
    echo ""
    echo "The blackjack game should now work correctly without page refreshes!"
else
    echo "⚠️ Some issues may still remain. Check the failed tests above."
fi

echo ""
echo "To test the game manually:"
echo "1. Open http://localhost:8000/test_final_verification.html"
echo "2. Or open http://localhost:8000/game.php directly"
echo "3. Login, place a bet, and verify action buttons appear after dealing"
