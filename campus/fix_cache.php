<?php
// fix_cache.php
$files = glob("*.php");
$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace href="dashboard.css" or href="dashboard.css?v=2" with a dynamic timestamp
    if (preg_match('/href="dashboard\.css(\?v=[0-9]+)?"/', $content)) {
        $content = preg_replace('/href="dashboard\.css(\?v=[0-9]+)?"/', 'href="dashboard.css?v=' . time() . '"', $content);
        file_put_contents($file, $content);
        $count++;
    }
}
echo "<h2 style='color:green;'>Forced CSS cache bypass on $count files!</h2>";
echo "<p>Please go back to the admin panel and try clicking the buttons now.</p>";
?>
