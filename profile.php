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
    <link rel="stylesheet" href="css/profile.css">
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
                                <div class="field-error"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                                       required
                                       oninput="validateEmail(this)">
                                <div class="field-error"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                       oninput="validatePhone(this)">
                                <div class="field-error" ></div>
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
                                <div class="field-error"></div>
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
    <script src="js/profile.js"></script>
</body>
</html>