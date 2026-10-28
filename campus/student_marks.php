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

// Calculate overall statistics
$stats_query = "SELECT 
                    COUNT(*) as total_tests,
                    AVG((marks_obtained / total_marks) * 100) as average_percentage,
                    MAX((marks_obtained / total_marks) * 100) as highest_percentage,
                    MIN((marks_obtained / total_marks) * 100) as lowest_percentage
                FROM tests 
                WHERE student_id = $student_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get all test records
$tests_query = "SELECT t.*, f.name as conducted_by_name 
                FROM tests t 
                LEFT JOIN faculty f ON t.conducted_by = f.faculty_id 
                WHERE t.student_id = $student_id 
                ORDER BY t.test_date DESC";
$tests_records = mysqli_query($conn, $tests_query);

// Get subject-wise performance
$subject_query = "SELECT 
                    subject,
                    COUNT(*) as test_count,
                    AVG((marks_obtained / total_marks) * 100) as avg_percentage,
                    SUM(marks_obtained) as total_obtained,
                    SUM(total_marks) as total_marks_sum
                  FROM tests 
                  WHERE student_id = $student_id 
                  GROUP BY subject
                  ORDER BY avg_percentage DESC";
$subject_records = mysqli_query($conn, $subject_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Marks - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .marks-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .marks-table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .marks-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .marks-table tr:hover {
            background: #f7fafc;
        }
        
        .percentage-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .percentage-excellent {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .percentage-good {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .percentage-average {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .percentage-poor {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .subject-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .subject-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .subject-name {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .subject-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .subject-percentage {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        
        .subject-info {
            font-size: 12px;
            color: #718096;
        }
        
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.3s ease;
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
                <a href="student_marks.php" class="nav-item active">
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
                <h1>Test Marks & Performance</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Marks Overview -->
            <div class="marks-overview">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-details">
                        <h3>Average Score</h3>
                        <p class="stat-value"><?php echo number_format($stats['average_percentage'] ?? 0, 2); ?>%</p>
                        <span class="stat-label">Overall average</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-details">
                        <h3>Highest Score</h3>
                        <p class="stat-value"><?php echo number_format($stats['highest_percentage'] ?? 0, 2); ?>%</p>
                        <span class="stat-label">Best performance</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📉</div>
                    <div class="stat-details">
                        <h3>Lowest Score</h3>
                        <p class="stat-value"><?php echo number_format($stats['lowest_percentage'] ?? 0, 2); ?>%</p>
                        <span class="stat-label">Needs improvement</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-details">
                        <h3>Total Tests</h3>
                        <p class="stat-value"><?php echo $stats['total_tests']; ?></p>
                        <span class="stat-label">Completed</span>
                    </div>
                </div>
            </div>

            <!-- Subject-wise Performance -->
            <h2 style="margin-bottom: 20px; color: #2d3748;">Subject-wise Performance</h2>
            <div class="subject-cards">
                <?php if (mysqli_num_rows($subject_records) > 0): ?>
                    <?php while ($subject = mysqli_fetch_assoc($subject_records)): ?>
                        <?php $percentage = round($subject['avg_percentage'], 2); ?>
                        <div class="subject-card">
                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject']); ?></div>
                            <div class="subject-info">
                                <?php echo $subject['test_count']; ?> test(s) | 
                                <?php echo $subject['total_obtained']; ?>/<?php echo $subject['total_marks_sum']; ?> marks
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="subject-stats">
                                <div class="subject-percentage"><?php echo $percentage; ?>%</div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No subject data available</p>
                <?php endif; ?>
            </div>

            <!-- Detailed Marks Table -->
            <div class="card">
                <h2>All Test Records</h2>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Marks Obtained</th>
                            <th>Total Marks</th>
                            <th>Percentage</th>
                            <th>Conducted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($tests_records) > 0): ?>
                            <?php while ($test = mysqli_fetch_assoc($tests_records)): ?>
                                <?php 
                                    $percentage = ($test['marks_obtained'] / $test['total_marks']) * 100;
                                    $badge_class = $percentage >= 75 ? 'percentage-excellent' : 
                                                  ($percentage >= 60 ? 'percentage-good' : 
                                                  ($percentage >= 40 ? 'percentage-average' : 'percentage-poor'));
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($test['subject']); ?></td>
                                    <td><?php echo $test['marks_obtained']; ?></td>
                                    <td><?php echo $test['total_marks']; ?></td>
                                    <td>
                                        <span class="percentage-badge <?php echo $badge_class; ?>">
                                            <?php echo number_format($percentage, 2); ?>%
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($test['conducted_by_name'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #a0aec0;">
                                    No test records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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