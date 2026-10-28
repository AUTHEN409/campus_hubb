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
$success = $error = '';

// ─── Add Payment ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $student_id   = intval($_POST['student_id']);
    $amount       = floatval($_POST['amount_paid']);
    $mode         = in_array($_POST['payment_mode'], ['cash','online','cheque','dd']) ? $_POST['payment_mode'] : 'cash';
    $receipt_no   = trim(mysqli_real_escape_string($conn, $_POST['receipt_no']));
    $remarks      = trim(mysqli_real_escape_string($conn, $_POST['remarks']));
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);

    // Get current fee state
    $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT total_fee, fee_paid, fee_pending FROM students WHERE student_id=$student_id"));
    if (!$cur) { $error = "Student not found."; goto END; }

    if ($amount <= 0) { $error = "Amount must be greater than 0."; goto END; }
    if ($amount > $cur['fee_pending']) { $error = "Amount exceeds pending fee (₹" . number_format($cur['fee_pending'],2) . ")."; goto END; }

    $new_paid    = $cur['fee_paid'] + $amount;
    $new_pending = $cur['total_fee'] - $new_paid;
    $new_status  = ($new_pending <= 0) ? 'paid' : 'partial';

    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, amount_paid, payment_mode, receipt_no, remarks, recorded_by, payment_date) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("idsssss", $student_id, $amount, $mode, $receipt_no, $remarks, $admin_id, $payment_date);
    $stmt->execute(); $stmt->close();

    // Update student fee columns
    mysqli_query($conn, "UPDATE students SET fee_paid=$new_paid, fee_pending=$new_pending WHERE student_id=$student_id");

    $success = "Payment of ₹" . number_format($amount, 2) . " recorded successfully!";
}

// ─── Update Total Fee ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fee'])) {
    $student_id = intval($_POST['student_id']);
    $total_fee  = floatval($_POST['total_fee']);
    $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT fee_paid FROM students WHERE student_id=$student_id"));
    if ($cur) {
        $new_pending = max(0, $total_fee - $cur['fee_paid']);
        mysqli_query($conn, "UPDATE students SET total_fee=$total_fee, fee_pending=$new_pending WHERE student_id=$student_id");
        $success = "Total fee updated successfully!";
    }
}

END:

// ─── Filters ──────────────────────────────────────────────────────────────────
$filter_dept   = isset($_GET['dept'])   ? intval($_GET['dept'])   : 0;
$filter_status = isset($_GET['status']) ? $_GET['status']         : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where = "WHERE 1=1";
if ($filter_dept)   $where .= " AND s.dept_id=$filter_dept";
if ($filter_status == 'paid')    $where .= " AND s.fee_pending=0";
if ($filter_status == 'pending') $where .= " AND s.fee_pending>0 AND s.fee_paid=0";
if ($filter_status == 'partial') $where .= " AND s.fee_pending>0 AND s.fee_paid>0";
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.email LIKE '%$search%')";

