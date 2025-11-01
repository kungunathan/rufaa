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

// Handle capacity update
if (isset($_POST['update_capacity'])) {
    try {
        $total_capacity = (int)$_POST['total_capacity'];
        $available_capacity = (int)$_POST['available_capacity'];
        
        if ($total_capacity < 0 || $available_capacity < 0) {
            $_SESSION['error_message'] = "Capacity values cannot be negative!";
        } elseif ($available_capacity > $total_capacity) {
            $_SESSION['error_message'] = "Available capacity cannot exceed total capacity!";
        } else {
            // Calculate utilization rate
            $utilization_rate = $total_capacity > 0 ? (($total_capacity - $available_capacity) / $total_capacity) * 100 : 0;
            
            $update_stmt = $pdo->prepare("
                INSERT INTO capacity (user_id, total_capacity, available_capacity, utilization_rate, notes) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    total_capacity = VALUES(total_capacity),
                    available_capacity = VALUES(available_capacity),
                    utilization_rate = VALUES(utilization_rate),
                    notes = VALUES(notes),
                    created_at = NOW()
            ");
            $update_stmt->execute([
                $user_id, 
                $total_capacity, 
                $available_capacity, 
                $utilization_rate,
                $_POST['capacity_notes'] ?? null
            ]);
            
            $_SESSION['success_message'] = "Capacity updated successfully!";
        }
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        error_log("Capacity update error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating capacity. Please try again.";
        header("Location: index.php");
        exit();
    }
}

// Handle mark all alerts as read
if (isset($_GET['mark_all_read'])) {
    try {
        $update_stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $update_stmt->execute([$user_id]);
        $_SESSION['success_message'] = "All alerts marked as read!";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        error_log("Mark alerts read error: " . $e->getMessage());
    }
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

    // Fetch recent alerts for display
    $recent_alerts_stmt = $pdo->prepare("
        SELECT id, message, alert_type, created_at, is_read 
        FROM alerts 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_alerts_stmt->execute([$user_id]);
    $recent_alerts = $recent_alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

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

    // Fetch capacity with latest record
    $capacity_stmt = $pdo->prepare("
        SELECT total_capacity, available_capacity, utilization_rate, notes 
        FROM capacity 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $capacity_stmt->execute([$user_id]);
    $capacity_data = $capacity_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_capacity = $capacity_data['total_capacity'] ?? 0;
    $available_capacity = $capacity_data['available_capacity'] ?? 0;
    $utilization_rate = $capacity_data['utilization_rate'] ?? 0;
    $capacity_notes = $capacity_data['notes'] ?? '';
    
    // Check if user is at full capacity
    $is_at_full_capacity = ($available_capacity <= 0 && $total_capacity > 0);

} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $alert_count = 0;
    $recent_alerts = [];
    $recent_referrals = [];
    $today_referrals = 0;
    $outgoing_referrals = 0;
    $incoming_referrals = 0;
    $pending_referrals = 0;
    $total_capacity = 0;
    $available_capacity = 0;
    $utilization_rate = 0;
    $capacity_notes = '';
    $is_at_full_capacity = false;
}

// Check for messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
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
            cursor: pointer;
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
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
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

        .mark-read-btn {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s ease;
        }

        .mark-read-btn:hover {
            background: rgba(255, 255, 255, 0.4);
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

        .referrals-list, .alerts-list {
            list-style: none;
        }

        .referral-item, .alert-item {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .referral-item:hover, .alert-item:hover {
            background-color: #f8f9fa;
        }

        .referral-item:last-child, .alert-item:last-child {
            border-bottom: none;
        }

        .referral-info, .alert-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
        }

        .referral-code {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }

        .referral-condition, .alert-message {
            color: #7f8c8d;
            font-size: 13px;
        }

        .alert-message.unread {
            color: #2c3e50;
            font-weight: 500;
        }

        .referral-meta, .alert-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .referral-type, .alert-type {
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

        .alert-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .alert-urgent {
            background-color: #f8d7da;
            color: #721c24;
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

        .referral-date, .alert-date {
            font-size: 12px;
            color: #95a5a6;
        }

        .no-referrals, .no-alerts {
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
            position: relative;
            overflow: hidden;
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

        .capacity-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            opacity: 0.1;
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

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
            min-width: auto;
        }

        /* Capacity Management Styles */
        .capacity-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .capacity-full {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .capacity-available {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .capacity-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .capacity-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .capacity-form-group {
            margin-bottom: 20px;
        }
        
        .capacity-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .capacity-form-group input,
        .capacity-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .capacity-form-group input:focus,
        .capacity-form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .capacity-form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .capacity-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
        
        .utilization-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .utilization-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .utilization-text {
            font-size: 12px;
            color: #6c757d;
            text-align: right;
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
                    <?php if ($is_at_full_capacity): ?>
                        <span class="capacity-status capacity-full" title="You are at full capacity and cannot receive new referrals">
                            Full Capacity
                        </span>
                    <?php else: ?>
                        <span class="capacity-status capacity-available" title="You have available capacity for new referrals">
                            Available
                        </span>
                    <?php endif; ?>
                    <?php if ($alert_count > 0): ?>
                        <a href="index.php?mark_all_read=true" class="alert-badge" title="Click to mark all alerts as read">
                            <?php echo $alert_count; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2 class="welcome-title">Welcome <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h2>
                <p class="welcome-message">
                    <?php if ($alert_count > 0): ?>
                        You have <span class="alert-count"><?php echo $alert_count; ?> unread alerts</span> that need your attention!
                        <a href="index.php?mark_all_read=true" class="mark-read-btn">Mark All Read</a>
                    <?php else: ?>
                        All systems are running smoothly! You have no unread alerts!
                    <?php endif; ?>
                </p>
                
                <?php if ($is_at_full_capacity): ?>
                    <div class="capacity-warning">
                        <strong>Capacity Alert:</strong> You are currently at full capacity and will not receive new referrals. 
                        Please update your capacity settings to accept new patients.
                    </div>
                <?php endif; ?>
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

                <!-- Alerts & Stats Section -->
                <div class="section">
                    <h2 class="section-title">Recent Alerts</h2>
                    <ul class="alerts-list">
                        <?php if (count($recent_alerts) > 0): ?>
                            <?php foreach ($recent_alerts as $alert): ?>
                                <li class="alert-item">
                                    <div class="alert-info">
                                        <span class="alert-message <?php echo $alert['is_read'] ? '' : 'unread'; ?>">
                                            <?php echo htmlspecialchars($alert['message']); ?>
                                        </span>
                                    </div>
                                    <div class="alert-meta">
                                        <span class="alert-type alert-<?php echo htmlspecialchars($alert['alert_type']); ?>">
                                            <?php echo ucfirst($alert['alert_type']); ?>
                                        </span>
                                        <span class="alert-date">
                                            <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="alert-item">
                                <span class="no-alerts">No recent alerts.</span>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <h2 class="section-title" style="margin-top: 30px;">Capacity & Stats</h2>
                    <div class="stats-grid">
                        <div class="stat-card capacity-card">
                            <div class="capacity-badge">📊</div>
                            <div class="stat-value"><?php echo $available_capacity; ?>/<?php echo $total_capacity; ?></div>
                            <div class="stat-label">Available Capacity</div>
                            <?php if ($total_capacity > 0): ?>
                                <div class="utilization-bar">
                                    <div class="utilization-fill" style="width: <?php echo $utilization_rate; ?>%"></div>
                                </div>
                                <div class="utilization-text">
                                    Utilization: <?php echo number_format($utilization_rate, 1); ?>%
                                </div>
                            <?php endif; ?>
                        </div>
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
                    </div>
                    
                    <button onclick="openCapacityModal()" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        Update Capacity Settings
                    </button>
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

    <!-- Capacity Modal -->
    <div id="capacityModal" class="capacity-modal">
        <div class="capacity-modal-content">
            <h2 style="margin-bottom: 20px; color: #2c3e50;">Update Capacity</h2>
            <form method="POST" action="index.php">
                <div class="capacity-form-group">
                    <label for="total_capacity">Total Capacity:</label>
                    <input type="number" id="total_capacity" name="total_capacity" 
                           value="<?php echo $total_capacity; ?>" min="0" required>
                </div>
                
                <div class="capacity-form-group">
                    <label for="available_capacity">Available Capacity:</label>
                    <input type="number" id="available_capacity" name="available_capacity" 
                           value="<?php echo $available_capacity; ?>" min="0" required>
                    <small style="color: #6c757d; display: block; margin-top: 5px;">
                        Set to 0 if you're at full capacity and don't want to receive new referrals.
                    </small>
                </div>
                
                <div class="capacity-form-group">
                    <label for="capacity_notes">Notes (Optional):</label>
                    <textarea id="capacity_notes" name="capacity_notes" rows="3" 
                              placeholder="Any notes about your current capacity..."><?php echo htmlspecialchars($capacity_notes); ?></textarea>
                </div>
                
                <div class="capacity-form-actions">
                    <button type="button" class="btn btn-logout btn-small" onclick="closeCapacityModal()">Cancel</button>
                    <button type="submit" name="update_capacity" class="btn btn-primary btn-small">Update Capacity</button>
                </div>
            </form>
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
                    if (this.href && this.href.includes('logout')) {
                        if (!confirm('Are you sure you want to log out?')) {
                            e.preventDefault();
                        }
                    }
                });
            });

            // Auto-refresh page every 60 seconds to update alert counts
            setInterval(() => {
                location.reload();
            }, 60000);
            
            // Validate capacity form
            const capacityForm = document.querySelector('form[method="POST"]');
            if (capacityForm) {
                capacityForm.addEventListener('submit', function(e) {
                    const total = parseInt(document.getElementById('total_capacity').value);
                    const available = parseInt(document.getElementById('available_capacity').value);
                    
                    if (total < 0 || available < 0) {
                        e.preventDefault();
                        alert('Capacity values cannot be negative!');
                        return false;
                    }
                    
                    if (available > total) {
                        e.preventDefault();
                        alert('Available capacity cannot exceed total capacity!');
                        return false;
                    }
                });
            }
        });

        // Capacity modal functions
        function openCapacityModal() {
            document.getElementById('capacityModal').style.display = 'flex';
        }
        
        function closeCapacityModal() {
            document.getElementById('capacityModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('capacityModal');
            if (e.target === modal) {
                closeCapacityModal();
            }
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