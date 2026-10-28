<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database.php';
// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student details
$query = "SELECT s.*, d.dept_name, c.class_name, c.year 
          FROM students s 
          JOIN departments d ON s.dept_id = d.dept_id 
          JOIN classes c ON s.class_id = c.class_id 
          WHERE s.student_id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// Check if student is in 3rd year
if ($student['year'] != 3) {
    $access_denied = true;
} else {
    $access_denied = false;
}

// Create placements table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS placements (
    placement_id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(200) NOT NULL,
    job_role VARCHAR(200) NOT NULL,
    job_type ENUM('Full-Time', 'Internship', 'Part-Time') NOT NULL,
    description TEXT,
    eligibility TEXT,
    package VARCHAR(100),
    location VARCHAR(200),
    application_deadline DATE,
    posted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    posted_by INT,
    status ENUM('active', 'expired', 'closed') DEFAULT 'active',
    FOREIGN KEY (posted_by) REFERENCES admin(admin_id)
)";
mysqli_query($conn, $create_table);

// Get active placements
$placements_query = "SELECT * FROM placements 
                     WHERE status = 'active' 
                     AND (application_deadline >= CURDATE() OR application_deadline IS NULL)
                     ORDER BY posted_date DESC";
$placements = mysqli_query($conn, $placements_query);

