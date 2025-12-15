<?php
session_start();

// Check if already logged in as admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit();
}

// Include database connection
require_once 'config/database.php';

// Admin authentication function
function authenticateAdmin($username, $password) {
    $conn = getDatabaseConnection();
    if (!$conn) return ['success' => false, 'message' => 'Database connection failed'];
    
    // Check if admin_users table exists
    try {
        $check = $conn->query("SHOW TABLES LIKE 'admin_users'");
        if ($check->rowCount() === 0) {
            return ['success' => false, 'message' => 'Admin system not set up. Please run setup first.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    
    $sql = "SELECT id, username, email, password_hash, role, full_name, permissions, is_active 
            FROM admin_users 
            WHERE username = ? OR email = ?";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $username]);
        
        if ($stmt->rowCount() === 1) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if account is active
            if (!$admin['is_active']) {
                return ['success' => false, 'message' => 'Account is disabled. Contact system administrator.'];
            }
            
            if (password_verify($password, $admin['password_hash'])) {
                // Update last login
                $update = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $update->execute([$admin['id']]);
                
                // Log activity
                try {
                    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent) 
                                           VALUES (?, 'login', ?, ?)");
                    $log->execute([
                        $admin['id'],
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                } catch (Exception $e) {
                    // Silently continue if logging fails
                }
                
                unset($admin['password_hash']);
                return ['success' => true, 'admin' => $admin];
            }
        }
        return ['success' => false, 'message' => 'Invalid username or password'];
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Process login
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = authenticateAdmin($username, $password);
        
        if ($result['success']) {
            $_SESSION['admin_id'] = $result['admin']['id'];
            $_SESSION['admin_username'] = $result['admin']['username'];
            $_SESSION['admin_role'] = $result['admin']['role'];
            $_SESSION['admin_name'] = $result['admin']['full_name'];
            $_SESSION['admin_permissions'] = json_decode($result['admin']['permissions'], true);
            $_SESSION['admin_logged_in'] = true;
            
            // Set remember me cookie
            if ($remember) {
                setcookie('admin_remember', $result['admin']['id'], time() + (30 * 24 * 60 * 60), '/');
            }
            
            // Redirect to dashboard
            header('Location: admin-dashboard.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Check for logout success
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = 'You have been successfully logged out.';
}

// Check for session timeout
if (isset($_GET['timeout']) && $_GET['timeout'] == 'true') {
    $error = 'Your session has expired. Please login again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Scout Salone</title>
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
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.3);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background elements */
        .bg-shape-1 {
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            z-index: -1;
        }

        .bg-shape-2 {
            position: absolute;
            bottom: -150px;
            left: -150px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
            z-index: -1;
        }

        .login-container {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, var(--dark) 0%, #1f2937 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.05)"/></svg>');
            background-size: cover;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .logo-text h1 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
            text-align: left;
        }

        .logo-text p {
            font-size: 14px;
            opacity: 0.8;
            text-align: left;
        }

        .login-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin-top: 15px;
            opacity: 0.9;
        }

        .login-form {
            padding: 50px 40px;
        }

        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .message i {
            font-size: 18px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label span {
            color: var(--danger);
        }

        .input-group {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--light);
            color: var(--dark);
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: var(--primary);
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
            padding: 5px;
        }

        .password-toggle:hover {
            color: var(--dark);
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            margin-top: 10px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-input {
            display: none;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-radius: 6px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            background: white;
        }

        .checkbox-custom::after {
            content: '✓';
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            color: white;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .checkbox-input:checked + .checkbox-custom {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-input:checked + .checkbox-custom::after {
            opacity: 1;
        }

        .checkbox-label {
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            font-family: 'Inter', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            margin-right: 10px;
        }

        /* Demo Credentials */
        .demo-credentials {
            background: #f8fafc;
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
        }

        .demo-credentials h4 {
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .demo-credentials h4 i {
            color: var(--primary);
        }

        .demo-info {
            color: var(--gray);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .demo-info span {
            color: var(--dark);
            font-weight: 600;
            background: #e0e7ff;
            padding: 2px 6px;
            border-radius: 4px;
            margin: 0 2px;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 30px 40px;
            border-top: 1px solid var(--border);
            color: var(--gray);
            font-size: 14px;
            background: #f9fafb;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }

        .footer-links a {
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #10b981;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        /* Setup Notice */
        .setup-notice {
            background: #fffbeb;
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            text-align: center;
        }

        .setup-notice h4 {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .setup-notice p {
            color: #92400e;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .setup-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .setup-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-header {
                padding: 40px 30px;
            }
            
            .login-form {
                padding: 40px 30px;
            }
            
            .login-footer {
                padding: 25px 30px;
            }
            
            .logo {
                flex-direction: column;
                text-align: center;
            }
            
            .logo-text h1,
            .logo-text p {
                text-align: center;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Background Shapes -->
    <div class="bg-shape-1"></div>
    <div class="bg-shape-2"></div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">
                    <h1>Scout Salone</h1>
                    <p>Football Talent Management</p>
                </div>
            </div>
            <h2>Administration Panel</h2>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username or Email <span>*</span></label>
                    <div class="input-group">
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Enter admin username or email" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password <span>*</span></label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-label">Remember me</span>
                    </label>
                    <a href="admin-forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Access Dashboard
                </button>
            </form>
            
            <!-- Demo Credentials -->
            <div class="demo-credentials">
                <h4><i class="fas fa-info-circle"></i> Demo Credentials</h4>
                <p class="demo-info">
                    <strong>Username:</strong> <span>superadmin</span><br>
                    <strong>Password:</strong> <span>admin123</span><br>
                    <small>Use these credentials for testing</small>
                </p>
                <button type="button" class="demo-btn" id="fillDemo" style="background: #e0e7ff; color: #4f46e5; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                    <i class="fas fa-bolt"></i> Fill Demo Credentials
                </button>
            </div>
            
            <!-- Setup Notice (only shows if tables don't exist) -->
            <?php
            try {
                $conn = getDatabaseConnection();
                if ($conn) {
                    $check = $conn->query("SHOW TABLES LIKE 'admin_users'");
                    if ($check->rowCount() === 0) {
                        echo '<div class="setup-notice">
                            <h4><i class="fas fa-tools"></i> Setup Required</h4>
                            <p>The admin system needs to be set up before you can login.</p>
                            <a href="setup-admin-tables.php" class="setup-btn">
                                <i class="fas fa-cogs"></i> Run Setup Now
                            </a>
                        </div>';
                    }
                }
            } catch (Exception $e) {
                // Silently continue
            }
            ?>
        </div>
        
        <div class="login-footer">
            <p>&copy; 2024 Scout Salone Football Agency. Secure Access Only.</p>
            <div class="footer-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> User Login</a>
                <a href="contact.php"><i class="fas fa-envelope"></i> Contact Support</a>
                <a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a>
            </div>
            <div class="security-badge">
                <i class="fas fa-lock"></i> Secure Connection
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const submitBtn = document.getElementById('submitBtn');
            const fillDemoBtn = document.getElementById('fillDemo');
            
            // Password toggle
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
            
            // Fill demo credentials
            fillDemoBtn.addEventListener('click', function() {
                document.getElementById('username').value = 'superadmin';
                document.getElementById('password').value = 'admin123';
                document.getElementById('remember').checked = true;
                
                // Show feedback
                const feedback = document.createElement('div');
                feedback.className = 'message message-success';
                feedback.innerHTML = '<i class="fas fa-check-circle"></i> Demo credentials loaded successfully!';
                feedback.style.marginTop = '15px';
                this.parentNode.appendChild(feedback);
                
                setTimeout(() => feedback.remove(), 3000);
            });
            
            // Form submission with loading state
            form.addEventListener('submit', function() {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<div class="loading"></div> Authenticating...';
                submitBtn.disabled = true;
                
                // Re-enable after 5 seconds in case of error
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });
            
            // Auto-focus username field
            document.getElementById('username').focus();
            
            // Check for saved credentials
            const savedUsername = localStorage.getItem('admin_username');
            const savedRemember = localStorage.getItem('admin_remember');
            
            if (savedRemember === 'true' && savedUsername) {
                document.getElementById('username').value = savedUsername;
                document.getElementById('remember').checked = true;
            }
            
            // Save credentials if remember me is checked
            form.addEventListener('submit', function() {
                const rememberCheckbox = document.getElementById('remember');
                if (rememberCheckbox.checked) {
                    localStorage.setItem('admin_username', document.getElementById('username').value);
                    localStorage.setItem('admin_remember', 'true');
                } else {
                    localStorage.removeItem('admin_username');
                    localStorage.setItem('admin_remember', 'false');
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+Enter to submit
                if (e.ctrlKey && e.key === 'Enter') {
                    form.submit();
                }
                
                // Escape to clear form
                if (e.key === 'Escape') {
                    form.reset();
                }
                
                // F1 for demo credentials
                if (e.key === 'F1') {
                    e.preventDefault();
                    fillDemoBtn.click();
                }
            });
            
            // Show keyboard shortcuts hint
            console.log('Keyboard shortcuts:\nCtrl+Enter: Submit form\nEscape: Clear form\nF1: Fill demo credentials');
        });
    </script>
</body>
</html>