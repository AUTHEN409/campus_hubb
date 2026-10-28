<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: ../index.php");
    exit();
}

$faculty_id   = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

// ─── Mark notification as read (AJAX) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = $conn->prepare("UPDATE faculty_notifications SET is_read=1 WHERE id=? AND faculty_id=?");
    $stmt->bind_param("ii", $notif_id, $faculty_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit();
}

// ─── Mark all as read ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    mysqli_query($conn, "UPDATE faculty_notifications SET is_read=1 WHERE faculty_id=$faculty_id");
    header("Location: dashboard.php");
    exit();
}

// ─── Faculty details ──────────────────────────────────────────────────────────
$result  = mysqli_query($conn, "SELECT f.*, d.dept_name FROM faculty f JOIN departments d ON f.dept_id=d.dept_id WHERE f.faculty_id=$faculty_id");
$faculty = mysqli_fetch_assoc($result);

$classes_q   = mysqli_query($conn, "SELECT c.*, d.dept_name FROM faculty_classes fc JOIN classes c ON fc.class_id=c.class_id JOIN departments d ON c.dept_id=d.dept_id WHERE fc.faculty_id=$faculty_id");
$total_classes = mysqli_num_rows($classes_q);

$total_students = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT s.student_id) as t FROM students s JOIN faculty_classes fc ON s.class_id=fc.class_id WHERE fc.faculty_id=$faculty_id"))['t'];

$recent_att  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM attendance WHERE marked_by=$faculty_id AND date>=CURDATE()-INTERVAL 7 DAY"))['t'];

$recent_test = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM tests WHERE conducted_by=$faculty_id AND test_date>=CURDATE()-INTERVAL 7 DAY"))['t'];

$pending_ann = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM announcements WHERE faculty_id=$faculty_id AND status='pending'"))['t'];

// ─── Notifications for this faculty ──────────────────────────────────────────
$notifications = mysqli_query($conn,
    "SELECT * FROM faculty_notifications WHERE faculty_id=$faculty_id ORDER BY created_at DESC LIMIT 20");
$unread_count  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM faculty_notifications WHERE faculty_id=$faculty_id AND is_read=0"))['t'];

