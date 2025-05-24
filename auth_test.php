<?php
/**
 * Simple authentication test
 */

// Test without session
session_start();

// Test AJAX authentication behavior
if (isset($_GET['test_auth'])) {
    // Simulate what happens in game.php when not logged in
    if (!isset($_SESSION['user_id'])) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'redirect' => 'login.php'
            ]);
            exit;
        }
        echo "Regular redirect would happen here\n";
        exit;
    }
    
    echo "User is authenticated\n";
    exit;
}

// Test with mock session
if (isset($_GET['test_mock'])) {
    $_SESSION['user_id'] = 1;
    echo "Mock session created. User ID: " . $_SESSION['user_id'] . "\n";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auth Test</title>
</head>
<body>
    <h1>Authentication Test</h1>
    
    <h2>Test 1: Check Authentication Status</h2>
    <p>Current session status: <?php echo isset($_SESSION['user_id']) ? 'Logged in (ID: ' . $_SESSION['user_id'] . ')' : 'Not logged in'; ?></p>
    
    <h2>Test 2: Mock Authentication</h2>
    <a href="?test_mock">Create Mock Session</a>
    
    <h2>Test 3: Test AJAX Auth</h2>
    <button onclick="testAjaxAuth()">Test AJAX Authentication</button>
    <div id="auth-result"></div>
    
    <h2>Test 4: Game Page Access</h2>
    <a href="game.php">Try to access game.php</a>
    
    <script>
        function testAjaxAuth() {
            const formData = new FormData();
            formData.append('ajax', '1');
            
            fetch('auth_test.php?test_auth', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('auth-result').innerHTML = 
                    '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                document.getElementById('auth-result').innerHTML = 
                    '<p>Error: ' + error.message + '</p>';
            });
        }
    </script>
</body>
</html>
