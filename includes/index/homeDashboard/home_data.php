<?php
// Fetch user alerts count
$alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = FALSE");
$alert_stmt->execute([$user_id]);
$alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
$alert_count = $alert_data['alert_count'] ?? 0;

// Fetch recent referrals
$referral_stmt = $pdo->prepare("SELECT referral_code, condition_description, created_at FROM referrals WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
$referral_stmt->execute([$user_id]);
$recent_referrals = $referral_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch today's stats
$today = date('Y-m-d');
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as today_referrals,
        SUM(CASE WHEN type = 'outgoing' THEN 1 ELSE 0 END) as outgoing,
        SUM(CASE WHEN type = 'incoming' THEN 1 ELSE 0 END) as incoming
    FROM referrals 
    WHERE DATE(created_at) = ? AND user_id = ?
");
$stats_stmt->execute([$today, $user_id]);
$stats_data = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$today_referrals = $stats_data['today_referrals'] ?? 0;
$outgoing_referrals = $stats_data['outgoing'] ?? 0;
$incoming_referrals = $stats_data['incoming'] ?? 0;

// Fetch capacity
$capacity_stmt = $pdo->prepare("SELECT available_capacity FROM capacity WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$capacity_stmt->execute([$user_id]);
$capacity_data = $capacity_stmt->fetch(PDO::FETCH_ASSOC);
$available_capacity = $capacity_data['available_capacity'] ?? 0;
?>