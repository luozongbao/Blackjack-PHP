<?php
/**
 * Hand Class
 * 
 * Represents a hand of cards in a Blackjack game.
 */

require_once 'card_class.php';

class Hand {
    private $cards = [];
    private $bet = 0;
    private $insuranceBet = 0;
    private $isDealer = false;
    private $isBlackjack = false;
    private $isBusted = false;
    private $isDoubled = false;
    private $isSurrendered = false;
    private $isSplit = false;
    private $isFinished = false;
    
    /**
     * Constructor
     * 
     * @param bool $isDealer Whether this is the dealer's hand
     */
    public function __construct($isDealer = false) {
        $this->isDealer = $isDealer;
    }
    
    /**
     * Add a card to the hand
     * 
     * @param Card $card The card to add
     */
    public function addCard($card) {
        $this->cards[] = $card;
        $this->checkStatus();
    }
    
    /**
     * Check hand status (blackjack, busted)
     */
    private function checkStatus() {
        // Check for blackjack (only applies to hands with exactly 2 cards)
        if (count($this->cards) === 2) {
            $hasAce = false;
            $hasTenValueCard = false;
            
            foreach ($this->cards as $card) {
                if ($card->getValue() === Card::VALUE_ACE) {
                    $hasAce = true;
                } else if ($card->getNumericValue() === 10) {
                    $hasTenValueCard = true;
                }
            }
            
            $this->isBlackjack = $hasAce && $hasTenValueCard;
        }
        
        // Check if busted
        $this->isBusted = $this->getHardValue() > 21;
    }
    
    /**
     * Get the value of the hand, accounting for aces
     * 
     * @param bool $useHardValue Whether to use hard value
     * @return int The hand value
     */
    public function getValue($useHardValue = false) {
        if ($useHardValue) {
            return $this->getHardValue();
        } else {
            return $this->getSoftValue();
        }
    }
    
    /**
     * Get the soft value of the hand (treating Aces as 11 if possible)
     * 
     * @return int The soft value of the hand
     */
    public function getSoftValue() {
        // Start with hard value (Aces as 1)
        $value = $this->getHardValue();
        
        // If we can add 10 more for an Ace without busting, do so
        foreach ($this->cards as $card) {
            if ($card->getValue() === Card::VALUE_ACE && $value <= 11) {
                $value += 10;
                break; // Only convert one Ace to 11
            }
        }
        
        return $value;
    }
    
    /**
     * Get the hard value of the hand (treating Aces as 1)
     * 
     * @return int The hard value of the hand
     */
    public function getHardValue() {
        $value = 0;
        
        foreach ($this->cards as $card) {
            // Always count Aces as 1 for hard value
            $value += $card->getNumericValue(true);
        }
        
        return $value;
    }
    
    /**
     * Check if the hand is soft (contains an Ace counted as 11)
     * 
     * @return bool True if the hand is soft
     */
    public function isSoft() {
        return $this->getSoftValue() !== $this->getHardValue();
    }
    
    /**
     * Get the best hand value (highest without busting)
     * 
     * @return int The best hand value
     */
    public function getBestValue() {
        $softValue = $this->getSoftValue();
        return ($softValue <= 21) ? $softValue : $this->getHardValue();
    }
    
    /**
     * Get the cards in the hand
     * 
     * @return array The cards
     */
    public function getCards() {
        return $this->cards;
    }
    
    /**
     * Check if the hand can be split 
     * (has exactly two cards of the same value)
     * 
     * @return bool True if the hand can be split
     */
    public function canSplit() {
        if (count($this->cards) !== 2) {
            return false;
        }
        
        $firstValue = $this->cards[0]->getNumericValue();
        $secondValue = $this->cards[1]->getNumericValue();
        
        return $firstValue === $secondValue;
    }
    
    /**
     * Split the hand and return a new hand with the second card
     * 
     * @return Hand The new split hand
     */
    public function split() {
        if (!$this->canSplit()) {
            return null;
        }
        
        $this->isSplit = true;
        $newHand = new Hand($this->isDealer);
        $newHand->isSplit = true;
        $newHand->bet = $this->bet;
        
        // Take the second card and add it to the new hand
        $newHand->addCard(array_pop($this->cards));
        
        return $newHand;
    }
    
    /**
     * Set the bet amount for the hand
     * 
     * @param float $amount The bet amount
     */
    public function setBet($amount) {
        $this->bet = $amount;
    }
    
    /**
     * Get the bet amount for the hand
     * 
     * @return float The bet amount
     */
    public function getBet() {
        return $this->bet;
    }
    
    /**
     * Set the insurance bet amount
     * 
     * @param float $amount The insurance bet amount
     */
    public function setInsuranceBet($amount) {
        $this->insuranceBet = $amount;
    }
    
    /**
     * Get the insurance bet amount
     * 
     * @return float The insurance bet amount
     */
    public function getInsuranceBet() {
        return $this->insuranceBet;
    }
    
    /**
     * Mark hand as doubled
     */
    public function doubleBet() {
        if (count($this->cards) === 2 && !$this->isFinished && !$this->isDoubled) {
            $this->isDoubled = true;
            // Double the bet amount will be handled by Game class
        }
    }
    
    /**
     * Check if the hand is doubled
     * 
     * @return bool True if the hand is doubled
     */
    public function isDoubled() {
        return $this->isDoubled;
    }
    
    /**
     * Mark hand as surrendered
     */
    public function surrender() {
        $this->isSurrendered = true;
        $this->isFinished = true;
    }
    
    /**
     * Check if the hand is surrendered
     * 
     * @return bool True if the hand is surrendered
     */
    public function isSurrendered() {
        return $this->isSurrendered;
    }
    
    /**
     * Mark the hand as finished (no more actions)
     */
    public function finish() {
        $this->isFinished = true;
    }
    
    /**
     * Check if the hand is finished
     * 
     * @return bool True if the hand is finished
     */
    public function isFinished() {
        return $this->isFinished || $this->isBlackjack || $this->isBusted || $this->isSurrendered;
    }
    
    /**
     * Check if the hand is blackjack
     * 
     * @return bool True if the hand is blackjack
     */
    public function isBlackjack() {
        return $this->isBlackjack;
    }
    
    /**
     * Check if the hand is busted
     * 
     * @return bool True if the hand is busted
     */
    public function isBusted() {
        return $this->isBusted;
    }
    
    /**
     * Check if the hand is a split hand
     * 
     * @return bool True if the hand is a split hand
     */
    public function isSplit() {
        return $this->isSplit;
    }
    
    /**
     * Convert the hand to an array for front-end use
     * 
     * @return array The hand data
     */
    public function toArray() {
        $cardData = [];
        foreach ($this->cards as $card) {
            $cardData[] = $card->toArray();
        }
        
        return [
            'cards' => $cardData,
            'softValue' => $this->getSoftValue(),
            'hardValue' => $this->getHardValue(),
            'bestValue' => $this->getBestValue(),
            'isSoft' => $this->isSoft(),
            'isBlackjack' => $this->isBlackjack,
            'isBusted' => $this->isBusted,
            'isDoubled' => $this->isDoubled,
            'isSurrendered' => $this->isSurrendered,
            'isDealer' => $this->isDealer,
            'isSplit' => $this->isSplit,
            'isFinished' => $this->isFinished(),
            'bet' => $this->bet,
            'insuranceBet' => $this->insuranceBet
        ];
    }
}