<?php
/**
 * Deck class for Blackjack Game
 */

require_once 'card_class.php';

class Deck {
    private $cards = [];
    private $suits = ['Hearts', 'Diamonds', 'Clubs', 'Spades'];
    private $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    
    public function __construct($numDecks = 1) {
        $this->createDeck($numDecks);
        $this->shuffle();
    }
    
    /**
     * Create a deck with the specified number of standard 52-card decks
     */
    private function createDeck($numDecks) {
        $this->cards = [];
        
        for ($deck = 0; $deck < $numDecks; $deck++) {
            foreach ($this->suits as $suit) {
                foreach ($this->ranks as $rank) {
                    $this->cards[] = new Card($suit, $rank);
                }
            }
        }
    }
    
    /**
     * Shuffle the deck
     */
    public function shuffle() {
        shuffle($this->cards);
    }
    
    /**
     * Deal a card from the top of the deck
     */
    public function dealCard() {
        if (empty($this->cards)) {
            throw new Exception("Cannot deal from empty deck");
        }
        return array_pop($this->cards);
    }
    
    /**
     * Get the number of cards remaining in the deck
     */
    public function getCardCount() {
        return count($this->cards);
    }
    
    /**
     * Check if deck needs reshuffling based on penetration percentage
     */
    public function needsReshuffle($originalCardCount, $penetrationPercent) {
        $cardsDealt = $originalCardCount - $this->getCardCount();
        $penetrationReached = ($cardsDealt / $originalCardCount) * 100;
        return $penetrationReached >= $penetrationPercent;
    }
    
    /**
     * Reset deck with new number of decks
     */
    public function resetDeck($numDecks) {
        $this->createDeck($numDecks);
        $this->shuffle();
    }
}