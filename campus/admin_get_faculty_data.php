<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$fid = intval($_GET['faculty_id'] ?? 0);
if (!$fid) { echo json_encode([]); exit(); }

$f = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT faculty_id, name, email, employee_id, phone FROM faculty WHERE faculty_id=$fid"));

if (!$f) { echo json_encode([]); exit(); }

// Get departments
$dept_ids = [];
$dr = mysqli_query($conn, "SELECT dept_id FROM faculty_departments WHERE faculty_id=$fid");
while ($d = mysqli_fetch_assoc($dr)) $dept_ids[] = (int)$d['dept_id'];

// Fallback: if faculty_departments empty, use faculty.dept_id
if (empty($dept_ids)) {
    $pr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dept_id FROM faculty WHERE faculty_id=$fid"));
    if ($pr && $pr['dept_id']) $dept_ids[] = (int)$pr['dept_id'];
}

// Get classes
$class_ids = [];
$cr = mysqli_query($conn, "SELECT class_id FROM faculty_classes WHERE faculty_id=$fid");
while ($c = mysqli_fetch_assoc($cr)) $class_ids[] = (int)$c['class_id'];

$f['dept_ids']  = $dept_ids;
$f['class_ids'] = $class_ids;

header('Content-Type: application/json');
echo json_encode($f);