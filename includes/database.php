<?php
/**
 * Database connection helper
 */
require_once __DIR__ . '/config.php';

// Get database connection instance
function getDb() {
    return getDbConnection();
}
