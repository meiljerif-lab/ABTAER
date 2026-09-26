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

$amount   = $input['amount']   ?? null;
$category = $input['category'] ?? null;

if (!is_numeric($amount) || $amount <= 0 || !$category) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid amount or category.']);
    exit;
}
$stmt = $conn->prepare('SELECT id FROM categories WHERE key_name = ?');
$stmt->bind_param('s', $category);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows === 0){
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid category.']);
    exit;
}
$rows = $result->fetch_assoc();
$categoryId = $rows['id'];

$stmt = $conn->prepare('INSERT INTO expenses (user_id, category_id, amount, created_at) VALUES (?,?,?,NOW())');
$stmt->bind_param('iid', $user_id, $categoryId, $amount);
$result = $stmt->execute();

    echo json_encode([
        'success' => $result,
        'expense' => [
            'category'   => $category,
            'amount'     => (float) $amount,
            'created_at' => date('c'),
        ],
    ]);
