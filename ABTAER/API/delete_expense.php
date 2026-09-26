<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
include 'db_connect.php';
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$expenseId = $input['id'] ?? null;

$stmt = $conn->prepare("DELETE FROM expenses WHERE expenses.user_id = ? AND expenses.id = ?");
$stmt->bind_param('ii',$user_id,$expenseId);
$stmt->execute();
if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Expense not found.']);
}

?>