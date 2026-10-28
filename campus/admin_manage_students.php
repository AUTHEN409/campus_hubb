<?php
session_start();
require_once 'database.php';
require_once 'security.php'; // Campus Hub WAF

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
$registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;

// Function to generate email from student name
function generateEmailFromName($conn, $name) {
    // Convert name to lowercase and remove extra spaces
    $name = strtolower(trim($name));
    
    // Replace spaces with dots, remove special characters
    $email_base = preg_replace('/[^a-z0-9\s]/', '', $name);
    $email_base = preg_replace('/\s+/', '.', $email_base);
    
    // Check if email already exists
    $email = $email_base . '@campus.com';
    $check_query = "SELECT email FROM students WHERE email = '$email'";
    $result = mysqli_query($conn, $check_query);
    
    // If email exists, add number suffix
    if (mysqli_num_rows($result) > 0) {
        $counter = 2;
        while (true) {
            $email = $email_base . $counter . '@campus.com';
            $check_query = "SELECT email FROM students WHERE email = '$email'";
            $result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($result) == 0) {
                break;
            }
            $counter++;
        }
    }
    
    return $email;
}

// Function to generate simple password
function generatePassword($length = 6) {
    // Simple 6-character password with letters and numbers
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // Removed confusing characters
    return substr(str_shuffle($chars), 0, $length);
}

// Handle Add Student with Generated Credentials
$registration_details = null; // will be set here on success, no redirect needed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $dept_id = intval($_POST['dept_id']);
    $class_id = intval($_POST['class_id']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $total_fee = floatval($_POST['total_fee']);

    // Validate phone number (must be exactly 10 digits)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } else {
        // Generate email from student name
        $email = generateEmailFromName($conn, $name);

        // Extract first name and convert to lowercase for the initial password
        $first_name = strtolower(explode(' ', trim($name))[0]);
        $plain_password = $first_name;
        $password = password_hash($plain_password, PASSWORD_DEFAULT);

        // Set requires_password_change to 1 for new students
        $query = "INSERT INTO students (name, email, password, dept_id, class_id, phone, total_fee, fee_paid, fee_pending, enrollment_date, requires_password_change)
                  VALUES ('$name', '$email', '$password', $dept_id, $class_id, '$phone', $total_fee, 0, $total_fee, CURDATE(), 1)";

        if (mysqli_query($conn, $query)) {
            $new_student_id = mysqli_insert_id($conn);

            // Build slip details directly — no redirect, no session juggling
            $dept_row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dept_name FROM departments WHERE dept_id=$dept_id"));
            $class_row  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT class_name FROM classes WHERE class_id=$class_id"));
            $registration_details = [
                'id'         => $new_student_id,
                'name'       => stripslashes($name),
                'email'      => $email,
                'password'   => $plain_password,
                'dept_name'  => $dept_row['dept_name'] ?? '',
                'class_name' => $class_row['class_name'] ?? '',
            ];
        } else {
            $error = "Failed to add student. Please try again.";
        }
    }
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    
    mysqli_query($conn, "DELETE FROM attendance WHERE student_id = $student_id");
    mysqli_query($conn, "DELETE FROM tests WHERE student_id = $student_id");
    mysqli_query($conn, "DELETE FROM remarks WHERE student_id = $student_id");
    mysqli_query($conn, "DELETE FROM notifications WHERE student_id = $student_id");
    
    if (mysqli_query($conn, "DELETE FROM students WHERE student_id = $student_id")) {
        header("Location: " . $_SERVER["PHP_SELF"] . "?success=Student+deleted+successfully");
        exit();
    }
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (isset($_POST['selected_students']) && !empty($_POST['selected_students'])) {
        $ids = array_map('intval', $_POST['selected_students']);
        $ids_str = implode(',', $ids);
        
        mysqli_query($conn, "DELETE FROM attendance WHERE student_id IN ($ids_str)");
        mysqli_query($conn, "DELETE FROM tests WHERE student_id IN ($ids_str)");
        mysqli_query($conn, "DELETE FROM remarks WHERE student_id IN ($ids_str)");
        mysqli_query($conn, "DELETE FROM notifications WHERE student_id IN ($ids_str)");
        mysqli_query($conn, "DELETE FROM students WHERE student_id IN ($ids_str)");
        
        header("Location: " . $_SERVER["PHP_SELF"] . "?success=" . count($ids) . "+students+deleted+successfully");
        exit();
    }
}


