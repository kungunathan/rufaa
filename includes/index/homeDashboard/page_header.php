<div class="page-header">
    <h1 class="page-title">Home</h1>
    <div class="user-info">
        <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
        <?php if ($alert_count > 0): ?>
            <div class="alert-badge"><?php echo $alert_count; ?></div>
        <?php endif; ?>
    </div>
</div>