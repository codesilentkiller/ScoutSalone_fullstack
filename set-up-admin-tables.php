<?php
echo "<h1>Setup Admin Tables</h1>";
echo "<p>Creating all necessary database tables for the admin dashboard...</p>";

// Database configuration
$host = 'localhost';
$dbname = 'scout_salone';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Connected to database</p>";
    
    // SQL to create admin tables
    $sql = "
    -- Admin users table (separate from regular users)
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'analyst', 'scout_manager', 'recruiter') NOT NULL,
        full_name VARCHAR(100),
        phone VARCHAR(20),
        permissions TEXT,
        last_login DATETIME,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Admin activity logs
    CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT,
        action VARCHAR(255) NOT NULL,
        table_name VARCHAR(100),
        record_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
    );

    -- Scout management
    CREATE TABLE IF NOT EXISTS scouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        country VARCHAR(50),
        region VARCHAR(100),
        specialization ENUM('youth', 'defenders', 'goalkeepers', 'midfielders', 'forwards', 'general') DEFAULT 'general',
        status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
        performance_score DECIMAL(5,2) DEFAULT 0,
        reports_submitted INT DEFAULT 0,
        reports_approved INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );

    -- Scouting reports
    CREATE TABLE IF NOT EXISTS scouting_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scout_id INT,
        player_id INT,
        match_id INT,
        report_code VARCHAR(20) UNIQUE,
        status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected') DEFAULT 'draft',
        
        -- Ratings (1-10)
        technical_ability DECIMAL(3,1),
        tactical_awareness DECIMAL(3,1),
        physical_attributes DECIMAL(3,1),
        mental_strength DECIMAL(3,1),
        overall_potential DECIMAL(3,1),
        
        strengths TEXT,
        weaknesses TEXT,
        recommendation ENUM('sign_immediately', 'high_priority', 'monitor', 'reject'),
        risk_assessment TEXT,
        comparison_players TEXT,
        
        -- Admin fields
        reviewed_by INT,
        review_notes TEXT,
        approved_at DATETIME,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (scout_id) REFERENCES scouts(id),
        FOREIGN KEY (player_id) REFERENCES users(id),
        FOREIGN KEY (reviewed_by) REFERENCES admin_users(id)
    );

    -- Matches & Events
    CREATE TABLE IF NOT EXISTS matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        home_team VARCHAR(100),
        away_team VARCHAR(100),
        competition VARCHAR(100),
        match_date DATE,
        match_time TIME,
        venue VARCHAR(255),
        country VARCHAR(50),
        assigned_scout_id INT,
        status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_scout_id) REFERENCES scouts(id)
    );

    -- Clubs & Partners
    CREATE TABLE IF NOT EXISTS clubs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        club_name VARCHAR(100) UNIQUE NOT NULL,
        country VARCHAR(50),
        league VARCHAR(100),
        contact_person VARCHAR(100),
        contact_email VARCHAR(100),
        contact_phone VARCHAR(20),
        needs_positions TEXT,
        needs_age_range VARCHAR(50),
        status ENUM('active', 'inactive', 'blacklisted') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Transfer Pipeline
    CREATE TABLE IF NOT EXISTS transfer_opportunities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT,
        club_id INT,
        status ENUM('shortlisted', 'trial_requested', 'negotiation', 'offer_made', 'signed', 'rejected', 'failed') DEFAULT 'shortlisted',
        deal_value DECIMAL(12,2),
        currency VARCHAR(3) DEFAULT 'EUR',
        commission_percentage DECIMAL(5,2),
        timeline_start DATE,
        timeline_end DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES users(id),
        FOREIGN KEY (club_id) REFERENCES clubs(id),
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
    );

    -- Communication & Notes
    CREATE TABLE IF NOT EXISTS player_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT,
        admin_id INT,
        note_type ENUM('general', 'performance', 'injury', 'transfer', 'scouting') DEFAULT 'general',
        note TEXT,
        is_private BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES users(id),
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
    );

    -- Analytics Cache
    CREATE TABLE IF NOT EXISTS analytics_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_name VARCHAR(100) UNIQUE,
        metric_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    
    // Execute SQL in parts to avoid errors
    $sqlParts = explode(';', $sql);
    
    foreach ($sqlParts as $part) {
        $part = trim($part);
        if (!empty($part)) {
            try {
                $pdo->exec($part);
                echo "<p style='color: green;'>✓ Executed SQL statement</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Insert default super admin (password: admin123)
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $permissions = json_encode([
        'players' => ['view', 'create', 'edit', 'delete'],
        'scouts' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view', 'create', 'edit', 'delete', 'approve'],
        'clubs' => ['view', 'create', 'edit', 'delete'],
        'transfers' => ['view', 'create', 'edit', 'delete'],
        'analytics' => ['view'],
        'settings' => ['view', 'edit']
    ]);
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (username, email, password_hash, role, full_name, permissions) 
                           VALUES ('superadmin', 'admin@scoutsalone.com', ?, 'super_admin', 'System Administrator', ?)");
    $stmt->execute([$adminPassword, $permissions]);
    
    echo "<p style='color: green;'>✓ Created super admin account</p>";
    
    // Insert sample data
    echo "<h3>Inserting Sample Data...</h3>";
    
    // Sample scouts
    $scouts = [
        ['Ahmed Bangura', 'ahmed@scoutsalone.com', 'Sierra Leone', 'Freetown', 'youth'],
        ['Fatmata Conteh', 'fatmata@scoutsalone.com', 'Sierra Leone', 'Bo', 'general'],
        ['Mohamed Turay', 'mohamed@scoutsalone.com', 'Ghana', 'Accra', 'defenders']
    ];
    
    foreach ($scouts as $scout) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO scouts (full_name, email, country, region, specialization, status, performance_score, reports_submitted) 
                               VALUES (?, ?, ?, ?, ?, 'active', RAND() * 2 + 7, FLOOR(RAND() * 20) + 5)");
        $stmt->execute($scout);
        echo "<p>✓ Added scout: {$scout[0]}</p>";
    }
    
    // Sample clubs
    $clubs = [
        ['East End Lions', 'Sierra Leone', 'Sierra Leone Premier League', 'John Kamara', 'Forward, Midfielder', '18-25'],
        ['Mighty Blackpool', 'Sierra Leone', 'Sierra Leone Premier League', 'David Sesay', 'Goalkeeper, Defender', '20-28'],
        ['Bo Rangers', 'Sierra Leone', 'Sierra Leone Premier League', 'Sarah Koroma', 'All positions', '16-22']
    ];
    
    foreach ($clubs as $club) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO clubs (club_name, country, league, contact_person, needs_positions, needs_age_range) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute($club);
        echo "<p>✓ Added club: {$club[0]}</p>";
    }
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Setup Complete!</h2>";
    echo "<p>Admin tables have been created successfully.</p>";
    
    echo "<h3>Login Credentials:</h3>";
    echo "<div style='background: #f0f4ff; padding: 15px; border-radius: 8px;'>";
    echo "<p><strong>Admin URL:</strong> <a href='admin-login.php'>admin-login.php</a></p>";
    echo "<p><strong>Username:</strong> superadmin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "</div>";
    
    echo "<p style='margin-top: 30px;'>";
    echo "<a href='admin-login.php' style='padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Admin Login</a>";
    echo "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure the 'users' table exists first. Run the regular setup first.</p>";
}
?>