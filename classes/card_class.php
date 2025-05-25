<?php
/**
 * Card class for Blackjack Game
 */

class Card {
    private $suit;
    private $rank;
    private $value;
    
    public function __construct($suit, $rank) {
        $this->suit = $suit;
        $this->rank = $rank;
        $this->value = $this->calculateValue();
    }
    
    /**
     * Calculate the value of the card
     */
    private function calculateValue() {
        if ($this->rank === 'A') {
            return 11; // Ace defaults to 11, can be adjusted in hand calculation
        } elseif (in_array($this->rank, ['J', 'Q', 'K'])) {
            return 10;
        } else {
            return (int) $this->rank;
        }
    }
    
    public function getSuit() {
        return $this->suit;
    }
    
    public function getRank() {
        return $this->rank;
    }
    
    public function getValue() {
        return $this->value;
    }
    
    public function isAce() {
        return $this->rank === 'A';
    }
    
    /**
     * Get the filename for the card image
     */
    public function getImageFilename() {
        $suit_map = [
            'Hearts' => 'H',
            'Diamonds' => 'D',
            'Clubs' => 'C',
            'Spades' => 'S'
        ];
        
        return $this->rank . $suit_map[$this->suit] . '.png';
    }
    
    /**
     * Convert card to array for JSON serialization
     */
    public function toArray() {
        return [
            'suit' => $this->suit,
            'rank' => $this->rank,
            'value' => $this->value,
            'image' => $this->getImageFilename()
        ];
    }
    
    /**
     * Create card from array (for JSON deserialization)
     */
    public static function fromArray($data) {
        return new self($data['suit'], $data['rank']);
    }
}