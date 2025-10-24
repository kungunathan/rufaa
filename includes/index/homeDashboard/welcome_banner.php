<div class="welcome-banner">
    <h2 class="welcome-title">Welcome <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></h2>
    <p class="welcome-message">
        <?php if ($alert_count > 0): ?>
            You have <span class="alert-count"><?php echo $alert_count; ?> unread alerts</span>!
        <?php else: ?>
            All systems are running smoothly! You have no unread alerts!
        <?php endif; ?>
    </p>
</div>