// ─── Today's schedule ─────────────────────────────────────────────────────────
$today = date('l');
$today_schedule = mysqli_query($conn,
    "SELECT s.*, c.class_name FROM schedule s JOIN classes c ON s.class_id=c.class_id
     JOIN faculty_classes fc ON c.class_id=fc.class_id
     WHERE fc.faculty_id=$faculty_id AND s.day_of_week='$today' AND s.faculty_id=$faculty_id
     ORDER BY s.start_time");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .action-card { background:#fff; padding:25px; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,.05);
                       text-align:center; transition:all .3s; cursor:pointer; text-decoration:none;
                       color:inherit; border:2px solid transparent; display:block; }
        .action-card:hover { transform:translateY(-5px); box-shadow:0 5px 20px rgba(0,0,0,.1); border-color:#667eea; }
        .action-icon { font-size:48px; margin-bottom:15px; }
        .action-title { font-size:16px; font-weight:600; color:#2d3748; }

        /* Notification panel */
        .notif-panel   { background:#fff; padding:25px; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,.05); margin-top:25px; }
        .notif-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .notif-header h2 { font-size:18px; color:#2d3748; display:flex; align-items:center; gap:10px; }
        .unread-badge  { background:#ef4444; color:#fff; border-radius:12px; padding:3px 10px; font-size:13px; font-weight:700; }
        .mark-all-btn  { background:none; border:1.5px solid #667eea; color:#667eea; padding:6px 14px;
                         border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; }
        .mark-all-btn:hover { background:#667eea; color:#fff; }

        .notif-empty   { text-align:center; padding:30px; color:#9ca3af; font-size:15px; }
        .notif-list    { display:flex; flex-direction:column; gap:10px; }
        .notif-item    { display:flex; align-items:flex-start; gap:14px; padding:14px 16px;
                         background:#f7fafc; border-radius:12px; border-left:4px solid #667eea;
                         transition:all .3s; position:relative; }
        .notif-item.unread  { background:#edf2ff; border-left-color:#667eea; }
        .notif-item.urgent  { border-left-color:#ef4444; }
        .notif-item.urgent.unread { background:#fff5f5; }
        .notif-item.read    { opacity:.75; }

        .notif-dot  { width:10px; height:10px; border-radius:50%; background:#667eea;
                      flex-shrink:0; margin-top:5px; }
        .notif-item.urgent .notif-dot { background:#ef4444; }
        .notif-item.read .notif-dot   { background:#d1d5db; }

        .ni-body    { flex:1; min-width:0; }
        .ni-title   { font-weight:700; color:#2d3748; font-size:15px; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
        .ni-msg     { font-size:13px; color:#718096; line-height:1.5; }
        .ni-meta    { font-size:11px; color:#9ca3af; margin-top:6px; display:flex; gap:10px; flex-wrap:wrap; }
        .badge-urg  { background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:700; }
        .badge-new  { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:700; }

        .read-btn   { background:none; border:none; cursor:pointer; font-size:18px; flex-shrink:0;
                      opacity:.5; transition:opacity .2s; padding:0; margin-top:2px; }
        .read-btn:hover { opacity:1; }

        /* Schedule */
        .schedule-card  { background:#fff; padding:20px; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:25px; }
        .schedule-item  { display:flex; justify-content:space-between; align-items:center;
                          padding:15px; background:#f7fafc; border-radius:10px; border-left:4px solid #667eea; }
        .schedule-time  { font-weight:700; color:#667eea; font-size:16px; }
        .schedule-room  { background:#667eea; color:#fff; padding:5px 12px; border-radius:8px; font-size:14px; font-weight:600; }

        .classes-taught { background:#fff; padding:25px; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
        .class-item     { padding:15px; background:#f7fafc; border-radius:10px; border-left:4px solid #48bb78; }
        .class-name     { font-weight:600; color:#2d3748; margin-bottom:5px; }
        .class-dept     { color:#718096; font-size:14px; }

        /* Sidebar notification badge */
        .nav-notif-badge { position:absolute; right:12px; top:50%; transform:translateY(-50%);
                           background:#ef4444; color:#fff; border-radius:10px; padding:1px 7px;
                           font-size:11px; font-weight:700; }
    </style>
</head>
<body>

<!-- ══ MOBILE TOP BAR (dashboard only) ══ -->
<div class="mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-app-name">Campus Hub</span>
    </div>
    <span class="mobile-user-chip"><?php echo htmlspecialchars($faculty_name); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar" id="mainSidebar">
        <div class="logo">
            <div class="college-logo">
                <img src="../assets/images/college-logo.png" alt="College Logo" onerror="this.style.display='none'">
            </div>
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Faculty Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php"         class="nav-item active" style="position:relative;">
                <span>📊</span> Dashboard
                <?php if ($unread_count > 0): ?>
                    <span class="nav-notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="faculty_mark_attendance.php"   class="nav-item"><span>📅</span> Mark Attendance</a>
            <a href="faculty_enter_marks.php"       class="nav-item"><span>📝</span> Enter Marks</a>
            <a href="faculty_upload_notes.php"      class="nav-item"><span>📚</span> Upload Notes</a>
            <a href="faculty_create_announcement.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="faculty_add_remarks.php"       class="nav-item"><span>💬</span> Add Remarks</a>
            <a href="faculty_view_students.php"     class="nav-item"><span>👥</span> View Students</a>
            <a href="faculty_daily_report.php"      class="nav-item"><span>📝</span> Daily Reports</a>
            <a href="faculty_profile.php"           class="nav-item"><span>👤</span> Profile</a>
            <a href="../logout.php"         class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <h1>Welcome, <?php echo htmlspecialchars($faculty_name); ?>!</h1>
            <div class="user-info">
                <span>Faculty</span>
                <span>|</span>
                <span><?php echo htmlspecialchars($faculty['dept_name']); ?> Department</span>
                <span>|</span>
                <span>ID: <?php echo htmlspecialchars($faculty['employee_id']); ?></span>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-details">
                    <h3>Total Students</h3>
                    <p class="stat-value"><?php echo $total_students; ?></p>
                    <span class="stat-label">Under your classes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-details">
                    <h3>Classes Teaching</h3>
                    <p class="stat-value"><?php echo $total_classes; ?></p>
                    <span class="stat-label">Total classes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-details">
                    <h3>Attendance Marked</h3>
                    <p class="stat-value"><?php echo $recent_att; ?></p>
                    <span class="stat-label">Last 7 days</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-details">
                    <h3>Tests Conducted</h3>
                    <p class="stat-value"><?php echo $recent_test; ?></p>
                    <span class="stat-label">Last 7 days</span>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════ -->
        <!-- NOTIFICATIONS PANEL                                      -->
        <!-- ════════════════════════════════════════════════════════ -->
        <div class="notif-panel">
            <div class="notif-header">
                <h2>
                    🔔 Notifications from Admin
                    <?php if ($unread_count > 0): ?>
                        <span class="unread-badge"><?php echo $unread_count; ?> new</span>
                    <?php endif; ?>
                </h2>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="mark_all_read" class="mark-all-btn">
                            ✓ Mark all as read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($notifications && mysqli_num_rows($notifications) > 0): ?>
                <div class="notif-list" id="notifList">
                    <?php while ($n = mysqli_fetch_assoc($notifications)):
                        $is_unread = !$n['is_read'];
                        $is_urgent = $n['priority'] == 'urgent';
                        $cls = '';
                        if ($is_urgent)  $cls .= ' urgent';
                        if ($is_unread)  $cls .= ' unread';
                        else             $cls .= ' read';
                    ?>
                    <div class="notif-item<?php echo $cls; ?>" id="notif-<?php echo $n['id']; ?>">
                        <div class="notif-dot"></div>
                        <div class="ni-body">
                            <div class="ni-title">
                                <?php echo htmlspecialchars($n['title']); ?>
                                <?php if ($is_urgent):  ?><span class="badge-urg">🔴 Urgent</span><?php endif; ?>
                                <?php if ($is_unread):  ?><span class="badge-new">New</span><?php endif; ?>
                            </div>
                            <div class="ni-msg"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                            <div class="ni-meta">
                                <span>🕐 <?php echo date('d M Y, g:i A', strtotime($n['created_at'])); ?></span>
                                <?php if (!$is_unread): ?>
                                    <span>✅ Read</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($is_unread): ?>
                            <button class="read-btn" title="Mark as read"
                                    onclick="markRead(<?php echo $n['id']; ?>, this)">✔️</button>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="notif-empty">
                    <div style="font-size:48px;margin-bottom:12px;">🔕</div>
                    <div>No notifications yet. You're all caught up!</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <h2 style="margin:30px 0 20px;color:#2d3748;">Quick Actions</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:30px;">
            <a href="faculty_mark_attendance.php"    class="action-card"><div class="action-icon">📅</div><div class="action-title">Mark Attendance</div></a>
            <a href="faculty_enter_marks.php"        class="action-card"><div class="action-icon">📝</div><div class="action-title">Enter Test Marks</div></a>
            <a href="faculty_upload_notes.php"       class="action-card"><div class="action-icon">📚</div><div class="action-title">Upload Notes</div></a>
            <a href="faculty_create_announcement.php" class="action-card"><div class="action-icon">📢</div><div class="action-title">Create Announcement</div></a>
            <a href="faculty_add_remarks.php"        class="action-card"><div class="action-icon">💬</div><div class="action-title">Add Student Remark</div></a>
        </div>

        <!-- Today's Schedule -->
        <div class="schedule-card">
            <h2>Today's Schedule (<?php echo $today; ?>)</h2>
            <?php if (mysqli_num_rows($today_schedule) > 0): ?>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:20px;">
                    <?php while ($cls = mysqli_fetch_assoc($today_schedule)): ?>
                        <div class="schedule-item">
                            <div class="schedule-time"><?php echo date('g:i A', strtotime($cls['start_time'])); ?></div>
                            <div style="flex:1;margin-left:20px;">
                                <div style="font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($cls['subject']); ?></div>
                                <div style="color:#718096;font-size:14px;"><?php echo htmlspecialchars($cls['class_name']); ?></div>
                            </div>
                            <div class="schedule-room">📍 <?php echo htmlspecialchars($cls['room']); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No classes scheduled for today</p>
            <?php endif; ?>
        </div>

        <!-- Classes Taught -->
        <div class="classes-taught">
            <h2>Classes You Teach</h2>
            <?php if ($total_classes > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;margin-top:20px;">
                    <?php
                    mysqli_data_seek($classes_q, 0);
                    while ($cls = mysqli_fetch_assoc($classes_q)):
                    ?>
                        <div class="class-item">
                            <div class="class-name"><?php echo htmlspecialchars($cls['class_name']); ?></div>
                            <div class="class-dept"><?php echo htmlspecialchars($cls['dept_name']); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No classes assigned yet</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Mark single notification as read via AJAX
function markRead(id, btn) {
    const item = document.getElementById('notif-' + id);
    fetch('dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'mark_read=1&notif_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Update UI
            item.classList.remove('unread');
            item.classList.add('read');
            item.querySelector('.notif-dot').style.background = '#d1d5db';
            // Remove "New" badge
            const newBadge = item.querySelector('.badge-new');
            if (newBadge) newBadge.remove();
            // Remove read button
            btn.remove();

            // Update counter badges
            const badges = document.querySelectorAll('.unread-badge, .nav-notif-badge');
            badges.forEach(b => {
                let cnt = parseInt(b.textContent);
                cnt = Math.max(0, cnt - 1);
                if (cnt === 0) b.style.display = 'none';
                else b.textContent = b.classList.contains('unread-badge') ? cnt + ' new' : cnt;
            });

            // Hide "mark all" button if no more unread
            const remaining = document.querySelectorAll('.notif-item.unread').length;
            if (remaining === 0) {
                const markAllForm = document.querySelector('form[method="POST"]');
                if (markAllForm) markAllForm.style.display = 'none';
            }
        }
    })
    .catch(() => alert('Failed to mark as read. Please refresh.'));
}
</script>

<script>
/* ── Mobile sidebar toggle (dashboard only) ─────────── */
const sidebar  = document.getElementById('mainSidebar');
const overlay  = document.getElementById('sidebarOverlay');
const burger   = document.getElementById('hamburgerBtn');

function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    burger.classList.add('open');
    document.body.classList.add('sidebar-open');
}

function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    burger.classList.remove('open');
    document.body.classList.remove('sidebar-open');
}

function toggleSidebar() {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}

/* Close with Escape key */
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

/* Auto-close when a nav link is tapped on mobile */
document.querySelectorAll('#mainSidebar .nav-item').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>
</body>
</html>