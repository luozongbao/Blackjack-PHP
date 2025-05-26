/**
 * Blackjack Game JavaScript
 * Enhanced game interactions and animations
 */

class BlackjackUI {
    constructor() {
        this.gameActive = false;
        this.animating = false;
        this.currentGameState = null;
        this.soundEnabled = true; // Default sound setting
        this.sounds = {}; // Will hold our audio objects
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateGameState();
        this.addCardAnimations();
        this.loadSounds();
        
        // Start background music on user interaction
        document.addEventListener('click', () => {
            // Try to play background music on first user interaction
            // This addresses autoplay restrictions in browsers
            if (this.sounds.backgroundMusic && this.sounds.backgroundMusic.paused && this.soundEnabled) {
                this.toggleBackgroundMusic();
            }
        }, { once: true }); // Only trigger once
    }

    // Load all sound effects and background music
    loadSounds() {
        // Background music
        this.sounds.backgroundMusic = new Audio('/assets/audio/background_music.mp3');
        this.sounds.backgroundMusic.loop = true;
        this.sounds.backgroundMusic.volume = 0.3;
        
        // Game action sounds
        this.sounds.deal = new Audio('/assets/audio/deal.mp3');
        this.sounds.hit = new Audio('/assets/audio/hit.mp3');
        this.sounds.stand = new Audio('/assets/audio/stand.mp3');
        this.sounds.double = new Audio('/assets/audio/double.mp3');
        this.sounds.split = new Audio('/assets/audio/split.mp3');
        this.sounds.shuffle = new Audio('/assets/audio/shuffle.mp3');
        this.sounds.chips = new Audio('/assets/audio/chips.mp3');
        
        // Result sounds
        this.sounds.win = new Audio('/assets/audio/win.mp3');
        this.sounds.lose = new Audio('/assets/audio/lose.mp3');
        this.sounds.push = new Audio('/assets/audio/push.mp3');
        this.sounds.blackjack = new Audio('/assets/audio/blackjack.mp3');
        
        // Preload sounds
        this.preloadSounds();
        
        // Load sound settings from localStorage if available
        const soundSetting = localStorage.getItem('blackjackSoundEnabled');
        if (soundSetting !== null) {
            this.soundEnabled = soundSetting === 'true';
        }
    }
    
    // Preload all sounds to ensure they play promptly
    preloadSounds() {
        for (const sound in this.sounds) {
            if (this.sounds[sound] instanceof Audio) {
                // Load a small part of each audio file
                this.sounds[sound].load();
                this.sounds[sound].volume = 0;
                this.sounds[sound].play().catch(() => {});
                this.sounds[sound].pause();
                this.sounds[sound].currentTime = 0;
                // Reset volume for actual playback
                if (sound === 'backgroundMusic') {
                    this.sounds[sound].volume = 0.3;
                } else {
                    this.sounds[sound].volume = 1.0;
                }
            }
        }
    }
    
    // Play a sound if sounds are enabled
    playSound(soundName) {
        if (this.soundEnabled && this.sounds[soundName]) {
            // Stop the sound if it's already playing and reset it
            this.sounds[soundName].pause();
            this.sounds[soundName].currentTime = 0;
            
            // Play the sound
            this.sounds[soundName].play().catch(error => {
                console.log('Sound play error:', error);
                // Often due to user not interacting with page yet
            });
        }
    }
    
    // Toggle background music
    toggleBackgroundMusic() {
        if (this.soundEnabled) {
            if (this.sounds.backgroundMusic.paused) {
                this.sounds.backgroundMusic.play().catch(error => {
                    console.log('Background music play error:', error);
                });
                // Update music button UI
                const musicButton = document.getElementById('music-toggle');
                if (musicButton) {
                    musicButton.innerHTML = '<i class="fas fa-music"></i>';
                    musicButton.classList.add('active');
                    musicButton.classList.remove('muted');
                    musicButton.title = "Music On - Click to Mute";
                }
            } else {
                this.sounds.backgroundMusic.pause();
                // Update music button UI
                const musicButton = document.getElementById('music-toggle');
                if (musicButton) {                musicButton.innerHTML = '<i class="fas fa-volume-xmark"></i>';
                musicButton.classList.remove('active');
                    musicButton.classList.add('muted');
                    musicButton.title = "Music Off - Click to Play";
                }
            }
        } else {
            this.sounds.backgroundMusic.pause();
        }
    }
    
    // Toggle all sounds on/off
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('blackjackSoundEnabled', this.soundEnabled);
        
