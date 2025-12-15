<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../functions/session.php';

logoutUser();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>