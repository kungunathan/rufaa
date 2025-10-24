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

// Fetch data for home dashboard
require_once 'includes/index/homeDashboard/home_data.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Home</title>
    <link rel="stylesheet" href="styles/index/index.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php' ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include 'includes/index/homeDashboard/page_header.php' ?>
            
            <?php include 'includes/index/homeDashboard/welcome_banner.php' ?>

            <div class="dashboard-grid">
                <?php include 'includes/index/homeDashboard/recent_referrals.php' ?>
                
                <?php include 'includes/index/homeDashboard/today_stats.php' ?>
            </div>

            <div class="action-buttons">
                <a href="write_referral.php" class="btn btn-primary">Write a referral</a>
                <a href="report_issue.php" class="btn btn-secondary">Report an issue</a>
                <a href="index.php?logout=true" class="btn btn-logout">Log out</a>
            </div>
        </div>
    </div>
</body>
</html>