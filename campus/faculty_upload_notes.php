<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'faculty') {
    header("Location: index.php");
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['name'];

$success_message = "";
$error_message = "";

// Get classes taught by this faculty
$classes_query = "SELECT c.* 
                  FROM faculty_classes fc
                  JOIN classes c ON fc.class_id = c.class_id
                  WHERE fc.faculty_id = $faculty_id
                  ORDER BY c.class_name";
$classes_result = mysqli_query($conn, $classes_query);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_note'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $class_id = $_POST['class_id'];
    
    // Handle file upload
    if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] == 0) {
        $file = $_FILES['note_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ppt', 'pptx');
        
        if (in_array($file_ext, $allowed)) {
            if ($file_size < 10485760) { // 10MB max
                // Create unique filename
                $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = 'uploads/notes/' . $new_filename;
                
                // Move file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $file_path = 'uploads/notes/' . $new_filename;
                    
                    // Insert into database
                    $insert_query = "INSERT INTO library_notes (title, subject, class_id, file_path, uploaded_by) 
                                    VALUES ('$title', '$subject', $class_id, '$file_path', $faculty_id)";
                    
                    if (mysqli_query($conn, $insert_query)) {
                        $success_message = "Note uploaded successfully!";
                    } else {
                        $error_message = "Database error: " . mysqli_error($conn);
                        unlink($upload_path); // Delete uploaded file if DB insert fails
                    }
                } else {
                    $error_message = "Failed to upload file. Please check folder permissions.";
                }
            } else {
                $error_message = "File size too large. Maximum 10MB allowed.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX, PPT, PPTX";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $note_id = $_GET['delete'];
    
    // Get file path
    $get_query = "SELECT file_path FROM library_notes WHERE note_id = $note_id AND uploaded_by = $faculty_id";
    $result = mysqli_query($conn, $get_query);
    
    if (mysqli_num_rows($result) > 0) {
        $note = mysqli_fetch_assoc($result);
        $file_path = '../' . $note['file_path'];
        
        // Delete from database
        $delete_query = "DELETE FROM library_notes WHERE note_id = $note_id";
        if (mysqli_query($conn, $delete_query)) {
            // Delete file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $success_message = "Note deleted successfully!";
        } else {
            $error_message = "Failed to delete note.";
        }
    }
}

// Get uploaded notes by this faculty
$notes_query = "SELECT ln.*, c.class_name 
                FROM library_notes ln 
                JOIN classes c ON ln.class_id = c.class_id 
                WHERE ln.uploaded_by = $faculty_id 
                ORDER BY ln.upload_date DESC";
$notes_result = mysqli_query($conn, $notes_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Notes - Campus Hub</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            width: 100%;
            padding: 40px;
            border: 3px dashed #cbd5e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        
        .file-input:hover {
            border-color: #667eea;
            background: #edf2f7;
        }
        
        .file-input input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-label {
            cursor: pointer;
            color: #4a5568;
        }
        
        .file-label strong {
            color: #667eea;
        }
        
        .upload-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
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
        
        .note-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
        }
        
        .view-btn {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .view-btn:hover {
            background: #90cdf4;
        }
        
        .delete-btn {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .delete-btn:hover {
            background: #fc8181;
        }
        
        .selected-file {
            margin-top: 10px;
            padding: 10px;
            background: #edf2f7;
            border-radius: 6px;
            color: #2d3748;
            font-size: 14px;
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
                <p style="font-size: 12px; opacity: 0.8; margin-top: 5px;">Faculty Panel</p>
            </div>
            <nav class="nav-menu">
                <a href="faculty_dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="faculty_mark_attendance.php" class="nav-item">
                    <span>📅</span> Mark Attendance
                </a>
                <a href="faculty_enter_marks.php" class="nav-item">
                    <span>📝</span> Enter Marks
                </a>
                <a href="faculty_upload_notes.php" class="nav-item active">
                    <span>📚</span> Upload Notes
                </a>
                <a href="faculty_create_announcement.php" class="nav-item">
                    <span>📢</span> Announcements
                </a>
                <a href="faculty_add_remarks.php" class="nav-item">
                    <span>💬</span> Add Remarks
                </a>
                <a href="faculty_view_students.php" class="nav-item">
                    <span>👥</span> View Students
                </a>
                <a href="faculty_profile.php" class="nav-item">
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
                <h1>Upload Study Notes</h1>
                <div class="user-info">
                    <span>Faculty Panel</span>
                </div>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Upload New Note</h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Note Title</label>
                        <input type="text" name="title" placeholder="e.g., Chapter 5 - Data Structures" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" placeholder="e.g., Computer Science" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Class</label>
                        <select name="class_id" required>
                            <option value="">-- Choose a class --</option>
                            <?php 
                            mysqli_data_seek($classes_result, 0);
                            while ($class = mysqli_fetch_assoc($classes_result)): 
                            ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload File (PDF, Images, Documents)</label>
                        <div class="file-input-wrapper">
                            <div class="file-input" onclick="document.getElementById('fileInput').click()">
                                <input type="file" id="fileInput" name="note_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx" required onchange="showFileName(this)">
                                <label class="file-label">
                                    📎 <strong>Click to select file</strong> or drag and drop<br>
                                    <small>Allowed: PDF, JPG, PNG, DOC, DOCX, PPT, PPTX (Max 10MB)</small>
                                </label>
                            </div>
                            <div id="selectedFile" class="selected-file" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <button type="submit" name="upload_note" class="upload-btn">
                        📤 Upload Note
                    </button>
                </form>
            </div>

            <!-- Uploaded Notes -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px;">Your Uploaded Notes</h2>
                
                <?php if (mysqli_num_rows($notes_result) > 0): ?>
                    <div class="notes-grid">
                        <?php while ($note = mysqli_fetch_assoc($notes_result)): ?>
                            <div class="note-card">
                                <div class="note-icon">📄</div>
                                
                                <div class="note-title">
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </div>
                                
                                <div class="note-meta">
                                    <div>📚 <?php echo htmlspecialchars($note['subject']); ?></div>
                                    <div>🎓 <?php echo htmlspecialchars($note['class_name']); ?></div>
                                    <div>📅 <?php echo date('M d, Y', strtotime($note['upload_date'])); ?></div>
                                </div>
                                
                                <div class="note-actions">
                                    <a href="<?php echo htmlspecialchars($note['file_path']); ?>" 
                                       class="action-btn view-btn" 
                                       target="_blank">
                                        👁️ View
                                    </a>
                                    <a href="?delete=<?php echo $note['note_id']; ?>" 
                                       class="action-btn delete-btn"
                                       onclick="return confirm('Are you sure you want to delete this note?')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">You haven't uploaded any notes yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function showFileName(input) {
            const fileDisplay = document.getElementById('selectedFile');
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
                fileDisplay.innerHTML = `✅ Selected: <strong>${fileName}</strong> (${fileSize} MB)`;
                fileDisplay.style.display = 'block';
            }
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