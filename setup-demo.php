<?php
echo "<h1>Scout Salone - Setup Demo Users</h1>";

// Include the existing config file instead of redefining the function
require_once 'config/database.php';

// Get the connection using the existing function
$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("<p style='color: red;'>Failed to connect to database. Check config/database.php</p>");
}

echo "<p style='color: green;'>✓ Database connection successful</p>";

// 1. Create users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
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
)";

try {
    $conn->exec($sql);
    echo "<p style='color: green;'>✓ Users table created/verified</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error creating table: " . $e->getMessage() . "</p>";
}

// 2. Create demo users
$demo_users = [
    [
        'username' => 'demo_player',
        'email' => 'player@demo.com',
        'password' => 'player123',
        'role' => 'player',
        'full_name' => 'Mohamed Kamara',
        'phone' => '+232-76-123456',
        'country' => 'Sierra Leone',
        'date_of_birth' => '1998-05-15',
        'position' => 'Forward',
        'current_club' => 'East End Lions'
    ],
    [
        'username' => 'demo_scout',
        'email' => 'scout@demo.com',
        'password' => 'scout123',
        'role' => 'scout',
        'full_name' => 'Ibrahim Sesay',
        'phone' => '+232-76-654321',
        'country' => 'Sierra Leone',
        'date_of_birth' => '1985-08-20',
        'position' => '',
        'current_club' => ''
    ],
    [
        'username' => 'demo_club',
        'email' => 'club@demo.com',
        'password' => 'club123',
        'role' => 'club',
        'full_name' => 'Mighty Blackpool FC',
        'phone' => '+232-22-222222',
        'country' => 'Sierra Leone',
        'date_of_birth' => '',
        'position' => '',
        'current_club' => ''
    ]
];

echo "<h3>Creating Demo Users:</h3>";

$created = 0;
$skipped = 0;

foreach ($demo_users as $user) {
    // Check if user already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$user['username'], $user['email']]);
    
    if ($check->rowCount() > 0) {
        echo "<p>User '{$user['username']}' already exists. Skipping...</p>";
        $skipped++;
        continue;
    }
    
    // Hash the password
    $hashedPassword = password_hash($user['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $sql = "INSERT INTO users (username, email, password_hash, role, full_name, phone, country, date_of_birth, position, current_club) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    try {
        $stmt->execute([
            $user['username'],
            $user['email'],
            $hashedPassword,
            $user['role'],
            $user['full_name'],
            $user['phone'],
            $user['country'],
            $user['date_of_birth'] ?: null,
            $user['position'],
            $user['current_club']
        ]);
        
        echo "<p style='color: green;'>✓ Created user: {$user['username']} ({$user['role']})</p>";
        $created++;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error creating '{$user['username']}': " . $e->getMessage() . "</p>";
    }
}

// 3. Verify creation
echo "<h3>Verifying Demo Users:</h3>";
$stmt = $conn->query("SELECT username, role, email FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "<p style='color: red;'>No users found in database!</p>";
} else {
    echo "<p>Total users in database: " . count($users) . "</p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr style='background: #333; color: white;'><th>ID</th><th>Username</th><th>Role</th><th>Email</th></tr>";
    
    $counter = 1;
    foreach ($users as $user) {
        $bgColor = $counter % 2 == 0 ? '#f2f2f2' : 'white';
        $textColor = '#333';
        echo "<tr style='background: $bgColor; color: $textColor;'>";
        echo "<td>$counter</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "</tr>";
        $counter++;
    }
    echo "</table>";
}

// 4. Test login functionality
echo "<h3>Testing Login:</h3>";

foreach ($demo_users as $user) {
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->execute([$user['username']]);
    $dbUser = $stmt->fetch();
    
    if ($dbUser) {
        if (password_verify($user['password'], $dbUser['password_hash'])) {
            echo "<p style='color: green;'>✓ {$user['username']}: Password verification SUCCESS</p>";
        } else {
            echo "<p style='color: red;'>✗ {$user['username']}: Password verification FAILED</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ {$user['username']}: User not found in database</p>";
    }
}

echo "<hr>";
echo "<h2>Setup Summary:</h2>";
echo "<p>Created: <strong>$created</strong> new users</p>";
echo "<p>Skipped: <strong>$skipped</strong> existing users</p>";

echo "<h3>Demo Credentials:</h3>";
echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>Player Account:</strong> demo_player / player123</p>";
echo "<p><strong>Scout Account:</strong> demo_scout / scout123</p>";
echo "<p><strong>Club Account:</strong> demo_club / club123</p>";
echo "</div>";

echo "<p style='margin-top: 30px;'><a href='login.php' style='display: inline-block; padding: 12px 24px; background: #000; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Go to Login Page</a></p>";

// Close connection
$conn = null;
?>