<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'player':
            header('Location: player-dashboard.php');
            exit();
        case 'scout':
            header('Location: scout-dashboard.php');
            exit();
        case 'club':
            header('Location: club-dashboard.php');
            exit();
        default:
            header('Location: dashboard.php');
            exit();
    }
}

// Include database and user functions
require_once 'config/database.php';
require_once 'functions/users.php';
require_once 'functions/session.php';

// Initialize variables
$error_message = '';
$success_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error_message = "Username/email and password are required.";
    } else {
        try {
            // Authenticate user using the function
            $result = authenticateUser($username, $password);
            
            if ($result['success']) {
                // Login user and start session
                loginUser($result['user']);
                
                // Set cookie for "Remember me" if checked
                if ($remember) {
                    setcookie('remember_user', $result['user']['id'], time() + (30 * 24 * 60 * 60), '/'); // 30 days
                }
                
                // Redirect based on role
                switch ($result['user']['role']) {
                    case 'player':
                        header('Location: player-dashboard.php');
                        exit();
                    case 'scout':
                        header('Location: admin-dashboard.php');
                        exit();
                    case 'club':
                        header('Location: admin-dashboard.php');
                        exit();
                    default:
                        header('Location: dashboard.php');
                        exit();
                }
            } else {
                $error_message = $result['message'];
            }
        } catch (Exception $e) {
            $error_message = "System error. Please try again later.";
            // Log the error for debugging
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for success message from registration
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success_message = "Registration successful! Please login with your credentials.";
}

// Get username from POST for repopulating
$username_value = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Scout Salone Football Agency</title>
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

        /* Navbar Styling - FIXED */
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

        /* Login Container */
        .login-container {
            display: flex;
            min-height: calc(100vh - 140px);
            width: 100%;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.9)), 
                        url('https://images.pexels.com/photos/1168946/pexels-photo-1168946.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
        }

        .login-left::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at left, transparent 30%, rgba(0, 0, 0, 0.9) 70%);
        }

        .login-left-content {
            position: relative;
            z-index: 10;
            max-width: 500px;
        }

        .login-left h1 {
            font-size: 48px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--white);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .login-divider {
            width: 80px;
            height: 3px;
            background: var(--white);
            margin-bottom: 25px;
        }

        .login-left p {
            font-size: 18px;
            color: var(--grey-95);
            margin-bottom: 30px;
            font-weight: 300;
            line-height: 1.8;
        }

        .features-list {
            list-style: none;
            margin-top: 30px;
        }

        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: var(--grey-95);
        }

        .features-list i {
            color: var(--white);
            margin-right: 12px;
            font-size: 18px;
        }

        /* Login Form */
        .login-form-container {
            flex: 1;
            background: var(--grey-20);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            max-width: 450px;
            width: 100%;
            background: var(--grey-30);
            border-radius: 8px;
            padding: 50px;
            border: 1px solid var(--grey-40);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-header h2 {
            font-size: 32px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--white);
            margin-bottom: 10px;
        }

        .form-header p {
            font-size: 16px;
            color: var(--grey-80);
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            color: var(--white);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .required::after {
            content: " *";
            color: var(--error);
        }

        .input-with-icon {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            background: var(--grey-20);
            border: 1px solid var(--grey-40);
            border-radius: 4px;
            color: var(--white);
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--white);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
        }

        .form-input::placeholder {
            color: var(--grey-70);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--grey-70);
            font-size: 18px;
        }

        /* Password Visibility Toggle */
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--grey-70);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--white);
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            margin-top: 10px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
        }

        .checkbox-input {
            display: none;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: var(--grey-90);
            font-weight: 500;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--grey-60);
            border-radius: 4px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .checkbox-custom::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: var(--white);
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .checkbox-input:checked + .checkbox-label .checkbox-custom {
            border-color: var(--white);
            background: var(--white);
        }

        .checkbox-input:checked + .checkbox-label .checkbox-custom::after {
            opacity: 1;
            color: var(--black);
        }

        .checkbox-input:checked + .checkbox-label {
            color: var(--white);
        }

        .forgot-password {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--grey-95);
            text-decoration: underline;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            flex: 1;
            text-align: center;
        }

        .btn-primary {
            background: var(--white);
            color: var(--black);
        }

        .btn-primary:hover {
            background: var(--grey-95);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.1);
        }

        .btn-secondary {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--grey-60);
        }

        .btn-secondary:hover {
            border-color: var(--white);
            transform: translateY(-2px);
        }

        /* Social Login */
        .social-login {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid var(--grey-40);
            padding-top: 30px;
        }

        .social-login p {
            color: var(--grey-80);
            margin-bottom: 20px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .social-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 4px;
            background: var(--grey-20);
            color: var(--white);
            border: 1px solid var(--grey-40);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            border-color: var(--white);
            transform: translateY(-2px);
        }

        .facebook-btn:hover {
            background: #3b5998;
            border-color: #3b5998;
        }

        .google-btn:hover {
            background: #db4437;
            border-color: #db4437;
        }

        .twitter-btn:hover {
            background: #1da1f2;
            border-color: #1da1f2;
        }

        /* Registration Link */
        .register-link {
            text-align: center;
            margin-top: 30px;
            color: var(--grey-80);
        }

        .register-link a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--grey-95);
            text-decoration: underline;
        }

        /* Custom Alert Styles */
        .alert-message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--error);
            color: #ff6b6b;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--success);
            color: #2ecc71;
        }

        /* Form Validation Styles */
        .error-message {
            color: var(--error);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .form-input.error {
            border-color: var(--error);
        }

        .form-input.success {
            border-color: var(--success);
        }

        /* Demo Credentials */
        .demo-credentials {
            background: var(--grey-20);
            border: 1px solid var(--grey-40);
            border-radius: 4px;
            padding: 15px;
            margin-top: 25px;
            margin-bottom: 20px;
        }

        .demo-credentials h4 {
            color: var(--white);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-credentials h4 i {
            color: var(--grey-95);
        }

        .demo-info {
            color: var(--grey-90);
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .demo-info strong {
            color: var(--white);
            display: block;
            margin-top: 5px;
        }

        .demo-info span {
            color: var(--white);
            font-weight: 600;
        }

        /* Demo Buttons */
        .demo-buttons {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .demo-btn {
            padding: 6px 12px;
            background: var(--grey-30);
            border: 1px solid var(--grey-50);
            border-radius: 4px;
            color: var(--white);
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Inter', sans-serif;
        }

        .demo-btn:hover {
            background: var(--grey-40);
            border-color: var(--grey-70);
        }

        /* Footer */
        .footer {
            background-color: var(--black);
            text-align: center;
            padding: 30px 20px;
            border-top: 1px solid var(--grey-30);
        }

        .fText {
            color: var(--grey-70);
            font-size: 16px;
            letter-spacing: 1px;
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
        }

        @media (max-width: 1024px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left, .login-form-container {
                padding: 40px;
            }
            
            .nav-links {
                gap: 8px;
            }
            
            .navbar a {
                font-size: 13px;
                padding: 5px 4px;
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
        }

        @media (max-width: 768px) {
            .login-left h1 {
                font-size: 36px;
            }
            
            .login-left p {
                font-size: 16px;
            }
            
            .form-header h2 {
                font-size: 28px;
            }
            
            .login-box {
                padding: 40px 30px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .nav-links {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            
            .navbar a {
                font-size: 13px;
                margin: 0 5px;
                padding: 5px 6px;
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
        }

        @media (max-width: 480px) {
            .login-left, .login-form-container {
                padding: 30px 20px;
            }
            
            .login-left h1 {
                font-size: 28px;
            }
            
            .form-header h2 {
                font-size: 24px;
            }
            
            .login-box {
                padding: 30px 20px;
            }
            
            .form-input {
                padding: 12px 14px 12px 40px;
            }
            
            .social-buttons {
                flex-wrap: wrap;
            }
            
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
        }

        @media (max-width: 360px) {
            .navbar a {
                font-size: 11px;
                padding: 3px 4px;
            }
            
            .logo {
                font-size: 16px;
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
             <a href="home.php" >Home</a>
                <a href="about.html">About</a>
                <a href="contact.html">Contact</a>
                <a href="players.php">Players</a>
                <a href="clubs.html">Clubs</a>
                <a href="matches.html">Matches</a>
                <a href="login.php" class="active">Login</a>
                <a href="register.php">Register</a>
        </div>
    </nav>

    <!-- Login Container -->
    <div class="login-container">
        <!-- Left Side: Information -->
        <div class="login-left">
            <div class="login-left-content">
                <h1>Welcome Back to Scout Salone</h1>
                <div class="login-divider"></div>
                <p>Access your account to manage your profile, connect with talent, and explore opportunities with Sierra Leone's premier football agency.</p>
                
                <ul class="features-list">
                    <li><i class="fas fa-users"></i> Connect with scouts and clubs</li>
                    <li><i class="fas fa-chart-line"></i> Track your performance stats</li>
                    <li><i class="fas fa-calendar-alt"></i> Manage your match schedule</li>
                    <li><i class="fas fa-bullhorn"></i> Receive opportunity alerts</li>
                    <li><i class="fas fa-headset"></i> Access professional support</li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-form-container">
            <div class="login-box">
                <div class="form-header">
                    <h2>Sign In</h2>
                    <p>Enter your credentials to access your account</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($error_message)): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success'): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle"></i> Registration successful! Please login with your credentials.
                </div>
                <?php endif; ?>

                <!-- Demo Credentials -->
                <div class="demo-credentials">
                    <h4><i class="fas fa-info-circle"></i> Demo Credentials</h4>
                    <p class="demo-info">For testing purposes, you can use:<br>
                    <strong>Player Account:</strong><br>
                    <span>Username:</span> demo_player<br>
                    <span>Password:</span> player123<br><br>
                    
                    <strong>Scout Account:</strong><br>
                    <span>Username:</span> demo_scout<br>
                    <span>Password:</span> scout123<br><br>
                    
                    <strong>Club Account:</strong><br>
                    <span>Username:</span> demo_club<br>
                    <span>Password:</span> club123</p>
                    
                    <div class="demo-buttons" id="demoButtonsContainer">
                        <!-- Demo buttons will be added by JavaScript -->
                    </div>
                </div>

                <form class="login-form" id="loginForm" method="POST" action="">
                    <!-- Username/Email -->
                    <div class="form-group">
                        <label for="username" class="form-label required">Username or Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="Enter your username or email" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required>
                            <div class="error-message" id="usernameError"></div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label required">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                            <div class="error-message" id="passwordError"></div>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="form-options">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                            <label for="remember" class="checkbox-label">
                                <span class="checkbox-custom"></span>
                                Remember me
                            </label>
                        </div>
                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                        <button type="button" class="btn btn-secondary" id="resetBtn">Clear</button>
                    </div>

                    <!-- Social Login -->
                    <div class="social-login">
                        <p>Or sign in with</p>
                        <div class="social-buttons">
                            <button type="button" class="social-btn facebook-btn" title="Sign in with Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button type="button" class="social-btn google-btn" title="Sign in with Google">
                                <i class="fab fa-google"></i>
                            </button>
                            <button type="button" class="social-btn twitter-btn" title="Sign in with Twitter">
                                <i class="fab fa-twitter"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Registration Link -->
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Create one now</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p class="fText">© 2025 SCOUT SALONE FOOTBALL AGENCY. ALL RIGHTS RESERVED.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const resetBtn = document.getElementById('resetBtn');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const usernameInput = document.getElementById('username');
            const demoButtonsContainer = document.getElementById('demoButtonsContainer');
            const menuToggle = document.getElementById('menuToggle');
            const navLinks = document.getElementById('navLinks');
            
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
            
            // Demo credentials
            const demoCredentials = [
                { username: 'demo_player', password: 'player123', label: 'Player Account' },
                { username: 'demo_scout', password: 'scout123', label: 'Scout Account' },
                { username: 'demo_club', password: 'club123', label: 'Club Account' }
            ];
            
            // Create demo buttons
            demoCredentials.forEach((cred, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'demo-btn';
                button.textContent = cred.label;
                
                button.addEventListener('click', function() {
                    usernameInput.value = cred.username;
                    passwordInput.value = cred.password;
                    passwordInput.type = 'password';
                    
                    // Reset toggle icon
                    const icon = togglePassword.querySelector('i');
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    
                    // Clear any previous feedback
                    const existingFeedback = this.parentNode.querySelector('.demo-feedback');
                    if (existingFeedback) {
                        existingFeedback.remove();
                    }
                    
                    // Show feedback
                    const feedback = document.createElement('div');
                    feedback.className = 'demo-feedback';
                    feedback.style.marginTop = '5px';
                    feedback.style.fontSize = '11px';
                    feedback.style.color = '#2ecc71';
                    feedback.textContent = `✓ ${cred.label} credentials loaded`;
                    this.parentNode.insertBefore(feedback, this.nextSibling);
                    
                    setTimeout(() => feedback.remove(), 2000);
                });
                
                demoButtonsContainer.appendChild(button);
            });
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle eye icon
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
            
            // Client-side validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous error states
                const errorMessages = document.querySelectorAll('.error-message');
                const inputs = document.querySelectorAll('.form-input');
                
                errorMessages.forEach(msg => {
                    if (msg.id === 'usernameError' || msg.id === 'passwordError') {
                        msg.style.display = 'none';
                    }
                });
                
                inputs.forEach(input => {
                    input.classList.remove('error', 'success');
                });
                
                // Validate username/email
                const username = usernameInput.value.trim();
                const usernameError = document.getElementById('usernameError');
                
                if (!username) {
                    usernameInput.classList.add('error');
                    usernameError.textContent = 'Username or email is required';
                    usernameError.style.display = 'block';
                    isValid = false;
                } else {
                    usernameInput.classList.add('success');
                }
                
                // Validate password
                const password = passwordInput.value.trim();
                const passwordError = document.getElementById('passwordError');
                
                if (!password) {
                    passwordInput.classList.add('error');
                    passwordError.textContent = 'Password is required';
                    passwordError.style.display = 'block';
                    isValid = false;
                } else if (password.length < 6) {
                    passwordInput.classList.add('error');
                    passwordError.textContent = 'Password must be at least 6 characters';
                    passwordError.style.display = 'block';
                    isValid = false;
                } else {
                    passwordInput.classList.add('success');
                }
                
                // If validation fails, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                } else {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
                    submitBtn.disabled = true;
                    
                    // The form will submit normally to PHP backend
                    // Allow form submission to proceed
                }
            });
            
            // Reset form functionality
            resetBtn.addEventListener('click', function() {
                // Clear form values
                usernameInput.value = '';
                passwordInput.value = '';
                
                // Clear all error states
                const errorMessages = document.querySelectorAll('.error-message');
                const inputs = document.querySelectorAll('.form-input');
                
                errorMessages.forEach(msg => {
                    msg.style.display = 'none';
                });
                
                inputs.forEach(input => {
                    input.classList.remove('error', 'success');
                });
                
                // Reset password visibility
                passwordInput.setAttribute('type', 'password');
                const icon = togglePassword.querySelector('i');
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                
                // Uncheck remember me
                const rememberCheckbox = document.getElementById('remember');
                if (rememberCheckbox) {
                    rememberCheckbox.checked = false;
                }
            });
            
            // Social login buttons
            const socialButtons = document.querySelectorAll('.social-btn');
            socialButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const platform = this.classList[1].replace('-btn', '');
                    alert(`In a real application, this would redirect to ${platform} authentication.`);
                });
            });
            
            // Remember me functionality
            const rememberCheckbox = document.getElementById('remember');
            
            // Check if credentials were saved in localStorage (for demo purposes)
            const savedUsername = localStorage.getItem('scoutSaloneUsername');
            const savedPassword = localStorage.getItem('scoutSalonePassword');
            const savedRemember = localStorage.getItem('scoutSaloneRemember');
            
            if (savedRemember === 'true' && savedUsername) {
                usernameInput.value = savedUsername;
                if (savedPassword) {
                    passwordInput.value = savedPassword;
                }
                rememberCheckbox.checked = true;
            }
            
            // Update saved credentials when form is submitted
            form.addEventListener('submit', function() {
                const rememberCheckbox = document.getElementById('remember');
                if (rememberCheckbox && rememberCheckbox.checked) {
                    localStorage.setItem('scoutSaloneUsername', usernameInput.value);
                    // Note: In production, NEVER store passwords in localStorage
                    // This is just for demo convenience
                    localStorage.setItem('scoutSalonePassword', passwordInput.value);
                    localStorage.setItem('scoutSaloneRemember', 'true');
                } else {
                    localStorage.removeItem('scoutSaloneUsername');
                    localStorage.removeItem('scoutSalonePassword');
                    localStorage.setItem('scoutSaloneRemember', 'false');
                }
            });
            
            // Auto-fill from URL parameters (for redirects from registration)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('email')) {
                usernameInput.value = urlParams.get('email');
            }
        });
    </script>
</body>

</html>