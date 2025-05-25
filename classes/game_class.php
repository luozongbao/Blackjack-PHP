<?php
/**
 * Game class for Blackjack Game
 */

require_once 'deck_class.php';
require_once 'hand_class.php';

class BlackjackGame {
    private $deck;
    private $dealerHand;
    private $playerHands = [];
    private $currentHandIndex = 0;
    private $gameState;
    private $settings;
    private $gameId;
    private $sessionId;
    private $db;
    private $originalDeckSize;
    
    // Game states
    const STATE_BETTING = 'betting';
    const STATE_DEALING = 'dealing';
    const STATE_PLAYER_TURN = 'player_turn';
    const STATE_DEALER_TURN = 'dealer_turn';
    const STATE_GAME_OVER = 'game_over';
    
    public function __construct($settings, $sessionId, $db) {
        $this->settings = $settings;
        $this->sessionId = $sessionId;
        $this->db = $db;
        $this->gameState = self::STATE_BETTING;
        
        // Initialize deck based on settings
        $this->initializeDeck();
    }
    
    /**
     * Initialize or shuffle deck based on settings
     */
    private function initializeDeck() {
        $needNewDeck = false;
        $needShuffle = false;
        
        if (!$this->deck) {
            $needNewDeck = true;
        } elseif ($this->settings['shuffle_method'] === 'auto') {
            // Auto shuffle machine: shuffle existing deck every game, don't create new deck
            $needShuffle = true;
        } elseif ($this->settings['shuffle_method'] === 'shoe') {
            // Shuffle Every Shoe: only reshuffle when penetration threshold is reached
            if ($this->deck->needsReshuffle($this->originalDeckSize, $this->settings['deck_penetration'])) {
                $needShuffle = true;
            }
            // Otherwise, use existing deck without shuffling
        }
        
        if ($needNewDeck) {
            $this->deck = new Deck($this->settings['decks_per_shoe']);
            $this->originalDeckSize = $this->deck->getCardCount();
        } elseif ($needShuffle) {
            // Reset deck to full size and shuffle for proper reshuffle
            if ($this->settings['shuffle_method'] === 'shoe') {
                $this->deck->resetDeck($this->settings['decks_per_shoe']);
                $this->originalDeckSize = $this->deck->getCardCount();
            } else {
                // For auto shuffle, just shuffle existing cards
                $this->deck->shuffle();
            }
        }
    }

    /**
     * Set deck from preserved state (for maintaining deck across games)
     */
    public function setDeck($deck, $originalDeckSize) {
        $this->deck = $deck;
        $this->originalDeckSize = $originalDeckSize;
        
        // Re-run initialization logic to check if shuffle is needed
        if ($this->settings['shuffle_method'] === 'auto') {
            $this->deck->shuffle();
        } elseif ($this->settings['shuffle_method'] === 'shoe') {
            if ($this->deck->needsReshuffle($this->originalDeckSize, $this->settings['deck_penetration'])) {
                // Reset deck to full size and shuffle for proper reshuffle
                $this->deck->resetDeck($this->settings['decks_per_shoe']);
                $this->originalDeckSize = $this->deck->getCardCount();
            }
        }
    }

    /**
     * Get deck state for preservation
     */
    public function getDeckState() {
        return [
            'deck' => $this->deck,
            'originalSize' => $this->originalDeckSize
        ];
    }
    
    /**
     * Start a new game with initial bet
     */
    public function startGame($betAmount) {
        if ($this->gameState !== self::STATE_BETTING) {
            throw new Exception("Cannot start game in current state: " . $this->gameState);
        }
        
        // Check if player has enough money
        if (!$this->hasEnoughMoney($betAmount)) {
            throw new Exception("Insufficient funds");
        }
        
        // Deduct bet amount from current money and update session_total_bet
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET current_money = current_money - ?,
                session_total_bet = session_total_bet + ?
            WHERE session_id = ?
        ");
        $stmt->execute([$betAmount, $betAmount, $this->sessionId]);
        
        // Initialize hands
        $this->dealerHand = new Hand();
        $this->playerHands = [new Hand($betAmount)];
        $this->currentHandIndex = 0;
        
        // Deal initial cards based on deal style
        $this->dealInitialCards();
        
        // Save game to database
        $this->saveGameState();
        
        // Check for immediate blackjack
        if ($this->checkForBlackjack()) {
            return $this->endGame();
        }
        
        $this->gameState = self::STATE_PLAYER_TURN;
        return $this->getGameState();
    }
    
