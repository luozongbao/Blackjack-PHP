<?php
/**
 * Header template for Blackjack PHP
 *
 * This file contains the header HTML that appears at the top of every page.
 * It includes the navigation bar and starts the HTML structure.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Get current page for active navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Blackjack PHP</title>
    <link rel="stylesheet" href="/Blackjack-PHP/assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <a href="/Blackjack-PHP/index.php">Blackjack PHP</a>
            </div>
            
            <?php include_once __DIR__ . '/navigation.php'; ?>
        </div>
    </header>
    
    <div class="container"><?php // Main content will go here ?>