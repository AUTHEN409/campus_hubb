<?php
session_start();
require_once 'database.php';

// Auto-create report_categories table and add category_id if missing
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS report_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
try {
    mysqli_query($conn, "ALTER TABLE daily_reports ADD COLUMN category_id INT NULL AFTER content");
    mysqli_query($conn, "ALTER TABLE daily_reports ADD CONSTRAINT fk_daily_reports_category FOREIGN KEY (category_id) REFERENCES report_categories(category_id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Column already exists, ignore the exception
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];
$success = "";
$error = "";

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $report_date = mysqli_real_escape_string($conn, $_POST['report_date']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $category_id = isset($_POST['category_id']) && !empty($_POST['category_id']) ? intval($_POST['category_id']) : "NULL";
    
    // Check if report already exists for this date
    $check_query = "SELECT report_id FROM daily_reports WHERE faculty_id = $faculty_id AND report_date = '$report_date'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "You have already submitted a report for this date.";
    } else {
        $query = "INSERT INTO daily_reports (faculty_id, report_date, category_id, content) VALUES ($faculty_id, '$report_date', $category_id, '$content')";
        if (mysqli_query($conn, $query)) {
            $success = "Daily report submitted successfully!";
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

// Fetch categories for dropdown
$categories_result = mysqli_query($conn, "SELECT * FROM report_categories ORDER BY category_name ASC");

// Fetch past reports
$reports_query = "SELECT r.*, c.category_name FROM daily_reports r LEFT JOIN report_categories c ON r.category_id = c.category_id WHERE r.faculty_id = $faculty_id ORDER BY r.report_date DESC LIMIT 30";
$reports_result = mysqli_query($conn, $reports_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Reports - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .btn-submit { background: #667eea; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #5a67d8; }
        .alert-success { background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f56565; }
        .report-card { background: #f7fafc; padding: 20px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #667eea; }
        .report-date { font-weight: 700; color: #4a5568; margin-bottom: 10px; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        .report-category { font-size: 12px; background: #ebf4ff; color: #3182ce; padding: 3px 8px; border-radius: 12px; font-weight: 600; }
        .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; box-sizing: border-box; background: white; }
        .form-group select:focus { outline: none; border-color: #667eea; }
        .report-content { color: #2d3748; line-height: 1.5; white-space: pre-wrap; }
        .admin-note { background: #fffbeb; padding: 12px; border-radius: 8px; margin-top: 15px; border-left: 3px solid #f59e0b; font-size: 14px; color: #92400e; }
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
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Faculty Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="faculty_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
            <a href="faculty_mark_attendance.php" class="nav-item"><span>📅</span> Mark Attendance</a>
            <a href="faculty_enter_marks.php" class="nav-item"><span>📝</span> Enter Marks</a>
            <a href="faculty_upload_notes.php" class="nav-item"><span>📚</span> Upload Notes</a>
            <a href="faculty_create_announcement.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="faculty_add_remarks.php" class="nav-item"><span>💬</span> Add Remarks</a>
            <a href="faculty_view_students.php" class="nav-item"><span>👥</span> View Students</a>
            <a href="faculty_daily_report.php" class="nav-item active"><span>📝</span> Daily Reports</a>
            <a href="faculty_profile.php" class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Daily Reports</h1>
        </header>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Submit Today's Report</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Report Date</label>
                    <input type="date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">-- Select Category (Optional) --</option>
                        <?php 
                        if ($categories_result && mysqli_num_rows($categories_result) > 0) {
                            mysqli_data_seek($categories_result, 0); // reset pointer in case
                            while ($cat = mysqli_fetch_assoc($categories_result)): 
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Report Content</label>
                    <textarea name="content" rows="5" required placeholder="What did you teach or accomplish today?"></textarea>
                </div>
                <button type="submit" name="submit_report" class="btn-submit">Submit Report</button>
            </form>
        </div>

        <div class="card" style="margin-top: 25px;">
            <h2>Past Reports</h2>
            <?php if (mysqli_num_rows($reports_result) > 0): ?>
                <?php while ($report = mysqli_fetch_assoc($reports_result)): ?>
                    <div class="report-card">
                        <div class="report-date">
                            <span>📅 <?php echo date('F d, Y', strtotime($report['report_date'])); ?></span>
                            <?php if (!empty($report['category_name'])): ?>
                                <span class="report-category"><?php echo htmlspecialchars($report['category_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="report-content"><?php echo htmlspecialchars($report['content']); ?></div>
                        <?php if (!empty($report['admin_notes'])): ?>
                            <div class="admin-note">
                                <strong>Admin Note:</strong><br>
                                <?php echo htmlspecialchars($report['admin_notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No past reports found.</p>
            <?php endif; ?>
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
