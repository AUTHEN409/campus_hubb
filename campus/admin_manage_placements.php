<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

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

// Handle Add Placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_placement'])) {
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $job_role = mysqli_real_escape_string($conn, $_POST['job_role']);
    $job_type = mysqli_real_escape_string($conn, $_POST['job_type']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $eligibility = mysqli_real_escape_string($conn, $_POST['eligibility']);
    $package = mysqli_real_escape_string($conn, $_POST['package']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $application_deadline = $_POST['application_deadline'];
    
    $query = "INSERT INTO placements (company_name, job_role, job_type, description, eligibility, package, location, application_deadline, posted_by) 
              VALUES ('$company_name', '$job_role', '$job_type', '$description', '$eligibility', '$package', '$location', '$application_deadline', $admin_id)";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_manage_placements.php?success=Placement added successfully!");
        exit();
    } else {
        header("Location: admin_manage_placements.php?error=Failed to add placement");
        exit();
    }
}

// Handle Update Placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_placement'])) {
    $placement_id = intval($_POST['placement_id']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $job_role = mysqli_real_escape_string($conn, $_POST['job_role']);
    $job_type = mysqli_real_escape_string($conn, $_POST['job_type']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $eligibility = mysqli_real_escape_string($conn, $_POST['eligibility']);
    $package = mysqli_real_escape_string($conn, $_POST['package']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $application_deadline = $_POST['application_deadline'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE placements SET 
              company_name = '$company_name',
              job_role = '$job_role',
              job_type = '$job_type',
              description = '$description',
              eligibility = '$eligibility',
              package = '$package',
              location = '$location',
              application_deadline = '$application_deadline',
              status = '$status'
              WHERE placement_id = $placement_id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin_manage_placements.php?success=Placement updated successfully!");
        exit();
    } else {
        header("Location: admin_manage_placements.php?error=Failed to update placement");
        exit();
    }
}

// Handle Delete Placement
if (isset($_GET['delete'])) {
    $placement_id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM placements WHERE placement_id = $placement_id")) {
        header("Location: admin_manage_placements.php?success=Placement deleted successfully!");
        exit();
    }
}

// Handle Change Status
if (isset($_GET['change_status'])) {
    $placement_id = intval($_GET['change_status']);
    $new_status = mysqli_real_escape_string($conn, $_GET['status']);
    if (mysqli_query($conn, "UPDATE placements SET status = '$new_status' WHERE placement_id = $placement_id")) {
        header("Location: admin_manage_placements.php?success=Status updated successfully!");
        exit();
    }
}

// Get statistics
$total_placements = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements"));
$active_placements = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements WHERE status = 'active'"));
$full_time_jobs = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements WHERE job_type = 'Full-Time' AND status = 'active'"));
$internships = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM placements WHERE job_type = 'Internship' AND status = 'active'"));

// Get all placements
$placements_query = "SELECT p.*, a.name as posted_by_name 
                     FROM placements p 
                     LEFT JOIN admin a ON p.posted_by = a.admin_id 
                     ORDER BY p.posted_date DESC";
$placements = mysqli_query($conn, $placements_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Placements - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; margin-right: 8px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 28px; cursor: pointer; color: #6b7280; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .placement-card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; border-left: 5px solid #667eea; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .placement-card.expired { border-left-color: #9ca3af; opacity: 0.7; }
        .placement-card.closed { border-left-color: #ef4444; opacity: 0.8; }
        
        .placement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .company-name { font-size: 22px; font-weight: 700; color: #2d3748; margin-bottom: 5px; }
        .job-role { font-size: 18px; color: #667eea; font-weight: 600; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-fulltime { background: #c6f6d5; color: #22543d; }
        .badge-internship { background: #bee3f8; color: #2c5282; }
        .badge-parttime { background: #feebc8; color: #7c2d12; }
        .badge-active { background: #c6f6d5; color: #22543d; }
        .badge-expired { background: #e5e7eb; color: #374151; }
        .badge-closed { background: #fed7d7; color: #742a2a; }
        
        .placement-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; padding: 15px; background: #f7fafc; border-radius: 10px; margin: 15px 0; font-size: 14px; }
        .detail-item { display: flex; align-items: center; gap: 8px; }
        
        .placement-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0; }
        
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filter-select { padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; min-width: 150px; }
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
                <h1>Manage Placements</h1>
                <button onclick="openAddModal()" class="btn btn-primary">+ Add New Placement</button>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💼</div>
                    <div class="stat-details">
                        <h3>Total Placements</h3>
                        <p class="stat-value"><?php echo $total_placements; ?></p>
                        <span class="stat-label">All time</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3>Active Openings</h3>
                        <p class="stat-value"><?php echo $active_placements; ?></p>
                        <span class="stat-label">Currently open</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-details">
                        <h3>Full-Time Jobs</h3>
                        <p class="stat-value"><?php echo $full_time_jobs; ?></p>
                        <span class="stat-label">Active positions</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🚀</div>
                    <div class="stat-details">
                        <h3>Internships</h3>
                        <p class="stat-value"><?php echo $internships; ?></p>
                        <span class="stat-label">Active internships</span>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <label style="font-weight: 600; color: #2d3748;">Filter:</label>
                <select class="filter-select" id="statusFilter" onchange="filterPlacements()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="closed">Closed</option>
                </select>
                <select class="filter-select" id="typeFilter" onchange="filterPlacements()">
                    <option value="all">All Types</option>
                    <option value="Full-Time">Full-Time</option>
                    <option value="Internship">Internship</option>
                    <option value="Part-Time">Part-Time</option>
                </select>
                <button onclick="resetFilters()" class="btn btn-sm">Reset Filters</button>
            </div>

            <!-- Placements List -->
            <div id="placementsList">
                <?php while ($placement = mysqli_fetch_assoc($placements)): ?>
                    <?php
                    $badge_class = 'badge-fulltime';
                    if ($placement['job_type'] == 'Internship') $badge_class = 'badge-internship';
                    if ($placement['job_type'] == 'Part-Time') $badge_class = 'badge-parttime';
                    
                    $status_badge = 'badge-active';
                    if ($placement['status'] == 'expired') $status_badge = 'badge-expired';
                    if ($placement['status'] == 'closed') $status_badge = 'badge-closed';
                    ?>
                    <div class="placement-card <?php echo $placement['status']; ?>" 
                         data-status="<?php echo $placement['status']; ?>" 
                         data-type="<?php echo $placement['job_type']; ?>">
                        <div class="placement-header">
                            <div style="flex: 1;">
                                <div class="company-name">🏢 <?php echo htmlspecialchars($placement['company_name']); ?></div>
                                <div class="job-role"><?php echo htmlspecialchars($placement['job_role']); ?></div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $placement['job_type']; ?></span>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($placement['status']); ?></span>
                            </div>
                        </div>

                        <div class="placement-details">
                            <?php if ($placement['package']): ?>
                                <div class="detail-item">
                                    <span>💰</span>
                                    <div>
                                        <small style="color: #718096;">Package</small>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($placement['package']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($placement['location']): ?>
                                <div class="detail-item">
                                    <span>📍</span>
                                    <div>
                                        <small style="color: #718096;">Location</small>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($placement['location']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($placement['application_deadline']): ?>
                                <div class="detail-item">
                                    <span>⏰</span>
                                    <div>
                                        <small style="color: #718096;">Deadline</small>
                                        <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($placement['application_deadline'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-item">
                                <span>📅</span>
                                <div>
                                    <small style="color: #718096;">Posted</small>
                                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($placement['posted_date'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($placement['description']): ?>
                            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <strong style="color: #2d3748;">Description:</strong>
                                <div style="color: #4a5568; margin-top: 8px; line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars(substr($placement['description'], 0, 200))); ?>
                                    <?php if (strlen($placement['description']) > 200) echo '...'; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="placement-actions">
                            <button onclick="editPlacement(<?php echo htmlspecialchars(json_encode($placement)); ?>)" class="btn btn-warning btn-sm">
                                ✏️ Edit
                            </button>
                            
                            <?php if ($placement['status'] == 'active'): ?>
                                <a href="?change_status=<?php echo $placement['placement_id']; ?>&status=closed" class="btn btn-danger btn-sm">
                                    🔒 Close
                                </a>
                            <?php else: ?>
                                <a href="?change_status=<?php echo $placement['placement_id']; ?>&status=active" class="btn btn-success btn-sm">
                                    ✅ Activate
                                </a>
                            <?php endif; ?>
                            
                            <a href="?delete=<?php echo $placement['placement_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this placement?')">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </main>
    </div>

    <!-- Add Placement Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Placement</h2>
                <span class="close-btn" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" required placeholder="e.g., Google, Microsoft">
                    </div>
                    <div class="form-group">
                        <label>Job Role *</label>
                        <input type="text" name="job_role" required placeholder="e.g., Software Engineer">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Job Type *</label>
                        <select name="job_type" required>
                            <option value="Full-Time">Full-Time</option>
                            <option value="Internship">Internship</option>
                            <option value="Part-Time">Part-Time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Package</label>
                        <input type="text" name="package" placeholder="e.g., ₹8-12 LPA">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="e.g., Bangalore, Remote">
                    </div>
                    <div class="form-group">
                        <label>Application Deadline</label>
                        <input type="date" name="application_deadline" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Job Description</label>
                    <textarea name="description" placeholder="Describe the role, responsibilities, and requirements..."></textarea>
                </div>

                <div class="form-group">
                    <label>Eligibility Criteria</label>
                    <textarea name="eligibility" placeholder="List eligibility requirements (CGPA, branch, year, etc.)..."></textarea>
                </div>

                <button type="submit" name="add_placement" class="btn btn-success" style="width: 100%;">
                    ✅ Add Placement
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Placement Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Placement</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="placement_id" id="edit_placement_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" id="edit_company_name" required>
                    </div>
                    <div class="form-group">
                        <label>Job Role *</label>
                        <input type="text" name="job_role" id="edit_job_role" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Job Type *</label>
                        <select name="job_type" id="edit_job_type" required>
                            <option value="Full-Time">Full-Time</option>
                            <option value="Internship">Internship</option>
                            <option value="Part-Time">Part-Time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" id="edit_status" required>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Package</label>
                        <input type="text" name="package" id="edit_package">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="edit_location">
                    </div>
                </div>

                <div class="form-group">
                    <label>Application Deadline</label>
                    <input type="date" name="application_deadline" id="edit_deadline">
                </div>

                <div class="form-group">
                    <label>Job Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>Eligibility Criteria</label>
                    <textarea name="eligibility" id="edit_eligibility"></textarea>
                </div>

                <button type="submit" name="update_placement" class="btn btn-warning" style="width: 100%;">
                    ✏️ Update Placement
                </button>
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
        
        function editPlacement(placement) {
            document.getElementById('edit_placement_id').value = placement.placement_id;
            document.getElementById('edit_company_name').value = placement.company_name;
            document.getElementById('edit_job_role').value = placement.job_role;
            document.getElementById('edit_job_type').value = placement.job_type;
            document.getElementById('edit_status').value = placement.status;
            document.getElementById('edit_package').value = placement.package || '';
            document.getElementById('edit_location').value = placement.location || '';
            document.getElementById('edit_deadline').value = placement.application_deadline || '';
            document.getElementById('edit_description').value = placement.description || '';
            document.getElementById('edit_eligibility').value = placement.eligibility || '';
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function filterPlacements() {
            const statusFilter = document.getElementById('statusFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const cards = document.querySelectorAll('.placement-card');
            
            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                const type = card.getAttribute('data-type');
                
                const statusMatch = statusFilter === 'all' || status === statusFilter;
                const typeMatch = typeFilter === 'all' || type === typeFilter;
                
                if (statusMatch && typeMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function resetFilters() {
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('typeFilter').value = 'all';
            filterPlacements();
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