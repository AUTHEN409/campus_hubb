<?php
session_start();
require_once 'database.php';
require_once 'security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$success = $error = '';

// ─── ADD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_faculty'])) {
    $name      = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email     = mysqli_real_escape_string($conn, trim($_POST['email']));
    $emp_id    = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
    $phone     = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $dept_id   = intval($_POST['dept_id']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $class_ids = isset($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];

    $q = "INSERT INTO faculty (name, email, password, employee_id, dept_id, phone)
          VALUES ('$name', '$email', '$password', '$emp_id', $dept_id, '$phone')";
    if (mysqli_query($conn, $q)) {
        $fid = mysqli_insert_id($conn);
        foreach ($class_ids as $cid) {
            if ($cid > 0)
                mysqli_query($conn, "INSERT IGNORE INTO faculty_classes (faculty_id, class_id) VALUES ($fid, $cid)");
        }
        $success = "Faculty '$name' added successfully!";
    } else {
        $error = "Failed to add faculty. Email or Employee ID may already exist.";
    }
}

// ─── EDIT ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_faculty'])) {
    $fid      = intval($_POST['faculty_id']);
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $emp_id   = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $dept_id  = intval($_POST['dept_id']);
    $class_ids = isset($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];

    $pass_sql = '';
    if (!empty($_POST['new_password'])) {
        $hp = mysqli_real_escape_string($conn, password_hash($_POST['new_password'], PASSWORD_DEFAULT));
        $pass_sql = ", password='$hp'";
    }
    mysqli_query($conn,
        "UPDATE faculty SET name='$name', email='$email', employee_id='$emp_id',
         dept_id=$dept_id, phone='$phone' $pass_sql WHERE faculty_id=$fid");

    mysqli_query($conn, "DELETE FROM faculty_classes WHERE faculty_id=$fid");
    foreach ($class_ids as $cid) {
        if ($cid > 0)
            mysqli_query($conn, "INSERT IGNORE INTO faculty_classes (faculty_id, class_id) VALUES ($fid, $cid)");
    }
    $success = "Faculty '$name' updated successfully!";
}

// ─── DELETE ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $fid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM faculty_classes WHERE faculty_id=$fid");
    mysqli_query($conn, "DELETE FROM library_notes WHERE uploaded_by=$fid");
    mysqli_query($conn, "DELETE FROM remarks WHERE faculty_id=$fid");
    if (mysqli_query($conn, "DELETE FROM faculty WHERE faculty_id=$fid"))
        $success = "Faculty deleted.";
    else
        $error = "Could not delete faculty.";
}

// ─── BULK DELETE ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['selected_faculty'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_faculty']));
        mysqli_query($conn, "DELETE FROM faculty_classes WHERE faculty_id IN ($ids)");
        mysqli_query($conn, "DELETE FROM library_notes WHERE uploaded_by IN ($ids)");
        mysqli_query($conn, "DELETE FROM remarks WHERE faculty_id IN ($ids)");
        mysqli_query($conn, "DELETE FROM faculty WHERE faculty_id IN ($ids)");
        $success = count($_POST['selected_faculty']) . " faculty member(s) deleted.";
    }
}

// ─── Fetch reference data ─────────────────────────────────────────────────────
$departments = [];
$dr = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
while ($d = mysqli_fetch_assoc($dr)) $departments[] = $d;

