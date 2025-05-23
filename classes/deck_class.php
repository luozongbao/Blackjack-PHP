<?php
/**
 * Deck Class
 * 
 * Represents a deck of cards (or multiple decks in a shoe) for a Blackjack game.
 */

require_once 'card_class.php';

class Deck {
    private $cards = [];
    private $discardPile = [];
    private $deckCount;
    private $penetration;
    private $cardsDealt = 0;
    
    /**
     * Constructor
     * 
     * @param int $deckCount The number of decks to use
     * @param int $penetration The deck penetration percentage (50-100)
     */
    public function __construct($deckCount = 1, $penetration = 80) {
        $this->deckCount = $deckCount;
        $this->penetration = $penetration;
        $this->initializeDeck();
    }
    
    /**
     * Initialize the deck(s) with cards
     */
    private function initializeDeck() {
        $this->cards = [];
        $this->discardPile = [];
        $this->cardsDealt = 0;
        
        $suits = [
            Card::SUIT_HEARTS,
            Card::SUIT_DIAMONDS, 
            Card::SUIT_CLUBS, 
            Card::SUIT_SPADES
        ];
        
        $values = [
            Card::VALUE_ACE,
            Card::VALUE_TWO,
            Card::VALUE_THREE,
            Card::VALUE_FOUR,
            Card::VALUE_FIVE,
            Card::VALUE_SIX,
            Card::VALUE_SEVEN,
            Card::VALUE_EIGHT,
            Card::VALUE_NINE,
            Card::VALUE_TEN,
            Card::VALUE_JACK,
            Card::VALUE_QUEEN,
            Card::VALUE_KING
        ];
        
        // Add cards for each deck in the shoe
        for ($deck = 0; $deck < $this->deckCount; $deck++) {
            foreach ($suits as $suit) {
                foreach ($values as $value) {
                    $this->cards[] = new Card($suit, $value);
                }
            }
        }
        
        $this->shuffle();
    }
    
    /**
     * Shuffle the deck
     */
    public function shuffle() {
        // Add any discarded cards back to the deck
        $this->cards = array_merge($this->cards, $this->discardPile);
        $this->discardPile = [];
        $this->cardsDealt = 0;
        
        // Shuffle the deck
        shuffle($this->cards);
    }
    
    /**
     * Deal a card from the deck
     * 
     * @param bool $isVisible Whether the card should be visible
     * @return Card|null The dealt card or null if deck is empty
     */
    public function dealCard($isVisible = true) {
        if (count($this->cards) === 0) {
            return null;
        }
        
        $card = array_pop($this->cards);
        $card->setVisible($isVisible);
        $this->cardsDealt++;
        
        return $card;
    }
    
    /**
     * Add a card to the discard pile
     * 
     * @param Card $card The card to discard
     */
    public function discard($card) {
        $this->discardPile[] = $card;
    }
    
    /**
     * Check if the deck should be shuffled based on penetration
     * 
     * @return bool True if the deck should be shuffled
     */
    public function shouldShuffle() {
        $totalCards = $this->deckCount * 52;
        $cardsRemaining = count($this->cards);
        $percentageUsed = (($totalCards - $cardsRemaining) / $totalCards) * 100;
        
        return $percentageUsed >= $this->penetration;
    }
    
    /**
     * Get the number of cards remaining in the deck
     * 
     * @return int The number of cards remaining
     */
    public function getCardsRemaining() {
        return count($this->cards);
    }
    
    /**
     * Get the total number of cards in the deck(s)
     * 
     * @return int The total number of cards
     */
    public function getTotalCards() {
        return $this->deckCount * 52;
    }
    
    /**
     * Get the percentage of cards dealt
     * 
     * @return float The percentage of cards dealt
     */
    public function getPercentageDealt() {
        $totalCards = $this->getTotalCards();
        return ($this->cardsDealt / $totalCards) * 100;
    }
    
    /**
     * Return the deck and discard pile status as array
     * 
     * @return array The deck status
     */
    public function toArray() {
        return [
            'deckCount' => $this->deckCount,
            'cardsRemaining' => $this->getCardsRemaining(),
            'cardsDealt' => $this->cardsDealt,
            'percentageDealt' => $this->getPercentageDealt()
        ];
    }
}