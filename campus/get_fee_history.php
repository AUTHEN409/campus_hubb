<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$student_id = intval($_GET['student_id'] ?? 0);
if (!$student_id) { echo json_encode([]); exit(); }

$res = mysqli_query($conn,
    "SELECT fp.*, DATE_FORMAT(fp.payment_date, '%d %b %Y') AS payment_date
     FROM fee_payments fp
     WHERE fp.student_id = $student_id
     ORDER BY fp.payment_date DESC, fp.created_at DESC");

$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

header('Content-Type: application/json');
echo json_encode($rows);