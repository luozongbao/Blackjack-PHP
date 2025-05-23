<?php
/**
 * Card Class
 * 
 * Represents a playing card in a Blackjack game.
 */

class Card {
    // Card properties
    private $suit;
    private $value;
    private $isVisible = true;
    
    // Suit constants
    const SUIT_HEARTS = 'hearts';
    const SUIT_DIAMONDS = 'diamonds';
    const SUIT_CLUBS = 'clubs';
    const SUIT_SPADES = 'spades';
    
    // Value constants
    const VALUE_ACE = 'A';
    const VALUE_KING = 'K';
    const VALUE_QUEEN = 'Q';
    const VALUE_JACK = 'J';
    const VALUE_TEN = '10';
    const VALUE_NINE = '9';
    const VALUE_EIGHT = '8';
    const VALUE_SEVEN = '7';
    const VALUE_SIX = '6';
    const VALUE_FIVE = '5';
    const VALUE_FOUR = '4';
    const VALUE_THREE = '3';
    const VALUE_TWO = '2';
    
    /**
     * Constructor
     * 
     * @param string $suit The card suit (hearts, diamonds, clubs, spades)
     * @param string $value The card value (A, 2-10, J, Q, K)
     */
    public function __construct($suit, $value) {
        $this->suit = $suit;
        $this->value = $value;
    }
    
    /**
     * Get the card suit
     * 
     * @return string The card suit
     */
    public function getSuit() {
        return $this->suit;
    }
    
    /**
     * Get the card value
     * 
     * @return string The card value
     */
    public function getValue() {
        return $this->value;
    }
    
    /**
     * Get the display value of the card (what shows on the card)
     * 
     * @return string The display value
     */
    public function getDisplayValue() {
        return $this->value;
    }
    
    /**
     * Get the numeric value of the card for scoring
     * 
     * @param bool $countAceAsOne Force Ace to be counted as 1
     * @return int The numeric value
     */
    public function getNumericValue($countAceAsOne = false) {
        switch ($this->value) {
            case self::VALUE_ACE:
                return $countAceAsOne ? 1 : 11;
            case self::VALUE_KING:
            case self::VALUE_QUEEN:
            case self::VALUE_JACK:
            case self::VALUE_TEN:
                return 10;
            default:
                return intval($this->value);
        }
    }
    
    /**
     * Set card visibility
     * 
     * @param bool $isVisible True if the card is face up
     */
    public function setVisible($isVisible) {
        $this->isVisible = $isVisible;
    }
    
    /**
     * Check if the card is visible
     * 
     * @return bool True if the card is face up
     */
    public function isVisible() {
        return $this->isVisible;
    }
    
    /**
     * Convert the card to JSON for front-end use
     * 
     * @return array Card data
     */
    public function toArray() {
        return [
            'suit' => $this->suit,
            'value' => $this->value,
            'isVisible' => $this->isVisible
        ];
    }
}