$students = mysqli_query($conn,
    "SELECT s.*, d.dept_name, c.class_name,
            ROUND((s.fee_paid / NULLIF(s.total_fee,0)) * 100, 1) AS pct
     FROM students s
     JOIN departments d ON s.dept_id = d.dept_id
     LEFT JOIN classes c ON s.class_id = c.class_id
     $where
     ORDER BY s.name");

// ─── Summary Stats ────────────────────────────────────────────────────────────
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total,
            SUM(total_fee) AS sum_total,
            SUM(fee_paid) AS sum_paid,
            SUM(fee_pending) AS sum_pending,
            SUM(fee_pending=0) AS fully_paid,
            SUM(fee_pending>0 AND fee_paid>0) AS partial,
            SUM(fee_paid=0) AS not_paid
     FROM students"));

$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* ── Stat cards ── */
        .fee-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:28px; }
        .fee-stat  { background:#fff; border-radius:14px; padding:20px 16px; box-shadow:0 2px 10px rgba(0,0,0,.06);
                     text-align:center; border-top:4px solid #667eea; }
        .fee-stat.green  { border-top-color:#10b981; }
        .fee-stat.red    { border-top-color:#ef4444; }
        .fee-stat.yellow { border-top-color:#f59e0b; }
        .fee-stat.purple { border-top-color:#8b5cf6; }
        .fee-stat-val  { font-size:22px; font-weight:800; color:#2d3748; margin:6px 0 4px; }
        .fee-stat-lbl  { font-size:12px; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
        .fee-stat-sub  { font-size:13px; color:#6b7280; margin-top:4px; }

        /* ── Filters ── */
        .filters { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
        .filters input[type="text"],
        .filters select { padding:10px 14px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px;
                          color:#374151; transition:border-color .2s; }
        .filters input[type="text"]:focus,
        .filters select:focus { outline:none; border-color:#667eea; }
        .filter-btn { padding:10px 20px; background:#667eea; color:#fff; border:none; border-radius:10px;
                      font-weight:600; cursor:pointer; font-size:14px; }
        .filter-btn:hover { background:#5568d3; }
        .reset-btn  { padding:10px 16px; background:#f3f4f6; color:#6b7280; border:none; border-radius:10px;
                      font-weight:600; cursor:pointer; font-size:14px; text-decoration:none; }

        /* ── Table ── */
        .data-table th { font-size:12px; }
        .fee-bar-wrap { width:100px; background:#e5e7eb; border-radius:10px; height:8px; }
        .fee-bar      { height:8px; border-radius:10px; background:#10b981; }
        .fee-bar.mid  { background:#f59e0b; }
        .fee-bar.low  { background:#ef4444; }
        .pct-lbl      { font-size:11px; color:#6b7280; margin-top:3px; }
        .badge-paid    { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:700; }
        .badge-partial { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:700; }
        .badge-notpaid { background:#fee2e2; color:#b91c1c; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:700; }

        .btn        { padding:8px 16px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px; transition:all .2s; }
        .btn-pay    { background:#667eea; color:#fff; }
        .btn-pay:hover    { background:#5568d3; }
        .btn-hist   { background:#f3f4f6; color:#374151; }
        .btn-hist:hover   { background:#e5e7eb; }
        .btn-edit   { background:#fef3c7; color:#92400e; }
        .btn-edit:hover   { background:#fde68a; }
        .btn-success { background:#10b981; color:#fff; width:100%; padding:12px; font-size:15px; font-weight:700; border-radius:10px; cursor:pointer; margin-top:8px; }
        .btn-success:hover { background:#059669; }

        /* ── Modals ── */
        .modal          { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
        .modal.active   { display:flex; }
        .modal-box      { background:#fff; border-radius:18px; padding:30px; width:90%; max-width:520px; max-height:90vh; overflow-y:auto; }
        .modal-hdr      { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
        .modal-hdr h2   { font-size:20px; color:#2d3748; }
        .close-x        { font-size:26px; cursor:pointer; color:#9ca3af; line-height:1; }
        .close-x:hover  { color:#374151; }
        .form-group     { margin-bottom:16px; }
        .form-group label { display:block; margin-bottom:7px; font-weight:600; color:#374151; font-size:14px; }
        .form-group input,
        .form-group select,
        .form-group textarea { width:100%; padding:11px 13px; border:2px solid #e5e7eb; border-radius:10px;
                               font-size:14px; color:#374151; box-sizing:border-box; transition:border-color .2s; }
        .form-group input:focus,
        .form-group select:focus { outline:none; border-color:#667eea; }
        .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .student-info-box { background:#f0f4ff; border-radius:10px; padding:14px 16px; margin-bottom:18px; }
        .student-info-box .si-name { font-weight:700; color:#2d3748; font-size:17px; }
        .student-info-box .si-meta { font-size:13px; color:#6b7280; margin-top:4px; }
        .fee-info-row { display:flex; gap:16px; margin-top:10px; flex-wrap:wrap; }
        .fee-chip { padding:6px 12px; border-radius:8px; font-size:13px; font-weight:700; }
        .chip-total   { background:#e0e7ff; color:#4338ca; }
        .chip-paid    { background:#d1fae5; color:#065f46; }
        .chip-pending { background:#fee2e2; color:#b91c1c; }

        /* ── History panel ── */
        .hist-item { display:flex; align-items:flex-start; gap:12px; padding:12px; border-bottom:1px solid #f3f4f6; }
        .hist-item:last-child { border-bottom:none; }
        .hist-icon { width:36px; height:36px; border-radius:10px; background:#e0e7ff; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .hist-amount { font-weight:700; color:#10b981; font-size:16px; }
        .hist-meta   { font-size:12px; color:#9ca3af; margin-top:3px; }
        .hist-receipt { font-size:12px; color:#6b7280; }

        /* ── Alert ── */
        .alert         { padding:13px 18px; border-radius:10px; font-weight:600; margin-bottom:22px; font-size:14px; }
        .alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        @media print {
            .sidebar, .filters, .modal, .btn { display:none !important; }
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
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php"         class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php"     class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_manage_fees.php"           class="nav-item active"><span>💰</span> Fee Management</a>
            <a href="admin_send_notifications.php"    class="nav-item"><span>🔔</span> Send Notifications</a>
            <a href="admin_profile.php"               class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"             class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>💰 Fee Management</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                <span>|</span>
                <span><?php echo date('l, F d, Y'); ?></span>
            </div>
        </header>

        <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Summary Stats -->
        <div class="fee-stats">
            <div class="fee-stat">
                <div class="fee-stat-lbl">Total Students</div>
                <div class="fee-stat-val"><?php echo $stats['total']; ?></div>
            </div>
            <div class="fee-stat purple">
                <div class="fee-stat-lbl">Total Fee Due</div>
                <div class="fee-stat-val">₹<?php echo number_format($stats['sum_total']); ?></div>
            </div>
            <div class="fee-stat green">
                <div class="fee-stat-lbl">Total Collected</div>
                <div class="fee-stat-val">₹<?php echo number_format($stats['sum_paid']); ?></div>
            </div>
            <div class="fee-stat red">
                <div class="fee-stat-lbl">Total Pending</div>
                <div class="fee-stat-val">₹<?php echo number_format($stats['sum_pending']); ?></div>
            </div>
            <div class="fee-stat green">
                <div class="fee-stat-lbl">Fully Paid</div>
                <div class="fee-stat-val"><?php echo $stats['fully_paid']; ?></div>
                <div class="fee-stat-sub">students</div>
            </div>
            <div class="fee-stat yellow">
                <div class="fee-stat-lbl">Partial</div>
                <div class="fee-stat-val"><?php echo $stats['partial']; ?></div>
                <div class="fee-stat-sub">students</div>
            </div>
            <div class="fee-stat red">
                <div class="fee-stat-lbl">Not Paid</div>
                <div class="fee-stat-val"><?php echo $stats['not_paid']; ?></div>
                <div class="fee-stat-sub">students</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="🔍 Search student name / email…"
                   value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:200px;">
            <select name="dept">
                <option value="">All Departments</option>
                <?php while($d = mysqli_fetch_assoc($departments)): ?>
                    <option value="<?php echo $d['dept_id']; ?>" <?php echo $filter_dept==$d['dept_id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($d['dept_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="paid"    <?php echo $filter_status=='paid'   ?'selected':''; ?>>✅ Fully Paid</option>
                <option value="partial" <?php echo $filter_status=='partial'?'selected':''; ?>>⚠️ Partial</option>
                <option value="pending" <?php echo $filter_status=='pending'?'selected':''; ?>>❌ Not Paid</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="admin_manage_fees.php" class="reset-btn">Reset</a>
        </form>

        <!-- Students Table -->
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h2>Student Fee Records</h2>
                <span style="font-size:13px;color:#9ca3af;"><?php echo mysqli_num_rows($students); ?> student(s) found</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Dept / Class</th>
                            <th>Total Fee</th>
                            <th>Paid</th>
                            <th>Pending</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($students) == 0): ?>
                        <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:30px;">No students found.</td></tr>
                    <?php else: while ($s = mysqli_fetch_assoc($students)):
                        $pct = $s['pct'] ?? 0;
                        $bar_cls = $pct >= 100 ? '' : ($pct >= 50 ? 'mid' : 'low');
                        if ($s['fee_pending'] == 0) $status_badge = '<span class="badge-paid">✅ Paid</span>';
                        elseif ($s['fee_paid'] > 0)  $status_badge = '<span class="badge-partial">⚠️ Partial</span>';
                        else                          $status_badge = '<span class="badge-notpaid">❌ Unpaid</span>';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                                <span style="font-size:12px;color:#9ca3af;"><?php echo htmlspecialchars($s['email']); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($s['dept_name']); ?><br>
                                <span style="font-size:12px;color:#9ca3af;"><?php echo htmlspecialchars($s['class_name'] ?? '—'); ?></span>
                            </td>
                            <td><strong>₹<?php echo number_format($s['total_fee'], 2); ?></strong></td>
                            <td style="color:#10b981;font-weight:700;">₹<?php echo number_format($s['fee_paid'], 2); ?></td>
                            <td style="color:#ef4444;font-weight:700;">₹<?php echo number_format($s['fee_pending'], 2); ?></td>
                            <td>
                                <div class="fee-bar-wrap">
                                    <div class="fee-bar <?php echo $bar_cls; ?>" style="width:<?php echo min(100,$pct); ?>%;"></div>
                                </div>
                                <div class="pct-lbl"><?php echo $pct; ?>%</div>
                            </td>
                            <td><?php echo $status_badge; ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <button class="btn btn-pay"
                                            onclick='openPayModal(<?php echo $s['student_id']; ?>, <?php echo json_encode($s['name']); ?>, <?php echo $s['total_fee']; ?>, <?php echo $s['fee_paid']; ?>, <?php echo $s['fee_pending']; ?>, <?php echo json_encode($s['dept_name']); ?>)'>
                                        + Pay
                                    </button>
                                    <button class="btn btn-hist"
                                            onclick='loadHistory(<?php echo $s['student_id']; ?>, <?php echo json_encode($s['name']); ?>)'>
                                        📋 History
                                    </button>
                                    <button class="btn btn-edit"
                                            onclick='openEditFee(<?php echo $s['student_id']; ?>, <?php echo json_encode($s['name']); ?>, <?php echo $s['total_fee']; ?>)'>
                                        ✏️ Edit Fee
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ══ ADD PAYMENT MODAL ══════════════════════════════════════════════════ -->
<div id="payModal" class="modal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>💳 Record Payment</h2>
            <span class="close-x" onclick="closeModal('payModal')">×</span>
        </div>
        <div class="student-info-box">
            <div class="si-name" id="pm-name"></div>
            <div class="si-meta" id="pm-dept"></div>
            <div class="fee-info-row">
                <span class="fee-chip chip-total">Total: <span id="pm-total"></span></span>
                <span class="fee-chip chip-paid">Paid: <span id="pm-paid"></span></span>
                <span class="fee-chip chip-pending">Pending: <span id="pm-pending"></span></span>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="student_id" id="pm-sid">
            <div class="form-row-2">
                <div class="form-group">
                    <label>Amount Paid (₹) *</label>
                    <input type="number" name="amount_paid" id="pm-amount" step="0.01" min="1" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Payment Mode *</label>
                    <select name="payment_mode" required>
                        <option value="cash">💵 Cash</option>
                        <option value="online">📱 Online / UPI</option>
                        <option value="cheque">🏦 Cheque</option>
                        <option value="dd">📄 DD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Receipt / Ref No. *</label>
                    <input type="text" name="receipt_no" required placeholder="REC-2024-001">
                </div>
            </div>
            <div class="form-group">
                <label>Remarks (optional)</label>
                <input type="text" name="remarks" placeholder="e.g. 1st semester fee">
            </div>
            <button type="submit" name="add_payment" class="btn-success">✅ Record Payment</button>
        </form>
    </div>
</div>

<!-- ══ PAYMENT HISTORY MODAL ═════════════════════════════════════════════ -->
<div id="histModal" class="modal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>📋 Payment History</h2>
            <span class="close-x" onclick="closeModal('histModal')">×</span>
        </div>
        <div id="hist-student-name" style="font-weight:700;color:#2d3748;font-size:17px;margin-bottom:16px;"></div>
        <div id="hist-body" style="min-height:80px;"></div>
    </div>
</div>

<!-- ══ EDIT FEE MODAL ════════════════════════════════════════════════════ -->
<div id="editFeeModal" class="modal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>✏️ Edit Total Fee</h2>
            <span class="close-x" onclick="closeModal('editFeeModal')">×</span>
        </div>
        <div id="ef-name" style="font-weight:700;color:#2d3748;margin-bottom:18px;font-size:16px;"></div>
        <form method="POST">
            <input type="hidden" name="student_id" id="ef-sid">
            <div class="form-group">
                <label>New Total Fee (₹) *</label>
                <input type="number" name="total_fee" id="ef-fee" step="0.01" min="0" required>
            </div>
            <button type="submit" name="update_fee" class="btn-success">💾 Update Total Fee</button>
        </form>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.addEventListener('click', e => {
    ['payModal','histModal','editFeeModal'].forEach(id => {
        if (e.target === document.getElementById(id)) closeModal(id);
    });
});

function fmt(n) { return '₹' + parseFloat(n).toLocaleString('en-IN', {minimumFractionDigits:2}); }

function openPayModal(sid, name, total, paid, pending, dept) {
    document.getElementById('pm-sid').value    = sid;
    document.getElementById('pm-name').textContent    = name;
    document.getElementById('pm-dept').textContent    = dept;
    document.getElementById('pm-total').textContent   = fmt(total);
    document.getElementById('pm-paid').textContent    = fmt(paid);
    document.getElementById('pm-pending').textContent = fmt(pending);
    document.getElementById('pm-amount').max          = pending;
    document.getElementById('payModal').classList.add('active');
}

function openEditFee(sid, name, totalFee) {
    document.getElementById('ef-sid').value  = sid;
    document.getElementById('ef-name').textContent = 'Student: ' + name;
    document.getElementById('ef-fee').value  = totalFee;
    document.getElementById('editFeeModal').classList.add('active');
}

function loadHistory(sid, name) {
    document.getElementById('hist-student-name').textContent = '📋 ' + name;
    document.getElementById('hist-body').innerHTML = '<div style="text-align:center;padding:20px;color:#9ca3af;">Loading…</div>';
    document.getElementById('histModal').classList.add('active');

    fetch('admin_get_fee_history.php?student_id=' + sid)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                document.getElementById('hist-body').innerHTML = '<div style="text-align:center;padding:30px;color:#9ca3af;">No payment records found.</div>';
                return;
            }
            let html = '';
            data.forEach(p => {
                const modeEmoji = {cash:'💵', online:'📱', cheque:'🏦', dd:'📄'}[p.payment_mode] || '💳';
                html += `<div class="hist-item">
                    <div class="hist-icon">${modeEmoji}</div>
                    <div style="flex:1;">
                        <div class="hist-amount">+${fmt(p.amount_paid)}</div>
                        <div class="hist-receipt">Receipt: ${p.receipt_no}${p.remarks ? ' · ' + p.remarks : ''}</div>
                        <div class="hist-meta">${p.payment_date} · ${p.payment_mode.charAt(0).toUpperCase()+p.payment_mode.slice(1)}</div>
                    </div>
                </div>`;
            });
            document.getElementById('hist-body').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('hist-body').innerHTML = '<div style="text-align:center;padding:20px;color:#ef4444;">Failed to load history.</div>';
        });
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