// ─── PROMOTE STUDENT ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['promote_student'])) {
    $sid       = intval($_POST['student_id']);
    $action    = $_POST['promo_action']; // promote | graduate | status_change | class_change
    $note      = mysqli_real_escape_string($conn, trim($_POST['promo_note'] ?? ''));
    $admin_id  = $_SESSION['user_id'];

    $cur = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT study_year, student_status, class_id FROM students WHERE student_id=$sid"));
    if (!$cur) { $error = "Student not found."; goto SKIP_PROMO; }

    $from_year   = $cur['study_year'] ?? 1;
    $from_status = $cur['student_status'] ?? 'active';
    $from_class  = $cur['class_id'];
    $to_year     = $from_year;
    $to_class    = $from_class;
    $to_status   = $from_status;

    if ($action === 'promote') {
        $to_year = $from_year + 1;
        $new_class = intval($_POST['new_class_id'] ?? 0);
        if ($new_class) $to_class = $new_class;
        mysqli_query($conn, "UPDATE students SET study_year=$to_year, class_id=$to_class WHERE student_id=$sid");
        $success = "Student promoted to Year $to_year!";

    } elseif ($action === 'graduate') {
        $to_status = 'graduated';
        mysqli_query($conn, "UPDATE students SET student_status='graduated' WHERE student_id=$sid");
        $success = "Student marked as Graduated!";

    } elseif ($action === 'status_change') {
        $to_status = in_array($_POST['new_status'], ['active','graduated','dropped','suspended'])
                     ? $_POST['new_status'] : 'active';
        mysqli_query($conn, "UPDATE students SET student_status='$to_status' WHERE student_id=$sid");
        $success = "Student status changed to " . ucfirst($to_status) . "!";

    } elseif ($action === 'class_change') {
        $to_class = intval($_POST['new_class_id'] ?? 0);
        if ($to_class) {
            mysqli_query($conn, "UPDATE students SET class_id=$to_class WHERE student_id=$sid");
            $success = "Student class updated!";
        }
    }

    // Log promotion
    $log_action = mysqli_real_escape_string($conn, $action);
    $log_note   = mysqli_real_escape_string($conn, $note);
    @mysqli_query($conn,
        "INSERT INTO student_promotions (student_id, action, from_year, to_year, from_class_id, to_class_id, from_status, to_status, note, done_by)
         VALUES ($sid, '$log_action', $from_year, $to_year, $from_class, $to_class, '$from_status', '$to_status', '$log_note', $admin_id)");

    SKIP_PROMO:;
}

// ─── EDIT FEE INLINE ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_fee_inline'])) {
    $sid        = intval($_POST['student_id']);
    $new_total  = floatval($_POST['new_total_fee']);
    $new_paid   = floatval($_POST['new_fee_paid']);
    $new_pending = max(0, $new_total - $new_paid);
    mysqli_query($conn, "UPDATE students SET total_fee=$new_total, fee_paid=$new_paid, fee_pending=$new_pending WHERE student_id=$sid");
    $success = "Fee updated successfully!";
}

$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");

$students_query = "SELECT s.*, d.dept_name, c.class_name,
                          COALESCE(s.study_year, 1) as study_year,
                          COALESCE(s.student_status, 'active') as student_status
                   FROM students s 
                   JOIN departments d ON s.dept_id = d.dept_id 
                   JOIN classes c ON s.class_id = c.class_id 
                   ORDER BY s.student_id DESC";
$students = mysqli_query($conn, $students_query);

