<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Include database and user functions
require_once 'config/database.php';
require_once 'functions/users.php';

// Initialize variables
$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $userData = [
        'username' => $_POST['email'] ?? '', // Using email as username
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'role' => $_POST['role'] ?? 'player',
        'full_name' => trim(($_POST['firstName'] ?? '') . ' ' . ($_POST['lastName'] ?? '')),
        'phone' => $_POST['phone'] ?? '',
        'country' => $_POST['country'] ?? 'Sierra Leone',
        'date_of_birth' => !empty($_POST['dob']) ? $_POST['dob'] : null,
        'position' => $_POST['position'] ?? '',
        'current_club' => $_POST['currentClub'] ?? ''
    ];
    
    // Convert age to date of birth if provided
    if (!empty($_POST['age']) && is_numeric($_POST['age'])) {
        $age = (int)$_POST['age'];
        $currentYear = date('Y');
        $birthYear = $currentYear - $age;
        $userData['date_of_birth'] = $birthYear . '-01-01'; // Default to Jan 1 of birth year
    }
    
    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'role', 'full_name', 'phone', 'country'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($userData[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($_POST['password'] !== $_POST['confirmPassword']) {
        $error_message = "Passwords do not match.";
    } elseif (!isset($_POST['terms'])) {
        $error_message = "You must accept the terms and conditions.";
    } else {
        // Create the user
        $result = createUser($userData);
        
        if ($result['success']) {
            $success_message = "Registration successful! You can now login.";
            // Redirect to login after 3 seconds
            header('refresh:3;url=login.php?registered=success');
        } else {
            $error_message = $result['message'];
        }
    }
}

// Preserve form data on error
$form_data = [
    'firstName' => $_POST['firstName'] ?? '',
    'lastName' => $_POST['lastName'] ?? '',
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'role' => $_POST['role'] ?? 'player',
    'position' => $_POST['position'] ?? '',
    'age' => $_POST['age'] ?? '',
    'country' => $_POST['country'] ?? 'sierra-leone',
    'terms' => isset($_POST['terms']) ? 'checked' : ''
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Scout Salone Football Agency</title>
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
            min-height: 70px;
        }

        .logo {
            color: var(--white);
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex: 1;
            margin: 0 15px;
        }

        .navbar a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.5px;
            position: relative;
            padding: 8px 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
            border-radius: 4px;
        }

        .navbar a:hover {
            color: var(--accent);
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar a.active {
            color: var(--accent);
            font-weight: 700;
            background: rgba(255, 255, 255, 0.15);
        }

        .navbar a.active::after,
        .navbar a:hover::after {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 12px;
            right: 12px;
            height: 2px;
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
            padding: 8px;
            z-index: 1001;
        }

        /* Alert Messages */
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

        /* Registration Container */
        .registration-container {
            display: flex;
            min-height: calc(100vh - 140px);
            width: 100%;
        }

        .registration-left {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.9)), 
                        url('https://images.pexels.com/photos/46798/the-ball-stadion-football-the-pitch-46798.jpeg') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
        }

        .registration-left::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at left, transparent 30%, rgba(0, 0, 0, 0.9) 70%);
        }

        .registration-left-content {
            position: relative;
            z-index: 10;
            max-width: 500px;
        }

        .registration-left h1 {
            font-size: 48px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--white);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .registration-left-divider {
            width: 80px;
            height: 3px;
            background: var(--white);
            margin-bottom: 25px;
        }

        .registration-left p {
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

        /* Registration Form */
        .registration-form-container {
            flex: 1;
            background: var(--grey-20);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
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

        .registration-form {
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
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

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--grey-30);
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

        .select-wrapper {
            position: relative;
        }

        .select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 40px;
            cursor: pointer;
        }

        .select-wrapper::after {
            content: "\f078";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--grey-70);
            pointer-events: none;
        }

        /* Radio Button Group */
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }

        .radio-option {
            display: flex;
            align-items: center;
        }

        .radio-input {
            display: none;
        }

        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: var(--grey-90);
            font-weight: 500;
        }

        .radio-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--grey-60);
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .radio-custom::after {
            content: "";
            width: 10px;
            height: 10px;
            background: var(--white);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .radio-input:checked + .radio-label .radio-custom {
            border-color: var(--white);
        }

        .radio-input:checked + .radio-label .radio-custom::after {
            opacity: 1;
        }

        .radio-input:checked + .radio-label {
            color: var(--white);
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin-top: 20px;
        }

        .checkbox-input {
            display: none;
        }

        .checkbox-label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            color: var(--grey-90);
            font-weight: 500;
            line-height: 1.5;
        }

        .checkbox-custom {
            min-width: 20px;
            height: 20px;
            border: 2px solid var(--grey-60);
            border-radius: 4px;
            margin-right: 12px;
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

        .terms-link {
            color: var(--white);
            text-decoration: underline;
            transition: color 0.3s ease;
        }

        .terms-link:hover {
            color: var(--grey-95);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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

        .login-link {
            text-align: center;
            margin-top: 30px;
            color: var(--grey-80);
        }

        .login-link a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--grey-95);
            text-decoration: underline;
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

        /* Responsive Design */
        @media (max-width: 1200px) {
            .nav-links {
                gap: 8px;
            }
            
            .navbar a {
                font-size: 14px;
                padding: 6px 10px;
            }
            
            .logo {
                font-size: 22px;
            }
        }

        @media (max-width: 1024px) {
            .registration-container {
                flex-direction: column;
            }
            
            .registration-left, .registration-form-container {
                padding: 40px;
            }
            
            .nav-links {
                gap: 6px;
            }
            
            .navbar a {
                font-size: 13px;
                padding: 6px 8px;
            }
        }

        @media (max-width: 900px) {
            .navbar {
                flex-direction: column;
                padding: 15px 20px;
                gap: 15px;
                min-height: auto;
            }
            
            .logo {
                font-size: 20px;
                letter-spacing: 1px;
                text-align: center;
                width: 100%;
                position: static;
                margin-bottom: 10px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
                margin: 10px 0;
                gap: 8px;
            }
            
            .navbar a {
                margin: 0;
                font-size: 14px;
                padding: 8px 12px;
            }
        }

        @media (max-width: 768px) {
            .registration-left h1 {
                font-size: 36px;
            }
            
            .registration-left p {
                font-size: 16px;
            }
            
            .form-header h2 {
                font-size: 28px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            
            .navbar a {
                font-size: 13px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 12px 15px;
                flex-direction: row;
                justify-content: space-between;
            }
            
            .logo {
                font-size: 18px;
                width: auto;
                margin-bottom: 0;
                text-align: left;
            }
            
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--black);
                flex-direction: column;
                padding: 20px;
                margin: 0;
                gap: 10px;
                border-top: 1px solid var(--grey-30);
                box-shadow: 0 10px 20px rgba(0,0,0,0.3);
                z-index: 1000;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .navbar a {
                width: 100%;
                text-align: center;
                padding: 12px;
                font-size: 15px;
                border-radius: 6px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .registration-left, .registration-form-container {
                padding: 30px 20px;
            }
            
            .registration-left h1 {
                font-size: 28px;
            }
            
            .form-header h2 {
                font-size: 24px;
            }
            
            .form-input {
                padding: 12px 14px;
            }
        }

        @media (max-width: 360px) {
            .logo {
                font-size: 16px;
            }
            
            .navbar a {
                font-size: 14px;
                padding: 10px;
            }
            
            .registration-left h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="logo">SCOUT SALONE</div>
        <a href="home.html">Home</a>
        <a href="about.html">About</a>
        <a href="players.html">Players</a>
        <a href="matches.html">Matches</a>
        <a href="contact.html">Contact Us</a>
        <a href="register.php" class="active">Register</a>
        <a href="login.php">Login</a>
    </nav>

    <!-- Registration Container -->
    <div class="registration-container">
        <!-- Left Side: Information -->
        <div class="registration-left">
            <div class="registration-left-content">
                <h1>Join Scout Salone Football Agency</h1>
                <div class="registration-left-divider"></div>
                <p>Register with Sierra Leone's premier football talent agency. Whether you're a player, scout, or club representative, become part of our network dedicated to developing football excellence.</p>
                
                <ul class="features-list">
                    <li><i class="fas fa-check"></i> Professional player representation</li>
                    <li><i class="fas fa-check"></i> Access to international clubs</li>
                    <li><i class="fas fa-check"></i> Professional development programs</li>
                    <li><i class="fas fa-check"></i> Scouting network across Africa</li>
                    <li><i class="fas fa-check"></i> Contract negotiation support</li>
                </ul>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="registration-form-container">
            <div class="form-header">
                <h2>Create Account</h2>
                <p>Fill in your details to register with our agency</p>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($error_message)): ?>
            <div class="alert-message alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <p>Redirecting to login page...</p>
            </div>
            <?php endif; ?>

            <form class="registration-form" id="registrationForm" method="POST" action="">
                <!-- Personal Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName" class="form-label required">First Name</label>
                        <input type="text" id="firstName" name="firstName" class="form-input" 
                               placeholder="Enter your first name" 
                               value="<?php echo htmlspecialchars($form_data['firstName']); ?>" required>
                        <div class="error-message" id="firstNameError">Please enter your first name</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName" class="form-label required">Last Name</label>
                        <input type="text" id="lastName" name="lastName" class="form-input" 
                               placeholder="Enter your last name" 
                               value="<?php echo htmlspecialchars($form_data['lastName']); ?>" required>
                        <div class="error-message" id="lastNameError">Please enter your last name</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label required">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="Enter your email address" 
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                    <div class="error-message" id="emailError">Please enter a valid email address</div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" 
                           placeholder="Enter your phone number" 
                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
                    <div class="error-message" id="phoneError">Please enter a valid phone number</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label required">Password</label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Create a password" required>
                    <div class="error-message" id="passwordError">Password must be at least 8 characters</div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label required">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" 
                           placeholder="Confirm your password" required>
                    <div class="error-message" id="confirmPasswordError">Passwords do not match</div>
                </div>

                <!-- Role Selection -->
                <div class="form-group">
                    <label class="form-label required">I am registering as a:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="player" name="role" class="radio-input" value="player" 
                                   <?php echo ($form_data['role'] == 'player') ? 'checked' : ''; ?>>
                            <label for="player" class="radio-label">
                                <span class="radio-custom"></span>
                                Player
                            </label>
                        </div>
                        
                        <div class="radio-option">
                            <input type="radio" id="scout" name="role" class="radio-input" value="scout"
                                   <?php echo ($form_data['role'] == 'scout') ? 'checked' : ''; ?>>
                            <label for="scout" class="radio-label">
                                <span class="radio-custom"></span>
                                Scout
                            </label>
                        </div>
                        
                        <div class="radio-option">
                            <input type="radio" id="club" name="role" class="radio-input" value="club"
                                   <?php echo ($form_data['role'] == 'club') ? 'checked' : ''; ?>>
                            <label for="club" class="radio-label">
                                <span class="radio-custom"></span>
                                Club Representative
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Additional Player Information (Conditional) -->
                <div class="form-group player-fields" id="playerFields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="position" class="form-label">Position</label>
                            <div class="select-wrapper">
                                <select id="position" name="position" class="form-input">
                                    <option value="">Select your position</option>
                                    <option value="Goalkeeper" <?php echo ($form_data['position'] == 'Goalkeeper') ? 'selected' : ''; ?>>Goalkeeper</option>
                                    <option value="Defender" <?php echo ($form_data['position'] == 'Defender') ? 'selected' : ''; ?>>Defender</option>
                                    <option value="Midfielder" <?php echo ($form_data['position'] == 'Midfielder') ? 'selected' : ''; ?>>Midfielder</option>
                                    <option value="Forward" <?php echo ($form_data['position'] == 'Forward') ? 'selected' : ''; ?>>Forward</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" id="age" name="age" class="form-input" 
                                   placeholder="Your age" min="16" max="40"
                                   value="<?php echo htmlspecialchars($form_data['age']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Country -->
                <div class="form-group">
                    <label for="country" class="form-label required">Country</label>
                    <div class="select-wrapper">
                        <select id="country" name="country" class="form-input" required>
                            <option value="">Select your country</option>
                            <option value="Sierra Leone" <?php echo ($form_data['country'] == 'Sierra Leone' || $form_data['country'] == 'sierra-leone') ? 'selected' : ''; ?>>Sierra Leone</option>
                            <option value="Ghana" <?php echo ($form_data['country'] == 'Ghana') ? 'selected' : ''; ?>>Ghana</option>
                            <option value="Nigeria" <?php echo ($form_data['country'] == 'Nigeria') ? 'selected' : ''; ?>>Nigeria</option>
                            <option value="Senegal" <?php echo ($form_data['country'] == 'Senegal') ? 'selected' : ''; ?>>Senegal</option>
                            <option value="Ivory Coast" <?php echo ($form_data['country'] == 'Ivory Coast') ? 'selected' : ''; ?>>Ivory Coast</option>
                            <option value="Other" <?php echo ($form_data['country'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="error-message" id="countryError">Please select your country</div>
                </div>

                <!-- Current Club (for players) -->
                <div class="form-group" id="currentClubField" style="display: none;">
                    <label for="currentClub" class="form-label">Current Club</label>
                    <input type="text" id="currentClub" name="currentClub" class="form-input" 
                           placeholder="Your current club (if any)">
                </div>

                <!-- Terms and Conditions -->
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" class="checkbox-input" required
                           <?php echo $form_data['terms']; ?>>
                    <label for="terms" class="checkbox-label">
                        <span class="checkbox-custom"></span>
                        I agree to the <a href="#" class="terms-link">Terms and Conditions</a> and <a href="#" class="terms-link">Privacy Policy</a> of Scout Salone Football Agency
                    </label>
                </div>
                <div class="error-message" id="termsError">You must accept the terms and conditions</div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                    <button type="button" class="btn btn-secondary" id="resetBtn">Reset Form</button>
                </div>

                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <p class="fText">© 2025 SCOUT SALONE FOOTBALL AGENCY. ALL RIGHTS RESERVED.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const resetBtn = document.getElementById('resetBtn');
            const roleInputs = document.querySelectorAll('input[name="role"]');
            const playerFields = document.getElementById('playerFields');
            const currentClubField = document.getElementById('currentClubField');
            
            // Toggle player-specific fields based on role selection
            function toggleRoleFields() {
                const selectedRole = document.querySelector('input[name="role"]:checked').value;
                if (selectedRole === 'player') {
                    playerFields.style.display = 'block';
                    currentClubField.style.display = 'block';
                } else {
                    playerFields.style.display = 'none';
                    currentClubField.style.display = 'none';
                }
            }
            
            // Initial toggle
            toggleRoleFields();
            
            // Add event listeners to role radio buttons
            roleInputs.forEach(input => {
                input.addEventListener('change', toggleRoleFields);
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous error states
                const errorMessages = document.querySelectorAll('.error-message');
                const inputs = document.querySelectorAll('.form-input');
                
                errorMessages.forEach(msg => {
                    msg.style.display = 'none';
                });
                
                inputs.forEach(input => {
                    input.classList.remove('error', 'success');
                });
                
                // Validate required fields
                const requiredFields = [
                    {id: 'firstName', name: 'First Name'},
                    {id: 'lastName', name: 'Last Name'},
                    {id: 'email', name: 'Email'},
                    {id: 'phone', name: 'Phone Number'},
                    {id: 'password', name: 'Password'},
                    {id: 'confirmPassword', name: 'Confirm Password'}
                ];
                
                requiredFields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const errorElement = document.getElementById(field.id + 'Error');
                    
                    if (!input.value.trim()) {
                        input.classList.add('error');
                        errorElement.textContent = `${field.name} is required`;
                        errorElement.style.display = 'block';
                        isValid = false;
                    } else {
                        input.classList.add('success');
                    }
                });
                
                // Validate email format
                const email = document.getElementById('email');
                const emailError = document.getElementById('emailError');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email.value && !emailRegex.test(email.value)) {
                    email.classList.add('error');
                    emailError.textContent = 'Please enter a valid email address';
                    emailError.style.display = 'block';
                    isValid = false;
                }
                
                // Validate password strength
                const password = document.getElementById('password');
                const passwordError = document.getElementById('passwordError');
                
                if (password.value && password.value.length < 6) {
                    password.classList.add('error');
                    passwordError.textContent = 'Password must be at least 6 characters';
                    passwordError.style.display = 'block';
                    isValid = false;
                }
                
                // Validate password match
                const confirmPassword = document.getElementById('confirmPassword');
                const confirmPasswordError = document.getElementById('confirmPasswordError');
                
                if (password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('error');
                    confirmPasswordError.textContent = 'Passwords do not match';
                    confirmPasswordError.style.display = 'block';
                    isValid = false;
                }
                
                // Validate country selection
                const country = document.getElementById('country');
                const countryError = document.getElementById('countryError');
                
                if (!country.value) {
                    country.classList.add('error');
                    countryError.style.display = 'block';
                    isValid = false;
                }
                
                // Validate terms acceptance
                const terms = document.getElementById('terms');
                const termsError = document.getElementById('termsError');
                
                if (!terms.checked) {
                    termsError.style.display = 'block';
                    isValid = false;
                }
                
                // If validation fails, prevent form submission
                if (!isValid) {
                    e.preventDefault();
                } else {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                    submitBtn.disabled = true;
                    
                    // Allow form submission to proceed to PHP backend
                }
            });
            
            // Reset form functionality
            resetBtn.addEventListener('click', function() {
                form.reset();
                toggleRoleFields();
                
                // Clear all error states
                const errorMessages = document.querySelectorAll('.error-message');
                const inputs = document.querySelectorAll('.form-input');
                
                errorMessages.forEach(msg => {
                    msg.style.display = 'none';
                });
                
                inputs.forEach(input => {
                    input.classList.remove('error', 'success');
                });
            });
            
            // Real-time validation for password match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirmPassword');
            
            function validatePasswordMatch() {
                const confirmPasswordError = document.getElementById('confirmPasswordError');
                
                if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('error');
                    confirmPasswordError.style.display = 'block';
                } else if (confirmPassword.value) {
                    confirmPassword.classList.remove('error');
                    confirmPassword.classList.add('success');
                    confirmPasswordError.style.display = 'none';
                }
            }
            
            password.addEventListener('input', validatePasswordMatch);
            confirmPassword.addEventListener('input', validatePasswordMatch);
        });
    </script>
</body>

</html>