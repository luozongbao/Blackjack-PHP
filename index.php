<?php
/**
 * Blackjack-PHP
 * 
 * Main entry point for the Blackjack PHP application.
 * This file checks if the application is installed (by checking for config.php),
 * and redirects to the appropriate page accordingly.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the application is installed

include_once __DIR__ . '/includes/database.php';

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: views/login.php');
    exit;
} else {
    // User is logged in, redirect to game page
    header('Location: views/game.php');
    exit;
}
