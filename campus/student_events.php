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
$query = "SELECT s.*, d.dept_name, c.class_name 
          FROM students s 
          JOIN departments d ON s.dept_id = d.dept_id 
          JOIN classes c ON s.class_id = c.class_id 
          WHERE s.student_id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// Get upcoming events
$upcoming_query = "SELECT * FROM events 
                   WHERE event_date >= CURDATE() 
                   ORDER BY event_date ASC, event_time ASC";
$upcoming_events = mysqli_query($conn, $upcoming_query);

// Get past events
$past_query = "SELECT * FROM events 
               WHERE event_date < CURDATE() 
               ORDER BY event_date DESC 
               LIMIT 10";
$past_events = mysqli_query($conn, $past_query);

// Count events
$upcoming_count = mysqli_num_rows($upcoming_events);
mysqli_data_seek($upcoming_events, 0); // Reset pointer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .events-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .event-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-top: 5px solid #667eea;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .event-date-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.9);
            color: #667eea;
            padding: 10px 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
        }
        
        .event-date-day {
            font-size: 24px;
            display: block;
        }
        
        .event-date-month {
            font-size: 12px;
            display: block;
            text-transform: uppercase;
        }
        
        .event-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
            padding-right: 80px;
        }
        
        .event-body {
            padding: 20px;
        }
        
        .event-description {
            color: #718096;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .event-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #2d3748;
        }
        
        .event-icon {
            width: 35px;
            height: 35px;
            background: #f7fafc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .past-event-card {
            opacity: 0.7;
        }
        
        .past-event-card .event-header {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        }
        
        .past-event-card .event-date-badge {
            color: #718096;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
            background: white;
            border-radius: 15px;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .event-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .status-upcoming {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-today {
            background: #feebc8;
            color: #7c2d12;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
                <a href="student_schedule.php" class="nav-item">
                    <span>🕐</span> Schedule
                </a>
                <a href="student_library.php" class="nav-item">
                    <span>📚</span> Library
                </a>
                <a href="student_events.php" class="nav-item active">
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
                <h1>College Events</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon">🎉</div>
                    <div class="stat-details">
                        <h3>Upcoming Events</h3>
                        <p class="stat-value"><?php echo $upcoming_count; ?></p>
                        <span class="stat-label">Don't miss out!</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <h3>Today's Date</h3>
                        <p class="stat-value"><?php echo date('d'); ?></p>
                        <span class="stat-label"><?php echo date('F Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="events-section">
                <h2 class="section-title">Upcoming Events</h2>
                
                <?php if ($upcoming_count > 0): ?>
                    <div class="events-grid">
                        <?php while ($event = mysqli_fetch_assoc($upcoming_events)): ?>
                            <?php 
                                $event_date = strtotime($event['event_date']);
                                $today = strtotime(date('Y-m-d'));
                                $is_today = ($event_date == $today);
                            ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div class="event-date-badge">
                                        <span class="event-date-day"><?php echo date('d', $event_date); ?></span>
                                        <span class="event-date-month"><?php echo date('M', $event_date); ?></span>
                                    </div>
                                    <div class="event-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </div>
                                    <?php if ($is_today): ?>
                                        <span class="event-status status-today">Today!</span>
                                    <?php else: ?>
                                        <span class="event-status status-upcoming">Upcoming</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-body">
                                    <div class="event-description">
                                        <?php echo htmlspecialchars($event['description']); ?>
                                    </div>
                                    
                                    <div class="event-details">
                                        <div class="event-detail-item">
                                            <div class="event-icon">📅</div>
                                            <span><?php echo date('l, F d, Y', $event_date); ?></span>
                                        </div>
                                        
                                        <?php if ($event['event_time']): ?>
                                            <div class="event-detail-item">
                                                <div class="event-icon">🕐</div>
                                                <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['venue']): ?>
                                            <div class="event-detail-item">
                                                <div class="event-icon">📍</div>
                                                <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎉</div>
                        <h3 style="color: #2d3748; margin-bottom: 10px;">No Upcoming Events</h3>
                        <p>Check back later for exciting events!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Events -->
            <?php if (mysqli_num_rows($past_events) > 0): ?>
                <div class="events-section">
                    <h2 class="section-title">Past Events</h2>
                    
                    <div class="events-grid">
                        <?php while ($event = mysqli_fetch_assoc($past_events)): ?>
                            <div class="event-card past-event-card">
                                <div class="event-header">
                                    <div class="event-date-badge">
                                        <span class="event-date-day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                        <span class="event-date-month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </div>
                                </div>
                                
                                <div class="event-body">
                                    <div class="event-description">
                                        <?php echo htmlspecialchars($event['description']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
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