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

// Check if attendance can be marked based on time
$can_mark_attendance = true;
$lock_message = "";

if (!empty($selected_date)) {
    if ($selected_date == date('Y-m-d')) {
        // Today's attendance - check if it's before 12 PM
        $current_time = date('H:i');
        if ($current_time >= '12:00') {
            $can_mark_attendance = false;
            $lock_message = "Attendance for today cannot be marked or modified after 12:00 PM (Noon).";
        }
    } elseif ($selected_date > date('Y-m-d')) {
        // Future date - not allowed
        $can_mark_attendance = false;
        $lock_message = "You cannot mark attendance for future dates.";
    } else {
        // Past date - check if it's within last 24 hours from 12 PM
        $attendance_date_12pm = strtotime($selected_date . ' 12:00:00');
        $current_timestamp = time();
        
        if ($current_timestamp > $attendance_date_12pm) {
            $can_mark_attendance = false;
            $lock_message = "Attendance for " . date('F d, Y', strtotime($selected_date)) . " is locked. It cannot be modified after 12:00 PM (Noon) on that day.";
        }
    }
}

// Get classes taught by this faculty
$classes_query = "SELECT c.* 
                  FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id
                  ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $classes_query);

// Handle class selection
$selected_class = isset($_POST['class_id']) ? $_POST['class_id'] : (isset($_GET['class_id']) ? $_GET['class_id'] : '');
$selected_date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : (isset($_GET['attendance_date']) ? $_GET['attendance_date'] : date('Y-m-d'));

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_attendance'])) {
    if (!$can_mark_attendance) {
        $error_message = $lock_message;
    } else {
        $class_id = $_POST['class_id'];
        $date = $_POST['attendance_date'];
        $attendance_data = $_POST['attendance'];
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($attendance_data as $student_id => $status) {
            // Check if attendance already exists
            $check_query = "SELECT * FROM attendance 
                            WHERE student_id = $student_id 
                            AND date = '$date'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing attendance
                $update_query = "UPDATE attendance 
                                SET status = '$status', marked_by = $faculty_id 
                                WHERE student_id = $student_id AND date = '$date'";
                if (mysqli_query($conn, $update_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new attendance
                $insert_query = "INSERT INTO attendance (student_id, date, status, marked_by) 
                                VALUES ($student_id, '$date', '$status', $faculty_id)";
                if (mysqli_query($conn, $insert_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $success_message = "Attendance marked successfully for $success_count student(s)!";
        }
        if ($error_count > 0) {
            $error_message = "Failed to mark attendance for $error_count student(s).";
        }
    }
}

// Get students for selected class
$students = [];
$calendar_data = [];

if ($selected_class) {
    // Get calendar data for current month
    $current_month = date('Y-m');
    $calendar_query = "SELECT DATE(a.date) as date, 
                              COUNT(*) as total_students,
                              SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
                       FROM attendance a
                       JOIN students s ON a.student_id = s.student_id
                       WHERE s.class_id = $selected_class
                       AND DATE_FORMAT(a.date, '%Y-%m') = '$current_month'
                       GROUP BY DATE(a.date)";
    $calendar_result = mysqli_query($conn, $calendar_query);
    while ($row = mysqli_fetch_assoc($calendar_result)) {
        $calendar_data[$row['date']] = $row;
    }
    
    // Get students
    $students_query = "SELECT s.* 
                       FROM students s 
                       WHERE s.class_id = $selected_class 
                       ORDER BY s.name";
    $students_result = mysqli_query($conn, $students_query);
    
    while ($student = mysqli_fetch_assoc($students_result)) {
        // Check if attendance already marked for this date
        $att_check = "SELECT status FROM attendance 
                      WHERE student_id = {$student['student_id']} 
                      AND date = '$selected_date'";
        $att_result = mysqli_query($conn, $att_check);
        $existing_status = mysqli_num_rows($att_result) > 0 ? mysqli_fetch_assoc($att_result)['status'] : '';
        
        $student['existing_status'] = $existing_status;
        $students[] = $student;
    }

    // Get per-date student breakdown for history modal
    $history_query = "SELECT DATE(a.date) as att_date, s.name, a.status
                      FROM attendance a
                      JOIN students s ON a.student_id = s.student_id
                      WHERE s.class_id = $selected_class
                      AND DATE_FORMAT(a.date, '%Y-%m') = '$current_month'
                      ORDER BY s.name";
    $history_result  = mysqli_query($conn, $history_query);
    $history_by_date = [];
    while ($h = mysqli_fetch_assoc($history_result)) {
        $history_by_date[$h['att_date']][] = ['name' => $h['name'], 'status' => $h['status']];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
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
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group select:focus,
        .form-group input:focus {
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
        
        .attendance-radio {
            display: flex;
            gap: 20px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .radio-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
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
        
        .submit-btn:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
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
        
        .mark-all-btns {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .mark-all-btn {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .mark-all-btn:hover {
            border-color: #667eea;
            background: #f7fafc;
        }
        
        .calendar-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .calendar-header {
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        
        .calendar-day-name {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            padding: 10px;
            font-size: 14px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f7fafc;
            color: #2d3748;
            border: 2px solid transparent;
            padding: 5px;
        }
        
        .calendar-day:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }
        
        .calendar-day.has-attendance {
            background: #c6f6d5;
            border-color: #48bb78;
        }
        
        .calendar-day.selected {
            background: #667eea;
            color: white;
            font-weight: 700;
        }
        
        .calendar-day.today {
            border: 2px solid #ed8936;
        }
        
        .calendar-day.empty {
            background: transparent;
            cursor: default;
        }
        
        .calendar-day.empty:hover {
            transform: none;
            border-color: transparent;
        }
        
        .day-number {
            font-size: 16px;
            font-weight: 600;
        }
        
        .day-info {
            font-size: 10px;
            margin-top: 2px;
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
        <!-- Mobile hamburger (CSS-powered, no JS needed) -->
        <input type="checkbox" id="nav-toggle" hidden>
        <button class="menu-btn" onclick="document.getElementById('nav-toggle').click()">
            <span></span><span></span><span></span>
        </button>
        <label class="nav-overlay" for="nav-toggle"></label>

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
                <a href="faculty_mark_attendance.php" class="nav-item active">
                    <span>📅</span> Mark Attendance
                </a>
                <a href="faculty_enter_marks.php" class="nav-item">
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
                <button class="header-menu-btn" onclick="document.getElementById('nav-toggle').click()" aria-label="Toggle navigation">
                    <span></span><span></span><span></span>
                </button>
                <h1>Mark Attendance</h1>
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
            
            <?php if ($lock_message && $selected_class): ?>
                <div class="alert alert-error">
                    🔒 <?php echo $lock_message; ?>
                </div>
            <?php endif; ?>

            <!-- Selection Form -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Select Class and Date</h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Select Class</label>
                        <select name="class_id" id="classSelect" required onchange="this.form.submit()">
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
                        <label>Select Date</label>
                        <input type="date" name="attendance_date" value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <button type="submit" class="load-btn">Load Students</button>
                </form>
            </div>

            <!-- Calendar View -->
            <?php if ($selected_class): ?>
                <div class="calendar-section">
                    <div class="calendar-header">
                        <?php echo date('F Y'); ?> - Attendance Overview
                    </div>
                    
                    <div class="calendar">
                        <!-- Day names -->
                        <div class="calendar-day-name">Sun</div>
                        <div class="calendar-day-name">Mon</div>
                        <div class="calendar-day-name">Tue</div>
                        <div class="calendar-day-name">Wed</div>
                        <div class="calendar-day-name">Thu</div>
                        <div class="calendar-day-name">Fri</div>
                        <div class="calendar-day-name">Sat</div>
                        
                        <?php
                        // Get first day of month and total days
                        $first_day = date('Y-m-01');
                        $first_day_of_week = date('w', strtotime($first_day));
                        $days_in_month = date('t');
                        $today = date('Y-m-d');
                        
                        // Empty cells before month starts
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }
                        
                        // Days of the month
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = date('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $classes = 'calendar-day';
                            
                            if ($date == $today) $classes .= ' today';
                            if ($date == $selected_date) $classes .= ' selected';
                            if (isset($calendar_data[$date])) $classes .= ' has-attendance';
                            
                            $info = '';
                            if (isset($calendar_data[$date])) {
                                $percentage = round(($calendar_data[$date]['present_count'] / $calendar_data[$date]['total_students']) * 100);
                                $info = $percentage . '%';
                            }
                            
                            echo '<div class="' . $classes . '" onclick="selectDate(\'' . $date . '\')">';
                            echo '<div class="day-number">' . $day . '</div>';
                            if ($info) echo '<div class="day-info">' . $info . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div style="margin-top: 15px; text-align: center; font-size: 12px; color: #718096;">
                        <span style="display: inline-block; margin: 0 10px;">🟢 Green = Attendance Marked</span>
                        <span style="display: inline-block; margin: 0 10px;">🔵 Blue = Selected Date</span>
                        <span style="display: inline-block; margin: 0 10px;">🟠 Orange Border = Today</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attendance Marking -->
            <?php if (!empty($students)): ?>
                <div class="form-section">
                    <h2 style="margin-bottom: 20px;">Mark Attendance for <?php echo date('F d, Y', strtotime($selected_date)); ?></h2>
                    
                    <?php if ($can_mark_attendance): ?>
                        <div class="mark-all-btns">
                            <button type="button" class="mark-all-btn" onclick="markAll('Present')">✅ Mark All Present</button>
                            <button type="button" class="mark-all-btn" onclick="markAll('Absent')">❌ Mark All Absent</button>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                            
                            <table class="students-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $count = 1; foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <div class="attendance-radio">
                                                    <label class="radio-option">
                                                        <input type="radio" 
                                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                                               value="Present" 
                                                               class="attendance-input"
                                                               <?php echo ($student['existing_status'] == 'Present') ? 'checked' : ''; ?>
                                                               required>
                                                        <span style="color: #48bb78; font-weight: 600;">Present</span>
                                                    </label>
                                                    <label class="radio-option">
                                                        <input type="radio" 
                                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                                               value="Absent"
                                                               class="attendance-input"
                                                               <?php echo ($student['existing_status'] == 'Absent') ? 'checked' : ''; ?>
                                                               required>
                                                        <span style="color: #f56565; font-weight: 600;">Absent</span>
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <button type="submit" name="submit_attendance" class="submit-btn">
                                💾 Save Attendance
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Show read-only view when locked -->
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <?php if ($student['existing_status']): ?>
                                                <span style="font-weight: 600; color: <?php echo $student['existing_status'] == 'Present' ? '#48bb78' : '#f56565'; ?>">
                                                    <?php echo $student['existing_status'] == 'Present' ? '✅ Present' : '❌ Absent'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #a0aec0;">Not Marked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php elseif ($selected_class): ?>
                <div class="form-section">
                    <p class="empty-state">No students found in this class.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Class History Modal -->
    <div class="att-modal-overlay" id="histModalOverlay" onclick="closeHistory()"></div>
    <div class="att-modal" id="histModal">
        <div class="att-modal-header">
            <span id="histModalTitle" class="att-modal-title"></span>
            <button class="att-modal-close" onclick="closeHistory()">✕</button>
        </div>
        <div id="histModalStats" class="hist-stats-row"></div>
        <div id="histModalBody" class="att-modal-body hist-scroll"></div>
    </div>

    <style>
        .att-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1500;
        }
        .att-modal {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            padding: 20px 22px 32px;
            z-index: 1600;
            box-shadow: 0 -6px 30px rgba(0,0,0,0.15);
            transform: translateY(100%);
            transition: transform 0.32s cubic-bezier(0.34,1.56,0.64,1);
            max-width: 520px;
            margin: 0 auto;
        }
        .att-modal.open { display: block; transform: translateY(0); }
        .att-modal-overlay.open { display: block; }
        .att-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .att-modal-title { font-size: 16px; font-weight: 700; color: #2d3748; }
        .att-modal-close {
            background: #f7fafc; border: none; border-radius: 50%;
            width: 32px; height: 32px; font-size: 15px; cursor: pointer;
            color: #4a5568; display: flex; align-items: center; justify-content: center;
        }
        .att-modal-body { font-size: 14px; }
        .att-drag-bar { width: 40px; height: 4px; background: #e2e8f0; border-radius: 4px; margin: 0 auto 16px; }
        .hist-stats-row {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }
        .hist-stat-pill {
            flex: 1;
            text-align: center;
            padding: 10px 8px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        .hist-stat-pill.present { background: #c6f6d5; color: #22543d; }
        .hist-stat-pill.absent  { background: #fed7d7; color: #742a2a; }
        .hist-stat-pill.total   { background: #ebf8ff; color: #2b6cb0; }
        .hist-stat-pill span { display: block; font-size: 22px; font-weight: 800; margin-bottom: 2px; }
        .hist-scroll { max-height: 48vh; overflow-y: auto; }
        .hist-student-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f4f8;
            font-size: 14px;
        }
        .hist-student-row:last-child { border-bottom: none; }
        .hist-student-name { color: #2d3748; font-weight: 500; }
        .hist-badge {
            font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px;
        }
        .hist-badge.present { background: #c6f6d5; color: #22543d; }
        .hist-badge.absent  { background: #fed7d7; color: #742a2a; }
        .att-no-record { text-align: center; padding: 24px 0; color: #a0aec0; font-style: italic; }

        @media (min-width: 520px) {
            .att-modal {
                bottom: auto; top: 50%; left: 50%;
                transform: translate(-50%, -60%);
                border-radius: 16px;
                width: 420px;
                max-height: 80vh;
                transition: none;
            }
            .att-modal.open { transform: translate(-50%, -50%); }
        }
    </style>

    <script>
        function markAll(status) {
            const radios = document.querySelectorAll(`input[value="${status}"]`);
            radios.forEach(radio => { radio.checked = true; });
        }

        const historyData = <?php echo isset($history_by_date) ? json_encode($history_by_date) : '{}'; ?>;

        function selectDate(date) {
            // If this date has existing attendance, show history modal
            if (historyData[date] && historyData[date].length > 0) {
                showHistory(date);
            } else {
                // No attendance yet: fill the date picker and scroll to form
                const dateInput = document.querySelector('input[name="attendance_date"]');
                if (dateInput) { dateInput.value = date; }
                document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
            }
        }

        function showHistory(date) {
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            const d = new Date(date + 'T00:00:00');
            const label = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();

            document.getElementById('histModalTitle').textContent = '📅 ' + label + ' — Class Attendance';

            const students = historyData[date] || [];
            const presentCount = students.filter(s => s.status === 'Present').length;
            const absentCount  = students.filter(s => s.status === 'Absent').length;
            const pct = students.length ? Math.round((presentCount / students.length) * 100) : 0;

            document.getElementById('histModalStats').innerHTML = `
                <div class="hist-stat-pill present"><span>${presentCount}</span>Present</div>
                <div class="hist-stat-pill absent"><span>${absentCount}</span>Absent</div>
                <div class="hist-stat-pill total"><span>${pct}%</span>Attendance</div>`;

            let rows = '<div class="att-drag-bar"></div>';
            if (students.length === 0) {
                rows += '<div class="att-no-record">📭 No records found for this date.</div>';
            } else {
                students.forEach(s => {
                    const cls = s.status === 'Present' ? 'present' : 'absent';
                    const icon = s.status === 'Present' ? '✅' : '❌';
                    rows += `<div class="hist-student-row">
                        <span class="hist-student-name">${s.name}</span>
                        <span class="hist-badge ${cls}">${icon} ${s.status}</span>
                    </div>`;
                });
            }

            document.getElementById('histModalBody').innerHTML = rows;
            document.getElementById('histModal').classList.add('open');
            document.getElementById('histModalOverlay').classList.add('open');
        }

        function closeHistory() {
            document.getElementById('histModal').classList.remove('open');
            document.getElementById('histModalOverlay').classList.remove('open');
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeHistory(); });
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