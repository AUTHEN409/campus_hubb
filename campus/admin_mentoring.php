<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Create mentoring table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS mentoring (
    mentoring_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    faculty_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_mentor (student_id)
)";
mysqli_query($conn, $create_table);

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle Assign Mentor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_mentor'])) {
    $student_id = intval($_POST['student_id']);
    $faculty_id = intval($_POST['faculty_id']);
    
    // Check if student already has a mentor
    $check = mysqli_query($conn, "SELECT * FROM mentoring WHERE student_id = $student_id");
    
    if (mysqli_num_rows($check) > 0) {
        // Update existing mentor
        $query = "UPDATE mentoring SET faculty_id = $faculty_id, assigned_date = CURDATE() WHERE student_id = $student_id";
    } else {
        // Insert new mentor assignment
        $query = "INSERT INTO mentoring (student_id, faculty_id, assigned_date) VALUES ($student_id, $faculty_id, CURDATE())";
    }
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_mentoring.php?success=Mentor assigned successfully!");
        exit();
    } else {
        header("Location: admin_mentoring.php?error=Failed to assign mentor");
        exit();
    }
}

// Handle Bulk Assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_assign'])) {
    if (isset($_POST['selected_students']) && !empty($_POST['selected_students'])) {
        $student_ids = array_map('intval', $_POST['selected_students']);
        $faculty_id = intval($_POST['bulk_faculty_id']);
        
        $success_count = 0;
        foreach ($student_ids as $student_id) {
            // Check if exists
            $check = mysqli_query($conn, "SELECT * FROM mentoring WHERE student_id = $student_id");
            
            if (mysqli_num_rows($check) > 0) {
                $query = "UPDATE mentoring SET faculty_id = $faculty_id, assigned_date = CURDATE() WHERE student_id = $student_id";
            } else {
                $query = "INSERT INTO mentoring (student_id, faculty_id, assigned_date) VALUES ($student_id, $faculty_id, CURDATE())";
            }
            
            if (mysqli_query($conn, $query)) {
                $success_count++;
            }
        }
        
        header("Location: admin_mentoring.php?success=$success_count students assigned to mentor!");
        exit();
    }
}

// Handle Remove Mentor
if (isset($_GET['remove'])) {
    $mentoring_id = intval($_GET['remove']);
    if (mysqli_query($conn, "DELETE FROM mentoring WHERE mentoring_id = $mentoring_id")) {
        header("Location: admin_mentoring.php?success=Mentor removed successfully!");
        exit();
    }
}

// Get filter parameters
$filter_dept = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$where_clauses = [];
if ($filter_dept > 0) $where_clauses[] = "s.dept_id = $filter_dept";
if ($filter_class > 0) $where_clauses[] = "s.class_id = $filter_class";
if ($filter_status == 'assigned') $where_clauses[] = "m.mentoring_id IS NOT NULL";
if ($filter_status == 'unassigned') $where_clauses[] = "m.mentoring_id IS NULL";

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get students with their mentors
$students_query = "SELECT s.*, d.dept_name, c.class_name, 
                          m.mentoring_id, m.assigned_date,
                          f.name as mentor_name, f.faculty_id as mentor_id
                   FROM students s
                   JOIN departments d ON s.dept_id = d.dept_id
                   JOIN classes c ON s.class_id = c.class_id
                   LEFT JOIN mentoring m ON s.student_id = m.student_id
                   LEFT JOIN faculty f ON m.faculty_id = f.faculty_id
                   $where_sql
                   ORDER BY s.name";
$students = mysqli_query($conn, $students_query);

// Get all faculty
$faculty = mysqli_query($conn, "SELECT * FROM faculty ORDER BY name");

// Get departments and classes for filters
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");

// Get statistics
$total_students = mysqli_num_rows(mysqli_query($conn, "SELECT student_id FROM students"));
$assigned_students = mysqli_num_rows(mysqli_query($conn, "SELECT student_id FROM mentoring"));
$unassigned_students = $total_students - $assigned_students;

