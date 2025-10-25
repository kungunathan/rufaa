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
                <span>Home</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="referrals.php" class="nav-link <?php echo $current_page == 'referrals.php' ? 'active' : ''; ?>">
                <span>Referrals</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="write_referral.php" class="nav-link <?php echo $current_page == 'write_referral.php' ? 'active' : ''; ?>">
                <span>Write Referral</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <span>Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="report_issue.php" class="nav-link <?php echo $current_page == 'report_issue.php' ? 'active' : ''; ?>">
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