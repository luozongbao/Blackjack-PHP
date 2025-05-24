<?php
session_start();

// Mock a user session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Mock user ID
}

require_once 'includes/database.php';
require_once 'classes/game_class.php';

$db = getDb();

// Handle AJAX test requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['test_action'];
        
        switch ($action) {
            case 'test_ajax':
                echo json_encode([
                    'success' => true,
                    'message' => 'AJAX working correctly',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
                
            case 'test_game_creation':
                // Test game creation
                $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
                $stmt->execute([1]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$settings) {
                    // Create default settings
                    $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
                    $stmt->execute([1]);
                    
                    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
                    $stmt->execute([1]);
                    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // Create or get session
                $stmt = $db->prepare("SELECT * FROM game_sessions WHERE user_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
                $stmt->execute([1]);
                $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$sessionData) {
                    $stmt = $db->prepare("INSERT INTO game_sessions (user_id, current_money) SELECT ?, initial_money FROM user_settings WHERE user_id = ?");
                    $stmt->execute([1, 1]);
                    $sessionId = $db->lastInsertId();
                    
                    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                    $stmt->execute([$sessionId]);
                    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $sessionId = $sessionData['session_id'];
                }
                
                $game = new BlackjackGame($settings, $sessionId, $db);
                $gameState = $game->startGame(100); // Start with $100 bet
                
                echo json_encode([
                    'success' => true,
                    'gameState' => $gameState,
                    'sessionData' => $sessionData
                ]);
                break;
                
            default:
                throw new Exception('Unknown test action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AJAX Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; }
        button { padding: 10px 20px; margin: 10px; }
        .result { background: #f5f5f5; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>AJAX Debug Test</h1>
    
    <div class="test-section">
        <h2>Test 1: Basic AJAX</h2>
        <button onclick="testBasicAjax()">Test Basic AJAX</button>
        <div id="basic-result" class="result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 2: Game Creation</h2>
        <button onclick="testGameCreation()">Test Game Creation</button>
        <div id="game-result" class="result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 3: Actual Game Flow</h2>
        <button onclick="testActualGame()">Test Game.php AJAX</button>
        <div id="actual-result" class="result"></div>
    </div>

    <script>
        function testBasicAjax() {
            const formData = new FormData();
            formData.append('test_action', 'test_ajax');
            
            fetch('debug_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Basic AJAX response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Basic AJAX response:', data);
                document.getElementById('basic-result').innerHTML = 
                    '<h3>Success!</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                console.error('Basic AJAX error:', error);
                document.getElementById('basic-result').innerHTML = 
                    '<h3>Error!</h3><p>' + error.message + '</p>';
            });
        }
        
        function testGameCreation() {
            const formData = new FormData();
            formData.append('test_action', 'test_game_creation');
            
            fetch('debug_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Game creation response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Game creation response:', data);
                document.getElementById('game-result').innerHTML = 
                    '<h3>Game Creation Result</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                console.error('Game creation error:', error);
                document.getElementById('game-result').innerHTML = 
                    '<h3>Error!</h3><p>' + error.message + '</p>';
            });
        }
        
        function testActualGame() {
            const formData = new FormData();
            formData.append('action', 'start_game');
            formData.append('bet_amount', '100');
            formData.append('ajax', '1');
            
            fetch('game.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Actual game response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text(); // Use text() first to see what we're getting
            })
            .then(text => {
                console.log('Actual game response text:', text);
                try {
                    const data = JSON.parse(text);
                    document.getElementById('actual-result').innerHTML = 
                        '<h3>Game.php AJAX Result</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } catch (e) {
                    document.getElementById('actual-result').innerHTML = 
                        '<h3>Game.php Response (not JSON)</h3><pre>' + text + '</pre>';
                }
            })
            .catch(error => {
                console.error('Actual game error:', error);
                document.getElementById('actual-result').innerHTML = 
                    '<h3>Error!</h3><p>' + error.message + '</p>';
            });
        }
    </script>
</body>
</html>
