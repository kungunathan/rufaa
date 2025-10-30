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
    // User not found or deactivated
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

// Fetch data for home dashboard
try {
    // Fetch user alerts count
    $alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $alert_stmt->execute([$user_id]);
    $alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    $alert_count = $alert_data['alert_count'] ?? 0;

    // Fetch recent referrals (both incoming and outgoing)
    $referral_stmt = $pdo->prepare("
        SELECT 
            referral_code, 
            condition_description, 
            type,
            status,
            patient_name,
            created_at 
        FROM referrals 
        WHERE user_id = ? OR receiving_user_id = ?
        ORDER BY created_at DESC 
        LIMIT 6
    ");
    $referral_stmt->execute([$user_id, $user_id]);
    $recent_referrals = $referral_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch today's stats
    $today = date('Y-m-d');
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as today_referrals,
            SUM(CASE WHEN type = 'outgoing' AND user_id = ? THEN 1 ELSE 0 END) as outgoing,
            SUM(CASE WHEN type = 'incoming' AND receiving_user_id = ? THEN 1 ELSE 0 END) as incoming,
            SUM(CASE WHEN status = 'pending' AND (user_id = ? OR receiving_user_id = ?) THEN 1 ELSE 0 END) as pending
        FROM referrals 
        WHERE DATE(created_at) = ?
    ");
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $today]);
    $stats_data = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    $today_referrals = $stats_data['today_referrals'] ?? 0;
    $outgoing_referrals = $stats_data['outgoing'] ?? 0;
    $incoming_referrals = $stats_data['incoming'] ?? 0;
    $pending_referrals = $stats_data['pending'] ?? 0;

    // Fetch capacity
    $capacity_stmt = $pdo->prepare("SELECT available_capacity FROM capacity WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $capacity_stmt->execute([$user_id]);
    $capacity_data = $capacity_stmt->fetch(PDO::FETCH_ASSOC);
    $available_capacity = $capacity_data['available_capacity'] ?? 0;

} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $alert_count = 0;
    $recent_referrals = [];
    $today_referrals = 0;
    $outgoing_referrals = 0;
    $incoming_referrals = 0;
    $pending_referrals = 0;
    $available_capacity = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Home</title>
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

        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }

        .welcome-title {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-message {
            font-size: 16px;
            opacity: 0.9;
        }

        .alert-count {
            font-weight: bold;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .section {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            font-weight: 600;
        }

        .referrals-list {
            list-style: none;
        }

        .referral-item {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .referral-item:hover {
            background-color: #f8f9fa;
        }

        .referral-item:last-child {
            border-bottom: none;
        }

        .referral-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .referral-code {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }

        .referral-condition {
            color: #7f8c8d;
            font-size: 13px;
        }

        .referral-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .referral-type {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .type-outgoing {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .type-incoming {
            background-color: #e8f5e8;
            color: #388e3c;
        }

        .referral-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .status-accepted {
            background-color: #e8f5e8;
            color: #388e3c;
        }

        .status-declined {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .referral-date {
            font-size: 12px;
            color: #95a5a6;
        }

        .no-referrals {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            min-width: 160px;
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
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }

        .btn-logout {
            background: transparent;
            color: #7f8c8d;
            border: 2px solid #ddd;
        }

        .btn-logout:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

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
            
            .nav-link span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .welcome-banner {
                padding: 20px;
            }
            
            .section {
                padding: 20px;
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
                    <a href="index.php" class="nav-link active">
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
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge" title="<?php echo $alert_count; ?> unread alerts"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2 class="welcome-title">Welcome <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h2>
                <p class="welcome-message">
                    <?php if ($alert_count > 0): ?>
                        You have <span class="alert-count"><?php echo $alert_count; ?> unread alerts</span> that need your attention!
                    <?php else: ?>
                        All systems are running smoothly! You have no unread alerts!
                    <?php endif; ?>
                </p>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Referrals Section -->
                <div class="section">
                    <h2 class="section-title">Recent Referrals</h2>
                    <ul class="referrals-list">
                        <?php if (count($recent_referrals) > 0): ?>
                            <?php foreach ($recent_referrals as $referral): ?>
                                <li class="referral-item">
                                    <div class="referral-info">
                                        <span class="referral-code"><?php echo htmlspecialchars($referral['referral_code']); ?></span>
                                        <span class="referral-condition"><?php echo htmlspecialchars($referral['condition_description'] ?: 'No description'); ?></span>
                                    </div>
                                    <div class="referral-meta">
                                        <span class="referral-type type-<?php echo htmlspecialchars($referral['type']); ?>">
                                            <?php echo ucfirst($referral['type']); ?>
                                        </span>
                                        <span class="referral-status status-<?php echo htmlspecialchars($referral['status']); ?>">
                                            <?php echo ucfirst($referral['status']); ?>
                                        </span>
                                        <span class="referral-date">
                                            <?php echo date('M j, g:i A', strtotime($referral['created_at'])); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="referral-item">
                                <span class="no-referrals">No recent referrals found.</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Today's Stats Section -->
                <div class="section">
                    <h2 class="section-title">Today's Overview</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $today_referrals; ?></div>
                            <div class="stat-label">Total Referrals Today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $outgoing_referrals; ?></div>
                            <div class="stat-label">Outgoing Referrals</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $incoming_referrals; ?></div>
                            <div class="stat-label">Incoming Referrals</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $available_capacity; ?></div>
                            <div class="stat-label">Available Capacity</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="write_referral.php" class="btn btn-primary">Write a Referral</a>
                <a href="report_issue.php" class="btn btn-secondary">Report an Issue</a>
                <a href="index.php?logout=true" class="btn btn-logout">Log Out</a>
            </div>
        </div>
    </div>

    <script>
        // Add smooth animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });

            // Add click animation to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href.includes('logout')) {
                        if (!confirm('Are you sure you want to log out?')) {
                            e.preventDefault();
                        }
                    }
                });
            });

            // Auto-refresh alerts count every 30 seconds
            setInterval(() => {
                fetch('includes/get_alerts_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const alertBadge = document.querySelector('.alert-badge');
                        const alertCount = document.querySelector('.alert-count');
                        const welcomeMessage = document.querySelector('.welcome-message');
                        
                        if (data.alert_count > 0) {
                            if (alertBadge) {
                                alertBadge.textContent = data.alert_count;
                            } else {
                                // Create alert badge if it doesn't exist
                                const userInfo = document.querySelector('.user-info');
                                const newBadge = document.createElement('div');
                                newBadge.className = 'alert-badge';
                                newBadge.textContent = data.alert_count;
                                userInfo.appendChild(newBadge);
                            }
                            
                            if (alertCount) {
                                alertCount.textContent = data.alert_count;
                            }
                            
                            if (welcomeMessage) {
                                welcomeMessage.innerHTML = `You have <span class="alert-count">${data.alert_count} unread alerts</span> that need your attention!`;
                            }
                        } else {
                            // Remove alert badge if no alerts
                            if (alertBadge) {
                                alertBadge.remove();
                            }
                            if (welcomeMessage) {
                                welcomeMessage.innerHTML = 'All systems are running smoothly! You have no unread alerts!';
                            }
                        }
                    })
                    .catch(error => console.error('Error fetching alerts:', error));
            }, 30000);
        });

        // Add fade-in animation
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