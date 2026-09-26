<?php
session_start();
header('Content-Type: application/json');
include 'db_connect.php';
include 'auth_helpers.php';

require_admin($conn);  // should block non-admins

echo json_encode(['success' => true, 'message' => 'You are an admin!']);