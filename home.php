<?php
// Start session
session_start();

// Include necessary files
require_once 'config/database.php';
require_once 'functions/users.php';
require_once 'functions/session.php';

// Check if user is logged in
$logged_in = isLoggedIn();
$current_user = getCurrentUser();

// Get real data from database
$players = getAllPlayers(6); // Get 6 players
$scouts = getScouts(3); // We'll create this function
$clubs = getClubs(4); // We'll create this function

// If logged in and trying to access home page, redirect to appropriate dashboard
if ($logged_in && isset($current_user['role'])) {
    switch ($current_user['role']) {
        case 'player':
            header('Location: player.php');
            exit();
        case 'scout':
            header('Location: home.php');
            exit();
        case 'club':
            header('Location: admin-dashboard.php');
            exit();
        default:
            // Stay on home page for other roles or if role is not defined
            break;
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Salone – Home</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800;900&display=swap"
        rel="stylesheet">
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
            --accent-blue: #2196F3;
            --accent-orange: #FF9800;
            --accent-purple: #9C27B0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: var(--pure-white);
            overflow-x: hidden;
        }

        /* STATIC BACKGROUND */
        .static-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.03) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.02) 0%, transparent 40%);
            z-index: -1;
        }

        /* NAVBAR */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--faint-white);
            padding: 20px 0;
            z-index: 1000;
            box-shadow: 0 4px 30px rgba(255, 255, 255, 0.03);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 3px;
            color: var(--pure-white);
            text-transform: uppercase;
            position: relative;
            padding: 10px 0;
            text-decoration: none;
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--pure-white);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.6s ease;
        }

        .logo:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .nav-links a {
            color: var(--medium-grey);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 1.5px;
            position: relative;
            padding: 8px 0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--pure-white);
            transition: width 0.4s ease;
        }

        .nav-links a:hover {
            color: var(--pure-white);
        }

        .nav-links a:hover::before {
            width: 100%;
        }

        .nav-links a.active {
            color: var(--pure-white);
            font-weight: 700;
        }

        .nav-links a.active::before {
            width: 100%;
        }

        /* User menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--light-grey);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pure-white);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--pure-white);
        }

        .user-role {
            font-size: 11px;
            color: var(--medium-grey);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid var(--light-grey);
            color: var(--pure-white);
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logout-btn:hover {
            background: var(--light-white);
            color: var(--dark-bg);
            border-color: var(--pure-white);
        }

        /* HERO SECTION */
        .hero-section {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 0 40px;
            margin-top: 80px;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #000000 0%, #0a0a0a 100%);
            z-index: -2;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 96px;
            font-weight: 900;
            letter-spacing: 4px;
            color: var(--pure-white);
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 30px;
        }

        .hero-subtitle {
            font-size: 20px;
            color: var(--medium-grey);
            max-width: 800px;
            margin: 0 auto 50px;
            line-height: 1.6;
            letter-spacing: 1px;
        }

        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 40px;
        }

        .btn {
            padding: 18px 40px;
            border-radius: 30px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--pure-white);
            color: var(--dark-bg);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95);
        }

        .btn-secondary {
            background: transparent;
            color: var(--pure-white);
            border: 2px solid var(--light-grey);
        }

        .btn-secondary:hover {
            border-color: var(--pure-white);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-5px);
        }

        .btn-success {
            background: var(--success-green);
            color: var(--pure-white);
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(76, 175, 80, 0.4);
            background: rgba(76, 175, 80, 0.9);
        }

        /* SECTION HEADERS */
        .section-header {
            text-align: center;
            margin-bottom: 80px;
            padding: 0 20px;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 56px;
            font-weight: 900;
            color: var(--pure-white);
            text-transform: uppercase;
            letter-spacing: 3px;
            position: relative;
            display: inline-block;
            padding-bottom: 20px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 2px;
            background: var(--pure-white);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .section-subtitle {
            font-size: 18px;
            color: var(--medium-grey);
            max-width: 700px;
            margin: 20px auto 0;
            line-height: 1.6;
        }

        /* PLAYERS SECTION - UPDATED */
        .players-section {
            padding: 120px 40px;
            background: var(--surface-bg);
            position: relative;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .player-card {
            background: var(--card-bg);
            border-radius: 25px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--dark-grey);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .player-card:hover {
            transform: translateY(-20px);
            border-color: var(--accent-blue);
            box-shadow:
                0 25px 50px rgba(33, 150, 243, 0.1),
                inset 0 0 40px rgba(33, 150, 243, 0.02);
        }

        .player-image {
            width: 100%;
            height: 300px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .player-image i {
            font-size: 120px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.6s ease;
        }

        .player-card:hover .player-image i {
            transform: scale(1.1);
            color: rgba(255, 255, 255, 1);
        }

        .player-number {
            position: absolute;
            top: 20px;
            right: 20px;
            font-family: 'Montserrat', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--pure-white);
            opacity: 0.2;
            z-index: 2;
        }

        .player-info {
            padding: 30px;
            position: relative;
        }

        .player-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--pure-white);
            margin-bottom: 10px;
        }

        .player-position {
            color: var(--light-grey);
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .player-position::before {
            content: '';
            width: 20px;
            height: 1px;
            background: var(--light-grey);
        }

        .player-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--accent-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--medium-grey);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .view-more-container {
            text-align: center;
            margin-top: 60px;
        }

        /* TEAMS SECTION - UPDATED */
        .teams-section {
            padding: 120px 40px;
            background: var(--surface-bg);
            position: relative;
        }

        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .team-card {
            background: var(--card-bg);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--dark-grey);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            padding: 40px 30px;
        }

        .team-card:hover {
            transform: translateY(-15px);
            border-color: var(--accent-orange);
            box-shadow:
                0 20px 40px rgba(255, 152, 0, 0.1),
                inset 0 0 30px rgba(255, 152, 0, 0.02);
        }

        .team-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            padding: 20px;
            border: 2px solid var(--dark-grey);
            transition: all 0.3s ease;
        }

        .team-card:hover .team-logo {
            border-color: var(--accent-orange);
            transform: scale(1.1);
        }

        .team-logo i {
            font-size: 50px;
            color: rgba(255, 255, 255, 0.9);
        }

        .team-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--pure-white);
            margin-bottom: 10px;
        }

        .team-league {
            color: var(--light-grey);
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .team-country {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--medium-grey);
            font-size: 14px;
            margin-bottom: 25px;
        }

        .team-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .team-stat {
            text-align: center;
        }

        .team-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent-orange);
            margin-bottom: 5px;
        }

        .team-stat-label {
            font-size: 11px;
            color: var(--medium-grey);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* AGENTS SECTION - UPDATED */
        .agents-section {
            padding: 120px 40px;
            background: var(--surface-bg);
            position: relative;
        }

        .agents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 50px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .agent-card {
            background: var(--card-bg);
            border-radius: 25px;
            padding: 40px;
            border: 1px solid var(--dark-grey);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .agent-card:hover {
            border-color: var(--accent-purple);
            box-shadow: 0 20px 50px rgba(156, 39, 176, 0.1);
            transform: translateY(-10px);
        }

        .agent-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--pure-white);
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.2);
            padding: 3px;
            position: relative;
            z-index: 2;
            overflow: hidden;
        }

        .agent-avatar i {
            font-size: 50px;
            color: rgba(255, 255, 255, 0.9);
        }

        .agent-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--pure-white);
            margin-bottom: 10px;
        }

        .agent-role {
            color: var(--light-grey);
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .agent-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 25px 0;
        }

        .agent-stat {
            text-align: center;
        }

        .agent-stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--accent-purple);
            margin-bottom: 5px;
        }

        .agent-stat-label {
            font-size: 11px;
            color: var(--medium-grey);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .agent-bio {
            color: var(--medium-grey);
            font-size: 14px;
            line-height: 1.6;
            margin-top: 20px;
        }

        /* CLUBS SECTION */
        .clubs-section {
            padding: 120px 40px;
            background: var(--surface-bg);
            position: relative;
        }

        .clubs-marquee {
            width: 100%;
            overflow: hidden;
            position: relative;
            padding: 40px 0;
            border-top: 1px solid var(--dark-grey);
            border-bottom: 1px solid var(--dark-grey);
        }

        .clubs-track {
            display: flex;
            gap: 80px;
            animation: marquee 30s linear infinite;
        }

        .club-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            min-width: 200px;
        }

        .club-logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            font-weight: 900;
            color: var(--medium-grey);
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .club-logo:hover {
            color: var(--pure-white);
            transform: scale(1.1);
        }

        .club-league {
            font-size: 12px;
            color: var(--light-grey);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        @keyframes marquee {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 80px 40px;
            background: rgba(0, 0, 0, 0.95);
            border-top: 1px solid var(--faint-white);
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg,
                    transparent,
                    var(--pure-white),
                    var(--light-grey),
                    transparent);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .footer-logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            font-weight: 900;
            color: var(--pure-white);
            letter-spacing: 4px;
            margin-bottom: 30px;
            text-transform: uppercase;
        }

        .footer-text {
            color: var(--medium-grey);
            font-size: 14px;
            letter-spacing: 2px;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.8;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 72px;
            }

            .teams-grid,
            .agents-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-title {
                font-size: 48px;
            }
        }

        @media (max-width: 900px) {
            .nav-container {
                padding: 0 20px;
                flex-direction: column;
                gap: 20px;
            }

            .nav-links {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .user-menu {
                margin-left: 0;
                margin-top: 10px;
            }

            .hero-title {
                font-size: 48px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            .teams-grid,
            .agents-grid {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .section-title {
                font-size: 36px;
            }

            .clubs-track {
                gap: 40px;
            }

            .club-item {
                min-width: 150px;
            }
        }

        @media (max-width: 600px) {
            .hero-title {
                font-size: 36px;
            }

            .hero-subtitle {
                font-size: 16px;
            }

            .teams-section,
            .agents-section,
            .clubs-section {
                padding: 80px 20px;
            }

            .section-title {
                font-size: 28px;
            }

            .team-stats,
            .agent-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .team-logo {
                width: 100px;
                height: 100px;
            }

            .club-item {
                min-width: 120px;
            }

            .club-logo {
                font-size: 24px;
            }

            .nav-links {
                gap: 10px;
            }

            .nav-links a {
                font-size: 13px;
            }
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--medium-grey);
            font-size: 18px;
            grid-column: 1 / -1;
        }

        /* Position Icons */
        .position-icon {
            font-size: 20px;
            margin-right: 8px;
        }

        .player-position-icon {
            color: var(--accent-blue);
        }

        .agent-role-icon {
            color: var(--accent-purple);
        }
    </style>
</head>

<body>
    <!-- STATIC BACKGROUND -->
    <div class="static-bg"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">SCOUT SALONE</a>
            <div class="nav-links">
                <a href="home.php" class="active">Home</a>
                <a href="about.html">About</a>
                <a href="contact.php">Contact</a>
                <a href="players.php">Players</a>
                <a href="clubs.php">Clubs</a>
                <a href="matches.html">Matches</a>
                
                
                <?php if ($logged_in): ?>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php 
                            // Get initials from full name or username
                            if (!empty($current_user['full_name'])) {
                                $name_parts = explode(' ', $current_user['full_name']);
                                $initials = '';
                                foreach ($name_parts as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                    if (strlen($initials) >= 2) break;
                                }
                                echo $initials;
                            } else {
                                echo strtoupper(substr($current_user['username'], 0, 2));
                            }
                            ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($current_user['username']); ?></div>
                            <div class="user-role"><?php echo ucfirst($current_user['role']); ?></div>
                        </div>
                        <a href="logout.php" class="logout-btn">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title">ELITE TALENT<br>DISCOVERY</h1>
            <p class="hero-subtitle">
                Connecting Africa's finest football prospects with the world's most prestigious clubs.
                Using cutting-edge analytics, global networks, and human expertise to shape the future of football.
            </p>
            <div class="hero-buttons">
                <?php if (!$logged_in): ?>
                    <button class="btn btn-primary" onclick="window.location.href='register.php'">
                        JOIN AS PLAYER
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='register.php?role=scout'">
                        BECOME A SCOUT
                    </button>
                <?php else: ?>
                    <?php if ($current_user['role'] == 'player'): ?>
                        <button class="btn btn-primary" onclick="window.location.href='player-dashboard.php'">
                            GO TO DASHBOARD
                        </button>
                        <button class="btn btn-secondary" onclick="window.location.href='players.php'">
                            BROWSE PLAYERS
                        </button>
                    <?php elseif ($current_user['role'] == 'scout'): ?>
                        <button class="btn btn-primary" onclick="window.location.href='scout-dashboard.php'">
                            GO TO DASHBOARD
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='search.php'">
                            SEARCH TALENT
                        </button>
                    <?php elseif ($current_user['role'] == 'club'): ?>
                        <button class="btn btn-primary" onclick="window.location.href='home.php'">
                            GO TO DASHBOARD
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='search.php'">
                            FIND PLAYERS
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- PLAYERS SECTION - UPDATED WITH DATABASE DATA -->
    <section id="players" class="players-section">
        <div class="section-header">
            <h2 class="section-title">Featured Players</h2>
            <p class="section-subtitle">
                Discover our elite roster of football talents, each handpicked for exceptional skill and potential
            </p>
        </div>

        <div class="players-grid">
            <?php if (empty($players)): ?>
                <div class="no-data">
                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px; color: var(--medium-grey);"></i>
                    <p>No players found in the database.</p>
                    <p>Be the first to register as a player!</p>
                </div>
            <?php else: ?>
                <?php 
                $player_images = [
                    'fas fa-futbol',
                    'fas fa-user',
                    'fas fa-running',
                    'fas fa-trophy',
                    'fas fa-shield-alt',
                    'fas fa-star'
                ];
                
                $player_count = 0;
                foreach ($players as $player): 
                    $image_index = $player_count % count($player_images);
                    $player_count++;
                    
                    // Calculate age if date of birth is available
                    $age = '';
                    if (!empty($player['date_of_birth'])) {
                        $birth_date = new DateTime($player['date_of_birth']);
                        $today = new DateTime();
                        $age = $today->diff($birth_date)->y;
                    }
                    
                    // Generate random stats for display
                    $goals = rand(5, 25);
                    $assists = rand(3, 15);
                    $matches = rand(10, 40);
                ?>
                <div class="player-card">
                    <div class="player-number"><?php echo $player_count; ?></div>
                    <div class="player-image">
                        <i class="<?php echo $player_images[$image_index]; ?>"></i>
                    </div>
                    <div class="player-info">
                        <h3 class="player-name"><?php echo strtoupper(htmlspecialchars($player['full_name'] ?: $player['username'])); ?></h3>
                        <div class="player-position">
                            <i class="fas fa-map-marker-alt position-icon player-position-icon"></i>
                            <?php echo strtoupper(htmlspecialchars($player['position'] ?: 'Football Player')); ?>
                        </div>
                        <div class="player-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $goals; ?></div>
                                <div class="stat-label">GOALS</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $assists; ?></div>
                                <div class="stat-label">ASSISTS</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $matches; ?></div>
                                <div class="stat-label">MATCHES</div>
                            </div>
                        </div>
                        <p style="color: var(--medium-grey); font-size: 14px; margin-top: 15px;">
                            <?php echo htmlspecialchars($player['country'] ?: 'International'); ?> player 
                            <?php echo $age ? "($age years old)" : ''; ?> 
                            <?php echo $player['current_club'] ? "playing for " . htmlspecialchars($player['current_club']) : 'seeking opportunities'; ?>.
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="view-more-container">
            <button class="btn btn-secondary" onclick="window.location.href='players.php'">
                VIEW MORE PLAYERS
            </button>
        </div>
    </section>

    <!-- CLUBS SECTION -->
    <section class="clubs-section">
        <div class="section-header">
            <h2 class="section-title">Affiliated Clubs</h2>
            <p class="section-subtitle">
                Partnering with elite football institutions across the globe
            </p>
        </div>

        <div class="clubs-marquee">
            <div class="clubs-track">
                <div class="club-item">
                    <div class="club-logo">PREMIER LEAGUE</div>
                    <div class="club-league">ENGLAND</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">LA LIGA</div>
                    <div class="club-league">SPAIN</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">SERIE A</div>
                    <div class="club-league">ITALY</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">BUNDESLIGA</div>
                    <div class="club-league">GERMANY</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">LIGUE 1</div>
                    <div class="club-league">FRANCE</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">EREDIVISIE</div>
                    <div class="club-league">NETHERLANDS</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">PRIMEIRA LIGA</div>
                    <div class="club-league">PORTUGAL</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">MLS</div>
                    <div class="club-league">USA/CANADA</div>
                </div>
                <!-- Duplicate for seamless animation -->
                <div class="club-item">
                    <div class="club-logo">PREMIER LEAGUE</div>
                    <div class="club-league">ENGLAND</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">LA LIGA</div>
                    <div class="club-league">SPAIN</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">SERIE A</div>
                    <div class="club-league">ITALY</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">BUNDESLIGA</div>
                    <div class="club-league">GERMANY</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">LIGUE 1</div>
                    <div class="club-league">FRANCE</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">EREDIVISIE</div>
                    <div class="club-league">NETHERLANDS</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">PRIMEIRA LIGA</div>
                    <div class="club-league">PORTUGAL</div>
                </div>
                <div class="club-item">
                    <div class="club-logo">MLS</div>
                    <div class="club-league">USA/CANADA</div>
                </div>
            </div>
        </div>
    </section>

    <!-- TEAMS SECTION - UPDATED WITH DATABASE CLUBS -->
    <section id="teams" class="teams-section">
        <div class="section-header">
            <h2 class="section-title">Registered Clubs</h2>
            <p class="section-subtitle">
                Football institutions actively seeking talent through our platform
            </p>
        </div>

        <div class="teams-grid">
            <?php if (empty($clubs)): ?>
                <div class="no-data">
                    <i class="fas fa-landmark" style="font-size: 48px; margin-bottom: 20px; color: var(--medium-grey);"></i>
                    <p>No clubs registered yet.</p>
                    <p>Be the first club to join our platform!</p>
                </div>
            <?php else: ?>
                <?php 
                $club_icons = [
                    'fas fa-futbol',
                    'fas fa-shield-alt',
                    'fas fa-trophy',
                    'fas fa-flag',
                    'fas fa-home',
                    'fas fa-star'
                ];
                
                $club_count = 0;
                foreach ($clubs as $club): 
                    $icon_index = $club_count % count($club_icons);
                    $club_count++;
                    
                    // Generate random stats for display
                    $players_scouted = rand(5, 50);
                    $years_active = rand(1, 10);
                    $transfers = rand(1, 20);
                ?>
                <div class="team-card">
                    <div class="team-logo">
                        <i class="<?php echo $club_icons[$icon_index]; ?>"></i>
                    </div>
                    <h3 class="team-name"><?php echo strtoupper(htmlspecialchars($club['club_name'] ?: $club['username'])); ?></h3>
                    <div class="team-league">REGISTERED CLUB</div>
                    <div class="team-country">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($club['country'] ?: 'International'); ?>
                    </div>
                    <div class="team-stats">
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo $players_scouted; ?></div>
                            <div class="team-stat-label">PLAYERS</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo $years_active; ?></div>
                            <div class="team-stat-label">YEARS</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo $transfers; ?></div>
                            <div class="team-stat-label">TRANSFERS</div>
                        </div>
                    </div>
                    <p style="color: var(--medium-grey); font-size: 14px; margin-top: 15px;">
                        Actively scouting talent through our platform. 
                        <?php echo htmlspecialchars($club['country'] ? 'Based in ' . $club['country'] : 'International club'); ?>.
                    </p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="view-more-container">
            <button class="btn btn-secondary" onclick="window.location.href='clubs.php'">
                VIEW ALL CLUBS
            </button>
        </div>
    </section>

    <!-- AGENTS SECTION - UPDATED WITH DATABASE SCOUTS -->
    <section class="agents-section">
        <div class="section-header">
            <h2 class="section-title">Our Scouts</h2>
            <p class="section-subtitle">
                Meet the professionals who discover and develop Africa's football talent
            </p>
        </div>

        <div class="agents-grid">
            <?php if (empty($scouts)): ?>
                <div class="no-data">
                    <i class="fas fa-binoculars" style="font-size: 48px; margin-bottom: 20px; color: var(--medium-grey);"></i>
                    <p>No scouts registered yet.</p>
                    <p>Be the first scout to join our platform!</p>
                </div>
            <?php else: ?>
                <?php 
                $scout_icons = [
                    'fas fa-user-tie',
                    'fas fa-binoculars',
                    'fas fa-chart-line',
                    'fas fa-search',
                    'fas fa-globe',
                    'fas fa-award'
                ];
                
                $scout_count = 0;
                foreach ($scouts as $scout): 
                    $icon_index = $scout_count % count($scout_icons);
                    $scout_count++;
                    
                    // Generate random stats for display
                    $players_discovered = rand(5, 30);
                    $years_experience = rand(3, 15);
                    $countries = rand(1, 8);
                ?>
                <div class="agent-card">
                    <div class="agent-avatar">
                        <i class="<?php echo $scout_icons[$icon_index]; ?>"></i>
                    </div>
                    <h3 class="agent-name"><?php echo strtoupper(htmlspecialchars($scout['full_name'] ?: $scout['username'])); ?></h3>
                    <div class="agent-role">
                        <i class="fas fa-map-marker-alt position-icon agent-role-icon"></i>
                        PROFESSIONAL SCOUT
                    </div>
                    <div class="agent-stats">
                        <div class="agent-stat">
                            <div class="agent-stat-value"><?php echo $players_discovered; ?></div>
                            <div class="agent-stat-label">PLAYERS</div>
                        </div>
                        <div class="agent-stat">
                            <div class="agent-stat-value"><?php echo $years_experience; ?>+</div>
                            <div class="agent-stat-label">YEARS</div>
                        </div>
                        <div class="agent-stat">
                            <div class="agent-stat-value"><?php echo $countries; ?></div>
                            <div class="agent-stat-label">COUNTRIES</div>
                        </div>
                    </div>
                    <p class="agent-bio">
                        Experienced scout specializing in talent discovery and development. 
                        Based in <?php echo htmlspecialchars($scout['country'] ?: 'various countries'); ?> 
                        with extensive networks across football communities.
                    </p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="view-more-container">
            <button class="btn btn-secondary" onclick="window.location.href='about.php'">
                MEET OUR FULL TEAM
            </button>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-logo">SCOUT SALONE</div>
        <div class="footer-text">
            We are building the future of football talent discovery. Through technology, data, and human insight,
            we're not just finding players – we're engineering legends.
        </div>
        <br>
        <div class="footer-text">
            <?php 
            $player_count = getPlayerCount();
            $total_users = getPlayerCount() + count($scouts) + count($clubs);
            ?>
            Platform Stats: <?php echo $player_count; ?> Players • <?php echo count($scouts); ?> Scouts • <?php echo count($clubs); ?> Clubs
        </div>
        <div class="footer-text">2025 SCOUT SALONE FOOTBALL AGENCY © ALL RIGHTS RESERVED</div>
    </footer>

    <script>
        // Simple hover effects for player and agent cards
        document.querySelectorAll('.player-card, .agent-card, .team-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-20px)';
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });

        // Smooth scroll for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Marquee animation pause on hover
        const marqueeTrack = document.querySelector('.clubs-track');
        if (marqueeTrack) {
            marqueeTrack.addEventListener('mouseenter', () => {
                marqueeTrack.style.animationPlayState = 'paused';
            });

            marqueeTrack.addEventListener('mouseleave', () => {
                marqueeTrack.style.animationPlayState = 'running';
            });
        }

        // Add animation to icons on hover
        document.querySelectorAll('.player-image i, .agent-avatar i, .team-logo i').forEach(icon => {
            icon.addEventListener('mouseenter', () => {
                icon.style.transform = 'scale(1.2) rotate(10deg)';
            });

            icon.addEventListener('mouseleave', () => {
                icon.style.transform = 'scale(1) rotate(0deg)';
            });
        });
    </script>
</body>

</html>