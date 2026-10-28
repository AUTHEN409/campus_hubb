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
$success    = '';
$error      = '';

// ─── Handle Faculty Notification Send ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_faculty_notification'])) {
    $title    = trim(mysqli_real_escape_string($conn, $_POST['title']));
    $message  = trim(mysqli_real_escape_string($conn, $_POST['message']));
    $priority = in_array($_POST['priority'], ['normal','urgent']) ? $_POST['priority'] : 'normal';
    $target   = $_POST['faculty_target'];

    $faculty_ids = [];

    if ($target == 'all_faculty') {
        $res = mysqli_query($conn, "SELECT faculty_id FROM faculty");
        while ($r = mysqli_fetch_assoc($res)) $faculty_ids[] = $r['faculty_id'];

    } elseif ($target == 'individual_faculty') {
        if (!empty($_POST['faculty_ids'])) {
            $faculty_ids = array_map('intval', $_POST['faculty_ids']);
        }
    }

    if (empty($faculty_ids)) {
        $error = "Please select at least one faculty member.";
    } elseif (empty($title) || empty($message)) {
        $error = "Title and message cannot be empty.";
    } else {
        $count = 0;
        $stmt  = $conn->prepare(
            "INSERT INTO faculty_notifications (faculty_id, title, message, sent_by, priority) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($faculty_ids as $fid) {
            $stmt->bind_param("issis", $fid, $title, $message, $admin_id, $priority);
            if ($stmt->execute()) $count++;
        }
        $stmt->close();
        $success = "Notification sent to $count faculty member(s) successfully!";
    }
}

// ─── Handle Student Notification Send ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_student_notification'])) {
    $message = mysqli_real_escape_string($conn, $_POST['student_message']);
    $target  = $_POST['student_target'];

    $student_ids = [];

    if ($target == 'all') {
        $res = mysqli_query($conn, "SELECT student_id FROM students");
        while ($r = mysqli_fetch_assoc($res)) $student_ids[] = $r['student_id'];

    } elseif ($target == 'department') {
        $dept_id = intval($_POST['dept_id']);
        $res = mysqli_query($conn, "SELECT student_id FROM students WHERE dept_id = $dept_id");
        while ($r = mysqli_fetch_assoc($res)) $student_ids[] = $r['student_id'];

    } elseif ($target == 'class') {
        $class_id = intval($_POST['class_id']);
        $res = mysqli_query($conn, "SELECT student_id FROM students WHERE class_id = $class_id");
        while ($r = mysqli_fetch_assoc($res)) $student_ids[] = $r['student_id'];

    } elseif ($target == 'individual') {
        if (!empty($_POST['student_ids'])) {
            $student_ids = array_map('intval', $_POST['student_ids']);
        }
    }

    $count = 0;
    foreach ($student_ids as $sid) {
        $q = "INSERT INTO notifications (student_id, message) VALUES ($sid, '$message')";
        if (mysqli_query($conn, $q)) $count++;
    }
    $success = "Notification sent to $count student(s) successfully!";
}

