<?php
session_start();
require_once 'database.php';

// Allow admin OR faculty
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin','faculty'])) {
    header("Location: index.php");
    exit();
}

$viewer_type = $_SESSION['user_type'];
$viewer_name = $_SESSION['name'];

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    header("Location: " . ($viewer_type=='admin' ? 'admin_manage_students.php' : 'faculty_view_students.php'));
    exit();
}

// ─── Student info ─────────────────────────────────────────────────────────────
$student = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT s.*, d.dept_name, c.class_name
     FROM students s
     JOIN departments d ON s.dept_id=d.dept_id
     JOIN classes c ON s.class_id=c.class_id
     WHERE s.student_id=$student_id"));

if (!$student) {
    header("Location: " . ($viewer_type=='admin' ? 'admin_manage_students.php' : 'faculty_view_students.php'));
    exit();
}

// ─── Attendance ───────────────────────────────────────────────────────────────
$att = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total,
            SUM(status='Present') AS present,
            SUM(status='Absent')  AS absent
     FROM attendance WHERE student_id=$student_id"));
$att['total']   = $att['total']   ?? 0;
$att['present'] = $att['present'] ?? 0;
$att['absent']  = $att['absent']  ?? 0;
$att_pct = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100, 1) : 0;

// Monthly attendance last 6 months
$mo_labels = $mo_present = $mo_absent = [];
$mar = mysqli_query($conn,
    "SELECT DATE_FORMAT(date,'%b %Y') AS mo,
            SUM(status='Present') AS p,
            SUM(status='Absent')  AS a
     FROM attendance
     WHERE student_id=$student_id AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(date,'%Y-%m') ORDER BY MIN(date)");
while ($r = mysqli_fetch_assoc($mar)) {
    $mo_labels[]  = $r['mo'];
    $mo_present[] = (int)$r['p'];
    $mo_absent[]  = (int)$r['a'];
}

// ─── Marks ────────────────────────────────────────────────────────────────────
$ms = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total_tests,
            ROUND(AVG((marks_obtained/total_marks)*100),1) AS avg_pct,
            ROUND(MAX((marks_obtained/total_marks)*100),1) AS best_pct,
            ROUND(MIN((marks_obtained/total_marks)*100),1) AS low_pct
     FROM tests WHERE student_id=$student_id AND total_marks > 0"));
$ms = $ms ?: ['total_tests'=>0,'avg_pct'=>null,'best_pct'=>null,'low_pct'=>null];

// Subject-wise
$subj_res = mysqli_query($conn,
    "SELECT subject,
            COUNT(*) AS cnt,
            ROUND(AVG((marks_obtained/total_marks)*100),1) AS avg_pct,
            SUM(marks_obtained) AS obtained,
            SUM(total_marks) AS possible
     FROM tests WHERE student_id=$student_id AND total_marks>0
     GROUP BY subject ORDER BY avg_pct DESC");

// Recent 8 tests
$recent_tests = mysqli_query($conn,
    "SELECT t.*, f.name AS faculty_name
     FROM tests t LEFT JOIN faculty f ON t.conducted_by=f.faculty_id
     WHERE t.student_id=$student_id AND t.total_marks>0
     ORDER BY t.test_date DESC LIMIT 8");

// Trend (last 10) for chart
$trend_rows = [];
$tr = mysqli_query($conn,
    "SELECT subject, test_date, ROUND((marks_obtained/total_marks)*100,1) AS pct
     FROM tests WHERE student_id=$student_id AND total_marks>0
     ORDER BY test_date DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($tr)) $trend_rows[] = $r;
$trend_rows   = array_reverse($trend_rows);
$trend_labels = array_map(fn($r) => date('d M', strtotime($r['test_date'])), $trend_rows);
$trend_data   = array_map(fn($r) => $r['pct'], $trend_rows);

// ─── Fee ──────────────────────────────────────────────────────────────────────
$total_fee   = (float)($student['total_fee']   ?? 0);
$fee_paid    = (float)($student['fee_paid']    ?? 0);
$fee_pending = (float)($student['fee_pending'] ?? 0);
$fee_pct     = $total_fee > 0 ? round($fee_paid / $total_fee * 100, 1) : 0;

// ─── Remarks ──────────────────────────────────────────────────────────────────
$remarks = mysqli_query($conn,
    "SELECT r.*, f.name AS faculty_name FROM remarks r
     JOIN faculty f ON r.faculty_id=f.faculty_id
     WHERE r.student_id=$student_id ORDER BY r.created_at DESC LIMIT 10");

$back = $viewer_type=='admin' ? 'admin_manage_students.php' : 'faculty_view_students.php';

// Avatar: use profile_pic only if it's a non-empty string (no file_exists on null)
$avatar_img  = !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : '';
$initials    = mb_strtoupper(mb_substr($student['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['name']); ?> – Profile | Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        /* Hero */
        .hero { background:linear-gradient(135deg,#667eea,#764ba2); border-radius:18px; padding:30px 28px;
                display:flex; align-items:center; gap:24px; color:#fff; flex-wrap:wrap; margin-bottom:26px; }
        .avatar { width:90px; height:90px; border-radius:50%; border:4px solid rgba(255,255,255,.4);
                  background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center;
                  font-size:38px; font-weight:700; flex-shrink:0; overflow:hidden; }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .hero h1 { font-size:24px; font-weight:800; margin-bottom:5px; }
        .hero-sub { opacity:.85; font-size:13px; }
        .hero-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .chip { background:rgba(255,255,255,.2); padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; }

        /* Quick stats */
        .qs { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
        .qsc { background:#fff; border-radius:14px; padding:18px 14px; text-align:center;
               box-shadow:0 2px 10px rgba(0,0,0,.05); border-top:4px solid #667eea; }
        .qsc.g  { border-top-color:#10b981; }
        .qsc.r  { border-top-color:#ef4444; }
        .qsc.y  { border-top-color:#f59e0b; }
        .qsc.p  { border-top-color:#8b5cf6; }
        .qv  { font-size:24px; font-weight:800; color:#2d3748; margin:5px 0 3px; }
        .ql  { font-size:11px; color:#9ca3af; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }

        /* Two col */
        .two { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media(max-width:860px){.two{grid-template-columns:1fr;}}

        /* Cards */
        .card { background:#fff; border-radius:14px; padding:22px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
        .sec-title { font-size:17px; font-weight:700; color:#2d3748; margin-bottom:16px; }

        /* Progress bars */
        .pb-wrap { margin-bottom:12px; }
        .pb-lbl  { display:flex; justify-content:space-between; font-size:14px; font-weight:600; color:#374151; margin-bottom:5px; }
        .pb-bar  { height:9px; background:#e5e7eb; border-radius:9px; overflow:hidden; }
        .pb-fill { height:100%; border-radius:9px; }
        .pb-g { background:linear-gradient(90deg,#10b981,#059669); }
        .pb-y { background:linear-gradient(90deg,#f59e0b,#d97706); }
        .pb-r { background:linear-gradient(90deg,#ef4444,#dc2626); }

        /* Fee ring */
        .fee-wrap { display:flex; gap:20px; align-items:center; flex-wrap:wrap; }
        .fee-ring { position:relative; width:110px; height:110px; flex-shrink:0; }
        .fee-ring svg { transform:rotate(-90deg); }
        .fee-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .fee-pct  { font-size:20px; font-weight:800; color:#2d3748; }
        .fee-txt  { font-size:10px; color:#9ca3af; font-weight:700; }
        .fee-rows { flex:1; }
        .fee-row  { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:14px; }
        .fee-row:last-child { border-bottom:none; }
        .fee-lbl  { color:#6b7280; font-weight:600; }
        .fee-val  { font-weight:700; }

        /* Tests table */
        .tests-tbl { width:100%; border-collapse:collapse; }
        .tests-tbl th { background:#f7fafc; padding:9px 12px; text-align:left; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; }
        .tests-tbl td { padding:11px 12px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#374151; vertical-align:middle; }
        .tests-tbl tr:last-child td { border-bottom:none; }
        .grade { padding:2px 8px; border-radius:7px; font-size:11px; font-weight:700; }
        .ga { background:#d1fae5; color:#065f46; }
        .gb { background:#dbeafe; color:#1e40af; }
        .gc { background:#fef3c7; color:#92400e; }
        .gf { background:#fee2e2; color:#b91c1c; }

        /* Info grid */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        @media(max-width:540px){.info-grid{grid-template-columns:1fr;}}
        .ir { display:flex; justify-content:space-between; align-items:center; padding:10px 12px;
              background:#f9fafb; border-radius:9px; }
        .ik { font-size:12px; color:#9ca3af; font-weight:700; }
        .iv { font-size:13px; color:#2d3748; font-weight:700; text-align:right; max-width:60%; word-break:break-word; }

        /* Remarks */
        .remark { padding:12px 14px; background:#fffaf0; border-radius:11px; border-left:4px solid #ed8936; margin-bottom:10px; }
        .rh { display:flex; justify-content:space-between; flex-wrap:wrap; gap:4px; margin-bottom:5px; }
        .rf { font-weight:700; color:#2d3748; font-size:14px; }
        .rd { font-size:12px; color:#9ca3af; }
        .rt { font-size:13px; color:#6b7280; line-height:1.6; }

        .back-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; background:#f3f4f6; color:#374151;
                    border-radius:9px; text-decoration:none; font-weight:600; font-size:14px; margin-bottom:20px; transition:all .2s; }
        .back-btn:hover { background:#e5e7eb; transform:translateX(-2px); }

        .chart-box { background:#fff; border-radius:14px; padding:22px; box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:20px; }
        .chart-box canvas { max-height:210px; }
        .empty { text-align:center; padding:24px; color:#9ca3af; font-style:italic; }
        .tbl-scroll { overflow-x:auto; }
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
                <img src="college-logo.png" alt="Logo" onerror="this.style.display='none'">
            </div>
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;"><?php echo $viewer_type=='admin'?'Admin Panel':'Faculty Panel'; ?></p>
        </div>
        <nav class="nav-menu">
        <?php if ($viewer_type == 'admin'): ?>
            <a href="student_dashboard.php"             class="nav-item"><span>📊</span> Dashboard</a>
            <a href="manage_students.php"       class="nav-item active"><span>👨‍🎓</span> Manage Students</a>
            <a href="manage_faculty.php"        class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="manage_schedule.php"       class="nav-item"><span>📅</span> Schedule</a>
            <a href="approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="manage_events.php"         class="nav-item"><span>🎉</span> Events</a>
            <a href="manage_placements.php"     class="nav-item"><span>💼</span> Placements</a>
            <a href="manage_fees.php"           class="nav-item"><span>💰</span> Fees</a>
            <a href="send_notifications.php"    class="nav-item"><span>🔔</span> Notifications</a>
            <a href="student_profile.php"               class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"             class="nav-item logout"><span>🚪</span> Logout</a>
        <?php else: ?>
            <a href="student_dashboard.php"             class="nav-item"><span>📊</span> Dashboard</a>
            <a href="mark_attendance.php"       class="nav-item"><span>📅</span> Mark Attendance</a>
            <a href="enter_marks.php"           class="nav-item"><span>📝</span> Enter Marks</a>
            <a href="upload_notes.php"          class="nav-item"><span>📚</span> Upload Notes</a>
            <a href="create_announcement.php"   class="nav-item"><span>📢</span> Announcements</a>
            <a href="add_remarks.php"           class="nav-item"><span>💬</span> Remarks</a>
            <a href="view_students.php"         class="nav-item active"><span>👥</span> View Students</a>
            <a href="student_profile.php"               class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"             class="nav-item logout"><span>🚪</span> Logout</a>
        <?php endif; ?>
        </nav>
    </aside>

    <main class="main-content">
        <a href="<?php echo $back; ?>" class="back-btn">← Back to Students</a>

        <!-- Hero -->
        <div class="hero">
            <div class="avatar">
                <?php if ($avatar_img): ?>
                    <img src="<?php echo $avatar_img; ?>" alt="Photo"
                         onerror="this.style.display='none';this.parentNode.textContent='<?php echo $initials; ?>'">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div>
                <h1><?php echo htmlspecialchars($student['name']); ?></h1>
                <div class="hero-sub"><?php echo htmlspecialchars($student['email']); ?></div>
                <div class="hero-chips">
                    <span class="chip">🏫 <?php echo htmlspecialchars($student['dept_name']); ?></span>
                    <span class="chip">📚 <?php echo htmlspecialchars($student['class_name']); ?></span>
                    <?php if (!empty($student['phone'])): ?>
                        <span class="chip">📱 <?php echo htmlspecialchars($student['phone']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($student['enrollment_date'])): ?>
                        <span class="chip">📅 <?php echo date('M Y', strtotime($student['enrollment_date'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="qs">
            <?php
            $ac = $att_pct >= 75 ? 'g' : ($att_pct >= 50 ? 'y' : 'r');
            $mc = ($ms['avg_pct'] !== null && $ms['avg_pct'] >= 75) ? 'g' : (($ms['avg_pct'] !== null && $ms['avg_pct'] >= 50) ? 'y' : 'r');
            $fc = $fee_pct >= 100 ? 'g' : ($fee_pct > 0 ? 'y' : 'r');
            ?>
            <div class="qsc <?php echo $ac; ?>">
                <div class="ql">Attendance</div>
                <div class="qv"><?php echo $att_pct; ?>%</div>
                <div style="font-size:12px;color:#6b7280;"><?php echo $att['present']; ?>/<?php echo $att['total']; ?> days</div>
            </div>
            <div class="qsc <?php echo $mc; ?>">
                <div class="ql">Avg Score</div>
                <div class="qv"><?php echo $ms['avg_pct'] !== null ? $ms['avg_pct'].'%' : '—'; ?></div>
                <div style="font-size:12px;color:#6b7280;"><?php echo $ms['total_tests']; ?> test(s)</div>
            </div>
            <div class="qsc <?php echo $fc; ?>">
                <div class="ql">Fee Paid</div>
                <div class="qv"><?php echo $fee_pct; ?>%</div>
                <div style="font-size:12px;color:#6b7280;">₹<?php echo number_format($fee_paid); ?></div>
            </div>
            <div class="qsc g">
                <div class="ql">Best Score</div>
                <div class="qv"><?php echo $ms['best_pct'] !== null ? $ms['best_pct'].'%' : '—'; ?></div>
                <div style="font-size:12px;color:#6b7280;">Highest test</div>
            </div>
        </div>

        <!-- Charts row -->
        <div class="two">
            <div class="chart-box">
                <div class="sec-title">📅 Monthly Attendance</div>
                <?php if (!empty($mo_labels)): ?>
                    <canvas id="attChart"></canvas>
                <?php else: ?><div class="empty">No attendance recorded.</div><?php endif; ?>
            </div>
            <div class="chart-box">
                <div class="sec-title">📈 Marks Trend</div>
                <?php if (!empty($trend_data)): ?>
                    <canvas id="trendChart"></canvas>
                <?php else: ?><div class="empty">No test records yet.</div><?php endif; ?>
            </div>
        </div>

        <!-- Subject performance + Fee -->
        <div class="two">
            <div class="card">
                <div class="sec-title">📚 Subject-wise Performance</div>
                <?php if (mysqli_num_rows($subj_res) > 0):
                    while ($s = mysqli_fetch_assoc($subj_res)):
                        $sc = $s['avg_pct'] >= 75 ? 'pb-g' : ($s['avg_pct'] >= 50 ? 'pb-y' : 'pb-r'); ?>
                    <div class="pb-wrap">
                        <div class="pb-lbl">
                            <span><?php echo htmlspecialchars($s['subject']); ?></span>
                            <span><?php echo $s['avg_pct']; ?>% <small style="color:#9ca3af;">(<?php echo $s['cnt']; ?> test<?php echo $s['cnt']!=1?'s':''; ?>)</small></span>
                        </div>
                        <div class="pb-bar"><div class="pb-fill <?php echo $sc; ?>" style="width:<?php echo min(100,$s['avg_pct']); ?>%;"></div></div>
                    </div>
                <?php endwhile; else: ?>
                    <div class="empty">No marks recorded.</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="sec-title">💰 Fee Status</div>
                <div class="fee-wrap">
                    <div class="fee-ring">
                        <?php $ring_color = $fee_pct>=100?'#10b981':($fee_pct>0?'#f59e0b':'#ef4444');
                              $dash = round(314.16 * $fee_pct / 100, 1); ?>
                        <svg width="110" height="110" viewBox="0 0 110 110">
                            <circle cx="55" cy="55" r="46" fill="none" stroke="#e5e7eb" stroke-width="11"/>
                            <circle cx="55" cy="55" r="46" fill="none"
                                    stroke="<?php echo $ring_color; ?>" stroke-width="11"
                                    stroke-dasharray="<?php echo $dash; ?> 289.03"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="fee-center">
                            <div class="fee-pct"><?php echo $fee_pct; ?>%</div>
                            <div class="fee-txt">PAID</div>
                        </div>
                    </div>
                    <div class="fee-rows">
                        <div class="fee-row"><span class="fee-lbl">Total</span><span class="fee-val">₹<?php echo number_format($total_fee,2); ?></span></div>
                        <div class="fee-row"><span class="fee-lbl">Paid</span><span class="fee-val" style="color:#10b981;">₹<?php echo number_format($fee_paid,2); ?></span></div>
                        <div class="fee-row"><span class="fee-lbl">Pending</span><span class="fee-val" style="color:#ef4444;">₹<?php echo number_format($fee_pending,2); ?></span></div>
                        <div class="fee-row">
                            <span class="fee-lbl">Status</span>
                            <?php if ($fee_pending == 0): ?>
                                <span class="grade ga">✅ Cleared</span>
                            <?php elseif ($fee_paid > 0): ?>
                                <span class="grade gc">⚠️ Partial</span>
                            <?php else: ?>
                                <span class="grade gf">❌ Unpaid</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tests -->
        <div class="card" style="margin-bottom:20px;">
            <div class="sec-title">📝 Recent Test Results</div>
            <?php if (mysqli_num_rows($recent_tests) > 0): ?>
            <div class="tbl-scroll">
                <table class="tests-tbl">
                    <thead>
                        <tr><th>Subject</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th><th>Faculty</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($t = mysqli_fetch_assoc($recent_tests)):
                        $pct = $t['total_marks'] > 0 ? round($t['marks_obtained']/$t['total_marks']*100,1) : 0;
                        $g = $pct>=75?['A','ga']:($pct>=60?['B','gb']:($pct>=40?['C','gc']:['F','gf']));
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['subject']); ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($t['test_date'])); ?></td>
                            <td><?php echo $t['marks_obtained']; ?>/<?php echo $t['total_marks']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <div style="width:60px;background:#e5e7eb;border-radius:4px;height:5px;flex-shrink:0;">
                                        <div style="width:<?php echo $pct; ?>%;background:<?php echo $pct>=75?'#10b981':($pct>=50?'#f59e0b':'#ef4444'); ?>;height:5px;border-radius:4px;"></div>
                                    </div>
                                    <span style="font-weight:700;font-size:13px;"><?php echo $pct; ?>%</span>
                                </div>
                            </td>
                            <td><span class="grade <?php echo $g[1]; ?>"><?php echo $g[0]; ?></span></td>
                            <td style="color:#6b7280;"><?php echo htmlspecialchars($t['faculty_name'] ?? '—'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty">No test records.</div><?php endif; ?>
        </div>

        <!-- Personal info + Remarks -->
        <div class="two">
            <div class="card">
                <div class="sec-title">👤 Personal Info</div>
                <div class="info-grid">
                    <div class="ir"><span class="ik">Name</span><span class="iv"><?php echo htmlspecialchars($student['name']); ?></span></div>
                    <div class="ir"><span class="ik">Email</span><span class="iv"><?php echo htmlspecialchars($student['email']); ?></span></div>
                    <div class="ir"><span class="ik">Phone</span><span class="iv"><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></span></div>
                    <div class="ir"><span class="ik">DOB</span><span class="iv"><?php echo !empty($student['date_of_birth']) ? date('d M Y',strtotime($student['date_of_birth'])) : '—'; ?></span></div>
                    <div class="ir"><span class="ik">Department</span><span class="iv"><?php echo htmlspecialchars($student['dept_name']); ?></span></div>
                    <div class="ir"><span class="ik">Class</span><span class="iv"><?php echo htmlspecialchars($student['class_name']); ?></span></div>
                    <div class="ir" style="grid-column:1/-1;"><span class="ik">Enrolled</span><span class="iv"><?php echo !empty($student['enrollment_date'])?date('d M Y',strtotime($student['enrollment_date'])):'—'; ?></span></div>
                    <?php if (!empty($student['address'])): ?>
                    <div class="ir" style="grid-column:1/-1;"><span class="ik">Address</span><span class="iv"><?php echo htmlspecialchars($student['address']); ?></span></div>
                    <?php endif; ?>
                    <div class="ir" style="grid-column:1/-1;">
                        <span class="ik">Attendance</span>
                        <span class="iv">
                            <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?php echo $att_pct>=75?'#10b981':($att_pct>=50?'#f59e0b':'#ef4444'); ?>;margin-right:5px;"></span>
                            <?php echo $att_pct>=75?'Good':'Low'; ?> (<?php echo $att_pct; ?>%)
                        </span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="sec-title">💬 Faculty Remarks</div>
                <?php if (mysqli_num_rows($remarks) > 0):
                    while ($r = mysqli_fetch_assoc($remarks)): ?>
                    <div class="remark">
                        <div class="rh">
                            <span class="rf">👨‍🏫 <?php echo htmlspecialchars($r['faculty_name']); ?></span>
                            <span class="rd"><?php echo date('d M Y', strtotime($r['created_at'])); ?></span>
                        </div>
                        <div class="rt"><?php echo htmlspecialchars($r['remark_text']); ?></div>
                    </div>
                <?php endwhile; else: ?>
                    <div class="empty">No remarks recorded.</div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
<?php if (!empty($mo_labels)): ?>
new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($mo_labels); ?>,
        datasets: [
            { label:'Present', data:<?php echo json_encode($mo_present); ?>, backgroundColor:'rgba(16,185,129,.8)', borderRadius:5 },
            { label:'Absent',  data:<?php echo json_encode($mo_absent); ?>,  backgroundColor:'rgba(239,68,68,.7)',  borderRadius:5 }
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'top'}},
               scales:{x:{stacked:true,grid:{display:false}}, y:{stacked:true,beginAtZero:true,ticks:{stepSize:1}}} }
});
<?php endif; ?>
<?php if (!empty($trend_data)): ?>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [{ label:'Score %', data:<?php echo json_encode($trend_data); ?>, borderColor:'#667eea',
                     backgroundColor:'rgba(102,126,234,.1)', borderWidth:2.5, pointRadius:5,
                     pointBackgroundColor:'#667eea', fill:true, tension:.4 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}},
               scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'},grid:{color:'#f3f4f6'}},
                       x:{grid:{display:false}}} }
});
<?php endif; ?>
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