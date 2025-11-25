<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$email = $password = $remember = "";
$error = "";
$success = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // Check if user exists and is active
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if user is active
                if (!$user['is_active']) {
                    $error = "Your account has been deactivated. Please contact support.";
                } 
                // Verify password
                elseif (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    // Set success message
                    $success = "Login successful! Welcome back, " . htmlspecialchars($user['first_name']) . ".";
                    
                    // Handle remember me functionality
                    if ($remember) {
                        // Generate remember token
                        $rememberToken = bin2hex(random_bytes(32));
                        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        // Store token in database
                        $tokenStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                        $tokenStmt->execute([$rememberToken, $user['id']]);
                        
                        // Set cookie
                        setcookie('remember_token', $rememberToken, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Strict'
                        ]);
                    }
                    
                    // Redirect to home page after 2 seconds
                    header("refresh:2;url=index.php");
                    
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Check for remember token
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['logged_in'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, is_active FROM users WHERE remember_token = ? AND is_active = 1");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        // Log error but don't show to user
        error_log("Remember token error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Login</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">Rufaa</div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px; font-size: 0.8rem;">Redirecting to dashboard...</div>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="email">E-mail Address *</label>
                <input type="email" id="email" name="email" class="form-input" 
                       placeholder="Enter your email" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       required>
                <div class="field-error" id="emailError">Please enter a valid email address</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password *</label>
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="Enter your password" 
                       required>
                <div class="field-error" id="passwordError">Password is required</div>
            </div>

            <div class="form-options">
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" class="checkbox-input" <?php echo $remember ? 'checked' : ''; ?>>
                    <label for="remember" class="checkbox-label">Remember me</label>
                </div>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="login-button" id="submitButton">
                Sign In
            </button>
        </form>
        <?php endif; ?>

        <div class="signup-section">
            Don't have an account? <a href="signup.php" class="signup-link">Sign up</a>
        </div>
    </div>
    <script src="js/login.js"></script>
</body>
</html>