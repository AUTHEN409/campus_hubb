<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

$success_message = "";
$error_message = "";

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Remark added successfully!";
}

$classes_query = "SELECT c.* FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $classes_query);

if (!$classes_result) {
    $error_message = "Error loading classes: " . mysqli_error($conn);
}

$selected_class = isset($_POST['class_id']) ? $_POST['class_id'] : (isset($_GET['class_id']) ? $_GET['class_id'] : '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_remark'])) {
    $student_id = intval($_POST['student_id']);
    $remark_text = mysqli_real_escape_string($conn, $_POST['remark_text']);
    $selected_class = intval($_POST['class_id']); // Preserve selected class
    
    $insert_query = "INSERT INTO remarks (student_id, faculty_id, remark_text) 
                    VALUES ($student_id, $faculty_id, '$remark_text')";
    
    if (mysqli_query($conn, $insert_query)) {
        // Redirect to maintain the class selection
        header("Location: faculty_add_remarks.php?class_id=" . $selected_class . "&success=1");
        exit();
    } else {
        $error_message = "Failed to add remark: " . mysqli_error($conn);
    }
}

$students = [];
if ($selected_class) {
    $students_query = "SELECT s.*, 
                              (SELECT COUNT(*) FROM remarks WHERE student_id = s.student_id) as total_remarks
                       FROM students s 
                       WHERE s.class_id = $selected_class 
                       ORDER BY s.name";
    $students_result = mysqli_query($conn, $students_query);
    
    if ($students_result) {
        while ($student = mysqli_fetch_assoc($students_result)) {
            $students[] = $student;
        }
    } else {
        $error_message = "Error loading students: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Remarks - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; }
        .load-btn { padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .load-btn:hover { background: #5568d3; transform: translateY(-2px); }
        .students-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .student-card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; transition: all 0.3s ease; }
        .student-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .student-name { font-size: 18px; font-weight: 600; color: #2d3748; margin-bottom: 10px; }
        .student-info { font-size: 14px; color: #718096; margin-bottom: 15px; }
        .remark-form textarea { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; min-height: 80px; resize: vertical; font-family: inherit; margin-bottom: 10px; }
        .add-btn { width: 100%; padding: 10px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .add-btn:hover { transform: translateY(-2px); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #c6f6d5; color: #22543d; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; border-left: 4px solid #f56565; }
        .remark-count { display: inline-block; padding: 4px 10px; background: #edf2f7; border-radius: 12px; font-size: 12px; color: #4a5568; font-weight: 600; }
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
                <a href="faculty_add_remarks.php" class="nav-item active"><span>💬</span> Add Remarks</a>
                <a href="faculty_view_students.php" class="nav-item"><span>👥</span> View Students</a>
                <a href="faculty_profile.php" class="nav-item"><span>👤</span> Profile</a>
                <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <button class="header-menu-btn" onclick="document.getElementById('nav-toggle').click()" aria-label="Toggle navigation">
                    <span></span><span></span><span></span>
                </button>
                <h1>Add Student Remarks</h1>
                <div class="user-info"><span>Faculty Panel</span></div>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Select Class</h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Select Class</label>
                        <select name="class_id" required>
                            <option value="">-- Choose a class --</option>
                            <?php 
                            if ($classes_result) {
                                mysqli_data_seek($classes_result, 0);
                                while ($class = mysqli_fetch_assoc($classes_result)): 
                            ?>
                                <option value="<?php echo $class['class_id']; ?>" 
                                        <?php echo ($selected_class == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="load-btn">Load Students</button>
                </form>
            </div>

            <?php if (!empty($students)): ?>
                <div class="form-section">
                    <h2 style="margin-bottom: 20px;">Add Remarks</h2>
                    
                    <div class="students-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="student-card">
                                <div class="student-name">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <span class="remark-count"><?php echo $student['total_remarks']; ?> remarks</span>
                                </div>
                                
                                <div class="student-info">
                                    📧 <?php echo htmlspecialchars($student['email']); ?>
                                </div>
                                
                                <form method="POST" action="" class="remark-form">
                                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                    
                                    <textarea name="remark_text" placeholder="Enter your remark about this student..." required></textarea>
                                    
                                    <button type="submit" name="add_remark" class="add-btn">
                                        💬 Add Remark
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($selected_class): ?>
                <div class="form-section">
                    <p class="empty-state">No students found in this class.</p>
                </div>
            <?php endif; ?>
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