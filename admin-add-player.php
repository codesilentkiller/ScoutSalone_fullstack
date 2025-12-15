<?php
session_start();
require_once 'config/database.php';
require_once 'functions/admin-functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

// Check if user has permission to add players
if (!hasPermission('players', 'create')) {
    die('Access denied. You do not have permission to add players.');
}

$conn = getDatabaseConnection();
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate form data
    $playerData = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'password' => $_POST['password'],
        'role' => 'player',
        'full_name' => trim($_POST['full_name']),
        'phone' => trim($_POST['phone']),
        'country' => $_POST['country'],
        'date_of_birth' => $_POST['date_of_birth'],
        'position' => $_POST['position'],
        'current_club' => trim($_POST['current_club']),
        'height' => $_POST['height'] ?: null,
        'weight' => $_POST['weight'] ?: null,
        'preferred_foot' => $_POST['preferred_foot'],
        'agent_name' => trim($_POST['agent_name']),
        'agent_contact' => trim($_POST['agent_contact']),
        'market_value' => $_POST['market_value'] ?: null,
        'contract_expiry' => $_POST['contract_expiry'] ?: null,
        'bio' => trim($_POST['bio'])
    ];
    
    // Validate required fields
    $required = ['username', 'email', 'password', 'full_name', 'date_of_birth', 'position', 'country'];
    foreach ($required as $field) {
        if (empty($playerData[$field])) {
            $error = "Please fill in all required fields";
            break;
        }
    }
    
    // Validate email
    if (!$error && !filter_var($playerData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    }
    
    // Validate password strength
    if (!$error && strlen($playerData['password']) < 6) {
        $error = "Password must be at least 6 characters";
    }
    
    // Check if username or email already exists
    if (!$error) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$playerData['username'], $playerData['email']]);
        if ($check->rowCount() > 0) {
            $error = "Username or email already exists";
        }
    }
    
    // If no errors, create player
    if (!$error) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Create user
            $sql = "INSERT INTO users (username, email, password_hash, role, full_name, phone, country, 
                    date_of_birth, position, current_club, created_at) 
                    VALUES (?, ?, ?, 'player', ?, ?, ?, ?, ?, ?, NOW())";
            
            $hashedPassword = password_hash($playerData['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $playerData['username'],
                $playerData['email'],
                $hashedPassword,
                $playerData['full_name'],
                $playerData['phone'],
                $playerData['country'],
                $playerData['date_of_birth'],
                $playerData['position'],
                $playerData['current_club']
            ]);
            
            $playerId = $conn->lastInsertId();
            
            // Create player profile
            $sql = "INSERT INTO player_profiles (user_id, height, weight, preferred_foot, bio) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $playerId,
                $playerData['height'],
                $playerData['weight'],
                $playerData['preferred_foot'],
                $playerData['bio']
            ]);
            
            // Insert agent info if provided
            if (!empty($playerData['agent_name'])) {
                $sql = "INSERT INTO player_notes (player_id, admin_id, note_type, note) 
                        VALUES (?, ?, 'general', ?)";
                $stmt = $conn->prepare($sql);
                $agentInfo = "Agent: {$playerData['agent_name']}\n";
                $agentInfo .= "Contact: {$playerData['agent_contact']}\n";
                $agentInfo .= "Market Value: €" . number_format($playerData['market_value'], 0) . "\n";
                $agentInfo .= "Contract Expiry: {$playerData['contract_expiry']}";
                $stmt->execute([$playerId, $_SESSION['admin_id'], $agentInfo]);
            }
            
            // Log the action
            $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id) 
                                   VALUES (?, 'create', 'users', ?)");
            $log->execute([$_SESSION['admin_id'], $playerId]);
            
            $conn->commit();
            
            $success = "Player '{$playerData['full_name']}' added successfully!";
            
            // Clear form data on success
            $playerData = array_fill_keys(array_keys($playerData), '');
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Player - Admin Dashboard</title>
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #f8fafc;
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--radius);
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 1200px;
            margin: 0 auto;
        }

        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .form-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-header h2 i {
            color: var(--primary);
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

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        /* Form Groups */
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

        .form-group label .required {
            color: var(--danger);
            margin-left: 4px;
        }

        .form-group .hint {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
            font-style: italic;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control.select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        .input-group {
            display: flex;
            gap: 12px;
        }

        .input-group .form-control {
            flex: 1;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
            margin-top: 40px;
        }

        .btn {
            padding: 14px 28px;
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

        /* Character Counter */
        .char-counter {
            text-align: right;
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
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
            
            .form-container {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .input-group {
                flex-direction: column;
                gap: 12px;
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
            <a href="admin-add-player.php" class="nav-item active">
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <a href="admin-dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <div>
                    <h1>Add New Player</h1>
                    <p>Fill in the player's details below</p>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search players...">
                </div>
                
                <a href="admin-logout.php" class="logout-btn" style="width: 40px; height: 40px; border-radius: 50%; background: white; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <form method="POST" action="">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Personal Information
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['full_name'] ?? ''); ?>" 
                                   required placeholder="Enter player's full name">
                        </div>

                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['username'] ?? ''); ?>" 
                                   required placeholder="e.g., john.doe22">
                            <div class="hint">Unique username for login</div>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['email'] ?? ''); ?>" 
                                   required placeholder="player@example.com">
                        </div>

                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" class="form-control" 
                                   required minlength="6" placeholder="At least 6 characters">
                            <div class="hint">Player will use this to login</div>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['phone'] ?? ''); ?>" 
                                   placeholder="+232 76 123 456">
                        </div>

                        <div class="form-group">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['date_of_birth'] ?? ''); ?>" 
                                   required max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label>Country <span class="required">*</span></label>
                            <select name="country" class="form-control select" required>
                                <option value="">Select Country</option>
                                <option value="Sierra Leone" <?php echo ($playerData['country'] ?? '') == 'Sierra Leone' ? 'selected' : ''; ?>>Sierra Leone</option>
                                <option value="Ghana" <?php echo ($playerData['country'] ?? '') == 'Ghana' ? 'selected' : ''; ?>>Ghana</option>
                                <option value="Nigeria" <?php echo ($playerData['country'] ?? '') == 'Nigeria' ? 'selected' : ''; ?>>Nigeria</option>
                                <option value="Liberia" <?php echo ($playerData['country'] ?? '') == 'Liberia' ? 'selected' : ''; ?>>Liberia</option>
                                <option value="Guinea" <?php echo ($playerData['country'] ?? '') == 'Guinea' ? 'selected' : ''; ?>>Guinea</option>
                                <option value="Other" <?php echo ($playerData['country'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Football Profile Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-futbol"></i>
                        Football Profile
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Primary Position <span class="required">*</span></label>
                            <select name="position" class="form-control select" required>
                                <option value="">Select Position</option>
                                <option value="Goalkeeper" <?php echo ($playerData['position'] ?? '') == 'Goalkeeper' ? 'selected' : ''; ?>>Goalkeeper</option>
                                <option value="Defender" <?php echo ($playerData['position'] ?? '') == 'Defender' ? 'selected' : ''; ?>>Defender</option>
                                <option value="Midfielder" <?php echo ($playerData['position'] ?? '') == 'Midfielder' ? 'selected' : ''; ?>>Midfielder</option>
                                <option value="Forward" <?php echo ($playerData['position'] ?? '') == 'Forward' ? 'selected' : ''; ?>>Forward</option>
                                <option value="Winger" <?php echo ($playerData['position'] ?? '') == 'Winger' ? 'selected' : ''; ?>>Winger</option>
                                <option value="Attacking Midfielder" <?php echo ($playerData['position'] ?? '') == 'Attacking Midfielder' ? 'selected' : ''; ?>>Attacking Midfielder</option>
                                <option value="Defensive Midfielder" <?php echo ($playerData['position'] ?? '') == 'Defensive Midfielder' ? 'selected' : ''; ?>>Defensive Midfielder</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Current Club</label>
                            <input type="text" name="current_club" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['current_club'] ?? ''); ?>" 
                                   placeholder="e.g., East End Lions">
                        </div>

                        <div class="form-group">
                            <label>Preferred Foot</label>
                            <select name="preferred_foot" class="form-control select">
                                <option value="">Select Foot</option>
                                <option value="left" <?php echo ($playerData['preferred_foot'] ?? '') == 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="right" <?php echo ($playerData['preferred_foot'] ?? '') == 'right' ? 'selected' : ''; ?>>Right</option>
                                <option value="both" <?php echo ($playerData['preferred_foot'] ?? '') == 'both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>

                        <div class="input-group">
                            <div class="form-group" style="flex: 1;">
                                <label>Height (cm)</label>
                                <input type="number" name="height" class="form-control" 
                                       value="<?php echo htmlspecialchars($playerData['height'] ?? ''); ?>" 
                                       min="100" max="250" step="0.1" placeholder="e.g., 185.5">
                            </div>

                            <div class="form-group" style="flex: 1;">
                                <label>Weight (kg)</label>
                                <input type="number" name="weight" class="form-control" 
                                       value="<?php echo htmlspecialchars($playerData['weight'] ?? ''); ?>" 
                                       min="40" max="150" step="0.1" placeholder="e.g., 75.2">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Details Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-briefcase"></i>
                        Professional Details
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Agent Name</label>
                            <input type="text" name="agent_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['agent_name'] ?? ''); ?>" 
                                   placeholder="Agent's full name">
                        </div>

                        <div class="form-group">
                            <label>Agent Contact</label>
                            <input type="text" name="agent_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['agent_contact'] ?? ''); ?>" 
                                   placeholder="Email or phone number">
                        </div>

                        <div class="form-group">
                            <label>Estimated Market Value (€)</label>
                            <input type="number" name="market_value" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['market_value'] ?? ''); ?>" 
                                   min="0" step="1000" placeholder="e.g., 500000">
                        </div>

                        <div class="form-group">
                            <label>Contract Expiry Date</label>
                            <input type="date" name="contract_expiry" class="form-control" 
                                   value="<?php echo htmlspecialchars($playerData['contract_expiry'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Biography Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Biography & Notes
                    </h2>
                    
                    <div class="form-group">
                        <label>Player Biography</label>
                        <textarea name="bio" class="form-control" rows="5" 
                                  placeholder="Describe the player's background, strengths, career highlights, etc."><?php echo htmlspecialchars($playerData['bio'] ?? ''); ?></textarea>
                        <div class="char-counter">
                            <span id="bio-counter">0</span>/1000 characters
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Add Player
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Clear Form
                    </button>
                    <a href="admin-players.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Character counter for biography
        document.addEventListener('DOMContentLoaded', function() {
            const bioTextarea = document.querySelector('textarea[name="bio"]');
            const bioCounter = document.getElementById('bio-counter');
            
            if (bioTextarea && bioCounter) {
                // Update counter on input
                bioTextarea.addEventListener('input', function() {
                    bioCounter.textContent = this.value.length;
                });
                
                // Initialize counter
                bioCounter.textContent = bioTextarea.value.length;
            }
            
            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#d1d5db';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields marked with *');
                }
            });
            
            // Reset form validation on input
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.style.borderColor = '#d1d5db';
                });
            });
        });
    </script>
</body>
</html>