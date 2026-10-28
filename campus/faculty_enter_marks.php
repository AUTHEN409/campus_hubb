<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

$success_message = "";
$error_message = "";

// Get classes taught by this faculty
$classes_query = "SELECT c.* 
                  FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id
                  ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $classes_query);

// Handle class selection
$selected_class = isset($_POST['class_id']) ? $_POST['class_id'] : (isset($_GET['class_id']) ? $_GET['class_id'] : '');
$test_date = isset($_POST['test_date']) ? $_POST['test_date'] : date('Y-m-d');
$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
$total_marks = isset($_POST['total_marks']) ? $_POST['total_marks'] : 100;

// Handle marks submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_marks'])) {
    $class_id = $_POST['class_id'];
    $test_date = $_POST['test_date'];
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $total_marks = $_POST['total_marks'];
    $marks_data = $_POST['marks'];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($marks_data as $student_id => $marks_obtained) {
        if ($marks_obtained !== '') {
            $insert_query = "INSERT INTO tests (student_id, class_id, subject, marks_obtained, total_marks, test_date, conducted_by) 
                            VALUES ($student_id, $class_id, '$subject', $marks_obtained, $total_marks, '$test_date', $faculty_id)";
            if (mysqli_query($conn, $insert_query)) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
    }
    
    if ($success_count > 0) {
        $success_message = "Marks entered successfully for $success_count student(s)!";
    }
    if ($error_count > 0) {
        $error_message = "Failed to enter marks for $error_count student(s).";
    }
}

// Get students for selected class
$students = [];
if ($selected_class) {
    $students_query = "SELECT s.* 
                       FROM students s 
                       WHERE s.class_id = $selected_class 
                       ORDER BY s.name";
    $students_result = mysqli_query($conn, $students_query);
    
    while ($student = mysqli_fetch_assoc($students_result)) {
        $students[] = $student;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Marks - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .load-btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .load-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .students-table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .students-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .students-table tr:hover {
            background: #f7fafc;
        }
        
        .marks-input {
            width: 100px;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }
        
        .marks-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        
        .quick-fill {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-fill-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .quick-fill-btn:hover {
            border-color: #667eea;
            background: #f7fafc;
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
                    <img src="college-logo.png" alt="College Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Faculty Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="faculty_dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="faculty_mark_attendance.php" class="nav-item">
                    <span>📅</span> Mark Attendance
                </a>
                <a href="faculty_enter_marks.php" class="nav-item active">
                    <span>📝</span> Enter Marks
                </a>
                <a href="faculty_upload_notes.php" class="nav-item">
                    <span>📚</span> Upload Notes
                </a>
                <a href="faculty_create_announcement.php" class="nav-item">
                    <span>📢</span> Announcements
                </a>
                <a href="faculty_add_remarks.php" class="nav-item">
                    <span>💬</span> Add Remarks
                </a>
                <a href="faculty_view_students.php" class="nav-item">
                    <span>👥</span> View Students
                </a>
                <a href="faculty_profile.php" class="nav-item">
                    <span>👤</span> Profile
                </a>
                <a href="logout.php" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1>Enter Test Marks</h1>
                <div class="user-info">
                    <span>Faculty Panel</span>
                </div>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Test Details Form -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Test Details</h2>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Class</label>
                            <select name="class_id" required>
                                <option value="">-- Choose a class --</option>
                                <?php 
                                mysqli_data_seek($classes_result, 0);
                                while ($class = mysqli_fetch_assoc($classes_result)): 
                                ?>
                                    <option value="<?php echo $class['class_id']; ?>" 
                                            <?php echo ($selected_class == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Test Date</label>
                            <input type="date" name="test_date" value="<?php echo $test_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="<?php echo htmlspecialchars($subject); ?>" placeholder="e.g., Mathematics, Physics" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Total Marks</label>
                            <input type="number" name="total_marks" value="<?php echo $total_marks; ?>" min="1" max="1000" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="load-btn">Load Students</button>
                </form>
            </div>

            <!-- Marks Entry -->
            <?php if (!empty($students)): ?>
                <div class="form-section">
                    <h2 style="margin-bottom: 20px;">Enter Marks for <?php echo htmlspecialchars($subject); ?> (<?php echo date('F d, Y', strtotime($test_date)); ?>)</h2>
                    
                    <div class="quick-fill">
                        <button type="button" class="quick-fill-btn" onclick="fillAll(<?php echo $total_marks; ?>)">Fill All: Full Marks</button>
                        <button type="button" class="quick-fill-btn" onclick="fillAll(<?php echo floor($total_marks * 0.75); ?>)">Fill All: 75%</button>
                        <button type="button" class="quick-fill-btn" onclick="fillAll(<?php echo floor($total_marks * 0.6); ?>)">Fill All: 60%</button>
                        <button type="button" class="quick-fill-btn" onclick="fillAll(0)">Fill All: 0</button>
                        <button type="button" class="quick-fill-btn" onclick="clearAll()">Clear All</button>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                        <input type="hidden" name="test_date" value="<?php echo $test_date; ?>">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
                        <input type="hidden" name="total_marks" value="<?php echo $total_marks; ?>">
                        
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Marks Obtained (out of <?php echo $total_marks; ?>)</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <input type="number" 
                                                   class="marks-input" 
                                                   name="marks[<?php echo $student['student_id']; ?>]" 
                                                   min="0" 
                                                   max="<?php echo $total_marks; ?>"
                                                   step="0.01"
                                                   placeholder="0"
                                                   onchange="calculatePercentage(this, <?php echo $total_marks; ?>)">
                                        </td>
                                        <td class="percentage-cell">-</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <button type="submit" name="submit_marks" class="submit-btn">
                            💾 Save All Marks
                        </button>
                    </form>
                </div>
            <?php elseif ($selected_class): ?>
                <div class="form-section">
                    <p class="empty-state">No students found in this class.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function fillAll(marks) {
            const inputs = document.querySelectorAll('.marks-input');
            const totalMarks = <?php echo $total_marks; ?>;
            inputs.forEach(input => {
                input.value = marks;
                calculatePercentage(input, totalMarks);
            });
        }
        
        function clearAll() {
            const inputs = document.querySelectorAll('.marks-input');
            inputs.forEach(input => {
                input.value = '';
                const row = input.closest('tr');
                row.querySelector('.percentage-cell').textContent = '-';
            });
        }
        
        function calculatePercentage(input, totalMarks) {
            const marksObtained = parseFloat(input.value) || 0;
            const percentage = ((marksObtained / totalMarks) * 100).toFixed(2);
            const row = input.closest('tr');
            const percentageCell = row.querySelector('.percentage-cell');
            
            if (input.value !== '') {
                percentageCell.textContent = percentage + '%';
                
                // Color code based on percentage
                if (percentage >= 75) {
                    percentageCell.style.color = '#48bb78';
                    percentageCell.style.fontWeight = '600';
                } else if (percentage >= 60) {
                    percentageCell.style.color = '#4299e1';
                    percentageCell.style.fontWeight = '600';
                } else if (percentage >= 40) {
                    percentageCell.style.color = '#ed8936';
                    percentageCell.style.fontWeight = '600';
                } else {
                    percentageCell.style.color = '#f56565';
                    percentageCell.style.fontWeight = '600';
                }
            } else {
                percentageCell.textContent = '-';
                percentageCell.style.color = '';
                percentageCell.style.fontWeight = '';
            }
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