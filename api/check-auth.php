<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../functions/session.php';

if (isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => getCurrentUser()
    ]);
} else {
    echo json_encode([
        'success' => true,
        'authenticated' => false,
        'user' => null
    ]);
}
?>