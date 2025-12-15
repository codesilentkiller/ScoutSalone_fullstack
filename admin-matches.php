<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

$conn = getDatabaseConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_match'])) {
        // Add new match
        $home_team = $_POST['home_team'] ?? '';
        $away_team = $_POST['away_team'] ?? '';
        $competition = $_POST['competition'] ?? '';
        $match_date = $_POST['match_date'] ?? '';
        $match_time = $_POST['match_time'] ?? '';
        $venue = $_POST['venue'] ?? '';
        $country = $_POST['country'] ?? '';
        $assigned_scout_id = $_POST['assigned_scout_id'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        if (empty($home_team) || empty($away_team) || empty($match_date)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $sql = "INSERT INTO matches (home_team, away_team, competition, match_date, match_time, venue, country, assigned_scout_id, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $home_team, $away_team, $competition, $match_date, $match_time, 
                    $venue, $country, $assigned_scout_id ?: null, $notes
                ]);
                
                // Log the action
                logAdminAction($conn, $_SESSION['admin_id'], 'create', 'matches', $conn->lastInsertId());
                
                $message = 'Match added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding match: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_status'])) {
        // Update match status
        $match_id = $_POST['match_id'] ?? '';
        $status = $_POST['status'] ?? '';
        
        if ($match_id && $status) {
            try {
                $sql = "UPDATE matches SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$status, $match_id]);
                
                logAdminAction($conn, $_SESSION['admin_id'], 'update', 'matches', $match_id);
                
                $message = 'Match status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating match: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_match'])) {
        // Delete match
        $match_id = $_POST['match_id'] ?? '';
        
        if ($match_id) {
            try {
                $sql = "DELETE FROM matches WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$match_id]);
                
                logAdminAction($conn, $_SESSION['admin_id'], 'delete', 'matches', $match_id);
                
                $message = 'Match deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Error deleting match: ' . $e->getMessage();
            }
        }
    }
}

// Get all matches
$matches = [];
$scouts = [];

try {
    // Get matches with scout information
    $sql = "SELECT m.*, s.full_name as scout_name 
            FROM matches m 
            LEFT JOIN scouts s ON m.assigned_scout_id = s.id 
            ORDER BY m.match_date DESC, m.match_time DESC";
    $stmt = $conn->query($sql);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available scouts for assignment
    $scout_stmt = $conn->query("SELECT id, full_name FROM scouts WHERE status = 'active' ORDER BY full_name");
    $scouts = $scout_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error loading data: ' . $e->getMessage();
}

