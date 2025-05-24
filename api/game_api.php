<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../classes/game_class.php';
require_once '../classes/deck_class.php';
require_once '../classes/hand_class.php';
require_once '../classes/card_class.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Load game settings from the database
try {
    $stmt = $pdo->prepare("SELECT * FROM game_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Use default settings
        $settings = [
            'deck_count' => 6,
            'shuffle_method' => 'autoshuffle',
            'penetration' => 80,
            'deal_style' => 'american',
            'dealer_draw_to' => 'any17',
            'blackjack_pay' => '3:2',
            'allow_surrender' => 'early',
            'allow_double_split' => 1,
            'allow_insurance' => 1,
            'allow_double' => 'any',
            'max_splits' => 3
        ];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

// Get session stats
try {
    $stmt = $pdo->prepare("SELECT current_money FROM session_stats WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        // Initialize session stats
        $stmt = $pdo->prepare("INSERT INTO session_stats (user_id, current_money) VALUES (?, 10000)");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = ['current_money' => 10000];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

// Initialize or get game from session
if (!isset($_SESSION['game'])) {
    $_SESSION['game'] = new Game([
        'deckCount' => $settings['deck_count'],
        'shuffleMethod' => $settings['shuffle_method'],
        'penetrationPercentage' => $settings['penetration'],
        'dealStyle' => $settings['deal_style'],
        'dealerDrawTo' => $settings['dealer_draw_to'],
        'blackjackPay' => $settings['blackjack_pay'],
        'allowSurrender' => $settings['allow_surrender'],
        'allowDoubleAfterSplit' => $settings['allow_double_split'],
        'allowInsurance' => $settings['allow_insurance'],
        'allowDouble' => $settings['allow_double'],
        'maxSplits' => $settings['max_splits']
    ]);
}

$game = $_SESSION['game'];
$action = $_POST['action'] ?? '';
$response = ['success' => true];

try {
    switch ($action) {
        case 'new_game':
            $_SESSION['game'] = new Game($settings);
            $response['gameState'] = $_SESSION['game']->getGameState();
            $response['balance'] = $stats['current_money'];
            break;

        case 'place_bet':
            $bet = intval($_POST['bet'] ?? 0);
            if ($bet <= 0 || $bet > $stats['current_money']) {
                throw new Exception('Invalid bet amount');
            }

            $game->placeBet($bet);
            
            // Update session money
            $stmt = $pdo->prepare("UPDATE session_stats SET current_money = current_money - ? WHERE user_id = ?");
            $stmt->execute([$bet, $_SESSION['user_id']]);
            
            $response['gameState'] = $game->getGameState();
            break;

        case 'hit':
            $game->hit();
            $response['gameState'] = $game->getGameState();
            break;

        case 'stand':
            $game->stand();
            $response['gameState'] = $game->getGameState();
            break;

        case 'double':
            $currentHand = $game->getCurrentHand();
            $doubleAmount = $currentHand->getBet();
            
            if ($doubleAmount > $stats['current_money']) {
                throw new Exception('Insufficient funds for double down');
            }

            // Update session money
            $stmt = $pdo->prepare("UPDATE session_stats SET current_money = current_money - ? WHERE user_id = ?");
            $stmt->execute([$doubleAmount, $_SESSION['user_id']]);

            $game->doubleDown();
            $response['gameState'] = $game->getGameState();
            break;

        case 'split':
            $currentHand = $game->getCurrentHand();
            $splitAmount = $currentHand->getBet();
            
            if ($splitAmount > $stats['current_money']) {
                throw new Exception('Insufficient funds for split');
            }

            // Update session money
            $stmt = $pdo->prepare("UPDATE session_stats SET current_money = current_money - ? WHERE user_id = ?");
            $stmt->execute([$splitAmount, $_SESSION['user_id']]);

            $game->split();
            $response['gameState'] = $game->getGameState();
            break;

        case 'surrender':
            $game->surrender();
            $response['gameState'] = $game->getGameState();
            break;

        case 'insurance':
            $acceptInsurance = $_POST['accept'] === 'true';
            if ($acceptInsurance) {
                $currentHand = $game->getCurrentHand();
                $insuranceAmount = $currentHand->getBet() / 2;
                
                if ($insuranceAmount > $stats['current_money']) {
                    throw new Exception('Insufficient funds for insurance');
                }

                // Update session money
                $stmt = $pdo->prepare("UPDATE session_stats SET current_money = current_money - ? WHERE user_id = ?");
                $stmt->execute([$insuranceAmount, $_SESSION['user_id']]);
            }

            $game->insurance($acceptInsurance);
            $response['gameState'] = $game->getGameState();
            break;

        default:
            throw new Exception('Invalid action');
    }

    // If game is finished, update stats
    if ($game->getGameState()['gameState'] === 'finished') {
        $results = $game->getGameState()['results'];
        $totalPayout = 0;
        $gamesWon = 0;
        $gamesLost = 0;
        $gamesPush = 0;
        
        foreach ($results as $result) {
            $totalPayout += $result['payout'] + $result['insurance'];
            
            switch ($result['outcome']) {
                case 'win':
                case 'blackjack':
                    $gamesWon++;
                    break;
                case 'lose':
                case 'bust':
                    $gamesLost++;
                    break;
                case 'push':
                    $gamesPush++;
                    break;
            }
        }

        // Update session money with payout
        $stmt = $pdo->prepare("
            UPDATE session_stats SET 
                current_money = current_money + ?,
                games_played = games_played + 1,
                games_won = games_won + ?,
                games_lost = games_lost + ?,
                games_push = games_push + ?,
                total_won = CASE WHEN ? > 0 THEN total_won + ? ELSE total_won END,
                total_loss = CASE WHEN ? < 0 THEN total_loss - ? ELSE total_loss END
            WHERE user_id = ?
        ");
        $stmt->execute([
            $totalPayout,
            $gamesWon,
            $gamesLost,
            $gamesPush,
            $totalPayout,
            max(0, $totalPayout),
            $totalPayout,
            min(0, $totalPayout),
            $_SESSION['user_id']
        ]);

        // Get updated balance
        $stmt = $pdo->prepare("SELECT current_money FROM session_stats WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['balance'] = $stats['current_money'];
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);