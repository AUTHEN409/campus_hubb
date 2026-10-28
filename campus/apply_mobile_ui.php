<?php
// apply_mobile_ui.php
// UPLOAD THIS SCRIPT TO YOUR HOSTING FILE MANAGER AND OPEN IT IN YOUR BROWSER

$files = glob("*.php");
$count = 0;

$topbarHtml = <<<HTML
<!-- ══ MOBILE TOP BAR (dashboard only) ══ -->
<div class="mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-app-name">Campus Hub</span>
    </div>
    <span class="mobile-user-chip"><?php echo htmlspecialchars(\$admin_name ?? \$faculty_name ?? \$student_name ?? 'User'); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
HTML;

$jsScript = <<<HTML
<script>
/* ── Mobile sidebar toggle ─────────── */
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
HTML;

echo "<div style='font-family: monospace; background: #f4f4f4; padding: 20px; border-radius: 8px;'>";
echo "<h2>Applying Mobile UI Updates...</h2>";

foreach ($files as $file) {
    if ($file === 'apply_mobile_ui.php' || $file === 'db_migration.php') continue;
    
    $content = file_get_contents($file);
    
    // Only target files that have the dashboard-container layout
    if (strpos($content, '<div class="dashboard-container">') !== false) {
        // Ensure we haven't already added the mobile topbar
        if (strpos($content, 'class="mobile-topbar"') === false) {
            
            // Inject Topbar right above dashboard-container
            $content = preg_replace('/<div class="dashboard-container">/i', $topbarHtml . "\n<div class=\"dashboard-container\">", $content);
            
            // Inject JS script right before closing body tag
            $content = preg_replace('/<\/body>/i', $jsScript . "\n</body>", $content);
            
            if (file_put_contents($file, $content)) {
                echo "<p style='color: green;'>✔ Updated: <b>$file</b></p>";
                $count++;
            } else {
                echo "<p style='color: red;'>✘ Error writing to: <b>$file</b> (Check file permissions)</p>";
            }
        } else {
            echo "<p style='color: gray;'>○ Skipped (Already has mobile UI): <b>$file</b></p>";
        }
    }
}

echo "<h3>Total files successfully updated: $count</h3>";
echo "<p style='color: red; font-weight: bold;'>⚠️ IMPORTANT: Delete this file (apply_mobile_ui.php) from your server now to prevent it from being run accidentally again!</p>";
echo "</div>";
?>
