<?php
// Fetch user alerts count
$alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = FALSE");
$alert_stmt->execute([$user_id]);
$alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
$alert_count = $alert_data['alert_count'] ?? 0;

// Fetch incoming referrals (pending acceptance)
$incoming_stmt = $pdo->prepare("
    SELECT r.*, u.first_name as sender_first_name, u.last_name as sender_last_name 
    FROM referrals r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.receiving_user_id = ? AND r.status = 'pending' 
    ORDER BY r.created_at DESC
");
$incoming_stmt->execute([$user_id]);
$incoming_referrals = $incoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch outgoing referrals (sent by current user)
$outgoing_stmt = $pdo->prepare("
    SELECT r.*, u.first_name as receiver_first_name, u.last_name as receiver_last_name 
    FROM referrals r 
    JOIN users u ON r.receiving_user_id = u.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$outgoing_stmt->execute([$user_id]);
$outgoing_referrals = $outgoing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users for resending declined referrals
$users_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id != ? AND is_active = TRUE");
$users_stmt->execute([$user_id]);
$available_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_referrals = count($outgoing_referrals);
$pending_incoming = count($incoming_referrals);
$accepted_referrals = array_filter($outgoing_referrals, function($ref) { return $ref['status'] === 'accepted'; });
$declined_referrals = array_filter($outgoing_referrals, function($ref) { return $ref['status'] === 'declined'; });
?>