<?php
session_start();
require_once 'database.php';
require_once 'security.php';

// Check if there is a pending user session that needs to change password
if (!isset($_SESSION['pending_user_id']) || $_SESSION['pending_user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $student_id = intval($_SESSION['pending_user_id']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE students SET password = '$hashed_password', requires_password_change = 0 WHERE student_id = $student_id";
        
        if (mysqli_query($conn, $query)) {
            // Password updated successfully, log the user in fully
            $_SESSION['user_id'] = $_SESSION['pending_user_id'];
            $_SESSION['user_type'] = $_SESSION['pending_user_type'];
            $_SESSION['name'] = $_SESSION['pending_name'];
            
            // Clear pending session variables
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_user_type']);
            unset($_SESSION['pending_name']);
            
            header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Hub - Change Password</title>
    <link rel="stylesheet" href="login.css">
    <style>
        .warning-box {
            background-color: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <h1>Campus Hub</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['pending_name']); ?></p>
            </div>
            
            <div class="warning-box">
                <strong>Action Required:</strong> For security reasons, you must change your default password before accessing your dashboard.
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password (min. 6 chars)">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter new password">
                </div>
                
                <button type="submit" class="login-btn">Change Password & Login</button>
            </form>
            
            <div class="footer-text">
                <p><a href="logout.php" style="color: #667eea; text-decoration: none;">Cancel and return to login</a></p>
                <p style="margin-top: 10px;">© <?php echo date('Y'); ?> Campus Hub. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
