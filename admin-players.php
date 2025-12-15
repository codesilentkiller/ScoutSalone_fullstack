<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Check if user has permission to view players
if (!hasPermission('players', 'view')) {
    die('Access denied. You do not have permission to view players.');
}

$conn = getDatabaseConnection();
$error = '';
$success = '';

// Handle delete player
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $playerId = $_GET['delete'];
    
    if (hasPermission('players', 'delete')) {
        try {
            $conn->beginTransaction();
            
            // Check if player exists
            $check = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'player'");
            $check->execute([$playerId]);
            
            if ($check->rowCount() > 0) {
                $player = $check->fetch();
                
                // Delete player (cascade will handle related records)
                $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete->execute([$playerId]);
                
                // Log the action
                $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                       VALUES (?, 'delete', 'users', ?)");
                $log->execute([$_SESSION['admin_id'], $playerId]);
                
                $conn->commit();
                $success = "Player '{$player['full_name']}' deleted successfully!";
            } else {
                $error = "Player not found!";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error deleting player: " . $e->getMessage();
        }
    } else {
        $error = "You don't have permission to delete players.";
    }
    
    // Redirect to clear delete parameter
    header("Location: admin-players.php?" . ($success ? "success=" . urlencode($success) : "error=" . urlencode($error)));
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$country = $_GET['country'] ?? '';
$position = $_GET['position'] ?? '';
$minAge = $_GET['min_age'] ?? '';
$maxAge = $_GET['max_age'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build filter array
$filters = [];
if (!empty($search)) $filters['search'] = $search;
if (!empty($country)) $filters['country'] = $country;
if (!empty($position)) $filters['position'] = $position;
if (!empty($minAge)) $filters['min_age'] = $minAge;
if (!empty($maxAge)) $filters['max_age'] = $maxAge;

// Get all players with filters
$players = getPlayers($conn, $filters);

// Sort players
if ($sort === 'name_asc') {
    usort($players, function($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });
} elseif ($sort === 'name_desc') {
    usort($players, function($a, $b) {
        return strcmp($b['full_name'], $a['full_name']);
    });
} elseif ($sort === 'oldest') {
    $players = array_reverse($players);
}

// Get unique countries and positions for filters
$countries = $conn->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND role = 'player' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
$positions = $conn->query("SELECT DISTINCT position FROM users WHERE position IS NOT NULL AND role = 'player' ORDER BY position")->fetchAll(PDO::FETCH_COLUMN);

// Get player stats summary
$totalPlayers = count($players);
$byCountry = array_count_values(array_column($players, 'country'));
$byPosition = array_count_values(array_column($players, 'position'));

// Ensure calculateAge exists (fallback if admin-functions.php wasn't loaded)
if (!function_exists('calculateAge')) {
    /**
     * Calculate age from date of birth (fallback)
     */
    function calculateAge($dateOfBirth) {
        if (empty($dateOfBirth)) return 0;
        try {
            $birth = new DateTime($dateOfBirth);
            $today = new DateTime('today');
            return $birth->diff($today)->y;
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #111827;
            --light: #f9fafb;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --border: #d1d5db;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            min-height: 100vh;
        }

        /* Sidebar - Same as dashboard */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.5px;
        }

        .sidebar-header p {
            color: var(--gray);
            font-size: 14px;
            margin-top: 4px;
        }

        .nav-menu {
            padding: 20px 0;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background: #f8fafc;
            color: var(--primary);
            border-left-color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .nav-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }

        .page-title p {
            color: var(--gray);
            font-size: 14px;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            width: 300px;
            background: white;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .logout-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--dark);
            text-decoration: none;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert i {
            font-size: 20px;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-card i {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .filter-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--gray);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        /* Players Table */
        .players-container {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .table-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .sort-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .players-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .players-table th {
            text-align: left;
            padding: 16px;
            background: #f8fafc;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }

        .players-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .players-table tbody tr:hover {
            background: #f8fafc;
        }

        .player-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .player-details h4 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .player-details p {
            color: var(--gray);
            font-size: 12px;
        }

        .position-badge {
            background: #e0e7ff;
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .country-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .age-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn.view {
            background: var(--primary);
        }

        .action-btn.edit {
            background: var(--secondary);
        }

        .action-btn.delete {
            background: var(--danger);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .page-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            color: var(--dark);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-btn:hover:not(.active) {
            background: #f8fafc;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2, .sidebar-header p, .nav-item span, .admin-info {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 16px;
            }
            
            .nav-item i {
                margin: 0;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .search-box input {
                width: 200px;
            }
            
            .players-table {
                display: block;
                overflow-x: auto;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>⚽ ScoutSalone</h2>
            <p>Admin Dashboard</p>
        </div>
        
        <nav class="nav-menu">
            <a href="admin-dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin-players.php" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Players</span>
            </a>
            <a href="admin-add-player.php" class="nav-item">
                <i class="fas fa-user-plus"></i>
                <span>Add Player</span>
            </a>
            <a href="admin-scouts.php" class="nav-item">
                <i class="fas fa-binoculars"></i>
                <span>Scouts</span>
            </a>
            <a href="admin-reports.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="admin-clubs.php" class="nav-item">
                <i class="fas fa-landmark"></i>
                <span>Clubs</span>
            </a>
            <a href="admin-transfers.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Transfers</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 2)); ?>
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h4>
                    <p><?php echo htmlspecialchars($_SESSION['admin_role']); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <a href="admin-dashboard.php" class="btn btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </a>
                <div>
                    <h1>Player Management</h1>
                    <p>Manage all players in the system</p>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="global-search" placeholder="Search players...">
                </div>
                
                <a href="admin-add-player.php" class="btn btn-primary" style="text-decoration: none;">
                    <i class="fas fa-user-plus"></i>
                    Add Player
                </a>
                
                <a href="admin-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_GET['success']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $totalPlayers; ?></h3>
                <p>Total Players</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-globe-africa"></i>
                <h3><?php echo count($byCountry); ?></h3>
                <p>Countries</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-futbol"></i>
                <h3><?php echo count($byPosition); ?></h3>
                <p>Positions</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <h3><?php 
                    $avgAge = array_sum(array_map(function($p) {
                        return calculateAge($p['date_of_birth']);
                    }, $players)) / max(1, $totalPlayers);
                    echo round($avgAge, 1);
                ?></h3>
                <p>Average Age</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h3>Filter Players</h3>
                <a href="admin-players.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    Reset Filters
                </a>
            </div>
            
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-control" 
                           placeholder="Name, username, or email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Country</label>
                    <select name="country" class="filter-control">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" 
                            <?php echo $country == $c ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Position</label>
                    <select name="position" class="filter-control">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>" 
                            <?php echo $position == $p ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Age Range</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="number" name="min_age" class="filter-control" 
                               placeholder="Min" min="16" max="50" value="<?php echo htmlspecialchars($minAge); ?>">
                        <input type="number" name="max_age" class="filter-control" 
                               placeholder="Max" min="16" max="50" value="<?php echo htmlspecialchars($maxAge); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Players Table -->
        <div class="players-container">
            <div class="table-header">
                <h3>All Players (<?php echo $totalPlayers; ?>)</h3>
                <div class="table-actions">
                    <select class="sort-select" onchange="window.location.href = '?<?php 
                        echo http_build_query(array_merge($_GET, ['sort' => ''])) . 'sort='; 
                    ?>' + this.value">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                    </select>
                </div>
            </div>
            
            <?php if ($totalPlayers > 0): ?>
            <table class="players-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Position</th>
                        <th>Country</th>
                        <th>Age</th>
                        <th>Club</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): 
                        $age = calculateAge($player['date_of_birth']);
                    ?>
                    <tr>
                        <td>
                            <div class="player-info">
                                <div class="player-avatar">
                                    <?php echo strtoupper(substr($player['full_name'], 0, 1)); ?>
                                </div>
                                <div class="player-details">
                                    <h4><?php echo htmlspecialchars($player['full_name']); ?></h4>
                                    <p>@<?php echo htmlspecialchars($player['username']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="position-badge"><?php echo htmlspecialchars($player['position'] ?: 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="country-badge"><?php echo htmlspecialchars($player['country']); ?></span>
                        </td>
                        <td>
                            <span class="age-badge"><?php echo $age; ?> years</span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($player['current_club'] ?: 'No Club'); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($player['created_at'])); ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="admin-view-player.php?id=<?php echo $player['id']; ?>" class="action-btn view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="admin-edit-player.php?id=<?php echo $player['id']; ?>" class="action-btn edit" title="Edit Player">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if (hasPermission('players', 'delete')): ?>
                                <a href="#" class="action-btn delete" 
                                   onclick="confirmDelete(<?php echo $player['id']; ?>, '<?php echo addslashes($player['full_name']); ?>')" 
                                   title="Delete Player">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination (Example - implement actual pagination) -->
            <div class="pagination">
                <a href="#" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <a href="#" class="page-btn active">1</a>
                <a href="#" class="page-btn">2</a>
                <a href="#" class="page-btn">3</a>
                <a href="#" class="page-btn"><i class="fas fa-chevron-right"></i></a>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Players Found</h3>
                <p><?php echo empty($search) && empty($country) && empty($position) ? 
                    'No players have been added yet.' : 
                    'No players match your search criteria.'; ?></p>
                <?php if (!empty($search) || !empty($country) || !empty($position)): ?>
                <a href="admin-players.php" class="btn btn-primary" style="margin-top: 20px;">
                    Clear Filters
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div style="padding: 20px 0;">
                <p>Are you sure you want to delete player <strong id="deletePlayerName"></strong>?</p>
                <p style="color: var(--danger); margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    This action cannot be undone. All player data, reports, and notes will be permanently deleted.
                </p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <a id="confirmDeleteBtn" class="btn btn-danger">Delete Player</a>
            </div>
        </div>
    </div>

    <script>
        // Global search
        document.getElementById('global-search').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const search = this.value.trim();
                const params = new URLSearchParams(window.location.search);
                if (search) {
                    params.set('search', search);
                } else {
                    params.delete('search');
                }
                window.location.href = 'admin-players.php?' + params.toString();
            }
        });

        // Delete confirmation modal
        function confirmDelete(playerId, playerName) {
            document.getElementById('deletePlayerName').textContent = playerName;
            document.getElementById('confirmDeleteBtn').href = 'admin-players.php?delete=' + playerId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Quick filter functions
        function filterByPosition(position) {
            const params = new URLSearchParams(window.location.search);
            params.set('position', position);
            window.location.href = 'admin-players.php?' + params.toString();
        }

        function filterByCountry(country) {
            const params = new URLSearchParams(window.location.search);
            params.set('country', country);
            window.location.href = 'admin-players.php?' + params.toString();
        }
    </script>
</body>