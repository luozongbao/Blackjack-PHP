<?php
/**
 * Blackjack Game Page
 * Main game interface for playing blackjack
 */

// Load classes BEFORE starting session to avoid deserialization errors
require_once 'includes/database.php';
require_once 'classes/game_class.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Handle AJAX requests differently
    if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    
    // Regular redirect for non-AJAX requests
    header('Location: login.php');
    exit;
}

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

// Validate game object and handle corruption
if ($game) {
    try {
        // Test if the game object is functional
        $testState = $game->getGameState();
        
        // If we get here, the game object is working
    } catch (Exception $e) {
        // Game object is corrupted, clear it and reload from database
        error_log("Game object corrupted, reloading: " . $e->getMessage());
        $_SESSION['game'] = null;
        $game = null;
    }
}

// If no game in session or corrupted, try to load from database
if (!$game) {
    try {
        $game = BlackjackGame::loadFromSession($sessionId, $settings, $db);
        if ($game) {
            $_SESSION['game'] = $game;
        }
    } catch (Exception $e) {
        // Failed to load from database, ensure clean state
        error_log("Failed to load game from session: " . $e->getMessage());
        $_SESSION['game'] = null;
        $game = null;
    }
}

// Get current game state (always check, even if game exists)
$gameState = null;
if ($game) {
    try {
        $gameState = $game->getGameState();
    } catch (Exception $e) {
        // Game state retrieval failed, clear corrupted game
        error_log("Failed to get game state: " . $e->getMessage());
        $_SESSION['game'] = null;
        $game = null;
        $gameState = null;
    }
}

$error = null;
$success = null;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle authentication test (no action specified)
    if (empty($action) && isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'gameState' => $gameState
        ]);
        exit;
    }
    
    try {
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
                
                // Only create new game if none exists or current game is not in betting state
                if (!$game || ($gameState && $gameState['gameState'] !== 'betting')) {
                    $game = new BlackjackGame($settings, $sessionId, $db);
                }
                
                $gameState = $game->startGame($betAmount);
                $_SESSION['game'] = $game;
                
                $success = "Game started successfully!";
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
                if ($game) {
                    $game->clearSessionState();
                }
                $_SESSION['game'] = null;
                $game = null;
                
                // Create a new empty game state for betting
                $gameState = [
                    'gameState' => 'betting',
                    'playerHands' => [],
                    'dealerHand' => null,
                    'currentHandIndex' => 0,
                    'canHit' => false,
                    'canStand' => false,
                    'canDouble' => false,
                    'canSplit' => false,
                    'canSurrender' => false
                ];
                
                // Refresh session data
                $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
                
            default:
                if (empty($action)) {
                    throw new Exception("No action specified");
                } else {
                    throw new Exception("Invalid action: " . $action);
                }
        }
        
        // Refresh session data after any game action to get updated money
        $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
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

// Debug: Log game state for troubleshooting
error_log("DEBUG: Game state check - gameState: " . ($gameState ? $gameState['gameState'] : 'NULL'));
if ($gameState && isset($gameState['canHit'])) {
    error_log("DEBUG: canHit: " . ($gameState['canHit'] ? 'true' : 'false'));
    error_log("DEBUG: canStand: " . ($gameState['canStand'] ? 'true' : 'false'));
    error_log("DEBUG: canDouble: " . ($gameState['canDouble'] ? 'true' : 'false'));
    error_log("DEBUG: Player hands count: " . count($gameState['playerHands']));
}

// Additional debug for display
if ($gameState) {
    echo "<!-- DEBUG: Game State: " . $gameState['gameState'] . " -->\n";
    echo "<!-- DEBUG: Can Hit: " . ($gameState['canHit'] ? 'true' : 'false') . " -->\n";
    echo "<!-- DEBUG: Can Stand: " . ($gameState['canStand'] ? 'true' : 'false') . " -->\n";
}