// ─── Fetch data for dropdowns ─────────────────────────────────────────────────
$departments  = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
$classes      = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
$students     = mysqli_query($conn, "SELECT student_id, name, email FROM students ORDER BY name");
$faculty_list = mysqli_query($conn,
    "SELECT f.faculty_id, f.name, f.email, d.dept_name
     FROM faculty f
     JOIN departments d ON f.dept_id = d.dept_id
     ORDER BY d.dept_name, f.name");

// ─── Recent faculty notifications sent ───────────────────────────────────────
$recent_notif = mysqli_query($conn,
    "SELECT fn.*, f.name AS faculty_name
     FROM faculty_notifications fn
     JOIN faculty f ON fn.faculty_id = f.faculty_id
     ORDER BY fn.created_at DESC
     LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Tabs */
        .notif-tabs { display:flex; gap:0; margin-bottom:28px; border-radius:12px; overflow:hidden; border:2px solid #e5e7eb; }
        .notif-tab  { flex:1; padding:14px 20px; background:#f7fafc; color:#718096; font-weight:600;
                      font-size:15px; border:none; cursor:pointer; transition:all .2s; }
        .notif-tab.active  { background:#667eea; color:#fff; }
        .notif-tab:hover:not(.active) { background:#edf2ff; }

        .tab-panel        { display:none; }
        .tab-panel.active { display:block; }

        /* Form */
        .form-group       { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151; font-size:14px; }
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select { width:100%; padding:12px 14px; border:2px solid #e5e7eb; border-radius:10px;
                             font-size:14px; color:#374151; transition:border-color .2s; box-sizing:border-box; }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group select:focus { outline:none; border-color:#667eea; }

        /* Priority */
        .priority-row { display:flex; gap:12px; }
        .priority-opt { flex:1; }
        .priority-opt input { display:none; }
        .priority-opt label { display:block; padding:12px; border:2px solid #e5e7eb; border-radius:10px;
                              text-align:center; cursor:pointer; font-weight:600; font-size:14px; transition:all .2s; }
        .priority-opt input:checked + label        { border-color:#667eea; background:#edf2ff; color:#667eea; }
        .priority-opt.urgent input:checked + label { border-color:#ef4444; background:#fef2f2; color:#ef4444; }

        /* Faculty checkbox list */
        .fac-checkboxes { max-height:320px; overflow-y:auto; border:2px solid #e5e7eb; border-radius:10px; padding:5px; }
        .dept-group-lbl { padding:8px 12px; font-size:11px; font-weight:700; color:#9ca3af;
                          text-transform:uppercase; letter-spacing:.05em; background:#f9fafb;
                          border-bottom:1px solid #e5e7eb; }
        .chk-item       { display:flex; align-items:center; gap:12px; padding:10px 12px;
                          border-bottom:1px solid #f3f4f6; transition:background .15s; cursor:pointer; }
        .chk-item:hover { background:#f7f8ff; }
        .chk-item:last-child { border-bottom:none; }
        .chk-item input[type="checkbox"] { width:17px; height:17px; accent-color:#667eea; cursor:pointer; flex-shrink:0; }
        .fi-name  { font-weight:600; color:#2d3748; font-size:14px; }
        .fi-email { font-size:12px; color:#9ca3af; }
        .fi-dept  { font-size:11px; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:10px; font-weight:600; white-space:nowrap; }

        /* Select-all bar */
        .sel-all-bar { display:flex; align-items:center; justify-content:space-between; padding:10px 14px;
                       background:#f0f4ff; border-radius:10px; margin-bottom:10px; }
        .sel-all-bar label { font-weight:600; color:#4338ca; cursor:pointer; display:flex; align-items:center; gap:8px; font-size:14px; }

        /* Conditional panels */
        .tgt-panel       { display:none; }
        .tgt-panel.show  { display:block; animation:fadein .2s; }
        @keyframes fadein { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

        /* Button */
        .btn-primary { padding:13px 28px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff;
                       border:none; border-radius:10px; font-weight:700; font-size:15px; cursor:pointer;
                       width:100%; transition:all .3s; margin-top:10px; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(102,126,234,.4); }

        /* Alert */
        .alert         { padding:14px 18px; border-radius:10px; font-weight:600; margin-bottom:24px; font-size:15px; }
        .alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        /* Recent table */
        .rec-table    { width:100%; border-collapse:collapse; margin-top:15px; }
        .rec-table th { background:#f7fafc; padding:10px 14px; text-align:left; font-size:12px; font-weight:700;
                        color:#6b7280; text-transform:uppercase; letter-spacing:.04em; }
        .rec-table td { padding:12px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#374151; vertical-align:middle; }
        .rec-table tr:last-child td { border-bottom:none; }
        .badge-normal  { background:#e0e7ff; color:#4338ca; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:600; }
        .badge-urgent  { background:#fee2e2; color:#b91c1c; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:600; }
        .msg-prev      { max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#6b7280; font-size:13px; }
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
                <img src="college-logo.png" alt="Logo" onerror="this.style.display='none'">
            </div>
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php"             class="nav-item"><span>📊</span> Dashboard</a>
            <a href="admin_manage_students.php"       class="nav-item"><span>👨‍🎓</span> Manage Students</a>
            <a href="admin_manage_faculty.php"        class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="admin_manage_schedule.php"       class="nav-item"><span>📅</span> Manage Schedule</a>
             <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php"         class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php"     class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_send_notifications.php"    class="nav-item active"><span>🔔</span> Send Notifications</a>
            <a href="admin_profile.php"               class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"             class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <h1>🔔 Send Notifications</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                <span>|</span>
                <span><?php echo date('l, F d, Y'); ?></span>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="notif-tabs">
            <button class="notif-tab active" onclick="switchTab('faculty', this)">👨‍🏫 Notify Faculty</button>
            <button class="notif-tab"        onclick="switchTab('student', this)">👨‍🎓 Notify Students</button>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- FACULTY TAB                                            -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div id="tab-faculty" class="tab-panel active">
            <div class="card">
                <h2 style="margin-bottom:20px;">Send Notification to Faculty</h2>
                <form method="POST">

                    <div class="form-group">
                        <label>Notification Title *</label>
                        <input type="text" name="title" placeholder="e.g. Staff Meeting – Please Attend" required>
                    </div>

                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="5" placeholder="Type your message here…" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Priority</label>
                        <div class="priority-row">
                            <div class="priority-opt">
                                <input type="radio" name="priority" id="pn" value="normal" checked>
                                <label for="pn">🟢 Normal</label>
                            </div>
                            <div class="priority-opt urgent">
                                <input type="radio" name="priority" id="pu" value="urgent">
                                <label for="pu">🔴 Urgent</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Send To *</label>
                        <select name="faculty_target" onchange="toggleFacPanel(this.value)" required>
                            <option value="">── Select Target ──</option>
                            <option value="all_faculty">📢 All Faculty Members</option>
                            <option value="individual_faculty">✅ Select Specific Faculty</option>
                        </select>
                    </div>

                    <div class="form-group tgt-panel" id="panel-fac-ind">
                        <label>Select Faculty Members</label>
                        <div class="sel-all-bar">
                            <label>
                                <input type="checkbox" id="selAll" onchange="toggleAll(this)">
                                Select / Deselect All
                            </label>
                            <span id="selCount" style="font-size:13px;color:#6b7280;">0 selected</span>
                        </div>
                        <div class="fac-checkboxes">
                            <?php
                            $cur_dept = '';
                            while ($f = mysqli_fetch_assoc($faculty_list)):
                                if ($f['dept_name'] !== $cur_dept) {
                                    $cur_dept = $f['dept_name'];
                                    echo '<div class="dept-group-lbl">' . htmlspecialchars($cur_dept) . '</div>';
                                }
                            ?>
                            <label class="chk-item">
                                <input type="checkbox" name="faculty_ids[]"
                                       value="<?php echo $f['faculty_id']; ?>"
                                       class="fac-chk" onchange="updateCnt()">
                                <div style="flex:1;">
                                    <div class="fi-name"><?php echo htmlspecialchars($f['name']); ?></div>
                                    <div class="fi-email"><?php echo htmlspecialchars($f['email']); ?></div>
                                </div>
                                <span class="fi-dept"><?php echo htmlspecialchars($f['dept_name']); ?></span>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <button type="submit" name="send_faculty_notification" class="btn-primary">
                        📤 Send Faculty Notification
                    </button>
                </form>
            </div>

            <!-- Recent sent -->
            <div class="card" style="margin-top:25px;">
                <h2 style="margin-bottom:15px;">📋 Recently Sent to Faculty</h2>
                <?php if ($recent_notif && mysqli_num_rows($recent_notif) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="rec-table">
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Priority</th>
                                <th style="text-align:center;">Read</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($n = mysqli_fetch_assoc($recent_notif)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($n['faculty_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($n['title']); ?></td>
                                <td><div class="msg-prev"><?php echo htmlspecialchars($n['message']); ?></div></td>
                                <td>
                                    <?php if ($n['priority'] == 'urgent'): ?>
                                        <span class="badge-urgent">🔴 Urgent</span>
                                    <?php else: ?>
                                        <span class="badge-normal">🟢 Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?php echo $n['is_read'] ? '✅' : '⬜'; ?></td>
                                <td style="font-size:12px;color:#9ca3af;">
                                    <?php echo date('d M Y, g:i A', strtotime($n['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="empty-state">No faculty notifications sent yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- STUDENT TAB                                            -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div id="tab-student" class="tab-panel">
            <div class="card">
                <h2 style="margin-bottom:20px;">Send Notification to Students</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Notification Message *</label>
                        <textarea name="student_message" rows="5" placeholder="Type your message here…" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Send To *</label>
                        <select name="student_target" onchange="toggleStuPanel(this.value)" required>
                            <option value="">── Select Target ──</option>
                            <option value="all">📢 All Students</option>
                            <option value="department">🏫 Specific Department</option>
                            <option value="class">📚 Specific Class</option>
                            <option value="individual">👤 Individual Students</option>
                        </select>
                    </div>

                    <div class="form-group tgt-panel" id="panel-stu-dept">
                        <label>Select Department</label>
                        <select name="dept_id">
                            <?php while($d = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group tgt-panel" id="panel-stu-class">
                        <label>Select Class</label>
                        <select name="class_id">
                            <?php while($c = mysqli_fetch_assoc($classes)): ?>
                                <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group tgt-panel" id="panel-stu-ind">
                        <label>Select Students</label>
                        <div class="fac-checkboxes">
                            <?php while($s = mysqli_fetch_assoc($students)): ?>
                                <label class="chk-item">
                                    <input type="checkbox" name="student_ids[]" value="<?php echo $s['student_id']; ?>">
                                    <div style="flex:1;">
                                        <div class="fi-name"><?php echo htmlspecialchars($s['name']); ?></div>
                                        <div class="fi-email"><?php echo htmlspecialchars($s['email']); ?></div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <button type="submit" name="send_student_notification" class="btn-primary">
                        📤 Send Student Notification
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.notif-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

function toggleFacPanel(val) {
    document.querySelectorAll('#tab-faculty .tgt-panel').forEach(p => p.classList.remove('show'));
    if (val === 'individual_faculty') document.getElementById('panel-fac-ind').classList.add('show');
}

function toggleStuPanel(val) {
    document.querySelectorAll('#tab-student .tgt-panel').forEach(p => p.classList.remove('show'));
    if (val === 'department') document.getElementById('panel-stu-dept').classList.add('show');
    if (val === 'class')      document.getElementById('panel-stu-class').classList.add('show');
    if (val === 'individual') document.getElementById('panel-stu-ind').classList.add('show');
}

function toggleAll(cb) {
    document.querySelectorAll('.fac-chk').forEach(c => c.checked = cb.checked);
    updateCnt();
}

function updateCnt() {
    const n = document.querySelectorAll('.fac-chk:checked').length;
    document.getElementById('selCount').textContent = n + ' selected';
    const total = document.querySelectorAll('.fac-chk').length;
    document.getElementById('selAll').indeterminate = n > 0 && n < total;
    document.getElementById('selAll').checked = n === total;
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