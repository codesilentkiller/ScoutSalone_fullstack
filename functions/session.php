<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login user
function loginUser($userData) {
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['role'] = $userData['role'];
    $_SESSION['full_name'] = $userData['full_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    return true;
}

// Logout user
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    return true;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Get current user data from session
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null
        ];
    }
    return null;
}

// Require login - redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Require specific role
function requireRole($allowedRoles) {
    requireLogin();
    
    $user = getCurrentUser();
    
    if (!in_array($user['role'], $allowedRoles)) {
        header("Location: unauthorized.php");
        exit();
    }
}

// Get user role
function getUserRole() {
    $user = getCurrentUser();
    return $user ? $user['role'] : null;
}

// Check if user has specific role
function hasRole($role) {
    return getUserRole() === $role;
}

// Get user ID from session
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>