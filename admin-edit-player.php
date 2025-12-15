<?php
// Start session
session_start();

// Include necessary files
require_once 'config/database.php';
require_once 'functions/users.php';
require_once 'functions/session.php';

// Check if user is logged in and is an admin
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$current_user = getCurrentUser();

// Check if user is admin (you might want to add an 'admin' role to your users table)
// For now, we'll check if it's one of the demo accounts or you can add admin logic
$is_admin = in_array($current_user['role'], ['admin', 'scout', 'club']); // Adjust based on your needs

if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

// Get player ID from URL
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get player data
$player = null;
$player_stats = null;
$player_profile = null;

if ($player_id > 0) {
    $player = getUserById($player_id);
    
    if ($player && $player['role'] == 'player') {
        // Get player profile data
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT * FROM player_profiles WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
        $stmt->execute();
        $player_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get player stats
        $stmt = $conn->prepare("SELECT * FROM player_stats WHERE user_id = :user_id ORDER BY season_year DESC");
        $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
        $stmt->execute();
        $player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        header('Location: admin-players.php');
        exit();
    }
} else {
    header('Location: admin-players.php');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Update basic profile
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $country = $_POST['country'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $position = $_POST['position'] ?? '';
        $current_club = $_POST['current_club'] ?? '';
        
        // Update user data
        $conn = getDatabaseConnection();
        $sql = "UPDATE users SET 
                full_name = :full_name,
                email = :email,
                phone = :phone,
                country = :country,
                date_of_birth = :date_of_birth,
                position = :position,
                current_club = :current_club
                WHERE id = :id";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':country', $country);
            $stmt->bindParam(':date_of_birth', $date_of_birth);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':current_club', $current_club);
            $stmt->bindParam(':id', $player_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Player profile updated successfully!";
                // Refresh player data
                $player = getUserById($player_id);
            } else {
                $error_message = "Failed to update profile.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['update_profile_details'])) {
        // Update player profile details
        $height = $_POST['height'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $preferred_foot = $_POST['preferred_foot'] ?? '';
        $video_url = $_POST['video_url'] ?? '';
        $bio = $_POST['bio'] ?? '';
        
        // Check if profile exists
        if ($player_profile) {
            $sql = "UPDATE player_profiles SET 
                    height = :height,
                    weight = :weight,
                    preferred_foot = :preferred_foot,
                    video_url = :video_url,
                    bio = :bio
                    WHERE user_id = :user_id";
        } else {
            $sql = "INSERT INTO player_profiles (user_id, height, weight, preferred_foot, video_url, bio) 
                    VALUES (:user_id, :height, :weight, :preferred_foot, :video_url, :bio)";
        }
        
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare($sql);
            
            if ($player_profile) {
                $stmt->bindParam(':height', $height);
                $stmt->bindParam(':weight', $weight);
                $stmt->bindParam(':preferred_foot', $preferred_foot);
                $stmt->bindParam(':video_url', $video_url);
                $stmt->bindParam(':bio', $bio);
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
            } else {
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                $stmt->bindParam(':height', $height);
                $stmt->bindParam(':weight', $weight);
                $stmt->bindParam(':preferred_foot', $preferred_foot);
                $stmt->bindParam(':video_url', $video_url);
                $stmt->bindParam(':bio', $bio);
            }
            
            if ($stmt->execute()) {
                $success_message = "Player details updated successfully!";
                // Refresh profile data
                $stmt = $conn->prepare("SELECT * FROM player_profiles WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                $stmt->execute();
                $player_profile = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to update player details.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['add_stats'])) {
        // Add new stats
        $season_year = $_POST['season_year'] ?? '';
        $matches_played = $_POST['matches_played'] ?? 0;
        $goals = $_POST['goals'] ?? 0;
        $assists = $_POST['assists'] ?? 0;
        $yellow_cards = $_POST['yellow_cards'] ?? 0;
        $red_cards = $_POST['red_cards'] ?? 0;
        
        $conn = getDatabaseConnection();
        $sql = "INSERT INTO player_stats (user_id, season_year, matches_played, goals, assists, yellow_cards, red_cards) 
                VALUES (:user_id, :season_year, :matches_played, :goals, :assists, :yellow_cards, :red_cards)";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
            $stmt->bindParam(':season_year', $season_year);
            $stmt->bindParam(':matches_played', $matches_played, PDO::PARAM_INT);
            $stmt->bindParam(':goals', $goals, PDO::PARAM_INT);
            $stmt->bindParam(':assists', $assists, PDO::PARAM_INT);
            $stmt->bindParam(':yellow_cards', $yellow_cards, PDO::PARAM_INT);
            $stmt->bindParam(':red_cards', $red_cards, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Player stats added successfully!";
                // Refresh stats data
                $stmt = $conn->prepare("SELECT * FROM player_stats WHERE user_id = :user_id ORDER BY season_year DESC");
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                $stmt->execute();
                $player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to add player stats.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['update_stats'])) {
        // Update existing stats
        $stats_id = $_POST['stats_id'] ?? 0;
        $matches_played = $_POST['matches_played'] ?? 0;
        $goals = $_POST['goals'] ?? 0;
        $assists = $_POST['assists'] ?? 0;
        $yellow_cards = $_POST['yellow_cards'] ?? 0;
        $red_cards = $_POST['red_cards'] ?? 0;
        
        if ($stats_id > 0) {
            $conn = getDatabaseConnection();
            $sql = "UPDATE player_stats SET 
                    matches_played = :matches_played,
                    goals = :goals,
                    assists = :assists,
                    yellow_cards = :yellow_cards,
                    red_cards = :red_cards
                    WHERE id = :id AND user_id = :user_id";
            
            try {
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':matches_played', $matches_played, PDO::PARAM_INT);
                $stmt->bindParam(':goals', $goals, PDO::PARAM_INT);
                $stmt->bindParam(':assists', $assists, PDO::PARAM_INT);
                $stmt->bindParam(':yellow_cards', $yellow_cards, PDO::PARAM_INT);
                $stmt->bindParam(':red_cards', $red_cards, PDO::PARAM_INT);
                $stmt->bindParam(':id', $stats_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success_message = "Player stats updated successfully!";
                    // Refresh stats data
                    $stmt = $conn->prepare("SELECT * FROM player_stats WHERE user_id = :user_id ORDER BY season_year DESC");
                    $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to update player stats.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
        
    } elseif (isset($_POST['delete_stats'])) {
        // Delete stats
        $stats_id = $_POST['stats_id'] ?? 0;
        
        if ($stats_id > 0) {
            $conn = getDatabaseConnection();
            $sql = "DELETE FROM player_stats WHERE id = :id AND user_id = :user_id";
            
            try {
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id', $stats_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success_message = "Player stats deleted successfully!";
                    // Refresh stats data
                    $stmt = $conn->prepare("SELECT * FROM player_stats WHERE user_id = :user_id ORDER BY season_year DESC");
                    $stmt->bindParam(':user_id', $player_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to delete player stats.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Player - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --pure-white: #ffffff;
            --light-white: rgba(255, 255, 255, 0.95);
            --medium-grey: rgba(255, 255, 255, 0.6);
            --light-grey: rgba(255, 255, 255, 0.3);
            --faint-white: rgba(255, 255, 255, 0.12);
            --dark-grey: rgba(255, 255, 255, 0.08);
            --dark-bg: #000000;
            --surface-bg: #0a0a0a;
            --card-bg: #111111;
            --success-green: #4caf50;
            --error-red: #f44336;
            --accent-blue: #2196F3;
            --accent-orange: #FF9800;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--pure-white);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .admin-header {
            background: var(--surface-bg);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--dark-grey);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            font-weight: 900;
            color: var(--pure-white);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .back-btn {
            background: transparent;
            border: 1px solid var(--light-grey);
            color: var(--pure-white);
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            border-color: var(--pure-white);
            background: rgba(255, 255, 255, 0.05);
        }

        .player-summary {
            display: flex;
            align-items: center;
            gap: 20px;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .player-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--pure-white);
        }

        .player-info h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--pure-white);
            margin-bottom: 5px;
        }

        .player-info p {
            color: var(--medium-grey);
            font-size: 14px;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid var(--success-green);
            color: var(--success-green);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border-left: 4px solid var(--error-red);
            color: #ff6b6b;
        }

        /* Forms */
        .form-section {
            background: var(--surface-bg);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--dark-grey);
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--pure-white);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--dark-grey);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--accent-blue);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: var(--pure-white);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--card-bg);
            border: 1px solid var(--dark-grey);
            border-radius: 5px;
            color: var(--pure-white);
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            background: var(--card-bg);
            border: 1px solid var(--dark-grey);
            border-radius: 5px;
            color: var(--pure-white);
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
        }

        .form-select option {
            background: var(--card-bg);
            color: var(--pure-white);
        }

        textarea.form-input {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--dark-grey);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--accent-blue);
            color: var(--pure-white);
        }

        .btn-primary:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-green);
            color: var(--pure-white);
        }

        .btn-success:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--error-red);
            color: var(--pure-white);
        }

        .btn-danger:hover {
            background: #D32F2F;
            transform: translateY(-2px);
        }

        /* Stats Table */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .stats-table th {
            background: var(--card-bg);
            padding: 15px;
            text-align: left;
            color: var(--pure-white);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid var(--dark-grey);
        }

        .stats-table td {
            padding: 15px;
            text-align: left;
            color: var(--medium-grey);
            font-size: 14px;
            border-bottom: 1px solid var(--dark-grey);
        }

        .stats-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .stats-table .stat-value {
            color: var(--pure-white);
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn.edit {
            background: var(--accent-orange);
            color: var(--pure-white);
        }

        .action-btn.delete {
            background: var(--error-red);
            color: var(--pure-white);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--medium-grey);
            font-size: 16px;
            grid-column: 1 / -1;
        }

        /* Modal for stats edit */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--surface-bg);
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--dark-grey);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--pure-white);
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--medium-grey);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--pure-white);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-container {
                padding: 15px;
            }

            .admin-header {
                padding: 20px;
            }

            .header-title {
                font-size: 24px;
            }

            .player-summary {
                flex-direction: column;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .stats-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Loading state */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--light-grey);
            border-radius: 50%;
            border-top-color: var(--accent-blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div class="header-top">
                <h1 class="header-title">Edit Player</h1>
                <a href="admin-players.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Players
                </a>
            </div>
            
            <div class="player-summary">
                <div class="player-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="player-info">
                    <h3><?php echo htmlspecialchars($player['full_name'] ?: $player['username']); ?></h3>
                    <p>Player ID: <?php echo $player_id; ?> • <?php echo htmlspecialchars($player['position'] ?: 'Player'); ?> • <?php echo htmlspecialchars($player['country'] ?: 'International'); ?></p>
                    <p>Joined: <?php echo date('F j, Y', strtotime($player['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Basic Information Form -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-user-circle"></i> Basic Information
            </h2>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($player['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($player['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($player['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <select name="country" class="form-select" required>
                            <option value="">Select Country</option>
                            <option value="Sierra Leone" <?php echo ($player['country'] ?? '') == 'Sierra Leone' ? 'selected' : ''; ?>>Sierra Leone</option>
                            <option value="Ghana" <?php echo ($player['country'] ?? '') == 'Ghana' ? 'selected' : ''; ?>>Ghana</option>
                            <option value="Nigeria" <?php echo ($player['country'] ?? '') == 'Nigeria' ? 'selected' : ''; ?>>Nigeria</option>
                            <option value="Liberia" <?php echo ($player['country'] ?? '') == 'Liberia' ? 'selected' : ''; ?>>Liberia</option>
                            <option value="Guinea" <?php echo ($player['country'] ?? '') == 'Guinea' ? 'selected' : ''; ?>>Guinea</option>
                            <option value="Other" <?php echo ($player['country'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input" 
                               value="<?php echo htmlspecialchars($player['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select" required>
                            <option value="">Select Position</option>
                            <option value="Goalkeeper" <?php echo ($player['position'] ?? '') == 'Goalkeeper' ? 'selected' : ''; ?>>Goalkeeper</option>
                            <option value="Defender" <?php echo ($player['position'] ?? '') == 'Defender' ? 'selected' : ''; ?>>Defender</option>
                            <option value="Midfielder" <?php echo ($player['position'] ?? '') == 'Midfielder' ? 'selected' : ''; ?>>Midfielder</option>
                            <option value="Forward" <?php echo ($player['position'] ?? '') == 'Forward' ? 'selected' : ''; ?>>Forward</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Current Club</label>
                        <input type="text" name="current_club" class="form-input" 
                               value="<?php echo htmlspecialchars($player['current_club'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Player Details Form -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Player Details
            </h2>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" name="height" class="form-input" step="0.1" min="0"
                               value="<?php echo htmlspecialchars($player_profile['height'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" name="weight" class="form-input" step="0.1" min="0"
                               value="<?php echo htmlspecialchars($player_profile['weight'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Preferred Foot</label>
                        <select name="preferred_foot" class="form-select">
                            <option value="">Select Foot</option>
                            <option value="left" <?php echo ($player_profile['preferred_foot'] ?? '') == 'left' ? 'selected' : ''; ?>>Left</option>
                            <option value="right" <?php echo ($player_profile['preferred_foot'] ?? '') == 'right' ? 'selected' : ''; ?>>Right</option>
                            <option value="both" <?php echo ($player_profile['preferred_foot'] ?? '') == 'both' ? 'selected' : ''; ?>>Both</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Highlight Video URL</label>
                        <input type="url" name="video_url" class="form-input" 
                               value="<?php echo htmlspecialchars($player_profile['video_url'] ?? ''); ?>"
                               placeholder="https://youtube.com/...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Player Bio</label>
                    <textarea name="bio" class="form-input" 
                              placeholder="Tell us about the player's skills, achievements, and potential..."><?php echo htmlspecialchars($player_profile['bio'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_profile_details" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Details
                    </button>
                </div>
            </form>
        </div>

        <!-- Player Statistics -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-futbol"></i> Player Statistics
            </h2>
            
            <!-- Add New Stats Form -->
            <form method="POST" action="" style="margin-bottom: 30px;">
                <h3 style="color: var(--pure-white); margin-bottom: 15px; font-size: 16px;">
                    <i class="fas fa-plus-circle"></i> Add New Season Stats
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Season Year</label>
                        <input type="number" name="season_year" class="form-input" min="2000" max="2030"
                               value="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Matches Played</label>
                        <input type="number" name="matches_played" class="form-input" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Goals</label>
                        <input type="number" name="goals" class="form-input" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assists</label>
                        <input type="number" name="assists" class="form-input" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Yellow Cards</label>
                        <input type="number" name="yellow_cards" class="form-input" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Red Cards</label>
                        <input type="number" name="red_cards" class="form-input" min="0" value="0" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_stats" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Stats
                    </button>
                </div>
            </form>
            
            <!-- Existing Stats Table -->
            <h3 style="color: var(--pure-white); margin-bottom: 15px; font-size: 16px;">
                <i class="fas fa-history"></i> Previous Seasons
            </h3>
            
            <?php if (empty($player_stats)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 20px; color: var(--medium-grey);"></i>
                    <p>No statistics recorded yet.</p>
                    <p>Add the player's first season stats above.</p>
                </div>
            <?php else: ?>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Season</th>
                            <th>Matches</th>
                            <th>Goals</th>
                            <th>Assists</th>
                            <th>Yellow Cards</th>
                            <th>Red Cards</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($player_stats as $stats): ?>
                        <tr>
                            <td class="stat-value"><?php echo htmlspecialchars($stats['season_year']); ?></td>
                            <td><?php echo htmlspecialchars($stats['matches_played']); ?></td>
                            <td class="stat-value"><?php echo htmlspecialchars($stats['goals']); ?></td>
                            <td><?php echo htmlspecialchars($stats['assists']); ?></td>
                            <td><?php echo htmlspecialchars($stats['yellow_cards']); ?></td>
                            <td><?php echo htmlspecialchars($stats['red_cards']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn edit" onclick="openEditModal(<?php echo $stats['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="stats_id" value="<?php echo $stats['id']; ?>">
                                        <button type="submit" name="delete_stats" class="action-btn delete" 
                                                onclick="return confirm('Are you sure you want to delete these stats?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Stats Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Season Statistics</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" id="editStatsForm">
                <input type="hidden" name="stats_id" id="editStatsId">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Matches Played</label>
                        <input type="number" name="matches_played" id="editMatches" class="form-input" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Goals</label>
                        <input type="number" name="goals" id="editGoals" class="form-input" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assists</label>
                        <input type="number" name="assists" id="editAssists" class="form-input" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Yellow Cards</label>
                        <input type="number" name="yellow_cards" id="editYellowCards" class="form-input" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Red Cards</label>
                        <input type="number" name="red_cards" id="editRedCards" class="form-input" min="0" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_stats" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Stats
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal(statsId) {
            // Find the row with these stats
            const row = document.querySelector(`tr[data-stats-id="${statsId}"]`);
            if (row) {
                document.getElementById('editStatsId').value = statsId;
                document.getElementById('editMatches').value = row.cells[1].textContent;
                document.getElementById('editGoals').value = row.cells[2].textContent;
                document.getElementById('editAssists').value = row.cells[3].textContent;
                document.getElementById('editYellowCards').value = row.cells[4].textContent;
                document.getElementById('editRedCards').value = row.cells[5].textContent;
            }
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Add data attributes to table rows for easier access
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.stats-table tbody tr');
            tableRows.forEach((row, index) => {
                // Skip header row
                if (index > 0) {
                    const statsId = row.querySelector('input[name="stats_id"]')?.value;
                    if (statsId) {
                        row.setAttribute('data-stats-id', statsId);
                    }
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = 'var(--error-red)';
                    } else {
                        input.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        // Auto-calculate age from date of birth
        const dobInput = document.querySelector('input[name="date_of_birth"]');
        if (dobInput) {
            dobInput.addEventListener('change', function() {
                const dob = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                
                // Update a display element if you want to show age
                console.log('Age:', age);
            });
        }
    </script>
</body>

</html>