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
    <link rel="stylesheet" href="css/reportIssue.css">    
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
    <script src="js/report_issue.js"></script>
</body>
</html>