// Count placements
$total_placements = mysqli_num_rows($placements);
$internships = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements WHERE job_type = 'Internship' AND status = 'active'"));
$full_time = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements WHERE job_type = 'Full-Time' AND status = 'active'"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Cell - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .access-denied-container {
            max-width: 600px;
            margin: 100px auto;
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .access-denied-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .access-denied-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
        }
        .access-denied-text {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .placements-grid {
            display: grid;
            gap: 25px;
        }
        .placement-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
        }
        .placement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .placement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        .company-info {
            flex: 1;
        }
        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .job-role {
            font-size: 18px;
            color: #667eea;
            font-weight: 600;
        }
        .job-type-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-fulltime {
            background: #c6f6d5;
            color: #22543d;
        }
        .badge-internship {
            background: #bee3f8;
            color: #2c5282;
        }
        .badge-parttime {
            background: #feebc8;
            color: #7c2d12;
        }
        .placement-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
            background: #f7fafc;
            border-radius: 12px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-icon {
            font-size: 20px;
        }
        .detail-label {
            font-size: 12px;
            color: #718096;
            display: block;
        }
        .detail-value {
            font-size: 15px;
            color: #2d3748;
            font-weight: 600;
        }
        .placement-description {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .eligibility-section {
            background: #edf2f7;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .eligibility-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .eligibility-text {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.5;
        }
        .deadline-alert {
            background: #fef3c7;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        .deadline-alert.urgent {
            background: #fed7d7;
            border-left-color: #ef4444;
        }
        .apply-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .posted-date {
            font-size: 13px;
            color: #a0aec0;
        }
        .apply-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .empty-state {
            background: white;
            padding: 80px 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
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
            <?php if ($access_denied): ?>
                <!-- Access Denied Message -->
                <div class="access-denied-container">
                    <div class="access-denied-icon">🔒</div>
                    <h2 class="access-denied-title">Access Restricted</h2>
                    <p class="access-denied-text">
                        The Placement Cell portal is exclusively available for <strong>Final Year (3rd Year)</strong> students. 
                        You are currently in <strong>Year <?php echo $student['year']; ?></strong>.
                    </p>
                    <p class="access-denied-text">
                        You will gain access to placement opportunities and campus recruitment updates once you reach your final year.
                    </p>
                    <a href="student_dashboard.php" class="apply-btn">← Back to Dashboard</a>
                </div>
            <?php else: ?>
                <!-- Placement Content for 3rd Year Students -->
                <header class="header">
                    <div>
                        <h1>🎓 Placement Cell</h1>
                        <p style="color: #718096; margin-top: 5px;">Campus Recruitment & Career Opportunities</p>
                    </div>
                </header>

                <!-- Statistics -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon">💼</div>
                        <div class="stat-details">
                            <h3>Total Opportunities</h3>
                            <p class="stat-value"><?php echo $total_placements; ?></p>
                            <span class="stat-label">Active openings</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">🏢</div>
                        <div class="stat-details">
                            <h3>Full-Time Jobs</h3>
                            <p class="stat-value"><?php echo $full_time; ?></p>
                            <span class="stat-label">Permanent positions</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">🚀</div>
                        <div class="stat-details">
                            <h3>Internships</h3>
                            <p class="stat-value"><?php echo $internships; ?></p>
                            <span class="stat-label">Training opportunities</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-details">
                            <h3>Your Department</h3>
                            <p class="stat-value" style="font-size: 20px;"><?php echo htmlspecialchars($student['dept_name']); ?></p>
                            <span class="stat-label">Final year student</span>
                        </div>
                    </div>
                </div>

                <!-- Placements Grid -->
                <div class="placements-grid">
                    <?php if (mysqli_num_rows($placements) > 0): ?>
                        <?php mysqli_data_seek($placements, 0); ?>
                        <?php while ($placement = mysqli_fetch_assoc($placements)): ?>
                            <?php
                            // Calculate days until deadline
                            $days_left = null;
                            $is_urgent = false;
                            if ($placement['application_deadline']) {
                                $deadline = strtotime($placement['application_deadline']);
                                $today = strtotime(date('Y-m-d'));
                                $days_left = floor(($deadline - $today) / (60 * 60 * 24));
                                $is_urgent = $days_left <= 3;
                            }
                            
                            $badge_class = 'badge-fulltime';
                            if ($placement['job_type'] == 'Internship') $badge_class = 'badge-internship';
                            if ($placement['job_type'] == 'Part-Time') $badge_class = 'badge-parttime';
                            ?>
                            <div class="placement-card">
                                <div class="placement-header">
                                    <div class="company-info">
                                        <div class="company-name">🏢 <?php echo htmlspecialchars($placement['company_name']); ?></div>
                                        <div class="job-role"><?php echo htmlspecialchars($placement['job_role']); ?></div>
                                    </div>
                                    <span class="job-type-badge <?php echo $badge_class; ?>">
                                        <?php echo $placement['job_type']; ?>
                                    </span>
                                </div>

                                <?php if ($days_left !== null): ?>
                                    <div class="deadline-alert <?php echo $is_urgent ? 'urgent' : ''; ?>">
                                        <span style="font-size: 20px;">⏰</span>
                                        <div>
                                            <strong>Application Deadline:</strong> 
                                            <?php echo date('F d, Y', strtotime($placement['application_deadline'])); ?>
                                            <span style="font-weight: 600;">
                                                (<?php echo $days_left; ?> day<?php echo $days_left != 1 ? 's' : ''; ?> left)
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="placement-details">
                                    <?php if ($placement['package']): ?>
                                        <div class="detail-item">
                                            <div>
                                                <span class="detail-icon">💰</span>
                                            </div>
                                            <div>
                                                <span class="detail-label">Package</span>
                                                <div class="detail-value"><?php echo htmlspecialchars($placement['package']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($placement['location']): ?>
                                        <div class="detail-item">
                                            <div>
                                                <span class="detail-icon">📍</span>
                                            </div>
                                            <div>
                                                <span class="detail-label">Location</span>
                                                <div class="detail-value"><?php echo htmlspecialchars($placement['location']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($placement['description']): ?>
                                    <div class="placement-description">
                                        <?php echo nl2br(htmlspecialchars($placement['description'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($placement['eligibility']): ?>
                                    <div class="eligibility-section">
                                        <div class="eligibility-title">📋 Eligibility Criteria</div>
                                        <div class="eligibility-text">
                                            <?php echo nl2br(htmlspecialchars($placement['eligibility'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="apply-section">
                                    <div class="posted-date">
                                        Posted on <?php echo date('M d, Y', strtotime($placement['posted_date'])); ?>
                                    </div>
                                    <button class="apply-btn" onclick="alert('To apply, please contact the Placement Cell office or your coordinator.\n\nCompany: <?php echo htmlspecialchars($placement['company_name']); ?>\nRole: <?php echo htmlspecialchars($placement['job_role']); ?>')">
                                        Apply Now →
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💼</div>
                            <h3 style="color: #2d3748; margin-bottom: 10px;">No Active Placements</h3>
                            <p style="color: #718096;">
                                There are currently no active placement opportunities. 
                                Check back soon for new openings!
                            </p>
                        </div>
                    <?php endif; ?>
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