    /**
     * Deal initial cards based on deal style
     */
    private function dealInitialCards() {
        $dealStyle = $this->settings['deal_style'];
        
        switch ($dealStyle) {
            case 'american':
                // Player card 1, Dealer card 1 (face up), Player card 2, Dealer card 2 (face down)
                $this->playerHands[0]->addCard($this->deck->dealCard());
                $this->dealerHand->addCard($this->deck->dealCard());
                $this->playerHands[0]->addCard($this->deck->dealCard());
                $this->dealerHand->addCard($this->deck->dealCard());
                break;
                
            case 'european':
            case 'macau':
                // Player gets 2 cards, dealer gets 1 card (face up)
                $this->playerHands[0]->addCard($this->deck->dealCard());
                $this->dealerHand->addCard($this->deck->dealCard());
                $this->playerHands[0]->addCard($this->deck->dealCard());
                break;
        }
        
        $this->gameState = self::STATE_DEALING;
    }
    
    /**
     * Check for immediate blackjack scenarios
     */
    private function checkForBlackjack() {
        $playerBlackjack = $this->playerHands[0]->isBlackjack();
        $dealerMightHaveBlackjack = false;
        
        if ($this->settings['deal_style'] === 'american') {
            $dealerFirstCard = $this->dealerHand->getCards()[0];
            $dealerMightHaveBlackjack = $dealerFirstCard->getValue() === 10 || $dealerFirstCard->isAce();
            
            if ($dealerMightHaveBlackjack) {
                $dealerBlackjack = $this->dealerHand->isBlackjack();
                
                if ($dealerBlackjack && !$playerBlackjack) {
                    // Dealer wins immediately
                    $this->gameState = self::STATE_GAME_OVER;
                    return true;
                } elseif ($dealerBlackjack && $playerBlackjack) {
                    // Push
                    $this->gameState = self::STATE_GAME_OVER;
                    return true;
                }
            }
        }
        
        if ($playerBlackjack && !$dealerMightHaveBlackjack) {
            // Player wins with blackjack
            $this->gameState = self::STATE_GAME_OVER;
            return true;
        }
        
        return false;
    }
    
