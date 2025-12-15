<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Get dashboard statistics
$conn = getDatabaseConnection();
$stats = getDashboardStats($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Scout Salone</title>
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
            display: flex;
        }

        /* Sidebar */
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

        .notification-btn,
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
        }

        .notification-btn:hover,
        .logout-btn:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .notification-btn .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--border);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .icon-players {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .icon-scouts {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .icon-reports {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
        }

        .icon-clubs {
            background: linear-gradient(135deg, #ef4444, #f87171);
        }

        .stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .trend-up {
            color: var(--secondary);
        }

        .trend-down {
            color: var(--danger);
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 12px;
            color: var(--gray);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 30px;
        }
         .home-btn button {
            background: linear-gradient(135deg, var(--secondary), #0da271);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home-btn button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }


        


        .quick-action {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .quick-action i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .quick-action h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar-header p,
            .nav-item span,
            .admin-info {
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

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .search-box input {
                width: 200px;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>⚽ ScoutSalone</h2>
            <p>Admin Dashboard</p>
        </div>

        <nav class="nav-menu">
            <a href="admin-dashboard.php" class="nav-item active">
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
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
            </div>

            <div class="header-actions">

                <!-- Home Button in Header -->
                <a href="home.php" class="home-btn" style="text-decoration: none;">
                    <button>
                        <i class="fas fa-home"></i>
                        Go to Home
                    </button>
                </a>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search players, scouts, reports...">
                </div>

                <div class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>

                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3><?php echo $stats['total_players']; ?></h3>
                        <p>Total Players</p>
                    </div>
                    <div class="stat-icon icon-players">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% this month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3><?php echo $stats['active_scouts']; ?></h3>
                        <p>Active Scouts</p>
                    </div>
                    <div class="stat-icon icon-scouts">
                        <i class="fas fa-binoculars"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+5% this month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3><?php echo $stats['pending_reports']; ?></h3>
                        <p>Pending Reports</p>
                    </div>
                    <div class="stat-icon icon-reports">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="stat-trend trend-down">
                    <i class="fas fa-arrow-down"></i>
                    <span>-3% from last week</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <h3><?php echo $stats['active_clubs']; ?></h3>
                        <p>Partner Clubs</p>
                    </div>
                    <div class="stat-icon icon-clubs">
                        <i class="fas fa-landmark"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+2 new this month</span>
                </div>
            </div>
        </div>


        <!-- Recent Players Added -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>Recently Added Players</h3>
                <a href="admin-players.php" style="color: var(--primary); font-size: 14px;">View All</a>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--gray);">Player
                            </th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--gray);">Position
                            </th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--gray);">Country
                            </th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--gray);">Added
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get recently added players
                        $sql = "SELECT full_name, position, country, created_at 
                        FROM users 
                        WHERE role = 'player' 
                        ORDER BY created_at DESC 
                        LIMIT 5";
                        $stmt = $conn->query($sql);
                        $recentPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($recentPlayers as $player):
                            ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div
                                            style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                            <?php echo strtoupper(substr($player['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500; color: var(--dark);">
                                                <?php echo htmlspecialchars($player['full_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <span
                                        style="background: #e0e7ff; color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                                        <?php echo htmlspecialchars($player['position'] ?: 'N/A'); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; color: var(--gray);">
                                    <?php echo htmlspecialchars($player['country']); ?>
                                </td>
                                <td style="padding: 12px; color: var(--gray); font-size: 13px;">
                                    <?php echo timeAgo($player['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Player Growth</h3>
                    <select style="padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;">
                        <option>Last 30 days</option>
                        <option>Last 90 days</option>
                        <option>This year</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="playerGrowthChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>Player Distribution by Position</h3>
                </div>
                <div class="chart-container">
                    <canvas id="positionDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="charts-grid">
            <div class="recent-activity">
                <div class="activity-header">
                    <h3>Recent Activity</h3>
                    <a href="#" style="color: var(--primary); font-size: 14px;">View All</a>
                </div>
                <div class="activity-list">
                    <?php
                    $activities = getRecentActivities($conn);
                    foreach ($activities as $activity):
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i> <?php echo $activity['time']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>Scout Performance</h3>
                </div>
                <div class="chart-container">
                    <canvas id="scoutPerformanceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="admin-add-player.php" class="quick-action">
                <i class="fas fa-user-plus"></i>
                <h4>Add New Player</h4>
            </a>
            <a href="admin-add-scout.php" class="quick-action">
                <i class="fas fa-user-tie"></i>
                <h4>Add New Scout</h4>
            </a>
            <a href="admin-review-reports.php" class="quick-action">
                <i class="fas fa-clipboard-check"></i>
                <h4>Review Reports</h4>
            </a>
            <a href="admin-schedule-match.php" class="quick-action">
                <i class="fas fa-calendar-plus"></i>
                <h4>Schedule Match</h4>
            </a>
            <a href="admin-generate-report.php" class="quick-action">
                <i class="fas fa-file-export"></i>
                <h4>Generate Report</h4>
            </a>
            <a href="admin-system-logs.php" class="quick-action">
                <i class="fas fa-history"></i>
                <h4>System Logs</h4>
            </a>
        </div>
    </main>

    <script>
        // Player Growth Chart
        const growthCtx = document.getElementById('playerGrowthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Players Added',
                    data: [120, 150, 180, 210, 240, 280],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Position Distribution Chart
        const positionCtx = document.getElementById('positionDistributionChart').getContext('2d');
        new Chart(positionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Forward', 'Midfielder', 'Defender', 'Goalkeeper'],
                datasets: [{
                    data: [35, 30, 25, 10],
                    backgroundColor: [
                        '#6366f1',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Scout Performance Chart
        const scoutCtx = document.getElementById('scoutPerformanceChart').getContext('2d');
        new Chart(scoutCtx, {
            type: 'bar',
            data: {
                labels: ['Ahmed B.', 'Fatmata C.', 'Mohamed T.', 'Samuel K.', 'Aminata S.'],
                datasets: [{
                    label: 'Reports Submitted',
                    data: [12, 18, 9, 7, 15],
                    backgroundColor: '#6366f1',
                    borderRadius: 6
                }, {
                    label: 'Reports Approved',
                    data: [10, 16, 7, 5, 13],
                    backgroundColor: '#10b981',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>