// Refresh session data for display
$stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
$stmt->execute([$sessionId]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

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
                <?php if ($gameState && ($gameState['gameState'] === 'player_turn' || $gameState['gameState'] === 'dealer_turn' || $gameState['gameState'] === 'game_over')): ?>
                    <!-- Show current game's total bet -->
                    <?php
                        $currentGameTotalBet = 0;
                        if (isset($gameState['playerHands'])) {
                            foreach ($gameState['playerHands'] as $hand) {
                                $currentGameTotalBet += $hand['bet'];
                            }
                        }
                    ?>
                    <div><strong>Current Game Bet:</strong> $<?php echo number_format($currentGameTotalBet, 2); ?></div>
                <?php else: ?>
                    <!-- Show $0.00 when no active game -->
                    <div><strong>Current Game Bet:</strong> $0.00</div>
                <?php endif; ?>
                <div class="<?php echo $sessionData['session_total_won'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <strong>Total Won:</strong> $<?php echo number_format($sessionData['session_total_won'], 2); ?>
                </div>
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

    <!-- Shoe Information Section -->
    <div class="card shoe-info-section" id="shoe-info">
        <?php if ($gameState && isset($gameState['shoeInfo'])): ?>
            <?php $shoeInfo = $gameState['shoeInfo']; ?>
            <div class="shoe-display">
                <div class="shoe-header">
                    <span class="shoe-title">🂠 Shoe Status</span>
                    <div class="penetration-bar">
                        <div class="penetration-progress" style="width: <?php echo min(100, $shoeInfo['penetrationPercentage']); ?>%"></div>
                    </div>
                    <span class="shuffle-method">
                        <?php if ($shoeInfo['shuffleMethod'] === 'auto'): ?>
                            🔄 Auto Shuffling Machine
                        <?php else: ?>
                            🃏 Manual Shuffle
                        <?php endif; ?>
                    </span>
                </div>
                <div class="shoe-stats">
                    <div class="penetration-display">
                        <span class="penetration-percentage"><?php echo number_format($shoeInfo['penetrationPercentage'], 1); ?>%</span>
                    </div>
                    <div class="cards-info">
                        <span class="cards-remaining">
                            <strong><?php echo $shoeInfo['cardsRemaining']; ?></strong> remaining
                        </span>
                        <span class="cards-total">
                            of <?php echo $shoeInfo['totalCards']; ?> total
                        </span>
                        <?php if ($shoeInfo['needsReshuffle']): ?>
                            <span class="reshuffle-indicator">
                                ⚠️ Reshuffle needed
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="shoe-display">
                <div class="shoe-header">
                    <span class="shoe-title">🂠 Shoe Status</span>
                    <div class="penetration-bar">
                        <div class="penetration-progress" style="width: 0%"></div>
                    </div>
                    <span class="shuffle-method">
                        <?php if (isset($settings['shuffle_method']) && $settings['shuffle_method'] === 'auto'): ?>
                            🔄 Auto Shuffling Machine
                        <?php else: ?>
                            🃏 Manual Shuffle
                        <?php endif; ?>
                    </span>
                </div>
                <div class="shoe-stats">
                    <div class="penetration-display">
                        <span class="penetration-percentage">0.0%</span>
                    </div>
                    <div class="cards-info">
                        <span class="cards-remaining">
                            <strong>-</strong> remaining
                        </span>
                        <span class="cards-total">
                            of - total
                        </span>
                    </div>
                </div>
            </div>
                        <div class="cards-total">
                            Ready for new game
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

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
            <div id="player-hands-container">
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
            </div>
        <?php else: ?>
            <div id="player-hands-container">
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
                               min="100" 
                               max="<?php echo $sessionData['current_money']; ?>"
                               step="100" 
                               value="100"
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
    console.log('gameAction called with:', action);
    const formData = new FormData();
    formData.append('action', action);
    formData.append('ajax', '1');
    
    fetch('game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            updateGameUI(data.gameState);
            updateShoeInfo(data.gameState);
        } else {
            if (data.redirect) {
                // Handle authentication redirect
                window.location.href = data.redirect;
            } else {
                alert('Error: ' + data.error);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function updateShoeInfo(gameState) {
    if (!gameState || !gameState.shoeInfo) return;
    
    const shoeInfo = gameState.shoeInfo;
    const shoeInfoSection = document.getElementById('shoe-info');
    
    if (shoeInfoSection) {
        // Update penetration percentage
        const penetrationPercentage = shoeInfoSection.querySelector('.penetration-percentage');
        if (penetrationPercentage) {
            penetrationPercentage.textContent = parseFloat(shoeInfo.penetrationPercentage).toFixed(1) + '%';
        }
        
        // Update penetration progress bar
        const penetrationProgress = shoeInfoSection.querySelector('.penetration-progress');
        if (penetrationProgress) {
            const width = Math.min(100, shoeInfo.penetrationPercentage);
            penetrationProgress.style.width = width + '%';
        }
        
        // Update cards remaining
        const cardsRemaining = shoeInfoSection.querySelector('.cards-remaining strong');
        if (cardsRemaining) {
            cardsRemaining.textContent = shoeInfo.cardsRemaining;
        }
        
        // Update reshuffle indicator
        const reshuffleIndicator = shoeInfoSection.querySelector('.reshuffle-indicator');
        if (shoeInfo.needsReshuffle) {
            if (!reshuffleIndicator) {
                const cardsInfo = shoeInfoSection.querySelector('.cards-info');
                const indicator = document.createElement('div');
                indicator.className = 'reshuffle-indicator';
                indicator.textContent = '⚠️ Reshuffle needed';
                cardsInfo.appendChild(indicator);
            }
        } else if (reshuffleIndicator) {
            reshuffleIndicator.remove();
        }
    }
}

function resetShoeInfo() {
    const shoeInfoSection = document.getElementById('shoe-info');
    
    if (shoeInfoSection) {
        // Reset penetration percentage
        const penetrationPercentage = shoeInfoSection.querySelector('.penetration-percentage');
        if (penetrationPercentage) {
            penetrationPercentage.textContent = '0.0%';
        }
        
        // Reset penetration progress bar
        const penetrationProgress = shoeInfoSection.querySelector('.penetration-progress');
        if (penetrationProgress) {
            penetrationProgress.style.width = '0%';
        }
        
        // Reset cards remaining
        const cardsRemaining = shoeInfoSection.querySelector('.cards-remaining strong');
        if (cardsRemaining) {
            cardsRemaining.textContent = '-';
        }
        
        // Update cards total message
        const cardsTotal = shoeInfoSection.querySelector('.cards-total');
        if (cardsTotal) {
            cardsTotal.textContent = 'Ready for new game';
        }
        
        // Remove reshuffle indicator
        const reshuffleIndicator = shoeInfoSection.querySelector('.reshuffle-indicator');
        if (reshuffleIndicator) {
            reshuffleIndicator.remove();
        }
    }
}

function updateGameUI(gameState) {
    if (!gameState) return;
    
    // Update dealer hand
    updateDealerHand(gameState.dealerHand, gameState.gameState);
    
    // Update player hands
    if (gameState.playerHands && gameState.playerHands.length > 0) {
        updatePlayerHands(gameState.playerHands, gameState.currentHandIndex);
    }
    
    // Update action buttons
    updateActionButtons(gameState);
    
    // Update money display
    updateMoneyDisplay(gameState);
    
    // Update game sections visibility
    updateGameSections(gameState);
}

function updateDealerHand(dealerHand, gameState) {
    const dealerCards = document.getElementById('dealer-cards');
    const dealerScore = document.getElementById('dealer-score');
    
    if (dealerCards) {
        dealerCards.innerHTML = '';
        
        dealerHand.cards.forEach((card, index) => {
            const cardElement = document.createElement('div');
            cardElement.className = 'playing-card';
            
            // Hide second card during player turn
            if (gameState === 'player_turn' && index === 1 && dealerHand.cards.length === 2) {
                cardElement.classList.add('card-back');
                cardElement.setAttribute('data-card', 'hidden');
                cardElement.innerHTML = '<div class="card-back-design">♠</div>';
            } else {
                cardElement.setAttribute('data-card', card.rank + card.suit);
                cardElement.setAttribute('data-rank', card.rank);
                cardElement.setAttribute('data-suit', getSuitSymbol(card.suit));
                
                const suitColor = getSuitColor(card.suit);
                cardElement.innerHTML = `
                    <div class="card-rank ${suitColor}">${card.rank}</div>
                    <div class="card-suit ${suitColor}">${getSuitSymbol(card.suit)}</div>
                `;
            }
            
            dealerCards.appendChild(cardElement);
        });
    }
    
    // Update dealer score
    if (dealerScore) {
        let scoreText = '';
        if (gameState === 'player_turn') {
            scoreText = `Score: ${dealerHand.cards[0].value} + ?`;
        } else {
            scoreText = `Score: ${dealerHand.score}`;
            if (dealerHand.isBlackjack) {
                scoreText += ' (Blackjack!)';
            } else if (dealerHand.score > 21) {
                scoreText += ' (Busted!)';
            }
        }
        dealerScore.textContent = scoreText;
    }
}

function updatePlayerHands(playerHands, currentHandIndex) {
    console.log('Updating player hands:', playerHands);
    
    const playerHandsContainer = document.getElementById('player-hands-container');
    if (!playerHandsContainer) {
        console.error('Player hands container not found');
        return;
    }
    
    // Clear existing content first
    playerHandsContainer.innerHTML = '';
    
    // Create player hand elements for each hand
    playerHands.forEach((hand, handIndex) => {
        console.log(`Creating player hand ${handIndex} with ${hand.cards.length} cards`);
        
        // Create the player hand element
        const handElement = document.createElement('div');
        handElement.className = `player-hand ${handIndex === currentHandIndex ? 'active-hand' : ''}`;
        handElement.setAttribute('data-hand', handIndex);
        
        // Create hand info
        const handInfo = document.createElement('div');
        handInfo.className = 'hand-info';
        
        const handLabel = document.createElement('span');
        handLabel.className = 'hand-label';
        handLabel.textContent = `Hand ${handIndex + 1}`;
        if (playerHands.length > 1 && handIndex === currentHandIndex) {
            handLabel.textContent += ' (Current)';
        }
        
        const betAmount = document.createElement('span');
        betAmount.className = 'bet-amount';
        betAmount.textContent = `Bet: $${hand.bet.toFixed(2)}`;
        
        handInfo.appendChild(handLabel);
        handInfo.appendChild(betAmount);
        
        // Create cards container
        const cardsContainer = document.createElement('div');
        cardsContainer.className = 'cards-container player-cards';
        
        // Add cards
        hand.cards.forEach(card => {
            console.log(`Adding card: ${card.rank} of ${card.suit}`);
            const cardElement = document.createElement('div');
            cardElement.className = 'playing-card';
            cardElement.setAttribute('data-card', card.rank + card.suit);
            cardElement.setAttribute('data-rank', card.rank);
            cardElement.setAttribute('data-suit', getSuitSymbol(card.suit));
            
            const suitColor = getSuitColor(card.suit);
            cardElement.innerHTML = `
                <div class="card-rank ${suitColor}">${card.rank}</div>
                <div class="card-suit ${suitColor}">${getSuitSymbol(card.suit)}</div>
            `;
            
            cardsContainer.appendChild(cardElement);
        });
        
        // Create hand score
        const handScore = document.createElement('div');
        handScore.className = 'hand-score';
        let scoreText = `Score: ${hand.score}`;
        if (hand.isSoft) scoreText += ' (Soft)';
        if (hand.isBlackjack) scoreText += ' (Blackjack!)';
        else if (hand.score > 21) scoreText += ' (Busted!)';
        if (hand.isSurrendered) scoreText += ' (Surrendered)';
        handScore.textContent = scoreText;
        
        // Assemble the hand element
        handElement.appendChild(handInfo);
        handElement.appendChild(cardsContainer);
        handElement.appendChild(handScore);
        
        // Add to container
        playerHandsContainer.appendChild(handElement);
    });
}

function updateGameSections(gameState) {
    const actionSection = document.getElementById('action-section');
    if (!actionSection) return;
    
    if (gameState.gameState === 'betting') {
        // Show betting form
        actionSection.innerHTML = `
            <form method="POST" id="bet-form" class="d-flex align-center">
                <input type="hidden" name="action" value="start_game">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group" style="margin-right: 15px;">
                    <label for="bet_amount">Bet Amount:</label>
                    <input type="number" 
                           id="bet_amount" 
                           name="bet_amount" 
                           min="100" 
                           max="${gameState.currentMoney || 1000}"
                           step="100" 
                           value="100"
                           class="form-control"
                           style="width: 120px;">
                </div>
                
                <button type="submit" class="btn btn-primary">Deal Cards</button>
            </form>
        `;
        
        // Re-attach the betting form event listener
        const newBetForm = document.getElementById('bet-form');
        if (newBetForm) {
            newBetForm.addEventListener('submit', handleBetFormSubmit);
        }
    } else if (gameState.gameState === 'player_turn') {
        // Show game actions
        actionSection.innerHTML = '<div class="game-actions"></div>';
        updateActionButtons(gameState);
    } else if (gameState.gameState === 'game_over') {
        // Show game results and new game button
        let resultText = '';
        if (gameState.results && gameState.results.length > 0) {
            resultText = gameState.results.map(result => 
                `Hand ${result.handIndex + 1}: ${result.result} - ${result.winnings >= 0 ? '+' : ''}$${result.winnings}`
            ).join('<br>');
        }
        
        actionSection.innerHTML = `
            <div class="game-results">
                ${resultText ? `<div class="results-summary">${resultText}</div>` : ''}
                <button class="btn btn-primary" onclick="newGame()">New Game</button>
            </div>
        `;
    }
}

// Helper functions that need to match PHP functions
function getSuitSymbol(suit) {
    const symbols = {
        'Hearts': '♥',
        'Diamonds': '♦',
        'Clubs': '♣',
        'Spades': '♠'
    };
    return symbols[suit] || suit;
}

function getSuitColor(suit) {
    return (suit === 'Hearts' || suit === 'Diamonds') ? 'red' : 'black';
}

function updateActionButtons(gameState) {
    const gameActions = document.querySelector('.game-actions');
    if (!gameActions) return;
    
    // Clear existing buttons
    gameActions.innerHTML = '';
    
    // Create and show available action buttons
    if (gameState.canHit) {
        const hitBtn = document.createElement('button');
        hitBtn.className = 'btn btn-secondary action-button';
        hitBtn.textContent = 'Hit';
        hitBtn.onclick = () => gameAction('hit');
        gameActions.appendChild(hitBtn);
    }
    
    if (gameState.canStand) {
        const standBtn = document.createElement('button');
        standBtn.className = 'btn btn-primary action-button';
        standBtn.textContent = 'Stand';
        standBtn.onclick = () => gameAction('stand');
        gameActions.appendChild(standBtn);
    }
    
    if (gameState.canDouble) {
        const doubleBtn = document.createElement('button');
        doubleBtn.className = 'btn btn-warning action-button';
        doubleBtn.textContent = 'Double';
        doubleBtn.onclick = () => gameAction('double');
        gameActions.appendChild(doubleBtn);
    }
    
    if (gameState.canSplit) {
        const splitBtn = document.createElement('button');
        splitBtn.className = 'btn btn-info action-button';
        splitBtn.textContent = 'Split';
        splitBtn.onclick = () => gameAction('split');
        gameActions.appendChild(splitBtn);
    }
    
    if (gameState.canSurrender) {
        const surrenderBtn = document.createElement('button');
        surrenderBtn.className = 'btn btn-danger action-button';
        surrenderBtn.textContent = 'Surrender';
        surrenderBtn.onclick = () => gameAction('surrender');
        gameActions.appendChild(surrenderBtn);
    }
}

function updateMoneyDisplay(gameState) {
    // Update money displays if they exist
    const currentMoney = document.querySelector('.current-money');
    if (currentMoney && gameState.currentMoney !== undefined) {
        currentMoney.textContent = `$${gameState.currentMoney.toLocaleString()}`;
    }
    
    // Update any other money displays
    const moneyDisplays = document.querySelectorAll('.money-amount');
    moneyDisplays.forEach(display => {
        if (gameState.currentMoney !== undefined) {
            display.textContent = `$${gameState.currentMoney.toLocaleString()}`;
        }
    });
}

// Prevent page navigation during active game
<?php if ($game && $gameState['gameState'] !== 'game_over' && $gameState['gameState'] !== 'betting'): ?>
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'You have an active game. Are you sure you want to leave?';
    return 'You have an active game. Are you sure you want to leave?';
});
<?php endif; ?>

// Handle betting form submission
function handleBetFormSubmit(e) {
    console.log('Bet form submitted');
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const betAmount = formData.get('bet_amount');
    console.log('Bet amount:', betAmount);
    
    fetch('game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Bet response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Bet response data:', data);
        if (data.success) {
            updateGameUI(data.gameState);
            updateShoeInfo(data.gameState);
        } else {
            if (data.redirect) {
                // Handle authentication redirect
                window.location.href = data.redirect;
            } else {
                alert('Error: ' + data.error);
            }
        }
    })
    .catch(error => {
        console.error('Bet error:', error);
        alert('An error occurred. Please try again.');
    });
}

// Handle betting form submission
document.addEventListener('DOMContentLoaded', function() {
    const betForm = document.getElementById('bet-form');
    if (betForm) {
        betForm.addEventListener('submit', handleBetFormSubmit);
    }
});
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