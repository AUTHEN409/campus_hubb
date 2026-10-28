$dir = "d:\campus"
$files = Get-ChildItem -Path $dir -Filter "*.php"

$topbarHtml = @"
<!-- ══ MOBILE TOP BAR (dashboard only) ══ -->
<div class="mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-app-name">Campus Hub</span>
    </div>
    <span class="mobile-user-chip"><?php echo htmlspecialchars(`$admin_name ?? `$faculty_name ?? `$student_name ?? 'User'); ?></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
"@

$jsScript = @"
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
"@

$count = 0
foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    
    # Check if it has dashboard-container
    if ($content -match '<div class="dashboard-container">') {
        # Check if it already has mobile-topbar
        if (-not ($content -match 'class="mobile-topbar"')) {
            # Inject topbar
            $content = $content -replace '(?i)<div class="dashboard-container">', "$topbarHtml`n<div class=`"dashboard-container`">"
            
            # Inject JS before </body>
            $content = $content -replace '(?i)</body>', "$jsScript`n</body>"
            
            # Save using standard UTF-8 without BOM
            $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
            [System.IO.File]::WriteAllText($file.FullName, $content, $utf8NoBom)
            Write-Host "Updated $($file.Name)"
            $count++
        } else {
            Write-Host "Skipped $($file.Name) - Already has mobile UI"
        }
    }
}
Write-Host "Total files updated: $count"
