<?php
require_once __DIR__ . '/../config/database.php';

// Create a new user
function createUser($userData) {
    $conn = getDatabaseConnection();
    
    // Check if username exists
    if (usernameExists($userData['username'])) {
        return ['success' => false, 'message' => 'Username already taken'];
    }
    
    // Check if email exists
    if (emailExists($userData['email'])) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash the password
    $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
    
    // Prepare SQL query
    $sql = "INSERT INTO users (
        username, email, password_hash, role, full_name, 
        phone, country, date_of_birth, position, current_club
    ) VALUES (
        :username, :email, :password_hash, :role, :full_name,
        :phone, :country, :date_of_birth, :position, :current_club
    )";
    
    try {
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':username', $userData['username']);
        $stmt->bindParam(':email', $userData['email']);
        $stmt->bindParam(':password_hash', $hashedPassword);
        $stmt->bindParam(':role', $userData['role']);
        $stmt->bindParam(':full_name', $userData['full_name']);
        $stmt->bindParam(':phone', $userData['phone']);
        $stmt->bindParam(':country', $userData['country']);
        $stmt->bindParam(':date_of_birth', $userData['date_of_birth']);
        $stmt->bindParam(':position', $userData['position']);
        $stmt->bindParam(':current_club', $userData['current_club']);
        
        if ($stmt->execute()) {
            $userId = $conn->lastInsertId();
            
            // If user is a player, create initial profile
            if ($userData['role'] == 'player') {
                createPlayerProfile($userId);
            }
            
            return [
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $userId,
                'username' => $userData['username']
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Check if username exists
function usernameExists($username) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id FROM users WHERE username = :username";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

// Check if email exists
function emailExists($email) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id FROM users WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

// Authenticate user (login) - FIXED VERSION
function authenticateUser($username, $password) {
    $conn = getDatabaseConnection();
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    // Using positional parameters to avoid the parameter name conflict
    $sql = "SELECT id, username, email, password_hash, role, full_name 
            FROM users 
            WHERE username = ? OR email = ?";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $username]); // Same value for both parameters
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Remove password hash from result
                unset($user['password_hash']);
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $user
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password'];
            }
        } else {
            return ['success' => false, 'message' => 'User not found'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Get user by ID
function getUserById($userId) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id, username, email, role, full_name, phone, 
                   country, date_of_birth, position, current_club, created_at
            FROM users 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 1) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return null;
}

// Get user by username
function getUserByUsername($username) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id, username, email, role, full_name, phone, 
                   country, date_of_birth, position, current_club, created_at
            FROM users 
            WHERE username = :username";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() === 1) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return null;
}

// Update user profile
function updateUserProfile($userId, $userData) {
    $conn = getDatabaseConnection();
    
    $sql = "UPDATE users SET
            full_name = :full_name,
            phone = :phone,
            country = :country,
            date_of_birth = :date_of_birth,
            position = :position,
            current_club = :current_club
            WHERE id = :id";
    
    try {
        $stmt = $conn->prepare($sql);
        
        $stmt->bindParam(':full_name', $userData['full_name']);
        $stmt->bindParam(':phone', $userData['phone']);
        $stmt->bindParam(':country', $userData['country']);
        $stmt->bindParam(':date_of_birth', $userData['date_of_birth']);
        $stmt->bindParam(':position', $userData['position']);
        $stmt->bindParam(':current_club', $userData['current_club']);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Create initial player profile
function createPlayerProfile($userId) {
    $conn = getDatabaseConnection();
    
    $sql = "INSERT INTO player_profiles (user_id) VALUES (:user_id)";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Get all players (for scout/club viewing)
function getAllPlayers($limit = 50, $offset = 0) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT u.id, u.username, u.full_name, u.country, u.position, 
                   u.current_club, u.date_of_birth,
                   p.height, p.weight, p.preferred_foot
            FROM users u
            LEFT JOIN player_profiles p ON u.id = p.user_id
            WHERE u.role = 'player'
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Search players by criteria
function searchPlayers($filters = []) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT u.id, u.username, u.full_name, u.country, u.position, 
                   u.current_club, u.date_of_birth,
                   p.height, p.weight, p.preferred_foot
            FROM users u
            LEFT JOIN player_profiles p ON u.id = p.user_id
            WHERE u.role = 'player'";
    
    $params = [];
    
    // Add filters if provided
    if (!empty($filters['country'])) {
        $sql .= " AND u.country LIKE :country";
        $params[':country'] = '%' . $filters['country'] . '%';
    }
    
    if (!empty($filters['position'])) {
        $sql .= " AND u.position = :position";
        $params[':position'] = $filters['position'];
    }
    
    if (!empty($filters['min_age']) || !empty($filters['max_age'])) {
        // Calculate birth year range based on age
        $currentYear = date('Y');
        
        if (!empty($filters['max_age'])) {
            $minBirthYear = $currentYear - $filters['max_age'];
            $sql .= " AND YEAR(u.date_of_birth) >= :min_birth_year";
            $params[':min_birth_year'] = $minBirthYear;
        }
        
        if (!empty($filters['min_age'])) {
            $maxBirthYear = $currentYear - $filters['min_age'];
            $sql .= " AND YEAR(u.date_of_birth) <= :max_birth_year";
            $params[':max_birth_year'] = $maxBirthYear;
        }
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get player count
function getPlayerCount() {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'player'";
    $stmt = $conn->query($sql);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['count'] : 0;
}

// Add these functions to your existing functions/users.php file

function getScouts($limit = 10) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id, username, email, role, full_name, phone, country, 
                   created_at, 'scout' as type
            FROM users 
            WHERE role = 'scout'
            ORDER BY created_at DESC
            LIMIT :limit";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClubs($limit = 10) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT id, username, email, role, full_name as club_name, 
                   phone, country, created_at, 'club' as type
            FROM users 
            WHERE role = 'club'
            ORDER BY created_at DESC
            LIMIT :limit";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get user count by role
function getUserCountByRole($role) {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = :role";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['count'] : 0;
}
?>