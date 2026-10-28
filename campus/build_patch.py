import base64
import os

files_to_bundle = [
    'faculty_daily_report.php',
    'admin_daily_reports.php',
    'force_change_password.php',
    'db_migration.php',
    'dashboard.css'
]

b64_data = {}
for f in files_to_bundle:
    if os.path.exists(f):
        with open(f, 'rb') as file:
            b64_data[f] = base64.b64encode(file.read()).decode('utf-8')
    else:
        b64_data[f] = ''

php_script = f"""<?php
// apply_all_updates.php
// UPLOAD THIS SCRIPT TO YOUR HOSTING FILE MANAGER AND OPEN IT IN YOUR BROWSER

echo "<div style='font-family: monospace; background: #f4f4f4; padding: 20px; border-radius: 8px;'>";
echo "<h2>Applying Campus Hub Updates...</h2>";

function write_b64_file($filename, $b64) {{
    if (!empty($b64)) {{
        file_put_contents($filename, base64_decode($b64));
        echo "<p style='color: green;'>✔ Created file: <b>$filename</b></p>";
    }}
}}

// 1. Create missing files
write_b64_file('faculty_daily_report.php', '{b64_data['faculty_daily_report.php']}');
write_b64_file('admin_daily_reports.php', '{b64_data['admin_daily_reports.php']}');
write_b64_file('force_change_password.php', '{b64_data['force_change_password.php']}');
write_b64_file('db_migration.php', '{b64_data['db_migration.php']}');
write_b64_file('dashboard.css', '{b64_data['dashboard.css']}');

// 2. Update Admin Sidebars
$admin_files = glob("admin_*.php");
foreach ($admin_files as $f) {{
    $c = file_get_contents($f);
    if (strpos($c, 'admin_daily_reports.php') === false && strpos($c, '<aside class="sidebar">') !== false) {{
        $c = preg_replace('/<a href="admin_profile\.php"/i', '<a href="admin_daily_reports.php" class="nav-item"><span>📝</span> Faculty Reports</a>'."\\n                ".'<a href="admin_profile.php"', $c);
        file_put_contents($f, $c);
        echo "<p style='color: blue;'>✔ Updated Sidebar in: <b>$f</b></p>";
    }}
}}

// 3. Update Faculty Sidebars
$fac_files = glob("faculty_*.php");
foreach ($fac_files as $f) {{
    $c = file_get_contents($f);
    if (strpos($c, 'faculty_daily_report.php') === false && strpos($c, '<aside class="sidebar">') !== false) {{
        $c = preg_replace('/<a href="faculty_profile\.php"/i', '<a href="faculty_daily_report.php" class="nav-item"><span>📝</span> Daily Reports</a>'."\\n                ".'<a href="faculty_profile.php"', $c);
        file_put_contents($f, $c);
        echo "<p style='color: blue;'>✔ Updated Sidebar in: <b>$f</b></p>";
    }}
}}

// 4. Update index.php for force password redirect
if (file_exists('index.php')) {{
    $idx = file_get_contents('index.php');
    if (strpos($idx, 'force_change_password.php') === false) {{
        $replacement = <<<PHP
        if (password_verify(\\$password, \\$user['password'])) {{
            // Check if password change is required
            if (isset(\\$user['requires_password_change']) && \\$user['requires_password_change'] == 1) {{
                \\$_SESSION['pending_user_id'] = \\$user['student_id'];
                \\$_SESSION['pending_user_type'] = 'student';
                \\$_SESSION['pending_name'] = \\$user['name'];
                header("Location: force_change_password.php");
                exit();
            }}

PHP;
        $idx = str_replace("if (password_verify(\\$password, \\$user['password'])) {{", $replacement, $idx);
        file_put_contents('index.php', $idx);
        echo "<p style='color: purple;'>✔ Patched logic in: <b>index.php</b></p>";
    }}
}}

// 5. Update admin_manage_students.php for new password logic
if (file_exists('admin_manage_students.php')) {{
    $ams = file_get_contents('admin_manage_students.php');
    if (strpos($ams, 'requires_password_change') === false) {{
        $search = <<<PHP
        // Generate simple 6-character password
        \\$plain_password = generatePassword(6);
        \\$password = password_hash(\\$plain_password, PASSWORD_DEFAULT);

        \\$query = "INSERT INTO students (name, email, password, dept_id, class_id, phone, total_fee, fee_paid, fee_pending, enrollment_date)
                  VALUES ('\\$name', '\\$email', '\\$password', \\$dept_id, \\$class_id, '\\$phone', \\$total_fee, 0, \\$total_fee, CURDATE())";
PHP;
        $replace = <<<PHP
        // Extract first name and convert to lowercase for the initial password
        \\$first_name = strtolower(explode(' ', trim(\\$name))[0]);
        \\$plain_password = \\$first_name;
        \\$password = password_hash(\\$plain_password, PASSWORD_DEFAULT);

        // Set requires_password_change to 1 for new students
        \\$query = "INSERT INTO students (name, email, password, dept_id, class_id, phone, total_fee, fee_paid, fee_pending, enrollment_date, requires_password_change)
                  VALUES ('\\$name', '\\$email', '\\$password', \\$dept_id, \\$class_id, '\\$phone', \\$total_fee, 0, \\$total_fee, CURDATE(), 1)";
PHP;
        $ams = str_replace($search, $replace, $ams);
        file_put_contents('admin_manage_students.php', $ams);
        echo "<p style='color: purple;'>✔ Patched logic in: <b>admin_manage_students.php</b></p>";
    }}
}}

echo "<h3 style='color: #d97706;'>Almost Done!</h3>";
echo "<p>If you haven't yet applied the database migration, click below:</p>";
echo "<a href='db_migration.php' style='display:inline-block; padding: 10px 15px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Run Database Migration</a>";
echo "<p style='color: red; font-weight: bold; margin-top: 20px;'>⚠️ IMPORTANT: Delete this file (apply_all_updates.php) from your server when finished!</p>";
echo "</div>";
?>
"""

with open('apply_all_updates.php', 'w', encoding='utf-8') as f:
    f.write(php_script)

print("Generated apply_all_updates.php")
