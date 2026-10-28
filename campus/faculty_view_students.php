<?php
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);
?><?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: ../index.php");
    exit();
}

$faculty_id   = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

// ─── Filters ──────────────────────────────────────────────────────────────────
$search     = isset($_GET['search'])  ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$dept_filter = isset($_GET['dept'])   ? intval($_GET['dept'])   : 0;
$class_filter = isset($_GET['class']) ? intval($_GET['class'])  : 0;
$att_filter  = isset($_GET['att'])    ? $_GET['att']            : '';

$where = "WHERE 1=1";
if ($search)       $where .= " AND (s.name LIKE '%$search%' OR s.email LIKE '%$search%')";
if ($dept_filter)  $where .= " AND s.dept_id=$dept_filter";
if ($class_filter) $where .= " AND s.class_id=$class_filter";

$students_q = mysqli_query($conn,
    "SELECT s.*, d.dept_name, c.class_name,
            ROUND((
                SELECT SUM(status='Present') / NULLIF(COUNT(*),0) * 100
                FROM attendance WHERE student_id=s.student_id
            ),1) AS att_pct,
            (SELECT ROUND(AVG((marks_obtained/total_marks)*100),1) FROM tests WHERE student_id=s.student_id) AS avg_marks,
            (SELECT COUNT(*) FROM tests WHERE student_id=s.student_id) AS test_count
     FROM students s
     JOIN departments d ON s.dept_id=d.dept_id
     JOIN classes c ON s.class_id=c.class_id
     $where
     ORDER BY s.name");

// Apply attendance filter in PHP (after fetch) so we can do it without a subquery in WHERE
$all_students = [];
while ($r = mysqli_fetch_assoc($students_q)) {
    if ($att_filter == 'good'    && ($r['att_pct'] ?? 0) < 75)  continue;
    if ($att_filter == 'low'     && ($r['att_pct'] ?? 0) >= 75) continue;
    $all_students[] = $r;
}

$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
$classes     = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
        .filters input, .filters select { padding:10px 13px; border:2px solid #e5e7eb; border-radius:10px;
                                          font-size:14px; color:#374151; transition:border-color .2s; }
        .filters input:focus, .filters select:focus { outline:none; border-color:#667eea; }
        .filter-btn { padding:10px 20px; background:#667eea; color:#fff; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:14px; }
        .filter-btn:hover { background:#5568d3; }
        .reset-lnk  { padding:10px 14px; background:#f3f4f6; color:#6b7280; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:14px; text-decoration:none; }

        .student-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }
        .student-card { background:#fff; border-radius:16px; box-shadow:0 2px 10px rgba(0,0,0,.06);
                        padding:22px; transition:all .25s; border:2px solid transparent; }
        .student-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.1); border-color:#667eea; }

        .sc-header { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
        .sc-avatar  { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2);
                      display:flex; align-items:center; justify-content:center; color:#fff;
                      font-size:22px; font-weight:700; flex-shrink:0; overflow:hidden; }
        .sc-avatar img { width:100%; height:100%; object-fit:cover; }
        .sc-name    { font-weight:700; color:#2d3748; font-size:16px; margin-bottom:3px; }
        .sc-sub     { font-size:12px; color:#9ca3af; }

        .sc-stats   { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:14px; }
        .sc-stat    { background:#f9fafb; border-radius:10px; padding:10px 8px; text-align:center; }
        .sc-stat-val { font-size:17px; font-weight:800; color:#2d3748; }
        .sc-stat-lbl { font-size:10px; color:#9ca3af; font-weight:700; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }

        .progress-mini { height:6px; background:#e5e7eb; border-radius:4px; margin-top:6px; overflow:hidden; }
        .progress-fill { height:100%; border-radius:4px; }

        .sc-footer  { display:flex; gap:8px; }
        .btn-view   { flex:1; padding:9px 0; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff;
                      border:none; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer;
                      text-decoration:none; text-align:center; transition:all .2s; display:block; }
        .btn-view:hover { opacity:.9; transform:translateY(-1px); }
        .btn-dept  { padding:9px 14px; background:#f0f4ff; color:#4338ca; border:none; border-radius:10px;
                     font-size:12px; font-weight:700; white-space:nowrap; }

        .result-meta { font-size:13px; color:#9ca3af; margin-bottom:14px; }

        .att-indicator { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
        .att-good { background:#10b981; } .att-warn { background:#f59e0b; } .att-bad { background:#ef4444; }

        .fee-badge-ok   { background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
        .fee-badge-part { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
        .fee-badge-no   { background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
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
                <img src="../assets/images/college-logo.png" alt="Logo" onerror="this.style.display='none'">
            </div>
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Faculty Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="faculty_dashboard.php"           class="nav-item"><span>📊</span> Dashboard</a>
            <a href="faculty_mark_attendance.php"     class="nav-item"><span>📅</span> Mark Attendance</a>
            <a href="faculty_enter_marks.php"         class="nav-item"><span>📝</span> Enter Marks</a>
            <a href="faculty_upload_notes.php"        class="nav-item"><span>📚</span> Upload Notes</a>
            <a href="faculty_create_announcement.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="faculty_add_remarks.php"         class="nav-item"><span>💬</span> Add Remarks</a>
            <a href="faculty_view_students.php"       class="nav-item active"><span>👥</span> View Students</a>
            <a href="faculty_profile.php"             class="nav-item"><span>👤</span> Profile</a>
            <a href="../logout.php"           class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>👥 All Students</h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($faculty_name); ?></span>
                <span>|</span>
                <span><?php echo count($all_students); ?> student(s) shown</span>
            </div>
        </header>

        <!-- Filters -->
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="🔍 Search name or email…"
                   value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:180px;">
            <select name="dept">
                <option value="">All Departments</option>
                <?php while($d=mysqli_fetch_assoc($departments)): ?>
                    <option value="<?php echo $d['dept_id']; ?>" <?php echo $dept_filter==$d['dept_id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($d['dept_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="class">
                <option value="">All Classes</option>
                <?php while($c=mysqli_fetch_assoc($classes)): ?>
                    <option value="<?php echo $c['class_id']; ?>" <?php echo $class_filter==$c['class_id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="att">
                <option value="">All Attendance</option>
                <option value="good" <?php echo $att_filter=='good'?'selected':''; ?>>✅ Good (≥75%)</option>
                <option value="low"  <?php echo $att_filter=='low' ?'selected':''; ?>>⚠️ Low (&lt;75%)</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="view_students.php" class="reset-lnk">Reset</a>
        </form>

        <!-- Student Cards Grid -->
        <?php if (empty($all_students)): ?>
            <div style="text-align:center;padding:50px;color:#9ca3af;">
                <div style="font-size:48px;margin-bottom:14px;">🔍</div>
                <div style="font-size:18px;font-weight:600;">No students found</div>
                <div style="font-size:14px;margin-top:6px;">Try adjusting your filters</div>
            </div>
        <?php else: ?>
            <div class="student-grid">
            <?php foreach ($all_students as $s):
                $att = $s['att_pct'] ?? 0;
                $att_cls = $att >= 75 ? 'att-good' : ($att >= 50 ? 'att-warn' : 'att-bad');
                $att_col = $att >= 75 ? '#10b981' : ($att >= 50 ? '#f59e0b' : '#ef4444');
                $marks = $s['avg_marks'] ?? 0;
                $marks_col = $marks >= 75 ? '#10b981' : ($marks >= 50 ? '#f59e0b' : '#ef4444');
                $initials = mb_strtoupper(mb_substr($s['name'],0,1));
                if ($s['fee_pending'] == 0)       $fee_b = '<span class="fee-badge-ok">✅ Paid</span>';
                elseif ($s['fee_paid'] > 0)        $fee_b = '<span class="fee-badge-part">⚠️ Partial</span>';
                else                               $fee_b = '<span class="fee-badge-no">❌ Unpaid</span>';
            ?>
                <div class="student-card">
                    <div class="sc-header">
                        <div class="sc-avatar">
                            <?php if (!empty($s['profile_pic']) && file_exists('../'.$s['profile_pic'])): ?>
                                <img src="../<?php echo htmlspecialchars($s['profile_pic']); ?>" alt="">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="sc-name"><?php echo htmlspecialchars($s['name']); ?></div>
                            <div class="sc-sub"><?php echo htmlspecialchars($s['dept_name']); ?> · <?php echo htmlspecialchars($s['class_name']); ?></div>
                            <div style="margin-top:4px;"><?php echo $fee_b; ?></div>
                        </div>
                    </div>

                    <div class="sc-stats">
                        <div class="sc-stat">
                            <div class="sc-stat-val" style="color:<?php echo $att_col; ?>"><?php echo $att; ?>%</div>
                            <div class="sc-stat-lbl">Attendance</div>
                            <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100,$att); ?>%;background:<?php echo $att_col; ?>;"></div></div>
                        </div>
                        <div class="sc-stat">
                            <div class="sc-stat-val" style="color:<?php echo $marks_col; ?>"><?php echo $marks ?: '—'; ?><?php echo $marks?'%':''; ?></div>
                            <div class="sc-stat-lbl">Avg Marks</div>
                            <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100,$marks); ?>%;background:<?php echo $marks_col; ?>;"></div></div>
                        </div>
                        <div class="sc-stat">
                            <div class="sc-stat-val"><?php echo $s['test_count']; ?></div>
                            <div class="sc-stat-lbl">Tests</div>
                        </div>
                    </div>

                    <div class="sc-footer">
                        <a href="student_profile_view.php?student_id=<?php echo $s['student_id']; ?>"
                           class="btn-view">👁 View Full Profile</a>
                    </div>
                </div>
            <?php endforeach; ?>
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