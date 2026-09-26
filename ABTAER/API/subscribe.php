<?php
session_start();
header("Content-Type:application/json");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
include 'db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$plan = $input['plan'] ?? null;

$allowedPlans = ['free', 'standard', 'student_worker'];
if (!in_array($plan, $allowedPlans, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid plan.']);
    exit;
}


if ($plan == 'student_worker') {
    $check = $conn->prepare("SELECT id FROM subscription_requests 
                         WHERE user_id = ? AND requested_plan = 'student_worker' 
                         AND status = 'pending'");
    $check->bind_param('i', $user_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You already have a pending request.']);
        exit;
    }

    $Insert = $conn->prepare("INSERT INTO subscription_requests (user_id, requested_plan) 
                          VALUES (?, ?)");
    if (!$Insert) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error.']);
        exit;
    }
    $Insert->bind_param('is', $user_id, $plan);
    if ($Insert->execute()) {
        echo json_encode(['success' => true, 'pending' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit request']);
    }
} else {
    $stmt = $conn->prepare("UPDATE users SET membership_type = ? WHERE id = ?");
    $stmt->bind_param('si', $plan, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'pending' => false]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to subscribe.']);
    }
}