    /**
     * Player hits (takes another card)
     */
    public function hit() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            throw new Exception("Cannot hit in current state");
        }
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        $currentHand->addCard($this->deck->dealCard());
        
        if ($currentHand->isBusted()) {
            $this->moveToNextHand();
        }
        
        $this->saveGameState();
        return $this->getGameState();
    }
    
    /**
     * Player stands
     */
    public function stand() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            throw new Exception("Cannot stand in current state");
        }
        
        $this->playerHands[$this->currentHandIndex]->stand();
        $this->moveToNextHand();
        
        $this->saveGameState();
        return $this->getGameState();
    }
    
    /**
     * Player doubles down
     */
    public function doubleDown() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            throw new Exception("Cannot double in current state");
        }
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        $additionalBet = $currentHand->getBet();
        
        if (!$this->hasEnoughMoney($additionalBet)) {
            throw new Exception("Insufficient funds for double down");
        }
        
        // Deduct additional bet from current money and update session_total_bet
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET current_money = current_money - ?,
                session_total_bet = session_total_bet + ?
            WHERE session_id = ?
        ");
        $stmt->execute([$additionalBet, $additionalBet, $this->sessionId]);
        
        $currentHand->doubleBet();
        $currentHand->addCard($this->deck->dealCard());
        $currentHand->stand();
        
        $this->moveToNextHand();
        
        $this->saveGameState();
        return $this->getGameState();
    }
    
    /**
     * Player splits hand
     */
    public function split() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            throw new Exception("Cannot split in current state");
        }
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        
        if (!$currentHand->canSplit()) {
            throw new Exception("Cannot split this hand");
        }
        
        if (count($this->playerHands) >= $this->settings['max_splits'] + 1) {
            throw new Exception("Maximum splits reached");
        }
        
        $splitBet = $currentHand->getBet();
        if (!$this->hasEnoughMoney($splitBet)) {
            throw new Exception("Insufficient funds for split");
        }
        
        // Deduct split bet from current money and update session_total_bet
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET current_money = current_money - ?,
                session_total_bet = session_total_bet + ?
            WHERE session_id = ?
        ");
        $stmt->execute([$splitBet, $splitBet, $this->sessionId]);
        
        // Split the hand
        $secondCard = $currentHand->split();
        $newHand = new Hand($splitBet);
        $newHand->addCard($secondCard);
        
        // Insert new hand after current hand
        array_splice($this->playerHands, $this->currentHandIndex + 1, 0, [$newHand]);
        
        // Deal new cards to both hands
        $currentHand->addCard($this->deck->dealCard());
        $newHand->addCard($this->deck->dealCard());
        
        $this->saveGameState();
        return $this->getGameState();
    }
    
    /**
     * Player surrenders
     */
    public function surrender() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            throw new Exception("Cannot surrender in current state");
        }
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        $currentHand->surrender();
        
        $this->moveToNextHand();
        
        $this->saveGameState();
        return $this->getGameState();
    }
    
    /**
     * Move to next hand or dealer turn
     */
    private function moveToNextHand() {
        $this->currentHandIndex++;
        
        if ($this->currentHandIndex >= count($this->playerHands)) {
            // All hands completed, move to dealer turn
            $this->gameState = self::STATE_DEALER_TURN;
            $this->playDealerHand();
        }
    }
    
    /**
     * Play dealer's hand according to rules
     */
    private function playDealerHand() {
        // For European/Macau style, deal second card now
        if ($this->settings['deal_style'] !== 'american') {
            $this->dealerHand->addCard($this->deck->dealCard());
        }
        
        // Dealer draws according to rules
        while ($this->shouldDealerHit()) {
            $this->dealerHand->addCard($this->deck->dealCard());
        }
        
        $this->gameState = self::STATE_GAME_OVER;
        $this->endGame();
    }
    
    /**
     * Determine if dealer should hit based on rules
     */
    private function shouldDealerHit() {
        $score = $this->dealerHand->getScore();
        $isSoft = $this->dealerHand->isSoft();
        
        if ($this->settings['dealer_draw_to'] === 'any17') {
            return $score < 17;
        } else { // hard17
            return $score < 17 || ($score == 17 && $isSoft);
        }
    }
    
    /**
     * End game and calculate results
     */
    private function endGame() {
        $this->gameState = self::STATE_GAME_OVER;
        $results = $this->calculateResults();
        $this->updateSessionStats($results);
        $this->saveGameState();
        
        return array_merge($this->getGameState(), ['results' => $results]);
    }
    
    /**
     * Calculate game results and payouts
     */
    private function calculateResults() {
        $dealerScore = $this->dealerHand->getScore();
        $dealerBlackjack = $this->dealerHand->isBlackjack();
        $dealerBusted = $this->dealerHand->isBusted();
        
        $results = [];
        $totalWon = 0;
        $totalLost = 0;
        
        foreach ($this->playerHands as $index => $hand) {
            $handResult = [
                'handIndex' => $index,
                'bet' => $hand->getBet(),
                'won' => 0,
                'status' => 'lost'
            ];
            
            if ($hand->isSurrendered()) {
                $handResult['won'] = $hand->getBet() * 0.5;
                $handResult['status'] = 'surrendered';
                $totalLost += $hand->getBet() * 0.5;
            } elseif ($hand->isBusted()) {
                $handResult['status'] = 'busted';
                $totalLost += $hand->getBet();
            } elseif ($hand->isBlackjack()) {
                if ($dealerBlackjack) {
                    $handResult['won'] = $hand->getBet();
                    $handResult['status'] = 'push';
                } else {
                    $payout = $this->settings['blackjack_payout'] === '3:2' ? 1.5 : 1;
                    $handResult['won'] = $hand->getBet() * (1 + $payout);
                    $handResult['status'] = 'blackjack';
                    $totalWon += $hand->getBet() * (1 + $payout);
                }
            } elseif ($dealerBusted) {
                $handResult['won'] = $hand->getBet() * 2;
                $handResult['status'] = 'won';
                $totalWon += $hand->getBet() * 2;
            } elseif ($hand->getScore() > $dealerScore) {
                $handResult['won'] = $hand->getBet() * 2;
                $handResult['status'] = 'won';
                $totalWon += $hand->getBet() * 2;
            } elseif ($hand->getScore() == $dealerScore) {
                $handResult['won'] = $hand->getBet();
                $handResult['status'] = 'push';
            } else {
                $handResult['status'] = 'lost';
                $totalLost += $hand->getBet();
            }
            
            $results[] = $handResult;
        }
        
        return [
            'hands' => $results,
            'totalWon' => $totalWon,
            'totalLost' => $totalLost,
            'netResult' => $totalWon - $totalLost,
            'gameOutcome' => $totalWon > $totalLost ? 'won' : ($totalWon == $totalLost ? 'push' : 'lost')
        ];
    }
    
    /**
     * Check if player has enough money
     */
    private function hasEnoughMoney($amount) {
        $stmt = $this->db->prepare("SELECT current_money FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$this->sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $session && $session['current_money'] >= $amount;
    }
    
    /**
     * Update session statistics
     */
    private function updateSessionStats($results) {
        $netResult = $results['netResult'];
        $totalBet = array_sum(array_column($results['hands'], 'bet'));
        
        $gameWon = $results['gameOutcome'] === 'won' ? 1 : 0;
        $gamePush = $results['gameOutcome'] === 'push' ? 1 : 0;
        $gameLost = $results['gameOutcome'] === 'lost' ? 1 : 0;
        
        $totalWon = $results['totalWon'];
        $totalLost = $results['totalLost'];
        
        // Calculate actual winnings (excluding original bet return)
        $actualWinnings = $totalWon - $totalBet; // Net winnings only, not including bet return
        
        // Add total payout to current money (bet was already deducted when placed)
        // For stats: track actual winnings separately from total payout
        // Store actual winnings (not including bet return) as previous_game_won
        // And update accumulated_previous_wins by adding the new previous_game_won
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET current_money = current_money + ?,
                session_total_won = session_total_won + ?,
                session_total_loss = session_total_loss + ?,
                session_games_played = session_games_played + 1,
                session_games_won = session_games_won + ?,
                session_games_push = session_games_push + ?,
                session_games_lost = session_games_lost + ?,
                accumulated_previous_wins = accumulated_previous_wins + previous_game_won,
                previous_game_won = ?
            WHERE session_id = ?
        ");
        
        $stmt->execute([
            $totalWon,  // Add full payout to current money
            $actualWinnings,  // Track only net winnings for display
            $totalLost,
            $gameWon,
            $gamePush,
            $gameLost,
            $actualWinnings,  // Store actual winnings (net profit/loss) as previous_game_won
            $this->sessionId
        ]);
    }
    
    /**
     * Save current game state to database
     */
    private function saveGameState() {
        $gameData = [
            'dealer_cards' => json_encode(array_map(function($card) { 
                return $card->toArray(); 
            }, $this->dealerHand->getCards())),
            'dealer_score' => $this->dealerHand->getScore(),
            'dealer_has_blackjack' => $this->dealerHand->isBlackjack() ? 1 : 0,
            'player_hands' => json_encode(array_map(function($hand) { 
                return $hand->toArray(); 
            }, $this->playerHands)),
            'settings_snapshot' => json_encode($this->settings),
            'game_state' => $this->gameState,
            'current_hand_index' => $this->currentHandIndex
        ];
        
        if ($this->gameId) {
            // Update existing game
            $stmt = $this->db->prepare("
                UPDATE game_hands 
                SET dealer_cards = ?, dealer_score = ?, dealer_has_blackjack = ?,
                    player_hands = ?, settings_snapshot = ?
                WHERE hand_id = ?
            ");
            $stmt->execute([
                $gameData['dealer_cards'],
                $gameData['dealer_score'],
                $gameData['dealer_has_blackjack'],
                $gameData['player_hands'],
                $gameData['settings_snapshot'],
                $this->gameId
            ]);
        } else {
            // Create new game record
            $stmt = $this->db->prepare("
                INSERT INTO game_hands 
                (session_id, game_number, dealer_cards, dealer_score, dealer_has_blackjack,
                 player_hands, initial_bet, total_bet, settings_snapshot)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $initialBet = $this->playerHands[0]->getBet();
            $totalBet = array_sum(array_map(function($hand) { 
                return $hand->getBet(); 
            }, $this->playerHands));
            
            $stmt->execute([
                $this->sessionId,
                $this->getNextGameNumber(),
                $gameData['dealer_cards'],
                $gameData['dealer_score'],
                $gameData['dealer_has_blackjack'],
                $gameData['player_hands'],
                $initialBet,
                $totalBet,
                $gameData['settings_snapshot']
            ]);
            
            $this->gameId = $this->db->lastInsertId();
        }
        
        // Also save complete game state to session for easy restoration
        $this->saveCompleteGameState();
    }
    
    /**
     * Get next game number for session
     */
    private function getNextGameNumber() {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(game_number), 0) + 1 as next_number 
            FROM game_hands 
            WHERE session_id = ?
        ");
        $stmt->execute([$this->sessionId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get current penetration percentage
     */
    private function getPenetrationPercentage() {
        if (!$this->deck || !$this->originalDeckSize) {
            return 0;
        }
        
        $cardsDealt = $this->originalDeckSize - $this->deck->getCardCount();
        return ($cardsDealt / $this->originalDeckSize) * 100;
    }
    
    /**
     * Get shoe status information
     */
    private function getShoeInfo() {
        $penetrationPercentage = $this->getPenetrationPercentage();
        
        return [
            'shuffleMethod' => $this->settings['shuffle_method'],
            'penetrationPercentage' => $penetrationPercentage,
            'penetrationThreshold' => $this->settings['deck_penetration'],
            'cardsRemaining' => $this->deck ? $this->deck->getCardCount() : 0,
            'totalCards' => $this->originalDeckSize ?? 0,
            'needsReshuffle' => $this->settings['shuffle_method'] === 'shoe' && 
                              $penetrationPercentage >= $this->settings['deck_penetration']
        ];
    }
    
    /**
     * Get current game state for client
     */
    public function getGameState() {
        return [
            'gameState' => $this->gameState,
            'dealerHand' => $this->dealerHand->toArray(),
            'playerHands' => array_map(function($hand) { 
                return $hand->toArray(); 
            }, $this->playerHands),
            'currentHandIndex' => $this->currentHandIndex,
            'canHit' => $this->canHit(),
            'canStand' => $this->canStand(),
            'canDouble' => $this->canDouble(),
            'canSplit' => $this->canSplit(),
            'canSurrender' => $this->canSurrender(),
            'shoeInfo' => $this->getShoeInfo()
        ];
    }
    
    /**
     * Save complete game state to session record
     */
    private function saveCompleteGameState() {
        $gameStateData = [
            'gameState' => $this->gameState,
            'currentHandIndex' => $this->currentHandIndex,
            'dealerHand' => $this->dealerHand->toArray(),
            'playerHands' => array_map(function($hand) { 
                return $hand->toArray(); 
            }, $this->playerHands),
            'deckState' => [
                'remaining' => $this->deck->getCardCount(),
                'originalSize' => $this->originalDeckSize
            ]
        ];
        
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET game_state = ?
            WHERE session_id = ?
        ");
        $stmt->execute([
            json_encode($gameStateData),
            $this->sessionId
        ]);
    }
    
    /**
     * Create game from saved state
     */
    public static function loadFromSession($sessionId, $settings, $db) {
        $stmt = $db->prepare("SELECT game_state FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sessionData || !$sessionData['game_state']) {
            return null;
        }
        
        $gameStateData = json_decode($sessionData['game_state'], true);
        if (!$gameStateData) {
            return null;
        }
        
        $game = new self($settings, $sessionId, $db);
        
        // Restore game state
        $game->gameState = $gameStateData['gameState'];
        $game->currentHandIndex = $gameStateData['currentHandIndex'];
        
        // Restore dealer hand
        $game->dealerHand = new Hand();
        foreach ($gameStateData['dealerHand']['cards'] as $cardData) {
            $card = new Card($cardData['suit'], $cardData['rank']);
            $game->dealerHand->addCard($card);
        }
        $game->dealerHand->setBet($gameStateData['dealerHand']['bet']);
        
        // Restore player hands
        $game->playerHands = [];
        foreach ($gameStateData['playerHands'] as $handData) {
            $hand = new Hand();
            foreach ($handData['cards'] as $cardData) {
                $card = new Card($cardData['suit'], $cardData['rank']);
                $hand->addCard($card);
            }
            $hand->setBet($handData['bet']);
            if ($handData['isDoubled']) $hand->markDoubled();
            if ($handData['isStood']) $hand->markStood();
            if ($handData['isSurrendered']) $hand->markSurrendered();
            $game->playerHands[] = $hand;
        }
        
        // Restore deck state approximately
        $game->originalDeckSize = $gameStateData['deckState']['originalSize'] ?? ($settings['decks_per_shoe'] * 52);
        
        return $game;
    }

    // Action validation methods
    private function canHit() {
        return $this->gameState === self::STATE_PLAYER_TURN && 
               !$this->playerHands[$this->currentHandIndex]->isBusted() &&
               !$this->playerHands[$this->currentHandIndex]->isStood();
    }
    
    private function canStand() {
        return $this->gameState === self::STATE_PLAYER_TURN &&
               !$this->playerHands[$this->currentHandIndex]->isBusted() &&
               !$this->playerHands[$this->currentHandIndex]->isStood();
    }
    
    private function canDouble() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) return false;
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        if (count($currentHand->getCards()) != 2) return false;
        if ($currentHand->isSplit() && !$this->settings['double_after_split']) return false;
        
        if ($this->settings['double_on'] === '9-10-11') {
            $score = $currentHand->getScore();
            return in_array($score, [9, 10, 11]);
        }
        
        return true;
    }
    
    private function canSplit() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) return false;
        if (count($this->playerHands) >= $this->settings['max_splits'] + 1) return false;
        
        return $this->playerHands[$this->currentHandIndex]->canSplit();
    }
    
    private function canSurrender() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) return false;
        if ($this->settings['surrender_option'] === 'none') return false;
        
        $currentHand = $this->playerHands[$this->currentHandIndex];
        $isFirstHand = $this->currentHandIndex === 0 && count($this->playerHands) === 1;
        
        if ($this->settings['surrender_option'] === 'early') {
            return count($currentHand->getCards()) == 2 && $isFirstHand;
        } else { // late
            return $isFirstHand;
        }
    }
    
    /**
     * Clear game state from session (call when game ends)
     */
    public function clearSessionState() {
        $stmt = $this->db->prepare("
            UPDATE game_sessions 
            SET game_state = NULL
            WHERE session_id = ?
        ");
        $stmt->execute([$this->sessionId]);
    }
    
    /**
     * Handle serialization - exclude PDO object
     */
    public function __sleep() {
        return array_diff(array_keys(get_object_vars($this)), ['db']);
    }
    
    /**
     * Handle unserialization - reconnect to database
     */
    public function __wakeup() {
        global $db;
        if ($db) {
            $this->db = $db;
        } else {
            require_once __DIR__ . '/../includes/database.php';
            $this->db = getDb();
        }
    }
}