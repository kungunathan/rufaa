<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$firstName = $lastName = $email = $phone = $password = $confirmPassword = "";
$terms = false;
$error = "";
$success = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validate inputs
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Please enter a valid 10-digit phone number (numbers only).";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!$terms) {
        $error = "You must agree to the Terms and Privacy Policy.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Email already exists. Please use a different email.";
            } else {
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with default role and active status
                $insertStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'user', 1, NOW(), NOW())");
                
                if ($insertStmt->execute([$firstName, $lastName, $email, $phone, $passwordHash])) {
                    // Registration successful - automatically log in
                    $userId = $pdo->lastInsertId();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$userId]);
                    
                    // Set success message
                    $success = "Registration successful! Welcome to Rufaa.";
                    
                    // Redirect to home page after 2 seconds
                    header("refresh:2;url=index.php");
                    
                } else {
                    $error = "Registration failed. Please try again.";
                }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Register</title>
    <link rel="stylesheet" href="css/signup.css">
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">Rufaa</div>
            <h1 class="register-title">Welcome to Rufaa</h1>
            <p class="register-subtitle">Create your account to get started</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px; font-size: 0.8rem;">Redirecting to home page...</div>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registrationForm">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="firstName">First Name *</label>
                        <input type="text" id="firstName" name="firstName" class="form-input" 
                               placeholder="Enter your first name" 
                               value="<?php echo htmlspecialchars($firstName); ?>" 
                               required
                               oninput="validateField(this)">
                        <div class="field-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="lastName" class="form-input" 
                               placeholder="Enter your last name" 
                               value="<?php echo htmlspecialchars($lastName); ?>" 
                               required
                               oninput="validateField(this)">
                        <div class="field-error"></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">E-mail Address *</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required
                               oninput="validateEmail(this)">
                        <div class="field-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="Enter 10-digit phone number" 
                               value="<?php echo htmlspecialchars($phone); ?>" 
                               required
                               maxlength="10"
                               oninput="validatePhone(this)">
                        <div class="field-error"></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Create a password (min. 8 characters)" 
                               required
                               oninput="validatePassword(this)">
                        <div class="password-strength"></div>
                        <div class="field-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirmPassword">Confirm Password *</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" 
                               placeholder="Confirm your password" 
                               required
                               oninput="validateConfirmPassword(this)">
                        <div class="field-error"></div>
                    </div>
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="terms" name="terms" class="checkbox-input" required <?php echo $terms ? 'checked' : ''; ?>>
                <label for="terms" class="checkbox-label">
                    I agree to all the <a href="#" class="terms-link">Terms</a> and <a href="#" class="terms-link">Privacy Policy</a> *
                </label>
            </div>

            <button type="submit" class="create-account-button" id="submitButton">
                Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="login-section">
            Already have an account? <a href="login.php" class="login-link">Log in</a>
        </div>
    </div>
    <script src="js/signup.js"></script>
</body>
</html>