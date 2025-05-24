/**
 * Blackjack Game JavaScript
 * Enhanced game interactions and animations
 */

class BlackjackUI {
    constructor() {
        this.gameActive = false;
        this.animating = false;
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

        this.animating = true;
        this.showLoading('Dealing cards...');
        
        this.makeGameAction('start_game', { bet_amount: betAmount })
            .then(() => {
                this.addDealAnimation();
            })
            .finally(() => {
                this.animating = false;
                this.hideLoading();
            });
    }

    handleActionClick(e) {
        if (this.animating) return;

        const action = e.target.getAttribute('onclick');
        if (action) {
            e.preventDefault();
            const actionName = action.match(/gameAction\('(.+?)'\)/)[1];
            
            this.animating = true;
            this.showLoading(this.getActionMessage(actionName));
            
            this.makeGameAction(actionName)
                .finally(() => {
                    this.animating = false;
                    this.hideLoading();
                });
        }
    }

    makeGameAction(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        return fetch('./api/game_api.php', {
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
                // Update UI with new game state
                this.showSuccess(data.message || 'Action completed successfully');
                setTimeout(() => {
                    location.reload();
                }, 1000);
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