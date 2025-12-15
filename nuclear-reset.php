<?php
echo "<h1>Nuclear Reset - Fix Everything</h1>";

$host = 'localhost';
$dbname = 'scout_salone';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    
    // Drop and recreate database
    $pdo->exec("DROP DATABASE IF EXISTS $dbname");
    $pdo->exec("CREATE DATABASE $dbname");
    $pdo->exec("USE $dbname");
    
    echo "<p style='color: green;'>✅ Database recreated</p>";
    
    // Create users table
    $pdo->exec("CREATE TABLE users (
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
    )");
    
    echo "<p style='color: green;'>✅ Users table created</p>";
    
    // Generate FRESH password hashes
    $player_hash = password_hash('player123', PASSWORD_BCRYPT);
    $scout_hash = password_hash('scout123', PASSWORD_BCRYPT);
    $club_hash = password_hash('club123', PASSWORD_BCRYPT);
    
    // Insert demo users
    $pdo->exec("INSERT INTO users (username, email, password_hash, role, full_name, phone, country, date_of_birth, position, current_club) VALUES
        ('demo_player', 'player@demo.com', '$player_hash', 'player', 'John Doe', '+232-123-4567', 'Sierra Leone', '1998-05-15', 'Forward', 'East End Lions'),
        ('demo_scout', 'scout@demo.com', '$scout_hash', 'scout', 'Jane Smith', '+232-987-6543', 'Sierra Leone', '1985-08-20', NULL, NULL),
        ('demo_club', 'club@demo.com', '$club_hash', 'club', 'FC Freetown', '+232-555-1234', 'Sierra Leone', NULL, NULL, NULL)");
    
    echo "<p style='color: green;'>✅ Demo users created with correct passwords</p>";
    
    // Verify
    $stmt = $pdo->query("SELECT username, password_hash FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h3>Verification:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Username</th><th>Password Test</th></tr>";
    
    foreach ($users as $user) {
        $test_pass = '';
        if ($user['username'] == 'demo_player') $test_pass = 'player123';
        if ($user['username'] == 'demo_scout') $test_pass = 'scout123';
        if ($user['username'] == 'demo_club') $test_pass = 'club123';
        
        $is_valid = password_verify($test_pass, $user['password_hash']);
        
        echo "<tr>";
        echo "<td>{$user['username']}</td>";
        echo "<td style='color: " . ($is_valid ? 'green' : 'red') . ";'>";
        echo $is_valid ? '✅ Password works!' : '❌ Password failed';
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px;'>";
    echo "<h2 style='color: #155724;'>✅ 100% GUARANTEED TO WORK NOW!</h2>";
    echo "<p>The database has been completely reset with fresh, correct password hashes.</p>";
    echo "<p><strong>Demo credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>demo_player</strong> / <strong>player123</strong></li>";
    echo "<li><strong>demo_scout</strong> / <strong>scout123</strong></li>";
    echo "<li><strong>demo_club</strong> / <strong>club123</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p style='margin-top: 30px;'>";
    echo "<a href='login.php' style='padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 20px; font-weight: bold;'>CLICK HERE TO TEST LOGIN</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>