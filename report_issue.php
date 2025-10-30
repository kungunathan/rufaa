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

// Fetch data
try {
    $alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $alert_stmt->execute([$user_id]);
    $alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    $alert_count = $alert_data['alert_count'] ?? 0;

} catch (PDOException $e) {
    error_log("Report issue data fetch error: " . $e->getMessage());
    $alert_count = 0;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $issue_type = $_POST['issue_type'];
    $related_module = $_POST['related_module'];
    $issue_title = trim($_POST['issue_title']);
    $issue_description = trim($_POST['issue_description']);
    $priority_level = $_POST['priority_level'];
    $impact_description = trim($_POST['impact_description']);
    $follow_up = isset($_POST['follow_up']) ? 1 : 0;

    // Validate required fields
    if (empty($issue_type) || empty($issue_title) || empty($issue_description) || empty($priority_level)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Insert issue report
            $stmt = $pdo->prepare("INSERT INTO issue_reports (
                user_id, issue_type, related_module, issue_title, issue_description,
                priority_level, impact_description, follow_up, reporter_name,
                reporter_email, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
            
            // Get user email for reporter information
            $user_email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $user_email_stmt->execute([$user_id]);
            $user_email = $user_email_stmt->fetch(PDO::FETCH_ASSOC)['email'];
            
            if ($stmt->execute([
                $user_id, $issue_type, $related_module, $issue_title, $issue_description,
                $priority_level, $impact_description, $follow_up, $user_name, $user_email
            ])) {
                $success = "Issue reported successfully! Your report has been submitted and will be reviewed by our team. Reference ID: #" . $pdo->lastInsertId();
                
                // Create alert for admin users about new issue report
                $alert_message = "New issue report submitted: " . $issue_title . " (Priority: " . $priority_level . ")";
                $admin_alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) 
                                                 SELECT id, ?, 'warning' FROM users WHERE role IN ('admin', 'super_admin') AND is_active = 1");
                $admin_alert_stmt->execute([$alert_message]);
                
                // Clear form data
                $_POST = array();
            } else {
                $error = "Failed to submit issue report. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Issue report submission error: " . $e->getMessage());
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
    <title>Rufaa - Report Issue</title>
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

        /* Form Container */
        .issue-form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f1f1f1;
        }

        .form-section:last-child {
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

        .section-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
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

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: #999;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        /* Priority Level Styles */
        .priority-options {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .priority-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .priority-option:hover {
            border-color: #667eea;
            background-color: #f8f9fa;
        }

        .priority-option.selected {
            color: white;
            border-color: transparent;
        }

        .priority-low.selected {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .priority-medium.selected {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }

        .priority-high.selected {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .priority-critical.selected {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
            animation: critical-pulse 2s infinite;
        }

        @keyframes critical-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        /* Checkbox Styles */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .checkbox-input {
            margin-top: 3px;
            width: 18px;
            height: 18px;
        }

        .checkbox-label {
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 500;
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

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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

        /* Field Error Styles */
        .field-error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .input-error {
            border-color: #e74c3c !important;
        }

        .input-success {
            border-color: #27ae60 !important;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .info-title {
            font-weight: 600;
            color: #1565c0;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-content {
            color: #1976d2;
            font-size: 14px;
            line-height: 1.5;
        }

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
            
            .priority-options {
                flex-direction: column;
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
            
            .issue-form-container {
                padding: 20px;
            }
            
            .form-section {
                margin-bottom: 30px;
                padding-bottom: 20px;
            }
            
            .checkbox-group {
                padding: 15px;
            }
            
            .info-box {
                padding: 15px;
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
                    <a href="profile.php" class="nav-link">
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="report_issue.php" class="nav-link active">
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
                <h1 class="page-title">Report an Issue</h1>
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

            <!-- Issue Form Container -->
            <div class="issue-form-container">
                <div class="info-box">
                    <div class="info-title">💡 Help Us Improve</div>
                    <div class="info-content">
                        Please provide detailed information about the issue you're experiencing. The more specific you are, the better we can help resolve it quickly. 
                        Our support team will review your report and get back to you if follow-up is requested.
                    </div>
                </div>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="issueForm">
                    <!-- Issue Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">Issue Details</h2>
                        <p class="section-subtitle">
                            Provide basic information about the issue you're reporting.
                        </p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="issue_type">Issue Type</label>
                                <select id="issue_type" name="issue_type" class="form-select" required onchange="validateField(this)">
                                    <option value="">Select issue type</option>
                                    <option value="technical" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'technical') ? 'selected' : ''; ?>>Technical Issue</option>
                                    <option value="bug" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'bug') ? 'selected' : ''; ?>>Bug Report</option>
                                    <option value="feature" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'feature') ? 'selected' : ''; ?>>Feature Request</option>
                                    <option value="data" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'data') ? 'selected' : ''; ?>>Data Issue</option>
                                    <option value="ui" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'ui') ? 'selected' : ''; ?>>User Interface</option>
                                    <option value="performance" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'performance') ? 'selected' : ''; ?>>Performance</option>
                                    <option value="other" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="field-error">Please select an issue type</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="related_module">Related Module</label>
                                <select id="related_module" name="related_module" class="form-select" onchange="validateField(this)">
                                    <option value="">Select module (if applicable)</option>
                                    <option value="referrals" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'referrals') ? 'selected' : ''; ?>>Referrals</option>
                                    <option value="patients" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'patients') ? 'selected' : ''; ?>>Patients</option>
                                    <option value="reports" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'reports') ? 'selected' : ''; ?>>Reports</option>
                                    <option value="user" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'user') ? 'selected' : ''; ?>>User Management</option>
                                    <option value="system" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'system') ? 'selected' : ''; ?>>System</option>
                                </select>
                                <div class="field-error"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="issue_title">Issue Title</label>
                            <input type="text" id="issue_title" name="issue_title" class="form-input" 
                                   placeholder="Brief, descriptive title of the issue" 
                                   value="<?php echo htmlspecialchars($_POST['issue_title'] ?? ''); ?>" 
                                   required
                                   oninput="validateField(this)">
                            <div class="field-error">Issue title is required</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="issue_description">Issue Description</label>
                            <textarea id="issue_description" name="issue_description" class="form-textarea" 
                                      placeholder="Please provide a detailed description of the issue. Include steps to reproduce, error messages, and what you expected to happen."
                                      required
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['issue_description'] ?? ''); ?></textarea>
                            <div class="field-error">Please provide a detailed description of the issue</div>
                        </div>
                    </div>

                    <!-- Priority & Impact Section -->
                    <div class="form-section">
                        <h2 class="section-title">Priority & Impact</h2>
                        <p class="section-subtitle">
                            Help us understand the severity and impact of this issue.
                        </p>

                        <div class="form-group">
                            <label class="form-label required">Priority Level</label>
                            <div class="priority-options">
                                <div class="priority-option priority-low <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'low') ? 'selected' : ''; ?>" 
                                     onclick="selectPriority('low')">
                                    Low
                                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Minor issue</div>
                                </div>
                                <div class="priority-option priority-medium <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'medium') ? 'selected' : ''; ?>" 
                                     onclick="selectPriority('medium')">
                                    Medium
                                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Moderate impact</div>
                                </div>
                                <div class="priority-option priority-high <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'high') ? 'selected' : ''; ?>" 
                                     onclick="selectPriority('high')">
                                    High
                                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Significant impact</div>
                                </div>
                                <div class="priority-option priority-critical <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'critical') ? 'selected' : ''; ?>" 
                                     onclick="selectPriority('critical')">
                                    Critical
                                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">System blocking</div>
                                </div>
                            </div>
                            <input type="hidden" id="priority_level" name="priority_level" value="<?php echo htmlspecialchars($_POST['priority_level'] ?? ''); ?>" required>
                            <div class="field-error">Please select priority level</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="impact_description">Impact Description</label>
                            <textarea id="impact_description" name="impact_description" class="form-textarea" 
                                      placeholder="How is this issue affecting your work? How many users are affected? What's the business impact?"
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['impact_description'] ?? ''); ?></textarea>
                            <div class="field-error"></div>
                        </div>
                    </div>

                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <h2 class="section-title">Additional Information</h2>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="follow_up" name="follow_up" class="checkbox-input" 
                                   <?php echo (isset($_POST['follow_up']) && $_POST['follow_up']) ? 'checked' : ''; ?>>
                            <label for="follow_up" class="checkbox-label">
                                I would like to be contacted for follow-up information regarding this issue. 
                                (Our support team may reach out to you for additional details if needed.)
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="submitButton">Submit Issue Report</button>
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
                showError(field, errorElement, field.labels[0].textContent + ' is required');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validateTextarea(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            if (field.hasAttribute('required') && field.value.trim() === '') {
                showError(field, errorElement, field.labels[0].textContent + ' is required');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function showError(field, errorElement, message) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            field.classList.add('input-error');
            field.classList.remove('input-success');
        }

        function hideError(field, errorElement) {
            errorElement.style.display = 'none';
            field.classList.remove('input-error');
            field.classList.add('input-success');
        }

        // Priority level selection
        function selectPriority(level) {
            const options = document.querySelectorAll('.priority-option');
            options.forEach(option => {
                option.classList.remove('selected');
            });
            
            const selectedOption = document.querySelector(`.priority-${level}`);
            selectedOption.classList.add('selected');
            
            const hiddenInput = document.getElementById('priority_level');
            hiddenInput.value = level;
            
            // Validate the field
            validateField(hiddenInput);
        }

        // Form submission validation
        document.getElementById('issueForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all required fields
            const requiredFields = document.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (field.tagName === 'SELECT') {
                    if (!validateField(field)) isValid = false;
                } else if (field.tagName === 'TEXTAREA') {
                    if (!validateTextarea(field)) isValid = false;
                } else {
                    if (!validateField(field)) isValid = false;
                }
            });
            
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
                submitButton.innerHTML = 'Submitting Report...';
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
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.1}s`;
                section.classList.add('fade-in');
            });

            // Initialize priority level if already selected
            const priorityLevel = document.getElementById('priority_level').value;
            if (priorityLevel) {
                selectPriority(priorityLevel);
            }
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