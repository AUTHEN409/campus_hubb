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

// Get total notes count
$count_query = "SELECT COUNT(*) as total FROM library_notes WHERE class_id = $class_id";
$count_result = mysqli_query($conn, $count_query);
$total_notes = mysqli_fetch_assoc($count_result)['total'];

// Get notes for student's class
$notes_query = "SELECT ln.*, f.name as uploaded_by_name 
                FROM library_notes ln 
                JOIN faculty f ON ln.uploaded_by = f.faculty_id 
                WHERE ln.class_id = $class_id 
                ORDER BY ln.upload_date DESC";
$notes_result = mysqli_query($conn, $notes_query);

// Group notes by subject
$notes_by_subject = [];
while ($note = mysqli_fetch_assoc($notes_result)) {
    $notes_by_subject[$note['subject']][] = $note;
}

// Get unique subjects for filtering
$subjects = array_keys($notes_by_subject);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .library-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #2d3748;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .subject-section {
            margin-bottom: 40px;
        }
        
        .subject-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .note-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .note-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .note-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .note-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #718096;
        }
        
        .note-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .download-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .search-box {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            width: 300px;
            transition: all 0.3s ease;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #667eea;
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
                <a href="student_library.php" class="nav-item active">
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
                <h1>Library & Study Materials</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span>|</span>
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
            </header>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3>Total Notes</h3>
                        <p class="stat-value"><?php echo $total_notes; ?></p>
                        <span class="stat-label">Available for your class</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📖</div>
                    <div class="stat-details">
                        <h3>Subjects</h3>
                        <p class="stat-value"><?php echo count($subjects); ?></p>
                        <span class="stat-label">Different subjects</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-details">
                        <h3>Your Class</h3>
                        <p class="stat-value"><?php echo htmlspecialchars($student['class_name']); ?></p>
                        <span class="stat-label">Study materials</span>
                    </div>
                </div>
            </div>

            <!-- Library Content -->
            <?php if (count($notes_by_subject) > 0): ?>
                <?php foreach ($notes_by_subject as $subject => $notes): ?>
                    <div class="subject-section">
                        <h2 class="subject-title"><?php echo htmlspecialchars($subject); ?></h2>
                        
                        <div class="notes-grid">
                            <?php foreach ($notes as $note): ?>
                                <div class="note-card">
                                    <div class="note-icon">📄</div>
                                    
                                    <div class="note-title">
                                        <?php echo htmlspecialchars($note['title']); ?>
                                    </div>
                                    
                                    <div class="note-meta">
                                        <div class="note-meta-item">
                                            <span>👨‍🏫</span>
                                            <span><?php echo htmlspecialchars($note['uploaded_by_name']); ?></span>
                                        </div>
                                        <div class="note-meta-item">
                                            <span>📅</span>
                                            <span><?php echo date('M d, Y', strtotime($note['upload_date'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <a href="<?php echo htmlspecialchars($note['file_path']); ?>" 
                                       class="download-btn" 
                                       download
                                       target="_blank">
                                        ⬇️ Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h3 style="color: #2d3748; margin-bottom: 10px;">No Study Materials Available</h3>
                    <p>Study materials will appear here once faculty uploads them for your class.</p>
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