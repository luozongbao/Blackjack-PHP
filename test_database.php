<?php
/**
 * Simple database test for money deduction
 */

require_once 'includes/database.php';

$db = getDb();

try {
    echo "=== Database Connection Test ===\n";
    
    // Test database connection
    $stmt = $db->query("SELECT COUNT(*) as count FROM game_sessions");
    $result = $stmt->fetch();
    echo "Found " . $result['count'] . " sessions in database\n";
    
    // Get a test session
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE current_money > 0 LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "\nTest Session Found:\n";
        echo "- Session ID: " . $session['session_id'] . "\n";
        echo "- Current Money: $" . number_format($session['current_money'], 2) . "\n";
        echo "- Session Total Bet: $" . number_format($session['session_total_bet'], 2) . "\n";
        
        // Test money deduction
        $betAmount = 100;
        $originalMoney = $session['current_money'];
        $originalTotalBet = $session['session_total_bet'];
        
        echo "\nTesting $100 bet deduction...\n";
        
        $stmt = $db->prepare("
            UPDATE game_sessions 
            SET current_money = current_money - ?,
                session_total_bet = session_total_bet + ?
            WHERE session_id = ?
        ");
        $success = $stmt->execute([$betAmount, $betAmount, $session['session_id']]);
        
        if ($success) {
            echo "Update executed successfully\n";
            
            // Check results
            $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
            $stmt->execute([$session['session_id']]);
            $updatedSession = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "After bet:\n";
            echo "- Current Money: $" . number_format($updatedSession['current_money'], 2) . "\n";
            echo "- Session Total Bet: $" . number_format($updatedSession['session_total_bet'], 2) . "\n";
            echo "- Money Change: $" . number_format($updatedSession['current_money'] - $originalMoney, 2) . "\n";
            echo "- Total Bet Change: $" . number_format($updatedSession['session_total_bet'] - $originalTotalBet, 2) . "\n";
            
            // Restore original values
            echo "\nRestoring original values...\n";
            $stmt = $db->prepare("
                UPDATE game_sessions 
                SET current_money = ?,
                    session_total_bet = ?
                WHERE session_id = ?
            ");
            $stmt->execute([$originalMoney, $originalTotalBet, $session['session_id']]);
            echo "Restored successfully\n";
            
        } else {
            echo "Update failed\n";
        }
        
    } else {
        echo "No test session found\n";
    }
    
    echo "\n=== Test Completed ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
