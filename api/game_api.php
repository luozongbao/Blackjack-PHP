<?php
/**
 * Game API
 * 
 * Handles AJAX requests for game actions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Include required files
require_once '../includes/database.php';
require_once '../classes/card_class.php';
require_once '../classes/deck_class.php';
require_once '../classes/hand_class.php';
require_once '../classes/game_class.php';

// Get the request data
$requestData = json_decode(file_get_contents('php://input'), true);
if (!$requestData) {
    $requestData = $_POST;
}

// Get the action
$action = $requestData['action'] ?? '';

// Get database connection
try {
    $db = getDb();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

// Get user ID and session
$userId = $_SESSION['user_id'];

// Get current active session
$sessionStmt = $db->prepare("
    SELECT * FROM game_sessions 
    WHERE user_id = :user_id AND is_active = 1
    ORDER BY start_time DESC LIMIT 1
");
$sessionStmt->bindParam(':user_id', $userId);
$sessionStmt->execute();
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No active session found']);
    exit;
}

$sessionId = $session['session_id'];

// Get user settings
$settingsStmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :user_id");
$settingsStmt->bindParam(':user_id', $userId);
$settingsStmt->execute();
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

// Initialize or load game
if (isset($_SESSION['game'])) {
    $game = unserialize($_SESSION['game']);
    // Restore database connection after unserialization
    $game->setDatabase($db);
} else {
    $game = new Game($db, $userId, $sessionId, $settings);
}

// Process the request
$response = ['success' => false];

switch ($action) {
    case 'startGame':
        $betAmount = floatval($requestData['betAmount'] ?? 0);
        
        // Validate bet amount
        if ($betAmount <= 0) {
            $response = ['success' => false, 'error' => 'Invalid bet amount'];
        } else if ($betAmount > $session['current_money']) {
            $response = ['success' => false, 'error' => 'Not enough money'];
        } else {
            $success = $game->startGame($betAmount);
            if ($success) {
                $response = ['success' => true, 'gameState' => $game->toArray()];
            } else {
                $response = ['success' => false, 'error' => 'Could not start game'];
            }
        }
        break;
        
    case 'hit':
        $success = $game->hit();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid hit action'];
        }
        break;
        
    case 'stand':
        $success = $game->stand();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid stand action'];
        }
        break;
        
    case 'double':
        // Check if player has enough money
        $currentHand = $game->getCurrentHand();
        if (!$currentHand) {
            $response = ['success' => false, 'error' => 'No active hand'];
            break;
        }
        
        $doubleAmount = $currentHand->getBet();
        if ($doubleAmount > $session['current_money']) {
            $response = ['success' => false, 'error' => 'Not enough money to double'];
            break;
        }
        
        $success = $game->double();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid double action'];
        }
        break;
        
    case 'split':
        // Check if player has enough money
        $currentHand = $game->getCurrentHand();
        if (!$currentHand) {
            $response = ['success' => false, 'error' => 'No active hand'];
            break;
        }
        
        $splitAmount = $currentHand->getBet();
        if ($splitAmount > $session['current_money']) {
            $response = ['success' => false, 'error' => 'Not enough money to split'];
            break;
        }
        
        $success = $game->split();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid split action'];
        }
        break;
        
    case 'surrender':
        $success = $game->surrender();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid surrender action'];
        }
        break;
        
    case 'takeInsurance':
        $insuranceAmount = floatval($requestData['insuranceAmount'] ?? 0);
        
        // Insurance should be half the original bet
        $currentHand = $game->getCurrentHand();
        if (!$currentHand) {
            $response = ['success' => false, 'error' => 'No active hand'];
            break;
        }
        
        $maxInsurance = $currentHand->getBet() / 2;
        
        // Validate insurance amount
        if ($insuranceAmount <= 0 || $insuranceAmount > $maxInsurance) {
            $response = ['success' => false, 'error' => 'Invalid insurance amount'];
        } else if ($insuranceAmount > $session['current_money']) {
            $response = ['success' => false, 'error' => 'Not enough money for insurance'];
        } else {
            $success = $game->takeInsurance($insuranceAmount);
            if ($success) {
                $response = ['success' => true, 'gameState' => $game->toArray()];
            } else {
                $response = ['success' => false, 'error' => 'Invalid insurance action'];
            }
        }
        break;
        
    case 'declineInsurance':
        $success = $game->declineInsurance();
        if ($success) {
            $response = ['success' => true, 'gameState' => $game->toArray()];
        } else {
            $response = ['success' => false, 'error' => 'Invalid insurance action'];
        }
        break;
        
    case 'getGameState':
        $response = ['success' => true, 'gameState' => $game->toArray()];
        break;
        
    case 'newGame':
        // Create a new game instance
        $game = new Game($db, $userId, $sessionId, $settings);
        $response = ['success' => true, 'gameState' => $game->toArray()];
        break;
        
    default:
        $response = ['success' => false, 'error' => 'Unknown action'];
        break;
}

// Save game state in session
try {
    // Temporarily remove database connection before serialization
    $tempDb = $game->getDatabase();
    $game->setDatabase(null);
    
    $_SESSION['game'] = serialize($game);
    
    // Restore database connection
    $game->setDatabase($tempDb);
} catch (Exception $e) {
    error_log('Serialization error: ' . $e->getMessage());
    // Create a new game instead of failing
    $game = new Game($db, $userId, $sessionId, $settings);
    
    // Remove database connection before serialization
    $game->setDatabase(null);
    $_SESSION['game'] = serialize($game);
    $game->setDatabase($db);
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);