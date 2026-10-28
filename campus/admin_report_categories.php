<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';
$success = "";
$error = "";

// Auto-create report_categories table just in case
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS report_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
try {
    mysqli_query($conn, "ALTER TABLE daily_reports ADD COLUMN category_id INT NULL AFTER content");
    mysqli_query($conn, "ALTER TABLE daily_reports ADD CONSTRAINT fk_daily_reports_category FOREIGN KEY (category_id) REFERENCES report_categories(category_id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Column already exists, ignore the exception
}

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
        if (!empty($category_name)) {
            $query = "INSERT INTO report_categories (category_name) VALUES ('$category_name')";
            if (mysqli_query($conn, $query)) {
                $success = "Category added successfully!";
            } else {
                $error = "Failed to add category.";
            }
        } else {
            $error = "Category name cannot be empty.";
        }
    } elseif (isset($_POST['edit_category'])) {
        $category_id = intval($_POST['category_id']);
        $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
        if (!empty($category_name)) {
            $query = "UPDATE report_categories SET category_name = '$category_name' WHERE category_id = $category_id";
            if (mysqli_query($conn, $query)) {
                $success = "Category updated successfully!";
            } else {
                $error = "Failed to update category.";
            }
        } else {
            $error = "Category name cannot be empty.";
        }
    } elseif (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        $query = "DELETE FROM report_categories WHERE category_id = $category_id";
        if (mysqli_query($conn, $query)) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Failed to delete category.";
        }
    }
}

// Fetch all categories
$categories_query = "SELECT * FROM report_categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Report Categories - Admin</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .card { background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .alert-success { background: #c6f6d5; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #48bb78; }
        .alert-error { background: #fed7d7; color: #742a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f56565; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn-submit { background: #667eea; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn-submit:hover { background: #5a67d8; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .data-table th { background: #f8fafc; font-weight: 600; color: #4a5568; }
        .btn-edit { background: #f6ad55; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-right: 5px; }
        .btn-edit:hover { background: #dd6b20; }
        .btn-delete { background: #fc8181; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-delete:hover { background: #c53030; }
        .action-btns { display: flex; gap: 5px; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 20px; color: #2d3748; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #a0aec0; }
        .close-modal:hover { color: #4a5568; }
        .btn-back { display: inline-block; background: #e2e8f0; color: #4a5568; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; margin-bottom: 20px; transition: background 0.2s; }
        .btn-back:hover { background: #cbd5e0; }
    </style>
</head>
<body>
<!-- MOBILE TOP BAR -->
<div class="mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-app-name">Campus Hub</span>
    </div>
    <span class="mobile-user-chip"><?php echo htmlspecialchars($admin_name); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="logo">
            <h2>Campus Hub</h2>
            <p style="font-size:12px;opacity:.8;margin-top:5px;">Admin Panel</p>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item"><span>📊</span> Dashboard</a>
            <a href="admin_manage_students.php" class="nav-item"><span>👨‍🎓</span> Manage Students</a>
            <a href="admin_manage_faculty.php" class="nav-item"><span>👨‍🏫</span> Manage Faculty</a>
            <a href="admin_manage_schedule.php" class="nav-item"><span>📅</span> Manage Schedule</a>
            <a href="admin_mentoring.php" class="nav-item"><span>🤝</span> Mentoring</a>
            <a href="admin_approve_announcements.php" class="nav-item"><span>📢</span> Announcements</a>
            <a href="admin_manage_events.php" class="nav-item"><span>🎉</span> Manage Events</a>
            <a href="admin_manage_placements.php" class="nav-item"><span>💼</span> Placements</a>
            <a href="admin_send_notifications.php" class="nav-item"><span>🔔</span> Send Notifications</a>
            <a href="admin_daily_reports.php" class="nav-item active"><span>📝</span> Faculty Reports</a>
            <a href="admin_profile.php" class="nav-item"><span>👤</span> Profile</a>
            <a href="logout.php" class="nav-item logout"><span>🚪</span> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <a href="admin_daily_reports.php" class="btn-back">← Back to Reports</a>
        <header class="header">
            <h1>Manage Report Categories</h1>
        </header>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Add New Category</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="category_name" required placeholder="e.g. Teaching, Research, Exam Duty">
                </div>
                <button type="submit" name="add_category" class="btn-submit">Add Category</button>
            </form>
        </div>

        <div class="card">
            <h2>Existing Categories</h2>
            <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <tr>
                                    <td><?php echo $cat['category_id']; ?></td>
                                    <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                    <td class="action-btns">
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $cat['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['category_name'])); ?>')">Edit</button>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this category?');" style="display:inline;">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                            <button type="submit" name="delete_category" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No categories found. Add your first category above.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Category</h2>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="category_name" id="edit_category_name" required>
            </div>
            <button type="submit" name="edit_category" class="btn-submit" style="width: 100%;">Update Category</button>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, name) {
        document.getElementById('edit_category_id').value = id;
        document.getElementById('edit_category_name').value = name;
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Sidebar Mobile
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const burger = document.getElementById('hamburgerBtn');

    function toggleSidebar() {
        if(sidebar) sidebar.classList.toggle('open');
        if(overlay) overlay.classList.toggle('active');
        if(burger) burger.classList.toggle('open');
        document.body.classList.toggle('sidebar-open');
    }
    
    function closeSidebar() {
        if(sidebar) sidebar.classList.remove('open');
        if(overlay) overlay.classList.remove('active');
        if(burger) burger.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }
</script>
</body>
</html>
