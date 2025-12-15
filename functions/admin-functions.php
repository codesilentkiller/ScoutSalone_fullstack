<?php
// Get dashboard statistics
function getDashboardStats($conn) {
    $stats = [];
    
    // Total players
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'player'");
    $stats['total_players'] = $stmt->fetch()['count'];
    
    // Active scouts
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scouts WHERE status = 'active'");
    $stats['active_scouts'] = $stmt->fetch()['count'];
    
    // Pending reports
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scouting_reports WHERE status IN ('submitted', 'under_review')");
    $stats['pending_reports'] = $stmt->fetch()['count'];
    
    // Active clubs
    $stmt = $conn->query("SELECT COUNT(*) as count FROM clubs WHERE status = 'active'");
    $stats['active_clubs'] = $stmt->fetch()['count'];
    
    return $stats;
}

// Get recent activities
function getRecentActivities($conn) {
    $activities = [];
    
    // Get recent admin logs
    $stmt = $conn->query("SELECT al.action, au.full_name, al.created_at 
                          FROM admin_logs al 
                          LEFT JOIN admin_users au ON al.admin_id = au.id 
                          ORDER BY al.created_at DESC LIMIT 5");
    
    while ($row = $stmt->fetch()) {
        $icon = match($row['action']) {
            'login' => 'sign-in-alt',
            'logout' => 'sign-out-alt',
            'create' => 'plus',
            'update' => 'edit',
            'delete' => 'trash',
            default => 'info-circle'
        };
        
        $description = match($row['action']) {
            'login' => "{$row['full_name']} logged in",
            'logout' => "{$row['full_name']} logged out",
            'create' => "{$row['full_name']} created a new record",
            'update' => "{$row['full_name']} updated a record",
            'delete' => "{$row['full_name']} deleted a record",
            default => "{$row['full_name']} performed an action"
        };
        
        $activities[] = [
            'icon' => $icon,
            'description' => $description,
            'time' => timeAgo($row['created_at'])
        ];
    }
    
    // Add some mock activities for demo
    if (empty($activities)) {
        $activities = [
            [
                'icon' => 'user-plus',
                'description' => 'New player "John Kamara" added to database',
                'time' => '2 hours ago'
            ],
            [
                'icon' => 'file-upload',
                'description' => 'Scout report #SR-2024-045 submitted for review',
                'time' => '5 hours ago'
            ],
            [
                'icon' => 'check-circle',
                'description' => 'Transfer negotiation with East End Lions completed',
                'time' => '1 day ago'
            ],
            [
                'icon' => 'calendar-plus',
                'description' => 'New match scheduled: Lions vs Blackpool',
                'time' => '2 days ago'
            ],
            [
                'icon' => 'chart-line',
                'description' => 'Monthly analytics report generated',
                'time' => '3 days ago'
            ]
        ];
    }
    
    return $activities;
}

// Time ago function
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

// Check admin permissions
function hasPermission($permission, $action) {
    if (!isset($_SESSION['admin_permissions'])) return false;
    
    $permissions = $_SESSION['admin_permissions'];
    return isset($permissions[$permission]) && 
           in_array($action, $permissions[$permission]);
}

// Get all players with filters
function getPlayers($conn, $filters = []) {
    $sql = "SELECT u.*, 
                   (SELECT COUNT(*) FROM scouting_reports WHERE player_id = u.id) as report_count,
                   (SELECT status FROM transfer_opportunities WHERE player_id = u.id ORDER BY created_at DESC LIMIT 1) as transfer_status
            FROM users u 
            WHERE u.role = 'player'";
    
    $params = [];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.position LIKE ?)";
        $search = "%{$filters['search']}%";
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    if (!empty($filters['country'])) {
        $sql .= " AND u.country = ?";
        $params[] = $filters['country'];
    }
    
    if (!empty($filters['position'])) {
        $sql .= " AND u.position = ?";
        $params[] = $filters['position'];
    }
    
    if (!empty($filters['min_age']) || !empty($filters['max_age'])) {
        $currentYear = date('Y');
        
        if (!empty($filters['min_age'])) {
            $maxBirthYear = $currentYear - $filters['min_age'];
            $sql .= " AND YEAR(u.date_of_birth) <= ?";
            $params[] = $maxBirthYear;
        }
        
        if (!empty($filters['max_age'])) {
            $minBirthYear = $currentYear - $filters['max_age'];
            $sql .= " AND YEAR(u.date_of_birth) >= ?";
            $params[] = $minBirthYear;
        }
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = $filters['limit'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generate report code
function generateReportCode($conn) {
    $year = date('Y');
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scouting_reports WHERE YEAR(created_at) = $year");
    $count = $stmt->fetch()['count'] + 1;
    
    return "SR-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Add this function to the existing admin-functions.php file

/**
 * Calculate age from date of birth
 */
function calculateAge($dateOfBirth) {
    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

/**
 * Get player age groups for analytics
 */
function getPlayerAgeGroups($conn) {
    $sql = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 21 THEN '18-21'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 22 AND 25 THEN '22-25'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 30 THEN '26-30'
                    ELSE 'Over 30'
                END as age_group,
                COUNT(*) as count
            FROM users 
            WHERE role = 'player' 
            GROUP BY age_group 
            ORDER BY 
                CASE age_group
                    WHEN 'Under 18' THEN 1
                    WHEN '18-21' THEN 2
                    WHEN '22-25' THEN 3
                    WHEN '26-30' THEN 4
                    ELSE 5
                END";
    
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get player positions distribution
 */
function getPlayerPositions($conn) {
    $sql = "SELECT 
                COALESCE(position, 'Not Specified') as position,
                COUNT(*) as count
            FROM users 
            WHERE role = 'player' 
            GROUP BY position 
            ORDER BY count DESC";
    
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Log admin action
function logAdminAction($conn, $action, $table, $record_id) {
    if (isset($_SESSION['admin_id'])) {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $table,
            $record_id,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    }
}

// Get scout by ID
function getScoutById($conn, $scout_id) {
    $stmt = $conn->prepare("
        SELECT s.*, u.username as login_username,
               COUNT(DISTINCT sr.id) as total_reports,
               COUNT(DISTINCT CASE WHEN sr.status = 'approved' THEN sr.id END) as approved_reports,
               COUNT(DISTINCT m.id) as matches_assigned
        FROM scouts s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN scouting_reports sr ON s.id = sr.scout_id
        LEFT JOIN matches m ON s.id = m.assigned_scout_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$scout_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get analytics data
function getAnalyticsData($conn, $timeframe = '30days') {
    $data = [];
    
    // Calculate days based on timeframe
    $days = match($timeframe) {
        '7days' => 7,
        '90days' => 90,
        'year' => 365,
        default => 30
    };
    
    // Player growth rate
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as current_count,
            (SELECT COUNT(*) FROM users WHERE role = 'player' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)) as previous_count
        FROM users 
        WHERE role = 'player' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$days, $days]);
    $growth = $stmt->fetch();
    
    if ($growth['previous_count'] > 0) {
        $data['player_growth_rate'] = round((($growth['current_count'] - $growth['previous_count']) / $growth['previous_count']) * 100, 1);
    } else {
        $data['player_growth_rate'] = 100;
    }
    
    // Scout accuracy (sample data - in real system, calculate from reports)
    $data['scout_accuracy'] = 85.6;
    
    // Average transfer value
    $stmt = $conn->query("SELECT AVG(deal_value) as avg_value FROM transfer_opportunities WHERE status = 'signed'");
    $transfer = $stmt->fetch();
    $data['avg_transfer_value'] = $transfer['avg_value'] ? round($transfer['avg_value']) : 150000;
    
    // High risk players
    $stmt = $conn->query("SELECT COUNT(*) as count FROM player_notes WHERE note_type = 'injury' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $data['high_risk_players'] = $stmt->fetch()['count'] ?: 3;
    
    // Top scouts
    $data['top_scouts'] = [
        ['name' => 'Ahmed Bangura', 'region' => 'Freetown', 'reports' => 12, 'accuracy' => 92.5, 'performance' => 9.2],
        ['name' => 'Fatmata Conteh', 'region' => 'Bo', 'reports' => 18, 'accuracy' => 88.3, 'performance' => 8.7],
        ['name' => 'Mohamed Turay', 'region' => 'Accra', 'reports' => 9, 'accuracy' => 85.1, 'performance' => 8.3],
        ['name' => 'Samuel Koroma', 'region' => 'Kenema', 'reports' => 7, 'accuracy' => 91.2, 'performance' => 8.9],
        ['name' => 'Aminata Sesay', 'region' => 'Makeni', 'reports' => 15, 'accuracy' => 86.7, 'performance' => 8.5]
    ];
    
    // Emerging talents
    $data['emerging_talents'] = [
        ['name' => 'John Kamara', 'club' => 'East End Lions', 'age' => 19, 'position' => 'Forward', 'potential' => 9.2, 'value' => 250000],
        ['name' => 'David Conteh', 'club' => 'Bo Rangers', 'age' => 18, 'position' => 'Midfielder', 'potential' => 8.8, 'value' => 180000],
        ['name' => 'Sarah Turay', 'club' => 'Mighty Blackpool', 'age' => 20, 'position' => 'Defender', 'potential' => 8.5, 'value' => 150000],
        ['name' => 'Michael Bangura', 'club' => 'FC Kallon', 'age' => 21, 'position' => 'Goalkeeper', 'potential' => 8.7, 'value' => 200000],
        ['name' => 'Elizabeth Koroma', 'club' => 'Diamond Stars', 'age' => 19, 'position' => 'Forward', 'potential' => 9.1, 'value' => 230000]
    ];
    
    return $data;
}
?>