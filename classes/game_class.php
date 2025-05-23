<?php
/**
 * Game Class
 * 
 * Manages the state and rules for a Blackjack game.
 */

require_once 'deck_class.php';
require_once 'hand_class.php';

class Game {
    // Game state constants
    const STATE_WAITING_FOR_BET = 'waiting_for_bet';
    const STATE_INITIAL_DEAL = 'initial_deal';
    const STATE_INSURANCE_OFFERED = 'insurance_offered';
    const STATE_PLAYER_TURN = 'player_turn';
    const STATE_DEALER_TURN = 'dealer_turn';
    const STATE_GAME_OVER = 'game_over';
    
    // Game outcome constants
    const OUTCOME_WIN = 'win';
    const OUTCOME_LOSS = 'loss';
    const OUTCOME_PUSH = 'push';
    const OUTCOME_BLACKJACK = 'blackjack';
    const OUTCOME_SURRENDER = 'surrender';
    
    // Game properties
    private $deck;
    private $dealerHand;
    private $playerHands = [];
    private $currentHandIndex = 0;
    private $gameId;
    private $userId;
    private $sessionId;
    private $gameState;
    private $outcomes = [];
    private $totalBet = 0;
    private $totalWon = 0;
    private $db;
    private $shuffleMethod;
    private $dealStyle;
    private $dealerDrawTo;
    private $blackjackPayout;
    private $surrenderOption;
    private $doubleAfterSplit;
    private $allowInsurance;
    private $doubleOn;
    private $maxSplits;
    private $splitCount = 0;
    private $insuranceTaken = false;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param int $userId The user ID
     * @param int $sessionId The session ID
     * @param array $settings Game settings
     */
    public function __construct($db, $userId, $sessionId, $settings) {
        $this->db = $db;
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->gameId = uniqid();
        $this->gameState = self::STATE_WAITING_FOR_BET;
        
        // Apply settings
        $this->shuffleMethod = $settings['shuffle_method'] ?? 'auto';
        $this->dealStyle = $settings['deal_style'] ?? 'american';
        $this->dealerDrawTo = $settings['dealer_draw_to'] ?? 'any17';
        $this->blackjackPayout = $settings['blackjack_payout'] ?? '3:2';
        $this->surrenderOption = $settings['surrender_option'] ?? 'late';
        $this->doubleAfterSplit = $settings['double_after_split'] ?? true;
        $this->allowInsurance = $settings['allow_insurance'] ?? true;
        $this->doubleOn = $settings['double_on'] ?? 'any';
        $this->maxSplits = $settings['max_splits'] ?? 3;
        
        // Initialize the deck(s)
        $deckCount = $settings['decks_per_shoe'] ?? 6;
        $penetration = $settings['deck_penetration'] ?? 80;
        $this->deck = new Deck($deckCount, $penetration);
        
        // Initialize dealer hand
        $this->dealerHand = new Hand(true);
        
        // Create initial player hand
        $this->playerHands[] = new Hand();
    }
    
