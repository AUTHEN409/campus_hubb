<?php
// ============================================================
//  CAMPUS HUB — DATABASE CONFIGURATION
//  ✏️  Edit the 4 values below, then upload. That's it!
// ============================================================
//
//  WHERE TO FIND THESE VALUES:
//  1. Log in at https://app.infinityfree.com
//  2. Go to your hosting account → MySQL Databases
//  3. Create a database and note the details shown
//
// ============================================================

define('DB_HOST', 'sql110.bytecluster.com');
//        ^ Find your exact host in cpanel → MySQL Databases
//          It will be something like sql200, sql201, sql202, etc.

define('DB_USER', 'if0_41237339');
//        ^ Your MySQL username shown in cpanel (starts with if0_)

define('DB_PASS', 'sicacollege78');
//        ^ The password you set when creating the database user

define('DB_NAME', 'if0_41237339_sicaindore');
//        ^ Your database name (shown in cpanel MySQL Databases list)

// ── Nothing below this line needs changing ──────────────────

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("
    <div style='font-family:Arial,sans-serif;max-width:500px;margin:80px auto;padding:32px;
                background:#fff3cd;border:1px solid #ffc107;border-radius:8px;text-align:center'>
        <h2 style='color:#856404'>Database Connection Failed</h2>
        <p style='color:#856404'>Check your credentials in <code>database.php</code>.</p>
        <p style='color:#856404;font-size:13px'>Error: " . mysqli_connect_error() . "</p>
    </div>");
}

mysqli_set_charset($conn, "utf8mb4");
date_default_timezone_set('Asia/Kolkata');
?>
