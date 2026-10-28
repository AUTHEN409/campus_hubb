<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Campus Hub — Setup Check</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f4f8;padding:40px 20px}
.wrap{max-width:680px;margin:0 auto}
h1{font-size:24px;color:#1a202c;margin-bottom:6px}
.subtitle{color:#718096;margin-bottom:32px;font-size:15px}
.card{background:#fff;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 20px;background:#f7fafc;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:15px;color:#2d3748}
.check-row{display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-bottom:1px solid #f0f4f8}
.check-row:last-child{border-bottom:none}
.icon{font-size:18px;flex-shrink:0;margin-top:1px}
.check-label{font-weight:600;font-size:14px;color:#2d3748}
.check-detail{font-size:13px;color:#718096;margin-top:2px}
.ok{color:#38a169}
.warn{color:#d69e2e}
.fail{color:#e53e3e}
.fix-box{background:#fff5f5;border:1px solid #fed7d7;border-radius:8px;padding:14px 18px;margin-top:16px}
.fix-box h3{color:#c53030;font-size:14px;margin-bottom:8px}
.fix-box ol{padding-left:20px;color:#c53030;font-size:13px;line-height:1.8}
.action-box{text-align:center;padding:24px;background:#ebf8ff;border:1px solid #bee3f8;border-radius:10px}
.action-box h2{color:#2b6cb0;margin-bottom:8px}
.action-box p{color:#2c5282;font-size:14px;margin-bottom:16px}
.btn{display:inline-block;background:#4299e1;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:15px}
.btn:hover{background:#3182ce}
.delete-note{margin-top:20px;background:#fffbeb;border:1px solid #f6e05e;border-radius:8px;padding:14px 18px;font-size:13px;color:#744210}
</style>
</head>
<body>
<div class="wrap">
<h1>🎓 Campus Hub — Setup Check</h1>
<p class="subtitle">Run this page once to verify your InfinityFree deployment is ready.</p>

<?php
$allGood = true;

// ─── 1. PHP Version ───────────────────────────────────────
$phpOk = version_compare(PHP_VERSION, '7.4', '>=');
if (!$phpOk) $allGood = false;
?>

<div class="card">
<div class="card-header">System Requirements</div>

<div class="check-row">
  <div class="icon <?= $phpOk ? 'ok' : 'fail' ?>"><?= $phpOk ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label">PHP Version</div>
    <div class="check-detail">Running PHP <?= PHP_VERSION ?> — <?= $phpOk ? 'OK' : 'Requires PHP 7.4 or higher' ?></div>
  </div>
</div>

<?php
// ─── 2. MySQLi Extension ──────────────────────────────────
$mysqliOk = extension_loaded('mysqli');
if (!$mysqliOk) $allGood = false;
?>
<div class="check-row">
  <div class="icon <?= $mysqliOk ? 'ok' : 'fail' ?>"><?= $mysqliOk ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label">MySQLi Extension</div>
    <div class="check-detail"><?= $mysqliOk ? 'Available ✓' : 'Not found — contact InfinityFree support' ?></div>
  </div>
</div>

<?php
// ─── 3. Sessions ──────────────────────────────────────────
@session_start();
$_SESSION['setup_test'] = 'ok';
$sessionOk = isset($_SESSION['setup_test']);
if (!$sessionOk) $allGood = false;
?>
<div class="check-row">
  <div class="icon <?= $sessionOk ? 'ok' : 'fail' ?>"><?= $sessionOk ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label">PHP Sessions</div>
    <div class="check-detail"><?= $sessionOk ? 'Working ✓' : 'Sessions not working — check server config' ?></div>
  </div>
</div>

<?php
// ─── 4. File Upload Support ───────────────────────────────
$uploadOk = ini_get('file_uploads') == '1';
?>
<div class="check-row">
  <div class="icon <?= $uploadOk ? 'ok' : 'warn' ?>"><?= $uploadOk ? '✅' : '⚠️' ?></div>
  <div>
    <div class="check-label">File Uploads</div>
    <div class="check-detail">
      <?= $uploadOk ? 'Enabled ✓' : 'Disabled — file uploads will not work' ?><br>
      Max upload size: <?= ini_get('upload_max_filesize') ?> | Max post: <?= ini_get('post_max_size') ?>
    </div>
  </div>
</div>

</div><!-- /card -->

<div class="card">
<div class="card-header">Database Connection</div>
<?php
// ─── 5. Database ──────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
$dbOk = isset($conn) && $conn;

// Check if tables exist
$tablesOk = false;
$tableCount = 0;
$requiredTables = ['admin','students','faculty','departments','classes','attendance','tests','announcements','library_notes','schedule','events','notifications','remarks','mentoring','placements'];
$missingTables = [];

if ($dbOk) {
    foreach ($requiredTables as $table) {
        $r = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($r) > 0) {
            $tableCount++;
        } else {
            $missingTables[] = $table;
        }
    }
    $tablesOk = count($missingTables) === 0;
}
if (!$dbOk || !$tablesOk) $allGood = false;
?>
<div class="check-row">
  <div class="icon <?= $dbOk ? 'ok' : 'fail' ?>"><?= $dbOk ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label">Database Connection</div>
    <div class="check-detail">
      <?php if ($dbOk): ?>
        Connected to <strong><?= DB_NAME ?></strong> on <strong><?= DB_HOST ?></strong> ✓
      <?php else: ?>
        Connection failed. Open <code>config/database.php</code> and check your credentials.
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($dbOk): ?>
<div class="check-row">
  <div class="icon <?= $tablesOk ? 'ok' : 'fail' ?>"><?= $tablesOk ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label">Database Tables</div>
    <div class="check-detail">
      <?php if ($tablesOk): ?>
        All <?= count($requiredTables) ?> tables found ✓
      <?php else: ?>
        <?= $tableCount ?>/<?= count($requiredTables) ?> tables found.
        Missing: <strong><?= implode(', ', $missingTables) ?></strong><br>
        <em>Import <code>database.sql</code> via phpMyAdmin to create all tables.</em>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// ─── 6. Admin account ─────────────────────────────────────
$adminExists = false;
if ($dbOk && $tablesOk) {
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM admin");
    $row = mysqli_fetch_assoc($r);
    $adminExists = $row['c'] > 0;
}
?>
<div class="check-row">
  <div class="icon <?= $adminExists ? 'ok' : 'warn' ?>"><?= $adminExists ? '✅' : '⚠️' ?></div>
  <div>
    <div class="check-label">Admin Account</div>
    <div class="check-detail">
      <?= $adminExists ? 'Admin account exists ✓' : 'No admin found — make sure you imported database.sql' ?>
    </div>
  </div>
</div>
<?php endif; ?>
</div><!-- /card -->

<div class="card">
<div class="card-header">Upload Folders</div>
<?php
$uploadDirs = [
    'uploads/notes'         => 'Notes folder',
    'uploads/announcements' => 'Announcements folder',
    'uploads/profiles'      => 'Profile pictures folder',
];
foreach ($uploadDirs as $dir => $label):
    $path   = __DIR__ . '/' . $dir;
    $exists  = is_dir($path);
    $writable = $exists && is_writable($path);
    if (!$exists) { @mkdir($path, 0755, true); $exists = is_dir($path); $writable = is_writable($path); }
    if (!$writable) $allGood = false;
?>
<div class="check-row">
  <div class="icon <?= $writable ? 'ok' : 'fail' ?>"><?= $writable ? '✅' : '❌' ?></div>
  <div>
    <div class="check-label"><?= $label ?> (<code><?= $dir ?></code>)</div>
    <div class="check-detail">
      <?php if (!$exists): ?>
        Folder not found — please create it manually in File Manager
      <?php elseif (!$writable): ?>
        Folder exists but not writable — set permissions to 755 in File Manager
      <?php else: ?>
        Exists and writable ✓
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div><!-- /card -->

<?php if (!$allGood): ?>
<div class="fix-box">
  <h3>⚠️ Action Required — Fix the issues above, then:</h3>
  <ol>
    <li>Open <code>config/database.php</code> and fill in your InfinityFree DB credentials</li>
    <li>Go to phpMyAdmin → select your database → SQL tab → paste &amp; run <code>database.sql</code></li>
    <li>In File Manager, set <code>uploads/</code> folder permissions to <strong>755</strong></li>
    <li>Refresh this page to verify everything is green</li>
    <li>Delete <code>setup_check.php</code> from your server</li>
  </ol>
</div>
<?php else: ?>
<div class="action-box">
  <h2>🎉 Everything looks good!</h2>
  <p>Your Campus Hub is ready to use.<br>
     Log in with: <strong>admin@campushub.com</strong> / <strong>Admin@123</strong></p>
  <a href="index.php" class="btn">Go to Login Page →</a>
</div>
<div class="delete-note">
  🔒 <strong>Security tip:</strong> Delete <code>setup_check.php</code> from your server after confirming everything works.
  It exposes database connection details.
</div>
<?php endif; ?>

</div>
</body>
</html>