// registration_details is set directly in the POST handler above — no session redirect needed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #c6f6d5; color: #22543d; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; border-left: 4px solid #f56565; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #feebc8; color: #7c2d12; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .form-group input.valid { border-color: #10b981; }
        .form-group input.invalid { border-color: #ef4444; }
        .phone-hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .phone-hint.valid { color: #10b981; }
        .phone-hint.invalid { color: #ef4444; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 28px; cursor: pointer; color: #6b7280; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .bulk-actions { display: none; background: #f7fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px; align-items: center; gap: 15px; }
        .bulk-actions.active { display: flex; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #667eea; color: white; padding: 15px; text-align: left; font-weight: 600; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
        .data-table tr:hover { background: #f7fafc; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        /* Registration Slip Styles */
        .registration-slip { background: white; padding: 40px; border-radius: 16px; max-width: 600px; margin: 0 auto 30px; border: 3px solid #667eea; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .slip-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
        .slip-header h2 { color: #667eea; margin-bottom: 5px; font-size: 32px; }
        .slip-header p { color: #6b7280; font-size: 16px; }
        .slip-content { margin: 25px 0; }
        .slip-row { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #f3f4f6; }
        .slip-label { font-weight: 600; color: #374151; }
        .slip-value { color: #1f2937; font-weight: 500; }
        .credentials-box { background: #fef3c7; padding: 20px; border-radius: 12px; margin: 25px 0; border: 2px dashed #f59e0b; }
        .credentials-box h3 { color: #92400e; margin-bottom: 15px; text-align: center; }
        .credential-item { background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px; font-family: monospace; font-size: 16px; }
        .slip-footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        .print-btn { width: 100%; padding: 15px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .info-box { background: #dbeafe; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: #1e40af; }
        .example-format { background: #f3f4f6; padding: 10px; border-radius: 6px; margin-top: 10px; font-family: monospace; }
        
        @media print {
            body * { visibility: hidden; }
            .registration-slip, .registration-slip * { visibility: visible; }
            .registration-slip { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
            .print-btn, .close-slip-btn { display: none; }
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
        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="college-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Admin Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
                <a href="admin_manage_students.php" class="nav-item active"><span>👨‍🎓</span> Manage Students</a>
                <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
                <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
                 <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
                <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
                <a href="admin_manage_events.php" class="nav-item"><span>🎉</span> Manage Events</a>
                <a href="admin_manage_placements.php" class="nav-item active"><span>💼</span> Placements</a>
                <a href="admin_send_notifications.php" class="nav-item"><span>🔔</span> Send Notifications</a>
                <a href="admin_profile.php" class="nav-item"><span>👤</span> Profile</a>
                <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1>Manage Students</h1>
                <button onclick="openModal()" class="btn btn-primary">+ Register New Student</button>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Registration Slip Modal — auto-opens when a student is just registered -->
            <?php if ($registration_details): ?>
            <div id="slipModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
                <div class="registration-slip" style="margin:0;max-height:92vh;overflow-y:auto;">
                    <div class="slip-header">
                        <h2>🎓 Campus Hub</h2>
                        <p>Student Registration Slip</p>
                    </div>
                    <div class="slip-content">
                        <div class="slip-row">
                            <span class="slip-label">Student Name:</span>
                            <span class="slip-value"><?php echo htmlspecialchars($registration_details['name']); ?></span>
                        </div>
                        <div class="slip-row">
                            <span class="slip-label">Student ID:</span>
                            <span class="slip-value">#<?php echo $registration_details['id']; ?></span>
                        </div>
                        <div class="slip-row">
                            <span class="slip-label">Department:</span>
                            <span class="slip-value"><?php echo htmlspecialchars($registration_details['dept_name']); ?></span>
                        </div>
                        <div class="slip-row">
                            <span class="slip-label">Class:</span>
                            <span class="slip-value"><?php echo htmlspecialchars($registration_details['class_name']); ?></span>
                        </div>
                        <div class="slip-row">
                            <span class="slip-label">Registration Date:</span>
                            <span class="slip-value"><?php echo date('F d, Y'); ?></span>
                        </div>
                    </div>
                    <div class="credentials-box">
                        <h3>⚠️ Login Credentials — Keep Secure!</h3>
                        <div class="credential-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($registration_details['email']); ?>
                        </div>
                        <div class="credential-item">
                            <strong>Password:</strong> <?php echo htmlspecialchars($registration_details['password']); ?>
                        </div>
                    </div>
                    <div class="slip-footer">
                        <p><strong>Important:</strong> Share these credentials with the student and keep a copy.</p>
                    </div>
                    <button onclick="window.print()" class="print-btn">🖨️ Print Slip</button>
                    <button onclick="document.getElementById('slipModal').style.display='none'" class="btn btn-primary close-slip-btn" style="width:100%;margin-top:10px;">✓ Done — Close</button>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="bulkForm">
                <div class="bulk-actions" id="bulkActions">
                    <span id="selectedCount">0 selected</span>
                    <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected students?')">
                        Delete Selected
                    </button>
                    <button type="button" class="btn btn-sm" onclick="clearSelection()">Clear</button>
                </div>

                <div class="card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Class</th>
                                <th>Phone</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Fee Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = mysqli_fetch_assoc($students)): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_students[]" value="<?php echo $student['student_id']; ?>" class="student-checkbox" onchange="updateBulkActions()"></td>
                                    <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['dept_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                    <td style="text-align:center;">
                                        <span style="font-size:13px;font-weight:700;color:#667eea;">Year <?php echo intval($student['study_year']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $st = $student['student_status'] ?? 'active';
                                        $sbg = ['active'=>'#d1fae5','graduated'=>'#dbeafe','dropped'=>'#fee2e2','suspended'=>'#fef3c7'];
                                        $stc = ['active'=>'#065f46','graduated'=>'#1e40af','dropped'=>'#b91c1c','suspended'=>'#92400e'];
                                        $ico = ['active'=>'✅','graduated'=>'🎓','dropped'=>'❌','suspended'=>'⚠️'];
                                        ?>
                                        <span style="background:<?php echo $sbg[$st]??'#e5e7eb'; ?>;color:<?php echo $stc[$st]??'#374151'; ?>;padding:3px 9px;border-radius:8px;font-size:12px;font-weight:700;">
                                            <?php echo ($ico[$st]??'').' '.ucfirst($st); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($student['fee_pending'] > 0): ?>
                                            <span class="badge badge-warning">₹<?php echo number_format($student['fee_pending']); ?> Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                            <a href="student_profile_view.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-primary btn-sm">👁 Profile</a>
                                            <button type="button" class="btn btn-sm" style="background:#8b5cf6;color:#fff;"
                                                onclick='openPromo(<?php echo json_encode(["id"=>$student["student_id"],"name"=>$student["name"],"year"=>intval($student["study_year"]),"status"=>$student["student_status"],"class_id"=>$student["class_id"]]); ?>)'>
                                                📈 Promote
                                            </button>
                                            <button type="button" class="btn btn-sm" style="background:#f59e0b;color:#fff;"
                                                onclick='openFeeEdit(<?php echo json_encode(["id"=>$student["student_id"],"name"=>$student["name"],"total"=>floatval($student["total_fee"]),"paid"=>floatval($student["fee_paid"]),"pending"=>floatval($student["fee_pending"])]); ?>)'>
                                                💰 Fee
                                            </button>
                                            <a href="?delete=<?php echo $student['student_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this student?')">🗑</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Register New Student</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <div class="info-box">
                ℹ️ <strong>Auto-Generated Credentials:</strong>
                <div class="example-format">
                    <div>📧 Email Format: <strong>firstname.lastname@campus.com</strong></div>
                    <div>🔑 Password: <strong>6 random characters (easy to remember)</strong></div>
                    <div style="margin-top: 8px; font-size: 12px; color: #4b5563;">Example: "Rahul Kumar" → rahul.kumar@campus.com</div>
                </div>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Enter student's full name">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="dept_id" required>
                            <option value="">Select</option>
                            <?php mysqli_data_seek($departments, 0); while($dept = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?php echo $dept['dept_id']; ?>"><?php echo $dept['dept_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" required>
                            <option value="">Select</option>
                            <?php mysqli_data_seek($classes, 0); while($class = mysqli_fetch_assoc($classes)): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number (10 digits)</label>
                        <input type="tel" name="phone" id="phoneInput" required placeholder="9876543210" pattern="[0-9]{10}" maxlength="10" title="Please enter exactly 10 digits">
                        <div class="phone-hint" id="phoneHint">Enter 10 digit mobile number</div>
                    </div>
                    <div class="form-group">
                        <label>Total Fee</label>
                        <input type="number" name="total_fee" required value="75000">
                    </div>
                </div>
                <button type="submit" name="add_student" class="btn btn-success" style="width: 100%;">✓ Register Student</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('addModal').classList.add('active'); }
        function closeModal() { document.getElementById('addModal').classList.remove('active'); }
        
        function toggleAll(checkbox) {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checkbox.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checked = document.querySelectorAll('.student-checkbox:checked').length;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checked > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = checked + ' selected';
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }
        
        // Phone number validation - only allow numbers and limit to 10 digits
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phoneInput');
            const phoneHint = document.getElementById('phoneHint');
            
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Limit to 10 digits
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                    
                    // Update visual feedback
                    if (this.value.length === 10) {
                        this.classList.add('valid');
                        this.classList.remove('invalid');
                        phoneHint.textContent = '✓ Valid phone number';
                        phoneHint.classList.add('valid');
                        phoneHint.classList.remove('invalid');
                    } else if (this.value.length > 0) {
                        this.classList.add('invalid');
                        this.classList.remove('valid');
                        phoneHint.textContent = `Enter ${10 - this.value.length} more digit(s)`;
                        phoneHint.classList.add('invalid');
                        phoneHint.classList.remove('valid');
                    } else {
                        this.classList.remove('valid', 'invalid');
                        phoneHint.textContent = 'Enter 10 digit mobile number';
                        phoneHint.classList.remove('valid', 'invalid');
                    }
                });
            }
        });
    </script>

<!-- ══ PROMOTION MODAL ══════════════════════════════════════════ -->
<div id="promoModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:18px;padding:28px;width:94%;max-width:520px;max-height:92vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="font-size:19px;color:#2d3748;">📈 Manage Student Progress</h2>
        <button onclick="document.getElementById('promoModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">×</button>
    </div>
    <div id="promoStudentName" style="font-weight:700;color:#667eea;font-size:16px;margin-bottom:18px;padding:10px 14px;background:#f0f4ff;border-radius:10px;"></div>
    <form method="POST">
        <input type="hidden" name="student_id" id="promoSid">
        <!-- Action selector -->
        <div style="margin-bottom:16px;">
            <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:8px;">Action *</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="promoActions">
                <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .2s;" id="act-promote">
                    <input type="radio" name="promo_action" value="promote" onchange="showPromoSection('promote')" style="accent-color:#667eea;"> <span>📈 Promote to Next Year</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .2s;" id="act-graduate">
                    <input type="radio" name="promo_action" value="graduate" onchange="showPromoSection('graduate')"> <span>🎓 Mark as Graduated</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .2s;" id="act-status">
                    <input type="radio" name="promo_action" value="status_change" onchange="showPromoSection('status')"> <span>🔄 Change Status</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .2s;" id="act-class">
                    <input type="radio" name="promo_action" value="class_change" onchange="showPromoSection('class')"> <span>📚 Change Class</span>
                </label>
            </div>
        </div>
        <!-- Promote section -->
        <div id="sec-promote" style="display:none;margin-bottom:14px;">
            <div style="background:#f0f4ff;border-radius:10px;padding:12px;margin-bottom:10px;">
                <span id="promoYearInfo" style="font-size:14px;color:#4338ca;font-weight:600;"></span>
            </div>
            <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:7px;">Move to Class (optional)</label>
            <select name="new_class_id" id="promoClassSel" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
                <option value="">-- Keep current class --</option>
                <?php
                $cls_r = mysqli_query($conn, "SELECT c.class_id, c.class_name, d.dept_name FROM classes c JOIN departments d ON c.dept_id=d.dept_id ORDER BY d.dept_name, c.class_name");
                while($cr=mysqli_fetch_assoc($cls_r)): ?>
                    <option value="<?php echo $cr['class_id']; ?>"><?php echo htmlspecialchars($cr['dept_name'].' – '.$cr['class_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <!-- Graduate section -->
        <div id="sec-graduate" style="display:none;margin-bottom:14px;">
            <div style="background:#dbeafe;border-radius:10px;padding:14px;font-size:14px;color:#1e40af;font-weight:600;">
                🎓 This will mark the student as <strong>Graduated</strong>. They will no longer appear as active.
            </div>
        </div>
        <!-- Status section -->
        <div id="sec-status" style="display:none;margin-bottom:14px;">
            <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:7px;">New Status</label>
            <select name="new_status" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
                <option value="active">✅ Active</option>
                <option value="graduated">🎓 Graduated</option>
                <option value="dropped">❌ Dropped Out</option>
                <option value="suspended">⚠️ Suspended</option>
            </select>
        </div>
        <!-- Class change section -->
        <div id="sec-class" style="display:none;margin-bottom:14px;">
            <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:7px;">New Class *</label>
            <select name="new_class_id" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
                <option value="">-- Select Class --</option>
                <?php
                mysqli_data_seek($cls_r, 0);
                $cls_r2 = mysqli_query($conn, "SELECT c.class_id, c.class_name, d.dept_name FROM classes c JOIN departments d ON c.dept_id=d.dept_id ORDER BY d.dept_name, c.class_name");
                while($cr=mysqli_fetch_assoc($cls_r2)): ?>
                    <option value="<?php echo $cr['class_id']; ?>"><?php echo htmlspecialchars($cr['dept_name'].' – '.$cr['class_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <!-- Note -->
        <div style="margin-bottom:16px;">
            <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:7px;">Note (optional)</label>
            <input type="text" name="promo_note" placeholder="e.g. End of academic year 2024-25" style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
        </div>
        <button type="submit" name="promote_student" style="width:100%;padding:13px;font-size:15px;font-weight:700;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px;cursor:pointer;">✅ Confirm Action</button>
    </form>
</div>
</div>

<!-- ══ FEE EDIT MODAL ════════════════════════════════════════════ -->
<div id="feeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
<div style="background:#fff;border-radius:18px;padding:28px;width:94%;max-width:480px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="font-size:19px;color:#2d3748;">💰 Edit Fee</h2>
        <button onclick="document.getElementById('feeModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">×</button>
    </div>
    <div id="feeStudentName" style="font-weight:700;color:#f59e0b;font-size:16px;margin-bottom:18px;padding:10px 14px;background:#fffbeb;border-radius:10px;"></div>
    <form method="POST">
        <input type="hidden" name="student_id" id="feeSid">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:6px;">Total Fee (₹)</label>
                <input type="number" name="new_total_fee" id="feeTotal" step="0.01" min="0" required
                       style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;font-weight:600;color:#374151;font-size:14px;margin-bottom:6px;">Amount Paid (₹)</label>
                <input type="number" name="new_fee_paid" id="feePaid" step="0.01" min="0" required
                       onchange="updatePending()"
                       style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:14px;box-sizing:border-box;">
            </div>
        </div>
        <div style="background:#f9fafb;border-radius:10px;padding:14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:#6b7280;font-weight:600;">Calculated Pending:</span>
            <span id="feePendingDisplay" style="font-size:18px;font-weight:800;color:#ef4444;"></span>
        </div>
        <!-- Quick set buttons -->
        <div style="margin-bottom:16px;">
            <div style="font-size:13px;font-weight:600;color:#6b7280;margin-bottom:8px;">Quick actions:</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" onclick="setFeePaid()" style="padding:7px 14px;background:#d1fae5;color:#065f46;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">✅ Mark Fully Paid</button>
                <button type="button" onclick="setFeeUnpaid()" style="padding:7px 14px;background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">❌ Mark Unpaid</button>
            </div>
        </div>
        <button type="submit" name="edit_fee_inline" style="width:100%;padding:13px;font-size:15px;font-weight:700;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;border-radius:10px;cursor:pointer;">💾 Save Fee Changes</button>
    </form>
</div>
</div>

<script>
// Promotion modal
function openPromo(d) {
    document.getElementById('promoSid').value = d.id;
    document.getElementById('promoStudentName').textContent = '👤 ' + d.name + '  (Year ' + d.year + ' · ' + d.status + ')';
    document.querySelectorAll('[id^="sec-"]').forEach(s => s.style.display='none');
    document.querySelectorAll('input[name="promo_action"]').forEach(r => r.checked=false);
    document.getElementById('promoModal').style.display='flex';
}
function showPromoSection(name) {
    document.querySelectorAll('[id^="sec-"]').forEach(s => s.style.display='none');
    const el = document.getElementById('sec-'+name);
    if (el) el.style.display='block';
    // Update year info when promoting
    if (name==='promote') {
        const m = document.getElementById('promoStudentName').textContent;
        const y = parseInt((m.match(/Year (\d+)/)||[0,1])[1]);
        document.getElementById('promoYearInfo').textContent = 'Year '+y+' → Year '+(y+1);
    }
}
window.addEventListener('click', e => {
    if (e.target===document.getElementById('promoModal')) document.getElementById('promoModal').style.display='none';
    if (e.target===document.getElementById('feeModal'))  document.getElementById('feeModal').style.display='none';
});

// Fee modal
var _feeTotal = 0;
function openFeeEdit(d) {
    _feeTotal = d.total;
    document.getElementById('feeSid').value = d.id;
    document.getElementById('feeStudentName').textContent = '👤 ' + d.name;
    document.getElementById('feeTotal').value  = d.total.toFixed(2);
    document.getElementById('feePaid').value   = d.paid.toFixed(2);
    updatePending();
    document.getElementById('feeModal').style.display='flex';
}
function updatePending() {
    const total   = parseFloat(document.getElementById('feeTotal').value)||0;
    const paid    = parseFloat(document.getElementById('feePaid').value)||0;
    const pending = Math.max(0, total-paid);
    document.getElementById('feePendingDisplay').textContent = '₹' + pending.toLocaleString('en-IN',{minimumFractionDigits:2});
}
function setFeePaid() {
    const total = parseFloat(document.getElementById('feeTotal').value)||0;
    document.getElementById('feePaid').value = total.toFixed(2);
    updatePending();
}
function setFeeUnpaid() {
    document.getElementById('feePaid').value = '0.00';
    updatePending();
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