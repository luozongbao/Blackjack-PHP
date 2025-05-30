<?php
/**
 * Diagnostic script for Community Dashboard
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Community Dashboard Diagnostic ===\n\n";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    require_once 'includes/database.php';
    $db = getDb();
    echo "   ✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Analytics class loading
echo "2. Testing Analytics class...\n";
try {
    require_once 'classes/analytics_class.php';
    $analytics = new Analytics();
    echo "   ✓ Analytics class loaded successfully\n\n";
} catch (Exception $e) {
    echo "   ✗ Analytics class failed: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 3: Direct SQL query
echo "3. Testing direct SQL query...\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_analytics");
    $result = $stmt->fetch();
    echo "   ✓ Total analytics records: " . $result['total'] . "\n\n";
} catch (Exception $e) {
    echo "   ✗ SQL query failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Analytics methods
echo "4. Testing Analytics methods...\n";
try {
    echo "   Testing getLocationStats()...\n";
    $locationStats = $analytics->getLocationStats('all');
    echo "   ✓ Location stats returned " . count($locationStats) . " records\n";
    
    if (!empty($locationStats)) {
        echo "   First location: " . $locationStats[0]['country'] . "\n";
    }
    
    echo "   Testing getBrowserStats()...\n";
    $browserStats = $analytics->getBrowserStats('all');
    echo "   ✓ Browser stats returned " . count($browserStats) . " records\n";
    
    echo "   Testing getRecentUserIPs()...\n";
    $recentIPs = $analytics->getRecentUserIPs('all', 5);
    echo "   ✓ Recent IPs returned " . count($recentIPs) . " records\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Analytics methods failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n\n";
}

echo "=== Diagnostic Complete ===\n";
?>
