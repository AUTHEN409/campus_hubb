<?php
require_once 'database.php';

// 1. Create daily_reports table
$sql1 = "CREATE TABLE IF NOT EXISTS daily_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    report_date DATE NOT NULL,
    content TEXT NOT NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql1)) {
    echo "Table daily_reports created successfully.\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}

// 2. Add requires_password_change to students
$sql2 = "ALTER TABLE students ADD COLUMN requires_password_change TINYINT(1) DEFAULT 0";
try {
    if (mysqli_query($conn, $sql2)) {
        echo "Column requires_password_change added successfully.\n";
    }
} catch (mysqli_sql_exception $e) {
    // If it's a duplicate column error, that's fine
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column requires_password_change already exists (Database is up to date).\n";
    } else {
        echo "Error adding column: " . $e->getMessage() . "\n";
    }
}
?>
