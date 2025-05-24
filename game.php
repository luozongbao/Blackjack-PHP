<?php
/**
 * Blackjack Game Page
 * Main game interface for playing blackjack
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/database.php';
require_once 'classes/game_class.php';

$db = getDb();
$pageTitle = 'Game Table';

// Get or create active session
$sessionId = null;
$sessionData = null;

// Check for active session
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
} else {
    $sessionId = $sessionData['session_id'];
}

// Get user settings
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    // Create default settings
    $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
    $stmt->execute([$_SESSION['user_id']]);
    
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Initialize game if not exists in session
if (!isset($_SESSION['game'])) {
    $_SESSION['game'] = null;
}

$game = $_SESSION['game'];
$gameState = null;
$error = null;
$success = null;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'start_game':
                $betAmount = (float) ($_POST['bet_amount'] ?? 0);
                if ($betAmount <= 0) {
                    throw new Exception("Invalid bet amount");
                }
                if ($betAmount > $sessionData['current_money']) {
                    throw new Exception("Insufficient funds");
                }
                
                $game = new BlackjackGame($settings, $sessionId, $db);
                $gameState = $game->startGame($betAmount);
                $_SESSION['game'] = $game;
                break;
                
            case 'hit':
                if (!$game) throw new Exception("No active game");
                $gameState = $game->hit();
                break;
                
            case 'stand':
                if (!$game) throw new Exception("No active game");
                $gameState = $game->stand();
                break;
                
            case 'double':
                if (!$game) throw new Exception("No active game");
                $gameState = $game->doubleDown();
                break;
                
            case 'split':
                if (!$game) throw new Exception("No active game");
                $gameState = $game->split();
                break;
                
            case 'surrender':
                if (!$game) throw new Exception("No active game");
                $gameState = $game->surrender();
                break;
                
            case 'new_game':
                $_SESSION['game'] = null;
                $game = null;
                
                // Refresh session data
                $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'gameState' => $gameState,
                'sessionData' => $sessionData
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $error
            ]);
            exit;
        }
    }
}

// Get current game state if game exists
if ($game) {
    $gameState = $game->getGameState();
}

// Refresh session data for display
$stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
$stmt->execute([$sessionId]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<script src="assets/js/game.js"></script>

<div class="game-container">
    <!-- Game Header with Money and Stats -->
    <div class="card game-header">
        <div class="d-flex justify-between align-center">
            <div>
                <div class="money-display">$<?php echo number_format($sessionData['current_money'], 2); ?></div>
                <small>Current Money</small>
            </div>
            <div class="stats-row">
                <div class="stat-item">
                    <strong>Games:</strong> <?php echo $sessionData['session_games_played']; ?>
                </div>
                <div class="stat-item">
                    <strong>Won:</strong> <?php echo $sessionData['session_games_won']; ?>
                </div>
                <div class="stat-item">
                    <strong>Lost:</strong> <?php echo $sessionData['session_games_lost']; ?>
                </div>
                <div class="stat-item">
                    <strong>Push:</strong> <?php echo $sessionData['session_games_push']; ?>
                </div>
            </div>
            <div class="text-right">
                <div><strong>Total Bet:</strong> $<?php echo number_format($sessionData['session_total_bet'], 2); ?></div>
                <div><strong>Total Won:</strong> $<?php echo number_format($sessionData['session_total_won'], 2); ?></div>
                <div class="<?php echo ($sessionData['session_total_won'] - $sessionData['session_total_loss']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <strong>Net:</strong> $<?php echo number_format($sessionData['session_total_won'] - $sessionData['session_total_loss'], 2); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Dealer Section -->
    <div class="dealer-section">
        <div class="section-title">Dealer</div>
        <div class="cards-container dealer-cards" id="dealer-cards">
            <?php if ($gameState && $gameState['dealerHand']['cards']): ?>
                <?php foreach ($gameState['dealerHand']['cards'] as $index => $card): ?>
                    <?php if ($gameState['gameState'] === 'player_turn' && $index === 1 && count($gameState['dealerHand']['cards']) === 2): ?>
                        <div class="playing-card card-back" data-card="hidden">
                            <div class="card-back-design">♠</div>
                        </div>
                    <?php else: ?>
                        <div class="playing-card" 
                             data-card="<?php echo $card['rank'] . $card['suit']; ?>"
                             data-rank="<?php echo $card['rank']; ?>"
                             data-suit="<?php echo getSuitSymbol($card['suit']); ?>">
                            <div class="card-rank <?php echo getSuitColor($card['suit']); ?>"><?php echo $card['rank']; ?></div>
                            <div class="card-suit <?php echo getSuitColor($card['suit']); ?>"><?php echo getSuitSymbol($card['suit']); ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card-placeholder">Dealer Cards</div>
            <?php endif; ?>
        </div>
        <div class="hand-score" id="dealer-score">
            <?php if ($gameState): ?>
                <?php if ($gameState['gameState'] === 'player_turn'): ?>
                    Score: <?php echo $gameState['dealerHand']['cards'][0]['value']; ?> + ?
                <?php else: ?>
                    Score: <?php echo $gameState['dealerHand']['score']; ?>
                    <?php if ($gameState['dealerHand']['isBlackjack']): ?>
                        (Blackjack!)
                    <?php elseif ($gameState['dealerHand']['score'] > 21): ?>
                        (Busted!)
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Player Section -->
    <div class="player-section">
        <div class="section-title">Player</div>
        
        <?php if ($gameState && $gameState['playerHands']): ?>
            <?php foreach ($gameState['playerHands'] as $handIndex => $hand): ?>
                <div class="player-hand <?php echo $handIndex === $gameState['currentHandIndex'] ? 'active-hand' : ''; ?>" 
                     data-hand="<?php echo $handIndex; ?>">
                    
                    <div class="hand-info">
                        <span class="hand-label">
                            Hand <?php echo $handIndex + 1; ?>
                            <?php if (count($gameState['playerHands']) > 1): ?>
                                <?php if ($handIndex === $gameState['currentHandIndex'] && $gameState['gameState'] === 'player_turn'): ?>
                                    (Current)
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                        <span class="bet-amount">Bet: $<?php echo number_format($hand['bet'], 2); ?></span>
                    </div>
                    
                    <div class="cards-container player-cards">
                        <?php foreach ($hand['cards'] as $card): ?>
                            <div class="playing-card" 
                                 data-card="<?php echo $card['rank'] . $card['suit']; ?>"
                                 data-rank="<?php echo $card['rank']; ?>"
                                 data-suit="<?php echo getSuitSymbol($card['suit']); ?>">
                                <div class="card-rank <?php echo getSuitColor($card['suit']); ?>"><?php echo $card['rank']; ?></div>
                                <div class="card-suit <?php echo getSuitColor($card['suit']); ?>"><?php echo getSuitSymbol($card['suit']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="hand-score">
                        Score: <?php echo $hand['score']; ?>
                        <?php if ($hand['isSoft']): ?>(Soft)<?php endif; ?>
                        <?php if ($hand['isBlackjack']): ?>(Blackjack!)<?php endif; ?>
                        <?php if ($hand['score'] > 21): ?>(Busted!)<?php endif; ?>
                        <?php if ($hand['isSurrendered']): ?>(Surrendered)<?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="cards-container">
                <div class="card-placeholder">Player Cards</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Section -->
    <div class="card">
        <div class="action-section" id="action-section">
            <?php if (!$game || $gameState['gameState'] === 'betting'): ?>
                <!-- Betting Phase -->
                <form method="POST" id="bet-form" class="d-flex align-center">
                    <input type="hidden" name="action" value="start_game">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div class="form-group" style="margin-right: 15px;">
                        <label for="bet_amount">Bet Amount:</label>
                        <input type="number" 
                               id="bet_amount" 
                               name="bet_amount" 
                               min="1" 
                               max="<?php echo $sessionData['current_money']; ?>"
                               step="0.01" 
                               value="10"
                               class="form-control"
                               style="width: 120px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Deal Cards</button>
                </form>
                
            <?php elseif ($gameState['gameState'] === 'player_turn'): ?>
                <!-- Player Action Phase -->
                <div class="game-actions">
                    <?php if ($gameState['canHit']): ?>
                        <button class="btn btn-secondary action-button" 
                                onclick="gameAction('hit')">Hit</button>
                    <?php endif; ?>
                    
                    <?php if ($gameState['canStand']): ?>
                        <button class="btn btn-primary action-button" 
                                onclick="gameAction('stand')">Stand</button>
                    <?php endif; ?>
                    
                    <?php if ($gameState['canDouble']): ?>
                        <button class="btn btn-warning action-button" 
                                onclick="gameAction('double')">Double</button>
                    <?php endif; ?>
                    
                    <?php if ($gameState['canSplit']): ?>
                        <button class="btn btn-info action-button" 
                                onclick="gameAction('split')">Split</button>
                    <?php endif; ?>
                    
                    <?php if ($gameState['canSurrender']): ?>
                        <button class="btn btn-danger action-button" 
                                onclick="gameAction('surrender')">Surrender</button>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($gameState['gameState'] === 'game_over'): ?>
                <!-- Game Over Phase -->
                <div class="game-results">
                    <?php if (isset($gameState['results'])): ?>
                        <div class="results-summary">
                            <h3>Game Results</h3>
                            <div class="result-outcome">
                                <?php if ($gameState['results']['gameOutcome'] === 'won'): ?>
                                    <span class="text-success">You Won! +$<?php echo number_format($gameState['results']['netResult'], 2); ?></span>
                                <?php elseif ($gameState['results']['gameOutcome'] === 'lost'): ?>
                                    <span class="text-danger">You Lost! -$<?php echo number_format(abs($gameState['results']['netResult']), 2); ?></span>
                                <?php else: ?>
                                    <span class="text-info">Push! $<?php echo number_format($gameState['results']['netResult'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="hand-results">
                                <?php foreach ($gameState['results']['hands'] as $result): ?>
                                    <div class="hand-result">
                                        Hand <?php echo $result['handIndex'] + 1; ?>: 
                                        <span class="status-<?php echo $result['status']; ?>">
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                        - Won: $<?php echo number_format($result['won'], 2); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" onclick="newGame()">New Game</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Game JavaScript functionality
function gameAction(action) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('ajax', '1');
    
    fetch('game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Reload to update game state
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function newGame() {
    const formData = new FormData();
    formData.append('action', 'new_game');
    formData.append('ajax', '1');
    
    fetch('game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        location.reload();
    });
}

// Handle bet form submission
document.addEventListener('DOMContentLoaded', function() {
    const betForm = document.getElementById('bet-form');
    if (betForm) {
        betForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(betForm);
            
            fetch('game.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
});

// Prevent page navigation during active game
<?php if ($game && $gameState['gameState'] !== 'game_over' && $gameState['gameState'] !== 'betting'): ?>
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'You have an active game. Are you sure you want to leave?';
    return 'You have an active game. Are you sure you want to leave?';
});
<?php endif; ?>
</script>

<?php
// Helper functions for game display
function getSuitSymbol($suit) {
    $symbols = [
        'Hearts' => '♥',
        'Diamonds' => '♦',
        'Clubs' => '♣',
        'Spades' => '♠'
    ];
    return $symbols[$suit] ?? $suit;
}

function getSuitColor($suit) {
    return in_array($suit, ['Hearts', 'Diamonds']) ? 'red' : 'black';
}

include 'includes/footer.php';
?>