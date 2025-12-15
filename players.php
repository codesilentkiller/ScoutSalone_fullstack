<?php
session_start();
require_once 'config/database.php';

// Function to get all players from database
function getAllPlayers() {
    $conn = getDatabaseConnection();
    
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.full_name, 
                u.country, 
                u.position, 
                u.current_club, 
                u.date_of_birth,
                u.phone,
                u.email,
                u.created_at,
                p.height, 
                p.weight, 
                p.preferred_foot,
                p.bio,
                ps.matches_played,
                ps.goals,
                ps.assists,
                ps.yellow_cards,
                ps.red_cards
            FROM users u
            LEFT JOIN player_profiles p ON u.id = p.user_id
            LEFT JOIN player_stats ps ON u.id = ps.user_id AND ps.season_year = YEAR(CURDATE())
            WHERE u.role = 'player'
            ORDER BY u.created_at DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching players: " . $e->getMessage());
        return [];
    }
}

// Get players from database
$players = getAllPlayers();

// Calculate age from date of birth
function calculateAge($date_of_birth) {
    if (empty($date_of_birth)) return 'N/A';
    $birthDate = new DateTime($date_of_birth);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

// Map positions for filtering
function getPositionClass($position) {
    $position_lower = strtolower($position);
    if (strpos($position_lower, 'forward') !== false || strpos($position_lower, 'striker') !== false || strpos($position_lower, 'winger') !== false) {
        return 'forward';
    } elseif (strpos($position_lower, 'midfield') !== false) {
        return 'midfielder';
    } elseif (strpos($position_lower, 'defender') !== false || strpos($position_lower, 'back') !== false) {
        return 'defender';
    } elseif (strpos($position_lower, 'goalkeeper') !== false || strpos($position_lower, 'keeper') !== false) {
        return 'goalkeeper';
    } else {
        return 'midfielder'; // default
    }
}

// Get player features based on position
function getPlayerFeatures($position) {
    $features = [];
    $position_lower = strtolower($position);
    
    if (strpos($position_lower, 'forward') !== false || strpos($position_lower, 'striker') !== false) {
        $features = ['Finishing', 'Speed', 'Dribbling'];
    } elseif (strpos($position_lower, 'midfield') !== false) {
        $features = ['Passing', 'Vision', 'Stamina'];
    } elseif (strpos($position_lower, 'defender') !== false) {
        $features = ['Tackling', 'Strength', 'Positioning'];
    } elseif (strpos($position_lower, 'goalkeeper') !== false) {
        $features = ['Reflexes', 'Command', 'Distribution'];
    } else {
        $features = ['Versatile', 'Technical', 'Professional'];
    }
    
    return $features;
}

// Get player stats based on position
function getPlayerStats($position, $stats) {
    if (strpos(strtolower($position), 'goalkeeper') !== false) {
        return [
            ['icon' => 'hands', 'label' => 'Saves', 'value' => $stats['goals'] * 2 + 15],
            ['icon' => 'times-circle', 'label' => 'Clean Sheets', 'value' => floor($stats['matches_played'] / 3)]
        ];
    } else {
        return [
            ['icon' => 'futbol', 'label' => 'Goals', 'value' => $stats['goals']],
            ['icon' => 'assistive-listening-systems', 'label' => 'Assists', 'value' => $stats['assists']]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Players - Scout Salone Football Agency</title>
    <link rel="stylesheet" href="main.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --black: #000000;
            --white: #ffffff;
            --grey-10: #0a0a0a;
            --grey-20: #1a1a1a;
            --grey-30: #2a2a2a;
            --grey-40: #3a3a3a;
            --grey-50: #4a4a4a;
            --grey-60: #5a5a5a;
            --grey-70: #6a6a6a;
            --grey-80: #8a8a8a;
            --grey-90: #aaaaaa;
            --grey-95: #d0d0d0;
            --accent: #ffffff;
            --accent-light: #f5f5f5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--grey-10);
            color: var(--white);
            line-height: 1.6;
        }

        h1,
        h2,
        h3,
        h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }

        /* Navbar Styling */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--black);
            padding: 15px 40px;
            z-index: 1000;
            border-bottom: 1px solid var(--grey-30);
            position: relative;
            flex-wrap: wrap;
            min-height: 70px;
        }

        .navbar a {
            color: var(--white);
            text-decoration: none;
            margin: 0 25px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
            position: relative;
            padding: 5px 0;
            transition: all 0.3s ease;
        }

        .navbar a:hover {
            color: var(--accent);
        }

        .navbar a.active {
            color: var(--accent);
            font-weight: 700;
        }

        .navbar a.active::after,
        .navbar a:hover::after {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 0;
            height: 2px;
            width: 100%;
            background: var(--white);
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.3);
        }

        .logo {
            position: absolute;
            left: 40px;
            color: var(--white);
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Hero Section */
        .players-hero {
            height: 65vh;
            background: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.95)),
                url('https://images.pexels.com/photos/1925216/pexels-photo-1925216.jpeg') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0 20px;
            position: relative;
            overflow: hidden;
        }

        .players-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, transparent 30%, rgba(0, 0, 0, 0.9) 70%);
        }

        .hero-content {
            position: relative;
            z-index: 10;
            max-width: 800px;
        }

        .players-hero h1 {
            font-size: 72px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--white);
            margin-bottom: 20px;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.7);
            letter-spacing: 2px;
        }

        .hero-divider {
            width: 120px;
            height: 3px;
            background: var(--white);
            margin: 0 auto 25px auto;
        }

        .players-hero p {
            font-size: 20px;
            color: var(--grey-95);
            max-width: 600px;
            margin: 0 auto;
            font-weight: 300;
            line-height: 1.8;
        }

        /* Players Section */
        .players-section {
            padding: 80px 40px;
            max-width: 1400px;
            margin: auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-size: 48px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--white);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--white);
        }

        .section-header p {
            font-size: 18px;
            color: var(--grey-80);
            max-width: 700px;
            margin: 0 auto;
        }

        /* Stats Counter */
        .stats-counter {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0 50px 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--grey-80);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Filter Controls */
        .filter-controls {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 50px;
        }

        .filter-btn {
            background: var(--grey-20);
            color: var(--grey-95);
            border: 1px solid var(--grey-40);
            padding: 10px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 1px;
        }

        .filter-btn:hover {
            background: var(--grey-30);
            border-color: var(--grey-70);
            color: var(--white);
        }

        .filter-btn.active {
            background: var(--white);
            color: var(--black);
            border-color: var(--white);
        }

        /* Players Grid */
        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 35px;
        }

        .player-card {
            background: var(--grey-20);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            border: 1px solid var(--grey-30);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            opacity: 1;
            transform: translateY(0);
            display: block;
        }

        .player-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(255, 255, 255, 0.1);
            border-color: var(--grey-70);
        }

        .player-card:hover .player-img {
            transform: scale(1.05);
            filter: brightness(1.1);
        }

        .player-card:hover .player-position {
            background: var(--white);
            color: var(--black);
        }

        .player-img-container {
            height: 320px;
            overflow: hidden;
            position: relative;
            background: var(--grey-10);
        }

        .player-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
            filter: grayscale(20%);
        }

        .player-position {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid var(--grey-70);
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .player-info {
            padding: 25px;
            text-align: center;
            background: var(--grey-20);
        }

        .player-info h3 {
            color: var(--white);
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .player-info p {
            margin: 8px 0;
            color: var(--grey-90);
            font-size: 16px;
        }

        .player-team {
            color: var(--white) !important;
            font-weight: 600;
            margin-bottom: 15px !important;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Stats */
        .stats {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .stat {
            background: var(--grey-30);
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 14px;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--grey-40);
        }

        .stat i {
            font-size: 12px;
            color: var(--grey-95);
        }

        /* Player Features */
        .player-features {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .feature {
            background: var(--grey-30);
            color: var(--grey-95);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid var(--grey-40);
        }

        .player-details {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--grey-30);
        }

        .detail-item {
            text-align: center;
        }

        .detail-value {
            color: var(--white);
            font-weight: 700;
            font-size: 16px;
        }

        .detail-label {
            color: var(--grey-70);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        /* No Players Message */
        .no-players {
            text-align: center;
            padding: 60px 20px;
            background: var(--grey-20);
            border-radius: 8px;
            border: 2px dashed var(--grey-40);
        }

        .no-players i {
            font-size: 48px;
            color: var(--grey-70);
            margin-bottom: 20px;
        }

        .no-players h3 {
            color: var(--white);
            margin-bottom: 10px;
        }

        .no-players p {
            color: var(--grey-80);
            margin-bottom: 20px;
        }

        /* Footer */
        .footer {
            background-color: var(--black);
            text-align: center;
            padding: 30px 20px;
            margin-top: 80px;
            border-top: 1px solid var(--grey-30);
        }

        .fText {
            color: var(--grey-70);
            font-size: 16px;
            letter-spacing: 1px;
        }

        /* Responsive Design */
        @media (max-width: 1100px) {
            .players-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
            }

            .logo {
                position: relative;
                left: 0;
                margin-bottom: 15px;
            }

            .navbar a {
                margin: 5px 15px;
            }

            .players-hero h1 {
                font-size: 48px;
            }

            .players-hero p {
                font-size: 18px;
            }

            .section-header h2 {
                font-size: 36px;
            }

            .players-section {
                padding: 60px 20px;
            }

            .filter-controls {
                gap: 8px;
            }

            .filter-btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .stats-counter {
                gap: 20px;
            }

            .stat-number {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .players-hero h1 {
                font-size: 36px;
            }

            .players-hero p {
                font-size: 16px;
            }

            .section-header h2 {
                font-size: 28px;
            }

            .player-img-container {
                height: 260px;
            }

            .players-grid {
                grid-template-columns: 1fr;
            }

            .stats {
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }

            .player-details {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="logo">SCOUT SALONE</div>
        <a href="home.php">Home</a>
        <a href="about.html">About</a>
        <a href="contact.html">Contact</a>
        <a href="players.php" class="active">Players</a>
        <a href="clubs.html">Clubs</a>
        <a href="matches.html">Matches</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </nav>

    <!-- HERO BANNER -->
    <section class="players-hero">
        <div class="hero-content">
            <h1>Our Players</h1>
            <div class="hero-divider"></div>
            <p>Discover elite football talent represented by Scout Salone. Our players combine technical excellence,
                tactical intelligence, and professional dedication to excel at the highest levels of the sport.</p>
        </div>
    </section>

    <!-- PLAYERS SECTION -->
    <section class="players-section">
        <div class="section-header">
            <h2>Player Roster</h2>
            <p>Professionals ready for the next level. Filter by position to explore our talent pool.</p>
        </div>

        <!-- Stats Counter -->
        <div class="stats-counter">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($players); ?></div>
                <div class="stat-label">Total Players</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $forwards = array_filter($players, function($player) {
                        return getPositionClass($player['position']) === 'forward';
                    });
                    echo count($forwards);
                    ?>
                </div>
                <div class="stat-label">Forwards</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $midfielders = array_filter($players, function($player) {
                        return getPositionClass($player['position']) === 'midfielder';
                    });
                    echo count($midfielders);
                    ?>
                </div>
                <div class="stat-label">Midfielders</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <?php 
                    $defenders = array_filter($players, function($player) {
                        return getPositionClass($player['position']) === 'defender';
                    });
                    echo count($defenders);
                    ?>
                </div>
                <div class="stat-label">Defenders</div>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <button class="filter-btn active" data-filter="all">All Players (<?php echo count($players); ?>)</button>
            <button class="filter-btn" data-filter="forward">Forwards (<?php echo count($forwards); ?>)</button>
            <button class="filter-btn" data-filter="midfielder">Midfielders (<?php echo count($midfielders); ?>)</button>
            <button class="filter-btn" data-filter="defender">Defenders (<?php echo count($defenders); ?>)</button>
            <button class="filter-btn" data-filter="goalkeeper">Goalkeepers</button>
        </div>

        <!-- PLAYERS GRID -->
        <div class="players-grid">
            <?php if (empty($players)): ?>
                <div class="no-players">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Players Found</h3>
                    <p>There are currently no players in the database.</p>
                    <?php if (isset($_SESSION['admin_logged_in']) || isset($_SESSION['user_id'])): ?>
                        <a href="admin-add-player.php" style="display: inline-block; padding: 10px 20px; background: var(--white); color: var(--black); text-decoration: none; border-radius: 4px; font-weight: 600;">
                            Add First Player
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($players as $player): ?>
                    <?php
                    $player_age = calculateAge($player['date_of_birth']);
                    $position_class = getPositionClass($player['position']);
                    $features = getPlayerFeatures($player['position']);
                    $player_stats = getPlayerStats($player['position'], [
                        'goals' => $player['goals'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'matches_played' => $player['matches_played'] ?? 0
                    ]);
                    ?>
                    
                    <div class="player-card" data-position="<?php echo $position_class; ?>">
                        <div class="player-img-container">
                            <!-- Using the same image for all players as requested -->
                            <img src="https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="<?php echo htmlspecialchars($player['full_name'] ?: $player['username']); ?>" class="player-img">
                            <div class="player-position"><?php echo htmlspecialchars($player['position'] ?: 'Player'); ?></div>
                        </div>
                        <div class="player-info">
                            <h3><?php echo htmlspecialchars($player['full_name'] ?: $player['username']); ?></h3>
                            <p class="player-team"><?php echo htmlspecialchars($player['current_club'] ?: 'Scout Salone FC'); ?></p>
                            
                            <div class="stats">
                                <?php foreach ($player_stats as $stat): ?>
                                <div class="stat">
                                    <i class="fas fa-<?php echo $stat['icon']; ?>"></i>
                                    <?php echo $stat['value']; ?> <?php echo $stat['label']; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="player-features">
                                <?php foreach ($features as $feature): ?>
                                <span class="feature"><?php echo $feature; ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="player-details">
                                <div class="detail-item">
                                    <div class="detail-value"><?php echo $player_age; ?></div>
                                    <div class="detail-label">Age</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value"><?php echo htmlspecialchars($player['height'] ? $player['height'] . 'm' : 'N/A'); ?></div>
                                    <div class="detail-label">Height</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value"><?php echo htmlspecialchars($player['preferred_foot'] ?: 'Right'); ?></div>
                                    <div class="detail-label">Foot</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <p class="fText">© 2025 SCOUT SALONE FOOTBALL AGENCY. ALL RIGHTS RESERVED.</p>
    </footer>

    <script>
        // Filter functionality for player cards
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const playerCards = document.querySelectorAll('.player-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', function () {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));

                    // Add active class to clicked button
                    this.classList.add('active');

                    const filterValue = this.getAttribute('data-filter');

                    // Show/hide player cards based on filter
                    playerCards.forEach(card => {
                        if (filterValue === 'all' || card.getAttribute('data-position') === filterValue) {
                            card.style.display = 'block';
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, 10);
                        } else {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                card.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });

            // Add hover effect to player cards
            playerCards.forEach(card => {
                card.addEventListener('mouseenter', function () {
                    this.style.zIndex = '10';
                });

                card.addEventListener('mouseleave', function () {
                    this.style.zIndex = '1';
                });
            });
        });
    </script>
</body>
</html>