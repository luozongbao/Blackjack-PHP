<?php
echo "Testing money deduction fix...\n";

try {
    require_once 'includes/database.php';
    $db = getDb();
    echo "Database connected successfully\n";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM game_sessions");
    $result = $stmt->fetch();
    echo "Sessions in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
