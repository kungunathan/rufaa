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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .login-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .error-message {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-message {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input::placeholder {
            color: #999;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-input {
            margin: 0;
        }

        .checkbox-label {
            font-size: 0.9rem;
            color: #555;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .signup-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            color: #666;
            font-size: 0.9rem;
        }

        .signup-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .logo {
                font-size: 1.8rem;
            }

            .login-title {
                font-size: 1.3rem;
            }
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .field-error {
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 5px;
            display: none;
        }

        .input-error {
            border-color: #e74c3c !important;
        }

        .input-success {
            border-color: #27ae60 !important;
        }
    </style>
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

    <script>
        function validateEmail(field) {
            const errorElement = document.getElementById('emailError');
            const email = field.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email === '') {
                showError(field, errorElement, 'Email is required');
                return false;
            } else if (!emailRegex.test(email)) {
                showError(field, errorElement, 'Please enter a valid email address');
                return false;
            } else {
                showSuccess(field, errorElement);
                return true;
            }
        }

        function validatePassword(field) {
            const errorElement = document.getElementById('passwordError');
            const password = field.value;
            
            if (password === '') {
                showError(field, errorElement, 'Password is required');
                return false;
            } else if (password.length < 6) {
                showError(field, errorElement, 'Password must be at least 6 characters');
                return false;
            } else {
                showSuccess(field, errorElement);
                return true;
            }
        }

        function showError(field, errorElement, message) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            field.classList.add('input-error');
            field.classList.remove('input-success');
        }

        function showSuccess(field, errorElement) {
            errorElement.style.display = 'none';
            field.classList.remove('input-error');
            field.classList.add('input-success');
        }

        // Form submission validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all fields
            isValid = validateEmail(document.getElementById('email')) && isValid;
            isValid = validatePassword(document.getElementById('password')) && isValid;
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.field-error[style="display: block;"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                // Show loading state
                const submitButton = document.getElementById('submitButton');
                submitButton.innerHTML = 'Signing In...';
                submitButton.disabled = true;
                document.getElementById('loginForm').classList.add('loading');
            }
        });

        // Auto-focus on email field
        document.getElementById('email').focus();

        // Add real-time validation
        document.getElementById('email').addEventListener('input', function() {
            validateEmail(this);
        });

        document.getElementById('password').addEventListener('input', function() {
            validatePassword(this);
        });
    </script>
</body>
</html>