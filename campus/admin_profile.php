<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $query = "UPDATE admin SET name = '$name', email = '$email' WHERE admin_id = $admin_id";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['name'] = $name;
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile";
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $check_query = "SELECT password FROM admin WHERE admin_id = $admin_id";
    $result = mysqli_query($conn, $check_query);
    $admin = mysqli_fetch_assoc($result);
    
    if (password_verify($current_password, $admin['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE admin SET password = '$hashed_password' WHERE admin_id = $admin_id";
            
            if (mysqli_query($conn, $update_query)) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password";
            }
        } else {
            $error = "New passwords do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}

$admin_query = "SELECT * FROM admin WHERE admin_id = $admin_id";
$admin = mysqli_fetch_assoc(mysqli_query($conn, $admin_query));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn-primary { padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; width: 100%; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .profile-avatar { width: 100px; height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; font-weight: 700; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <!-- â•â• MOBILE TOP BAR (dashboard only) â•â• -->
<div class="mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-app-name">Campus Hub</span>
    </div>
    <span class="mobile-user-chip"><?php echo htmlspecialchars($admin_name ?? $faculty_name ?? $student_name ?? 'User'); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="dashboard-container">
        <!-- Mobile hamburger (CSS-powered, no JS needed) -->
        <input type="checkbox" id="nav-toggle" hidden>
        <button class="menu-btn" onclick="document.getElementById('nav-toggle').click()">
            <span></span><span></span><span></span>
        </button>
        <label class="nav-overlay" for="nav-toggle"></label>

        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="college-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Admin Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
                <a href="admin_manage_students.php" class="nav-item"><span>👨‍🎓</span> Manage Students</a>
                <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
                <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
                 <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
                <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
                <a href="admin_manage_events.php" class="nav-item"><span>🎉</span> Manage Events</a>
                <a href="admin_manage_placements.php" class="nav-item active"><span>💼</span> Placements</a>
                <a href="admin_send_notifications.php" class="nav-item"><span>🔔</span> Send Notifications</a>
                <a href="admin_profile.php" class="nav-item active"><span>👤</span> Profile</a>
                <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <button class="header-menu-btn" onclick="document.getElementById('nav-toggle').click()" aria-label="Toggle navigation">
                    <span></span><span></span><span></span>
                </button>
                <h1>My Profile</h1>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card" style="text-align: center; margin-bottom: 30px;">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                </div>
                <h2 style="margin-bottom: 8px;"><?php echo htmlspecialchars($admin['name']); ?></h2>
                <p style="color: #6b7280;">System Administrator</p>
            </div>

            <div class="card">
                <h2>Update Profile Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                </form>
            </div>

            <div class="card">
                <h2>Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                </form>
            </div>
        </main>
    </div>
<script>
/* â”€â”€ Mobile sidebar toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
const sidebar  = document.getElementById('mainSidebar') || document.querySelector('.sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const burger   = document.getElementById('hamburgerBtn');

function openSidebar() {
    if(sidebar) sidebar.classList.add('open');
    if(overlay) overlay.classList.add('active');
    if(burger) burger.classList.add('open');
    document.body.classList.add('sidebar-open');
}

function closeSidebar() {
    if(sidebar) sidebar.classList.remove('open');
    if(overlay) overlay.classList.remove('active');
    if(burger) burger.classList.remove('open');
    document.body.classList.remove('sidebar-open');
}

function toggleSidebar() {
    if(sidebar && sidebar.classList.contains('open')) closeSidebar();
    else openSidebar();
}

/* Close with Escape key */
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

/* Auto-close when a nav link is tapped on mobile */
document.querySelectorAll('.sidebar .nav-item').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>
</body>
</html>