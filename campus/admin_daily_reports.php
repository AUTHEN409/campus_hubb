<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$success = "";
$error = "";

// Handle admin notes submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_note'])) {
    $report_id = intval($_POST['report_id']);
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes']);
    
    $query = "UPDATE daily_reports SET admin_notes = '$admin_notes' WHERE report_id = $report_id";
    if (mysqli_query($conn, $query)) {
        $success = "Note added successfully!";
    } else {
        $error = "Failed to add note. Please try again.";
    }
}

// Fetch all reports with faculty details
$reports_query = "
    SELECT r.*, f.name as faculty_name, d.dept_name, c.category_name
    FROM daily_reports r
    JOIN faculty f ON r.faculty_id = f.faculty_id
    LEFT JOIN departments d ON f.dept_id = d.dept_id
    LEFT JOIN report_categories c ON r.category_id = c.category_id
    ORDER BY r.report_date DESC, r.created_at DESC
    LIMIT 50
";
$reports_result = mysqli_query($conn, $reports_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Daily Reports - Admin</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .report-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .faculty-info h3 { margin: 0 0 5px 0; color: #2d3748; }
        .faculty-dept { font-size: 13px; color: #718096; font-weight: 600; background: #e2e8f0; padding: 3px 8px; border-radius: 4px; display: inline-block; }
        .report-date { font-weight: 700; color: #4a5568; font-size: 14px; background: #ebf4ff; padding: 4px 10px; border-radius: 20px; color: #434190; display: inline-block; }
        .report-category { font-size: 12px; background: #e6fffa; color: #319795; padding: 4px 8px; border-radius: 20px; font-weight: 600; margin-left: 10px; display: inline-block; border: 1px solid #b2f5ea; }
        .btn-manage-categories { background: #48bb78; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: background 0.2s; }
        .btn-manage-categories:hover { background: #38a169; }
        .header-content { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 20px; }
        .header-content h1 { margin-bottom: 0; }
        .report-content { color: #2d3748; line-height: 1.6; white-space: pre-wrap; margin-bottom: 15px; }
        
        .admin-note-section { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .admin-note-section h4 { margin: 0 0 10px 0; font-size: 14px; color: #4a5568; display: flex; justify-content: space-between; }
        .admin-note-display { background: #fffbeb; padding: 12px; border-radius: 8px; border-left: 3px solid #f59e0b; font-size: 14px; color: #92400e; margin-bottom: 10px; }
        
        .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; box-sizing: border-box; }
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .btn-sm { padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; font-size: 13px; margin-top: 10px; }
        .btn-sm:hover { background: #5a67d8; }
        
        .alert-success { background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f56565; }
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
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
            <a href="admin_manage_students.php" class="nav-item"><span>👨‍🎓</span> Manage Students</a>
            <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
            <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php" class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php" class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_send_notifications.php" class="nav-item"><span>🔔</span> Send Notifications</a>
            <a href="admin_daily_reports.php" class="nav-item active"><span>📝</span> Faculty Reports</a>
            <a href="admin_profile.php" class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Faculty Daily Reports</h1>
                <a href="admin_report_categories.php" class="btn-manage-categories">Manage Categories</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (mysqli_num_rows($reports_result) > 0): ?>
            <?php while ($report = mysqli_fetch_assoc($reports_result)): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div class="faculty-info">
                            <h3><?php echo htmlspecialchars($report['faculty_name']); ?></h3>
                            <span class="faculty-dept"><?php echo htmlspecialchars($report['dept_name']); ?></span>
                        </div>
                        <div class="report-date">
                            📅 <?php echo date('F d, Y', strtotime($report['report_date'])); ?>
                            <?php if (!empty($report['category_name'])): ?>
                                <span class="report-category"><?php echo htmlspecialchars($report['category_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="report-content"><?php echo htmlspecialchars($report['content']); ?></div>
                    
                    <div class="admin-note-section">
                        <?php if (!empty($report['admin_notes'])): ?>
                            <div class="admin-note-display">
                                <strong>Your Note:</strong><br>
                                <?php echo htmlspecialchars($report['admin_notes']); ?>
                            </div>
                            <h4 style="margin-top:15px; cursor:pointer; color:#667eea;" onclick="document.getElementById('form-<?php echo $report['report_id']; ?>').style.display = 'block';">Edit Note ✎</h4>
                        <?php else: ?>
                            <h4>Add a Note</h4>
                        <?php endif; ?>
                        
                        <form id="form-<?php echo $report['report_id']; ?>" method="POST" style="<?php echo !empty($report['admin_notes']) ? 'display:none;' : ''; ?>">
                            <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                            <div class="form-group">
                                <textarea name="admin_notes" rows="2" placeholder="Leave feedback or notes for the faculty..."><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="submit_note" class="btn-sm">Save Note</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No daily reports have been submitted yet.</p>
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
