<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Get analytics data
$conn = getDatabaseConnection();
$timeframe = $_GET['timeframe'] ?? '30days'; // 7days, 30days, 90days, year
$chart_type = $_GET['chart'] ?? 'player_growth';

// Get analytics data
$analytics_data = getAnalyticsData($conn, $timeframe);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        .timeframe-selector {
            display: flex;
            gap: 8px;
            background: white;
            padding: 6px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .timeframe-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            color: var(--gray);
            transition: all 0.3s ease;
        }

        .timeframe-btn.active {
            background: var(--primary);
            color: white;
        }

        .timeframe-btn:hover:not(.active) {
            background: #f8fafc;
        }

        .export-btn {
            padding: 10px 20px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background: #0da271;
            transform: translateY(-2px);
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .kpi-1 { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .kpi-2 { background: linear-gradient(135deg, #10b981, #34d399); }
        .kpi-3 { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .kpi-4 { background: linear-gradient(135deg, #ef4444, #f87171); }
        .kpi-5 { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
        .kpi-6 { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }

        .kpi-content h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .kpi-content p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .kpi-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .trend-up { color: var(--secondary); }
        .trend-down { color: var(--danger); }
        .trend-neutral { color: var(--gray); }

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

        /* Analytics Tables */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .table-card {
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

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8fafc;
        }

        .player-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .player-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .rating {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .rating-high { background: #d1fae5; color: #065f46; }
        .rating-medium { background: #fef3c7; color: #92400e; }
        .rating-low { background: #fee2e2; color: #991b1b; }

        /* Insights Section */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .insight-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .insight-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .insight-content h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .insight-content p {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
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
            
            .charts-grid, .tables-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
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
            <a href="admin-matches.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Matches</span>
            </a>
            <a href="admin-analytics.php" class="nav-item active">
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
                <h1>Analytics & Insights</h1>
                <p>Data-driven decision making for talent management</p>
            </div>
            
            <div class="header-actions">
                <div class="timeframe-selector">
                    <button class="timeframe-btn <?php echo $timeframe === '7days' ? 'active' : ''; ?>" 
                            onclick="window.location.href='?timeframe=7days'">
                        7 Days
                    </button>
                    <button class="timeframe-btn <?php echo $timeframe === '30days' ? 'active' : ''; ?>" 
                            onclick="window.location.href='?timeframe=30days'">
                        30 Days
                    </button>
                    <button class="timeframe-btn <?php echo $timeframe === '90days' ? 'active' : ''; ?>" 
                            onclick="window.location.href='?timeframe=90days'">
                        90 Days
                    </button>
                    <button class="timeframe-btn <?php echo $timeframe === 'year' ? 'active' : ''; ?>" 
                            onclick="window.location.href='?timeframe=year'">
                        Year
                    </button>
                </div>
                
                <button class="export-btn">
                    <i class="fas fa-download"></i>
                    Export Report
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon kpi-1">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?php echo number_format($analytics_data['player_growth_rate']); ?>%</h3>
                        <p>Player Growth Rate</p>
                    </div>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% from last period</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon kpi-2">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?php echo number_format($analytics_data['scout_accuracy'], 1); ?>%</h3>
                        <p>Scout Accuracy</p>
                    </div>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+3.2% improvement</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon kpi-3">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="kpi-content">
                        <h3>$<?php echo number_format($analytics_data['avg_transfer_value']/1000, 1); ?>K</h3>
                        <p>Avg. Transfer Value</p>
                    </div>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>+18% increase</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-icon kpi-4">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?php echo $analytics_data['high_risk_players']; ?></h3>
                        <p>High Risk Players</p>
                    </div>
                </div>
                <div class="kpi-trend trend-down">
                    <i class="fas fa-arrow-down"></i>
                    <span>-2 from last month</span>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Player Acquisition Funnel</h3>
                    <select onchange="window.location.href='?chart='+this.value" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;">
                        <option value="player_growth" <?php echo $chart_type === 'player_growth' ? 'selected' : ''; ?>>Player Growth</option>
                        <option value="acquisition" <?php echo $chart_type === 'acquisition' ? 'selected' : ''; ?>>Acquisition Funnel</option>
                        <option value="retention" <?php echo $chart_type === 'retention' ? 'selected' : ''; ?>>Retention Rate</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="acquisitionChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>Market Value by Age Group</h3>
                </div>
                <div class="chart-container">
                    <canvas id="marketValueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="tables-grid">
            <div class="table-card">
                <div class="table-header">
                    <h3>Top Performing Scouts</h3>
                    <a href="admin-scouts.php" style="color: var(--primary); font-size: 14px;">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Scout</th>
                                <th>Reports</th>
                                <th>Accuracy</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics_data['top_scouts'] as $scout): ?>
                            <tr>
                                <td>
                                    <div class="player-cell">
                                        <div class="player-avatar">
                                            <?php echo strtoupper(substr($scout['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($scout['name']); ?></div>
                                            <div style="font-size: 12px; color: var(--gray);"><?php echo htmlspecialchars($scout['region']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $scout['reports']; ?></td>
                                <td><?php echo number_format($scout['accuracy'], 1); ?>%</td>
                                <td>
                                    <span class="rating <?php 
                                        echo $scout['performance'] >= 8.5 ? 'rating-high' : 
                                             ($scout['performance'] >= 7 ? 'rating-medium' : 'rating-low');
                                    ?>">
                                        <?php echo number_format($scout['performance'], 1); ?>/10
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3>Emerging Talents</h3>
                    <a href="admin-players.php" style="color: var(--primary); font-size: 14px;">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Age</th>
                                <th>Position</th>
                                <th>Potential</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics_data['emerging_talents'] as $player): ?>
                            <tr>
                                <td>
                                    <div class="player-cell">
                                        <div class="player-avatar">
                                            <?php echo strtoupper(substr($player['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($player['name']); ?></div>
                                            <div style="font-size: 12px; color: var(--gray);"><?php echo htmlspecialchars($player['club']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $player['age']; ?></td>
                                <td><?php echo htmlspecialchars($player['position']); ?></td>
                                <td>
                                    <span class="rating <?php 
                                        echo $player['potential'] >= 8.5 ? 'rating-high' : 
                                             ($player['potential'] >= 7 ? 'rating-medium' : 'rating-low');
                                    ?>">
                                        <?php echo number_format($player['potential'], 1); ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($player['value']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Insights -->
        <div class="insights-grid">
            <div class="insight-card">
                <div class="insight-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="insight-content">
                    <h4>Key Insight</h4>
                    <p>Forwards aged 18-21 show the highest appreciation rate (+34% annually) in market value.</p>
                </div>
            </div>

            <div class="insight-card">
                <div class="insight-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="insight-content">
                    <h4>Opportunity Alert</h4>
                    <p>Scout coverage in Eastern Region is 40% below average. Consider assigning additional resources.</p>
                </div>
            </div>

            <div class="insight-card">
                <div class="insight-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="insight-content">
                    <h4>Risk Warning</h4>
                    <p>3 players with recurring injury patterns detected. Monitor closely before transfer recommendations.</p>
                </div>
            </div>
        </div>

        <!-- Additional Charts -->
        <div class="charts-grid" style="margin-top: 30px;">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Injury Risk Analysis</h3>
                </div>
                <div class="chart-container">
                    <canvas id="injuryRiskChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>Geographic Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="geoDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Acquisition Funnel Chart
        const acquisitionCtx = document.getElementById('acquisitionChart').getContext('2d');
        new Chart(acquisitionCtx, {
            type: 'bar',
            data: {
                labels: ['Discovered', 'Scouted', 'Evaluated', 'Recommended', 'Transferred'],
                datasets: [{
                    label: 'Players',
                    data: [1000, 750, 400, 150, 25],
                    backgroundColor: [
                        '#6366f1',
                        '#8b5cf6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444'
                    ],
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: 'white',
                        font: {
                            weight: 'bold'
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Number of Players'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Market Value by Age Chart
        const marketValueCtx = document.getElementById('marketValueChart').getContext('2d');
        new Chart(marketValueCtx, {
            type: 'line',
            data: {
                labels: ['16-18', '19-21', '22-24', '25-27', '28-30', '31+'],
                datasets: [{
                    label: 'Market Value ($K)',
                    data: [50, 180, 320, 280, 150, 80],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Potential Growth',
                    data: [200, 350, 180, 100, 50, 20],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    borderDash: [5, 5]
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
                        },
                        title: {
                            display: true,
                            text: 'Value in $ Thousands'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Age Group'
                        }
                    }
                }
            }
        });

        // Injury Risk Chart
        const injuryRiskCtx = document.getElementById('injuryRiskChart').getContext('2d');
        new Chart(injuryRiskCtx, {
            type: 'radar',
            data: {
                labels: ['Muscle Injuries', 'Joint Issues', 'Recovery Time', 'Match Density', 'Previous History'],
                datasets: [{
                    label: 'High Risk Players',
                    data: [8, 6, 9, 7, 8],
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    pointBackgroundColor: '#ef4444'
                }, {
                    label: 'Average',
                    data: [5, 4, 5, 6, 4],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    pointBackgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 10,
                        ticks: {
                            stepSize: 2
                        }
                    }
                }
            }
        });

        // Geographic Distribution Chart
        const geoCtx = document.getElementById('geoDistributionChart').getContext('2d');
        new Chart(geoCtx, {
            type: 'polarArea',
            data: {
                labels: ['Freetown', 'Bo', 'Kenema', 'Makeni', 'Koidu', 'Other'],
                datasets: [{
                    data: [35, 25, 15, 10, 8, 7],
                    backgroundColor: [
                        '#6366f1',
                        '#8b5cf6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#3b82f6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html>