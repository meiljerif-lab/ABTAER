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

$conn->begin_transaction();
$stmt1 = $conn->prepare('DELETE FROM expenses WHERE user_id = ?');
$stmt1->bind_param('i', $user_id);
$stmt1->execute();


$stmt2 = $conn->prepare('UPDATE category_budgets SET amount = 0 WHERE user_id = ?');
$stmt2->bind_param('i', $user_id);
$stmt2->execute();


$stmt3 = $conn->prepare('UPDATE period_budgets SET total = 0 WHERE user_id = ?');
$stmt3->bind_param('i', $user_id);
$stmt3->execute();


$stmt4 = $conn->prepare('UPDATE savings SET balance = 0 WHERE user_id = ?');
$stmt4->bind_param('i', $user_id);
$stmt4->execute();


$conn->commit();
echo json_encode(['success' => true]);
