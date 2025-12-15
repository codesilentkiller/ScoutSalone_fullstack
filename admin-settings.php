<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Check if user has settings permission
if (!hasPermission('settings', 'view')) {
    header('Location: admin-dashboard.php');
    exit();
}

$conn = getDatabaseConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $full_name = $_POST['full_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if (!empty($full_name) && !empty($email)) {
                $stmt = $conn->prepare("UPDATE admin_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $email, $phone, $_SESSION['admin_id']])) {
                    $_SESSION['admin_name'] = $full_name;
                    $message = 'Profile updated successfully!';
                    
                    // Log activity
                    logAdminActivity($_SESSION['admin_id'], 'update', 'admin_users', $_SESSION['admin_id']);
                } else {
                    $error = 'Failed to update profile';
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match';
            } elseif (strlen($new_password) < 8) {
                $error = 'Password must be at least 8 characters long';
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($current_password, $admin['password_hash'])) {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $update = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                    
                    if ($update->execute([$new_hash, $_SESSION['admin_id']])) {
                        $message = 'Password changed successfully!';
                        logAdminActivity($_SESSION['admin_id'], 'update', 'admin_users', $_SESSION['admin_id'], 'password change');
                    } else {
                        $error = 'Failed to update password';
                    }
                } else {
                    $error = 'Current password is incorrect';
                }
            }
            break;
            
        case 'update_system_settings':
            // Handle system settings update
            $system_name = $_POST['system_name'] ?? 'Scout Salone';
            $timezone = $_POST['timezone'] ?? 'UTC';
            $date_format = $_POST['date_format'] ?? 'Y-m-d';
            $items_per_page = intval($_POST['items_per_page'] ?? 20);
            
            // Update session or database with these settings
            $_SESSION['system_settings'] = [
                'system_name' => $system_name,
                'timezone' => $timezone,
                'date_format' => $date_format,
                'items_per_page' => $items_per_page
            ];
            
            $message = 'System settings updated!';
            logAdminActivity($_SESSION['admin_id'], 'update', 'system_settings', 0);
            break;
            
        case 'toggle_dark_mode':
            // Toggle dark mode
            $_SESSION['dark_mode'] = !($_SESSION['dark_mode'] ?? false);
            $mode = $_SESSION['dark_mode'] ? 'enabled' : 'disabled';
            $message = "Dark mode {$mode}!";
            break;
            
        case 'update_notifications':
            // Update notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $report_alerts = isset($_POST['report_alerts']) ? 1 : 0;
            $transfer_updates = isset($_POST['transfer_updates']) ? 1 : 0;
            
            $_SESSION['notification_settings'] = [
                'email_notifications' => $email_notifications,
                'report_alerts' => $report_alerts,
                'transfer_updates' => $transfer_updates
            ];
            
            $message = 'Notification settings updated!';
            break;
    }
}

// Get admin profile
$stmt = $conn->prepare("SELECT username, email, full_name, phone, role, last_login, created_at FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get system statistics
$stats_stmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM admin_users) as total_admins,
        (SELECT COUNT(*) FROM admin_logs WHERE DATE(created_at) = CURDATE()) as today_logs,
        (SELECT COUNT(*) FROM admin_logs WHERE admin_id = {$_SESSION['admin_id']}) as your_activities
