<?php
session_start();
require_once __DIR__ . '/database.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch student details
$query = "SELECT s.*, d.dept_name, c.class_name, c.year 
          FROM students s
          JOIN departments d ON s.dept_id = d.dept_id
          JOIN classes c ON s.class_id = c.class_id
          WHERE s.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $date_of_birth = $_POST['date_of_birth'];
        
        // Handle profile picture upload
        $profile_pic = $student['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = "profile_" . $student_id . "_" . time() . "." . $ext;
                $upload_path = "uploads/profiles/" . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    $profile_pic = $upload_path;
                }
            }
        }
        
        $update_query = "UPDATE students SET name=?, phone=?, address=?, date_of_birth=?, profile_pic=? WHERE student_id=?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssssi", $name, $phone, $address, $date_of_birth, $profile_pic, $student_id);
        
        if ($update_stmt->execute()) {
            $message = "Profile updated successfully!";
            // Refresh student data
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating profile.";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $student['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pwd_query = "UPDATE students SET password=? WHERE student_id=?";
                    $pwd_stmt = $conn->prepare($pwd_query);
                    $pwd_stmt->bind_param("si", $hashed_password, $student_id);
                    
                    if ($pwd_stmt->execute()) {
                        $message = "Password changed successfully!";
                    } else {
                        $error = "Error changing password.";
                    }
                } else {
                    $error = "Password must be at least 6 characters long.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// Calculate attendance percentage
$attendance_query = "SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present_days
    FROM attendance WHERE student_id = ?";
$att_stmt = $conn->prepare($attendance_query);
$att_stmt->bind_param("i", $student_id);
$att_stmt->execute();
$attendance = $att_stmt->get_result()->fetch_assoc();
$attendance_percentage = $attendance['total_days'] > 0 ? 
    ($attendance['present_days'] / $attendance['total_days'] * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .profile-picture-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #667eea;
            margin-bottom: 15px;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 180px;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            position: relative;
            transition: color 0.3s;
        }
        
        .tab.active {
            color: #667eea;
            font-weight: 600;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .fee-status {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .fee-box {
            flex: 1;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .fee-box.total {
            background: #e3f2fd;
        }
        
        .fee-box.paid {
            background: #d4edda;
        }
        
        .fee-box.pending {
            background: #fff3cd;
        }
        
        .fee-amount {
            font-size: 28px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        @media (max-width: 968px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .fee-status {
                flex-direction: column;
            }
        }
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="college-logo.png" alt="College Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
            </div>
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="student_attendance.php" class="nav-item">
                    <span>📅</span> Attendance
                </a>
                <a href="student_marks.php" class="nav-item">
                    <span>📝</span> Marks
                </a>
                <a href="student_schedule.php" class="nav-item">
                    <span>🕐</span> Schedule
                </a>
                <a href="student_library.php" class="nav-item">
                    <span>📚</span> Library
                </a>
                <a href="student_events.php" class="nav-item">
                    <span>🎉</span> Events
                </a>
                <a href="student_placements.php" class="nav-item active">
                    <span>💼</span> Placements
                </a>
                <a href="student_performance.php" class="nav-item">
                    <span>📈</span> Performance
                </a>
                <a href="student_profile.php" class="nav-item active">
                    <span>👤</span> Profile
                </a>
                <a href="logout.php" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1>My Profile</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Left Sidebar -->
            <div class="profile-card">
                <div class="profile-picture-section">
                    <?php if ($student['profile_pic']): ?>
                        <img src="<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                             alt="Profile Picture" class="profile-picture">
                    <?php else: ?>
                        <img src="default-avatar.png" alt="Profile Picture" class="profile-picture">
                    <?php endif; ?>
                    <div class="profile-name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="profile-role"><?php echo htmlspecialchars($student['dept_name']); ?> Student</div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $student['year']; ?></div>
                        <div class="stat-label">Year</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($attendance_percentage, 1); ?>%</div>
                        <div class="stat-label">Attendance</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">₹<?php echo number_format($student['fee_paid']); ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">₹<?php echo number_format($student['fee_pending']); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-card">
                <div class="tabs">
                    <button class="tab active" onclick="showTab('personal')">Personal Information</button>
                    <button class="tab" onclick="showTab('edit')">Edit Profile</button>
                    <button class="tab" onclick="showTab('password')">Change Password</button>
                    <button class="tab" onclick="showTab('fees')">Fee Details</button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <div class="info-section">
                        <h3>Basic Information</h3>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth:</span>
                            <span class="info-value"><?php echo $student['date_of_birth'] ? date('F d, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['address'] ?: 'Not provided'); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Academic Information</h3>
                        <div class="info-row">
                            <span class="info-label">Department:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['dept_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Class:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['class_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Year:</span>
                            <span class="info-value">Year <?php echo $student['year']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Enrollment Date:</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($student['enrollment_date'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit" class="tab-content">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address"><?php echo htmlspecialchars($student['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Profile Picture</label>
                            <input type="file" name="profile_pic" accept="image/*">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div id="password" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
                
                <!-- Fee Details Tab -->
                <div id="fees" class="tab-content">
                    <div class="info-section">
                        <h3>Fee Structure</h3>
                        <div class="fee-status">
                            <div class="fee-box total">
                                <div class="stat-label">Total Fee</div>
                                <div class="fee-amount">₹<?php echo number_format($student['total_fee'], 2); ?></div>
                            </div>
                            <div class="fee-box paid">
                                <div class="stat-label">Fee Paid</div>
                                <div class="fee-amount">₹<?php echo number_format($student['fee_paid'], 2); ?></div>
                            </div>
                            <div class="fee-box pending">
                                <div class="stat-label">Fee Pending</div>
                                <div class="fee-amount">₹<?php echo number_format($student['fee_pending'], 2); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($student['fee_pending'] > 0): ?>
                            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                                <strong>Note:</strong> You have a pending fee of ₹<?php echo number_format($student['fee_pending'], 2); ?>. 
                                Please contact the accounts department for payment.
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 8px; color: #155724;">
                                <strong>✓ All fees paid!</strong> Your fee payments are up to date.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
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