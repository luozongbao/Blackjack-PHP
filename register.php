<?php
/**
 * Registration Page
 * 
 * Allows new users to create an account for the Blackjack PHP application.
 * Validates user input and creates a new user account.
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
$pageTitle = 'Register';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($displayName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be between 3 and 50 characters.';
    } elseif (strlen($displayName) < 3 || strlen($displayName) > 100) {
        $error = 'Display name must be between 3 and 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Get database connection
            $db = getDb();
            
            // Check if username exists
            $checkUsernameStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkUsernameStmt->bindParam(':username', $username);
            $checkUsernameStmt->execute();
            
            if ($checkUsernameStmt->fetchColumn() > 0) {
                $error = 'Username already exists. Please choose another one.';
            } else {
                // Check if email exists
                $checkEmailStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $checkEmailStmt->bindParam(':email', $email);
                $checkEmailStmt->execute();
                
                if ($checkEmailStmt->fetchColumn() > 0) {
                    $error = 'Email already registered. Please use another email or try to recover your password.';
                } else {
                    // All checks passed, create the user
                    
                    // Hash the password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create user
                    $db->beginTransaction();
                    
                    try {
                        // Insert user
                        $createUserStmt = $db->prepare("
                            INSERT INTO users (username, password, display_name, email, created_at) 
                            VALUES (:username, :password, :display_name, :email, CURRENT_TIMESTAMP)
                        ");
                        $createUserStmt->bindParam(':username', $username);
                        $createUserStmt->bindParam(':password', $hashedPassword);
                        $createUserStmt->bindParam(':display_name', $displayName);
                        $createUserStmt->bindParam(':email', $email);
                        $createUserStmt->execute();
                        
                        // Get the new user ID
                        $userId = $db->lastInsertId();
                        
                        // Create default user settings
                        $createSettingsStmt = $db->prepare("
                            INSERT INTO user_settings (user_id) VALUES (:user_id)
                        ");
                        $createSettingsStmt->bindParam(':user_id', $userId);
                        $createSettingsStmt->execute();
                        
                        // Create initial game session
                        $createSessionStmt = $db->prepare("
                            INSERT INTO game_sessions (user_id, start_time, is_active) 
                            VALUES (:user_id, CURRENT_TIMESTAMP, 1)
                        ");
                        $createSessionStmt->bindParam(':user_id', $userId);
                        $createSessionStmt->execute();
                        
                        $db->commit();
                        
                        $success = 'Registration successful! You can now log in.';
                    } catch (PDOException $e) {
                        $db->rollBack();
                        $error = 'Database error during registration: ' . $e->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<?php include_once 'includes/header.php'; ?>

<div class="form-container">
    <h1>Create a New Account</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
            <p class="mt-2">
                <a href="login.php" class="btn btn-block">Go to Login</a>
            </p>
        </div>
    <?php else: ?>
        <p class="mb-3">Fill out the form below to create your Blackjack PHP account.</p>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                <small>Must be between 3 and 50 characters. Will be used for login.</small>
            </div>
            
            <div class="form-group">
                <label for="display_name">Display Name:</label>
                <input type="text" id="display_name" name="display_name" value="<?php echo isset($_POST['display_name']) ? htmlspecialchars($_POST['display_name']) : ''; ?>" required>
                <small>This name will be displayed to other players.</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                <small>We'll use this for password recovery.</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <small>Must be at least 8 characters long.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <small>Enter your password again to confirm.</small>
            </div>
            
            <button type="submit" class="btn btn-block">Create Account</button>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>