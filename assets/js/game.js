/**
 * Blackjack Game JavaScript
 * 
 * Handles UI interaction for the blackjack game
 */

document.addEventListener('DOMContentLoaded', function() {
    // Game elements
    const betControls = document.querySelector('.bet-controls');
    const gameControls = document.querySelector('.game-controls');
    const gameOverControls = document.querySelector('.game-over-controls');
    const insuranceControls = document.querySelector('.insurance-controls');
    const dealerCardsContainer = document.querySelector('.dealer-cards');
    const playerHandsContainer = document.querySelector('.player-hands');
    const dealerScoreDisplay = document.querySelector('.dealer-score');
    const balanceDisplay = document.querySelector('.balance-amount');
    const resultMessage = document.querySelector('.result-message');
    const gameMessages = document.querySelector('.game-messages');
    
    // Game buttons
    const placeBetBtn = document.getElementById('place-bet-btn');
    const hitBtn = document.getElementById('hit-btn');
    const standBtn = document.getElementById('stand-btn');
    const doubleBtn = document.getElementById('double-btn');
    const splitBtn = document.getElementById('split-btn');
    const surrenderBtn = document.getElementById('surrender-btn');
    const insuranceYesBtn = document.getElementById('insurance-yes-btn');
    const insuranceNoBtn = document.getElementById('insurance-no-btn');
    const newGameBtn = document.getElementById('new-game-btn');
    
    // Input elements
    const betAmountInput = document.getElementById('bet-amount');
    
    // Game state variables
    let gameState = null;
    let currentBalance = gameSettings.currentMoney;
    
    // Set bet limits based on current balance
    function updateBetLimits() {
        const maxBet = Math.min(1000, currentBalance);
        betAmountInput.max = maxBet;
        betAmountInput.value = Math.min(betAmountInput.value, maxBet);
    }
    
    // Update the UI based on the game state
    function updateUI() {
        if (!gameState) return;
        
        // Update balance
        updateBalance();
        
        // Clear cards
        dealerCardsContainer.innerHTML = '';
        playerHandsContainer.innerHTML = '';
        
        // Update dealer cards
        const dealerCards = gameState.dealer.cards;
        dealerCards.forEach(card => {
            dealerCardsContainer.appendChild(createCardElement(card));
        });
        
        // Update dealer score
        if (gameState.gameState === 'game_over' || gameState.gameState === 'dealer_turn') {
            dealerScoreDisplay.textContent = gameState.dealer.bestValue;
        } else if (gameState.dealer.cards.length > 0 && gameState.dealer.cards[0].isVisible) {
            dealerScoreDisplay.textContent = '?';
        } else {
            dealerScoreDisplay.textContent = '--';
        }
        
        // Update player hands
        gameState.playerHands.forEach((hand, index) => {
            const handDiv = document.createElement('div');
            handDiv.className = 'player-hand';
            if (hand.isActive) {
                handDiv.classList.add('active-hand');
            }
            
            // Add hand index for multiple hands
            if (gameState.playerHands.length > 1) {
                const handLabel = document.createElement('div');
                handLabel.className = 'hand-label';
                handLabel.textContent = `Hand ${index + 1}`;
                handDiv.appendChild(handLabel);
            }
            
            // Add bet amount
            const betDiv = document.createElement('div');
            betDiv.className = 'bet-amount';
            betDiv.textContent = `Bet: $${hand.bet.toFixed(2)}`;
            handDiv.appendChild(betDiv);
            
            // Add insurance bet if applicable
            if (hand.insuranceBet > 0) {
                const insuranceDiv = document.createElement('div');
                insuranceDiv.className = 'insurance-bet';
                insuranceDiv.textContent = `Insurance: $${hand.insuranceBet.toFixed(2)}`;
                handDiv.appendChild(insuranceDiv);
            }
            
            // Add cards
            const cardsDiv = document.createElement('div');
            cardsDiv.className = 'cards-container';
            hand.cards.forEach(card => {
                cardsDiv.appendChild(createCardElement(card));
            });
            handDiv.appendChild(cardsDiv);
            
            // Add score
            const scoreDiv = document.createElement('div');
            scoreDiv.className = 'hand-score';
            scoreDiv.textContent = `Score: ${hand.bestValue}`;
            handDiv.appendChild(scoreDiv);
            
            // Add outcome if game is over
            if (hand.outcome) {
                const outcomeDiv = document.createElement('div');
                outcomeDiv.className = 'hand-outcome';
                let outcomeText = '';
                
                switch (hand.outcome) {
                    case 'win':
                        outcomeDiv.classList.add('win');
                        outcomeText = 'Win';
                        break;
                    case 'loss':
                        outcomeDiv.classList.add('loss');
                        outcomeText = 'Loss';
                        break;
                    case 'push':
                        outcomeDiv.classList.add('push');
                        outcomeText = 'Push';
                        break;
                    case 'blackjack':
                        outcomeDiv.classList.add('blackjack');
                        outcomeText = 'Blackjack!';
                        break;
                    case 'surrender':
                        outcomeDiv.classList.add('surrender');
                        outcomeText = 'Surrendered';
                        break;
                }
                
                outcomeDiv.textContent = outcomeText;
                handDiv.appendChild(outcomeDiv);
            }
            
            playerHandsContainer.appendChild(handDiv);
        });
        
        // Update controls visibility based on game state
        updateControls();
        
        // Show game outcome message if game is over
        if (gameState.gameState === 'game_over') {
            showGameResult();
        }
    }
    
    // Create a card element
    function createCardElement(card) {
        const cardEl = document.createElement('div');
        cardEl.className = 'playing-card';
        
        if (!card.isVisible) {
            cardEl.classList.add('card-back');
            return cardEl;
        }
        
        // Add card content
        cardEl.classList.add(`card-${card.suit}`);
        
        const valueDiv = document.createElement('div');
        valueDiv.className = 'card-value';
        valueDiv.textContent = card.value;
        
        const suitDiv = document.createElement('div');
        suitDiv.className = 'card-suit';
        
        switch (card.suit) {
            case 'hearts':
                suitDiv.textContent = '♥';
                cardEl.classList.add('card-red');
                break;
            case 'diamonds':
                suitDiv.textContent = '♦';
                cardEl.classList.add('card-red');
                break;
            case 'clubs':
                suitDiv.textContent = '♣';
                break;
            case 'spades':
                suitDiv.textContent = '♠';
                break;
        }
        
        cardEl.appendChild(valueDiv);
        cardEl.appendChild(suitDiv);
        
        return cardEl;
    }
    
    // Update controls based on game state
    function updateControls() {
        // Always hide all controls first
        betControls.style.display = 'none';
        gameControls.style.display = 'none';
        gameOverControls.style.display = 'none';
        insuranceControls.style.display = 'none';
        
        if (!gameState) {
            betControls.style.display = 'block';
            return;
        }
        
        switch (gameState.gameState) {
            case 'waiting_for_bet':
                betControls.style.display = 'block';
                updateBetLimits();
                break;
                
            case 'insurance_offered':
                gameControls.style.display = 'block';
                insuranceControls.style.display = 'block';
                break;
                
            case 'player_turn':
                gameControls.style.display = 'block';
                
                // Update action button availability
                doubleBtn.disabled = !gameState.canDouble;
                splitBtn.disabled = !gameState.canSplit;
                surrenderBtn.disabled = !gameState.canSurrender;
                
                // Show/hide surrender button based on settings
                if (gameSettings.surrenderOption === 'none') {
                    surrenderBtn.style.display = 'none';
                } else {
                    surrenderBtn.style.display = 'inline-block';
                }
                break;
                
            case 'dealer_turn':
                gameControls.style.display = 'none';
                break;
                
            case 'game_over':
                gameOverControls.style.display = 'block';
                break;
        }
    }
    
    // Update the balance display
    function updateBalance() {
        if (gameState && gameState.gameState === 'game_over') {
            // Calculate new balance
            currentBalance = parseFloat(currentBalance) - gameState.totalBet + gameState.totalWon;
        }
        
        balanceDisplay.textContent = `$${currentBalance.toFixed(2)}`;
    }
    
    // Show game result message
    function showGameResult() {
        const netWin = gameState.totalWon - gameState.totalBet;
        let message = '';
        
        if (netWin > 0) {
            message = `You won $${netWin.toFixed(2)}!`;
            resultMessage.className = 'result-message win';
        } else if (netWin < 0) {
            message = `You lost $${Math.abs(netWin).toFixed(2)}.`;
            resultMessage.className = 'result-message loss';
        } else {
            message = 'Push - your bet has been returned.';
            resultMessage.className = 'result-message push';
        }
        
        resultMessage.textContent = message;
    }
    
    // Display a game message
    function showMessage(message, type = 'info') {
        const msgElement = document.createElement('div');
        msgElement.className = `game-message ${type}`;
        msgElement.textContent = message;
        
        gameMessages.appendChild(msgElement);
        
        // Remove after a delay
        setTimeout(() => {
            msgElement.remove();
        }, 5000);
    }
    
    // Make an API request
    async function apiRequest(action, data = {}) {
        try {
            data.action = action;
            
            const response = await fetch('api/game_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                gameState = result.gameState;
                updateUI();
                return result;
            } else {
                showMessage(result.error, 'error');
                return null;
            }
        } catch (error) {
            console.error('API request failed:', error);
            showMessage('Network error. Please try again.', 'error');
            return null;
        }
    }
    
    // Event Listeners for game actions
    
    // Place bet
    placeBetBtn.addEventListener('click', async () => {
        const betAmount = parseFloat(betAmountInput.value);
        
        if (isNaN(betAmount) || betAmount <= 0) {
            showMessage('Please enter a valid bet amount.', 'error');
            return;
        }
        
        if (betAmount > currentBalance) {
            showMessage('You don\'t have enough money for that bet.', 'error');
            return;
        }
        
        await apiRequest('startGame', { betAmount });
    });
    
    // Hit
    hitBtn.addEventListener('click', async () => {
        await apiRequest('hit');
    });
    
    // Stand
    standBtn.addEventListener('click', async () => {
        await apiRequest('stand');
    });
    
    // Double
    doubleBtn.addEventListener('click', async () => {
        await apiRequest('double');
    });
    
    // Split
    splitBtn.addEventListener('click', async () => {
        await apiRequest('split');
    });
    
    // Surrender
    surrenderBtn.addEventListener('click', async () => {
        await apiRequest('surrender');
    });
    
    // Insurance - Yes
    insuranceYesBtn.addEventListener('click', async () => {
        const insuranceAmount = gameState.playerHands[0].bet / 2;
        await apiRequest('takeInsurance', { insuranceAmount });
    });
    
    // Insurance - No
    insuranceNoBtn.addEventListener('click', async () => {
        await apiRequest('declineInsurance');
    });
    
    // New Game
    newGameBtn.addEventListener('click', async () => {
        await apiRequest('newGame');
    });
    
    // Initialize the game
    async function initGame() {
        await apiRequest('getGameState');
    }
    
    // Start the game
    initGame();
    
    // Handle page unload to prevent accidentally leaving during a game
    window.addEventListener('beforeunload', (event) => {
        if (gameState && 
            (gameState.gameState === 'player_turn' || 
             gameState.gameState === 'insurance_offered' ||
             gameState.gameState === 'dealer_turn')) {
            event.preventDefault();
            event.returnValue = 'Game in progress. Are you sure you want to leave?';
            return event.returnValue;
        }
    });
});