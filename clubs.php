<?php
session_start();
require_once 'functions/session.php';
require_once 'functions/users.php';

$pageTitle = "Football Clubs - Scout Salone";
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="main.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            --primary: #1e90ff;
            --success: #2ecc71;
            --error: #e74c3c;
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
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
        }

        .logo {
            color: var(--white);
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            flex: 1;
            margin: 0 20px;
        }

        .navbar a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.5px;
            position: relative;
            padding: 5px 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
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

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 40px 20px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 50px;
            padding: 30px 20px;
            background: var(--grey-20);
            border-radius: 10px;
            border: 1px solid var(--grey-40);
        }

        .page-header h1 {
            font-size: 42px;
            margin-bottom: 15px;
            color: var(--white);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .page-header p {
            font-size: 18px;
            color: var(--grey-95);
            max-width: 800px;
            margin: 0 auto;
        }

        /* Search and Filters */
        .search-filters {
            background: var(--grey-20);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid var(--grey-40);
        }

        .search-filters h2 {
            margin-bottom: 25px;
            color: var(--white);
            font-size: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            color: var(--white);
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-select,
        .filter-input {
            padding: 12px 15px;
            background: var(--grey-30);
            border: 1px solid var(--grey-50);
            border-radius: 5px;
            color: var(--white);
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(30, 144, 255, 0.2);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #1c7ed6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 144, 255, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--grey-60);
        }

        .btn-secondary:hover {
            border-color: var(--white);
            background: var(--grey-30);
            transform: translateY(-2px);
        }

        /* Clubs Grid */
        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .club-card {
            background: var(--grey-20);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--grey-40);
            transition: all 0.3s ease;
            position: relative;
        }

        .club-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .club-header {
            padding: 25px;
            background: var(--grey-30);
            border-bottom: 1px solid var(--grey-40);
            text-align: center;
        }

        .club-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: var(--grey-40);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--white);
            border: 3px solid var(--primary);
        }

        .club-name {
            font-size: 22px;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 5px;
        }

        .club-location {
            color: var(--grey-95);
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .club-body {
            padding: 25px;
        }

        .club-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--grey-40);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--grey-90);
            font-size: 14px;
            font-weight: 600;
        }

        .info-value {
            color: var(--white);
            font-weight: 600;
        }

        .club-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
            text-align: center;
        }

        .stat-item {
            padding: 15px;
            background: var(--grey-30);
            border-radius: 5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--grey-90);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .club-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 12px;
            flex: 1;
        }

        /* Featured Clubs Section */
        .featured-section {
            margin-bottom: 60px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 28px;
            color: var(--white);
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .page-btn {
            padding: 10px 15px;
            background: var(--grey-30);
            border: 1px solid var(--grey-50);
            border-radius: 5px;
            color: var(--white);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .page-btn:hover {
            background: var(--grey-40);
            border-color: var(--primary);
        }

        .page-btn.active {
            background: var(--primary);
            border-color: var(--primary);
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-btn.disabled:hover {
            background: var(--grey-30);
            border-color: var(--grey-50);
        }

        /* Footer */
        .footer {
            background-color: var(--black);
            text-align: center;
            padding: 30px 20px;
            border-top: 1px solid var(--grey-30);
            margin-top: 50px;
        }

        .fText {
            color: var(--grey-70);
            font-size: 16px;
            letter-spacing: 1px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--grey-20);
            border-radius: 10px;
            border: 2px dashed var(--grey-40);
        }

        .empty-state i {
            font-size: 60px;
            color: var(--grey-60);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--white);
            margin-bottom: 15px;
            font-size: 24px;
        }

        .empty-state p {
            color: var(--grey-90);
            max-width: 500px;
            margin: 0 auto 25px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .nav-links {
                gap: 10px;
            }
            
            .navbar a {
                font-size: 14px;
                padding: 5px 6px;
            }
            
            .clubs-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 1024px) {
            .page-header h1 {
                font-size: 36px;
            }
            
            .nav-links {
                gap: 8px;
            }
            
            .navbar a {
                font-size: 13px;
                padding: 5px 4px;
            }
            
            .clubs-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 900px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
                gap: 15px;
            }
            
            .logo {
                font-size: 22px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
                margin: 10px 0;
            }
            
            .navbar a {
                margin: 0 8px;
                font-size: 14px;
                padding: 5px 8px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .page-header {
                padding: 20px;
                margin-bottom: 30px;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .page-header p {
                font-size: 16px;
            }
            
            .search-filters {
                padding: 20px;
            }
            
            .clubs-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .club-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 12px 15px;
            }
            
            .logo {
                font-size: 20px;
                letter-spacing: 1px;
            }
            
            .nav-links {
                gap: 8px;
            }
            
            .navbar a {
                font-size: 12px;
                margin: 0 3px;
                padding: 4px 5px;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .club-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 10px;
            }
            
            .logo {
                font-size: 18px;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
                gap: 10px;
                display: none;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .menu-toggle {
                display: block;
                position: absolute;
                right: 20px;
                top: 15px;
            }
            
            .navbar {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .logo {
                margin-left: 10px;
            }
            
            .club-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 360px) {
            .navbar a {
                font-size: 11px;
                padding: 3px 4px;
            }
            
            .logo {
                font-size: 16px;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="logo">SCOUT SALONE</div>
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="nav-links" id="navLinks">
            <a href="home.php">Home</a>
            <a href="about.html">About</a>
            <a href="players.php">Players</a>
            <a href="clubs.php" class="active">Clubs</a>
            <a href="matches.html">Matches</a>
            <a href="contact.php">Contact Us</a>
            <?php if ($user): ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="register.php">Register</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Football Clubs</h1>
            <p>Discover top football clubs in Sierra Leone and beyond. Connect with clubs looking for talent and explore opportunities to showcase your skills.</p>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <h2>Find Clubs</h2>
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="country">Country</label>
                    <select id="country" class="filter-select">
                        <option value="">All Countries</option>
                        <option value="Sierra Leone" selected>Sierra Leone</option>
                        <option value="Ghana">Ghana</option>
                        <option value="Nigeria">Nigeria</option>
                        <option value="Liberia">Liberia</option>
                        <option value="Guinea">Guinea</option>
                        <option value="Ivory Coast">Ivory Coast</option>
                        <option value="Senegal">Senegal</option>
                        <option value="International">International</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="league">League Level</label>
                    <select id="league" class="filter-select">
                        <option value="">All Levels</option>
                        <option value="Professional">Professional</option>
                        <option value="Semi-Professional">Semi-Professional</option>
                        <option value="Amateur">Amateur</option>
                        <option value="Academy">Academy</option>
                        <option value="Youth">Youth</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="facilities">Facilities</label>
                    <select id="facilities" class="filter-select">
                        <option value="">All Facilities</option>
                        <option value="Training Ground">Training Ground</option>
                        <option value="Stadium">Stadium</option>
                        <option value="Gym">Gym Facilities</option>
                        <option value="Medical">Medical Center</option>
                        <option value="Academy">Youth Academy</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search Clubs</label>
                    <input type="text" id="search" class="filter-input" placeholder="Enter club name or location...">
                </div>
            </div>
            
            <div class="filter-actions">
                <button class="btn btn-secondary" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
                <button class="btn btn-primary" id="applyFilters">
                    <i class="fas fa-search"></i> Search Clubs
                </button>
            </div>
        </div>

        <!-- Featured Clubs -->
        <div class="featured-section">
            <div class="section-header">
                <h2>Featured Clubs</h2>
                <a href="#" class="view-all">View All Featured</a>
            </div>
            
            <div class="clubs-grid" id="featuredClubs">
                <!-- Featured clubs will be loaded here -->
            </div>
        </div>

        <!-- All Clubs -->
        <div class="featured-section">
            <div class="section-header">
                <h2>All Football Clubs</h2>
                <div class="sort-options">
                    <select id="sortBy" class="filter-select" style="width: 200px;">
                        <option value="name_asc">Name: A to Z</option>
                        <option value="name_desc">Name: Z to A</option>
                        <option value="established_asc">Established: Oldest</option>
                        <option value="established_desc">Established: Newest</option>
                        <option value="players_asc">Players: Fewest</option>
                        <option value="players_desc">Players: Most</option>
                    </select>
                </div>
            </div>
            
            <div class="clubs-grid" id="allClubs">
                <!-- All clubs will be loaded here -->
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="page-btn disabled" id="prevPage">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">3</button>
                <button class="page-btn">4</button>
                <button class="page-btn">5</button>
                <button class="page-btn" id="nextPage">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p class="fText">© 2025 SCOUT SALONE FOOTBALL AGENCY. ALL RIGHTS RESERVED.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const navLinks = document.getElementById('navLinks');
            const featuredClubs = document.getElementById('featuredClubs');
            const allClubs = document.getElementById('allClubs');
            const searchInput = document.getElementById('search');
            const countrySelect = document.getElementById('country');
            const leagueSelect = document.getElementById('league');
            const facilitiesSelect = document.getElementById('facilities');
            const sortSelect = document.getElementById('sortBy');
            const applyFiltersBtn = document.getElementById('applyFilters');
            const resetFiltersBtn = document.getElementById('resetFilters');
            const prevPageBtn = document.getElementById('prevPage');
            const nextPageBtn = document.getElementById('nextPage');
            
            // Sample club data (in a real application, this would come from a database)
            const clubsData = [
                {
                    id: 1,
                    name: "East End Lions FC",
                    location: "Freetown, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Professional",
                    established: 1928,
                    stadium: "National Stadium",
                    capacity: "36,000",
                    coach: "John Keister",
                    players: 25,
                    trophies: 15,
                    facilities: ["Training Ground", "Stadium", "Medical"],
                    description: "One of Sierra Leone's most successful football clubs with a rich history.",
                    featured: true
                },
                {
                    id: 2,
                    name: "Mighty Blackpool FC",
                    location: "Freetown, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Professional",
                    established: 1923,
                    stadium: "Trade Center Field",
                    capacity: "10,000",
                    coach: "Lansana Turay",
                    players: 22,
                    trophies: 12,
                    facilities: ["Training Ground", "Gym"],
                    description: "Known for developing young talents and competitive spirit.",
                    featured: true
                },
                {
                    id: 3,
                    name: "Bo Rangers FC",
                    location: "Bo, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Professional",
                    established: 1963,
                    stadium: "Bo Stadium",
                    capacity: "15,000",
                    coach: "Abdulai Bah",
                    players: 24,
                    trophies: 8,
                    facilities: ["Training Ground", "Stadium", "Academy"],
                    description: "Dominant force in Southern Sierra Leone football.",
                    featured: true
                },
                {
                    id: 4,
                    name: "Kallon FC",
                    location: "Freetown, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Professional",
                    established: 2002,
                    stadium: "Kallon Field",
                    capacity: "5,000",
                    coach: "Mohamed Kallon",
                    players: 28,
                    trophies: 5,
                    facilities: ["Training Ground", "Academy", "Medical"],
                    description: "Founded by football legend Mohamed Kallon.",
                    featured: false
                },
                {
                    id: 5,
                    name: "Asante Kotoko SC",
                    location: "Kumasi, Ghana",
                    country: "Ghana",
                    league: "Professional",
                    established: 1935,
                    stadium: "Baba Yara Stadium",
                    capacity: "40,528",
                    coach: "Prosper Narteh Ogum",
                    players: 30,
                    trophies: 25,
                    facilities: ["Training Ground", "Stadium", "Gym", "Medical"],
                    description: "One of Africa's most successful football clubs.",
                    featured: false
                },
                {
                    id: 6,
                    name: "Hearts of Oak SC",
                    location: "Accra, Ghana",
                    country: "Ghana",
                    league: "Professional",
                    established: 1911,
                    stadium: "Accra Sports Stadium",
                    capacity: "40,000",
                    coach: "Samuel Boadu",
                    players: 27,
                    trophies: 21,
                    facilities: ["Training Ground", "Stadium", "Academy"],
                    description: "Historic Ghanaian club with continental success.",
                    featured: false
                },
                {
                    id: 7,
                    name: "Rivers United FC",
                    location: "Port Harcourt, Nigeria",
                    country: "Nigeria",
                    league: "Professional",
                    established: 2016,
                    stadium: "Yakubu Gowon Stadium",
                    capacity: "16,000",
                    coach: "Stanley Eguma",
                    players: 26,
                    trophies: 3,
                    facilities: ["Training Ground", "Stadium"],
                    description: "Nigerian professional football club based in Port Harcourt.",
                    featured: false
                },
                {
                    id: 8,
                    name: "LISCR FC",
                    location: "Monrovia, Liberia",
                    country: "Liberia",
                    league: "Professional",
                    established: 1995,
                    stadium: "Antoinette Tubman Stadium",
                    capacity: "10,000",
                    coach: "Tapha Manneh",
                    players: 23,
                    trophies: 7,
                    facilities: ["Training Ground", "Stadium"],
                    description: "Leading football club in Liberia.",
                    featured: false
                },
                {
                    id: 9,
                    name: "FC Kallon Academy",
                    location: "Freetown, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Academy",
                    established: 2010,
                    stadium: "Kallon Academy Field",
                    capacity: "2,000",
                    coach: "Various Coaches",
                    players: 45,
                    trophies: 0,
                    facilities: ["Training Ground", "Academy", "Medical"],
                    description: "Youth development academy for aspiring footballers.",
                    featured: false
                },
                {
                    id: 10,
                    name: "Ports Authority FC",
                    location: "Freetown, Sierra Leone",
                    country: "Sierra Leone",
                    league: "Professional",
                    established: 1978,
                    stadium: "National Stadium",
                    capacity: "36,000",
                    coach: "Khalil Jabbie",
                    players: 21,
                    trophies: 4,
                    facilities: ["Training Ground"],
                    description: "Competitive team with strong midfield play.",
                    featured: false
                }
            ];
            
            // Mobile menu toggle
            menuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('active');
                const icon = this.querySelector('i');
                if (navLinks.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
            
            // Close menu when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 480) {
                    if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
                        navLinks.classList.remove('active');
                        const icon = menuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });
            
            // Function to render club cards
            function renderClubCard(club) {
                const facilitiesHtml = club.facilities.map(facility => 
                    `<span style="display: inline-block; background: var(--grey-30); padding: 4px 8px; border-radius: 3px; margin: 2px; font-size: 12px;">${facility}</span>`
                ).join('');
                
                return `
                    <div class="club-card" data-id="${club.id}">
                        <div class="club-header">
                            <div class="club-logo">
                                <i class="fas fa-futbol"></i>
                            </div>
                            <h3 class="club-name">${club.name}</h3>
                            <div class="club-location">
                                <i class="fas fa-map-marker-alt"></i>
                                ${club.location}
                            </div>
                        </div>
                        <div class="club-body">
                            <div class="club-info">
                                <div class="info-row">
                                    <span class="info-label">Country</span>
                                    <span class="info-value">${club.country}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">League</span>
                                    <span class="info-value">${club.league}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Established</span>
                                    <span class="info-value">${club.established}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Stadium</span>
                                    <span class="info-value">${club.stadium}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Capacity</span>
                                    <span class="info-value">${club.capacity}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Head Coach</span>
                                    <span class="info-value">${club.coach}</span>
                                </div>
                            </div>
                            
                            <div class="club-stats">
                                <div class="stat-item">
                                    <div class="stat-value">${club.players}</div>
                                    <div class="stat-label">Players</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${club.trophies}</div>
                                    <div class="stat-label">Trophies</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${club.league === 'Professional' ? 'Pro' : club.league.charAt(0)}</div>
                                    <div class="stat-label">Level</div>
                                </div>
                            </div>
                            
                            <div style="margin: 20px 0;">
                                <p style="color: var(--grey-95); font-size: 14px; line-height: 1.5;">${club.description}</p>
                                <div style="margin-top: 10px;">
                                    <strong style="font-size: 12px; color: var(--grey-90); display: block; margin-bottom: 5px;">FACILITIES:</strong>
                                    <div>${facilitiesHtml}</div>
                                </div>
                            </div>
                            
                            <div class="club-actions">
                                <button class="btn btn-primary btn-small" onclick="viewClubDetails(${club.id})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-secondary btn-small" onclick="contactClub(${club.id})">
                                    <i class="fas fa-envelope"></i> Contact
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Function to filter and display clubs
            function displayClubs(filteredClubs, containerId) {
                const container = document.getElementById(containerId);
                if (filteredClobs.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Clubs Found</h3>
                            <p>Try adjusting your search filters or browse all clubs.</p>
                            <button class="btn btn-secondary" onclick="resetFilters()">
                                Reset Filters
                            </button>
                        </div>
                    `;
                } else {
                    container.innerHTML = filteredClubs.map(club => renderClubCard(club)).join('');
                }
            }
            
            // Function to filter clubs based on criteria
            function filterClubs() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedCountry = countrySelect.value;
                const selectedLeague = leagueSelect.value;
                const selectedFacility = facilitiesSelect.value;
                const sortOption = sortSelect.value;
                
                let filtered = clubsData.filter(club => {
                    // Search filter
                    const matchesSearch = !searchTerm || 
                        club.name.toLowerCase().includes(searchTerm) ||
                        club.location.toLowerCase().includes(searchTerm) ||
                        club.description.toLowerCase().includes(searchTerm);
                    
                    // Country filter
                    const matchesCountry = !selectedCountry || club.country === selectedCountry;
                    
                    // League filter
                    const matchesLeague = !selectedLeague || club.league === selectedLeague;
                    
                    // Facilities filter
                    const matchesFacility = !selectedFacility || club.facilities.includes(selectedFacility);
                    
                    return matchesSearch && matchesCountry && matchesLeague && matchesFacility;
                });
                
                // Sort clubs
                filtered.sort((a, b) => {
                    switch (sortOption) {
                        case 'name_asc':
                            return a.name.localeCompare(b.name);
                        case 'name_desc':
                            return b.name.localeCompare(a.name);
                        case 'established_asc':
                            return a.established - b.established;
                        case 'established_desc':
                            return b.established - a.established;
                        case 'players_asc':
                            return a.players - b.players;
                        case 'players_desc':
                            return b.players - a.players;
                        default:
                            return 0;
                    }
                });
                
                return filtered;
            }
            
            // Function to update displayed clubs
            function updateDisplayedClubs() {
                const filteredClubs = filterClubs();
                const featuredClubsList = filteredClubs.filter(club => club.featured);
                const allClubsList = filteredClubs.filter(club => !club.featured);
                
                displayClubs(featuredClubsList, 'featuredClubs');
                displayClubs(allClubsList, 'allClubs');
                
                // Update pagination
                updatePagination(allClubsList.length);
            }
            
            // Function to update pagination
            function updatePagination(totalClubs) {
                const totalPages = Math.ceil(totalClubs / 6); // 6 clubs per page
                const currentPage = 1;
                
                // In a real app, you would implement proper pagination logic
                // This is a simplified version
                if (totalClubs === 0) {
                    prevPageBtn.classList.add('disabled');
                    nextPageBtn.classList.add('disabled');
                } else {
                    prevPageBtn.classList.add('disabled');
                    if (totalPages > 1) {
                        nextPageBtn.classList.remove('disabled');
                    } else {
                        nextPageBtn.classList.add('disabled');
                    }
                }
            }
            
            // Apply filters
            applyFiltersBtn.addEventListener('click', updateDisplayedClubs);
            
            // Reset filters
            resetFiltersBtn.addEventListener('click', function() {
                searchInput.value = '';
                countrySelect.value = '';
                leagueSelect.value = '';
                facilitiesSelect.value = '';
                sortSelect.value = 'name_asc';
                updateDisplayedClubs();
            });
            
            // Sort change
            sortSelect.addEventListener('change', updateDisplayedClubs);
            
            // Search on Enter key
            searchInput.addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    updateDisplayedClubs();
                }
            });
            
            // Pagination handlers
            nextPageBtn.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    alert('In a real application, this would load the next page of clubs from the database.');
                }
            });
            
            prevPageBtn.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    alert('In a real application, this would load the previous page of clubs from the database.');
                }
            });
            
            // Initial display
            updateDisplayedClubs();
        });
        
        // Global functions for button actions
        function viewClubDetails(clubId) {
            alert(`Viewing details for club ID: ${clubId}\n\nIn a real application, this would redirect to a club profile page with detailed information, player roster, achievements, and contact information.`);
            // Example: window.location.href = `club-details.php?id=${clubId}`;
        }
        
        function contactClub(clubId) {
            alert(`Contacting club ID: ${clubId}\n\nIn a real application, this would open a contact form or show the club's contact information for players, agents, or other clubs to get in touch.`);
            // Example: window.location.href = `contact-club.php?id=${clubId}`;
        }
        
        function resetFilters() {
            document.getElementById('search').value = '';
            document.getElementById('country').value = '';
            document.getElementById('league').value = '';
            document.getElementById('facilities').value = '';
            document.getElementById('sortBy').value = 'name_asc';
            document.querySelector('#applyFilters').click();
        }
    </script>
</body>

</html>