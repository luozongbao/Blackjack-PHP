<?php
/**
 * Database connection helper
 */

// Check if config file exists, if not, redirect to installation
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: ./includes/install.php');
    exit;
}


require_once __DIR__ . '/config.php';

/**
 * Get a database connection instance
 * 
 * @return PDO Database connection object
 */
// Get database connection instance
function getDb() {
    return getDbConnection();
}
