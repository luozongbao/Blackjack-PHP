document.addEventListener('DOMContentLoaded', () => {
    const gameContainer = document.querySelector('.game-container');
    const dealerCards = document.getElementById('dealer-cards');
    const dealerScore = document.getElementById('dealer-score');
    const playerHands = document.getElementById('player-hands');
    const betSection = document.querySelector('.betting-actions');
    const gameActions = document.querySelector('.game-actions');
    const betInput = document.getElementById('bet-amount');
    const betBtn = document.getElementById('bet-btn');
    const currentBalance = document.getElementById('current-balance');
    const gameMessage = document.getElementById('game-message');

    let gameState = null;

    // Start a new game
    function initGame() {
        fetch('api/game_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=new_game'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gameState = data.gameState;
                currentBalance.textContent = data.balance;
                showBettingUI();
            }
        })
        .catch(err => console.error('Error:', err));
    }

    // Place a bet and start the round
    function placeBet() {
        const bet = parseInt(betInput.value);
        if (isNaN(bet) || bet < 1) {
            alert('Please enter a valid bet amount');
            return;
        }

        fetch('api/game_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=place_bet&bet=${bet}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gameState = data.gameState;
                updateGameUI();
            } else {
                alert(data.error);
            }
        })
        .catch(err => console.error('Error:', err));
    }

    // Handle game actions (hit, stand, etc.)
    function handleGameAction(action, params = {}) {
        const formData = new URLSearchParams({
            action,
            ...params
        });

        fetch('api/game_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                gameState = data.gameState;
                if (data.balance !== undefined) {
                    currentBalance.textContent = data.balance;
                }
                updateGameUI();
            } else {
                alert(data.error);
            }
        })
        .catch(err => console.error('Error:', err));
    }

    // Update the game UI based on current state
    function updateGameUI() {
        // Update dealer's cards
        updateDealerCards();

        // Update player's hands
        updatePlayerHands();

        // Update action buttons
        updateActionButtons();

        // Show game message if finished
        if (gameState.gameState === 'finished') {
            showGameResults();
        }
    }

    function updateDealerCards() {
        dealerCards.innerHTML = '';
        gameState.dealer.cards.forEach(card => {
            dealerCards.appendChild(createCardElement(card));
        });
        dealerScore.textContent = `Score: ${gameState.dealer.value}`;
    }

    function updatePlayerHands() {
        playerHands.innerHTML = '';
        gameState.playerHands.forEach((hand, index) => {
            const handDiv = document.createElement('div');
            handDiv.className = 'player-hand';
            handDiv.dataset.handIndex = index;

            if (index === gameState.currentHandIndex) {
                handDiv.classList.add('active-hand');
            }

            const cardsDiv = document.createElement('div');
            cardsDiv.className = 'hand-cards';
            hand.cards.forEach(card => {
                cardsDiv.appendChild(createCardElement(card));
            });

            const scoreDiv = document.createElement('div');
            scoreDiv.className = 'hand-score';
            scoreDiv.textContent = `Score: ${hand.value}`;

            const betDiv = document.createElement('div');
            betDiv.className = 'hand-bet';
            betDiv.textContent = `Bet: $${hand.bet}`;

            handDiv.appendChild(cardsDiv);
            handDiv.appendChild(scoreDiv);
            handDiv.appendChild(betDiv);
            playerHands.appendChild(handDiv);
        });
    }

    function createCardElement(card) {
        const cardDiv = document.createElement('div');
        cardDiv.className = `card ${card.isHidden ? 'face-down' : ''}`;
        
        if (!card.isHidden) {
            cardDiv.classList.add(card.suit === '♥' || card.suit === '♦' ? 'red' : 'black');
            
            const rankDiv = document.createElement('div');
            rankDiv.className = 'rank';
            rankDiv.textContent = card.rank;
            
            const suitDiv = document.createElement('div');
            suitDiv.className = 'suit';
            suitDiv.textContent = card.suit;
            
            cardDiv.appendChild(rankDiv);
            cardDiv.appendChild(suitDiv);
        }
        
        return cardDiv;
    }

    function updateActionButtons() {
        betSection.style.display = gameState.gameState === 'betting' ? 'flex' : 'none';
        gameActions.style.display = gameState.gameState !== 'betting' ? 'flex' : 'none';

        // Hide all action buttons first
        document.querySelectorAll('.game-btn:not(.bet-btn)').forEach(btn => {
            btn.style.display = 'none';
        });

        if (gameState.gameState === 'playing') {
            const currentHand = gameState.playerHands[gameState.currentHandIndex];
            
            // Show relevant buttons based on game state and current hand
            document.querySelector('.hit-btn').style.display = 'inline-block';
            document.querySelector('.stand-btn').style.display = 'inline-block';
            
            if (currentHand.cards.length === 2) {
                if (currentHand.canSplit) {
                    document.querySelector('.split-btn').style.display = 'inline-block';
                }
                document.querySelector('.double-btn').style.display = 'inline-block';
                document.querySelector('.surrender-btn').style.display = 'inline-block';
            }
        } else if (gameState.gameState === 'insurance') {
            document.querySelector('.insurance-btn').style.display = 'inline-block';
            document.querySelector('.no-insurance-btn').style.display = 'inline-block';
        }
    }

    function showGameResults() {
        const results = gameState.results;
        let message = '';

        results.forEach((result, index) => {
            message += `Hand ${index + 1}: `;
            switch (result.outcome) {
                case 'blackjack':
                    message += 'Blackjack! ';
                    break;
                case 'win':
                    message += 'Won! ';
                    break;
                case 'lose':
                    message += 'Lost. ';
                    break;
                case 'push':
                    message += 'Push. ';
                    break;
                case 'bust':
                    message += 'Bust! ';
                    break;
                case 'surrender':
                    message += 'Surrendered. ';
                    break;
            }
            message += `Payout: $${result.payout}`;
            if (result.insurance > 0) {
                message += `, Insurance: $${result.insurance}`;
            }
            message += '<br>';
        });

        gameMessage.innerHTML = message;
        setTimeout(() => {
            gameMessage.innerHTML = '';
            initGame();
        }, 5000);
    }

    // Event Listeners
    betBtn.addEventListener('click', placeBet);

    document.querySelectorAll('.game-btn[data-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.action;
            let params = {};
            
            if (action === 'insurance') {
                params.accept = 'true';
            } else if (action === 'no-insurance') {
                params.accept = 'false';
            }
            
            handleGameAction(action, params);
        });
    });

    // Initialize the game
    initGame();
});