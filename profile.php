<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and active
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Validate user session and check if user is still active
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Check if user exists and is active
$user_stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ? AND is_active = 1");
$user_stmt->execute([$user_id]);

if ($user_stmt->rowCount() === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear remember token from database
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $update_stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $update_stmt->execute([$user_id]);
        setcookie('remember_token', '', time() - 3600, "/");
    }
    
    // Clear session
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch user data
try {
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, created_at, is_active FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $alert_stmt->execute([$user_id]);
    $alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    $alert_count = $alert_data['alert_count'] ?? 0;

} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $user_data = [];
    $alert_count = 0;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists (excluding current user)
            $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $email_check->execute([$email, $user_id]);
            
            if ($email_check->rowCount() > 0) {
                $error = "Email already exists. Please use a different email.";
            } else {
                // Update user data
                $update_stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                
                if ($update_stmt->execute([$first_name, $last_name, $email, $phone, $user_id])) {
                    $update_success = true;
                    
                    // Handle password change if provided
                    if (!empty($current_password) && !empty($new_password)) {
                        // Verify current password
                        $verify_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                        $verify_stmt->execute([$user_id]);
                        $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (password_verify($current_password, $user['password_hash'])) {
                            if ($new_password === $confirm_password) {
                                if (strlen($new_password) >= 8) {
                                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                    $password_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                                    
                                    if ($password_stmt->execute([$new_password_hash, $user_id])) {
                                        $success = "Profile and password updated successfully!";
                                    } else {
                                        $error = "Failed to update password. Please try again.";
                                    }
                                } else {
                                    $error = "New password must be at least 8 characters long.";
                                }
                            } else {
                                $error = "New passwords do not match.";
                            }
                        } else {
                            $error = "Current password is incorrect.";
                        }
                    } else {
                        if (empty($error)) {
                            $success = "Profile updated successfully!";
                        }
                    }
                    
                    // Update session data
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $user_name = $_SESSION['user_name'];
                    
                    // Refresh user data
                    $user_stmt->execute([$user_id]);
                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                } else {
                    $error = "Failed to update profile. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = "Database error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            padding: 0 20px;
            color: white;
        }

        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        .nav-link span {
            margin-left: 10px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            color: #555;
        }

        .alert-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Container */
        .profile-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .profile-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f1f1f1;
        }

        .profile-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            font-weight: 600;
        }

        /* Form Styles */
        .form-row {
            display: flex;
            gap: 25px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
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

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 500;
        }

        .status-active {
            color: #28a745;
            font-weight: 600;
        }

        .status-inactive {
            color: #dc3545;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f1f1f1;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: #7f8c8d;
            border: 2px solid #bdc3c7;
        }

        .btn-secondary:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            display: none;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .logo span, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                min-width: auto;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .profile-container {
                padding: 20px;
            }
            
            .profile-section {
                margin-bottom: 30px;
                padding-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">Rufaa</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="referrals.php" class="nav-link">
                        <span>Referrals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="write_referral.php" class="nav-link">
                        <span>Write Referral</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link active">
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="report_issue.php" class="nav-link">
                        <span>Report Issue</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?logout=true" class="nav-link">
                        <span>Log out</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Profile Settings</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge" title="<?php echo $alert_count; ?> unread alerts"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="profileForm">
                    <!-- Personal Information Section -->
                    <div class="profile-section">
                        <h2 class="section-title">Personal Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                                       required
                                       oninput="validateEmail(this)">
                                <div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                       oninput="validatePhone(this)">
                                <div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Section -->
                    <div class="profile-section">
                        <h2 class="section-title">Change Password</h2>
                        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
                            Leave password fields blank if you don't want to change your password.
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-input" 
                                       placeholder="Enter current password"
                                       oninput="validatePasswordChange()">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-input" 
                                       placeholder="Enter new password (min. 8 characters)"
                                       oninput="validatePasswordStrength(this)">
                                <div class="password-strength"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                       placeholder="Confirm new password"
                                       oninput="validatePasswordMatch()">
                                <div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="profile-section">
                        <h2 class="section-title">Account Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label class="info-label">Member Since</label>
                                <div class="info-value"><?php echo date('F j, Y', strtotime($user_data['created_at'] ?? '')); ?></div>
                            </div>
                            <div class="info-item">
                                <label class="info-label">User ID</label>
                                <div class="info-value"><?php echo $user_id; ?></div>
                            </div>
                            <div class="info-item">
                                <label class="info-label">Account Status</label>
                                <div class="info-value <?php echo ($user_data['is_active'] ?? 0) ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ($user_data['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <label class="info-label">User Role</label>
                                <div class="info-value"><?php echo ucfirst($user_role); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="submitButton">Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Back to Dashboard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Field validation functions
        function validateField(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            if (field.value.trim() === '') {
                showError(field, errorElement, 'This field is required');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validateEmail(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const email = field.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email === '') {
                showError(field, errorElement, 'Email is required');
                return false;
            } else if (!emailRegex.test(email)) {
                showError(field, errorElement, 'Please enter a valid email address');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validatePhone(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const phone = field.value.trim();
            
            if (phone !== '' && phone.length < 10) {
                showError(field, errorElement, 'Please enter a valid phone number');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validatePasswordStrength(field) {
            const strengthElement = field.parentElement.querySelector('.password-strength');
            const password = field.value;
            
            if (password === '') {
                strengthElement.style.display = 'none';
                return false;
            }
            
            strengthElement.style.display = 'block';
            
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
            
            return password.length >= 8;
        }

        function validatePasswordMatch() {
            const confirmField = document.getElementById('confirm_password');
            const newField = document.getElementById('new_password');
            const errorElement = confirmField.parentElement.querySelector('.field-error');
            
            if (confirmField.value !== '' && newField.value !== '' && confirmField.value !== newField.value) {
                showError(confirmField, errorElement, 'Passwords do not match');
                return false;
            } else {
                hideError(confirmField, errorElement);
                return true;
            }
        }

        function validatePasswordChange() {
            const currentField = document.getElementById('current_password');
            const newField = document.getElementById('new_password');
            const confirmField = document.getElementById('confirm_password');
            
            // If any password field has value, all should be validated
            if (currentField.value !== '' || newField.value !== '' || confirmField.value !== '') {
                if (currentField.value === '') {
                    showError(currentField, currentField.parentElement.querySelector('.field-error') || createErrorElement(currentField), 'Current password is required');
                    return false;
                }
                
                if (newField.value === '') {
                    showError(newField, newField.parentElement.querySelector('.field-error') || createErrorElement(newField), 'New password is required');
                    return false;
                }
                
                if (newField.value.length < 8) {
                    showError(newField, newField.parentElement.querySelector('.field-error') || createErrorElement(newField), 'Password must be at least 8 characters');
                    return false;
                }
                
                if (confirmField.value === '') {
                    showError(confirmField, confirmField.parentElement.querySelector('.field-error') || createErrorElement(confirmField), 'Please confirm your password');
                    return false;
                }
                
                if (confirmField.value !== newField.value) {
                    showError(confirmField, confirmField.parentElement.querySelector('.field-error') || createErrorElement(confirmField), 'Passwords do not match');
                    return false;
                }
            }
            
            return true;
        }

        function showError(field, errorElement, message) {
            if (!errorElement) {
                errorElement = createErrorElement(field);
            }
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            field.style.borderColor = '#e74c3c';
        }

        function hideError(field, errorElement) {
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            field.style.borderColor = '#e1e5e9';
        }

        function createErrorElement(field) {
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.style.cssText = 'color: #e74c3c; font-size: 12px; margin-top: 5px;';
            field.parentElement.appendChild(errorElement);
            return errorElement;
        }

        // Form submission validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all required fields
            isValid = validateField(document.getElementById('first_name')) && isValid;
            isValid = validateField(document.getElementById('last_name')) && isValid;
            isValid = validateEmail(document.getElementById('email')) && isValid;
            isValid = validatePhone(document.getElementById('phone')) && isValid;
            isValid = validatePasswordChange() && isValid;
            
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
                submitButton.innerHTML = 'Saving Changes...';
                submitButton.disabled = true;
            }
        });

        // Auto-close messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });

            // Add fade-in animation to sections
            const sections = document.querySelectorAll('.profile-section');
            sections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.1}s`;
                section.classList.add('fade-in');
            });
        });

        // Add fade-in animation style
        const style = document.createElement('style');
        style.textContent = `
            .fade-in {
                animation: fadeIn 0.6s ease-in-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>