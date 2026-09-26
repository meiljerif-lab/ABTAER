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
$input      = json_decode(file_get_contents('php://input'), true);
$categories = $input['categories'] ?? null;

$allowedKeys = ['utilities', 'transportation', 'grocery', 'necessities', 'projects'];

if (!is_array($categories) || empty($categories)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing categories.']);
    exit;
}

$total = 0;
$clean = [];
foreach ($categories as $cat) {
    $key    = $cat['key']    ?? null;
    $amount = $cat['amount'] ?? null;

    if (!in_array($key, $allowedKeys, true) || !is_numeric($amount) || $amount < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid category or amount for '{$key}'."]);
        exit;
    }

    $amount = (float) $amount;
    $total += $amount;
    $clean[] = ['key' => $key, 'amount' => $amount];
}

foreach ($clean as $c) {
    // Step 1: turn "utilities" into whatever id it has in the categories table
    $lookup = $conn->prepare('SELECT id FROM categories WHERE key_name = ?');
    $lookup->bind_param('s', $c['key']);
    $lookup->execute();
    $row = $lookup->get_result()->fetch_assoc();

    if (!$row) {
        continue; // no matching category found — skip this one, move to the next
    }

    $categoryId = $row['id'];

    // Step 2: save (or update) this category's budget amount using that id
    $save = $conn->prepare('INSERT INTO category_budgets (user_id, category_id, amount)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount)');
    $save->bind_param('iid', $user_id, $categoryId, $c['amount']);
    $save->execute();
}

echo json_encode(['success' => true, 'categories' => $clean, 'total' => $total]);