$all_classes = [];
$cr = mysqli_query($conn,
    "SELECT c.class_id, c.class_name, d.dept_name
     FROM classes c JOIN departments d ON c.dept_id=d.dept_id
     ORDER BY d.dept_name, c.class_name");
while ($c = mysqli_fetch_assoc($cr)) $all_classes[] = $c;

// ─── Fetch faculty list with their assigned classes ───────────────────────────
$faculty_list = [];
$fr = mysqli_query($conn,
    "SELECT f.*, d.dept_name FROM faculty f
     LEFT JOIN departments d ON f.dept_id=d.dept_id
     ORDER BY f.name");
while ($f = mysqli_fetch_assoc($fr)) {
    $fcr = mysqli_query($conn,
        "SELECT fc.class_id, c.class_name FROM faculty_classes fc
         JOIN classes c ON fc.class_id=c.class_id
         WHERE fc.faculty_id={$f['faculty_id']} ORDER BY c.class_name");
    $f['class_names'] = [];
    $f['class_ids']   = [];
    while ($fc = mysqli_fetch_assoc($fcr)) {
        $f['class_names'][] = $fc['class_name'];
        $f['class_ids'][]   = (int)$fc['class_id'];
    }
    $faculty_list[] = $f;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty – Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn        { padding:9px 18px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-block; font-size:14px; }
        .btn-primary { background:#667eea; color:#fff; }
        .btn-primary:hover { background:#5568d3; }
        .btn-warning { background:#f59e0b; color:#fff; }
        .btn-warning:hover { background:#d97706; }
        .btn-danger  { background:#ef4444; color:#fff; }
        .btn-danger:hover  { background:#dc2626; }
        .btn-sm     { padding:6px 12px; font-size:13px; }

        .modal      { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
        .modal.open { display:flex; }
        .modal-box  { background:#fff; padding:28px 24px; border-radius:18px; width:94%; max-width:600px; max-height:94vh; overflow-y:auto; }
        .modal-hdr  { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .modal-hdr h2 { font-size:19px; color:#2d3748; }
        .close-x    { font-size:26px; cursor:pointer; color:#9ca3af; background:none; border:none; line-height:1; padding:0; }
        .close-x:hover { color:#374151; }

        .fg       { margin-bottom:14px; }
        .fg label { display:block; margin-bottom:6px; font-weight:600; color:#374151; font-size:14px; }
        .fg input, .fg select { width:100%; padding:10px 12px; border:2px solid #e5e7eb; border-radius:9px; font-size:14px; color:#374151; box-sizing:border-box; transition:border-color .2s; }
        .fg input:focus, .fg select:focus { outline:none; border-color:#667eea; }
        .form-2col { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

        .cls-grid  { display:grid; grid-template-columns:1fr 1fr; gap:5px; max-height:210px; overflow-y:auto; padding:8px; border:2px solid #e5e7eb; border-radius:9px; }
        .cls-lbl   { display:flex; align-items:center; gap:8px; padding:7px 8px; border-radius:7px; cursor:pointer; transition:background .15s; font-size:13px; color:#374151; }
        .cls-lbl:hover { background:#f0f4ff; }
        .cls-lbl input { width:15px; height:15px; accent-color:#667eea; cursor:pointer; flex-shrink:0; }
        .dept-sep  { font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; padding:5px 8px 2px; grid-column:1/-1; border-top:1px solid #f3f4f6; margin-top:4px; }
        .dept-sep:first-child { border-top:none; margin-top:0; }

        .sel-bar   { display:flex; justify-content:space-between; align-items:center; background:#f0f4ff; border-radius:8px; padding:8px 12px; margin-bottom:8px; font-size:13px; }
        .sel-bar strong { color:#4338ca; }
        .sel-bar button { background:none; border:none; color:#667eea; font-size:12px; font-weight:700; cursor:pointer; padding:0 4px; }

        .submit-btn { width:100%; padding:13px; font-size:15px; font-weight:700; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; border-radius:10px; cursor:pointer; margin-top:6px; }
        .submit-btn:hover { opacity:.9; }

        .bulk-bar     { display:none; background:#f7fafc; padding:12px 16px; border-radius:10px; margin-bottom:16px; align-items:center; gap:14px; }
        .bulk-bar.on  { display:flex; }

        .cls-tag   { display:inline-block; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; margin:1px; }

        .alert         { padding:12px 16px; border-radius:10px; font-weight:600; margin-bottom:18px; font-size:14px; }
        .alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .tbl-wrap      { overflow-x:auto; }
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
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php"             class="nav-item"><span>📊</span> Dashboard</a>
            <a href="admin_manage_students.php"       class="nav-item"><span>👨‍🎓</span> Manage Students</a>
            <a href="admin_manage_faculty.php"        class="nav-item active"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="admin_manage_schedule.php"       class="nav-item"><span>📅</span> Manage Schedule</a>
             <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php"         class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php"     class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_manage_fees.php"           class="nav-item"><span>💰</span> Fee Management</a>
            <a href="admin_send_notifications.php"    class="nav-item"><span>🔔</span> Notifications</a>
            <a href="admin_profile.php"               class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php"             class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>👨‍🏫 Manage Faculty</h1>
            <button onclick="openAdd()" class="btn btn-primary">+ Add New Faculty</button>
        </header>

        <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" id="bulkForm">
            <div class="bulk-bar" id="bulkBar">
                <span id="bulkCnt">0 selected</span>
                <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete selected faculty?')">🗑 Delete Selected</button>
                <button type="button" class="btn btn-sm" onclick="clearSel()">Cancel</button>
            </div>
            <div class="card">
                <div class="tbl-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selAll" onchange="toggleAll(this)"></th>
                                <th>Name</th>
                                <th>Emp ID</th>
                                <th>Department</th>
                                <th>Email / Phone</th>
                                <th>Classes Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($faculty_list as $f): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_faculty[]"
                                           value="<?php echo $f['faculty_id']; ?>"
                                           class="fchk" onchange="updateBulk()"></td>
                                <td><strong><?php echo htmlspecialchars($f['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($f['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($f['dept_name'] ?? '—'); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($f['email']); ?></div>
                                    <div style="font-size:12px;color:#9ca3af;"><?php echo htmlspecialchars($f['phone']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($f['class_names'])): ?>
                                        <?php foreach ($f['class_names'] as $cn): ?>
                                            <span class="cls-tag"><?php echo htmlspecialchars($cn); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color:#9ca3af;font-size:13px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button type="button" class="btn btn-warning btn-sm"
                                            onclick='openEdit(<?php echo json_encode([
                                                "faculty_id"  => (int)$f['faculty_id'],
                                                "name"        => $f['name'],
                                                "email"       => $f['email'],
                                                "employee_id" => $f['employee_id'],
                                                "phone"       => $f['phone'],
                                                "dept_id"     => (int)$f['dept_id'],
                                                "class_ids"   => $f['class_ids'],
                                            ], JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                            ✏️ Edit
                                        </button>
                                        <a href="?delete=<?php echo $f['faculty_id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete <?php echo addslashes(htmlspecialchars($f['name'])); ?>?')">
                                            🗑
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($faculty_list)): ?>
                            <tr><td colspan="7" class="empty-state">No faculty added yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </main>
</div>

<!-- ══ ADD MODAL ══════════════════════════════════════════════════ -->
<div id="addModal" class="modal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>➕ Add New Faculty</h2>
            <button class="close-x" onclick="closeModal('addModal')" type="button">×</button>
        </div>
        <form method="POST" autocomplete="off">
            <div class="form-2col">
                <div class="fg"><label>Full Name *</label>
                    <input type="text" name="name" required placeholder="Dr. Ramesh Sharma"></div>
                <div class="fg"><label>Employee ID *</label>
                    <input type="text" name="employee_id" required placeholder="EMP-001"></div>
            </div>
            <div class="form-2col">
                <div class="fg"><label>Email *</label>
                    <input type="email" name="email" required></div>
                <div class="fg"><label>Phone *</label>
                    <input type="tel" name="phone" required maxlength="10" placeholder="9876543210"></div>
            </div>
            <div class="form-2col">
                <div class="fg"><label>Password *</label>
                    <input type="password" name="password" required minlength="6"></div>
                <div class="fg"><label>Department *</label>
                    <select name="dept_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="fg">
                <label>Assign Classes <small style="color:#9ca3af;font-weight:400;">(any number, any department)</small></label>
                <div class="sel-bar">
                    <strong id="addCnt">0 selected</strong>
                    <span>
                        <button type="button" onclick="toggleGrid('addGrid','addCnt',true)">Select All</button>
                        <button type="button" onclick="toggleGrid('addGrid','addCnt',false)">Clear</button>
                    </span>
                </div>
                <div class="cls-grid" id="addGrid">
                    <?php $cur=''; foreach ($all_classes as $cls):
                        if ($cls['dept_name'] !== $cur) { $cur=$cls['dept_name']; echo '<div class="dept-sep">'.htmlspecialchars($cur).'</div>'; } ?>
                        <label class="cls-lbl">
                            <input type="checkbox" name="class_ids[]"
                                   value="<?php echo $cls['class_id']; ?>"
                                   onchange="cntGrid('addGrid','addCnt')">
                            <?php echo htmlspecialchars($cls['class_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" name="add_faculty" class="submit-btn">✅ Add Faculty</button>
        </form>
    </div>
</div>

<!-- ══ EDIT MODAL ═════════════════════════════════════════════════ -->
<div id="editModal" class="modal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>✏️ Edit Faculty</h2>
            <button class="close-x" onclick="closeModal('editModal')" type="button">×</button>
        </div>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="faculty_id" id="ef_id">
            <div class="form-2col">
                <div class="fg"><label>Full Name *</label>
                    <input type="text" name="name" id="ef_name" required></div>
                <div class="fg"><label>Employee ID *</label>
                    <input type="text" name="employee_id" id="ef_empid" required></div>
            </div>
            <div class="form-2col">
                <div class="fg"><label>Email *</label>
                    <input type="email" name="email" id="ef_email" required></div>
                <div class="fg"><label>Phone *</label>
                    <input type="tel" name="phone" id="ef_phone" required></div>
            </div>
            <div class="form-2col">
                <div class="fg"><label>New Password <small style="color:#9ca3af;">(blank = keep)</small></label>
                    <input type="password" name="new_password" minlength="6" placeholder="Leave blank to keep"></div>
                <div class="fg"><label>Department *</label>
                    <select name="dept_id" id="ef_dept" required>
                        <option value="">— Select —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['dept_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="fg">
                <label>Assign Classes <small style="color:#9ca3af;font-weight:400;">(any number, any department)</small></label>
                <div class="sel-bar">
                    <strong id="editCnt">0 selected</strong>
                    <span>
                        <button type="button" onclick="toggleGrid('editGrid','editCnt',true)">Select All</button>
                        <button type="button" onclick="toggleGrid('editGrid','editCnt',false)">Clear</button>
                    </span>
                </div>
                <div class="cls-grid" id="editGrid">
                    <?php $cur=''; foreach ($all_classes as $cls):
                        if ($cls['dept_name'] !== $cur) { $cur=$cls['dept_name']; echo '<div class="dept-sep">'.htmlspecialchars($cur).'</div>'; } ?>
                        <label class="cls-lbl">
                            <input type="checkbox" name="class_ids[]"
                                   value="<?php echo $cls['class_id']; ?>"
                                   class="edit-cls"
                                   data-cid="<?php echo $cls['class_id']; ?>"
                                   onchange="cntGrid('editGrid','editCnt')">
                            <?php echo htmlspecialchars($cls['class_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" name="edit_faculty" class="submit-btn">💾 Save Changes</button>
        </form>
    </div>
</div>

<script>
function openAdd() { document.getElementById('addModal').classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
window.addEventListener('click', e => {
    if (e.target.id === 'addModal'  || e.target.id === 'editModal') closeModal(e.target.id);
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal('addModal'); closeModal('editModal'); }
});

function openEdit(d) {
    document.getElementById('ef_id').value    = d.faculty_id;
    document.getElementById('ef_name').value  = d.name;
    document.getElementById('ef_empid').value = d.employee_id;
    document.getElementById('ef_email').value = d.email;
    document.getElementById('ef_phone').value = d.phone;
    document.getElementById('ef_dept').value  = d.dept_id;

    document.querySelectorAll('.edit-cls').forEach(cb => {
        cb.checked = d.class_ids.includes(parseInt(cb.dataset.cid));
    });
    cntGrid('editGrid', 'editCnt');
    document.getElementById('editModal').classList.add('open');
}

function cntGrid(gid, cid) {
    const n = document.querySelectorAll('#' + gid + ' input[type=checkbox]:checked').length;
    document.getElementById(cid).textContent = n + ' selected';
}
function toggleGrid(gid, cid, state) {
    document.querySelectorAll('#' + gid + ' input[type=checkbox]').forEach(c => c.checked = state);
    cntGrid(gid, cid);
}

function toggleAll(cb) { document.querySelectorAll('.fchk').forEach(c => c.checked = cb.checked); updateBulk(); }
function updateBulk() {
    const n = document.querySelectorAll('.fchk:checked').length;
    document.getElementById('bulkBar').classList.toggle('on', n > 0);
    document.getElementById('bulkCnt').textContent = n + ' selected';
}
function clearSel() {
    document.querySelectorAll('.fchk').forEach(c => c.checked = false);
    document.getElementById('selAll').checked = false;
    updateBulk();
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