<?php
/**
 * Test script to verify session deserialization fixes
 */

// Test 1: Basic game.php access without existing session
echo "=== Test 1: Direct game.php access (should redirect to login) ===\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8003/game.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 302) {
    echo "✓ Correctly redirected to login\n";
} elseif ($httpCode === 500) {
    echo "✗ HTTP 500 error occurred\n";
    echo "Response headers:\n" . substr($response, 0, 500) . "\n";
} else {
    echo "Response: " . substr($response, 0, 200) . "\n";
}
echo "\n";

// Test 2: Login first, then access game
echo "=== Test 2: Login then access game ===\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8003/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookies.txt');
$loginPage = curl_exec($ch);
curl_close($ch);

// Extract CSRF token if needed
preg_match('/<input[^>]+name="csrf_token"[^>]+value="([^"]+)"/', $loginPage, $matches);
$csrfToken = $matches[1] ?? '';

// Try to login (assuming we have a test user)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8003/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'test',
    'password' => 'test123',
    'csrf_token' => $csrfToken
]));
curl_setopt($ch, CURLOPT_HEADER, true);
$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login HTTP Code: $loginHttpCode\n";

// Now try to access game.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8003/game.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_HEADER, true);
$gameResponse = curl_exec($ch);
$gameHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Game access HTTP Code: $gameHttpCode\n";
if ($gameHttpCode === 200) {
    echo "✓ Game page loaded successfully\n";
} elseif ($gameHttpCode === 500) {
    echo "✗ HTTP 500 error on game page\n";
    echo "Response headers:\n" . substr($gameResponse, 0, 500) . "\n";
} else {
    echo "Unexpected response code\n";
}

echo "\n=== Test Complete ===\n";
