<?php
class Hand {
    private $cards = [];
    private $bet = 0;
    private $insurance = 0;
    private $isDealer = false;
    private $isSplit = false;
    private $isDoubled = false;
    private $isSurrendered = false;

    public function __construct($isDealer = false) {
        $this->isDealer = $isDealer;
    }

    public function addCard(Card $card) {
        $this->cards[] = $card;
    }

    public function getCards() {
        return $this->cards;
    }

    public function setBet($amount) {
        $this->bet = $amount;
    }

    public function getBet() {
        return $this->bet;
    }

    public function setInsurance($amount) {
        $this->insurance = $amount;
    }

    public function getInsurance() {
        return $this->insurance;
    }

    public function setSplit($isSplit) {
        $this->isSplit = $isSplit;
    }

    public function isSplit() {
        return $this->isSplit;
    }

    public function setDoubled($isDoubled) {
        $this->isDoubled = $isDoubled;
    }

    public function isDoubled() {
        return $this->isDoubled;
    }

    public function setSurrendered($isSurrendered) {
        $this->isSurrendered = $isSurrendered;
    }

    public function isSurrendered() {
        return $this->isSurrendered;
    }

    public function getValue() {
        $sum = 0;
        $aces = 0;

        foreach ($this->cards as $card) {
            if (!$card->isHidden()) {
                if ($card->getRank() === 'A') {
                    $aces++;
                } else {
                    $sum += $card->getValue();
                }
            }
        }

        // Add aces last
        for ($i = 0; $i < $aces; $i++) {
            if ($sum + 11 <= 21) {
                $sum += 11;
            } else {
                $sum += 1;
            }
        }

        return $sum;
    }

    public function hasBlackjack() {
        return count($this->cards) === 2 && $this->getValue() === 21;
    }

    public function isBusted() {
        return $this->getValue() > 21;
    }

    public function canSplit() {
        if (count($this->cards) !== 2) {
            return false;
        }
        return $this->cards[0]->getValue() === $this->cards[1]->getValue();
    }

    public function split() {
        if (!$this->canSplit()) {
            return null;
        }

        $newHand = new Hand();
        $newHand->setBet($this->bet);
        $newHand->setSplit(true);
        $newHand->addCard(array_pop($this->cards));
        
        return $newHand;
    }

    public function toArray() {
        return [
            'cards' => array_map(function($card) {
                return $card->toArray();
            }, $this->cards),
            'value' => $this->getValue(),
            'bet' => $this->bet,
            'insurance' => $this->insurance,
            'isDealer' => $this->isDealer,
            'isSplit' => $this->isSplit,
            'isDoubled' => $this->isDoubled,
            'isSurrendered' => $this->isSurrendered,
            'hasBlackjack' => $this->hasBlackjack(),
            'isBusted' => $this->isBusted(),
            'canSplit' => $this->canSplit()
        ];
    }
}