<?php
/**
 * Forget Password Page
 * 
 * Allows users to request a password reset by entering their username and email.
 * If the credentials match, a reset token is generated and the user is redirected
 * to the reset password form.
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
$pageTitle = 'Forgot Password';

$error = '';
$success = '';

// Check the number of failed attempts for this IP address
function checkAttemptLimits($db, $ip) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = :ip AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Record a failed attempt
function recordFailedAttempt($db, $ip) {
    $stmt = $db->prepare("INSERT INTO password_reset_attempts (ip_address, attempt_time) VALUES (:ip, NOW())");
    $stmt->bindParam(':ip', $ip);
    $stmt->execute();
}

// Process any session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Basic validation
    if (empty($username) || empty($email)) {
        $error = 'Both username and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Get database connection
            $db = getDb();
            
            // Check attempt limits
            $attempts = checkAttemptLimits($db, $ipAddress);
            $remainingAttempts = 5 - $attempts;
            
            if ($attempts >= 5) {
                $error = 'Too many attempts. Please try again after 24 hours.';
            } else {
                // Prepare and execute query to find the user
                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = :username AND email = :email");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                $user = $stmt->fetch();
                
                if ($user) {
                    // User found - generate reset token
                    $token = bin2hex(random_bytes(32));
                    // Set expiry to 24 hours from now to give ample time
                    $tokenExpiry = date('Y-m-d H:i:s', time() + 86400); // Token valid for 24 hours
                    
                    // Store the token in the database
                    $updateStmt = $db->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE user_id = :user_id");
                    $updateStmt->bindParam(':token', $token);
                    $updateStmt->bindParam(':expiry', $tokenExpiry);
                    $updateStmt->bindParam(':user_id', $user['user_id']);
                    $updateStmt->execute();
                    
                    // Debug: Log token information
                    error_log('Token generated: ' . $token);
                    error_log('Token expiry set to: ' . $tokenExpiry);
                    
                    // Store token in session and redirect to reset password page
                    $_SESSION['reset_token'] = $token;
                    
                    // Add debug logging to verify token is set
                    error_log('Reset token set: ' . $token);
                    
                    header('Location: reset_password.php?token=' . urlencode($token));
                    exit;
                } else {
                    // User not found - record failed attempt
                    recordFailedAttempt($db, $ipAddress);
                    $error = 'The username and email combination does not match our records. ' . 
                        ($remainingAttempts - 1 <= 3 ? 'You have ' . ($remainingAttempts - 1) . ' attempts remaining before lockout.' : '');
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            // Log the error (not shown to the user)
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
}

// Include header and create a custom standalone page like the login page
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
            <h1>Forgot Your Password?</h1>
            <p class="text-center">Enter your username and email to reset your password.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="post" action="forget_password.php">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <button type="submit" class="btn btn-block">Reset Password</button>
            </form>
            
            <div class="text-center mt-3">
                <p>Remember your password? <a href="login.php">Log In</a></p>
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php';
// Don't close PHP tag
?>