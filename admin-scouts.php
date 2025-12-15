<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Check permissions
if (!hasPermission('scouts', 'view')) {
    header('Location: admin-dashboard.php');
    exit();
}

$conn = getDatabaseConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scout_id = $_POST['scout_id'] ?? '';
    
    if ($action === 'update_status' && hasPermission('scouts', 'edit')) {
        $status = $_POST['status'] ?? '';
        $stmt = $conn->prepare("UPDATE scouts SET status = ? WHERE id = ?");
        $stmt->execute([$status, $scout_id]);
        
        // Log the action
        logAdminAction($conn, 'update', 'scouts', $scout_id);
    }
    
    if ($action === 'delete' && hasPermission('scouts', 'delete')) {
        $stmt = $conn->prepare("DELETE FROM scouts WHERE id = ?");
        $stmt->execute([$scout_id]);
        
        // Log the action
        logAdminAction($conn, 'delete', 'scouts', $scout_id);
    }
}

// Get filter parameters with null checks
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$country = isset($_GET['country']) ? trim($_GET['country']) : '';
$specialization = isset($_GET['specialization']) ? trim($_GET['specialization']) : '';

// Build query with filters
$query = "SELECT s.*, 
                 u.username as login_username,
                 COUNT(DISTINCT sr.id) as total_reports,
                 COUNT(DISTINCT CASE WHEN sr.status = 'approved' THEN sr.id END) as approved_reports,
                 COUNT(DISTINCT m.id) as matches_assigned
          FROM scouts s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN scouting_reports sr ON s.id = sr.scout_id
          LEFT JOIN matches m ON s.id = m.assigned_scout_id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (s.full_name LIKE ? OR s.email LIKE ? OR s.region LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status) && $status !== 'all') {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

if (!empty($country) && $country !== 'all') {
    $query .= " AND s.country = ?";
    $params[] = $country;
}

if (!empty($specialization) && $specialization !== 'all') {
    $query .= " AND s.specialization = ?";
    $params[] = $specialization;
}

$query .= " GROUP BY s.id ORDER BY s.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$scouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique countries and specializations for filters
$countries_stmt = $conn->query("SELECT DISTINCT country FROM scouts WHERE country IS NOT NULL ORDER BY country");
$countries = $countries_stmt->fetchAll(PDO::FETCH_COLUMN);

$specializations_stmt = $conn->query("SELECT DISTINCT specialization FROM scouts WHERE specialization IS NOT NULL ORDER BY specialization");
$specializations = $specializations_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_scouts,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_scouts,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_scouts,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_scouts,
        ROUND(AVG(performance_score), 1) as avg_performance
    FROM scouts
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Helper function for safe output
function safeOutput($value, $default = '') {
    return $value !== null ? htmlspecialchars($value) : $default;
}

// Helper function for safe echo
function safeEcho($value, $default = '') {
    echo safeOutput($value, $default);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scouts Management - Scout Salone</title>
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
            display: flex;
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

        .admin-info h4 {
            font-size: 14px;
            font-weight: 600;
        }

        .admin-info p {
            font-size: 12px;
            color: var(--gray);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
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

        .notification-btn, .logout-btn {
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
        }

        .notification-btn:hover, .logout-btn:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
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
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Filters */
        .filters-container {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filters-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-select, .filter-input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }

        .btn-success {
            background: var(--secondary);
            color: white;
        }

        .btn-success:hover {
            background: #0da271;
            transform: translateY(-2px);
        }

        /* Scouts Table */
        .table-container {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--dark);
        }

        .table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .performance-score {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .score-high { color: var(--secondary); }
        .score-medium { color: var(--warning); }
        .score-low { color: var(--danger); }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: var(--dark);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 24px;
        }

        .pagination-btn {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: var(--dark);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:hover:not(.active) {
            background: #f8fafc;
            border-color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--gray-light);
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
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .search-box input {
                width: 200px;
            }
            
            .table {
                display: block;
                overflow-x: auto;
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
            <a href="admin-players.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Players</span>
            </a>
            <a href="admin-scouts.php" class="nav-item active">
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
            <a href="admin-matches.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Matches</span>
            </a>
            <a href="admin-analytics.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
            <a href="admin-settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr(safeOutput($_SESSION['admin_name']), 0, 2)); ?>
                </div>
                <div class="admin-info">
                    <h4><?php safeEcho($_SESSION['admin_name']); ?></h4>
                    <p><?php safeEcho($_SESSION['admin_role']); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Scouts Management</h1>
                <p>Manage field intelligence network</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search scouts..." id="globalSearch">
                </div>
                
                <a href="admin-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_scouts'] ?? 0; ?></h3>
                <p>Total Scouts</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['active_scouts'] ?? 0; ?></h3>
                <p>Active Scouts</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['inactive_scouts'] ?? 0; ?></h3>
                <p>Inactive Scouts</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['avg_performance'] ?? '0.0'; ?>/10</h3>
                <p>Average Performance Score</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filters-header">
                <h3>Filter Scouts</h3>
                <a href="admin-add-scout.php" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Add New Scout
                </a>
            </div>
            
            <form method="GET" action="" class="filters-grid">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Name, email, region..." value="<?php safeEcho($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Country</label>
                    <select name="country" class="filter-select">
                        <option value="all">All Countries</option>
                        <?php foreach ($countries as $country_option): ?>
                            <option value="<?php safeEcho($country_option); ?>" <?php echo $country === $country_option ? 'selected' : ''; ?>>
                                <?php safeEcho($country_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Specialization</label>
                    <select name="specialization" class="filter-select">
                        <option value="all">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php safeEcho($spec); ?>" <?php echo $specialization === $spec ? 'selected' : ''; ?>>
                                <?php safeEcho($spec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                    <a href="admin-scouts.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Scouts Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>Scouts List (<?php echo count($scouts); ?>)</h3>
                <div class="action-buttons">
                    <button class="btn-icon" title="Export to CSV">
                        <i class="fas fa-file-csv"></i>
                    </button>
                    <button class="btn-icon" title="Print">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
            
            <?php if (empty($scouts)): ?>
                <div class="empty-state">
                    <i class="fas fa-binoculars"></i>
                    <h3>No Scouts Found</h3>
                    <p>Try adjusting your filters or add new scouts</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Scout</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Specialization</th>
                            <th>Performance</th>
                            <th>Reports</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scouts as $scout): 
                            $full_name = $scout['full_name'] ?? '';
                            $email = $scout['email'] ?? '';
                            $phone = $scout['phone'] ?? '';
                            $country_val = $scout['country'] ?? '';
                            $region = $scout['region'] ?? '';
                            $specialization_val = $scout['specialization'] ?? '';
                            $login_username = $scout['login_username'] ?? '';
                            $performance_score = $scout['performance_score'] ?? 0;
                            $total_reports = $scout['total_reports'] ?? 0;
                            $approved_reports = $scout['approved_reports'] ?? 0;
                            $matches_assigned = $scout['matches_assigned'] ?? 0;
                            $scout_status = $scout['status'] ?? 'pending';
                            $scout_id = $scout['id'] ?? 0;
                            
                            // Determine score class
                            $score_class = '';
                            if ($performance_score >= 8) $score_class = 'score-high';
                            elseif ($performance_score >= 6) $score_class = 'score-medium';
                            else $score_class = 'score-low';
                            
                            // Determine status class
                            $status_class = '';
                            if ($scout_status === 'active') $status_class = 'status-active';
                            elseif ($scout_status === 'inactive') $status_class = 'status-inactive';
                            else $status_class = 'status-pending';
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                        <?php echo strtoupper(substr(safeOutput($full_name), 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?php safeEcho($full_name); ?></div>
                                        <?php if ($login_username): ?>
                                        <div style="font-size: 12px; color: var(--gray);">@<?php safeEcho($login_username); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php safeEcho($email); ?></div>
                                <div style="font-size: 12px; color: var(--gray);"><?php safeEcho($phone); ?></div>
                            </td>
                            <td>
                                <div><?php safeEcho($country_val); ?></div>
                                <div style="font-size: 12px; color: var(--gray);"><?php safeEcho($region); ?></div>
                            </td>
                            <td>
                                <div style="display: inline-block; padding: 4px 12px; background: #f3f4f6; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                    <?php safeEcho($specialization_val); ?>
                                </div>
                            </td>
                            <td>
                                <div class="performance-score <?php echo $score_class; ?>">
                                    <i class="fas fa-chart-line"></i>
                                    <?php echo number_format($performance_score, 1); ?>/10
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div style="font-size: 12px;">
                                        <span style="color: var(--secondary);">✓ <?php echo $approved_reports; ?> approved</span> / 
                                        <span><?php echo $total_reports; ?> total</span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray);">
                                        <?php echo $matches_assigned; ?> matches assigned
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($scout_status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="admin-view-scout.php?id=<?php echo $scout_id; ?>" class="btn-icon" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="admin-edit-scout.php?id=<?php echo $scout_id; ?>" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (hasPermission('scouts', 'edit')): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="scout_id" value="<?php echo $scout_id; ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="status" value="<?php echo $scout_status === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn-icon" title="<?php echo $scout_status === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (hasPermission('scouts', 'delete')): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this scout?');" style="display: inline;">
                                        <input type="hidden" name="scout_id" value="<?php echo $scout_id; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn-icon" title="Delete" style="color: var(--danger);">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <button class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="filters-container">
            <h3 style="margin-bottom: 20px;">Performance Overview</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="margin-bottom: 10px; font-size: 14px; color: var(--gray);">Top Performing Scouts</h4>
                    <?php 
                    $top_scouts = array_slice($scouts, 0, 3);
                    foreach ($top_scouts as $top_scout): 
                        $top_name = $top_scout['full_name'] ?? '';
                        $top_score = $top_scout['performance_score'] ?? 0;
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
                                <?php echo strtoupper(substr(safeOutput($top_name), 0, 2)); ?>
                            </div>
                            <span style="font-weight: 500;"><?php safeEcho($top_name); ?></span>
                        </div>
                        <span style="font-weight: 600; color: var(--secondary);"><?php echo number_format($top_score, 1); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 10px; font-size: 14px; color: var(--gray);">Specialization Distribution</h4>
                    <div style="background: #f8fafc; border-radius: 8px; padding: 20px;">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php 
                            $spec_counts = [];
                            foreach ($scouts as $scout) {
                                $spec = $scout['specialization'] ?? 'Unknown';
                                $spec_counts[$spec] = ($spec_counts[$spec] ?? 0) + 1;
                            }
                            foreach ($spec_counts as $spec => $count):
                                $percentage = count($scouts) > 0 ? ($count / count($scouts)) * 100 : 0;
                            ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="font-size: 13px;"><?php safeEcho($spec); ?></span>
                                    <span style="font-weight: 600;"><?php echo $count; ?></span>
                                </div>
                                <div style="height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--primary); width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Global search
        document.getElementById('globalSearch').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                window.location.href = 'admin-scouts.php?search=' + encodeURIComponent(this.value);
            }
        });

        // Status update confirmation
        document.querySelectorAll('form[action="update_status"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const currentStatus = this.querySelector('input[name="status"]').value;
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                const action = newStatus === 'active' ? 'activate' : 'deactivate';
                
                if (!confirm(`Are you sure you want to ${action} this scout?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>