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

        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .register-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 8px;
        }

        .register-subtitle {
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

        .form-section {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 15px;
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

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 25px 0;
        }

        .checkbox-input {
            margin-top: 2px;
        }

        .checkbox-label {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }

        .terms-link {
            color: #667eea;
            text-decoration: none;
        }

        .terms-link:hover {
            text-decoration: underline;
        }

        .create-account-button {
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

        .create-account-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .create-account-button:active {
            transform: translateY(0);
        }

        .login-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            color: #666;
            font-size: 0.9rem;
        }

        .login-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .logo {
                font-size: 1.8rem;
            }

            .register-title {
                font-size: 1.3rem;
            }
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
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
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="lastName" class="form-input" 
                               placeholder="Enter your last name" 
                               value="<?php echo htmlspecialchars($lastName); ?>" 
                               required
                               oninput="validateField(this)">
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
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
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="Enter your phone number" 
                               value="<?php echo htmlspecialchars($phone); ?>" 
                               required
                               oninput="validatePhone(this)">
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
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
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirmPassword">Confirm Password *</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" 
                               placeholder="Confirm your password" 
                               required
                               oninput="validateConfirmPassword(this)">
                        <div class="field-error" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;"></div>
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

    <script>
        function validateField(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            if (field.value.trim() === '') {
                showError(errorElement, 'This field is required');
                return false;
            } else {
                hideError(errorElement);
                return true;
            }
        }

        function validateEmail(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const email = field.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email === '') {
                showError(errorElement, 'Email is required');
                return false;
            } else if (!emailRegex.test(email)) {
                showError(errorElement, 'Please enter a valid email address');
                return false;
            } else {
                hideError(errorElement);
                return true;
            }
        }

        function validatePhone(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const phone = field.value.trim();
            
            if (phone === '') {
                showError(errorElement, 'Phone number is required');
                return false;
            } else if (phone.length < 10) {
                showError(errorElement, 'Please enter a valid phone number');
                return false;
            } else {
                hideError(errorElement);
                return true;
            }
        }

        function validatePassword(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const strengthElement = field.parentElement.querySelector('.password-strength');
            const password = field.value;
            
            if (password === '') {
                showError(errorElement, 'Password is required');
                strengthElement.textContent = '';
                return false;
            } else if (password.length < 8) {
                showError(errorElement, 'Password must be at least 8 characters');
                strengthElement.textContent = 'Weak';
                strengthElement.className = 'password-strength strength-weak';
                return false;
            } else {
                hideError(errorElement);
                
                // Simple password strength check
                let strength = 'Weak';
                let strengthClass = 'strength-weak';
                
                if (password.length >= 12) {
                    strength = 'Strong';
                    strengthClass = 'strength-strong';
                } else if (password.length >= 8) {
                    strength = 'Medium';
                    strengthClass = 'strength-medium';
                }
                
                strengthElement.textContent = strength;
                strengthElement.className = 'password-strength ' + strengthClass;
                return true;
            }
        }

        function validateConfirmPassword(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const confirmPassword = field.value;
            const password = document.getElementById('password').value;
            
            if (confirmPassword === '') {
                showError(errorElement, 'Please confirm your password');
                return false;
            } else if (confirmPassword !== password) {
                showError(errorElement, 'Passwords do not match');
                return false;
            } else {
                hideError(errorElement);
                return true;
            }
        }

        function showError(errorElement, message) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        function hideError(errorElement) {
            errorElement.style.display = 'none';
        }

        // Form submission validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all fields
            isValid = validateField(document.getElementById('firstName')) && isValid;
            isValid = validateField(document.getElementById('lastName')) && isValid;
            isValid = validateEmail(document.getElementById('email')) && isValid;
            isValid = validatePhone(document.getElementById('phone')) && isValid;
            isValid = validatePassword(document.getElementById('password')) && isValid;
            isValid = validateConfirmPassword(document.getElementById('confirmPassword')) && isValid;
            
            // Validate terms
            const termsCheckbox = document.getElementById('terms');
            const termsError = termsCheckbox.parentElement.querySelector('.field-error') || 
                             (function() {
                                 const errorDiv = document.createElement('div');
                                 errorDiv.className = 'field-error';
                                 errorDiv.style.cssText = 'color: #e74c3c; font-size: 0.8rem; margin-top: 5px;';
                                 termsCheckbox.parentElement.appendChild(errorDiv);
                                 return errorDiv;
                             })();
            
            if (!termsCheckbox.checked) {
                showError(termsError, 'You must agree to the terms and privacy policy');
                isValid = false;
            } else {
                hideError(termsError);
            }
            
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
                submitButton.innerHTML = 'Creating Account...';
                submitButton.disabled = true;
                document.getElementById('registrationForm').classList.add('loading');
            }
        });
    </script>
</body>
</html>