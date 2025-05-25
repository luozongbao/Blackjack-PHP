/**
 * Blackjack Game JavaScript
 * Enhanced game interactions and animations
 */

class BlackjackUI {
    constructor() {
        this.gameActive = false;
        this.animating = false;
        this.currentGameState = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateGameState();
        this.addCardAnimations();
    }

    bindEvents() {
        // Bet form submission
        const betForm = document.getElementById('bet-form');
        if (betForm) {
            betForm.addEventListener('submit', (e) => this.handleBetSubmission(e));
        }

        // Action buttons
        document.querySelectorAll('.action-button').forEach(button => {
            button.addEventListener('click', (e) => this.handleActionClick(e));
        });

        // Prevent accidental page refresh during game
        this.setupPageLeaveWarning();
    }

    handleBetSubmission(e) {
        e.preventDefault();
        
        if (this.animating) return;

        const form = e.target;
        const betAmount = parseFloat(form.bet_amount.value);
        
        if (betAmount <= 0) {
            this.showError('Please enter a valid bet amount');
            return;
        }

        // Disable form to prevent double submission
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Dealing...';
        }

        this.animating = true;
        this.showLoading('Dealing cards...');
        
        this.makeGameAction('start_game', { bet_amount: betAmount })
            .then(() => {
                this.addDealAnimation();
            })
            .catch(() => {
                // Re-enable form on error
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Deal Cards';
                }
            })
            .finally(() => {
                this.animating = false;
                this.hideLoading();
            });
    }

    handleActionClick(e) {
        if (this.animating) return;

        // Get action from data-action attribute or onclick attribute for backward compatibility
        let actionName = e.target.getAttribute('data-action');
        if (!actionName) {
            const action = e.target.getAttribute('onclick');
            if (action) {
                actionName = action.match(/gameAction\('(.+?)'\)/)[1];
            }
        }
        
        if (actionName) {
            e.preventDefault();
            
            // Disable button to prevent double clicks
            e.target.disabled = true;
            const originalText = e.target.textContent;
            e.target.textContent = 'Processing...';
            
            this.animating = true;
            this.showLoading(this.getActionMessage(actionName));
            
            this.makeGameAction(actionName)
                .catch(() => {
                    // Re-enable button on error
                    e.target.disabled = false;
                    e.target.textContent = originalText;
                })
                .finally(() => {
                    this.animating = false;
                    this.hideLoading();
                });
        }
    }

    makeGameAction(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('ajax', '1'); // Add ajax parameter for game.php handling
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        return fetch('game.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Game action response:', data);
            if (data.success) {
                // Update UI with new game state without page reload
                this.showSuccess(data.message || 'Action completed successfully');
                this.updateGameStateFromResponse(data);
            } else {
                this.showError(data.error || 'An error occurred');
            }
            return data;
        })
        .catch(error => {
            console.error('Game action error:', error);
            this.showError('Network error. Please try again.');
            throw error;
        });
    }

    getActionMessage(action) {
        const messages = {
            'hit': 'Dealing card...',
            'stand': 'Standing...',
            'double': 'Doubling down...',
            'split': 'Splitting hand...',
            'surrender': 'Surrendering...'
        };
        return messages[action] || 'Processing...';
    }

    addDealAnimation() {
        const cards = document.querySelectorAll('.playing-card:not(.card-back)');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('deal-animation');
            }, index * 200);
        });
    }

    addCardAnimations() {
        // Add entrance animations to existing cards
        const cards = document.querySelectorAll('.playing-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    }

    updateGameState() {
        // Highlight active hand
        const activeHand = document.querySelector('.active-hand');
        if (activeHand) {
            activeHand.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Update button states
        this.updateActionButtons();
        
        // Check game state
        this.gameActive = document.querySelector('#action-section .game-actions') !== null;
    }

    updateActionButtons() {
        const buttons = document.querySelectorAll('.action-button');
        buttons.forEach(button => {
            // Add hover effects and improve accessibility
            button.addEventListener('mouseenter', () => {
                if (!button.disabled) {
                    button.style.transform = 'translateY(-2px) scale(1.05)';
                }
            });
            
            button.addEventListener('mouseleave', () => {
                button.style.transform = '';
            });
        });
    }

    setupPageLeaveWarning() {
        if (this.gameActive) {
            window.addEventListener('beforeunload', (e) => {
                e.preventDefault();
                e.returnValue = 'You have an active game. Are you sure you want to leave?';
                return 'You have an active game. Are you sure you want to leave?';
            });
        }
    }

    showLoading(message = 'Loading...') {
        this.hideError();
        
        let loader = document.getElementById('game-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'game-loader';
            loader.className = 'game-loader';
            document.body.appendChild(loader);
        }
        
        loader.innerHTML = `
            <div class="loader-content">
                <div class="spinner"></div>
                <div class="loader-message">${message}</div>
            </div>
        `;
        loader.style.display = 'flex';
    }

    hideLoading() {
        const loader = document.getElementById('game-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    showError(message) {
        this.hideLoading();
        
        let errorDiv = document.getElementById('game-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'game-error';
            errorDiv.className = 'alert alert-danger game-error';
            
            const gameContainer = document.querySelector('.game-container');
            if (gameContainer) {
                gameContainer.insertBefore(errorDiv, gameContainer.firstChild);
            }
        }
        
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => this.hideError(), 5000);
    }

    hideError() {
        const errorDiv = document.getElementById('game-error');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }

    showSuccess(message) {
        this.hideLoading();
        
        let successDiv = document.getElementById('game-success');
        if (!successDiv) {
            successDiv = document.createElement('div');
            successDiv.id = 'game-success';
            successDiv.className = 'alert alert-success game-success';
            
            const gameContainer = document.querySelector('.game-container');
            if (gameContainer) {
                gameContainer.insertBefore(successDiv, gameContainer.firstChild);
            }
        }
        
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        
        // Auto hide after 3 seconds
        setTimeout(() => {
            if (successDiv) {
                successDiv.style.display = 'none';
            }
        }, 3000);
    }

    // Card value calculator for client-side display
    calculateHandValue(cards) {
        let value = 0;
        let aces = 0;
        
        cards.forEach(card => {
            if (card.rank === 'A') {
                aces++;
                value += 11;
            } else if (['J', 'Q', 'K'].includes(card.rank)) {
                value += 10;
            } else {
                value += parseInt(card.rank);
            }
        });
        
        // Adjust for aces
        while (value > 21 && aces > 0) {
            value -= 10;
            aces--;
        }
        
        return value;
    }

    // Money formatting helper
    formatMoney(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    updateGameStateFromResponse(data) {
        if (!data.gameState) return;
        
        const gameState = data.gameState;
        
        // Store current game state for reference
        this.currentGameState = gameState.gameState;
        
        // Update player hands
        if (gameState.playerHands) {
            this.updatePlayerHands(gameState.playerHands, gameState.currentHandIndex);
        }
        
        // Update dealer hand
        if (gameState.dealerHand) {
            this.updateDealerHand(gameState.dealerHand);
        }
        
        // Update action buttons based on game state
        this.updateActionButtonsFromState(gameState);
        
        // Update money display
        if (data.sessionData && data.sessionData.current_money) {
            const moneyDisplay = document.querySelector('.money-display');
            if (moneyDisplay) {
                moneyDisplay.textContent = '$' + parseFloat(data.sessionData.current_money).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
        
        // Update game status section
        this.updateGameStatusFromState(gameState);
        
        // Update shoe information
        if (gameState.shoe) {
            this.updateShoeInfo(gameState.shoe);
        }
    }
    
    updateActionButtonsFromState(gameState) {
        const actionSection = document.getElementById('action-section');
        if (!actionSection) return;
        
        if (gameState.gameState === 'betting') {
            // Show betting form
            this.showBettingForm(actionSection);
        } else if (gameState.gameState === 'player_turn') {
            // Show action buttons
            this.showActionButtons(actionSection, gameState);
        } else if (gameState.gameState === 'game_over') {
            // Show results and new game button
            this.showGameResults(actionSection, gameState);
        }
    }
    
    showBettingForm(container) {
        container.innerHTML = `
            <form method="POST" id="bet-form" class="d-flex align-center">
                <input type="hidden" name="action" value="start_game">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group" style="margin-right: 15px;">
                    <label for="bet_amount">Bet Amount:</label>
                    <input type="number" 
                           id="bet_amount" 
                           name="bet_amount" 
                           min="100" 
                           step="100" 
                           value="100"
                           class="form-control"
                           style="width: 120px;">
                </div>
                
                <button type="submit" class="btn btn-primary">Deal Cards</button>
            </form>
        `;
        
        // Re-attach event listener
        const betForm = document.getElementById('bet-form');
        if (betForm) {
            betForm.addEventListener('submit', (e) => this.handleBetSubmission(e));
        }
    }
    
    showActionButtons(container, gameState) {
        let buttonsHtml = '<div class="game-actions">';
        
        if (gameState.canHit) {
            buttonsHtml += '<button class="btn btn-secondary action-button" data-action="hit">Hit</button>';
        }
        
        if (gameState.canStand) {
            buttonsHtml += '<button class="btn btn-primary action-button" data-action="stand">Stand</button>';
        }
        
        if (gameState.canDouble) {
            buttonsHtml += '<button class="btn btn-warning action-button" data-action="double">Double Down</button>';
        }
        
        if (gameState.canSplit) {
            buttonsHtml += '<button class="btn btn-info action-button" data-action="split">Split</button>';
        }
        
        if (gameState.canSurrender) {
            buttonsHtml += '<button class="btn btn-danger action-button" data-action="surrender">Surrender</button>';
        }
        
        buttonsHtml += '</div>';
        container.innerHTML = buttonsHtml;
        
        // Re-attach event listeners for action buttons
        document.querySelectorAll('.action-button').forEach(button => {
            button.addEventListener('click', (e) => this.handleActionClick(e));
        });
    }
    
    showGameResults(container, gameState) {
        let resultText = '';
        if (gameState.results && gameState.results.length > 0) {
            resultText = gameState.results.map(result => 
                `Hand ${result.handIndex + 1}: ${result.result} - ${result.winnings >= 0 ? '+' : ''}$${result.winnings}`
            ).join('<br>');
        }
        
        container.innerHTML = `
            <div class="game-results">
                ${resultText ? `<div class="results-summary">${resultText}</div>` : ''}
                <button class="btn btn-primary" onclick="newGame()">New Game</button>
            </div>
        `;
    }
    
    updateGameStatusFromState(gameState) {
        // Update any status indicators based on game state
        const statusElements = document.querySelectorAll('.game-status');
        statusElements.forEach(element => {
            if (gameState.gameState === 'game_over' && gameState.results) {
                element.textContent = 'Game Over';
            } else if (gameState.gameState === 'player_turn') {
                element.textContent = `Hand ${gameState.currentHandIndex + 1} Turn`;
            }
        });
    }

    updatePlayerHands(playerHands, currentHandIndex) {
        console.log('Updating player hands:', playerHands);
        
        const playerHandsContainer = document.getElementById('player-hands-container');
        if (!playerHandsContainer) {
            console.error('Player hands container not found');
            return;
        }
        
        // Clear existing content first
        playerHandsContainer.innerHTML = '';
        
        // Create player hand elements for each hand
        playerHands.forEach((hand, handIndex) => {
            console.log(`Creating player hand ${handIndex} with ${hand.cards.length} cards`);
            
            // Create the player hand element
            const handElement = document.createElement('div');
            handElement.className = `player-hand ${handIndex === currentHandIndex ? 'active-hand' : ''}`;
            handElement.setAttribute('data-hand', handIndex);
            
            // Create hand info
            const handInfo = document.createElement('div');
            handInfo.className = 'hand-info';
            
            const handLabel = document.createElement('span');
            handLabel.className = 'hand-label';
            handLabel.textContent = `Hand ${handIndex + 1}`;
            if (playerHands.length > 1 && handIndex === currentHandIndex) {
                handLabel.textContent += ' (Current)';
            }
            
            const betAmount = document.createElement('span');
            betAmount.className = 'bet-amount';
            betAmount.textContent = `Bet: $${hand.bet.toFixed(2)}`;
            
            handInfo.appendChild(handLabel);
            handInfo.appendChild(betAmount);
            
            // Create cards container
            const cardsContainer = document.createElement('div');
            cardsContainer.className = 'cards-container player-cards';
            
            // Add cards
            hand.cards.forEach(card => {
                console.log(`Adding card: ${card.rank} of ${card.suit}`);
                const cardElement = document.createElement('div');
                cardElement.className = 'playing-card';
                cardElement.setAttribute('data-card', card.rank + card.suit);
                cardElement.setAttribute('data-rank', card.rank);
                cardElement.setAttribute('data-suit', this.getSuitSymbol(card.suit));
                
                const suitColor = this.getSuitColor(card.suit);
                cardElement.innerHTML = `
                    <div class="card-rank ${suitColor}">${card.rank}</div>
                    <div class="card-suit ${suitColor}">${this.getSuitSymbol(card.suit)}</div>
                `;
                
                cardsContainer.appendChild(cardElement);
            });
            
            // Create hand score
            const handScore = document.createElement('div');
            handScore.className = 'hand-score';
            let scoreText = `Score: ${hand.score}`;
            if (hand.isSoft) scoreText += ' (Soft)';
            if (hand.isBlackjack) scoreText += ' (Blackjack!)';
            else if (hand.score > 21) scoreText += ' (Busted!)';
            if (hand.isSurrendered) scoreText += ' (Surrendered)';
            handScore.textContent = scoreText;
            
            // Assemble the hand element
            handElement.appendChild(handInfo);
            handElement.appendChild(cardsContainer);
            handElement.appendChild(handScore);
            
            // Add to container
            playerHandsContainer.appendChild(handElement);
        });
    }

    updateDealerHand(dealerHand) {
        const dealerCards = document.getElementById('dealer-cards');
        const dealerScore = document.getElementById('dealer-score');
        
        if (dealerCards) {
            dealerCards.innerHTML = '';
            
            dealerHand.cards.forEach((card, index) => {
                const cardElement = document.createElement('div');
                cardElement.className = 'playing-card';
                
                // Hide second card during player turn
                if (this.currentGameState === 'player_turn' && index === 1 && dealerHand.cards.length === 2) {
                    cardElement.classList.add('card-back');
                    cardElement.setAttribute('data-card', 'hidden');
                    cardElement.innerHTML = '<div class="card-back-design">♠</div>';
                } else {
                    cardElement.setAttribute('data-card', card.rank + card.suit);
                    cardElement.setAttribute('data-rank', card.rank);
                    cardElement.setAttribute('data-suit', this.getSuitSymbol(card.suit));
                    
                    const suitColor = this.getSuitColor(card.suit);
                    cardElement.innerHTML = `
                        <div class="card-rank ${suitColor}">${card.rank}</div>
                        <div class="card-suit ${suitColor}">${this.getSuitSymbol(card.suit)}</div>
                    `;
                }
                
                dealerCards.appendChild(cardElement);
            });
        }
        
        // Update dealer score
        if (dealerScore) {
            let scoreText = '';
            if (this.currentGameState === 'player_turn') {
                scoreText = `Score: ${dealerHand.cards[0].value} + ?`;
            } else {
                scoreText = `Score: ${dealerHand.score}`;
                if (dealerHand.isBlackjack) {
                    scoreText += ' (Blackjack!)';
                } else if (dealerHand.score > 21) {
                    scoreText += ' (Busted!)';
                }
            }
            dealerScore.textContent = scoreText;
        }
    }

    getSuitSymbol(suit) {
        const symbols = {
            'Hearts': '♥',
            'Diamonds': '♦', 
            'Clubs': '♣',
            'Spades': '♠',
            // Also handle lowercase for compatibility
            'hearts': '♥',
            'diamonds': '♦',
            'clubs': '♣',
            'spades': '♠'
        };
        return symbols[suit] || suit;
    }

    getSuitColor(suit) {
        return (suit === 'Hearts' || suit === 'Diamonds' || 
                suit === 'hearts' || suit === 'diamonds') ? 'red' : 'black';
    }
    
    updateShoeInfo(shoeData) {
        const shoeInfoSection = document.getElementById('shoe-info');
        if (!shoeInfoSection || !shoeData) return;
        
        // Update penetration percentage
        const penetrationPercentage = shoeInfoSection.querySelector('.penetration-percentage');
        if (penetrationPercentage) {
            penetrationPercentage.textContent = `${shoeData.penetrationPercentage.toFixed(1)}%`;
        }
        
        // Update penetration bar
        const penetrationProgress = shoeInfoSection.querySelector('.penetration-progress');
        if (penetrationProgress) {
            penetrationProgress.style.width = `${Math.min(100, shoeData.penetrationPercentage)}%`;
        }
        
        // Update cards remaining
        const cardsRemaining = shoeInfoSection.querySelector('.cards-remaining strong');
        if (cardsRemaining) {
            cardsRemaining.textContent = shoeData.cardsRemaining;
        }
        
        // Update shuffle method indicator
        const shuffleMethod = shoeInfoSection.querySelector('.shuffle-method');
        if (shuffleMethod) {
            shuffleMethod.innerHTML = shoeData.shuffleMethod === 'auto' 
                ? '🔄 Auto Shuffling Machine' 
                : '🃏 Manual Shuffle';
        }
        
        // Update reshuffle indicator
        let reshuffleIndicator = shoeInfoSection.querySelector('.reshuffle-indicator');
        if (shoeData.needsReshuffle) {
            if (!reshuffleIndicator) {
                reshuffleIndicator = document.createElement('div');
                reshuffleIndicator.className = 'reshuffle-indicator';
                shoeInfoSection.querySelector('.cards-info').appendChild(reshuffleIndicator);
            }
            reshuffleIndicator.textContent = '⚠️ Reshuffle needed';
        } else if (reshuffleIndicator) {
            reshuffleIndicator.remove();
        }
    }

    // ...existing code...
}

// Global game action functions (called from PHP-generated onclick handlers)
function gameAction(action) {
    if (window.blackjackGame) {
        window.blackjackGame.makeGameAction(action);
    }
}

function newGame() {
    if (window.blackjackGame) {
        window.blackjackGame.makeGameAction('new_game');
    }
}

// Initialize game when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.blackjackGame = new BlackjackUI();
    
    // Add some visual enhancements
    addVisualEnhancements();
});

function addVisualEnhancements() {
    // Add card flip effect for hidden dealer card
    const hiddenCard = document.querySelector('.card-back');
    if (hiddenCard) {
        hiddenCard.addEventListener('click', () => {
            hiddenCard.style.transform = 'rotateY(180deg)';
        });
    }
    
    // Add money counter animation
    const moneyDisplay = document.querySelector('.money-display');
    if (moneyDisplay) {
        const value = parseFloat(moneyDisplay.textContent.replace(/[$,]/g, ''));
        animateNumber(moneyDisplay, 0, value, 1000);
    }
}

function animateNumber(element, start, end, duration) {
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = start + (end - start) * progress;
        element.textContent = '$' + currentValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}