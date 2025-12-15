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

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$required_fields = ['username', 'email', 'password', 'role'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit();
    }
}

// Prepare user data
$userData = [
    'username' => trim($data['username']),
    'email' => trim($data['email']),
    'password' => $data['password'],
    'role' => $data['role'],
    'full_name' => trim($data['full_name'] ?? ''),
    'phone' => trim($data['phone'] ?? ''),
    'country' => trim($data['country'] ?? 'Sierra Leone'),
    'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
    'position' => trim($data['position'] ?? ''),
    'current_club' => trim($data['current_club'] ?? '')
];

// Validate email format
if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit();
}

// Validate password length
if (strlen($userData['password']) < 6) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters long'
    ]);
    exit();
}

// Validate role
$valid_roles = ['player', 'scout', 'club'];
if (!in_array($userData['role'], $valid_roles)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role selected'
    ]);
    exit();
}

// Create the user
$result = createUser($userData);

if ($result['success']) {
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'user_id' => $result['user_id'],
            'username' => $result['username']
        ]
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
?>