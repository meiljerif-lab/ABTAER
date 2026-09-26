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
$id = $input['id'] ?? null;
$amount = $input['amount'] ?? null;

if ($id === null || $amount === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id or amount.']);
    exit;
}
if (!is_numeric($amount) || $amount < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid amount.']);
    exit;
}

$stmt = $conn->prepare("UPDATE expenses SET amount = ? WHERE user_id = ? AND id = ?");
$stmt->bind_param('dii',$amount,$user_id,$id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to edit']);
}

?>