// Get mentor workload
$mentor_workload = mysqli_query($conn, "SELECT f.name, COUNT(m.student_id) as student_count
                                         FROM faculty f
                                         LEFT JOIN mentoring m ON f.faculty_id = m.faculty_id
                                         GROUP BY f.faculty_id
                                         ORDER BY student_count DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentoring System - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-value { font-size: 36px; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 14px; color: #718096; margin-top: 5px; }
        .filters { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #2d3748; font-size: 14px; }
        .filter-group select { width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; font-size: 14px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .data-table tr:hover { background: #f7fafc; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #feebc8; color: #7c2d12; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 500px; width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 28px; cursor: pointer; color: #6b7280; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; }
        .bulk-actions { display: none; background: #f7fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px; align-items: center; gap: 15px; }
        .bulk-actions.active { display: flex; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #c6f6d5; color: #22543d; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; border-left: 4px solid #f56565; }
        .workload-list { display: grid; gap: 10px; }
        .workload-item { display: flex; justify-content: space-between; padding: 12px; background: #f7fafc; border-radius: 8px; }
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
        <!-- Mobile hamburger (CSS-powered, no JS needed) -->
        <input type="checkbox" id="nav-toggle" hidden>
        <button class="menu-btn" onclick="document.getElementById('nav-toggle').click()">
            <span></span><span></span><span></span>
        </button>
        <label class="nav-overlay" for="nav-toggle"></label>

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
                <a href="admin_manage_students.php" class="nav-item"><span>👨‍🎓</span> Manage Students</a>
                <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
                <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
                <a href="admin_mentoring.php" class="nav-item active"><span>🤝</span> Mentoring</a>
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
                <button class="header-menu-btn" onclick="document.getElementById('nav-toggle').click()" aria-label="Toggle navigation">
                    <span></span><span></span><span></span>
                </button>
                <h1>Mentoring System</h1>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $assigned_students; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $unassigned_students; ?></div>
                    <div class="stat-label">Unassigned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round(($assigned_students / $total_students) * 100); ?>%</div>
                    <div class="stat-label">Coverage</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div>
                    <!-- Filters -->
                    <form method="GET" class="filters">
                        <div class="filter-group">
                            <label>Department</label>
                            <select name="dept_id" onchange="this.form.submit()">
                                <option value="0">All Departments</option>
                                <?php while($dept = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>" <?php echo $filter_dept == $dept['dept_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Class</label>
                            <select name="class_id" onchange="this.form.submit()">
                                <option value="0">All Classes</option>
                                <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo $filter_class == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Students</option>
                                <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="unassigned" <?php echo $filter_status == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </form>

                    <!-- Bulk Actions -->
                    <form method="POST" id="bulkForm">
                        <div class="bulk-actions" id="bulkActions">
                            <span id="selectedCount">0 selected</span>
                            <select name="bulk_faculty_id" required style="padding: 8px; border-radius: 6px; border: 2px solid #e2e8f0;">
                                <option value="">Select Mentor</option>
                                <?php mysqli_data_seek($faculty, 0); while($fac = mysqli_fetch_assoc($faculty)): ?>
                                    <option value="<?php echo $fac['faculty_id']; ?>"><?php echo htmlspecialchars($fac['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="bulk_assign" class="btn btn-primary btn-sm">Assign Mentor</button>
                            <button type="button" class="btn btn-sm" onclick="clearSelection()">Clear</button>
                        </div>

                        <!-- Students Table -->
                        <div class="card">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Class</th>
                                        <th>Current Mentor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($students) > 0): ?>
                                        <?php while ($student = mysqli_fetch_assoc($students)): ?>
                                            <tr>
                                                <td><input type="checkbox" name="selected_students[]" value="<?php echo $student['student_id']; ?>" class="student-checkbox" onchange="updateBulkActions()"></td>
                                                <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                                <td>
                                                    <?php if ($student['mentor_name']): ?>
                                                        <span class="badge badge-success">👨‍🏫 <?php echo htmlspecialchars($student['mentor_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button onclick="assignMentor(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')" class="btn btn-primary btn-sm">
                                                        <?php echo $student['mentor_name'] ? 'Change' : 'Assign'; ?>
                                                    </button>
                                                    <?php if ($student['mentoring_id']): ?>
                                                        <a href="?remove=<?php echo $student['mentoring_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove mentor?')">Remove</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 30px; color: #a0aec0;">
                                                No students found matching the filters
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>

                <!-- Mentor Workload Sidebar -->
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Mentor Workload</h3>
                    <div class="workload-list">
                        <?php while($mentor = mysqli_fetch_assoc($mentor_workload)): ?>
                            <div class="workload-item">
                                <span><?php echo htmlspecialchars($mentor['name']); ?></span>
                                <strong style="color: #667eea;"><?php echo $mentor['student_count']; ?> students</strong>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Assign Mentor Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Mentor</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="student_id" id="modal_student_id">
                <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>Student:</strong> <span id="modal_student_name"></span>
                </div>
                <div class="form-group">
                    <label>Select Mentor Faculty</label>
                    <select name="faculty_id" required>
                        <option value="">Choose Faculty</option>
                        <?php mysqli_data_seek($faculty, 0); while($fac = mysqli_fetch_assoc($faculty)): ?>
                            <option value="<?php echo $fac['faculty_id']; ?>"><?php echo htmlspecialchars($fac['name']); ?> (<?php echo htmlspecialchars($fac['employee_id']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="assign_mentor" class="btn btn-primary" style="width: 100%;">✓ Assign Mentor</button>
            </form>
        </div>
    </div>

    <script>
        function assignMentor(studentId, studentName) {
            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('modal_student_name').textContent = studentName;
            document.getElementById('assignModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('assignModal').classList.remove('active');
        }

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