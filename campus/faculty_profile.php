<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $update_query = "UPDATE faculty 
                    SET name = '$name', email = '$email', phone = '$phone' 
                    WHERE faculty_id = $faculty_id";
    
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['name'] = $name;
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Failed to update profile.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $check_query = "SELECT password FROM faculty WHERE faculty_id = $faculty_id";
    $result = mysqli_query($conn, $check_query);
    $faculty = mysqli_fetch_assoc($result);
    
    if (password_verify($current_password, $faculty['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE faculty SET password = '$hashed_password' WHERE faculty_id = $faculty_id";
            
            if (mysqli_query($conn, $update_query)) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to change password.";
            }
        } else {
            $error_message = "New passwords do not match!";
        }
    } else {
        $error_message = "Current password is incorrect!";
    }
}

$query = "SELECT f.*, d.dept_name FROM faculty f 
          JOIN departments d ON f.dept_id = d.dept_id 
          WHERE f.faculty_id = $faculty_id";
$result = mysqli_query($conn, $query);
$faculty = mysqli_fetch_assoc($result);

$classes_query = "SELECT c.class_name FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id";
$classes_result = mysqli_query($conn, $classes_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .form-group input:disabled { background: #f7fafc; color: #a0aec0; cursor: not-allowed; }
        .update-btn { width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .update-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #c6f6d5; color: #22543d; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; border-left: 4px solid #f56565; }
        .profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .profile-avatar { width: 100px; height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: white; font-weight: 700; }
        .profile-info h2 { color: #2d3748; margin-bottom: 5px; }
        .profile-info p { color: #718096; }
        .classes-list { display: flex; flex-wrap: wrap; gap: 10px; }
        .class-badge { padding: 8px 15px; background: #edf2f7; border-radius: 20px; font-size: 14px; color: #2d3748; font-weight: 600; }
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
        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="college-logo.png" alt="College Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Faculty Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="faculty_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
                <a href="faculty_mark_attendance.php" class="nav-item"><span>📅</span> Mark Attendance</a>
                <a href="faculty_enter_marks.php" class="nav-item"><span>📝</span> Enter Marks</a>
                <a href="faculty_upload_notes.php" class="nav-item"><span>📚</span> Upload Notes</a>
                <a href="faculty_create_announcement.php" class="nav-item"><span>📢</span> Announcements</a>
                <a href="faculty_add_remarks.php" class="nav-item"><span>💬</span> Add Remarks</a>
                <a href="faculty_view_students.php" class="nav-item"><span>👥</span> View Students</a>
                <a href="faculty_profile.php" class="nav-item active"><span>👤</span> Profile</a>
                <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1>My Profile</h1>
                <div class="user-info"><span>Faculty Panel</span></div>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-section">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($faculty['name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($faculty['name']); ?></h2>
                        <p><?php echo htmlspecialchars($faculty['dept_name']); ?> Department</p>
                        <p>Employee ID: <strong><?php echo htmlspecialchars($faculty['employee_id']); ?></strong></p>
                    </div>
                </div>

                <h3 style="margin-bottom: 15px; color: #2d3748;">Classes Teaching</h3>
                <div class="classes-list">
                    <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                        <span class="class-badge"><?php echo htmlspecialchars($class['class_name']); ?></span>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Update Profile</h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($faculty['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($faculty['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($faculty['employee_id']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($faculty['dept_name']); ?>" disabled>
                    </div>
                    
                    <button type="submit" name="update_profile" class="update-btn">
                        💾 Update Profile
                    </button>
                </form>
            </div>

            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Change Password</h2>
                
                <form method="POST" action="">
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
                    
                    <button type="submit" name="change_password" class="update-btn">
                        🔒 Change Password
                    </button>
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