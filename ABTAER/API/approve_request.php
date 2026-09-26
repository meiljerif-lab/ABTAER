<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'auth_helpers.php';

require_admin($conn);   // ← blocks non-admins with 403

$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? null;

// Validate request_id
if (!is_numeric($request_id) || $request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request id.']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM subscription_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Request not found or already reviewed'
    ]);
    exit;
}
$target_user_id = $row['user_id'];


// Two updates, atomically
$conn->begin_transaction();
$stmt1 = $conn->prepare("UPDATE users SET membership_type = 'student_worker' WHERE id = ?");
$stmt1->bind_param('i', $target_user_id);

$stmt2 = $conn->prepare("UPDATE subscription_requests 
                         SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? 
                         WHERE id = ?");
$stmt2->bind_param('ii', $_SESSION['user_id'], $request_id);

if ($stmt1->execute() && $stmt2->execute()) {
    $conn->commit();
    echo json_encode(['success' => true]);
} else {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to approve request.']);
}
