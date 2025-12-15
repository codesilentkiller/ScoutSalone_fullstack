<?php
session_start();

// If logout is confirmed via POST or GET parameter
if (isset($_GET['confirm']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection for logging
    require_once 'config/database.php';

    // Function to log the logout activity
    function logLogoutActivity($userId, $userType, $conn) {
        if ($conn && $userId) {
            try {
                // Determine which log table to use based on user type
                if ($userType === 'admin') {
                    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent) 
                                           VALUES (?, 'logout', ?, ?)");
                    $log->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                } else {
                    // For regular users
                    $log = $conn->prepare("INSERT INTO user_logs (user_id, action_type, ip_address, user_agent) 
                                           VALUES (?, 'logout', ?, ?)");
                    $log->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                }
            } catch (Exception $e) {
                // Silently fail if logging doesn't work
                error_log("Logout logging failed: " . $e->getMessage());
            }
        }
    }

    // Get user info before destroying session
    $userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    $username = $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'User';
    $userType = isset($_SESSION['admin_logged_in']) ? 'admin' : 
               (isset($_SESSION['role']) ? $_SESSION['role'] : 'user');

    // Log the logout activity
    if ($userId) {
        $conn = getDatabaseConnection();
        logLogoutActivity($userId, $userType, $conn);
    }

    // Clear all session variables
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Clear any remember me cookies
    setcookie('remember_user', '', time() - 3600, '/');
    setcookie('remember_admin', '', time() - 3600, '/');
    setcookie('scoutSaloneUsername', '', time() - 3600, '/');
    setcookie('scoutSalonePassword', '', time() - 3600, '/');
    setcookie('scoutSaloneRemember', '', time() - 3600, '/');

    // Show logout success page instead of immediate redirect
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logged Out - Scout Salone</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .logout-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                width: 100%;
                max-width: 500px;
                text-align: center;
                padding: 60px 40px;
            }

            .logout-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                color: white;
                font-size: 36px;
            }

            h1 {
                color: #333;
                font-size: 32px;
                font-weight: 700;
                margin-bottom: 15px;
            }

            p {
                color: #666;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }

            .user-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 30px;
            }

            .user-info strong {
                color: #333;
                font-weight: 600;
            }

            .btn-group {
                display: flex;
                gap: 15px;
                justify-content: center;
            }

            .btn {
                padding: 14px 32px;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
                display: inline-block;
            }

            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }

            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e1e5e9;
            }

            .btn-secondary:hover {
                background: #e9ecef;
                transform: translateY(-2px);
            }

            .auto-redirect {
                margin-top: 30px;
                color: #666;
                font-size: 14px;
            }

            .countdown {
                font-weight: 600;
                color: #667eea;
            }

            .login-links {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            .login-links a {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
                margin: 0 10px;
            }

            .login-links a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="logout-container">
            <div class="logout-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1>Successfully Logged Out</h1>
            
            <p>You have been securely logged out of your account. For security, please close your browser if you're on a shared computer.</p>
            
            <div class="user-info">
                <p>Account: <strong><?php echo htmlspecialchars($username); ?></strong></p>
                <p>User Type: <strong><?php echo ucfirst($userType); ?></strong></p>
                <p>Time: <strong><?php echo date('H:i:s'); ?></strong></p>
            </div>
            
            <div class="btn-group">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login Again
                </a>
                
                <?php if ($userType === 'admin'): ?>
                <a href="admin-login.php" class="btn btn-secondary">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
                <?php endif; ?>
            </div>
            
            <div class="auto-redirect">
                <p>You will be automatically redirected to the login page in <span id="countdown" class="countdown">10</span> seconds...</p>
            </div>
            
            <div class="login-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
            </div>
        </div>

        <script>
            // Countdown timer for auto-redirect
            let seconds = 10;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    // Redirect based on user type
                    <?php if ($userType === 'admin'): ?>
                    window.location.href = 'admin-login.php';
                    <?php else: ?>
                    window.location.href = 'login.php';
                    <?php endif; ?>
                }
            }, 1000);
            
            // Cancel auto-redirect if user interacts
            document.addEventListener('click', () => {
                clearInterval(countdownInterval);
                document.querySelector('.auto-redirect').innerHTML = '<p>Auto-redirect cancelled. Click a button above to navigate.</p>';
            });
        </script>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// If not confirmed, show confirmation page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - Scout Salone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirm-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            text-align: center;
            padding: 50px 40px;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 36px;
        }

        h1 {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .user-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            display: inline-block;
            flex: 1;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e1e5e9;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .security-notice {
            margin-top: 30px;
            padding: 15px;
            background: #fef3c7;
            border-radius: 8px;
            border-left: 4px solid #f59e0b;
            text-align: left;
        }

        .security-notice h4 {
            color: #92400e;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .security-notice p {
            color: #92400e;
            font-size: 13px;
            margin-bottom: 0;
        }

        .stay-logged-in {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .stay-logged-in a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .stay-logged-in a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="confirm-container">
        <div class="warning-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h1>Confirm Logout</h1>
        
        <p>Are you sure you want to log out of your account? You'll need to log in again to access your dashboard.</p>
        
        <div class="user-info">
            <?php if (isset($_SESSION['username'])): ?>
            <div class="info-row">
                <span class="info-label">Username:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['admin_username'])): ?>
            <div class="info-row">
                <span class="info-label">Admin Username:</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['role'])): ?>
            <div class="info-row">
                <span class="info-label">Role:</span>
                <span class="info-value"><?php echo ucfirst($_SESSION['role']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['admin_role'])): ?>
            <div class="info-row">
                <span class="info-label">Admin Role:</span>
                <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['admin_role'])); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">Login Time:</span>
                <span class="info-value"><?php echo isset($_SESSION['login_time']) ? date('H:i:s', $_SESSION['login_time']) : 'N/A'; ?></span>
            </div>
        </div>
        
        <form method="POST" action="logout.php">
            <div class="btn-group">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Yes, Logout
                </button>
                
                <?php 
                // Determine where to go back based on user role
                $backUrl = 'javascript:history.back()';
                if (isset($_SESSION['admin_logged_in'])) {
                    $backUrl = 'admin-dashboard.php';
                } elseif (isset($_SESSION['role'])) {
                    switch ($_SESSION['role']) {
                        case 'player': $backUrl = 'player-dashboard.php'; break;
                        case 'scout': $backUrl = 'scout-dashboard.php'; break;
                        case 'club': $backUrl = 'home.php'; break;
                        default: $backUrl = 'admin-dashboard.php';
                    }
                }
                ?>
                <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        
        <div class="security-notice">
            <h4><i class="fas fa-shield-alt"></i> Security Notice</h4>
            <p>Always log out when using shared or public computers to protect your account information.</p>
        </div>
        
        <div class="stay-logged-in">
            <a href="<?php echo $backUrl; ?>">
                <i class="fas fa-arrow-left"></i> Return to dashboard without logging out
            </a>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>