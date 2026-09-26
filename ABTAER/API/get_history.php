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
$stmt = $conn->prepare("SELECT expenses.amount, categories.name AS category, expenses.created_at AS created_at FROM expenses JOIN categories ON expenses.category_id = categories.id WHERE expenses.user_id = ? ORDER BY expenses.created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['expenses' => $expenses]);
