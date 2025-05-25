<?php
/**
 * Hand class for Blackjack Game
 */

require_once 'card_class.php';

class Hand {
    private $cards = [];
    private $bet = 0;
    private $isDoubled = false;
    private $isSplit = false;
    private $isStood = false;
    private $isSurrendered = false;
    
    public function __construct($bet = 0) {
        $this->bet = $bet;
    }
    
    /**
     * Add a card to the hand
     */
    public function addCard(Card $card) {
        $this->cards[] = $card;
    }
    
    /**
     * Get all cards in the hand
     */
    public function getCards() {
        return $this->cards;
    }
    
    /**
     * Calculate the best possible score for the hand
     */
    public function getScore() {
        $score = 0;
        $aces = 0;
        
        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $aces++;
                $score += 11;
            } else {
                $score += $card->getValue();
            }
        }
        
        // Adjust for aces
        while ($score > 21 && $aces > 0) {
            $score -= 10; // Convert ace from 11 to 1
            $aces--;
        }
        
        return $score;
    }
    
    /**
     * Get the hard score (all aces count as 1)
     */
    public function getHardScore() {
        $score = 0;
        
        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $score += 1;
            } else {
                $score += $card->getValue();
            }
        }
        
        return $score;
    }
    
    /**
     * Check if hand is soft (contains ace counted as 11)
     */
    public function isSoft() {
        $score = 0;
        $hasAce = false;
        
        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $hasAce = true;
                $score += 11;
            } else {
                $score += $card->getValue();
            }
        }
        
        return $hasAce && $score <= 21;
    }
    
    /**
     * Check if hand is blackjack (21 with exactly 2 cards)
     */
    public function isBlackjack() {
        return count($this->cards) == 2 && $this->getScore() == 21;
    }
    
    /**
     * Check if hand is busted (over 21)
     */
    public function isBusted() {
        return $this->getScore() > 21;
    }
    
    /**
     * Check if hand can be split (two cards of same value)
     */
    public function canSplit() {
        if (count($this->cards) != 2) {
            return false;
        }
        
        $card1 = $this->cards[0];
        $card2 = $this->cards[1];
        
        // Check if both cards have the same value (10, J, Q, K all count as 10)
        return $card1->getValue() == $card2->getValue() || 
               (in_array($card1->getRank(), ['10', 'J', 'Q', 'K']) && 
                in_array($card2->getRank(), ['10', 'J', 'Q', 'K']));
    }
    
    /**
     * Split the hand (returns the second card for new hand)
     */
    public function split() {
        if (!$this->canSplit()) {
            throw new Exception("Cannot split this hand");
        }
        
        $secondCard = array_pop($this->cards);
        $this->isSplit = true;
        
        return $secondCard;
    }
    
    // Getter and setter methods
    public function getBet() {
        return $this->bet;
    }
    
    public function setBet($bet) {
        $this->bet = $bet;
    }
    
    public function doubleBet() {
        $this->bet *= 2;
        $this->isDoubled = true;
    }
    
    public function isDoubled() {
        return $this->isDoubled;
    }
    
    public function isSplit() {
        return $this->isSplit;
    }
    
    public function stand() {
        $this->isStood = true;
    }
    
    public function isStood() {
        return $this->isStood;
    }
    
    public function surrender() {
        $this->isSurrendered = true;
    }
    
    public function isSurrendered() {
        return $this->isSurrendered;
    }
    
    /**
     * Mark hand as stood (for session restoration)
     */
    public function markStood() {
        $this->isStood = true;
    }
    
    /**
     * Mark hand as doubled (for session restoration)
     */
    public function markDoubled() {
        $this->isDoubled = true;
    }
    
    /**
     * Mark hand as surrendered (for session restoration)
     */
    public function markSurrendered() {
        $this->isSurrendered = true;
    }
    
    /**
     * Convert hand to array for JSON serialization
     */
    public function toArray() {
        return [
            'cards' => array_map(function($card) { return $card->toArray(); }, $this->cards),
            'bet' => $this->bet,
            'score' => $this->getScore(),
            'isBlackjack' => $this->isBlackjack(),
            'isBusted' => $this->isBusted(),
            'isSoft' => $this->isSoft(),
            'isDoubled' => $this->isDoubled,
            'isSplit' => $this->isSplit,
            'isStood' => $this->isStood,
            'isSurrendered' => $this->isSurrendered
        ];
    }
}