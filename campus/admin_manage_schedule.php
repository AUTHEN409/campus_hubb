<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle Add Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $class_id = intval($_POST['class_id']);
    $day_of_week = mysqli_real_escape_string($conn, $_POST['day_of_week']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $faculty_id = intval($_POST['faculty_id']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);
    
    $query = "INSERT INTO schedule (class_id, day_of_week, start_time, subject, faculty_id, room) 
              VALUES ($class_id, '$day_of_week', '$start_time', '$subject', $faculty_id, '$room')";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_manage_schedule.php?success=Schedule added successfully!");
        exit();
    } else {
        header("Location: admin_manage_schedule.php?error=Failed to add schedule");
        exit();
    }
}

// Handle Delete Schedule
if (isset($_GET['delete'])) {
    $schedule_id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM schedule WHERE schedule_id = $schedule_id")) {
        header("Location: admin_manage_schedule.php?success=Schedule deleted successfully!");
        exit();
    }
}

// Handle Update Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $schedule_id = intval($_POST['schedule_id']);
    $class_id = intval($_POST['class_id']);
    $day_of_week = mysqli_real_escape_string($conn, $_POST['day_of_week']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $faculty_id = intval($_POST['faculty_id']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);
    
    $query = "UPDATE schedule SET 
              class_id = $class_id,
              day_of_week = '$day_of_week',
              start_time = '$start_time',
              subject = '$subject',
              faculty_id = $faculty_id,
              room = '$room'
              WHERE schedule_id = $schedule_id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_manage_schedule.php?success=Schedule updated successfully!");
        exit();
    }
}

// Get data for dropdowns
$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
$faculty = mysqli_query($conn, "SELECT * FROM faculty ORDER BY name");

// Get all schedules
$schedules_query = "SELECT s.*, c.class_name, f.name as faculty_name, d.dept_name
                    FROM schedule s
                    JOIN classes c ON s.class_id = c.class_id
                    JOIN faculty f ON s.faculty_id = f.faculty_id
                    JOIN departments d ON c.dept_id = d.dept_id
                    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                             s.start_time";
$schedules = mysqli_query($conn, $schedules_query);

// Organize by day
$schedule_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => []
];

