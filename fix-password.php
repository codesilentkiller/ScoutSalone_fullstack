<?php
echo "<h1>Fix Password Issues</h1>";

// Generate CORRECT password hashes
$passwords = [
    'player123' => password_hash('player123', PASSWORD_BCRYPT),
    'scout123' => password_hash('scout123', PASSWORD_BCRYPT),
    'club123' => password_hash('club123', PASSWORD_BCRYPT)
];

echo "<h3>Generated Password Hashes:</h3>";
foreach ($passwords as $plain => $hash) {
    echo "<p><strong>$plain</strong>: <code>$hash</code></p>";
}

// Test database connection
$host = 'localhost';
$dbname = 'scout_salone';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Database connected</p>";
    
    // First, let's check current users
    echo "<h3>Current Users in Database:</h3>";
    $stmt = $pdo->query("SELECT username, password_hash FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: red;'>No users found! Creating demo users...</p>";
        
        // Create demo users table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
        
        // Insert fresh demo users with CORRECT hashes
        $sql = "INSERT INTO users (username, email, password_hash, role, full_name, phone, country, date_of_birth, position, current_club) VALUES 
                ('demo_player', 'player@demo.com', :player_hash, 'player', 'John Doe', '+232-123-4567', 'Sierra Leone', '1998-05-15', 'Forward', 'East End Lions'),
                ('demo_scout', 'scout@demo.com', :scout_hash, 'scout', 'Jane Smith', '+232-987-6543', 'Sierra Leone', '1985-08-20', NULL, NULL),
                ('demo_club', 'club@demo.com', :club_hash, 'club', 'FC Freetown', '+232-555-1234', 'Sierra Leone', NULL, NULL, NULL)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':player_hash' => $passwords['player123'],
            ':scout_hash' => $passwords['scout123'],
            ':club_hash' => $passwords['club123']
        ]);
        
        echo "<p style='color: green;'>✓ Demo users created</p>";
        
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Username</th><th>Password Hash</th><th>Status</th></tr>";
        
        foreach ($users as $user) {
            $password_to_test = '';
            if ($user['username'] == 'demo_player') $password_to_test = 'player123';
            if ($user['username'] == 'demo_scout') $password_to_test = 'scout123';
            if ($user['username'] == 'demo_club') $password_to_test = 'club123';
            
            if ($password_to_test) {
                $is_valid = password_verify($password_to_test, $user['password_hash']);
                $status = $is_valid ? '✅ Valid' : '❌ Invalid';
                echo "<tr>";
                echo "<td>{$user['username']}</td>";
                echo "<td><code>" . substr($user['password_hash'], 0, 30) . "...</code></td>";
                echo "<td>$status</td>";
                echo "</tr>";
                
                // If invalid, update it
                if (!$is_valid) {
                    $new_hash = password_hash($password_to_test, PASSWORD_BCRYPT);
                    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                    $update->execute([$new_hash, $user['username']]);
                    echo "<tr><td colspan='3' style='color: orange;'>Updated password for {$user['username']}</td></tr>";
                }
            }
        }
        echo "</table>";
    }
    
    // Test authentication function
    echo "<h3>Testing Authentication:</h3>";
    
    require_once 'functions/users.php';
    
    $test_accounts = [
        ['demo_player', 'player123'],
        ['demo_scout', 'scout123'],
        ['demo_club', 'club123']
    ];
    
    foreach ($test_accounts as $account) {
        $result = authenticateUser($account[0], $account[1]);
        
        if ($result['success']) {
            echo "<p style='color: green;'>✅ {$account[0]}: Login SUCCESS! Role: {$result['user']['role']}</p>";
        } else {
            echo "<p style='color: red;'>❌ {$account[0]}: FAILED - {$result['message']}</p>";
            
            // Manual test
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
            $stmt->execute([$account[0]]);
            $db_user = $stmt->fetch();
            
            if ($db_user) {
                $manual_check = password_verify($account[1], $db_user['password_hash']);
                echo "<p style='color: " . ($manual_check ? 'green' : 'red') . ";'>Manual check: " . ($manual_check ? 'Password matches' : 'Password DOES NOT match') . "</p>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h2 style='color: green;'>Fix Complete!</h2>";
    echo "<p><a href='login.php' style='padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 18px;'>Test Login Now</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>