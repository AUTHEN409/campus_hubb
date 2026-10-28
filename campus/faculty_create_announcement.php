<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: ../index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

$success_message = "";
$error_message = "";

$classes_query = "SELECT c.* FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $classes_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_announcement'])) {
    $message = mysqli_real_escape_string($conn, $_POST['message_text']);
    $target_type = $_POST['target_type'];
    $department = isset($_POST['department']) ? mysqli_real_escape_string($conn, $_POST['department']) : NULL;
    $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : NULL;
    
    $attachment_path = NULL;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $file = $_FILES['attachment'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
        
        if (in_array($file_ext, $allowed) && $file['size'] < 5242880) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
            $upload_path = '../uploads/announcements/' . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $attachment_path = 'uploads/announcements/' . $new_filename;
            }
        }
    }
    
    $dept_value = ($target_type == 'department') ? "'$department'" : 'NULL';
    $class_value = ($target_type == 'class') ? $class_id : 'NULL';
    $attach_value = $attachment_path ? "'$attachment_path'" : 'NULL';
    
    $insert_query = "INSERT INTO announcements (faculty_id, message_text, target_type, department, class_id, attachment_path, status) 
                    VALUES ($faculty_id, '$message', '$target_type', $dept_value, $class_value, $attach_value, 'pending')";
    
    if (mysqli_query($conn, $insert_query)) {
        $success_message = "Announcement submitted! Waiting for admin approval.";
    } else {
        $error_message = "Failed to create announcement.";
    }
}

$announcements_query = "SELECT a.*, ad.name as reviewed_by_name 
                       FROM announcements a 
                       LEFT JOIN admin ad ON a.reviewed_by_admin_id = ad.admin_id 
                       WHERE a.faculty_id = $faculty_id 
                       ORDER BY a.created_at DESC";
$announcements_result = mysqli_query($conn, $announcements_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Announcement - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .announcement-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .status-approved {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-rejected {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .announcement-message {
            color: #2d3748;
            line-height: 1.6;
            margin: 10px 0;
        }
        
        .announcement-meta {
            font-size: 12px;
            color: #718096;
            margin-top: 10px;
        }
        
        .target-select {
            display: none;
        }
        
        .target-select.active {
            display: block;
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
        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="../assets/images/college-logo.png" alt="College Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Faculty Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="faculty_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
                <a href="faculty_mark_attendance.php" class="nav-item"><span>📅</span> Mark Attendance</a>
                <a href="faculty_enter_marks.php" class="nav-item"><span>📝</span> Enter Marks</a>
                <a href="faculty_upload_notes.php" class="nav-item"><span>📚</span> Upload Notes</a>
                <a href="faculty_create_announcement.php" class="nav-item active"><span>📢</span> Announcements</a>
                <a href="faculty_add_remarks.php" class="nav-item"><span>💬</span> Add Remarks</a>
                <a href="faculty_view_students.php" class="nav-item"><span>👥</span> View Students</a>
                <a href="faculty_profile.php" class="nav-item"><span>👤</span> Profile</a>
                <a href="../logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1>Create Announcement</h1>
                <div class="user-info"><span>Faculty Panel</span></div>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-section">
                <h2 style="margin-bottom: 20px;">New Announcement</h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Announcement Message</label>
                        <textarea name="message_text" placeholder="Type your announcement here..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_type" id="targetType" onchange="toggleTargetSelects()" required>
                            <option value="">-- Select Target --</option>
                            <option value="college">Entire College</option>
                            <option value="department">Specific Department</option>
                            <option value="class">Specific Class</option>
                        </select>
                    </div>
                    
                    <div class="form-group target-select" id="departmentSelect">
                        <label>Select Department</label>
                        <select name="department">
                            <option value="BCA">BCA</option>
                            <option value="BBA">BBA</option>
                            <option value="BCOM">BCOM</option>
                            <option value="BA">BA</option>
                            <option value="BAJMC">BAJMC</option>
                        </select>
                    </div>
                    
                    <div class="form-group target-select" id="classSelect">
                        <label>Select Class</label>
                        <select name="class_id">
                            <?php 
                            mysqli_data_seek($classes_result, 0);
                            while ($class = mysqli_fetch_assoc($classes_result)): 
                            ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachment (Optional)</label>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small style="color: #718096;">Max 5MB - PDF, Images, Documents</small>
                    </div>
                    
                    <button type="submit" name="create_announcement" class="submit-btn">
                        📤 Submit for Approval
                    </button>
                </form>
            </div>

            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Your Announcements</h2>
                
                <?php if (mysqli_num_rows($announcements_result) > 0): ?>
                    <div class="announcements-list">
                        <?php while ($ann = mysqli_fetch_assoc($announcements_result)): ?>
                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <span class="status-badge status-<?php echo $ann['status']; ?>">
                                        <?php echo ucfirst($ann['status']); ?>
                                    </span>
                                    <span style="font-size: 12px; color: #718096;">
                                        <?php echo date('M d, Y', strtotime($ann['created_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="announcement-message">
                                    <?php echo nl2br(htmlspecialchars($ann['message_text'])); ?>
                                </div>
                                
                                <div class="announcement-meta">
                                    Target: <strong><?php echo ucfirst($ann['target_type']); ?></strong>
                                    <?php if ($ann['department']): ?>
                                        - <?php echo $ann['department']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">You haven't created any announcements yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function toggleTargetSelects() {
            const targetType = document.getElementById('targetType').value;
            const deptSelect = document.getElementById('departmentSelect');
            const classSelect = document.getElementById('classSelect');
            
            deptSelect.classList.remove('active');
            classSelect.classList.remove('active');
            
            if (targetType === 'department') {
                deptSelect.classList.add('active');
            } else if (targetType === 'class') {
                classSelect.classList.add('active');
            }
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