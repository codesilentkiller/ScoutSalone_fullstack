<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Check permission
if (!hasPermission('reports', 'view')) {
    header('Location: admin-dashboard.php');
    exit();
}

$conn = getDatabaseConnection();

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$scout_id = $_GET['scout_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Get all reports with filters
$reports = getScoutingReports($conn, [
    'status' => $status !== 'all' ? $status : null,
    'scout_id' => $scout_id,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'search' => $search
]);

// Get all scouts for filter dropdown
$scouts = $conn->query("SELECT id, full_name FROM scouts WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Get report statistics
$reportStats = getReportStatistics($conn);

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $report_id = $_POST['report_id'] ?? '';

    if ($action && $report_id && hasPermission('reports', 'edit')) {
        handleReportAction($conn, $report_id, $action, $_SESSION['admin_id']);
        header('Location: admin-reports.php');
        exit();
    }
}

function getScoutingReports($conn, $filters)
{
    $sql = "SELECT sr.*, 
                   s.full_name as scout_name,
                   u.full_name as player_name,
                   u.position as player_position,
                   u.current_club as player_club,
                   au.full_name as reviewer_name
            FROM scouting_reports sr
            LEFT JOIN scouts s ON sr.scout_id = s.id
            LEFT JOIN users u ON sr.player_id = u.id
            LEFT JOIN admin_users au ON sr.reviewed_by = au.id
            WHERE 1=1";

    $params = [];

    if (!empty($filters['status'])) {
        $sql .= " AND sr.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['scout_id'])) {
        $sql .= " AND sr.scout_id = ?";
        $params[] = $filters['scout_id'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(sr.created_at) >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(sr.created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (sr.report_code LIKE ? OR u.full_name LIKE ? OR s.full_name LIKE ?)";
        $search = "%{$filters['search']}%";
        $params = array_merge($params, [$search, $search, $search]);
    }

    $sql .= " ORDER BY sr.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReportStatistics($conn)
{
    $stats = [];

    // Total reports
    $stmt = $conn->query("SELECT COUNT(*) as total FROM scouting_reports");
    $stats['total'] = $stmt->fetch()['total'];

    // By status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM scouting_reports GROUP BY status");
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Last 30 days
    $stmt = $conn->query("SELECT COUNT(*) as count FROM scouting_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['last_30_days'] = $stmt->fetch()['count'];

    // Average rating
    $stmt = $conn->query("SELECT AVG(overall_potential) as avg_rating FROM scouting_reports WHERE overall_potential IS NOT NULL");
    $avg_rating = $stmt->fetch()['avg_rating'];
    $stats['avg_rating'] = $avg_rating ? round($avg_rating, 1) : 0;
    return $stats;
}

function handleReportAction($conn, $report_id, $action, $admin_id)
{
    $status_map = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'review' => 'under_review',
        'draft' => 'draft'
    ];

    if (isset($status_map[$action])) {
        $sql = "UPDATE scouting_reports SET status = ?, reviewed_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status_map[$action], $admin_id, $report_id]);

        // Log the action
        $log_sql = "INSERT INTO admin_logs (admin_id, action, table_name, record_id) VALUES (?, ?, 'scouting_reports', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([$admin_id, $action, $report_id]);

        return true;
    }

    return false;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scouting Reports - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #111827;
            --light: #f9fafb;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --border: #d1d5db;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
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

        /* Sidebar (from dashboard) */
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

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .page-title p {
            color: var(--gray);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
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
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .icon-total {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .icon-pending {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
        }

        .icon-approved {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .icon-rating {
            background: linear-gradient(135deg, #8b5cf6, #d946ef);
        }

        .stat-content h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

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
            font-weight: 600;
            color: var(--dark);
        }

        .filter-select,
        .filter-input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            width: 100%;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Reports Table */
        .reports-table-container {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
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
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reports-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .reports-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }

        .reports-table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-draft {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status-submitted {
            background: #fef3c7;
            color: #92400e;
        }

        .status-under_review {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #f8fafc;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .rating-badge .stars {
            color: #f59e0b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            color: white;
            font-size: 14px;
        }

        .btn-view {
            background: var(--primary);
        }

        .btn-approve {
            background: var(--secondary);
        }

        .btn-reject {
            background: var(--danger);
        }

        .btn-edit {
            background: var(--info);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
        }

        .page-link {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            color: var(--dark);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .page-link:hover,
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--gray-light);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .nav-item span {
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

            .reports-table {
                display: block;
                overflow-x: auto;
            }

            .top-bar {
                flex-direction: column;
                gap: 20px;
                align-items: stretch;
            }

            .header-actions {
                justify-content: center;
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
            <a href="admin-reports.php" class="nav-item active">
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
            <a href="admin-analytics.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Scouting Reports</h1>
                <p>Manage and review all scouting reports</p>
            </div>

            <div class="header-actions">
                <a href="admin-add-report.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    New Report
                </a>
                <a href="admin-export-reports.php" class="btn btn-outline">
                    <i class="fas fa-download"></i>
                    Export
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $reportStats['total']; ?></h3>
                    <p>Total Reports</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $reportStats['by_status']['submitted'] ?? 0; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $reportStats['by_status']['approved'] ?? 0; ?></h3>
                    <p>Approved Reports</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-rating">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $reportStats['avg_rating']; ?>/10</h3>
                    <p>Avg. Rating</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <h3>Filter Reports</h3>
                <a href="admin-reports.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px;">
                    <i class="fas fa-redo"></i>
                    Reset Filters
                </a>
            </div>

            <form method="GET" action="" class="filters-grid">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Submitted
                        </option>
                        <option value="under_review" <?php echo $status === 'under_review' ? 'selected' : ''; ?>>Under
                            Review</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Scout</label>
                    <select name="scout_id" class="filter-select">
                        <option value="">All Scouts</option>
                        <?php foreach ($scouts as $scout): ?>
                            <option value="<?php echo $scout['id']; ?>" <?php echo $scout_id == $scout['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($scout['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="filter-input"
                        value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="filter-input"
                        value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Player, Scout, or Report Code"
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Reports Table -->
        <div class="reports-table-container">
            <div class="table-header">
                <h3>Scouting Reports (<?php echo count($reports); ?>)</h3>
                <div class="table-actions">
                    <select class="filter-select" style="width: auto;"
                        onchange="window.location.href = 'admin-reports.php?status=' + this.value">
                        <option value="all">All Reports</option>
                        <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Pending Review
                        </option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    </select>
                </div>
            </div>

            <?php if (empty($reports)): ?>
                <div class="no-data">
                    <i class="fas fa-file-alt"></i>
                    <h3>No reports found</h3>
                    <p>Try adjusting your filters or create a new report</p>
                    <a href="admin-add-report.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        Create First Report
                    </a>
                </div>
            <?php else: ?>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Report Code</th>
                            <th>Player</th>
                            <th>Scout</th>
                            <th>Rating</th>
                            <th>Recommendation</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <strong
                                        style="color: var(--primary);"><?php echo htmlspecialchars($report['report_code']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($report['player_name']); ?></div>
                                    <div style="font-size: 12px; color: var(--gray);">
                                        <?php echo htmlspecialchars($report['player_position']); ?> •
                                        <?php echo htmlspecialchars($report['player_club']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($report['scout_name']); ?></td>
                                <td>
                                    <?php if ($report['overall_potential']): ?>
                                        <div class="rating-badge">
                                            <span class="stars">★★★★★</span>
                                            <span><?php echo $report['overall_potential']; ?>/10</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--gray); font-size: 12px;">Not rated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $rec_colors = [
                                        'sign_immediately' => 'var(--secondary)',
                                        'high_priority' => 'var(--warning)',
                                        'monitor' => 'var(--info)',
                                        'reject' => 'var(--danger)'
                                    ];
                                    $rec_labels = [
                                        'sign_immediately' => 'Sign Now',
                                        'high_priority' => 'High Priority',
                                        'monitor' => 'Monitor',
                                        'reject' => 'Reject'
                                    ];
                                    if ($report['recommendation']):
                                        ?>
                                        <span style="padding: 4px 8px; background: <?php echo $rec_colors[$report['recommendation']]; ?>20; 
                                  color: <?php echo $rec_colors[$report['recommendation']]; ?>; 
                                  border-radius: 6px; font-size: 12px; font-weight: 600;">
                                            <?php echo $rec_labels[$report['recommendation']]; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 14px;">
                                        <?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                    <div style="font-size: 12px; color: var(--gray);">
                                        <?php echo date('h:i A', strtotime($report['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin-view-report.php?id=<?php echo $report['id']; ?>"
                                            class="action-btn btn-view" title="View Report">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if (hasPermission('reports', 'edit')): ?>
                                            <?php if ($report['status'] === 'submitted' || $report['status'] === 'under_review'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn btn-approve" title="Approve Report"
                                                        onclick="return confirm('Approve this report?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="action-btn btn-reject" title="Reject Report"
                                                        onclick="return confirm('Reject this report?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <a href="admin-edit-report.php?id=<?php echo $report['id']; ?>"
                                                class="action-btn btn-edit" title="Edit Report">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <a href="#" class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="#" class="page-link active">1</a>
                    <a href="#" class="page-link">2</a>
                    <a href="#" class="page-link">3</a>
                    <span style="color: var(--gray);">...</span>
                    <a href="#" class="page-link">10</a>
                    <a href="#" class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Status filter quick actions
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('click', function () {
                const status = this.classList[1].replace('status-', '');
                window.location.href = `admin-reports.php?status=${status}`;
            });
        });

        // Report search
        document.querySelector('input[name="search"]').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Date range validation
        document.querySelector('input[name="date_from"]').addEventListener('change', function () {
            const dateTo = document.querySelector('input[name="date_to"]');
            if (dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });

        // Quick status update
        function updateReportStatus(reportId, status) {
            if (confirm(`Are you sure you want to mark this report as ${status}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const reportIdInput = document.createElement('input');
                reportIdInput.type = 'hidden';
                reportIdInput.name = 'report_id';
                reportIdInput.value = reportId;

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = status;

                form.appendChild(reportIdInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>