// Helper function to log admin actions
function logAdminAction($conn, $admin_id, $action, $table, $record_id) {
    $log_sql = "INSERT INTO admin_logs (admin_id, action, table_name, record_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([
        $admin_id, $action, $table, $record_id,
        $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matches Management - Scout Salone Admin</title>
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

        /* Sidebar (Same as dashboard) */
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

        /* Messages */
        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Two Column Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Add Match Form */
        .form-card {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            height: fit-content;
        }

        .form-card h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card h2 i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Matches Table */
        .table-card {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: var(--primary);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .matches-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .matches-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .matches-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .matches-table tr:hover {
            background: #f8fafc;
        }

        .match-teams {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .match-vs {
            color: var(--gray);
            font-weight: 400;
        }

        .match-details {
            font-size: 13px;
            color: var(--gray);
            margin-top: 4px;
        }

        .match-details i {
            margin-right: 6px;
            width: 16px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-ongoing { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

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
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            color: white;
            font-size: 14px;
        }

        .action-edit { background: var(--primary); }
        .action-delete { background: var(--danger); }
        .action-view { background: var(--secondary); }

        .action-btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
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
            margin-bottom: 24px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            background: #f8fafc;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .search-box input {
                width: 200px;
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
            <a href="admin-clubs.php" class="nav-item">
                <i class="fas fa-landmark"></i>
                <span>Clubs</span>
            </a>
            <a href="admin-transfers.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Transfers</span>
            </a>
            <a href="admin-matches.php" class="nav-item active">
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
                <h1>Matches Management</h1>
                <p>Schedule and manage football matches for scouting</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search matches..." id="searchMatches">
                </div>
                
                <a href="admin-dashboard.php" class="logout-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <?php
            $stats_sql = "SELECT 
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
                FROM matches";
            $stats_stmt = $conn->query($stats_sql);
            $match_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="stat-card">
                <h3><?php echo $match_stats['scheduled'] ?? 0; ?></h3>
                <p>Scheduled Matches</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo $match_stats['ongoing'] ?? 0; ?></h3>
                <p>Ongoing Matches</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo $match_stats['completed'] ?? 0; ?></h3>
                <p>Completed Matches</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo $match_stats['cancelled'] ?? 0; ?></h3>
                <p>Cancelled Matches</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Add Match Form -->
            <div class="form-card">
                <h2><i class="fas fa-plus-circle"></i> Schedule New Match</h2>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Home Team <span class="required">*</span></label>
                            <input type="text" name="home_team" class="form-input" 
                                   placeholder="e.g., East End Lions" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Away Team <span class="required">*</span></label>
                            <input type="text" name="away_team" class="form-input" 
                                   placeholder="e.g., Mighty Blackpool" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Competition</label>
                            <input type="text" name="competition" class="form-input" 
                                   placeholder="e.g., Sierra Leone Premier League">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Match Date <span class="required">*</span></label>
                            <input type="date" name="match_date" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Match Time</label>
                            <input type="time" name="match_time" class="form-input">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Venue <span class="required">*</span></label>
                            <input type="text" name="venue" class="form-input" 
                                   placeholder="e.g., Siaka Stevens Stadium, Freetown" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" 
                                   placeholder="e.g., Sierra Leone" value="Sierra Leone">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Assign Scout</label>
                            <select name="assigned_scout_id" class="form-select">
                                <option value="">-- Select Scout --</option>
                                <?php foreach ($scouts as $scout): ?>
                                <option value="<?php echo $scout['id']; ?>">
                                    <?php echo htmlspecialchars($scout['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-textarea" 
                                      placeholder="Additional match information..."></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_match" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Schedule Match
                    </button>
                </form>
            </div>

            <!-- Matches Table -->
            <div class="table-card">
                <div class="table-header">
                    <h2><i class="fas fa-list"></i> All Matches</h2>
                    <div class="table-filters">
                        <select id="filterStatus" class="form-select" style="width: auto;">
                            <option value="">All Status</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="matches-table">
                        <thead>
                            <tr>
                                <th>Match</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Scout</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($matches)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-calendar-times" style="font-size: 48px; color: var(--gray-light); margin-bottom: 16px;"></i>
                                    <p>No matches scheduled yet</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                            <tr data-status="<?php echo $match['status']; ?>">
                                <td>
                                    <div class="match-teams">
                                        <span><?php echo htmlspecialchars($match['home_team']); ?></span>
                                        <span class="match-vs">vs</span>
                                        <span><?php echo htmlspecialchars($match['away_team']); ?></span>
                                    </div>
                                    <div class="match-details">
                                        <?php if ($match['competition']): ?>
                                        <i class="fas fa-trophy"></i>
                                        <?php echo htmlspecialchars($match['competition']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($match['match_date'])); ?>
                                    <?php if ($match['match_time']): ?>
                                    <div class="match-details">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('g:i A', strtotime($match['match_time'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($match['venue']); ?>
                                    <?php if ($match['country']): ?>
                                    <div class="match-details">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($match['country']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $match['scout_name'] ? htmlspecialchars($match['scout_name']) : '<span style="color: var(--gray);">Not assigned</span>'; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_classes = [
                                        'scheduled' => 'status-scheduled',
                                        'ongoing' => 'status-ongoing',
                                        'completed' => 'status-completed',
                                        'cancelled' => 'status-cancelled'
                                    ];
                                    $status_text = ucfirst($match['status']);
                                    ?>
                                    <span class="status-badge <?php echo $status_classes[$match['status']]; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-view" 
                                                onclick="viewMatch(<?php echo $match['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn action-edit" 
                                                onclick="editMatch(<?php echo $match['id']; ?>)"
                                                title="Edit Match">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <button type="submit" name="delete_match" 
                                                    class="action-btn action-delete"
                                                    onclick="return confirm('Are you sure you want to delete this match?')"
                                                    title="Delete Match">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Match Details Modal -->
        <div class="modal" id="matchModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Match Details</h3>
                    <button class="close-modal" onclick="closeModal()">&times;</button>
                </div>
                <div id="matchDetails">
                    <!-- Details will be loaded here via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Edit Match Modal -->
        <div class="modal" id="editModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Match</h3>
                    <button class="close-modal" onclick="closeEditModal()">&times;</button>
                </div>
                <div id="editForm">
                    <!-- Edit form will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <script>
        // Search functionality
        document.getElementById('searchMatches').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.matches-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter by status
        document.getElementById('filterStatus').addEventListener('change', function(e) {
            const status = e.target.value;
            const rows = document.querySelectorAll('.matches-table tbody tr');
            
            rows.forEach(row => {
                if (!status || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // View match details
        function viewMatch(matchId) {
            fetch(`ajax/get-match-details.php?id=${matchId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('matchDetails').innerHTML = html;
                    document.getElementById('matchModal').classList.add('active');
                })
                .catch(error => {
                    document.getElementById('matchDetails').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i>
                            Error loading match details
                        </div>
                    `;
                    document.getElementById('matchModal').classList.add('active');
                });
        }

        // Edit match
        function editMatch(matchId) {
            fetch(`ajax/get-match-edit.php?id=${matchId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('editForm').innerHTML = html;
                    document.getElementById('editModal').classList.add('active');
                })
                .catch(error => {
                    document.getElementById('editForm').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i>
                            Error loading edit form
                        </div>
                    `;
                    document.getElementById('editModal').classList.add('active');
                });
        }

        // Close modals
        function closeModal() {
            document.getElementById('matchModal').classList.remove('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Set today's date as minimum for date input
        document.querySelector('input[name="match_date"]').min = new Date().toISOString().split('T')[0];

        // Auto-populate time with current time
        document.querySelector('input[name="match_time"]').value = new Date().toTimeString().slice(0,5);
    </script>
</body>
</html>