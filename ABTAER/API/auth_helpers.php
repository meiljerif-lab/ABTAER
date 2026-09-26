<?php 


function require_login(){
    if(!isset($_SESSION['user_id'])){
        http_response_code(401);
        echo json_encode([
            'success'=>false,  'error' => 'Not logged in'
        ]);
        exit;
    }
}

function require_admin($conn){
    if(!isset($_SESSION['user_id'])){
        http_response_code(401);
        echo json_encode([
            'success'=>false, 'error'=>'Not logged in'
        ]);
        exit;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if(!$row || $row['role'] !== 'admin'){
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>'Admin access required']);
        exit;
    }
}
function require_admin_page($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || $row['role'] !== 'admin') {
        header("Location: index.php");
        exit;
    }
}
?>