<?php
/**
 * Token Testing and Debugging Script
 */

require_once '../config/database.php';

echo "<h2>Password Reset Token Debug</h2>";

// Check if we have any reset tokens in the database
try {
    $db = new Database();
    
    // Get all users with reset tokens
    $sql = "SELECT id, email, name, reset_token, reset_token_expires, 
                   CASE 
                       WHEN reset_token_expires > NOW() THEN 'Valid' 
                       ELSE 'Expired' 
                   END as token_status
            FROM users 
            WHERE reset_token IS NOT NULL 
            ORDER BY reset_token_expires DESC";
    
    $result = $db->query($sql);
    
    if ($result->num_rows > 0) {
        echo "<h3>Users with Reset Tokens:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Email</th><th>Token (First 20 chars)</th><th>Expires</th><th>Status</th><th>Test Link</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $tokenPreview = substr($row['reset_token'], 0, 20) . '...';
            $testLink = "forgot-password.php?step=2&token=" . urlencode($row['reset_token']);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($tokenPreview) . "</td>";
            echo "<td>" . $row['reset_token_expires'] . "</td>";
            echo "<td>" . $row['token_status'] . "</td>";
            echo "<td><a href='$testLink' target='_blank'>Test Link</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users with reset tokens found.</p>";
    }
    
    // Show current server time
    echo "<h3>Server Time Info:</h3>";
    echo "<p>Current Server Time: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>MySQL NOW(): ";
    $timeResult = $db->query("SELECT NOW() as db_time");
    $timeRow = $timeResult->fetch_assoc();
    echo $timeRow['db_time'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test form to generate a new token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_email'])) {
    try {
        $testEmail = $_POST['test_email'];
        
        // Check if user exists
        $userResult = $db->query("SELECT id, name FROM users WHERE email = ?", [$testEmail]);
        
        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            
            // Generate new token
            $resetToken = generateSecureToken(64);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Update user with new token
            $updateSQL = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
            $db->query($updateSQL, [$resetToken, $expiresAt, $user['id']]);
            
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4>New Token Generated!</h4>";
            echo "<p><strong>Email:</strong> " . htmlspecialchars($testEmail) . "</p>";
            echo "<p><strong>Token:</strong> " . substr($resetToken, 0, 20) . "...</p>";
            echo "<p><strong>Expires:</strong> $expiresAt</p>";
            echo "<p><strong>Test Link:</strong> <a href='forgot-password.php?step=2&token=" . urlencode($resetToken) . "' target='_blank'>Click Here</a></p>";
            echo "</div>";
            
            // Refresh the page to show updated data
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        } else {
            echo "<p style='color: red;'>User with email '$testEmail' not found.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error generating token: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Token Test - GASC Blood Bridge</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <form method="POST">
        <h3>Generate Test Token</h3>
        <p>Enter an email address that exists in your users table:</p>
        <input type="email" name="test_email" placeholder="user@example.com" required style="width: 300px; padding: 8px;">
        <button type="submit" style="padding: 8px 16px; margin-left: 10px;">Generate Token</button>
    </form>
    
    <p><a href="forgot-password.php">← Back to Forgot Password</a></p>
</body>
</html>
