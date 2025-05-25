<?php
/**
 * Game API Endpoint
 * Handles AJAX requests for game actions
 */

header('Content-Type: application/json');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../includes/database.php';
require_once '../classes/game_class.php';

try {
    $db = getDb();
    $action = $_POST['action'] ?? '';
    
    // Get or create active session
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE user_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessionData) {
        // Create new session
        $stmt = $db->prepare("
            INSERT INTO game_sessions (user_id, current_money) 
            SELECT ?, initial_money FROM user_settings WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $sessionId = $db->lastInsertId();
        
        // Fetch the new session
        $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $sessionId = $sessionData['session_id'];
    
    // Get user settings
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        throw new Exception("User settings not found");
    }
    
    // Get existing game from session
    $game = $_SESSION['game'] ?? null;
    $gameState = null;
    
    switch ($action) {
        case 'start_game':
            $betAmount = (float) ($_POST['bet_amount'] ?? 0);
            if ($betAmount <= 0 || $betAmount < 100) {
                throw new Exception("Minimum bet amount is $100");
            }
            if ($betAmount % 100 !== 0) {
                throw new Exception("Bet amount must be in multiples of $100");
            }
            if ($betAmount > $sessionData['current_money']) {
                throw new Exception("Insufficient funds");
            }
            
            $game = new BlackjackGame($settings, $sessionId, $db);
            $gameState = $game->startGame($betAmount);
            $_SESSION['game'] = $game;
            
            // Save complete game state
            $game->saveCompleteGameState();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Cards dealt! Make your move.',
                'gameState' => $gameState
            ]);
            exit;
            
        case 'hit':
            if (!$game) throw new Exception("No active game");
            $gameState = $game->hit();
            $game->saveCompleteGameState();
            break;
            
        case 'stand':
            if (!$game) throw new Exception("No active game");
            $gameState = $game->stand();
            $game->saveCompleteGameState();
            break;
            
        case 'double':
            if (!$game) throw new Exception("No active game");
            $gameState = $game->doubleDown();
            $game->saveCompleteGameState();
            break;
            
        case 'split':
            if (!$game) throw new Exception("No active game");
            $gameState = $game->split();
            $game->saveCompleteGameState();
            break;
            
        case 'surrender':
            if (!$game) throw new Exception("No active game");
            $gameState = $game->surrender();
            $game->saveCompleteGameState();
            break;
            
        case 'new_game':
            $_SESSION['game'] = null;
            $game = null;
            
            // Refresh session data
            $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'get_state':
            // Just return current game state
            if ($game) {
                $gameState = $game->getGameState();
            }
            break;
            
        default:
            throw new Exception("Invalid action: " . $action);
    }
    
    // Refresh session data
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $updatedSessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'gameState' => $gameState,
        'sessionData' => $updatedSessionData,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>