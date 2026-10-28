<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? $_GET['success'] : '';

// Handle Add Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    
    $query = "INSERT INTO events (title, description, event_date, event_time, venue, created_by) 
              VALUES ('$title', '$description', '$event_date', '$event_time', '$venue', $admin_id)";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_manage_events.php?success=Event created successfully!");
        exit();
    }
}

// Handle Delete Event
if (isset($_GET['delete'])) {
    $event_id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM events WHERE event_id = $event_id")) {
        header("Location: admin_manage_events.php?success=Event deleted successfully!");
        exit();
    }
}

// Get all events
$events_query = "SELECT * FROM events ORDER BY event_date DESC";
$events = mysqli_query($conn, $events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 28px; cursor: pointer; color: #6b7280; }
        .event-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #667eea; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .event-card.past { opacity: 0.7; border-left-color: #9ca3af; }
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
                <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
                 <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
                <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
                <a href="admin_manage_events.php" class="nav-item active"><span>🎉</span> Manage Events</a>
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
                <h1>Manage Events</h1>
                <button onclick="openModal()" class="btn btn-primary">+ Create New Event</button>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php 
            $today = date('Y-m-d');
            while ($event = mysqli_fetch_assoc($events)): 
                $isPast = $event['event_date'] < $today;
            ?>
                <div class="event-card <?php echo $isPast ? 'past' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div>
                            <h3 style="color: #2d3748; margin-bottom: 8px; font-size: 20px;">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h3>
                            <div style="display: flex; gap: 20px; color: #6b7280; font-size: 14px;">
                                <span>📅 <?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                                <?php if ($event['event_time']): ?>
                                    <span>🕐 <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                <?php endif; ?>
                                <?php if ($event['venue']): ?>
                                    <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge badge-<?php echo $isPast ? 'secondary' : 'success'; ?>">
                            <?php echo $isPast ? 'Past' : 'Upcoming'; ?>
                        </span>
                    </div>

                    <?php if ($event['description']): ?>
                        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 15px; color: #374151; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <div style="text-align: right;">
                        <a href="?delete=<?php echo $event['event_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this event?')">Delete Event</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </main>
    </div>

    <!-- Add Event Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Event</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="title" required placeholder="e.g., Annual Sports Day">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Event details..."></textarea>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Event Time</label>
                    <input type="time" name="event_time">
                </div>
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" placeholder="e.g., Main Auditorium">
                </div>
                <button type="submit" name="add_event" class="btn btn-primary" style="width: 100%;">Create Event</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('addModal').classList.add('active'); }
        function closeModal() { document.getElementById('addModal').classList.remove('active'); }
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