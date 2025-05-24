<?php
class Card {
    private $suit;
    private $rank;
    private $value;
    private $isHidden = false;

    public function __construct($suit, $rank) {
        $this->suit = $suit;
        $this->rank = $rank;
        $this->setValue();
    }

    private function setValue() {
        if (in_array($this->rank, ['J', 'Q', 'K'])) {
            $this->value = 10;
        } elseif ($this->rank === 'A') {
            $this->value = 11; // Ace can be 1 or 11, this is handled in Hand class
        } else {
            $this->value = intval($this->rank);
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

    public function setHidden($hidden) {
        $this->isHidden = $hidden;
    }

    public function isHidden() {
        return $this->isHidden;
    }

    public function toArray() {
        return [
            'suit' => $this->suit,
            'rank' => $this->rank,
            'value' => $this->value,
            'isHidden' => $this->isHidden
        ];
    }
}