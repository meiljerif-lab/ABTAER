<?php
define('ROLLOVER_SECRET', '9Ko4-LP6QzSzQlB9fefFSXHLPuW5oj_SRVjHGw73XV4');

if (($_GET['token'] ?? '') !== ROLLOVER_SECRET) {
    http_response_code(403);
    exit;
}

include '../API/db_connect.php';
$users = $conn->query('SELECT id FROM users');
while ($user = $users->fetch_assoc()) {
    $userId = $user['id'];
    $stmt = $conn->prepare("SELECT total FROM period_budgets WHERE user_id = ? AND type = 'day'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $dayBudget = $row ? (float) $row['total'] : 0;

    $stmt2 = $conn->prepare("SELECT SUM(amount) AS total_spent FROM expenses WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND user_id = ?");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $daySpent = $row2 && $row2['total_spent'] !== null ? (float) $row2['total_spent'] : 0;
    
    $diff = $dayBudget - $daySpent;

    $stmt3 = $conn->prepare('UPDATE savings SET balance = balance + ? WHERE user_id = ?');
    $stmt3->bind_param('di', $diff, $userId);
    $stmt3->execute();
}
   
echo "Rollover complete.\n";
