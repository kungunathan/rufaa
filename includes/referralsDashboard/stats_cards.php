<div class="stat-card">
    <div class="stat-header">
        <div>
            <div class="stat-value"><?php echo $total_referrals; ?></div>
            <div class="stat-label">Total Referrals Sent</div>
        </div>
        <div class="stat-icon outgoing">📤</div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-header">
        <div>
            <div class="stat-value"><?php echo count($accepted_referrals); ?></div>
            <div class="stat-label">Accepted</div>
        </div>
        <div class="stat-icon accepted">✅</div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-header">
        <div>
            <div class="stat-value"><?php echo count($declined_referrals); ?></div>
            <div class="stat-label">Declined</div>
        </div>
        <div class="stat-icon declined">❌</div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-header">
        <div>
            <div class="stat-value"><?php echo $pending_incoming; ?></div>
            <div class="stat-label">Pending Incoming</div>
        </div>
        <div class="stat-icon pending">⏳</div>
    </div>
</div>