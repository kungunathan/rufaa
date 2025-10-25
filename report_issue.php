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

            <!-- Report issues form -->
            <?php include 'includes/reportIssue/form1.php' ?>
            <!-- FAQ Section -->
            <?php include 'includes/reportIssue/faqs.php'?>
        </div>
    </div>
    <script src="scripts/reportIssue.js"></script>
</body>
</html>