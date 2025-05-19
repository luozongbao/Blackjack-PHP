<?php
/**
 * Reset Password Page
 * 
 * Allows users to set a new password after requesting a password reset.
 * Validates the reset token and updates the us   
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: lobby.php');
    exit;
}

// Include database connection
require_once 'includes/database.php';

// Set page title
$pageTitle = 'Reset Password';

$error = '';
$success = '';

// Verify token from session or URL
$token = $_GET['token'] ?? $_SESSION['reset_token'] ?? null;

// Add debug logging
error_log('Token in reset_password.php: ' . ($token ?? 'null'));
error_log('Session token: ' . ($_SESSION['reset_token'] ?? 'not set'));
error_log('GET token: ' . ($_GET['token'] ?? 'not set'));

if (!$token) {
    $_SESSION['error'] = 'Missing reset token. Please try again.';
    header('Location: forget_password.php');
    exit;
}

// Function to validate token
function validateToken($db, $token) {
    // First check if the token exists without checking expiry
    $checkStmt = $db->prepare("SELECT user_id, username, email, reset_token_expiry FROM users WHERE reset_token = :token");
    $checkStmt->bindParam(':token', $token);
    $checkStmt->execute();
    $userData = $checkStmt->fetch();
    
    if (!$userData) {
        error_log('Token not found in database: ' . $token);
        return false;
    }
    
    // Token exists, now check if it's expired
    error_log('Token found for user_id: ' . $userData['user_id']);
    error_log('Token expiry from DB: ' . $userData['reset_token_expiry']);
    error_log('Current server time: ' . date('Y-m-d H:i:s'));
    
    // Compare expiry with current time
    $currentTime = new DateTime();
    $expiryTime = new DateTime($userData['reset_token_expiry']);
    $isExpired = $currentTime > $expiryTime;
    
    error_log('Token is ' . ($isExpired ? 'expired' : 'valid'));
    
    if ($isExpired) {
        return false;
    }
    
    return $userData;
}

// Pre-validate token before showing the form
try {
    $db = getDb();
    
    // First, just check if the token exists in the database at all
    $checkStmt = $db->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = :token");
    $checkStmt->bindParam(':token', $token);
    $checkStmt->execute();
    $tokenData = $checkStmt->fetch();
    
    if (!$tokenData) {
        // Token doesn't exist at all
        $_SESSION['error'] = 'Invalid password reset token. Please request a new one.';
        header('Location: forget_password.php');
        exit;
    }
    
    // Token exists, now check if it's expired
    $currentTime = new DateTime();
    $expiryTime = new DateTime($tokenData['reset_token_expiry']);
    
    if ($currentTime > $expiryTime) {
        // Token has expired
        $_SESSION['error'] = 'Your password reset token has expired. Please request a new one.';
        header('Location: forget_password.php');
        exit;
    }
    
    // Token is valid, get full user info
    $user = validateToken($db, $token);
    
    if (!$user) {
        // This shouldn't happen at this point but just in case
        $_SESSION['error'] = 'There was a problem with your password reset token. Please try again.';
        header('Location: forget_password.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Database error. Please try again later.';
    error_log('Password reset token validation error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Both password fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Get database connection
            $db = getDb();
            
            // Check if token exists and get user data
            $checkStmt = $db->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = :token");
            $checkStmt->bindParam(':token', $token);
            $checkStmt->execute();
            $userData = $checkStmt->fetch();
            
            if (!$userData) {
                $error = 'Invalid token. Please request a new password reset.';
            } else {
                // Token exists, check if it's expired
                $currentTime = new DateTime();
                $expiryTime = new DateTime($userData['reset_token_expiry']);
                
                if ($currentTime > $expiryTime) {
                    $error = 'Your password reset token has expired. Please request a new one.';
                } else {
                    // Token is valid, proceed with password reset
                    // Hash the new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update user's password and clear the reset token
                    $stmt = $db->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id");
                    $stmt->bindParam(':password', $hashedPassword);
                    $stmt->bindParam(':user_id', $userData['user_id']);
                    $stmt->execute();
                    
                    // Show success message
                    $success = 'Your password has been reset successfully. Redirecting to login page...';
                    
                    // Clear the session token
                    unset($_SESSION['reset_token']);
                    
                    // Set a success message for the login page
                    $_SESSION['success'] = 'Password successfully reset. You can now log in with your new password.';
                    
                    // JavaScript redirect after showing the message for a moment
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "login.php";
                        }, 3000);
                    </script>';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            // Log the error (not shown to the user)
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
}

// Include header - Using a standalone page structure like login page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Blackjack PHP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Reset Your Password</h1>
            <p class="text-center">Please enter your new password below.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$success): // Only show the form if not successful yet ?>
                <form method="post" action="reset_password.php<?php echo isset($_GET['token']) ? '?token=' . htmlspecialchars($_GET['token']) : ''; ?>">
                    <div class="form-group">
                        <label for="password">New Password:</label>
                        <input type="password" id="password" name="password" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" class="btn btn-block">Update Password</button>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php';
// Don't close PHP tag
?>