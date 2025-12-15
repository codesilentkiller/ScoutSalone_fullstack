<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/users.php';
require_once __DIR__ . '/../functions/session.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username/email and password are required'
    ]);
    exit();
}

// Authenticate user
$result = authenticateUser($data['username'], $data['password']);

if ($result['success']) {
    // Start session and log in user
    loginUser($result['user']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => $result['user']
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
?>