<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Get student details
$query = "SELECT s.*, d.dept_name, c.class_name 
          FROM students s 
          JOIN departments d ON s.dept_id = d.dept_id 
          JOIN classes c ON s.class_id = c.class_id 
          WHERE s.student_id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// Calculate attendance percentage
$att_query = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
              FROM attendance 
              WHERE student_id = $student_id 
              AND date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)";
$att_result = mysqli_query($conn, $att_query);
$attendance = mysqli_fetch_assoc($att_result);
$attendance_percentage = $attendance['total_days'] > 0 
    ? round(($attendance['present_days'] / $attendance['total_days']) * 100, 2) 
    : 0;

// Get recent notifications
$notif_query = "SELECT * FROM notifications 
                WHERE student_id = $student_id 
                ORDER BY created_at DESC 
                LIMIT 5";
$notifications = mysqli_query($conn, $notif_query);

// Get upcoming events
$events_query = "SELECT * FROM events 
                 WHERE event_date >= CURDATE() 
                 ORDER BY event_date ASC 
                 LIMIT 3";
$events = mysqli_query($conn, $events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
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
    <span class="mobile-user-chip"><?php echo htmlspecialchars($student_name); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="mainSidebar">
            <div class="logo">
                <h2>Campus Hub</h2>
            </div>
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="nav-item active">
                    <span>📊</span> Dashboard
                </a>
                <a href="student_attendance.php" class="nav-item">
                    <span>📅</span> Attendance
                </a>
                <a href="student_marks.php" class="nav-item">
                    <span>📝</span> Marks
                </a>
                <a href="student_schedule.php" class="nav-item">
                    <span>🕐</span> Schedule
                </a>
                <a href="student_library.php" class="nav-item">
                    <span>📚</span> Library
                </a>
                <a href="student_events.php" class="nav-item">
                    <span>🎉</span> Events
                </a>
                <a href="student_placements.php" class="nav-item active">
                    <span>💼</span> Placements
                </a>
                <a href="student_performance.php" class="nav-item">
                    <span>📈</span> Performance
                </a>
                <a href="student_profile.php" class="nav-item">
                    <span>👤</span> Profile
                </a>
               
                <a href="../logout.php" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <h1>Welcome back, <?php echo htmlspecialchars($student_name); ?>!</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <h3>Attendance</h3>
                        <p class="stat-value"><?php echo $attendance_percentage; ?>%</p>
                        <span class="stat-label">Last 28 days</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <h3>Fee Status</h3>
                        <p class="stat-value">₹<?php echo number_format($student['fee_pending'], 2); ?></p>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3>Department</h3>
                        <p class="stat-value"><?php echo htmlspecialchars($student['dept_name']); ?></p>
                        <span class="stat-label"><?php echo htmlspecialchars($student['class_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Notifications -->
                <div class="card">
                    <h2>Recent Notifications</h2>
                    <div class="notifications-list">
                        <?php if (mysqli_num_rows($notifications) > 0): ?>
                            <?php while ($notif = mysqli_fetch_assoc($notifications)): ?>
                                <div class="notification-item">
                                    <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="time"><?php echo date('M d, Y', strtotime($notif['created_date'])); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="empty-state">No notifications yet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="card">
                    <h2>Upcoming Events</h2>
                    <div class="events-list">
                        <?php if (mysqli_num_rows($events) > 0): ?>
                            <?php while ($event = mysqli_fetch_assoc($events)): ?>
                                <div class="event-item">
                                    <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($event['description']); ?></p>
                                    <span class="event-date">📅 <?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="empty-state">No upcoming events</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

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