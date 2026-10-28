<?php
session_start();
require_once 'database.php';
require_once 'security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// ─── Existing Stats ───────────────────────────────────────────────────────────
$total_students   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM students"))['t'];
$total_faculty    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM faculty"))['t'];
$total_classes    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM classes"))['t'];
$pending_ann      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM announcements WHERE status='pending'"))['t'];
$upcoming_events  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM events WHERE event_date >= CURDATE()"))['t'];

// ─── Notification Stats ───────────────────────────────────────────────────────
$total_notif_sent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM faculty_notifications"))['t'];
$unread_notif     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM faculty_notifications WHERE is_read=0"))['t'];

// ─── Recent announcements (pending) ──────────────────────────────────────────
$recent_ann = mysqli_query($conn,
    "SELECT a.*, f.name AS faculty_name
     FROM announcements a
     JOIN faculty f ON a.faculty_id = f.faculty_id
     WHERE a.status = 'pending'
     ORDER BY a.created_at DESC
     LIMIT 5");

// ─── Dept-wise student count ──────────────────────────────────────────────────
$dept_stats = mysqli_query($conn,
    "SELECT d.dept_name, COUNT(s.student_id) as cnt
     FROM departments d
     LEFT JOIN students s ON d.dept_id = s.dept_id
     GROUP BY d.dept_id, d.dept_name");

// ─── Recent faculty notifications ────────────────────────────────────────────
$recent_fac_notif = mysqli_query($conn,
    "SELECT fn.id, fn.title, fn.message, fn.priority, fn.is_read, fn.created_at,
            f.name AS faculty_name
     FROM faculty_notifications fn
     JOIN faculty f ON fn.faculty_id = f.faculty_id
     ORDER BY fn.created_at DESC
     LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .action-card { padding:25px; background:#fff; border:2px solid #e5e7eb; border-radius:12px;
                       text-align:center; transition:all .3s; cursor:pointer; }
        .action-card:hover { transform:translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,.1); border-color:#667eea; }

        /* Notification panel */
        .notif-list { display:flex; flex-direction:column; gap:12px; margin-top:16px; }
        .notif-row  { display:flex; align-items:flex-start; gap:12px; padding:12px 14px;
                      background:#f7fafc; border-radius:10px; border-left:4px solid #667eea; }
        .notif-row.urgent { border-left-color:#ef4444; background:#fff5f5; }
        .notif-icon { font-size:22px; flex-shrink:0; margin-top:2px; }
        .notif-body { flex:1; min-width:0; }
        .notif-title-text { font-weight:700; color:#2d3748; font-size:14px; margin-bottom:3px; }
        .notif-meta  { font-size:12px; color:#9ca3af; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .notif-msg-p { font-size:13px; color:#718096; margin-top:4px; white-space:nowrap;
                       overflow:hidden; text-overflow:ellipsis; max-width:340px; }
        .badge-unread { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
        .badge-read   { background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
        .badge-urg    { background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
        .notif-stat-card { position:relative; }
        .notif-badge-count { position:absolute; top:-8px; right:-8px; background:#ef4444; color:#fff;
                             border-radius:50%; width:24px; height:24px; display:flex; align-items:center;
                             justify-content:center; font-size:12px; font-weight:800; }
        .view-all-link { text-align:right; margin-top:12px; }
        .view-all-link a { font-size:13px; color:#667eea; font-weight:600; text-decoration:none; }
        .view-all-link a:hover { text-decoration:underline; }
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
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php"             class="nav-item active"><span>📊</span> Dashboard</a>
            <a href="admin_manage_students.php"       class="nav-item"><span>👨‍🎓</span> Manage Students</a>
            <a href="admin_manage_faculty.php"        class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="admin_manage_schedule.php"       class="nav-item"><span>📅</span> Manage Schedule</a>
             <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php"         class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php"     class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_send_notifications.php"    class="nav-item" style="position:relative;">
                <span>🔔</span> Send Notifications
                <?php if ($unread_notif > 0): ?>
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:#ef4444;
                                 color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;">
                        <?php echo $unread_notif; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin_daily_reports.php"        class="nav-item"><span>📝</span> Faculty Reports</a>
            <a href="admin_profile.php"              class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"            class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                <span>|</span>
                <span><?php echo date('l, F d, Y'); ?></span>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-details">
                    <h3>Total Students</h3>
                    <p class="stat-value"><?php echo $total_students; ?></p>
                    <span class="stat-label">Enrolled students</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-details">
                    <h3>Total Faculty</h3>
                    <p class="stat-value"><?php echo $total_faculty; ?></p>
                    <span class="stat-label">Teaching staff</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-details">
                    <h3>Total Classes</h3>
                    <p class="stat-value"><?php echo $total_classes; ?></p>
                    <span class="stat-label">Active classes</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📢</div>
                <div class="stat-details">
                    <h3>Pending Approvals</h3>
                    <p class="stat-value"><?php echo $pending_ann; ?></p>
                    <span class="stat-label">Announcements</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🎉</div>
                <div class="stat-details">
                    <h3>Upcoming Events</h3>
                    <p class="stat-value"><?php echo $upcoming_events; ?></p>
                    <span class="stat-label">Scheduled events</span>
                </div>
            </div>

            <div class="stat-card notif-stat-card">
                <div class="stat-icon">🔔</div>
                <?php if ($unread_notif > 0): ?>
                    <div class="notif-badge-count"><?php echo $unread_notif; ?></div>
                <?php endif; ?>
                <div class="stat-details">
                    <h3>Faculty Notifications</h3>
                    <p class="stat-value"><?php echo $total_notif_sent; ?></p>
                    <span class="stat-label"><?php echo $unread_notif; ?> unread</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h2>Quick Actions</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-top:20px;">
                <a href="admin_manage_students.php" class="action-card" style="text-decoration:none;">
                    <div style="font-size:40px;margin-bottom:10px;">➕</div>
                    <div style="font-weight:600;color:#2d3748;">Add Student</div>
                </a>
                <a href="admin_manage_faculty.php" class="action-card" style="text-decoration:none;">
                    <div style="font-size:40px;margin-bottom:10px;">➕</div>
                    <div style="font-weight:600;color:#2d3748;">Add Faculty</div>
                </a>
                <a href="admin_manage_events.php" class="action-card" style="text-decoration:none;">
                    <div style="font-size:40px;margin-bottom:10px;">🎉</div>
                    <div style="font-weight:600;color:#2d3748;">Create Event</div>
                </a>
                <a href="admin_send_notifications.php" class="action-card" style="text-decoration:none;position:relative;">
                    <div style="font-size:40px;margin-bottom:10px;">🔔</div>
                    <div style="font-weight:600;color:#2d3748;">Send Notification</div>
                    <?php if ($unread_notif > 0): ?>
                        <span style="position:absolute;top:10px;right:10px;background:#ef4444;color:#fff;
                                     border-radius:10px;padding:2px 8px;font-size:11px;font-weight:700;">
                            <?php echo $unread_notif; ?> unread
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Two-column row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:25px;">

            <!-- Pending Announcements -->
            <div class="card">
                <h2>Pending Announcements</h2>
                <?php if (mysqli_num_rows($recent_ann) > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:15px;margin-top:20px;">
                        <?php while ($ann = mysqli_fetch_assoc($recent_ann)): ?>
                            <div style="padding:15px;background:#f7fafc;border-radius:10px;border-left:4px solid #fbbf24;">
                                <div style="font-weight:600;color:#2d3748;margin-bottom:5px;">
                                    <?php echo htmlspecialchars($ann['faculty_name']); ?>
                                </div>
                                <div style="font-size:14px;color:#718096;margin-bottom:10px;">
                                    <?php echo substr(htmlspecialchars($ann['message_text']), 0, 100); ?>…
                                </div>
                                <a href="admin_approve_announcements.php" style="font-size:12px;color:#667eea;text-decoration:none;font-weight:600;">
                                    Review →
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">No pending announcements</p>
                <?php endif; ?>
            </div>

            <!-- Department Stats -->
            <div class="card">
                <h2>Department-wise Students</h2>
                <div style="margin-top:20px;">
                    <?php while ($dept = mysqli_fetch_assoc($dept_stats)): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;
                                    padding:12px;border-bottom:1px solid #e5e7eb;">
                            <span style="font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($dept['dept_name']); ?></span>
                            <span style="background:#667eea;color:#fff;padding:4px 12px;border-radius:12px;font-size:14px;font-weight:600;">
                                <?php echo $dept['cnt']; ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Faculty Notifications Panel -->
        <div class="card" style="margin-top:25px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h2>🔔 Recent Faculty Notifications</h2>
                <a href="admin_send_notifications.php" style="font-size:13px;color:#667eea;font-weight:600;text-decoration:none;">
                    Send New →
                </a>
            </div>

            <?php if ($recent_fac_notif && mysqli_num_rows($recent_fac_notif) > 0): ?>
                <div class="notif-list">
                    <?php while ($n = mysqli_fetch_assoc($recent_fac_notif)): ?>
                        <div class="notif-row <?php echo $n['priority'] == 'urgent' ? 'urgent' : ''; ?>">
                            <div class="notif-icon"><?php echo $n['priority'] == 'urgent' ? '🔴' : '🔔'; ?></div>
                            <div class="notif-body">
                                <div class="notif-title-text"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notif-msg-p"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notif-meta">
                                    <span>👤 <?php echo htmlspecialchars($n['faculty_name']); ?></span>
                                    <span>·</span>
                                    <span>🕐 <?php echo date('d M, g:i A', strtotime($n['created_at'])); ?></span>
                                    <?php if ($n['priority'] == 'urgent'): ?>
                                        <span class="badge-urg">Urgent</span>
                                    <?php endif; ?>
                                    <?php if ($n['is_read']): ?>
                                        <span class="badge-read">✅ Read</span>
                                    <?php else: ?>
                                        <span class="badge-unread">⬜ Unread</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="view-all-link">
                    <a href="admin_send_notifications.php">View All Notifications →</a>
                </div>
            <?php else: ?>
                <p class="empty-state" style="margin-top:20px;">No faculty notifications sent yet.
                    <a href="admin_send_notifications.php" style="color:#667eea;font-weight:600;">Send one now →</a>
                </p>
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