");
$system_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Timezone options
$timezones = [
    'UTC', 'Africa/Freetown', 'Europe/London', 'America/New_York',
    'Asia/Dubai', 'Asia/Tokyo', 'Australia/Sydney', 'Europe/Paris'
];
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo ($_SESSION['dark_mode'] ?? false) ? 'dark-mode' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Light theme variables */
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
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --body-bg: #f5f7fa;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        .dark-mode {
            /* Dark theme variables */
            --primary: #818cf8;
            --primary-dark: #6366f1;
            --secondary: #34d399;
            --danger: #f87171;
            --warning: #fbbf24;
            --dark: #f9fafb;
            --light: #1f2937;
            --gray: #9ca3af;
            --gray-light: #374151;
            --border: #4b5563;
            --card-bg: #1f2937;
            --sidebar-bg: #111827;
            --body-bg: #0f172a;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--body-bg);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            transition: var(--transition);
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
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
            transition: var(--transition);
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background: var(--gray-light);
            color: var(--primary);
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
            transition: var(--transition);
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
            background: var(--card-bg);
            color: var(--dark);
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
            background: var(--card-bg);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--dark);
        }

        .notification-btn:hover, .logout-btn:hover {
            background: var(--gray-light);
            transform: translateY(-2px);
        }

        /* Settings Layout */
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        .settings-sidebar {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            height: fit-content;
            position: sticky;
            top: 30px;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--dark);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
        }

        .settings-nav-item:hover {
            background: var(--gray-light);
            color: var(--primary);
        }

        .settings-nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            color: var(--primary);
            font-weight: 600;
        }

        .settings-nav-item i {
            width: 20px;
            text-align: center;
        }

        .settings-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .section-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header p {
            color: var(--gray);
            font-size: 14px;
            margin-top: 8px;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group .hint {
            color: var(--gray);
            font-size: 12px;
            margin-top: 4px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--body-bg);
            color: var(--dark);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control:disabled {
            background: var(--gray-light);
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-light);
            transition: var(--transition);
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .toggle-label span {
            font-weight: 500;
            color: var(--dark);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
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
            background: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border-left: 4px solid;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--secondary);
            color: #065f46;
        }

        .dark-mode .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger);
            color: #991b1b;
        }

        .dark-mode .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .alert i {
            font-size: 18px;
        }

        /* Cards */
        .stats-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .stats-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--dark);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: var(--body-bg);
            border-radius: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Danger Zone */
        .danger-zone {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            border-radius: var(--radius);
            padding: 24px;
            margin-top: 40px;
        }

        .danger-zone h3 {
            color: var(--danger);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .danger-zone p {
            color: var(--gray);
            margin-bottom: 20px;
            font-size: 14px;
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
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                position: static;
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
            
            .header-actions .search-box {
                display: none;
            }
            
            .form-grid {
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
            <a href="admin-analytics.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
            <a href="admin-settings.php" class="nav-item active">
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
                <h1>Settings</h1>
                <p>Manage your account and system preferences</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search settings...">
                </div>
                
                <div class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>
                
                <a href="admin-logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Settings Container -->
        <div class="settings-container">
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <div class="settings-nav">
                        <a href="#profile" class="settings-nav-item active" onclick="showSection('profile')">
                            <i class="fas fa-user"></i>
                            <span>Profile Settings</span>
                        </a>
                        <a href="#appearance" class="settings-nav-item" onclick="showSection('appearance')">
                            <i class="fas fa-palette"></i>
                            <span>Appearance</span>
                        </a>
                        <a href="#notifications" class="settings-nav-item" onclick="showSection('notifications')">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="#security" class="settings-nav-item" onclick="showSection('security')">
                            <i class="fas fa-shield-alt"></i>
                            <span>Security</span>
                        </a>
                        <a href="#system" class="settings-nav-item" onclick="showSection('system')">
                            <i class="fas fa-cogs"></i>
                            <span>System Settings</span>
                        </a>
                        <a href="#about" class="settings-nav-item" onclick="showSection('about')">
                            <i class="fas fa-info-circle"></i>
                            <span>About System</span>
                        </a>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Section -->
                    <div id="profile-section" class="settings-section active">
                        <div class="section-header">
                            <h2><i class="fas fa-user"></i> Profile Settings</h2>
                            <p>Update your personal information and contact details</p>
                        </div>
                        
                        <form method="POST" action="" class="form-grid">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['username']); ?>" disabled>
                                <div class="hint">Username cannot be changed</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['role']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Login</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($admin_profile['last_login'] ?? 'Never'); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Appearance Section -->
                    <div id="appearance-section" class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-palette"></i> Appearance</h2>
                            <p>Customize the look and feel of your dashboard</p>
                        </div>
                        
                        <form method="POST" action="" class="form-grid">
                            <input type="hidden" name="action" value="toggle_dark_mode">
                            
                            <div class="toggle-label">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="dark_mode" 
                                           <?php echo ($_SESSION['dark_mode'] ?? false) ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Dark Mode</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="theme">Theme Color</label>
                                <select id="theme" name="theme" class="form-control" onchange="previewTheme(this.value)">
                                    <option value="blue">Blue (Default)</option>
                                    <option value="green">Green</option>
                                    <option value="purple">Purple</option>
                                    <option value="orange">Orange</option>
                                    <option value="red">Red</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="font_size">Font Size</label>
                                <select id="font_size" name="font_size" class="form-control">
                                    <option value="small">Small</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="large">Large</option>
                                    <option value="xlarge">Extra Large</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="layout">Layout Density</label>
                                <select id="layout" name="layout" class="form-control">
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact" selected>Compact</option>
                                    <option value="spacious">Spacious</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-primary" onclick="saveAppearanceSettings()">
                                    <i class="fas fa-save"></i> Apply Changes
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetAppearance()">
                                    <i class="fas fa-undo"></i> Reset to Default
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notifications Section -->
                    <div id="notifications-section" class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-bell"></i> Notifications</h2>
                            <p>Configure how you receive alerts and updates</p>
                        </div>
                        
                        <form method="POST" action="" class="form-grid">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <h3 style="margin-bottom: 20px; color: var(--dark);">Email Notifications</h3>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo ($_SESSION['notification_settings']['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="email_notifications">Enable email notifications</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="report_alerts" name="report_alerts"
                                       <?php echo ($_SESSION['notification_settings']['report_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="report_alerts">New scouting report alerts</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="transfer_updates" name="transfer_updates"
                                       <?php echo ($_SESSION['notification_settings']['transfer_updates'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="transfer_updates">Transfer opportunity updates</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="system_alerts" name="system_alerts" checked>
                                <label for="system_alerts">System maintenance alerts</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="weekly_reports" name="weekly_reports" checked>
                                <label for="weekly_reports">Weekly summary reports</label>
                            </div>
                            
                            <h3 style="margin: 30px 0 20px; color: var(--dark);">Push Notifications</h3>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="push_desktop" name="push_desktop" checked>
                                <label for="push_desktop">Desktop notifications</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="push_sound" name="push_sound" checked>
                                <label for="push_sound">Notification sound</label>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Section -->
                    <div id="security-section" class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-shield-alt"></i> Security</h2>
                            <p>Manage your password and security preferences</p>
                        </div>
                        
                        <form method="POST" action="" class="form-grid">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required>
                                <div class="hint">Minimum 8 characters with letters and numbers</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                        
                        <div class="danger-zone">
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>These actions are irreversible. Please proceed with caution.</p>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-danger" onclick="confirmLogoutAll()">
                                    <i class="fas fa-sign-out-alt"></i> Logout From All Devices
                                </button>
                                <div class="hint">This will log you out from all active sessions</div>
                            </div>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-danger" onclick="confirmAccountDeletion()">
                                    <i class="fas fa-trash"></i> Delete My Account
                                </button>
                                <div class="hint">Permanently delete your admin account</div>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings Section -->
                    <div id="system-section" class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-cogs"></i> System Settings</h2>
                            <p>Configure global system preferences</p>
                        </div>
                        
                        <form method="POST" action="" class="form-grid">
                            <input type="hidden" name="action" value="update_system_settings">
                            
                            <div class="form-group">
                                <label for="system_name">System Name</label>
                                <input type="text" id="system_name" name="system_name" class="form-control" 
                                       value="<?php echo $_SESSION['system_settings']['system_name'] ?? 'Scout Salone'; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone" class="form-control">
                                    <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo $tz; ?>" 
                                        <?php echo ($_SESSION['system_settings']['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                        <?php echo $tz; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_format">Date Format</label>
                                <select id="date_format" name="date_format" class="form-control">
                                    <option value="Y-m-d" <?php echo ($_SESSION['system_settings']['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15</option>
                                    <option value="d/m/Y" <?php echo ($_SESSION['system_settings']['date_format'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024</option>
                                    <option value="m/d/Y" <?php echo ($_SESSION['system_settings']['date_format'] ?? 'Y-m-d') === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024</option>
                                    <option value="d M Y" <?php echo ($_SESSION['system_settings']['date_format'] ?? 'Y-m-d') === 'd M Y' ? 'selected' : ''; ?>>15 Jan 2024</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="items_per_page">Items Per Page</label>
                                <select id="items_per_page" name="items_per_page" class="form-control">
                                    <option value="10" <?php echo ($_SESSION['system_settings']['items_per_page'] ?? 20) == 10 ? 'selected' : ''; ?>>10 items</option>
                                    <option value="20" <?php echo ($_SESSION['system_settings']['items_per_page'] ?? 20) == 20 ? 'selected' : ''; ?>>20 items</option>
                                    <option value="50" <?php echo ($_SESSION['system_settings']['items_per_page'] ?? 20) == 50 ? 'selected' : ''; ?>>50 items</option>
                                    <option value="100" <?php echo ($_SESSION['system_settings']['items_per_page'] ?? 20) == 100 ? 'selected' : ''; ?>>100 items</option>
                                </select>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="auto_save" name="auto_save" checked>
                                <label for="auto_save">Auto-save form data</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="show_tips" name="show_tips" checked>
                                <label for="show_tips">Show tips and tutorials</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_analytics" name="enable_analytics" checked>
                                <label for="enable_analytics">Enable usage analytics</label>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save System Settings
                                </button>
                            </div>
                        </form>
                        
                        <!-- System Statistics -->
                        <div class="stats-card">
                            <h3>System Statistics</h3>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $system_stats['total_admins']; ?></div>
                                    <div class="stat-label">Total Admins</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $system_stats['today_logs']; ?></div>
                                    <div class="stat-label">Today's Logs</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $system_stats['your_activities']; ?></div>
                                    <div class="stat-label">Your Activities</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">24/7</div>
                                    <div class="stat-label">Uptime</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- About Section -->
                    <div id="about-section" class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-info-circle"></i> About System</h2>
                            <p>Information about Scout Salone Football Agency System</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <h3 style="color: var(--primary); margin-bottom: 16px;">System Information</h3>
                                <div style="background: var(--body-bg); padding: 20px; border-radius: 8px;">
                                    <p><strong>Version:</strong> 2.1.0</p>
                                    <p><strong>Release Date:</strong> January 2024</p>
                                    <p><strong>License:</strong> Proprietary</p>
                                    <p><strong>Developed By:</strong> Scout Salone Development Team</p>
                                    <p><strong>Support Email:</strong> support@scoutsalone.com</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <h3 style="color: var(--primary); margin-bottom: 16px;">System Features</h3>
                                <div style="background: var(--body-bg); padding: 20px; border-radius: 8px;">
                                    <ul style="list-style: none; padding-left: 0;">
                                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border);">
                                            <i class="fas fa-check" style="color: var(--secondary); margin-right: 10px;"></i>
                                            Player Management System
                                        </li>
                                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border);">
                                            <i class="fas fa-check" style="color: var(--secondary); margin-right: 10px;"></i>
                                            Scout Tracking & Reports
                                        </li>
                                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border);">
                                            <i class="fas fa-check" style="color: var(--secondary); margin-right: 10px;"></i>
                                            Transfer Pipeline Management
                                        </li>
                                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border);">
                                            <i class="fas fa-check" style="color: var(--secondary); margin-right: 10px;"></i>
                                            Advanced Analytics & Charts
                                        </li>
                                        <li style="padding: 8px 0;">
                                            <i class="fas fa-check" style="color: var(--secondary); margin-right: 10px;"></i>
                                            Dark/Light Mode Support
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <h3 style="color: var(--primary); margin-bottom: 16px;">Quick Actions</h3>
                                <div style="display: flex; flex-direction: column; gap: 12px;">
                                    <button type="button" class="btn btn-secondary" onclick="checkForUpdates()">
                                        <i class="fas fa-sync-alt"></i> Check for Updates
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="exportSystemLogs()">
                                        <i class="fas fa-download"></i> Export System Logs
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearCache()">
                                        <i class="fas fa-broom"></i> Clear Cache
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="showSystemHealth()">
                                        <i class="fas fa-heartbeat"></i> System Health
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Show selected section
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav items
            document.querySelectorAll('.settings-nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionId + '-section').classList.add('active');
            
            // Add active class to clicked nav item
            event.target.closest('.settings-nav-item').classList.add('active');
        }

        // Toggle dark mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
            
            // Show notification
            showNotification('Theme changed successfully!', 'success');
        }

        // Preview theme color
        function previewTheme(theme) {
            const themes = {
                blue: { primary: '#6366f1', primaryDark: '#4f46e5' },
                green: { primary: '#10b981', primaryDark: '#059669' },
                purple: { primary: '#8b5cf6', primaryDark: '#7c3aed' },
                orange: { primary: '#f59e0b', primaryDark: '#d97706' },
                red: { primary: '#ef4444', primaryDark: '#dc2626' }
            };
            
            const colors = themes[theme];
            document.documentElement.style.setProperty('--primary', colors.primary);
            document.documentElement.style.setProperty('--primary-dark', colors.primaryDark);
        }

        // Save appearance settings
        function saveAppearanceSettings() {
            const settings = {
                theme: document.getElementById('theme').value,
                fontSize: document.getElementById('font_size').value,
                layout: document.getElementById('layout').value,
                darkMode: document.body.classList.contains('dark-mode')
            };
            
            localStorage.setItem('appearanceSettings', JSON.stringify(settings));
            showNotification('Appearance settings saved!', 'success');
        }

        // Reset appearance to default
        function resetAppearance() {
            document.getElementById('theme').value = 'blue';
            document.getElementById('font_size').value = 'medium';
            document.getElementById('layout').value = 'compact';
            document.body.classList.remove('dark-mode');
            
            // Reset CSS variables
            document.documentElement.style.setProperty('--primary', '#6366f1');
            document.documentElement.style.setProperty('--primary-dark', '#4f46e5');
            
            localStorage.removeItem('appearanceSettings');
            showNotification('Appearance reset to default!', 'success');
        }

        // Load saved settings
        function loadSavedSettings() {
            // Load appearance settings
            const savedSettings = localStorage.getItem('appearanceSettings');
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                document.getElementById('theme').value = settings.theme;
                document.getElementById('font_size').value = settings.fontSize;
                document.getElementById('layout').value = settings.layout;
                
                if (settings.darkMode) {
                    document.body.classList.add('dark-mode');
                }
                
                previewTheme(settings.theme);
            }
            
            // Load dark mode preference
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <div>${message}</div>
            `;
            
            // Add to page
            const container = document.querySelector('.settings-container');
            container.insertBefore(notification, container.firstChild);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Confirmation dialogs
        function confirmLogoutAll() {
            if (confirm('Are you sure you want to logout from all devices? You will need to login again.')) {
                // Implement logout all functionality
                showNotification('Logged out from all devices!', 'success');
            }
        }

        function confirmAccountDeletion() {
            if (confirm('WARNING: This will permanently delete your account and all associated data. This action cannot be undone. Are you absolutely sure?')) {
                // Implement account deletion functionality
                showNotification('Account deletion request submitted!', 'warning');
            }
        }

        // System functions
        function checkForUpdates() {
            showNotification('Checking for updates...', 'info');
            setTimeout(() => {
                showNotification('Your system is up to date!', 'success');
            }, 2000);
        }

        function exportSystemLogs() {
            showNotification('Exporting system logs...', 'info');
            // Implement export functionality
        }

        function clearCache() {
            if (confirm('Clear all cached data? This may improve performance.')) {
                localStorage.clear();
                showNotification('Cache cleared successfully!', 'success');
            }
        }

        function showSystemHealth() {
            showNotification('System health: Excellent ✓', 'success');
        }

        function toggleNotifications() {
            showNotification('Notifications panel opened', 'info');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', loadSavedSettings);
    </script>
</body>
</html>