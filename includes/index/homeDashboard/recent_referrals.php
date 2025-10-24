<div class="section">
    <h2 class="section-title">Recent Referrals</h2>
    <ul class="referrals-list">
        <?php if (count($recent_referrals) > 0): ?>
            <?php foreach ($recent_referrals as $referral): ?>
                <li class="referral-item">
                    <span class="referral-code"><?php echo htmlspecialchars($referral['referral_code']); ?></span>
                    <span class="referral-condition"><?php echo htmlspecialchars($referral['condition_description']); ?></span>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="referral-item">
                <span class="no-referrals">No recent referrals found.</span>
            </li>
        <?php endif; ?>
    </ul>
</div>