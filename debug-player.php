<?php
session_start();
require_once 'config/database.php';

echo "<h1>Debug Players Page</h1>";
echo "<pre>";

// Test database connection
$conn = getDatabaseConnection();
if (!$conn) {
    echo "❌ Database connection failed!\n";
} else {
    echo "✅ Database connected successfully\n\n";
}

// Check users table
echo "=== Checking users table ===\n";
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'player'");
$result = $stmt->fetch();
echo "Total players in users table: " . $result['count'] . "\n";

if ($result['count'] > 0) {
    $stmt = $conn->query("SELECT id, username, full_name, position, current_club, date_of_birth FROM users WHERE role = 'player'");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Player Details ===\n";
    foreach ($players as $player) {
        echo "ID: " . $player['id'] . "\n";
        echo "Username: " . $player['username'] . "\n";
        echo "Full Name: " . ($player['full_name'] ?: 'NULL') . "\n";
        echo "Position: " . ($player['position'] ?: 'NULL') . "\n";
        echo "Club: " . ($player['current_club'] ?: 'NULL') . "\n";
        echo "DOB: " . ($player['date_of_birth'] ?: 'NULL') . "\n";
        echo "---\n";
    }
}

// Check player_profiles table
echo "\n=== Checking player_profiles table ===\n";
$stmt = $conn->query("SELECT COUNT(*) as count FROM player_profiles");
$result = $stmt->fetch();
echo "Total records in player_profiles: " . $result['count'] . "\n";

if ($result['count'] > 0) {
    $stmt = $conn->query("SELECT * FROM player_profiles LIMIT 5");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Profile Details ===\n";
    foreach ($profiles as $profile) {
        print_r($profile);
        echo "---\n";
    }
}

// Check player_stats table
echo "\n=== Checking player_stats table ===\n";
$stmt = $conn->query("SELECT COUNT(*) as count FROM player_stats WHERE season_year = YEAR(CURDATE())");
$result = $stmt->fetch();
echo "Current season stats records: " . $result['count'] . "\n";

// Test the getAllPlayers function
echo "\n=== Testing getAllPlayers function ===\n";
function getAllPlayers() {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.full_name, 
                u.country, 
                u.position, 
                u.current_club, 
                u.date_of_birth,
                u.phone,
                u.email,
                u.created_at,
                p.height, 
                p.weight, 
                p.preferred_foot,
                p.bio,
                ps.matches_played,
                ps.goals,
                ps.assists,
                ps.yellow_cards,
                ps.red_cards
            FROM users u
            LEFT JOIN player_profiles p ON u.id = p.user_id
            LEFT JOIN player_stats ps ON u.id = ps.user_id AND ps.season_year = YEAR(CURDATE())
            WHERE u.role = 'player'
            ORDER BY u.created_at DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Query returned " . count($results) . " rows\n";
        if (count($results) > 0) {
            echo "First player data:\n";
            print_r($results[0]);
        }
        return $results;
    } catch (PDOException $e) {
        echo "❌ Query error: " . $e->getMessage() . "\n";
        return [];
    }
}

$players = getAllPlayers();
echo "\n=== Final Result ===\n";
echo "Number of players to display: " . count($players) . "\n";

echo "</pre>";

// Now display them in a simple format
if (count($players) > 0) {
    echo "<h2>Players Found:</h2>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Position</th><th>Club</th><th>Height</th><th>Goals</th></tr>";
    
    foreach ($players as $player) {
        echo "<tr>";
        echo "<td>" . $player['id'] . "</td>";
        echo "<td>" . htmlspecialchars($player['full_name'] ?: $player['username']) . "</td>";
        echo "<td>" . htmlspecialchars($player['position'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($player['current_club'] ?: 'N/A') . "</td>";
        echo "<td>" . ($player['height'] ? $player['height'] . 'm' : 'N/A') . "</td>";
        echo "<td>" . ($player['goals'] ?: '0') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h3 style='color: red;'>❌ No players found in the query result!</h3>";
    echo "<p>Possible issues:</p>";
    echo "<ol>";
    echo "<li>No users with role='player' in database</li>";
    echo "<li>JOIN conditions not matching (check user_id in player_profiles)</li>";
    echo "<li>Database tables don't exist</li>";
    echo "</ol>";
}
?>