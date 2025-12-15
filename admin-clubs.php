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
if (!hasPermission('clubs', 'view')) {
    header('Location: admin-dashboard.php');
    exit();
}

$conn = getDatabaseConnection();
if (!$conn) die('Database connection failed');

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_club']) && hasPermission('clubs', 'create')) {
        $club_name = $_POST['club_name'] ?? '';
        $country = $_POST['country'] ?? '';
        $league = $_POST['league'] ?? '';
        $contact_person = $_POST['contact_person'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        $contact_phone = $_POST['contact_phone'] ?? '';
        $needs_positions = $_POST['needs_positions'] ?? '';
        $needs_age_range = $_POST['needs_age_range'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if (!empty($club_name)) {
            try {
                $sql = "INSERT INTO clubs (club_name, country, league, contact_person, contact_email, 
                         contact_phone, needs_positions, needs_age_range, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $club_name, $country, $league, $contact_person, $contact_email,
                    $contact_phone, $needs_positions, $needs_age_range, $notes, $status
                ]);
                
                // Log activity
                $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                       VALUES (?, 'create', 'clubs', ?)");
                $log->execute([$_SESSION['admin_id'], $conn->lastInsertId()]);
                
                $message = "Club added successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error adding club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['update_club']) && hasPermission('clubs', 'edit')) {
        $club_id = $_POST['club_id'] ?? '';
        $club_name = $_POST['club_name'] ?? '';
        $country = $_POST['country'] ?? '';
        $league = $_POST['league'] ?? '';
        $contact_person = $_POST['contact_person'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        $contact_phone = $_POST['contact_phone'] ?? '';
        $needs_positions = $_POST['needs_positions'] ?? '';
        $needs_age_range = $_POST['needs_age_range'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if (!empty($club_id)) {
            try {
                $sql = "UPDATE clubs SET 
                        club_name = ?, country = ?, league = ?, contact_person = ?, 
                        contact_email = ?, contact_phone = ?, needs_positions = ?, 
                        needs_age_range = ?, notes = ?, status = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $club_name, $country, $league, $contact_person, $contact_email,
                    $contact_phone, $needs_positions, $needs_age_range, $notes, $status,
                    $club_id
                ]);
                
                // Log activity
                $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                       VALUES (?, 'update', 'clubs', ?)");
                $log->execute([$_SESSION['admin_id'], $club_id]);
                
                $message = "Club updated successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error updating club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['delete_club']) && hasPermission('clubs', 'delete')) {
        $club_id = $_POST['club_id'] ?? '';
        
        if (!empty($club_id)) {
            try {
                $sql = "DELETE FROM clubs WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$club_id]);
                
                // Log activity
                $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                       VALUES (?, 'delete', 'clubs', ?)");
                $log->execute([$_SESSION['admin_id'], $club_id]);
                
                $message = "Club deleted successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error deleting club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Get all clubs with filters
$search = $_GET['search'] ?? '';
$country_filter = $_GET['country'] ?? '';
$status_filter = $_GET['status'] ?? '';
$league_filter = $_GET['league'] ?? '';

$sql = "SELECT * FROM clubs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (club_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($country_filter)) {
    $sql .= " AND country = ?";
    $params[] = $country_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($league_filter)) {
    $sql .= " AND league LIKE ?";
    $params[] = "%$league_filter%";
}

$sql .= " ORDER BY club_name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique countries for filter
$countries_stmt = $conn->query("SELECT DISTINCT country FROM clubs WHERE country IS NOT NULL ORDER BY country");
$countries = $countries_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique leagues for filter
$leagues_stmt = $conn->query("SELECT DISTINCT league FROM clubs WHERE league IS NOT NULL ORDER BY league");
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get club stats
$stats_sql = "SELECT 
    COUNT(*) as total_clubs,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_clubs,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_clubs,
    SUM(CASE WHEN status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted_clubs
    FROM clubs";
$stats_stmt = $conn->query($stats_sql);
$club_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Management - Scout Salone Admin</title>
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

        /* Sidebar (same as dashboard) */
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

        /* Message Alert */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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

        .stat-content h3 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 12px;
        }

        .icon-total { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .icon-active { background: linear-gradient(135deg, #10b981, #34d399); }
        .icon-inactive { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .icon-blacklisted { background: linear-gradient(135deg, #ef4444, #f87171); }

        /* Filters */
        .filters-card {
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

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
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
            background: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        /* Clubs Table */
        .table-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .table-actions {
            display: flex;
            gap: 12px;
        }

        .btn-add {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 16px 24px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fef3c7;
            color: #92400e;
        }

        .status-blacklisted {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-edit {
            background: var(--primary);
        }

        .btn-delete {
            background: var(--danger);
        }

        .btn-view {
            background: var(--secondary);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s ease;
        }

        .modal-close:hover {
            background: var(--gray-light);
        }

        .modal-body {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group label .required {
            color: var(--danger);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
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
            
            .header-actions {
                flex-wrap: wrap;
            }
            
            .search-box input {
                width: 200px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .table-actions {
                justify-content: flex-end;
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
            <a href="admin-scouts.php" class="nav-item">
                <i class="fas fa-binoculars"></i>
                <span>Scouts</span>
            </a>
            <a href="admin-reports.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="admin-clubs.php" class="nav-item active">
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
                <h1>Club Management</h1>
                <p>Manage partner clubs, contacts, and opportunities</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="globalSearch" placeholder="Search clubs...">
                </div>
                
                <a href="admin-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Club Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total">
                    <i class="fas fa-landmark"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $club_stats['total_clubs']; ?></h3>
                    <p>Total Clubs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-active">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $club_stats['active_clubs']; ?></h3>
                    <p>Active Clubs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-inactive">
                    <i class="fas fa-pause-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $club_stats['inactive_clubs']; ?></h3>
                    <p>Inactive Clubs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-blacklisted">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $club_stats['blacklisted_clubs']; ?></h3>
                    <p>Blacklisted</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <h3>Filter Clubs</h3>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
            <form method="GET" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Club name, contact person...">
                    </div>
                    
                    <div class="filter-group">
                        <label>Country</label>
                        <select name="country">
                            <option value="">All Countries</option>
                            <?php foreach ($countries as $country): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" 
                                <?php echo $country_filter === $country ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>League</label>
                        <select name="league">
                            <option value="">All Leagues</option>
                            <?php foreach ($leagues as $league): ?>
                            <option value="<?php echo htmlspecialchars($league); ?>" 
                                <?php echo $league_filter === $league ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($league); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="blacklisted" <?php echo $status_filter === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                        </select>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Clubs Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Partner Clubs (<?php echo count($clubs); ?>)</h3>
                <div class="table-actions">
                    <?php if (hasPermission('clubs', 'create')): ?>
                    <a href="#" class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Club
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-secondary" onclick="exportClubs()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th>Country</th>
                            <th>League</th>
                            <th>Contact Person</th>
                            <th>Contact Email</th>
                            <th>Needs</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clubs)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: var(--gray); margin-bottom: 16px;"></i>
                                <p style="color: var(--gray);">No clubs found. Add your first club!</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($clubs as $club): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($club['club_name']); ?></strong>
                                <?php if (!empty($club['notes'])): ?>
                                <br><small style="color: var(--gray);"><?php echo substr(htmlspecialchars($club['notes']), 0, 50); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($club['country']); ?></td>
                            <td><?php echo htmlspecialchars($club['league']); ?></td>
                            <td><?php echo htmlspecialchars($club['contact_person']); ?></td>
                            <td>
                                <?php if (!empty($club['contact_email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($club['contact_email']); ?>" style="color: var(--primary);">
                                    <?php echo htmlspecialchars($club['contact_email']); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($club['needs_positions'])): ?>
                                <span style="font-size: 12px; background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                    <?php echo htmlspecialchars($club['needs_positions']); ?>
                                </span>
                                <br>
                                <small style="color: var(--gray);">Age: <?php echo htmlspecialchars($club['needs_age_range']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = 'status-' . $club['status'];
                                $status_text = ucfirst($club['status']);
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="#" class="btn-action btn-view" onclick="viewClub(<?php echo $club['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('clubs', 'edit')): ?>
                                    <a href="#" class="btn-action btn-edit" onclick="editClub(<?php echo $club['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('clubs', 'delete')): ?>
                                    <a href="#" class="btn-action btn-delete" onclick="deleteClub(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['club_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="clubModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Club</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="clubForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="club_id" id="club_id">
                    <input type="hidden" name="add_club" id="add_club" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Club Name <span class="required">*</span></label>
                            <input type="text" name="club_name" id="club_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="country" id="country" list="countries">
                            <datalist id="countries">
                                <option value="Sierra Leone">
                                <option value="Ghana">
                                <option value="Nigeria">
                                <option value="Liberia">
                                <option value="Guinea">
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label>League</label>
                            <input type="text" name="league" id="league">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Person</label>
                            <input type="text" name="contact_person" id="contact_person">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" id="contact_email">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input type="tel" name="contact_phone" id="contact_phone">
                        </div>
                        
                        <div class="form-group">
                            <label>Positions Needed</label>
                            <input type="text" name="needs_positions" id="needs_positions" 
                                   placeholder="e.g., Forward, Goalkeeper, Defender">
                        </div>
                        
                        <div class="form-group">
                            <label>Age Range Needed</label>
                            <input type="text" name="needs_age_range" id="needs_age_range" 
                                   placeholder="e.g., 18-25, 16-21">
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blacklisted">Blacklisted</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Notes</label>
                            <textarea name="notes" id="notes" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmit">Add Club</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this club?</p>
                <form id="deleteForm" method="POST" style="margin-top: 24px;">
                    <input type="hidden" name="club_id" id="delete_club_id">
                    <input type="hidden" name="delete_club" value="1">
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Club</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Club';
            document.getElementById('modalSubmit').textContent = 'Add Club';
            document.getElementById('clubForm').reset();
            document.getElementById('club_id').value = '';
            document.getElementById('add_club').value = '1';
            document.getElementById('clubModal').style.display = 'flex';
        }

        function editClub(clubId) {
            // In a real app, you would fetch club data via AJAX
            // For now, we'll redirect to an edit page or show a simplified modal
            alert('Edit functionality would fetch club ' + clubId + ' data via AJAX');
            // You could implement AJAX to populate the form
        }

        function viewClub(clubId) {
            window.location.href = 'admin-club-details.php?id=' + clubId;
        }

        function deleteClub(clubId, clubName) {
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete <strong>${clubName}</strong>? This action cannot be undone.`;
            document.getElementById('delete_club_id').value = clubId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('clubModal').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Filter functions
        function resetFilters() {
            window.location.href = 'admin-clubs.php';
        }

        function exportClubs() {
            // In a real app, this would generate a CSV/Excel file
            alert('Export functionality would generate a CSV file with all club data');
        }

        // Global search
        document.getElementById('globalSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('clubModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Add sample data for demonstration
        document.addEventListener('DOMContentLoaded', function() {
            // Add some sample data if table is empty (for demo)
            const tableBody = document.querySelector('tbody');
            if (tableBody && tableBody.children.length === 1 && 
                tableBody.children[0].children[0].colSpan) {
                // Table is empty, show demo message
            }
        });
    </script>
</body>
</html>