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

// Calculate overall attendance
$overall_query = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
                  FROM attendance 
                  WHERE student_id = $student_id";
$overall_result = mysqli_query($conn, $overall_query);
$overall = mysqli_fetch_assoc($overall_result);
$overall_percentage = $overall['total_days'] > 0 
    ? round(($overall['present_days'] / $overall['total_days']) * 100, 2) 
    : 0;

// Get attendance records (last 30 days)
$attendance_query = "SELECT a.*, f.name as marked_by_name 
                     FROM attendance a 
                     LEFT JOIN faculty f ON a.marked_by = f.faculty_id 
                     WHERE a.student_id = $student_id 
                     ORDER BY a.date DESC 
                     LIMIT 30";
$attendance_records = mysqli_query($conn, $attendance_query);

// Calculate monthly attendance
$monthly_query = "SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
                  FROM attendance 
                  WHERE student_id = $student_id 
                  AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(date, '%Y-%m')
                  ORDER BY month DESC";
$monthly_records = mysqli_query($conn, $monthly_query);

// Get current month attendance for calendar (with faculty name)
$current_month = date('Y-m');
$calendar_query = "SELECT DATE(a.date) as date, a.status, f.name as marked_by_name
                   FROM attendance a
                   LEFT JOIN faculty f ON a.marked_by = f.faculty_id
                   WHERE a.student_id = $student_id
                   AND DATE_FORMAT(a.date, '%Y-%m') = '$current_month'";
