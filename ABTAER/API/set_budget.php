<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
include 'db_connect.php';



$user_id = $_SESSION['user_id'];
$input  = json_decode(file_get_contents('php://input'), true);
$total  = $input['total'] ?? null;
$period = $input['period'] ?? [];

if (!is_numeric($total) || $total < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid budget amount.']);
    exit;
}

if (!in_array($period, ['day', 'week', 'month'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid period.']);
    exit;
}

// TODO(PHP): upsert into DB, e.g.:
// $stmt = $pdo->prepare('INSERT INTO budgets (user_id, period, total) VALUES (?, ?, ?)
//                         ON DUPLICATE KEY UPDATE total = VALUES(total)');
// $stmt->execute([$user_id, $period, $total]);
$stmt = $conn->prepare('INSERT INTO period_budgets (user_id, type, total) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE total = VALUES(total)');
$stmt->bind_param('isd', $user_id, $period, $total);
$stmt->execute();
echo json_encode(['success' => true, 'total' => (float) $total, 'period' => $period]);
