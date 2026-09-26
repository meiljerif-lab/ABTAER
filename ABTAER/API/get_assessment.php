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
$period = $_GET['period'] ?? 'month';
$period = in_array($period, ['week', 'month'], true) ? $period : 'month';
if($period == 'week'){
    $dateCondition = "YEARWEEK(created_at) = YEARWEEK(CURDATE())";
}else{
    $dateCondition = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
}
$stmt=$conn->prepare('SELECT categories.id, categories.key_name, SUM(expenses.amount) AS spent FROM categories LEFT JOIN expenses ON categories.id = expenses.category_id AND expenses.user_id = ? AND ' . $dateCondition . ' GROUP BY categories.id');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categoryColors = [
    'utilities' => '#6B5CA5',
    'transportation' => '#2F6F4E',
    'grocery' => '#C7862B',
    'necessities' => '#3C7A89',
    'projects' => '#B8492E',
];
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'name' => ucfirst($row['key_name']),
        'amount' => (float) ($row['spent'] ?? 0),
        'color' => $categoryColors[$row['key_name']] ?? '#6B5CA5', 
    ];
}

if ($period === 'week') {
    $stmt = $conn->prepare('SELECT WEEKDAY(created_at) AS day_of_week, SUM(amount) AS total_spent FROM expenses WHERE user_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE()) GROUP BY day_of_week');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $values = [0, 0, 0, 0, 0, 0, 0];
    while ($row = $result->fetch_assoc()) {
        $values[$row['day_of_week']] = (float) $row['total_spent'];
    }

    $series = [
        'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'values' => $values,
    ];
} else {
   $stmt = $conn->prepare('SELECT LEAST(3, FLOOR((DAY(created_at) - 1) / 7)) AS week_bucket, SUM(amount) AS total_spent FROM expenses WHERE user_id = ? AND ' . $dateCondition . ' GROUP BY week_bucket');
   $stmt->bind_param('i', $user_id);
   $stmt->execute();
   $result = $stmt->get_result();

   $values = [0, 0, 0, 0];
   while ($row = $result->fetch_assoc()) {
       $values[$row['week_bucket']] = (float) $row['total_spent'];
   }

   $series = [
       'labels' => ['1st Week', '2nd Week', '3rd Week', '4th Week'],
       'values' => $values,
   ];
}

echo json_encode(['categories' => $categories, 'series' => $series]);