mysqli_data_seek($schedules, 0);
while ($row = mysqli_fetch_assoc($schedules)) {
    $schedule_by_day[$row['day_of_week']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #c6f6d5; color: #22543d; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; border-left: 4px solid #f56565; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 28px; cursor: pointer; color: #6b7280; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .day-section { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .day-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .day-name { font-size: 22px; font-weight: 700; color: #667eea; }
        .schedule-grid { display: grid; gap: 15px; }
        .schedule-card { background: #f7fafc; padding: 15px; border-radius: 10px; border-left: 4px solid #667eea; display: grid; grid-template-columns: 80px 1fr auto; gap: 15px; align-items: center; }
        .schedule-time { font-weight: 700; color: #667eea; font-size: 14px; }
        .schedule-details { flex: 1; }
        .schedule-subject { font-weight: 600; color: #2d3748; margin-bottom: 4px; }
        .schedule-meta { font-size: 13px; color: #718096; }
        .schedule-actions { display: flex; gap: 8px; }
        .empty-day { text-align: center; padding: 30px; color: #a0aec0; font-style: italic; }
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

        <aside class="sidebar">
            <div class="logo">
                <div class="college-logo">
                    <img src="college-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <h2>Campus Hub</h2>
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Admin Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
                <a href="admin_manage_students.php" class="nav-item"><span>👨‍🎓</span> Manage Students</a>
                <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
                <a href="admin_manage_schedule.php" class="nav-item active"><span>📅</span> Manage Schedule</a>
                <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
                <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
                <a href="admin_manage_events.php" class="nav-item"><span>🎉</span> Manage Events</a>
                <a href="admin_manage_placements.php" class="nav-item active"><span>💼</span> Placements</a>
                <a href="admin_send_notifications.php" class="nav-item"><span>🔔</span> Send Notifications</a>
                <a href="admin_profile.php" class="nav-item"><span>👤</span> Profile</a>
                <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <button class="header-menu-btn" onclick="document.getElementById('nav-toggle').click()" aria-label="Toggle navigation">
                    <span></span><span></span><span></span>
                </button>
                <h1>Manage Class Schedule</h1>
                <button onclick="openAddModal()" class="btn btn-primary">+ Add Schedule Entry</button>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Weekly Schedule View -->
            <?php foreach ($schedule_by_day as $day => $day_schedules): ?>
                <div class="day-section">
                    <div class="day-header">
                        <span class="day-name">📅 <?php echo $day; ?></span>
                        <span style="color: #718096; font-size: 14px;"><?php echo count($day_schedules); ?> classes</span>
                    </div>

                    <?php if (count($day_schedules) > 0): ?>
                        <div class="schedule-grid">
                            <?php foreach ($day_schedules as $schedule): ?>
                                <div class="schedule-card">
                                    <div class="schedule-time">
                                        🕐 <?php echo date('g:i A', strtotime($schedule['start_time'])); ?>
                                    </div>
                                    <div class="schedule-details">
                                        <div class="schedule-subject"><?php echo htmlspecialchars($schedule['subject']); ?></div>
                                        <div class="schedule-meta">
                                            👨‍🏫 <?php echo htmlspecialchars($schedule['faculty_name']); ?> | 
                                            🎓 <?php echo htmlspecialchars($schedule['class_name']); ?> | 
                                            📍 Room <?php echo htmlspecialchars($schedule['room']); ?>
                                        </div>
                                    </div>
                                    <div class="schedule-actions">
                                        <button onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)" class="btn btn-warning btn-sm">Edit</button>
                                        <a href="?delete=<?php echo $schedule['schedule_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this schedule?')">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-day">No classes scheduled for <?php echo $day; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

    <!-- Add Schedule Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Schedule Entry</h2>
                <span class="close-btn" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php mysqli_data_seek($classes, 0); while($class = mysqli_fetch_assoc($classes)): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Day</label>
                        <select name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="e.g., Mathematics, Physics">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Faculty</label>
                        <select name="faculty_id" required>
                            <option value="">Select Faculty</option>
                            <?php mysqli_data_seek($faculty, 0); while($fac = mysqli_fetch_assoc($faculty)): ?>
                                <option value="<?php echo $fac['faculty_id']; ?>"><?php echo htmlspecialchars($fac['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="room" required placeholder="e.g., 101, Lab-A">
                    </div>
                </div>

                <button type="submit" name="add_schedule" class="btn btn-success" style="width: 100%;">✓ Add Schedule</button>
            </form>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Schedule Entry</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                
                <div class="form-group">
                    <label>Class</label>
                    <select name="class_id" id="edit_class_id" required>
                        <option value="">Select Class</option>
                        <?php mysqli_data_seek($classes, 0); while($class = mysqli_fetch_assoc($classes)): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Day</label>
                        <select name="day_of_week" id="edit_day_of_week" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="edit_start_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" id="edit_subject" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Faculty</label>
                        <select name="faculty_id" id="edit_faculty_id" required>
                            <?php mysqli_data_seek($faculty, 0); while($fac = mysqli_fetch_assoc($faculty)): ?>
                                <option value="<?php echo $fac['faculty_id']; ?>"><?php echo htmlspecialchars($fac['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="room" id="edit_room" required>
                    </div>
                </div>

                <button type="submit" name="update_schedule" class="btn btn-warning" style="width: 100%;">✓ Update Schedule</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function editSchedule(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.schedule_id;
            document.getElementById('edit_class_id').value = schedule.class_id;
            document.getElementById('edit_day_of_week').value = schedule.day_of_week;
            document.getElementById('edit_start_time').value = schedule.start_time;
            document.getElementById('edit_subject').value = schedule.subject;
            document.getElementById('edit_faculty_id').value = schedule.faculty_id;
            document.getElementById('edit_room').value = schedule.room;
            
            document.getElementById('editModal').classList.add('active');
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