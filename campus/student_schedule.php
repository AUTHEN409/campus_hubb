<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Get student details
$query = "SELECT s.*, d.dept_name, c.class_name, c.class_id 
          FROM students s 
          JOIN departments d ON s.dept_id = d.dept_id 
          JOIN classes c ON s.class_id = c.class_id 
          WHERE s.student_id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

$class_id = $student['class_id'];

// Get schedule for the student's class
$schedule_query = "SELECT s.*, f.name as faculty_name 
                   FROM schedule s 
                   JOIN faculty f ON s.faculty_id = f.faculty_id 
                   WHERE s.class_id = $class_id 
                   ORDER BY 
                       FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                       s.start_time";
$schedule_result = mysqli_query($conn, $schedule_query);

// Organize schedule by day
$schedule_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => []
];

while ($row = mysqli_fetch_assoc($schedule_result)) {
    $schedule_by_day[$row['day_of_week']][] = $row;
}

// Get current day
$current_day = date('l');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .schedule-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .day-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .day-card.today {
            border-left: 5px solid #667eea;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
        }
        
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }
        
        .day-name {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .day-name.today-label {
            color: #667eea;
        }
        
        .today-badge {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .classes-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .class-item {
            display: grid;
            grid-template-columns: 100px 1fr 150px 120px;
            gap: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .class-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }
        
        .class-time {
            font-weight: 700;
            color: #667eea;
            font-size: 16px;
        }
        
        .class-subject {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .class-faculty {
            color: #718096;
            font-size: 14px;
        }
        
        .class-room {
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .no-classes {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
            font-style: italic;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .class-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }
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
            </div>
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="student_attendance.php" class="nav-item">
                    <span>📅</span> Attendance
                </a>
                <a href="student_marks.php" class="nav-item">
                    <span>📝</span> Marks
                </a>
                <a href="student_schedule.php" class="nav-item active">
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
                <a href="logout.php" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1>Class Schedule</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Quick Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <h3>Today</h3>
                        <p class="stat-value"><?php echo $current_day; ?></p>
                        <span class="stat-label"><?php echo date('M d, Y'); ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3>Classes Today</h3>
                        <p class="stat-value"><?php echo count($schedule_by_day[$current_day]); ?></p>
                        <span class="stat-label">Scheduled</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-details">
                        <h3>Your Class</h3>
                        <p class="stat-value"><?php echo htmlspecialchars($student['class_name']); ?></p>
                        <span class="stat-label"><?php echo htmlspecialchars($student['dept_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule -->
            <h2 style="margin-bottom: 20px; color: #2d3748;">Weekly Timetable</h2>
            <div class="schedule-container">
                <?php foreach ($schedule_by_day as $day => $classes): ?>
                    <div class="day-card <?php echo ($day == $current_day) ? 'today' : ''; ?>">
                        <div class="day-header">
                            <div class="day-name <?php echo ($day == $current_day) ? 'today-label' : ''; ?>">
                                <?php echo $day; ?>
                            </div>
                            <?php if ($day == $current_day): ?>
                                <span class="today-badge">Today</span>
                            <?php endif; ?>
                        </div>

                        <?php if (count($classes) > 0): ?>
                            <div class="classes-list">
                                <?php foreach ($classes as $class): ?>
                                    <div class="class-item">
                                        <div class="class-time">
                                            <?php echo date('g:i A', strtotime($class['start_time'])); ?>
                                        </div>
                                        <div>
                                            <div class="class-subject">
                                                <?php echo htmlspecialchars($class['subject']); ?>
                                            </div>
                                            <div class="class-faculty">
                                                👨‍🏫 <?php echo htmlspecialchars($class['faculty_name']); ?>
                                            </div>
                                        </div>
                                        <div class="class-room">
                                            📍 <?php echo htmlspecialchars($class['room']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-classes">
                                No classes scheduled for this day
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
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