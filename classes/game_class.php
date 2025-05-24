<?php
class Game {
    private $deck;
    private $dealer;
    private $playerHands = [];
    private $currentHandIndex = 0;
    private $settings;
    private $gameState = 'betting'; // betting, insurance, playing, dealer, finished

    public function __construct($settings = []) {
        $this->settings = array_merge([
            'deckCount' => 6,
            'shuffleMethod' => 'autoshuffle',
            'penetrationPercentage' => 80,
            'dealStyle' => 'american', // american, european, macau
            'dealerDrawTo' => 'any17', // any17, hard17
            'blackjackPay' => '3:2', // 3:2, 1:1
            'allowSurrender' => 'early', // early, late, none
            'allowDoubleAfterSplit' => true,
            'allowInsurance' => true,
            'allowDouble' => 'any', // any, 9to11
            'maxSplits' => 3
        ], $settings);

        $this->deck = new Deck($this->settings['deckCount'], [
            'method' => $this->settings['shuffleMethod'],
            'penetrationPercentage' => $this->settings['penetrationPercentage']
        ]);

        $this->dealer = new Hand(true);
    }

    public function placeBet($amount) {
        if ($this->gameState !== 'betting') {
            throw new Exception('Cannot place bet at this time');
        }

        $hand = new Hand();
        $hand->setBet($amount);
        $this->playerHands[] = $hand;
        
        // Deal initial cards based on deal style
        if ($this->settings['dealStyle'] === 'american') {
            $this->dealAmericanStyle();
        } else {
            $this->dealEuropeanStyle();
        }

        // Check for dealer blackjack if American style
        if ($this->settings['dealStyle'] === 'american' && 
            $this->dealer->getCards()[0]->getValue() === 10) {
            if ($this->dealer->hasBlackjack()) {
                $this->gameState = 'finished';
                return;
            }
        }

        // Check for insurance opportunity
        if ($this->settings['allowInsurance'] && 
            $this->dealer->getCards()[0]->getRank() === 'A') {
            $this->gameState = 'insurance';
        } else {
            $this->gameState = 'playing';
        }
    }

    private function dealAmericanStyle() {
        // Player card 1
        foreach ($this->playerHands as $hand) {
            $hand->addCard($this->deck->drawCard());
        }
        // Dealer card 1 (face up)
        $dealerCard = $this->deck->drawCard();
        $this->dealer->addCard($dealerCard);
        
        // Player card 2
        foreach ($this->playerHands as $hand) {
            $hand->addCard($this->deck->drawCard());
        }
        // Dealer card 2 (face down)
        $dealerCard = $this->deck->drawCard();
        $dealerCard->setHidden(true);
        $this->dealer->addCard($dealerCard);
    }

    private function dealEuropeanStyle() {
        // Player card 1
        foreach ($this->playerHands as $hand) {
            $hand->addCard($this->deck->drawCard());
        }
        // Dealer card 1 (face up)
        $dealerCard = $this->deck->drawCard();
        $this->dealer->addCard($dealerCard);
        
        // Player card 2
        foreach ($this->playerHands as $hand) {
            $hand->addCard($this->deck->drawCard());
        }
    }

    public function getCurrentHand() {
        return isset($this->playerHands[$this->currentHandIndex]) 
            ? $this->playerHands[$this->currentHandIndex] 
            : null;
    }

    public function hit() {
        if ($this->gameState !== 'playing') {
            throw new Exception('Cannot hit at this time');
        }

        $currentHand = $this->getCurrentHand();
        if (!$currentHand || $currentHand->isBusted()) {
            throw new Exception('Invalid hand for hit');
        }

        $currentHand->addCard($this->deck->drawCard());
        
        if ($currentHand->isBusted()) {
            $this->moveToNextHand();
        }
    }

    public function stand() {
        if ($this->gameState !== 'playing') {
            throw new Exception('Cannot stand at this time');
        }

        $this->moveToNextHand();
    }

    public function doubleDown() {
        if ($this->gameState !== 'playing') {
            throw new Exception('Cannot double down at this time');
        }

        $currentHand = $this->getCurrentHand();
        if (!$currentHand || count($currentHand->getCards()) !== 2) {
            throw new Exception('Can only double down on initial two cards');
        }

        if ($this->settings['allowDouble'] === '9to11') {
            $value = $currentHand->getValue();
            if ($value < 9 || $value > 11) {
                throw new Exception('Can only double down on 9, 10, or 11');
            }
        }

        $currentHand->setBet($currentHand->getBet() * 2);
        $currentHand->setDoubled(true);
        $currentHand->addCard($this->deck->drawCard());
        $this->moveToNextHand();
    }

