<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely (prevents "session already active" error)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

$student_id = $_SESSION['user_id'];

// Fetch student details
$student_query = "SELECT s.*, d.dept_name, c.class_name, c.year 
                  FROM students s
                  JOIN departments d ON s.dept_id = d.dept_id
                  JOIN classes c ON s.class_id = c.class_id
                  WHERE s.student_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Fetch test results
$tests_query = "SELECT t.*, c.class_name, f.name as faculty_name 
                FROM tests t
                JOIN classes c ON t.class_id = c.class_id
                LEFT JOIN faculty f ON t.conducted_by = f.faculty_id
                WHERE t.student_id = ?
                ORDER BY t.test_date DESC";
$tests_stmt = $conn->prepare($tests_query);
$tests_stmt->bind_param("i", $student_id);
$tests_stmt->execute();
$tests = $tests_stmt->get_result();

// Calculate overall statistics
$stats_query = "SELECT 
    COUNT(*) as total_tests,
    AVG((marks_obtained/total_marks)*100) as average_percentage,
    MAX((marks_obtained/total_marks)*100) as highest_percentage,
    MIN((marks_obtained/total_marks)*100) as lowest_percentage,
    SUM(marks_obtained) as total_marks_obtained,
    SUM(total_marks) as total_marks_possible
    FROM tests WHERE student_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Subject-wise performance
$subject_query = "SELECT 
    subject,
    COUNT(*) as test_count,
    AVG((marks_obtained/total_marks)*100) as avg_percentage,
    SUM(marks_obtained) as total_obtained,
    SUM(total_marks) as total_possible
    FROM tests 
    WHERE student_id = ?
    GROUP BY subject
    ORDER BY avg_percentage DESC";
$subject_stmt = $conn->prepare($subject_query);
$subject_stmt->bind_param("i", $student_id);
$subject_stmt->execute();
$subjects = $subject_stmt->get_result();

// Fetch attendance statistics
$attendance_query = "SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance WHERE student_id = ?";
$att_stmt = $conn->prepare($attendance_query);
$att_stmt->bind_param("i", $student_id);
$att_stmt->execute();
$attendance = $att_stmt->get_result()->fetch_assoc();
$attendance_percentage = $attendance['total_days'] > 0 ? 
    ($attendance['present_days'] / $attendance['total_days'] * 100) : 0;

// Monthly attendance for chart
$monthly_att_query = "SELECT 
    DATE_FORMAT(date, '%Y-%m') as month,
    COUNT(*) as total,
    SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month";
$monthly_att_stmt = $conn->prepare($monthly_att_query);
$monthly_att_stmt->bind_param("i", $student_id);
$monthly_att_stmt->execute();
$monthly_attendance = $monthly_att_stmt->get_result();

// Fetch remarks
$remarks_query = "SELECT r.*, f.name as faculty_name, f.employee_id
                  FROM remarks r
                  JOIN faculty f ON r.faculty_id = f.faculty_id
                  WHERE r.student_id = ?
                  ORDER BY r.created_at DESC
                  LIMIT 5";
$remarks_stmt = $conn->prepare($remarks_query);
$remarks_stmt->bind_param("i", $student_id);
$remarks_stmt->execute();
$remarks = $remarks_stmt->get_result();

// Determine performance grade
function getGrade($percentage) {
    if ($percentage >= 90) return ['A+', '#28a745'];
    if ($percentage >= 80) return ['A', '#5cb85c'];
    if ($percentage >= 70) return ['B+', '#17a2b8'];
    if ($percentage >= 60) return ['B', '#ffc107'];
    if ($percentage >= 50) return ['C', '#fd7e14'];
    return ['F', '#dc3545'];
}

