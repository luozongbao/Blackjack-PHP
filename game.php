<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'classes/game_class.php';
require_once 'classes/deck_class.php';
require_once 'classes/hand_class.php';
require_once 'classes/card_class.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'includes/header.php';
?>

<div class="game-container">
    <div class="game-info">
        <div class="session-money">Current Balance: $<span id="current-balance">0</span></div>
    </div>
    
    <div class="dealer-section">
        <h2>Dealer's Hand</h2>
        <div class="dealer-cards" id="dealer-cards"></div>
        <div class="dealer-score" id="dealer-score">Score: 0</div>
    </div>

    <div class="player-section">
        <h2>Your Hand</h2>
        <div class="player-hands" id="player-hands">
            <div class="player-hand" data-hand-index="0">
                <div class="hand-cards"></div>
                <div class="hand-score">Score: 0</div>
            </div>
        </div>
    </div>

    <div class="action-section" id="action-section">
        <div class="betting-actions">
            <input type="number" id="bet-amount" min="1" value="100" class="bet-input">
            <button class="game-btn bet-btn" id="bet-btn">Place Bet</button>
        </div>
        <div class="game-actions" style="display: none;">
            <button class="game-btn hit-btn" data-action="hit">Hit</button>
            <button class="game-btn stand-btn" data-action="stand">Stand</button>
            <button class="game-btn double-btn" data-action="double">Double</button>
            <button class="game-btn split-btn" data-action="split">Split</button>
            <button class="game-btn surrender-btn" data-action="surrender">Surrender</button>
            <button class="game-btn insurance-btn" data-action="insurance">Insurance</button>
            <button class="game-btn no-insurance-btn" data-action="no-insurance">No Insurance</button>
        </div>
    </div>
    
    <div class="game-message" id="game-message"></div>
</div>

<script src="assets/js/game.js"></script>

<?php include 'includes/footer.php'; ?>