    /**
     * Start a new game with a bet
     * 
     * @param float $betAmount The bet amount
     * @return bool True if the game was successfully started
     */
    public function startGame($betAmount) {
        // Make sure we're in the right state
        if ($this->gameState !== self::STATE_WAITING_FOR_BET) {
            return false;
        }
        
        // Check if we should shuffle
        if ($this->shuffleMethod === 'auto' || 
            ($this->shuffleMethod === 'shoe' && $this->deck->shouldShuffle())) {
            $this->deck->shuffle();
        }
        
        // Set the bet on the first hand
        $this->playerHands[0]->setBet($betAmount);
        $this->totalBet = $betAmount;
        
        // Deal initial cards based on style
        switch ($this->dealStyle) {
            case 'american':
                // Player, dealer (face up), player, dealer (face down)
                $this->playerHands[0]->addCard($this->deck->dealCard(true));
                $this->dealerHand->addCard($this->deck->dealCard(true));
                $this->playerHands[0]->addCard($this->deck->dealCard(true));
                $this->dealerHand->addCard($this->deck->dealCard(false));
                break;
                
            case 'european':
            case 'macau':
                // Player, dealer (face up), player
                $this->playerHands[0]->addCard($this->deck->dealCard(true));
                $this->dealerHand->addCard($this->deck->dealCard(true));
                $this->playerHands[0]->addCard($this->deck->dealCard(true));
                break;
        }
        
        $this->gameState = self::STATE_INITIAL_DEAL;
        
        // Check for blackjack and insurance
        if ($this->dealStyle === 'american') {
            if ($this->dealerHand->getCards()[0]->getValue() === Card::VALUE_ACE && 
                $this->allowInsurance) {
                $this->gameState = self::STATE_INSURANCE_OFFERED;
            } else {
                // American style: check for dealer blackjack if face card is showing
                if ($this->dealerHand->getCards()[0]->getNumericValue() === 10) {
                    // Peek at dealer's hole card
                    if ($this->checkDealerBlackjack()) {
                        $this->endGame();
                        return true;
                    }
                }
                $this->gameState = self::STATE_PLAYER_TURN;
            }
        } else {
            $this->gameState = self::STATE_PLAYER_TURN;
        }
        
        // Check if player has blackjack
        if ($this->playerHands[0]->isBlackjack()) {
            // If European or Macau style, we need to finish the dealer's hand
            if ($this->dealStyle !== 'american') {
                $this->dealerTurn();
            } else {
                // American style and player has blackjack
                if ($this->dealerHand->isBlackjack()) {
                    // Push
                    $this->outcomes[0] = self::OUTCOME_PUSH;
                    $this->totalWon += $this->playerHands[0]->getBet();
                } else {
                    // Player blackjack wins
                    $this->outcomes[0] = self::OUTCOME_BLACKJACK;
                    $payout = $this->calculateBlackjackPayout($this->playerHands[0]->getBet());
                    $this->totalWon += $this->playerHands[0]->getBet() + $payout;
                }
                $this->endGame();
            }
        }
        
        return true;
    }
    
    /**
     * Take insurance bet
     * 
     * @param float $insuranceAmount The insurance amount
     * @return bool True if insurance was taken successfully
     */
    public function takeInsurance($insuranceAmount) {
        if ($this->gameState !== self::STATE_INSURANCE_OFFERED) {
            return false;
        }
        
        // Insurance must be half of the original bet
        $maxInsurance = $this->playerHands[0]->getBet() / 2;
        if ($insuranceAmount > $maxInsurance) {
            return false;
        }
        
        $this->playerHands[0]->setInsuranceBet($insuranceAmount);
        $this->totalBet += $insuranceAmount;
        $this->insuranceTaken = true;
        
        // Check if dealer has blackjack
        if ($this->checkDealerBlackjack()) {
            // Insurance pays 2:1
            $insuranceWin = $insuranceAmount * 2;
            $this->totalWon += $insuranceWin;
            
            if ($this->playerHands[0]->isBlackjack()) {
                // Player also has blackjack, so it's a push
                $this->outcomes[0] = self::OUTCOME_PUSH;
                $this->totalWon += $this->playerHands[0]->getBet();
            } else {
                // Player doesn't have blackjack, so they lose their bet
                $this->outcomes[0] = self::OUTCOME_LOSS;
            }
            
            $this->endGame();
            return true;
        }
        
        // Dealer doesn't have blackjack, so insurance loses
        $this->gameState = self::STATE_PLAYER_TURN;
        return true;
    }
    
    /**
     * Decline insurance
     * 
     * @return bool True if insurance was declined successfully
     */
    public function declineInsurance() {
        if ($this->gameState !== self::STATE_INSURANCE_OFFERED) {
            return false;
        }
        
        // Check if dealer has blackjack
        if ($this->checkDealerBlackjack()) {
            // Player automatically loses unless they have blackjack too
            if ($this->playerHands[0]->isBlackjack()) {
                $this->outcomes[0] = self::OUTCOME_PUSH;
                $this->totalWon += $this->playerHands[0]->getBet();
            } else {
                $this->outcomes[0] = self::OUTCOME_LOSS;
            }
            
            $this->endGame();
            return true;
        }
        
        $this->gameState = self::STATE_PLAYER_TURN;
        return true;
    }
    