$overall_grade = isset($stats['average_percentage']) && $stats['total_tests'] > 0 
    ? getGrade($stats['average_percentage']) 
    : ['N/A', '#6c757d'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 10px 30px;
            border-radius: 50px;
            font-size: 32px;
            font-weight: bold;
            color: white;
            margin-top: 10px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-title {
            font-size: 22px;
            color: #333;
            font-weight: 600;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .percentage-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .percentage-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .subject-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .subject-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        
        .subject-stats {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .remark-item {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .remark-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .remark-faculty {
            font-weight: 600;
            color: #667eea;
        }
        
        .remark-date {
            font-size: 12px;
            color: #999;
        }
        
        .remark-text {
            color: #555;
            line-height: 1.6;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
                <a href="student_schedule.php" class="nav-item">
                    <span>🕐</span> Schedule
                </a>
                <a href="student_library.php" class="nav-item">
                    <span>📚</span> Library
                </a>
                <a href="student_events.php" class="nav-item">
                    <span>🎉</span> Events
                </a>
                <a href="student_performance.php" class="nav-item active">
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
                <h1>My Performance</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['name']); ?> - <?php echo htmlspecialchars($student['class_name']); ?></span>
                </div>
            </header>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $stats['total_tests'] ?? 0; ?></div>
                <div class="stat-label">Total Tests</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-value"><?php echo isset($stats['average_percentage']) ? number_format($stats['average_percentage'], 1) . '%' : 'N/A'; ?></div>
                <div class="stat-label">Average Score</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?php echo isset($stats['highest_percentage']) ? number_format($stats['highest_percentage'], 1) . '%' : 'N/A'; ?></div>
                <div class="stat-label">Highest Score</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo number_format($attendance_percentage, 1); ?>%</div>
                <div class="stat-label">Attendance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-label">Overall Grade</div>
                <div class="grade-badge" style="background: <?php echo $overall_grade[1]; ?>">
                    <?php echo $overall_grade[0]; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Recent Test Results -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h2 class="card-title">Recent Test Results</h2>
                    </div>
                    
                    <?php if ($tests->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Marks</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($test = $tests->fetch_assoc()): 
                                        $percentage = ($test['marks_obtained'] / $test['total_marks']) * 100;
                                        $grade = getGrade($percentage);
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($test['subject']); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                            <td><?php echo $test['marks_obtained']; ?> / <?php echo $test['total_marks']; ?></td>
                                            <td>
                                                <?php echo number_format($percentage, 1); ?>%
                                                <div class="percentage-bar">
                                                    <div class="percentage-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $grade[1]; ?>;"></div>
                                                </div>
                                            </td>
                                            <td><span style="color: <?php echo $grade[1]; ?>; font-weight: bold;"><?php echo $grade[0]; ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <p>No test results available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Attendance Chart -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Attendance Trend (Last 6 Months)</h2>
                    </div>
                    
                    <?php if ($monthly_attendance->num_rows > 0): ?>
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <p>No attendance data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Subject-wise Performance -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h2 class="card-title">Subject Performance</h2>
                    </div>
                    
                    <?php if ($subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): 
                            $grade = getGrade($subject['avg_percentage']);
                        ?>
                            <div class="subject-item">
                                <div class="subject-name">
                                    <span><?php echo htmlspecialchars($subject['subject']); ?></span>
                                    <span style="color: <?php echo $grade[1]; ?>; font-weight: bold;"><?php echo $grade[0]; ?></span>
                                </div>
                                <div><?php echo number_format($subject['avg_percentage'], 1); ?>%</div>
                                <div class="percentage-bar">
                                    <div class="percentage-fill" style="width: <?php echo $subject['avg_percentage']; ?>%; background: <?php echo $grade[1]; ?>;"></div>
                                </div>
                                <div class="subject-stats">
                                    <?php echo $subject['test_count']; ?> tests • 
                                    <?php echo $subject['total_obtained']; ?>/<?php echo $subject['total_possible']; ?> marks
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📚</div>
                            <p>No subject data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Faculty Remarks -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Faculty Remarks</h2>
                    </div>
                    
                    <?php if ($remarks->num_rows > 0): ?>
                        <?php while ($remark = $remarks->fetch_assoc()): ?>
                            <div class="remark-item">
                                <div class="remark-header">
                                    <span class="remark-faculty"><?php echo htmlspecialchars($remark['faculty_name']); ?></span>
                                    <span class="remark-date"><?php echo date('M d, Y', strtotime($remark['created_date'])); ?></span>
                                </div>
                                <div class="remark-text"><?php echo htmlspecialchars($remark['remark_text']); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💬</div>
                            <p>No remarks from faculty yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </main>
    </div>
    
    <script>
        <?php 
        // Prepare data for attendance chart
        $monthly_attendance->data_seek(0);
        $months = [];
        $attendance_data = [];
        while ($row = $monthly_attendance->fetch_assoc()) {
            $months[] = date('M Y', strtotime($row['month'] . '-01'));
            $attendance_data[] = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 1) : 0;
        }
        ?>
        
        <?php if (count($months) > 0): ?>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Attendance %',
                    data: <?php echo json_encode($attendance_data); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                return 'Attendance: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>
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