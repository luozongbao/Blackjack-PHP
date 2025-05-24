<?php
echo "Basic PHP test\n";
try {
    require_once 'includes/database.php';
    echo "Database include successful\n";
} catch (Exception $e) {
    echo "Database include failed: " . $e->getMessage() . "\n";
}

try {
    require_once 'classes/game_class.php';
    echo "Game class include successful\n";
} catch (Exception $e) {
    echo "Game class include failed: " . $e->getMessage() . "\n";
}

try {
    session_start();
    echo "Session start successful\n";
} catch (Exception $e) {
    echo "Session start failed: " . $e->getMessage() . "\n";
}

echo "Test complete\n";
?>
