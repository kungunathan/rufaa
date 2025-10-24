<?php
// Get current page filename to determine active tab
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="logo">Rufaa</div>
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i>🏠</i>
                <span>Home</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="referrals.php" class="nav-link <?php echo $current_page == 'referrals.php' ? 'active' : ''; ?>">
                <i>📋</i>
                <span>Referrals</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i>👤</i>
                <span>Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="report_issue.php" class="nav-link <?php echo $current_page == 'report_issue.php' ? 'active' : ''; ?>">
                <i>⚠️</i>
                <span>Report issue</span>
            </a>
        </li>
    </ul>
    
    <div class="divider"></div>
    
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="write_referral.php" class="nav-link <?php echo $current_page == 'write_referral.php' ? 'active' : ''; ?>">
                <i>✍️</i>
                <span>Write a referral</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="report_issue.php" class="nav-link <?php echo $current_page == 'report_issue.php' ? 'active' : ''; ?>">
                <i>🔧</i>
                <span>Report an issue</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="index.php?logout=true" class="nav-link">
                <i>🚪</i>
                <span>Log out</span>
            </a>
        </li>
    </ul>
</div>