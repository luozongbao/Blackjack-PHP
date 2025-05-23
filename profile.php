<?php
/**
 * Profile Page
 * 
 * Allows users to view and update their profile information.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'includes/database.php';

// Set page title
$pageTitle = 'My Profile';

// Initialize variables
$userId = $_SESSION['user_id'];
$displayNameError = '';
$emailError = '';
$passwordError = '';
$success = '';

// Get user data
try {
    $db = getDb();
    $stmt = $db->prepare("SELECT username, display_name, email FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Display Name
    if (isset($_POST['update_display_name'])) {
        $newDisplayName = trim($_POST['display_name']);
        
        if (empty($newDisplayName)) {
            $displayNameError = 'Display name cannot be empty.';
        } elseif (strlen($newDisplayName) < 3 || strlen($newDisplayName) > 100) {
            $displayNameError = 'Display name must be between 3 and 100 characters.';
        } else {
            try {
                $updateStmt = $db->prepare("UPDATE users SET display_name = :display_name WHERE user_id = :user_id");
                $updateStmt->bindParam(':display_name', $newDisplayName);
                $updateStmt->bindParam(':user_id', $userId);
                $updateStmt->execute();
                
                // Update session variable
                $_SESSION['display_name'] = $newDisplayName;
                $user['display_name'] = $newDisplayName;
                $success = 'Display name updated successfully!';
            } catch (PDOException $e) {
                $displayNameError = 'Error updating display name: ' . $e->getMessage();
            }
        }
    }
    
    // Update Email
    if (isset($_POST['update_email'])) {
        $newEmail = trim($_POST['email']);
        
        if (empty($newEmail)) {
            $emailError = 'Email address cannot be empty.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $emailError = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email is already in use by another user
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
                $checkStmt->bindParam(':email', $newEmail);
                $checkStmt->bindParam(':user_id', $userId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    $emailError = 'Email address is already in use by another account.';
                } else {
                    $updateStmt = $db->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
                    $updateStmt->bindParam(':email', $newEmail);
                    $updateStmt->bindParam(':user_id', $userId);
                    $updateStmt->execute();
                    
                    $user['email'] = $newEmail;
                    $success = 'Email address updated successfully!';
                }
            } catch (PDOException $e) {
                $emailError = 'Error updating email address: ' . $e->getMessage();
            }
        }
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $passwordError = 'All password fields are required.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New passwords do not match.';
        } else {
            try {
                // Verify current password
                $verifyStmt = $db->prepare("SELECT password FROM users WHERE user_id = :user_id");
                $verifyStmt->bindParam(':user_id', $userId);
                $verifyStmt->execute();
                $storedHash = $verifyStmt->fetchColumn();
                
                if (!password_verify($currentPassword, $storedHash)) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    // Hash the new password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update the password
                    $updateStmt = $db->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
                    $updateStmt->bindParam(':password', $newHash);
                    $updateStmt->bindParam(':user_id', $userId);
                    $updateStmt->execute();
                    
                    $success = 'Password changed successfully!';
                }
            } catch (PDOException $e) {
                $passwordError = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}

include_once 'includes/header.php';
?>

<div class="card mb-3">
    <h1>My Profile</h1>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
</div>

<div class="profile-container">
    <div class="row">
        <div class="col-md-6">
            <!-- Username (Non-editable) -->
            <div class="card mb-3">
                <h3>Account Information</h3>
                <div class="profile-item">
                    <label>Username:</label>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                    <small>Username cannot be changed.</small>
                </div>
            </div>
            
            <!-- Display Name -->
            <div class="card mb-3">
                <h3>Display Name</h3>
                <?php if (!empty($displayNameError)): ?>
                    <div class="alert alert-danger"><?php echo $displayNameError; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="display_name">Display Name:</label>
                        <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name']); ?>" required>
                        <small>This name will be displayed to other players.</small>
                    </div>
                    <button type="submit" name="update_display_name" class="btn">Update Display Name</button>
                </form>
            </div>
            
            <!-- Email -->
            <div class="card mb-3">
                <h3>Email Address</h3>
                <?php if (!empty($emailError)): ?>
                    <div class="alert alert-danger"><?php echo $emailError; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <small>Used for password recovery and notifications.</small>
                    </div>
                    <button type="submit" name="update_email" class="btn">Update Email</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Change Password -->
            <div class="card">
                <h3>Change Password</h3>
                <?php if (!empty($passwordError)): ?>
                    <div class="alert alert-danger"><?php echo $passwordError; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>Must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>