    public function split() {
        if ($this->gameState !== 'playing') {
            throw new Exception('Cannot split at this time');
        }

        $currentHand = $this->getCurrentHand();
        if (!$currentHand || !$currentHand->canSplit()) {
            throw new Exception('Cannot split this hand');
        }

        $splitCount = 0;
        foreach ($this->playerHands as $hand) {
            if ($hand->isSplit()) {
                $splitCount++;
            }
        }

        if ($splitCount >= $this->settings['maxSplits']) {
            throw new Exception('Maximum splits reached');
        }

        $newHand = $currentHand->split();
        array_splice($this->playerHands, $this->currentHandIndex + 1, 0, [$newHand]);

        // Deal a new card to each split hand
        $currentHand->addCard($this->deck->drawCard());
        $newHand->addCard($this->deck->drawCard());
    }

    public function surrender() {
        if ($this->settings['allowSurrender'] === 'none') {
            throw new Exception('Surrender not allowed');
        }

        if ($this->settings['allowSurrender'] === 'late' && 
            $this->dealer->hasBlackjack()) {
            throw new Exception('Cannot surrender when dealer has blackjack');
        }

        $currentHand = $this->getCurrentHand();
        if (!$currentHand) {
            throw new Exception('No hand to surrender');
        }

        $currentHand->setSurrendered(true);
        $this->moveToNextHand();
    }

    public function insurance($accept) {
        if ($this->gameState !== 'insurance') {
            throw new Exception('Cannot make insurance decision at this time');
        }

        if ($accept) {
            foreach ($this->playerHands as $hand) {
                $hand->setInsurance($hand->getBet() / 2);
            }
        }

        $this->gameState = 'playing';
    }

    private function moveToNextHand() {
        $this->currentHandIndex++;
        if ($this->currentHandIndex >= count($this->playerHands)) {
            $this->startDealerTurn();
        }
    }

    private function startDealerTurn() {
        $this->gameState = 'dealer';
        
        // Reveal dealer's hole card
        foreach ($this->dealer->getCards() as $card) {
            $card->setHidden(false);
        }

        // Complete dealer's hand according to rules
        while ($this->shouldDealerHit()) {
            $this->dealer->addCard($this->deck->drawCard());
        }

        $this->gameState = 'finished';
    }

    private function shouldDealerHit() {
        $value = $this->dealer->getValue();
        
        if ($value < 17) {
            return true;
        }
        
        if ($value === 17 && $this->settings['dealerDrawTo'] === 'any17') {
            // Count aces as 11 to determine if this is a soft 17
            $sum = 0;
            $aces = 0;
            foreach ($this->dealer->getCards() as $card) {
                if ($card->getRank() === 'A') {
                    $aces++;
                } else {
                    $sum += $card->getValue();
                }
            }
            
            // If we can count an ace as 11 and still get 17, it's a soft 17
            return ($sum + $aces + 10 === 17);
        }
        
        return false;
    }

    public function getGameState() {
        $result = [
            'gameState' => $this->gameState,
            'currentHandIndex' => $this->currentHandIndex,
            'dealer' => $this->dealer->toArray(),
            'playerHands' => array_map(function($hand) {
                return $hand->toArray();
            }, $this->playerHands),
            'settings' => $this->settings
        ];

        if ($this->gameState === 'finished') {
            $result['results'] = $this->calculateResults();
        }

        return $result;
    }

    private function calculateResults() {
        $results = [];
        $dealerValue = $this->dealer->getValue();
        $dealerBlackjack = $this->dealer->hasBlackjack();
        $dealerBusted = $this->dealer->isBusted();

        foreach ($this->playerHands as $index => $hand) {
            $result = [
                'handIndex' => $index,
                'originalBet' => $hand->getBet(),
                'payout' => 0,
                'insurance' => 0,
                'outcome' => ''
            ];

            if ($hand->isSurrendered()) {
                $result['payout'] = -($hand->getBet() / 2);
                $result['outcome'] = 'surrender';
            } elseif ($dealerBlackjack) {
                if ($hand->hasBlackjack()) {
                    $result['payout'] = 0;
                    $result['outcome'] = 'push';
                } else {
                    $result['payout'] = -$hand->getBet();
                    $result['outcome'] = 'lose';
                }
                // Insurance pays 2:1
                if ($hand->getInsurance() > 0) {
                    $result['insurance'] = $hand->getInsurance() * 2;
                }
            } elseif ($hand->hasBlackjack()) {
                $multiplier = $this->settings['blackjackPay'] === '3:2' ? 1.5 : 1;
                $result['payout'] = $hand->getBet() * $multiplier;
                $result['outcome'] = 'blackjack';
            } elseif ($hand->isBusted()) {
                $result['payout'] = -$hand->getBet();
                $result['outcome'] = 'bust';
            } elseif ($dealerBusted) {
                $result['payout'] = $hand->getBet();
                $result['outcome'] = 'win';
            } else {
                $playerValue = $hand->getValue();
                if ($playerValue > $dealerValue) {
                    $result['payout'] = $hand->getBet();
                    $result['outcome'] = 'win';
                } elseif ($playerValue < $dealerValue) {
                    $result['payout'] = -$hand->getBet();
                    $result['outcome'] = 'lose';
                } else {
                    $result['payout'] = 0;
                    $result['outcome'] = 'push';
                }
            }

            $results[] = $result;
        }

        return $results;
    }
}