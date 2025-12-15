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
if (!hasPermission('reports', 'create')) {
    header('Location: admin-dashboard.php');
    exit();
}

$conn = getDatabaseConnection();
$message = '';
$error = '';

// Get players for dropdown
$players = getPlayers($conn, ['limit' => 100]);
$scouts = $conn->query("SELECT id, full_name, specialization FROM scouts WHERE status = 'active' ORDER BY full_name")->fetchAll();
$matches = $conn->query("SELECT id, CONCAT(home_team, ' vs ', away_team, ' - ', DATE_FORMAT(match_date, '%d %b %Y')) as match_info FROM matches ORDER BY match_date DESC LIMIT 50")->fetchAll();

// Generate report code
$report_code = generateReportCode($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Get form data
        $scout_id = $_POST['scout_id'];
        $player_id = $_POST['player_id'];
        $match_id = !empty($_POST['match_id']) ? $_POST['match_id'] : null;
        
        // Ratings
        $technical_ability = $_POST['technical_ability'];
        $tactical_awareness = $_POST['tactical_awareness'];
        $physical_attributes = $_POST['physical_attributes'];
        $mental_strength = $_POST['mental_strength'];
        $overall_potential = $_POST['overall_potential'];
        
        // Calculate overall rating
        $overall_rating = ($technical_ability + $tactical_awareness + $physical_attributes + $mental_strength) / 4;
        
        // Text fields
        $strengths = $_POST['strengths'];
        $weaknesses = $_POST['weaknesses'];
        $comparison_players = $_POST['comparison_players'];
        $risk_assessment = $_POST['risk_assessment'];
        $additional_notes = $_POST['additional_notes'];
        
        // Recommendation
        $recommendation = $_POST['recommendation'];
        $status = 'submitted'; // Default status
        
        // If admin is submitting, auto-approve if they have permission
        if (hasPermission('reports', 'approve') && isset($_POST['auto_approve'])) {
            $status = 'approved';
            $reviewed_by = $_SESSION['admin_id'];
            $review_notes = 'Auto-approved by admin';
        }
        
        // Insert report
        $sql = "INSERT INTO scouting_reports (
            report_code, scout_id, player_id, match_id, 
            technical_ability, tactical_awareness, physical_attributes, 
            mental_strength, overall_potential, overall_rating,
            strengths, weaknesses, comparison_players, 
            risk_assessment, additional_notes, recommendation, status,
            reviewed_by, review_notes, approved_at
        ) VALUES (
            :report_code, :scout_id, :player_id, :match_id,
            :technical_ability, :tactical_awareness, :physical_attributes,
            :mental_strength, :overall_potential, :overall_rating,
            :strengths, :weaknesses, :comparison_players,
            :risk_assessment, :additional_notes, :recommendation, :status,
            :reviewed_by, :review_notes, NOW()
        )";
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            ':report_code' => $report_code,
            ':scout_id' => $scout_id,
            ':player_id' => $player_id,
            ':match_id' => $match_id,
            ':technical_ability' => $technical_ability,
            ':tactical_awareness' => $tactical_awareness,
            ':physical_attributes' => $physical_attributes,
            ':mental_strength' => $mental_strength,
            ':overall_potential' => $overall_potential,
            ':overall_rating' => $overall_rating,
            ':strengths' => $strengths,
            ':weaknesses' => $weaknesses,
            ':comparison_players' => $comparison_players,
            ':risk_assessment' => $risk_assessment,
            ':additional_notes' => $additional_notes,
            ':recommendation' => $recommendation,
            ':status' => $status,
            ':reviewed_by' => isset($reviewed_by) ? $reviewed_by : null,
            ':review_notes' => isset($review_notes) ? $review_notes : null
        ];
        
        if ($stmt->execute($params)) {
            $report_id = $conn->lastInsertId();
            
            // Update scout's report count
            $update_scout = $conn->prepare("UPDATE scouts SET reports_submitted = reports_submitted + 1 WHERE id = ?");
            $update_scout->execute([$scout_id]);
            
            // Log activity
            $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                   VALUES (?, 'create', 'scouting_reports', ?)");
            $log->execute([$_SESSION['admin_id'], $report_id]);
            
            $conn->commit();
            
            // Generate new report code for next entry
            $report_code = generateReportCode($conn);
            
            $message = "✅ Scouting report submitted successfully! Report ID: {$report_code}";
            
            // Clear form if not staying on page
            if (!isset($_POST['add_another'])) {
                $_POST = [];
            }
        } else {
            throw new Exception("Failed to save report");
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Scouting Report - Admin Dashboard</title>
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

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
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

        .btn-back {
            padding: 10px 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Form Container */
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Messages */
        .message {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--secondary);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        /* Report Header */
        .report-header {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-group label .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Rating System */
        .rating-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .rating-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .rating-item {
            text-align: center;
        }

        .rating-slider {
            width: 100%;
            margin: 10px 0;
            -webkit-appearance: none;
            height: 6px;
            border-radius: 3px;
            background: var(--gray-light);
            outline: none;
        }

        .rating-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .rating-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-top: 8px;
        }

        .rating-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Overall Rating Display */
        .overall-rating {
            text-align: center;
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, #f0f4ff, #e0e7ff);
            border-radius: var(--radius);
        }

        .overall-score {
            font-size: 48px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .overall-label {
            font-size: 14px;
            color: var(--gray);
            margin-top: 8px;
        }

        /* Text Areas Section */
        .textarea-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .textarea-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        /* Recommendation Section */
        .recommendation-section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .recommendation-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .recommendation-option {
            position: relative;
        }

        .recommendation-option input[type="radio"] {
            display: none;
        }

        .recommendation-card {
            padding: 20px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .recommendation-card:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }

        .recommendation-option input[type="radio"]:checked + .recommendation-card {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), transparent);
        }

        .recommendation-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .sign-immediately .recommendation-icon { color: var(--secondary); }
        .high-priority .recommendation-icon { color: var(--primary); }
        .monitor .recommendation-icon { color: var(--warning); }
        .reject .recommendation-icon { color: var(--danger); }

        .recommendation-card h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .recommendation-card p {
            font-size: 12px;
            color: var(--gray);
        }

        /* Admin Options */
        .admin-options {
            background: #f0f9ff;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #bae6fd;
        }

        .admin-options h3 {
            color: #0369a1;
            margin-bottom: 16px;
            font-size: 18px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid var(--border);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding: 24px 0;
            border-top: 1px solid var(--border);
            margin-top: 24px;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
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
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--secondary), #34d399);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
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
                <a href="admin-add-report.php" class="nav-item active">
                    <i class="fas fa-file-medical"></i>
                    <span>Add Report</span>
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
            <div class="top-bar">
                <div class="page-title">
                    <h1>Add Scouting Report</h1>
                    <p>Submit detailed player evaluation report</p>
                </div>
                
                <div class="header-actions">
                    <a href="admin-reports.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to Reports
                    </a>
                </div>
            </div>

            <div class="form-container">
                <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- Report Header -->
                    <div class="report-header">
                        <h3 style="margin-bottom: 20px; color: var(--dark);">
                            <i class="fas fa-id-card"></i> Report Details
                        </h3>
                        <div class="report-meta">
                            <div class="form-group">
                                <label>Report Code <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo $report_code; ?>" readonly style="background: #f8fafc;">
                            </div>

                            <div class="form-group">
                                <label>Scout <span class="required">*</span></label>
                                <select name="scout_id" class="form-control" required>
                                    <option value="">Select Scout</option>
                                    <?php foreach ($scouts as $scout): ?>
                                    <option value="<?php echo $scout['id']; ?>" <?php echo isset($_POST['scout_id']) && $_POST['scout_id'] == $scout['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($scout['full_name']); ?> (<?php echo $scout['specialization']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Player <span class="required">*</span></label>
                                <select name="player_id" class="form-control" required onchange="loadPlayerInfo(this.value)">
                                    <option value="">Select Player</option>
                                    <?php foreach ($players as $player): ?>
                                    <option value="<?php echo $player['id']; ?>" 
                                            data-position="<?php echo htmlspecialchars($player['position']); ?>"
                                            data-club="<?php echo htmlspecialchars($player['current_club']); ?>"
                                            data-age="<?php echo date_diff(date_create($player['date_of_birth']), date_create('today'))->y; ?>"
                                            <?php echo isset($_POST['player_id']) && $_POST['player_id'] == $player['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($player['full_name']); ?> 
                                        (<?php echo $player['position']; ?> - <?php echo $player['current_club']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Match (Optional)</label>
                                <select name="match_id" class="form-control">
                                    <option value="">Select Match</option>
                                    <?php foreach ($matches as $match): ?>
                                    <option value="<?php echo $match['id']; ?>" <?php echo isset($_POST['match_id']) && $_POST['match_id'] == $match['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($match['match_info']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div id="player-info" style="display: none; margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                                <div>
                                    <strong>Position:</strong> <span id="player-position">-</span>
                                </div>
                                <div>
                                    <strong>Club:</strong> <span id="player-club">-</span>
                                </div>
                                <div>
                                    <strong>Age:</strong> <span id="player-age">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Player Ratings -->
                    <div class="rating-section">
                        <h3 style="margin-bottom: 20px; color: var(--dark);">
                            <i class="fas fa-chart-bar"></i> Player Ratings (1-10)
                        </h3>
                        
                        <div class="rating-grid">
                            <div class="rating-item">
                                <label>Technical Ability</label>
                                <input type="range" name="technical_ability" class="rating-slider" min="1" max="10" step="0.5" 
                                       value="<?php echo $_POST['technical_ability'] ?? '5'; ?>" oninput="updateRating(this)">
                                <div class="rating-value"><?php echo $_POST['technical_ability'] ?? '5'; ?></div>
                                <div class="rating-label">Ball Control, Passing, Shooting</div>
                            </div>

                            <div class="rating-item">
                                <label>Tactical Awareness</label>
                                <input type="range" name="tactical_awareness" class="rating-slider" min="1" max="10" step="0.5" 
                                       value="<?php echo $_POST['tactical_awareness'] ?? '5'; ?>" oninput="updateRating(this)">
                                <div class="rating-value"><?php echo $_POST['tactical_awareness'] ?? '5'; ?></div>
                                <div class="rating-label">Positioning, Decision Making</div>
                            </div>

                            <div class="rating-item">
                                <label>Physical Attributes</label>
                                <input type="range" name="physical_attributes" class="rating-slider" min="1" max="10" step="0.5" 
                                       value="<?php echo $_POST['physical_attributes'] ?? '5'; ?>" oninput="updateRating(this)">
                                <div class="rating-value"><?php echo $_POST['physical_attributes'] ?? '5'; ?></div>
                                <div class="rating-label">Speed, Strength, Stamina</div>
                            </div>

                            <div class="rating-item">
                                <label>Mental Strength</label>
                                <input type="range" name="mental_strength" class="rating-slider" min="1" max="10" step="0.5" 
                                       value="<?php echo $_POST['mental_strength'] ?? '5'; ?>" oninput="updateRating(this)">
                                <div class="rating-value"><?php echo $_POST['mental_strength'] ?? '5'; ?></div>
                                <div class="rating-label">Composure, Leadership, Work Ethic</div>
                            </div>

                            <div class="rating-item">
                                <label>Overall Potential</label>
                                <input type="range" name="overall_potential" class="rating-slider" min="1" max="10" step="0.5" 
                                       value="<?php echo $_POST['overall_potential'] ?? '5'; ?>" oninput="updateRating(this)">
                                <div class="rating-value"><?php echo $_POST['overall_potential'] ?? '5'; ?></div>
                                <div class="rating-label">Future Development Ceiling</div>
                            </div>
                        </div>

                        <div class="overall-rating">
                            <div class="overall-score" id="overall-rating">0.0</div>
                            <div class="overall-label">Overall Rating</div>
                        </div>
                    </div>

                    <!-- Strengths & Weaknesses -->
                    <div class="textarea-section">
                        <h3 style="margin-bottom: 20px; color: var(--dark);">
                            <i class="fas fa-list-check"></i> Analysis
                        </h3>
                        
                        <div class="textarea-grid">
                            <div class="form-group">
                                <label>Key Strengths <span class="required">*</span></label>
                                <textarea name="strengths" class="form-control" required 
                                          placeholder="List player's main strengths (one per line)"><?php echo $_POST['strengths'] ?? ''; ?></textarea>
                                <small style="color: var(--gray); font-size: 12px;">Separate each strength with a new line</small>
                            </div>

                            <div class="form-group">
                                <label>Areas for Improvement <span class="required">*</span></label>
                                <textarea name="weaknesses" class="form-control" required 
                                          placeholder="List areas needing improvement"><?php echo $_POST['weaknesses'] ?? ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Player Comparison</label>
                                <textarea name="comparison_players" class="form-control" 
                                          placeholder="Compare to well-known players (style or ability)"><?php echo $_POST['comparison_players'] ?? ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Risk Assessment</label>
                                <textarea name="risk_assessment" class="form-control" 
                                          placeholder="Potential risks (injury prone, attitude, etc.)"><?php echo $_POST['risk_assessment'] ?? ''; ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Additional Notes</label>
                            <textarea name="additional_notes" class="form-control" rows="4" 
                                      placeholder="Any other observations, context, or specific match details"><?php echo $_POST['additional_notes'] ?? ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Recommendation -->
                    <div class="recommendation-section">
                        <h3 style="margin-bottom: 20px; color: var(--dark);">
                            <i class="fas fa-flag"></i> Recommendation
                        </h3>
                        
                        <div class="recommendation-options">
                            <div class="recommendation-option">
                                <input type="radio" name="recommendation" id="sign_immediately" value="sign_immediately" 
                                       <?php echo ($_POST['recommendation'] ?? '') == 'sign_immediately' ? 'checked' : ''; ?> required>
                                <label for="sign_immediately" class="recommendation-card sign-immediately">
                                    <div class="recommendation-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <h4>Sign Immediately</h4>
                                    <p>Top talent, urgent signing required</p>
                                </label>
                            </div>

                            <div class="recommendation-option">
                                <input type="radio" name="recommendation" id="high_priority" value="high_priority"
                                       <?php echo ($_POST['recommendation'] ?? '') == 'high_priority' ? 'checked' : ''; ?>>
                                <label for="high_priority" class="recommendation-card high-priority">
                                    <div class="recommendation-icon">
                                        <i class="fas fa-bullseye"></i>
                                    </div>
                                    <h4>High Priority</h4>
                                    <p>Strong prospect, follow closely</p>
                                </label>
                            </div>

                            <div class="recommendation-option">
                                <input type="radio" name="recommendation" id="monitor" value="monitor"
                                       <?php echo ($_POST['recommendation'] ?? '') == 'monitor' ? 'checked' : ''; ?>>
                                <label for="monitor" class="recommendation-card monitor">
                                    <div class="recommendation-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <h4>Monitor</h4>
                                    <p>Potential, needs development</p>
                                </label>
                            </div>

                            <div class="recommendation-option">
                                <input type="radio" name="recommendation" id="reject" value="reject"
                                       <?php echo ($_POST['recommendation'] ?? '') == 'reject' ? 'checked' : ''; ?>>
                                <label for="reject" class="recommendation-card reject">
                                    <div class="recommendation-icon">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <h4>Reject</h4>
                                    <p>Not suitable for our needs</p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Options -->
                    <?php if (hasPermission('reports', 'approve')): ?>
                    <div class="admin-options">
                        <h3><i class="fas fa-user-shield"></i> Admin Options</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_approve" id="auto_approve" value="1">
                            <label for="auto_approve">
                                <strong>Auto-approve this report</strong><br>
                                <small style="color: #666;">The report will be marked as approved immediately</small>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="add_another" value="1" class="btn btn-secondary">
                            <i class="fas fa-plus-circle"></i>
                            Save & Add Another
                        </button>
                        
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                            Reset Form
                        </button>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Update rating display
        function updateRating(slider) {
            const valueDisplay = slider.nextElementSibling;
            valueDisplay.textContent = slider.value;
            calculateOverallRating();
        }

        // Calculate overall rating
        function calculateOverallRating() {
            const technical = parseFloat(document.querySelector('[name="technical_ability"]').value) || 0;
            const tactical = parseFloat(document.querySelector('[name="tactical_awareness"]').value) || 0;
            const physical = parseFloat(document.querySelector('[name="physical_attributes"]').value) || 0;
            const mental = parseFloat(document.querySelector('[name="mental_strength"]').value) || 0;
            
            const overall = (technical + tactical + physical + mental) / 4;
            document.getElementById('overall-rating').textContent = overall.toFixed(1);
        }

        // Load player info when selected
        function loadPlayerInfo(playerId) {
            const select = document.querySelector('[name="player_id"]');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                document.getElementById('player-info').style.display = 'block';
                document.getElementById('player-position').textContent = option.dataset.position || '-';
                document.getElementById('player-club').textContent = option.dataset.club || '-';
                document.getElementById('player-age').textContent = option.dataset.age ? option.dataset.age + ' years' : '-';
            } else {
                document.getElementById('player-info').style.display = 'none';
            }
        }

        // Initialize overall rating
        document.addEventListener('DOMContentLoaded', function() {
            calculateOverallRating();
            
            // Load player info if already selected
            const playerSelect = document.querySelector('[name="player_id"]');
            if (playerSelect.value) {
                loadPlayerInfo(playerSelect.value);
            }
            
            // Add real-time updates to all sliders
            const sliders = document.querySelectorAll('.rating-slider');
            sliders.forEach(slider => {
                slider.addEventListener('input', function() {
                    updateRating(this);
                });
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const recommendation = document.querySelector('input[name="recommendation"]:checked');
            if (!recommendation) {
                e.preventDefault();
                alert('Please select a recommendation');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>