        // Update sound button UI
        const soundButton = document.getElementById('sound-toggle');
        if (soundButton) {
            if (this.soundEnabled) {
                soundButton.innerHTML = '<i class="fas fa-volume-up"></i>';
                soundButton.classList.remove('muted');
                soundButton.classList.add('active');
                soundButton.title = "Sound On - Click to Mute";
            } else {
                soundButton.innerHTML = '<i class="fas fa-volume-mute"></i>';
                soundButton.classList.add('muted');
                soundButton.classList.remove('active');
                soundButton.title = "Sound Off - Click to Enable";
            }
        }
        
        // Update music button to match sound state
        const musicButton = document.getElementById('music-toggle');
        if (musicButton) {
            if (!this.soundEnabled) {
                musicButton.classList.add('muted');
                musicButton.classList.remove('active');
            } else if (!this.sounds.backgroundMusic.paused) {
                musicButton.classList.add('active');
                musicButton.classList.remove('muted');
            }
        }
        
        // Handle background music based on new setting
        if (!this.soundEnabled && this.sounds.backgroundMusic) {
            this.sounds.backgroundMusic.pause();
        }
        
        return this.soundEnabled;
    }

    bindEvents() {
        // Remove existing event listeners to prevent duplicates
        this.unbindEvents();
        
        // Bet form submission
        const betForm = document.getElementById('bet-form');
        if (betForm) {
            this.betFormHandler = (e) => this.handleBetSubmission(e);
            betForm.addEventListener('submit', this.betFormHandler);
        }

        // Action buttons
        document.querySelectorAll('.action-button').forEach(button => {
            button.addEventListener('click', (e) => this.handleActionClick(e));
        });

        // Initialize sound buttons
        const soundButton = document.getElementById('sound-toggle');
        if (soundButton) {
            if (this.soundEnabled) {
                soundButton.innerHTML = '<i class="fas fa-volume-up"></i>';
                soundButton.classList.remove('muted');
                soundButton.classList.add('active');
                soundButton.title = "Sound On - Click to Mute";
            } else {
                soundButton.innerHTML = '<i class="fas fa-volume-mute"></i>';
                soundButton.classList.add('muted');
                soundButton.classList.remove('active');
                soundButton.title = "Sound Off - Click to Enable";
            }
        }
        
        // Initialize music button
        const musicButton = document.getElementById('music-toggle');
        if (musicButton) {
            if (this.soundEnabled && this.sounds.backgroundMusic && !this.sounds.backgroundMusic.paused) {
                musicButton.classList.add('active');
                musicButton.classList.remove('muted');
                musicButton.title = "Music On - Click to Mute";
            } else {
                musicButton.classList.add('muted');
                musicButton.classList.remove('active');
                musicButton.title = "Music Off - Click to Play";
            }
        }
        
        // Prevent accidental page refresh during game
        this.setupPageLeaveWarning();
    }

    unbindEvents() {
        // Remove bet form event listener if it exists
        const betForm = document.getElementById('bet-form');
        if (betForm && this.betFormHandler) {
            betForm.removeEventListener('submit', this.betFormHandler);
        }
    }

    handleBetSubmission(e) {
        e.preventDefault();
        
        if (this.animating) return;

        const form = e.target;
        const betAmount = parseInt(form.bet_amount.value, 10);
        const betInput = form.bet_amount;
        const tableMinBet = parseInt(betInput.min, 10) || 100;
        const tableMaxBet = parseInt(betInput.max, 10) || 10000;
        
        if (betAmount <= 0 || isNaN(betAmount)) {
            this.showOverlayMessage('Please enter a valid bet amount', 'error');
            return;
        }
        
        // Client-side validation for table limits
        if (betAmount < tableMinBet) {
            this.showOverlayMessage(`Minimum bet is $${tableMinBet.toLocaleString()}`, 'error');
            return;
        }
        
        if (betAmount > tableMaxBet) {
            this.showOverlayMessage(`Maximum bet is $${tableMaxBet.toLocaleString()}`, 'error');
            return;
        }
        
        // Ensure bet is multiple of 100
        if (betAmount % 100 !== 0) {
            this.showOverlayMessage('Bet amount must be a multiple of $100', 'error');
            return;
        }

        // Disable form to prevent double submission
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Dealing...';
        }

        this.animating = true;
        
        // Only show loading after 300ms for smooth experience
        const loadingTimeout = setTimeout(() => {
            this.showLoading('Dealing cards...');
        }, 300);
        
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
                clearTimeout(loadingTimeout);
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
            
            // Play appropriate sound for this action
            this.playSound(actionName);
            
            // Disable button to prevent double clicks
            e.target.disabled = true;
            const originalText = e.target.textContent;
            e.target.textContent = 'Processing...';
            
            this.animating = true;
            
            // Only show loading for slower actions to avoid flicker
            let loadingTimeout;
            if (['double', 'split', 'surrender'].includes(actionName)) {
                loadingTimeout = setTimeout(() => {
                    this.showLoading(this.getActionMessage(actionName));
                }, 200); // Delay loading indicator for quick actions
            }
            
            this.makeGameAction(actionName)
                .catch(() => {
                    // Re-enable button on error
                    e.target.disabled = false;
                    e.target.textContent = originalText;
                })
                .finally(() => {
                    this.animating = false;
                    if (loadingTimeout) {
                        clearTimeout(loadingTimeout);
                    }
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

        // Play appropriate sound for each action
        switch(action) {
            case 'hit':
                this.playSound('hit');
                break;
            case 'stand':
                this.playSound('stand');
                break;
            case 'double':
                this.playSound('double');
                break;
            case 'split':
                this.playSound('split');
                break;
            case 'surrender':
                this.playSound('lose');
                break;
            case 'start_game':
                this.playSound('deal');
                break;
            case 'new_game':
                this.playSound('shuffle');
                break;
            default:
                break;
        }

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
                // Removed disruptive success message for smooth gameplay
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
        // Play deal sound
        this.playSound('deal');
        
        const cards = document.querySelectorAll('.playing-card:not(.card-back)');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('deal-animation');
                // Add card sound with slight delay between each card
                if (index > 0) {
                    setTimeout(() => this.playSound('hit'), 100);
                }
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
        // Force reflow and add show class for animation
        setTimeout(() => loader.classList.add('show'), 10);
    }

    hideLoading() {
        const loader = document.getElementById('game-loader');
        if (loader) {
            loader.classList.remove('show');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 200);
        }
    }

    showError(message) {
        this.hideLoading();
        this.showOverlayMessage(message, 'error');
    }

    hideError() {
        // Errors auto-hide with the overlay system
    }

    showSuccess(message) {
        this.hideLoading();
        this.showOverlayMessage(message, 'success');
    }

    // New non-intrusive overlay message system
    showOverlayMessage(message, type = 'info') {
        // Remove any existing overlay messages
        const existingMessage = document.getElementById('game-overlay-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.id = 'game-overlay-message';
        messageDiv.className = `game-overlay-message ${type}`;
        messageDiv.textContent = message;
        
        document.body.appendChild(messageDiv);
        
        // Trigger show animation
        setTimeout(() => messageDiv.classList.add('show'), 10);
        
        // Auto-hide after 2 seconds with fade animation
        setTimeout(() => {
            messageDiv.classList.remove('show');
            messageDiv.classList.add('hide');
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 300);
        }, 2000);
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
        
        // Update money display and stats with real-time data
        if (data.sessionData) {
            this.updateMoneyAndStats(data.sessionData);
        }
        
        // Update game status section
        this.updateGameStatusFromState(gameState);
        
        // Update shoe information
        if (gameState.shoeInfo) {
            this.updateShoeInfo(gameState.shoeInfo);
        }
        
        // Play sounds based on game results
        if (gameState.gameState === 'game_over' && gameState.results) {
            // If there's only one result, play direct sound
            if (gameState.results.length === 1) {
                const result = gameState.results[0];
                if (result.result === 'Blackjack!') {
                    this.playSound('blackjack');
                } else if (result.result === 'Win') {
                    this.playSound('win');
                } else if (result.result === 'Loss') {
                    this.playSound('lose');
                } else if (result.result === 'Push') {
                    this.playSound('push');
                }
            } 
            // If there are multiple hands, determine overall result
            else if (gameState.results.length > 1) {
                const hasWin = gameState.results.some(r => r.result === 'Win' || r.result === 'Blackjack!');
                const hasLoss = gameState.results.some(r => r.result === 'Loss');
                
                if (hasWin && !hasLoss) {
                    this.playSound('win');
                } else if (hasLoss && !hasWin) {
                    this.playSound('lose');
                } else {
                    this.playSound('push'); // Mixed results
                }
            }
        }
    }
    
    updateActionButtonsFromState(gameState) {
        const actionSection = document.getElementById('action-section');
        if (!actionSection) return;
        
        if (gameState.gameState === 'betting') {
            // Show betting form
            this.showBettingForm(actionSection, gameState);
        } else if (gameState.gameState === 'player_turn') {
            // Show action buttons
            this.showActionButtons(actionSection, gameState);
        } else if (gameState.gameState === 'game_over') {
            // Show results and new game button
            this.showGameResults(actionSection, gameState);
        }
    }
    
    showBettingForm(container, gameState) {
        // Get table limits from gameState settings or use defaults
        const tableMinBet = gameState?.settings?.table_min_bet || 100;
        const tableMaxBet = gameState?.settings?.table_max_bet || 10000;
        const currentMoney = gameState?.currentMoney || 1000;
        const maxAllowedBet = Math.min(currentMoney, tableMaxBet);
        
        // Determine default bet amount
        let defaultBet = tableMinBet;
        if (gameState?.defaultBet) {
            // Use the defaultBet from server (previous game's initial bet or table minimum)
            defaultBet = gameState.defaultBet;
        }
        
        container.innerHTML = `
            <form method="POST" id="bet-form" class="d-flex align-center">
                <input type="hidden" name="action" value="start_game">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group" style="margin-right: 15px;">
                    <label for="bet_amount">Bet Amount:</label>
                    <input type="number" 
                           id="bet_amount" 
                           name="bet_amount" 
                           min="${tableMinBet}" 
                           max="${maxAllowedBet}"
                           step="${tableMinBet}" 
                           value="${defaultBet}"
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
        
        // Update cards total
        const cardsTotal = shoeInfoSection.querySelector('.cards-total');
        if (cardsTotal && shoeData.totalCards) {
            cardsTotal.textContent = `of ${shoeData.totalCards} total`;
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
                reshuffleIndicator = document.createElement('span');
                reshuffleIndicator.className = 'reshuffle-indicator';
                shoeInfoSection.querySelector('.cards-info').appendChild(reshuffleIndicator);
            }
            reshuffleIndicator.textContent = '⚠️ Reshuffle needed';
        } else if (reshuffleIndicator) {
            reshuffleIndicator.remove();
        }
    }

    // Reset shoe information display for new game
    resetShoeInfo() {
        const shoeInfoSection = document.getElementById('shoe-info');
        
        if (shoeInfoSection) {
            // Reset penetration percentage
            const penetrationPercentage = shoeInfoSection.querySelector('.penetration-percentage');
            if (penetrationPercentage) {
                penetrationPercentage.textContent = '0.0%';
            }
            
            // Reset penetration progress bar
            const penetrationProgress = shoeInfoSection.querySelector('.penetration-progress');
            if (penetrationProgress) {
                penetrationProgress.style.width = '0%';
            }
            
            // Reset cards remaining
            const cardsRemaining = shoeInfoSection.querySelector('.cards-remaining strong');
            if (cardsRemaining) {
                cardsRemaining.textContent = '-';
            }
            
            // Update cards total message
            const cardsTotal = shoeInfoSection.querySelector('.cards-total');
            if (cardsTotal) {
                cardsTotal.textContent = 'Ready for new game';
            }
            
            // Remove reshuffle indicator
            const reshuffleIndicator = shoeInfoSection.querySelector('.reshuffle-indicator');
            if (reshuffleIndicator) {
                reshuffleIndicator.remove();
            }
            
            // Ensure shoe section remains visible
            shoeInfoSection.style.display = '';
        }
    }

    // Real-time money and stats updates
    updateMoneyAndStats(sessionData) {
        if (!sessionData) return;
        
        // Update current money
        const moneyDisplay = document.querySelector('.money-display');
        if (moneyDisplay) {
            moneyDisplay.textContent = '$' + parseFloat(sessionData.current_money).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Update session stats
        const statsElements = {
            games: document.querySelector('.stat-item:nth-child(1) strong').nextSibling,
            won: document.querySelector('.stat-item:nth-child(2) strong').nextSibling,
            lost: document.querySelector('.stat-item:nth-child(3) strong').nextSibling,
            push: document.querySelector('.stat-item:nth-child(4) strong').nextSibling
        };
        
        if (statsElements.games) statsElements.games.textContent = ' ' + sessionData.session_games_played;
        if (statsElements.won) statsElements.won.textContent = ' ' + sessionData.session_games_won;
        if (statsElements.lost) statsElements.lost.textContent = ' ' + sessionData.session_games_lost;
        if (statsElements.push) statsElements.push.textContent = ' ' + sessionData.session_games_push;
        
        // Calculate revised statistics for real-time updates
        const sessionTotalWon = Math.abs(parseFloat(sessionData.session_total_won || 0));
        const sessionTotalLoss = -Math.abs(parseFloat(sessionData.session_total_loss || 0));
        const sessionNet = sessionTotalWon + sessionTotalLoss;
        
        // Update Previous Game Won with color coding
        const previousGameWonElement = document.querySelector('.text-right div:nth-child(2) strong').parentElement;
        if (previousGameWonElement) {
            const previousGameWonAmount = parseFloat(sessionData.previous_game_won || 0);
            const previousGameWonClass = previousGameWonAmount >= 0 ? 'text-success' : 'text-danger';
            previousGameWonElement.className = previousGameWonClass;
            previousGameWonElement.innerHTML = '<strong>Previous Game Won:</strong> $' + previousGameWonAmount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Update Net with color coding (using new calculation)
        const netElement = document.querySelector('.text-right div:nth-child(3)');
        if (netElement) {
            const netClass = sessionNet >= 0 ? 'text-success' : 'text-danger';
            netElement.className = netClass;
            netElement.innerHTML = '<strong>Net:</strong> $' + sessionNet.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Update current game bet display
        this.updateCurrentGameBet();
    }
    
    // Update current game bet display
    updateCurrentGameBet() {
        const currentGameBetElement = document.querySelector('.text-right div:nth-child(1)');
        if (!currentGameBetElement) return;
        
        // Check if there's an active game with hands
        if (this.currentGameState && 
            (this.currentGameState === 'player_turn' || this.currentGameState === 'dealer_turn' || this.currentGameState === 'game_over')) {
            
            // Calculate total bet from all active hands
            let totalBet = 0;
            const playerHands = document.querySelectorAll('.player-hand');
            playerHands.forEach(hand => {
                const betText = hand.querySelector('.bet-amount')?.textContent || '';
                const betMatch = betText.match(/\$([0-9,.]+)/);
                if (betMatch) {
                    totalBet += parseFloat(betMatch[1].replace(/,/g, ''));
                }
            });
            
            if (totalBet > 0) {
                currentGameBetElement.innerHTML = '<strong>Current Game Bet:</strong> $' + totalBet.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        } else {
            // Reset to $0.00 when no active game (betting state or new game)
            currentGameBetElement.innerHTML = '<strong>Current Game Bet:</strong> $0.00';
        }
    }

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('blackjackSoundEnabled', this.soundEnabled);
        
        // Update sound button UI
        const soundButton = document.getElementById('sound-toggle');
        if (soundButton) {
            soundButton.innerHTML = this.soundEnabled ? 
                '<i class="fas fa-volume-up"></i>' : 
                '<i class="fas fa-volume-mute"></i>';
        }
        
        // Handle background music based on new setting
        if (!this.soundEnabled && this.sounds.backgroundMusic) {
            this.sounds.backgroundMusic.pause();
        }
        
        return this.soundEnabled;
    }
}

// Global game action functions (called from PHP-generated onclick handlers)
function gameAction(action) {
    if (window.blackjackGame) {
        window.blackjackGame.makeGameAction(action);
    }
}

function newGame() {
    if (window.blackjackGame) {
        // Play shuffle sound
        window.blackjackGame.playSound('shuffle');
        
        // Clear dealer cards immediately for better UX
        const dealerCards = document.getElementById('dealer-cards');
        if (dealerCards) {
            dealerCards.innerHTML = '<div class="card-placeholder">Dealer Cards</div>';
        }
        
        // Clear player hands
        const playerHandsContainer = document.getElementById('player-hands-container');
        if (playerHandsContainer) {
            playerHandsContainer.innerHTML = '<div class="card-placeholder">Player Cards</div>';
        }
        
        // Reset current game state immediately
        window.blackjackGame.currentGameState = 'betting';
        
        // Reset current game bet to $0.00 immediately
        const currentGameBetElement = document.querySelector('.text-right div:nth-child(1)');
        if (currentGameBetElement) {
            currentGameBetElement.innerHTML = '<strong>Current Game Bet:</strong> $0.00';
        }
        
        // Don't reset shoe info - let the backend response update it correctly
        // The backend preserves deck state when using manual shuffle method
        
        // Make the new game action
        window.blackjackGame.makeGameAction('new_game')
            .then(data => {
                if (data.success) {
                    // Update UI with new game state
                    window.blackjackGame.updateGameStateFromResponse(data);
                    
                    // Ensure betting state and reset
                    window.blackjackGame.currentGameState = 'betting';
                } else {
                    // Fallback to page reload if there's an error
                    location.reload();
                }
            })
            .catch(error => {
                console.error('New game error:', error);
                // Fallback to page reload on error
                location.reload();
            });
    }
}

// Initialize game when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.blackjackGame = new BlackjackUI();
    
    // Ensure shoe info section is always visible
    const shoeInfoSection = document.getElementById('shoe-info');
    if (shoeInfoSection) {
        shoeInfoSection.style.display = 'block';
    }
    
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