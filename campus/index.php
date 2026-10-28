<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
?><?php
session_start();
require_once 'database.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] == 'student') {
        header("Location: student_dashboard.php");
    } elseif ($_SESSION['user_type'] == 'faculty') {
        header("Location: faculty_dashboard.php");
    } elseif ($_SESSION['user_type'] == 'admin') {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$error = "";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check in students table
    $query = "SELECT * FROM students WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            // Check if password change is required
            if (isset($user['requires_password_change']) && $user['requires_password_change'] == 1) {
                $_SESSION['pending_user_id'] = $user['student_id'];
                $_SESSION['pending_user_type'] = 'student';
                $_SESSION['pending_name'] = $user['name'];
                header("Location: force_change_password.php");
                exit();
            }

            $_SESSION['user_id'] = $user['student_id'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['name'] = $user['name'];
            header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        // Check in faculty table
        $query = "SELECT * FROM faculty WHERE email = '$email'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['faculty_id'];
                $_SESSION['user_type'] = 'faculty';
                $_SESSION['name'] = $user['name'];
                header("Location: faculty_dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
         // Check in admin table
$query = "SELECT * FROM admin WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 1) {
    $user = mysqli_fetch_assoc($result);

    // Plain text password check
    if ($password == $user['password']) {

        $_SESSION['user_id'] = $user['admin_id'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['name'] = $user['name'];

        header("Location: admin_dashboard.php");
        exit();

    } else {
        $error = "Invalid password!";
    }

} else {
    $error = "Email not found!";
}
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Hub - Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <h1>Campus Hub</h1>
                <p>Your College Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="footer-text">
                <p>© 2024 Campus Hub. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>