$calendar_result = mysqli_query($conn, $calendar_query);
$calendar_data   = [];
$calendar_detail = []; // full detail keyed by date string
while ($row = mysqli_fetch_assoc($calendar_result)) {
    $calendar_data[$row['date']]   = $row['status'];
    $calendar_detail[$row['date']] = [
        'status'    => $row['status'],
        'faculty'   => $row['marked_by_name'] ?? 'N/A',
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .attendance-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .attendance-table tr:hover {
            background: #f7fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-present {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-absent {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .monthly-stats {
            margin-top: 30px;
        }
        
        .month-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .month-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 16px;
        }
        
        .month-percentage {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .calendar-container {
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
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f7fafc;
            color: #2d3748;
        }
        
        .calendar-day:hover {
            transform: scale(1.05);
        }
        
        .calendar-day.present {
            background: #c6f6d5;
            color: #22543d;
            font-weight: 700;
        }
        
        .calendar-day.absent {
            background: #fed7d7;
            color: #742a2a;
            font-weight: 700;
        }
        
        .calendar-day.today {
            border: 2px solid #667eea;
        }
        
        .calendar-day.empty {
            background: transparent;
        }
        
        .calendar-legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
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
            </div>
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="student_attendance.php" class="nav-item active">
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
                <h1>Attendance Records</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Attendance Overview -->
            <div class="attendance-overview">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <h3>Overall Attendance</h3>
                        <p class="stat-value"><?php echo $overall_percentage; ?>%</p>
                        <span class="stat-label">All time</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3>Present Days</h3>
                        <p class="stat-value"><?php echo $overall['present_days']; ?></p>
                        <span class="stat-label">Total present</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-details">
                        <h3>Absent Days</h3>
                        <p class="stat-value"><?php echo $overall['absent_days']; ?></p>
                        <span class="stat-label">Total absent</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <h3>Total Days</h3>
                        <p class="stat-value"><?php echo $overall['total_days']; ?></p>
                        <span class="stat-label">Recorded</span>
                    </div>
                </div>
            </div>

            <!-- Attendance Calendar -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <?php echo date('F Y'); ?> Attendance
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
                        $status_class = '';
                        $today_class = ($date == $today) ? 'today' : '';
                        
                        if (isset($calendar_data[$date])) {
                            $status_class = strtolower($calendar_data[$date]);
                        }
                        
                        echo '<div class="calendar-day ' . $status_class . ' ' . $today_class . '" title="' . $date . '" onclick="showDayDetail(\'' . $date . '\')">' . $day . '</div>';
                    }
                    ?>
                </div>
                
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-box" style="background: #c6f6d5;"></div>
                        <span>Present</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box" style="background: #fed7d7;"></div>
                        <span>Absent</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box" style="background: #f7fafc; border: 2px solid #667eea;"></div>
                        <span>Today</span>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Table -->
            <div class="card">
                <h2>Recent Attendance (Last 30 Days)</h2>
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Status</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($attendance_records) > 0): ?>
                            <?php while ($record = mysqli_fetch_assoc($attendance_records)): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                            <?php echo $record['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['marked_by_name'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: #a0aec0;">
                                    No attendance records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Monthly Statistics -->
            <div class="monthly-stats">
                <h2 style="margin-bottom: 20px; color: #2d3748;">Monthly Breakdown</h2>
                <?php if (mysqli_num_rows($monthly_records) > 0): ?>
                    <?php while ($month = mysqli_fetch_assoc($monthly_records)): ?>
                        <?php 
                            $month_percentage = round(($month['present'] / $month['total']) * 100, 2);
                            $month_name = date('F Y', strtotime($month['month'] . '-01'));
                        ?>
                        <div class="month-card">
                            <div>
                                <div class="month-name"><?php echo $month_name; ?></div>
                                <small style="color: #718096;">
                                    <?php echo $month['present']; ?> / <?php echo $month['total']; ?> days
                                </small>
                            </div>
                            <div class="month-percentage"><?php echo $month_percentage; ?>%</div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No monthly data available</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Day Detail Modal -->
    <div class="att-modal-overlay" id="attModalOverlay" onclick="closeDayDetail()"></div>
    <div class="att-modal" id="attModal">
        <div class="att-modal-header">
            <span id="attModalDate" class="att-modal-title"></span>
            <button class="att-modal-close" onclick="closeDayDetail()">✕</button>
        </div>
        <div id="attModalBody" class="att-modal-body"></div>
    </div>

    <style>
        /* ── Attendance day-detail modal ── */
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
        .att-modal.open {
            display: block;
            transform: translateY(0);
        }
        .att-modal-overlay.open { display: block; }
        .att-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .att-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
        }
        .att-modal-close {
            background: #f7fafc;
            border: none;
            border-radius: 50%;
            width: 32px; height: 32px;
            font-size: 15px;
            cursor: pointer;
            color: #4a5568;
            display: flex; align-items: center; justify-content: center;
        }
        .att-modal-body { font-size: 14px; }
        .att-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f4f8;
        }
        .att-detail-row:last-child { border-bottom: none; }
        .att-detail-label { color: #718096; font-size: 13px; }
        .att-detail-value { font-weight: 600; color: #2d3748; font-size: 14px; }
        .att-no-record {
            text-align: center;
            padding: 24px 0;
            color: #a0aec0;
            font-style: italic;
        }
        .att-drag-bar {
            width: 40px; height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            margin: 0 auto 18px;
        }
        /* Clickable cursor for days that have data */
        .calendar-day:not(.empty) { cursor: pointer; }

        @media (min-width: 520px) {
            .att-modal {
                bottom: auto;
                top: 50%; left: 50%;
                transform: translate(-50%, -60%);
                border-radius: 16px;
                width: 380px;
                transition: none;
            }
            .att-modal.open {
                transform: translate(-50%, -50%);
            }
        }
    </style>

    <script>
        const calendarDetail = <?php echo json_encode($calendar_detail); ?>;

        function showDayDetail(date) {
            const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            const d = new Date(date + 'T00:00:00');
            const label = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
            const days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

            document.getElementById('attModalDate').textContent = label;

            let html = '<div class="att-drag-bar"></div>';
            const rec = calendarDetail[date];

            if (rec) {
                const isPresent = rec.status === 'Present';
                const statusColor = isPresent ? '#22543d' : '#742a2a';
                const statusBg    = isPresent ? '#c6f6d5' : '#fed7d7';
                const statusIcon  = isPresent ? '✅' : '❌';

                html += `
                    <div class="att-detail-row">
                        <span class="att-detail-label">Date</span>
                        <span class="att-detail-value">${label}</span>
                    </div>
                    <div class="att-detail-row">
                        <span class="att-detail-label">Day</span>
                        <span class="att-detail-value">${days[d.getDay()]}</span>
                    </div>
                    <div class="att-detail-row">
                        <span class="att-detail-label">Status</span>
                        <span class="att-detail-value">
                            <span style="background:${statusBg};color:${statusColor};padding:4px 12px;border-radius:20px;font-size:13px;">
                                ${statusIcon} ${rec.status}
                            </span>
                        </span>
                    </div>
                    <div class="att-detail-row">
                        <span class="att-detail-label">Marked By</span>
                        <span class="att-detail-value">${rec.faculty}</span>
                    </div>`;
            } else {
                html += `<div class="att-no-record">📭 No attendance record for this day.</div>`;
            }

            document.getElementById('attModalBody').innerHTML = html;
            document.getElementById('attModal').classList.add('open');
            document.getElementById('attModalOverlay').classList.add('open');
        }

        function closeDayDetail() {
            document.getElementById('attModal').classList.remove('open');
            document.getElementById('attModalOverlay').classList.remove('open');
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDayDetail(); });
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