    /**
     * Hit the current hand
     * 
     * @return bool True if hit was successful
     */
    public function hit() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished()) {
            return false;
        }
        
        // Deal card to the current hand
        $currentHand->addCard($this->deck->dealCard(true));
        
        // Check if hand is busted
        if ($currentHand->isBusted()) {
            $currentHand->finish();
            $this->outcomes[$this->currentHandIndex] = self::OUTCOME_LOSS;
            
            // Move to next hand or dealer turn
            $this->nextHand();
        }
        
        return true;
    }
    
    /**
     * Stand on the current hand
     * 
     * @return bool True if stand was successful
     */
    public function stand() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null) {
            return false;
        }
        
        $currentHand->finish();
        
        // Move to next hand or dealer turn
        $this->nextHand();
        
        return true;
    }
    
    /**
     * Double the bet on the current hand
     * 
     * @return bool True if double was successful
     */
    public function double() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished() || count($currentHand->getCards()) !== 2) {
            return false;
        }
        
        // Check if doubling is allowed
        if ($currentHand->isSplit() && !$this->doubleAfterSplit) {
            return false;
        }
        
        // Check if hand value meets double requirements
        if ($this->doubleOn === '9-10-11') {
            $value = $currentHand->getBestValue();
            if ($value < 9 || $value > 11) {
                return false;
            }
        }
        
        // Double the bet
        $additionalBet = $currentHand->getBet();
        $this->totalBet += $additionalBet;
        $currentHand->doubleBet();
        
        // Deal one more card and finish
        $currentHand->addCard($this->deck->dealCard(true));
        $currentHand->finish();
        
        // Check if hand busted
        if ($currentHand->isBusted()) {
            $this->outcomes[$this->currentHandIndex] = self::OUTCOME_LOSS;
        }
        
        // Move to next hand or dealer turn
        $this->nextHand();
        
        return true;
    }
    
    /**
     * Split the current hand
     * 
     * @return bool True if split was successful
     */
    public function split() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished() || !$currentHand->canSplit()) {
            return false;
        }
        
        // Check max split count
        if ($this->splitCount >= $this->maxSplits) {
            return false;
        }
        
        // Split the hand
        $newHand = $currentHand->split();
        if ($newHand === null) {
            return false;
        }
        
        // Add the split hand to the player hands array
        $this->playerHands = array_merge(
            array_slice($this->playerHands, 0, $this->currentHandIndex + 1),
            [$newHand],
            array_slice($this->playerHands, $this->currentHandIndex + 1)
        );
        
        $this->splitCount++;
        $this->totalBet += $newHand->getBet();
        
        // Deal an additional card to the current hand
        $currentHand->addCard($this->deck->dealCard(true));
        
        return true;
    }
    
    /**
     * Surrender the current hand
     * 
     * @return bool True if surrender was successful
     */
    public function surrender() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        // Can only surrender first hand
        if ($this->currentHandIndex !== 0 || count($this->playerHands) > 1) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished()) {
            return false;
        }
        
        // Check surrender option
        if ($this->surrenderOption === 'none') {
            return false;
        }
        
        // Early surrender is always allowed, late surrender requires checking dealer blackjack
        if ($this->surrenderOption === 'late' && $this->dealStyle === 'american') {
            // Check if dealer has a ten or ace up
            $dealerUpCard = $this->dealerHand->getCards()[0];
            $dealerValue = $dealerUpCard->getNumericValue();
            
            if ($dealerValue === 10 || $dealerUpCard->getValue() === Card::VALUE_ACE) {
                // Check for dealer blackjack
                if ($this->checkDealerBlackjack()) {
                    // Can't surrender against a dealer blackjack
                    return false;
                }
            }
        }
        
        // Process surrender
        $currentHand->surrender();
        $this->outcomes[$this->currentHandIndex] = self::OUTCOME_SURRENDER;
        
        // Player gets half their bet back
        $halfBet = $currentHand->getBet() / 2;
        $this->totalWon += $halfBet;
        
        // Move to next hand or end game
        $this->nextHand();
        
        return true;
    }
    
    /**
     * Move to the next hand or dealer turn if no more hands
     */
    private function nextHand() {
        $this->currentHandIndex++;
        
        // If all hands are finished, move to dealer turn or end game
        if ($this->currentHandIndex >= count($this->playerHands) || 
            $this->allHandsBustedOrSurrendered()) {
            
            if ($this->allHandsBustedOrSurrendered()) {
                $this->endGame();
            } else {
                $this->dealerTurn();
            }
        } else {
            // If we moved to a split hand and it's an Ace, automatically deal
            // a card and finish the hand per blackjack rules
            $currentHand = $this->getCurrentHand();
            if ($currentHand->isSplit() && $currentHand->getCards()[0]->getValue() === Card::VALUE_ACE) {
                $currentHand->addCard($this->deck->dealCard(true));
                $currentHand->finish();
                $this->nextHand();
            }
        }
    }
    
    /**
     * Check if all hands are busted or surrendered
     * 
     * @return bool True if all hands are busted or surrendered
     */
    private function allHandsBustedOrSurrendered() {
        foreach ($this->playerHands as $index => $hand) {
            if (!$hand->isBusted() && !$hand->isSurrendered()) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Dealer's turn
     */
    private function dealerTurn() {
        $this->gameState = self::STATE_DEALER_TURN;
        
        // If this is European or Macau style, deal the dealer's second card
        if ($this->dealStyle === 'european' || $this->dealStyle === 'macau') {
            $this->dealerHand->addCard($this->deck->dealCard(true));
            
            // Check for dealer blackjack
            if ($this->dealerHand->isBlackjack()) {
                // Handle European/Macau style blackjack rules
                for ($i = 0; $i < count($this->playerHands); $i++) {
                    $hand = $this->playerHands[$i];
                    
                    if ($hand->isBlackjack()) {
                        // Push on blackjack
                        $this->outcomes[$i] = self::OUTCOME_PUSH;
                        $this->totalWon += $hand->getBet();
                    } else if ($hand->isSurrendered()) {
                        // Already handled
                    } else if ($hand->isBusted()) {
                        // Already lost
                    } else {
                        // Loss on dealer blackjack
                        $this->outcomes[$i] = self::OUTCOME_LOSS;
                        
                        // If this is Macau style, return doubled and split bets
                        if ($this->dealStyle === 'macau' && ($hand->isDoubled() || $hand->isSplit())) {
                            // Return only the doubled portion of the bet
                            if ($hand->isDoubled()) {
                                $this->totalWon += $hand->getBet() / 2;
                            }
                            
                            // Split hands are fully returned in Macau style
                            if ($hand->isSplit()) {
                                $this->totalWon += $hand->getBet();
                            }
                        }
                    }
                }
                
                $this->endGame();
                return;
            }
        } else {
            // Flip over the dealer's hole card in American style
            $this->dealerHand->getCards()[1]->setVisible(true);
        }
        
        // Continue dealing cards to dealer until they reach their target value
        while (true) {
            $dealerValue = $this->dealerHand->getBestValue();
            
            // Determine if dealer should hit
            $shouldHit = false;
            
            if ($dealerValue < 17) {
                // Always hit below 17
                $shouldHit = true;
            } else if ($dealerValue === 17 && $this->dealerDrawTo === 'hard17' && $this->dealerHand->isSoft()) {
                // Hit on soft 17 if setting is 'hard17'
                $shouldHit = true;
            }
            
            if ($shouldHit) {
                $this->dealerHand->addCard($this->deck->dealCard(true));
            } else {
                break;
            }
        }
        
        // Determine outcomes for each hand
        for ($i = 0; $i < count($this->playerHands); $i++) {
            $hand = $this->playerHands[$i];
            
            // Skip hands that already have outcomes
            if (isset($this->outcomes[$i])) {
                continue;
            }
            
            if ($hand->isBusted()) {
                $this->outcomes[$i] = self::OUTCOME_LOSS;
            } else if ($hand->isBlackjack() && !$this->dealerHand->isBlackjack()) {
                $this->outcomes[$i] = self::OUTCOME_BLACKJACK;
                $payout = $this->calculateBlackjackPayout($hand->getBet());
                $this->totalWon += $hand->getBet() + $payout;
            } else if ($this->dealerHand->isBusted()) {
                // Dealer busts, player wins
                $this->outcomes[$i] = self::OUTCOME_WIN;
                $this->totalWon += $hand->getBet() * 2;
            } else {
                $playerValue = $hand->getBestValue();
                $dealerValue = $this->dealerHand->getBestValue();
                
                if ($playerValue > $dealerValue) {
                    // Player wins
                    $this->outcomes[$i] = self::OUTCOME_WIN;
                    $this->totalWon += $hand->getBet() * 2;
                } else if ($playerValue < $dealerValue) {
                    // Dealer wins
                    $this->outcomes[$i] = self::OUTCOME_LOSS;
                } else {
                    // Push
                    $this->outcomes[$i] = self::OUTCOME_PUSH;
                    $this->totalWon += $hand->getBet();
                }
            }
        }
        
        $this->endGame();
    }
    
    /**
     * Check if dealer has blackjack
     * 
     * @return bool True if dealer has blackjack
     */
    private function checkDealerBlackjack() {
        if ($this->dealStyle === 'american' && count($this->dealerHand->getCards()) === 2) {
            // Make dealer cards visible temporarily to check for blackjack
            $wasFaceDown = !$this->dealerHand->getCards()[1]->isVisible();
            if ($wasFaceDown) {
                $this->dealerHand->getCards()[1]->setVisible(true);
            }
            
            $hasBlackjack = $this->dealerHand->isBlackjack();
            
            // Restore visibility
            if ($wasFaceDown && !$hasBlackjack) {
                $this->dealerHand->getCards()[1]->setVisible(false);
            }
            
            return $hasBlackjack;
        }
        
        return false;
    }
    
    /**
     * Calculate blackjack payout amount
     * 
     * @param float $bet The bet amount
     * @return float The payout amount
     */
    private function calculateBlackjackPayout($bet) {
        if ($this->blackjackPayout === '3:2') {
            return $bet * 1.5;
        } else { // 1:1
            return $bet;
        }
    }
    
    /**
     * End the game, save results, and update stats
     */
    private function endGame() {
        $this->gameState = self::STATE_GAME_OVER;
        
        // Calculate game outcome for stats
        $netWin = $this->totalWon - $this->totalBet;
        $gameOutcome = 'loss';
        if ($netWin > 0) {
            $gameOutcome = 'win';
        } else if ($netWin === 0) {
            $gameOutcome = 'push';
        }
        
        // Save game data and update stats
        $this->saveGameData($gameOutcome);
    }
    
    /**
     * Save game data to database
     * 
     * @param string $gameOutcome The overall game outcome (win/loss/push)
     */
    private function saveGameData($gameOutcome) {
        // Start a transaction
        $this->db->beginTransaction();
        
        try {
            // Get current session money
            $sessionStmt = $this->db->prepare("
                SELECT current_money FROM game_sessions 
                WHERE session_id = :session_id AND is_active = 1
            ");
            $sessionStmt->bindParam(':session_id', $this->sessionId);
            $sessionStmt->execute();
            $currentMoney = $sessionStmt->fetchColumn();
            
            // Calculate new money amount
            $newMoney = $currentMoney - $this->totalBet + $this->totalWon;
            
            // Update session money and stats
            $updateStmt = $this->db->prepare("
                UPDATE game_sessions SET
                current_money = :current_money,
                session_total_loss = session_total_loss + :total_bet,
                session_total_won = session_total_won + :total_won,
                session_total_bet = session_total_bet + :total_bet,
                session_games_played = session_games_played + 1,
                session_games_won = session_games_won + :games_won,
                session_games_push = session_games_push + :games_push,
                session_games_lost = session_games_lost + :games_lost,
                all_time_total_loss = all_time_total_loss + :total_bet,
                all_time_total_won = all_time_total_won + :total_won,
                all_time_total_bet = all_time_total_bet + :total_bet,
                all_time_games_played = all_time_games_played + 1,
                all_time_games_won = all_time_games_won + :games_won,
                all_time_games_push = all_time_games_push + :games_push,
                all_time_games_lost = all_time_games_lost + :games_lost
                WHERE session_id = :session_id
            ");
            
            $gamesWon = ($gameOutcome === 'win') ? 1 : 0;
            $gamesPush = ($gameOutcome === 'push') ? 1 : 0;
            $gamesLost = ($gameOutcome === 'loss') ? 1 : 0;
            
            $updateStmt->bindParam(':current_money', $newMoney);
            $updateStmt->bindParam(':total_bet', $this->totalBet);
            $updateStmt->bindParam(':total_won', $this->totalWon);
            $updateStmt->bindParam(':games_won', $gamesWon);
            $updateStmt->bindParam(':games_push', $gamesPush);
            $updateStmt->bindParam(':games_lost', $gamesLost);
            $updateStmt->bindParam(':session_id', $this->sessionId);
            $updateStmt->execute();
            
            // Save detailed game info
            $settings = [
                'shuffle_method' => $this->shuffleMethod,
                'deal_style' => $this->dealStyle,
                'dealer_draw_to' => $this->dealerDrawTo,
                'blackjack_payout' => $this->blackjackPayout,
                'surrender_option' => $this->surrenderOption,
                'double_after_split' => $this->doubleAfterSplit,
                'allow_insurance' => $this->allowInsurance,
                'double_on' => $this->doubleOn,
                'max_splits' => $this->maxSplits
            ];
            
            $settingsJson = json_encode($settings);
            $playerHandsJson = json_encode(array_map(function ($hand) {
                return $hand->toArray();
            }, $this->playerHands));
            
            $dealerCardsJson = json_encode(array_map(function ($card) {
                return $card->toArray();
            }, $this->dealerHand->getCards()));
            
            $saveStmt = $this->db->prepare("
                INSERT INTO game_hands (
                    session_id, game_number, start_time, end_time, hand_status,
                    dealer_cards, dealer_score, dealer_has_blackjack,
                    player_hands, initial_bet, insurance_bet, total_bet, total_won,
                    settings_snapshot
                ) VALUES (
                    :session_id, 
                    (SELECT COALESCE(MAX(game_number), 0) + 1 FROM game_hands WHERE session_id = :session_id),
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :hand_status,
                    :dealer_cards, :dealer_score, :dealer_has_blackjack,
                    :player_hands, :initial_bet, :insurance_bet, :total_bet, :total_won,
                    :settings_snapshot
                )
            ");
            
            $initialBet = $this->playerHands[0]->getBet();
            $insuranceBet = $this->playerHands[0]->getInsuranceBet();
            $dealerScore = $this->dealerHand->getBestValue();
            $dealerHasBlackjack = $this->dealerHand->isBlackjack() ? 1 : 0;
            
            $saveStmt->bindParam(':session_id', $this->sessionId);
            $saveStmt->bindParam(':hand_status', $gameOutcome);
            $saveStmt->bindParam(':dealer_cards', $dealerCardsJson);
            $saveStmt->bindParam(':dealer_score', $dealerScore);
            $saveStmt->bindParam(':dealer_has_blackjack', $dealerHasBlackjack);
            $saveStmt->bindParam(':player_hands', $playerHandsJson);
            $saveStmt->bindParam(':initial_bet', $initialBet);
            $saveStmt->bindParam(':insurance_bet', $insuranceBet);
            $saveStmt->bindParam(':total_bet', $this->totalBet);
            $saveStmt->bindParam(':total_won', $this->totalWon);
            $saveStmt->bindParam(':settings_snapshot', $settingsJson);
            $saveStmt->execute();
            
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error saving game data: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the current hand
     * 
     * @return Hand|null The current hand or null if none
     */
    public function getCurrentHand() {
        if ($this->currentHandIndex < count($this->playerHands)) {
            return $this->playerHands[$this->currentHandIndex];
        }
        return null;
    }
    
    /**
     * Get the game state
     * 
     * @return string The game state
     */
    public function getGameState() {
        return $this->gameState;
    }
    
    /**
     * Check if insurance is allowed in the current state
     * 
     * @return bool True if insurance is allowed
     */
    public function canTakeInsurance() {
        return $this->gameState === self::STATE_INSURANCE_OFFERED && $this->allowInsurance;
    }
    
    /**
     * Check if doubling is allowed on the current hand
     * 
     * @return bool True if doubling is allowed
     */
    public function canDouble() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished() || count($currentHand->getCards()) !== 2) {
            return false;
        }
        
        // Check if hand is split and doubling after split is allowed
        if ($currentHand->isSplit() && !$this->doubleAfterSplit) {
            return false;
        }
        
        // Check hand value for doubling restrictions
        if ($this->doubleOn === '9-10-11') {
            $value = $currentHand->getBestValue();
            return ($value >= 9 && $value <= 11);
        }
        
        return true;
    }
    
    /**
     * Check if splitting is allowed on the current hand
     * 
     * @return bool True if splitting is allowed
     */
    public function canSplit() {
        if ($this->gameState !== self::STATE_PLAYER_TURN) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished() || !$currentHand->canSplit()) {
            return false;
        }
        
        return $this->splitCount < $this->maxSplits;
    }
    
    /**
     * Check if surrender is allowed in the current state
     * 
     * @return bool True if surrender is allowed
     */
    public function canSurrender() {
        if ($this->gameState !== self::STATE_PLAYER_TURN || $this->surrenderOption === 'none') {
            return false;
        }
        
        // Can only surrender first hand
        if ($this->currentHandIndex !== 0 || count($this->playerHands) > 1) {
            return false;
        }
        
        $currentHand = $this->getCurrentHand();
        if ($currentHand === null || $currentHand->isFinished()) {
            return false;
        }
        
        // Check surrender type
        if ($this->surrenderOption === 'early') {
            return true;
        }
        
        // Late surrender
        if ($this->dealStyle === 'american') {
            $dealerUpCard = $this->dealerHand->getCards()[0];
            if ($dealerUpCard->getValue() === Card::VALUE_ACE || $dealerUpCard->getNumericValue() === 10) {
                // Check for dealer blackjack
                if ($this->checkDealerBlackjack()) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Convert the game state to an array for front-end use
     * 
     * @return array The game state
     */
    public function toArray() {
        $dealerData = $this->dealerHand->toArray();
        
        $playerHandsData = [];
        foreach ($this->playerHands as $index => $hand) {
            $handData = $hand->toArray();
            $handData['isActive'] = ($index === $this->currentHandIndex) && $this->gameState === self::STATE_PLAYER_TURN;
            $handData['outcome'] = $this->outcomes[$index] ?? null;
            $playerHandsData[] = $handData;
        }
        
        return [
            'gameId' => $this->gameId,
            'gameState' => $this->gameState,
            'dealer' => $dealerData,
            'playerHands' => $playerHandsData,
            'currentHandIndex' => $this->currentHandIndex,
            'deck' => $this->deck->toArray(),
            'totalBet' => $this->totalBet,
            'totalWon' => $this->totalWon,
            'netWin' => $this->totalWon - $this->totalBet,
            'splitCount' => $this->splitCount,
            'canDouble' => $this->canDouble(),
            'canSplit' => $this->canSplit(),
            'canSurrender' => $this->canSurrender(),
            'canTakeInsurance' => $this->canTakeInsurance(),
            'insuranceTaken' => $this->insuranceTaken,
            'settings' => [
                'shuffleMethod' => $this->shuffleMethod,
                'dealStyle' => $this->dealStyle,
                'dealerDrawTo' => $this->dealerDrawTo,
                'blackjackPayout' => $this->blackjackPayout,
                'surrenderOption' => $this->surrenderOption,
                'doubleAfterSplit' => $this->doubleAfterSplit,
                'allowInsurance' => $this->allowInsurance,
                'doubleOn' => $this->doubleOn,
                'maxSplits' => $this->maxSplits
            ]
        ];
    }
}