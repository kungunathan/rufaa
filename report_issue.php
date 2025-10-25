<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle logout
if (isset($_GET['logout'])) {
    // Clear session
    session_destroy();
    
    // Clear remember token
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$token]);
        setcookie('remember_token', '', time() - 3600, "/");
    }
    
    header("Location: login.php");
    exit();
}

// Fetch user alerts count
$alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = FALSE");
$alert_stmt->execute([$user_id]);
$alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
$alert_count = $alert_data['alert_count'] ?? 0;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $issue_type = trim($_POST['issue_type']);
    $related_module = trim($_POST['related_module']);
    $issue_title = trim($_POST['issue_title']);
    $issue_description = trim($_POST['issue_description']);
    $priority_level = trim($_POST['priority_level']);
    $impact_description = trim($_POST['impact_description']);
    $reporter_name = trim($_POST['reporter_name']);
    $reporter_email = trim($_POST['reporter_email']);
    $reporter_phone = trim($_POST['reporter_phone']);
    $follow_up = isset($_POST['follow_up']) ? 1 : 0;
    
    // Insert issue report
    $stmt = $pdo->prepare("INSERT INTO issue_reports (user_id, issue_type, related_module, issue_title, issue_description, priority_level, impact_description, reporter_name, reporter_email, reporter_phone, follow_up, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
    
    if ($stmt->execute([$user_id, $issue_type, $related_module, $issue_title, $issue_description, $priority_level, $impact_description, $reporter_name, $reporter_email, $reporter_phone, $follow_up])) {
        $success = "Issue report submitted successfully! We will contact you soon.";
    } else {
        $error = "Failed to submit issue report. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Report Issue</title>
    <link rel="stylesheet" href="styles/reportIssue/reportIssue.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php' ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Report Issue</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="issue-form-container">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Issue Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">Issue Details</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="issueType">Issue Type</label>
                                <select id="issueType" name="issue_type" class="form-select" required>
                                    <option value="">Select issue type</option>
                                    <option value="technical" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'technical') ? 'selected' : ''; ?>>Technical Problem</option>
                                    <option value="feature" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'feature') ? 'selected' : ''; ?>>Feature Request</option>
                                    <option value="bug" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'bug') ? 'selected' : ''; ?>>Software Bug</option>
                                    <option value="data" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'data') ? 'selected' : ''; ?>>Data Issue</option>
                                    <option value="ui" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'ui') ? 'selected' : ''; ?>>User Interface Problem</option>
                                    <option value="performance" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'performance') ? 'selected' : ''; ?>>Performance Issue</option>
                                    <option value="other" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="relatedModule">Related Module</label>
                                <select id="relatedModule" name="related_module" class="form-select">
                                    <option value="">Select module (if applicable)</option>
                                    <option value="referrals" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'referrals') ? 'selected' : ''; ?>>Referrals</option>
                                    <option value="patients" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'patients') ? 'selected' : ''; ?>>Patient Management</option>
                                    <option value="reports" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'reports') ? 'selected' : ''; ?>>Reports & Analytics</option>
                                    <option value="user" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'user') ? 'selected' : ''; ?>>User Management</option>
                                    <option value="system" <?php echo (isset($_POST['related_module']) && $_POST['related_module'] == 'system') ? 'selected' : ''; ?>>System Settings</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="issueTitle">Issue Title</label>
                            <input type="text" id="issueTitle" name="issue_title" class="form-input" placeholder="Brief description of the issue" value="<?php echo htmlspecialchars($_POST['issue_title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="issueDescription">Detailed Description</label>
                            <textarea id="issueDescription" name="issue_description" class="form-textarea" placeholder="Please provide a detailed description of the issue, including steps to reproduce if applicable" required><?php echo htmlspecialchars($_POST['issue_description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Priority & Impact Section -->
                    <div class="form-section">
                        <h2 class="section-title">Priority & Impact</h2>
                        <div class="form-group">
                            <label class="form-label">Priority Level</label>
                            <div class="priority-indicator">
                                <div class="priority-option priority-low" data-level="low">Low</div>
                                <div class="priority-option priority-medium" data-level="medium">Medium</div>
                                <div class="priority-option priority-high" data-level="high">High</div>
                                <div class="priority-option priority-critical" data-level="critical">Critical</div>
                            </div>
                            <input type="hidden" id="priorityLevel" name="priority_level" value="<?php echo htmlspecialchars($_POST['priority_level'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="impactDescription">Impact Description</label>
                            <textarea id="impactDescription" name="impact_description" class="form-textarea" placeholder="How is this issue affecting your work?"><?php echo htmlspecialchars($_POST['impact_description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h2 class="section-title">Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="reporterName">Your Name</label>
                                <input type="text" id="reporterName" name="reporter_name" class="form-input" placeholder="Enter your name" value="<?php echo htmlspecialchars($_POST['reporter_name'] ?? $user_name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="reporterEmail">Email Address</label>
                                <input type="email" id="reporterEmail" name="reporter_email" class="form-input" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['reporter_email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reporterPhone">Phone Number (Optional)</label>
                            <input type="tel" id="reporterPhone" name="reporter_phone" class="form-input" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['reporter_phone'] ?? ''); ?>">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="followUp" name="follow_up" class="checkbox-input" <?php echo (isset($_POST['follow_up']) && $_POST['follow_up']) ? 'checked' : ''; ?>>
                            <label for="followUp" class="checkbox-label">
                                Please contact me for follow-up information if needed
                            </label>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Submit Issue Report</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section">
                <h2 class="faq-title">Frequently Asked Questions</h2>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What types of issues should I report here?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Report any technical problems, software bugs, feature requests, or usability issues you encounter while using Rufaa. This includes problems with referrals, patient data, reporting, or any other system functionality.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How quickly will my issue be addressed?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Response times vary based on issue priority. Critical issues affecting patient care are addressed within 2 hours. High priority issues are typically resolved within 24 hours, while medium and low priority issues are addressed within 3-5 business days.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I track the status of my reported issue?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, after submitting an issue, you will receive a tracking number. You can use this to check the status of your report in the "My Reports" section of your profile.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What information should I include for technical issues?</span>
                        <span>+</span>
                    </div>
                    <div class="faq-answer">
                        <p>For technical issues, please include: your browser and version, steps to reproduce the problem, error messages (if any), screenshots, and the date/time the issue occurred.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="scripts/reportIssue.js"></script>
</body>
</html>