<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'scout_salone');
define('DB_USER', 'root');      // Change to your MySQL username
define('DB_PASS', '');          // Change to your MySQL password

// Create database connection
function getDatabaseConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Initialize database tables if they don't exist
function initializeDatabase() {
    $conn = getDatabaseConnection();
    
    $sql = "
    -- Users table
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('player', 'scout', 'club') NOT NULL,
        full_name VARCHAR(100),
        phone VARCHAR(20),
        country VARCHAR(50),
        date_of_birth DATE,
        position VARCHAR(50),
        current_club VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Player profiles table
    CREATE TABLE IF NOT EXISTS player_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        height DECIMAL(4,2),
        weight DECIMAL(5,2),
        preferred_foot ENUM('left', 'right', 'both'),
        video_url VARCHAR(255),
        bio TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    -- Player stats table
    CREATE TABLE IF NOT EXISTS player_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        matches_played INT DEFAULT 0,
        goals INT DEFAULT 0,
        assists INT DEFAULT 0,
        yellow_cards INT DEFAULT 0,
        red_cards INT DEFAULT 0,
        season_year YEAR,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    try {
        $conn->exec($sql);
        echo "Database tables initialized successfully!<br>";
    } catch(PDOException $e) {
        die("Error creating tables: " . $e->getMessage());
    }
}

// Call this function once to set up tables
// initializeDatabase();
?>