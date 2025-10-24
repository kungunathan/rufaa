<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$email = $password = "";
$remember = false;
$error = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, password_hash FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Handle remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $sessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
                $sessionStmt->execute([$user['id'], $token, $expires]);
                
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), "/");
            }
            
            // Redirect to home page
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

// Check for remember token
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['logged_in'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM users u JOIN user_sessions us ON u.id = us.user_id WHERE us.session_token = ? AND us.expires_at > NOW() AND u.is_active = TRUE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['logged_in'] = true;
        header("Location: index.php");
        exit();
    }
}

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Login</title>
    <link rel="stylesheet" href="styles/login/loginStyle.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">Rufaa</div>
            <h1 class="login-title">Login</h1>
            <p class="login-subtitle">Login to your account.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label class="form-label" for="email">E-mail Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" class="checkbox-input" <?php echo $remember ? 'checked' : ''; ?>>
                <label for="remember" class="checkbox-label">Remember me</label>
                <a href="reset_password.php" class="reset-link">Reset Password?</a>
            </div>

            <button type="submit" class="signin-button">Sign In</button>
        </form>

        <div class="signup-section">
            Don't have an account yet? <a href="signup.php" class="signup-link">Join Rufaa today.</a>
        </div>
    </div>
</body>
</html>