<?php
class Deck {
    private $cards = [];
    private $suits = ['♠', '♥', '♦', '♣'];
    private $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    private $deckCount;
    private $cardsDealt = 0;
    private $shuffleSettings;

    public function __construct($deckCount = 6, $shuffleSettings = []) {
        $this->deckCount = $deckCount;
        $this->shuffleSettings = array_merge([
            'method' => 'autoshuffle', // or 'penetration'
            'penetrationPercentage' => 80
        ], $shuffleSettings);
        
        $this->initializeDeck();
    }

    private function initializeDeck() {
        $this->cards = [];
        for ($d = 0; $d < $this->deckCount; $d++) {
            foreach ($this->suits as $suit) {
                foreach ($this->ranks as $rank) {
                    $this->cards[] = new Card($suit, $rank);
                }
            }
        }
        $this->shuffle();
    }

    public function shuffle() {
        shuffle($this->cards);
        $this->cardsDealt = 0;
    }

    public function drawCard() {
        if (empty($this->cards)) {
            return null;
        }

        $this->cardsDealt++;
        
        // Check if shuffle is needed based on settings
        if ($this->shouldShuffle()) {
            $this->initializeDeck();
        }
        
        return array_pop($this->cards);
    }

    private function shouldShuffle() {
        if ($this->shuffleSettings['method'] === 'autoshuffle') {
            return empty($this->cards);
        } else {
            $totalCards = 52 * $this->deckCount;
            $remainingPercentage = (count($this->cards) / $totalCards) * 100;
            return $remainingPercentage < (100 - $this->shuffleSettings['penetrationPercentage']);
        }
    }

    public function getCardsRemaining() {
        return count($this->cards);
    }

    public function getCardsDealt() {
        return $this->cardsDealt;
    }
}