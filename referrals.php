<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle referral actions (accept/deny)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle accept/deny actions
    if (isset($_POST['action'])) {
        $referral_id = $_POST['referral_id'];
        $action = $_POST['action'];
        $feedback = trim($_POST['feedback'] ?? '');
        
        if ($action === 'accept') {
            $stmt = $pdo->prepare("UPDATE referrals SET status = 'accepted', responded_at = NOW() WHERE id = ? AND receiving_user_id = ?");
            $stmt->execute([$referral_id, $user_id]);
            
            // Create alert for the sender
            $referral_info = $pdo->prepare("SELECT r.user_id as sender_id, r.referral_code FROM referrals r WHERE r.id = ?");
            $referral_info->execute([$referral_id]);
            $ref_data = $referral_info->fetch(PDO::FETCH_ASSOC);
            
            $alert_message = "Your referral " . $ref_data['referral_code'] . " has been accepted by " . $user_name;
            $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'info')");
            $alert_stmt->execute([$ref_data['sender_id'], $alert_message]);
            
        } elseif ($action === 'deny') {
            $stmt = $pdo->prepare("UPDATE referrals SET status = 'declined', responded_at = NOW(), feedback = ? WHERE id = ? AND receiving_user_id = ?");
            $stmt->execute([$feedback, $referral_id, $user_id]);
            
            // Create alert for the sender
            $referral_info = $pdo->prepare("SELECT r.user_id as sender_id, r.referral_code FROM referrals r WHERE r.id = ?");
            $referral_info->execute([$referral_id]);
            $ref_data = $referral_info->fetch(PDO::FETCH_ASSOC);
            
            $alert_message = "Your referral " . $ref_data['referral_code'] . " has been declined by " . $user_name . ". Feedback: " . $feedback;
            $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'warning')");
            $alert_stmt->execute([$ref_data['sender_id'], $alert_message]);
        }
        
        header("Location: referrals.php");
        exit();
    }
    
    // Handle resend referral
    if (isset($_POST['resend_referral'])) {
        $original_referral_id = $_POST['original_referral_id'];
        $new_receiving_user_id = $_POST['new_receiving_user_id'];
        
        // Get original referral data
        $original_stmt = $pdo->prepare("SELECT * FROM referrals WHERE id = ? AND user_id = ?");
        $original_stmt->execute([$original_referral_id, $user_id]);
        $original_referral = $original_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($original_referral) {
            // Generate new referral code
            $new_referral_code = "REF" . date('ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            // Create new referral
            $resend_stmt = $pdo->prepare("INSERT INTO referrals (user_id, referral_code, patient_id, patient_name, patient_age, patient_gender, condition_description, symptoms, medical_history, current_medications, referring_doctor, referring_facility, receiving_user_id, specialty, urgency_level, additional_notes, status, original_referral_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            
            $resend_stmt->execute([
                $user_id, $new_referral_code, $original_referral['patient_id'], $original_referral['patient_name'],
                $original_referral['patient_age'], $original_referral['patient_gender'], $original_referral['condition_description'],
                $original_referral['symptoms'], $original_referral['medical_history'], $original_referral['current_medications'],
                $original_referral['referring_doctor'], $original_referral['referring_facility'], $new_receiving_user_id,
                $original_referral['specialty'], $original_referral['urgency_level'], $original_referral['additional_notes'],
                $original_referral_id
            ]);
            
            // Create alert for new receiving user
            $alert_message = "New referral received from " . $user_name . " - " . $original_referral['condition_description'];
            $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'urgent')");
            $alert_stmt->execute([$new_receiving_user_id, $alert_message]);
        }
        
        header("Location: referrals.php");
        exit();
    }
    
    // Handle edit referral
    if (isset($_POST['edit_referral'])) {
        $referral_id = $_POST['referral_id'];
        $patient_id = trim($_POST['patient_id']);
        $patient_name = trim($_POST['patient_name']);
        $patient_age = trim($_POST['patient_age']);
        $patient_gender = trim($_POST['patient_gender']);
        $condition = trim($_POST['condition']);
        $symptoms = trim($_POST['symptoms']);
        $medical_history = trim($_POST['medical_history']);
        $current_medications = trim($_POST['current_medications']);
        $referring_doctor = trim($_POST['referring_doctor']);
        $referring_facility = trim($_POST['referring_facility']);
        $specialty = trim($_POST['specialty']);
        $urgency_level = trim($_POST['urgency_level']);
        $additional_notes = trim($_POST['additional_notes']);
        
        // Update referral - allow editing of declined referrals
        $update_stmt = $pdo->prepare("UPDATE referrals SET patient_id = ?, patient_name = ?, patient_age = ?, patient_gender = ?, condition_description = ?, symptoms = ?, medical_history = ?, current_medications = ?, referring_doctor = ?, referring_facility = ?, specialty = ?, urgency_level = ?, additional_notes = ? WHERE id = ? AND user_id = ?");
        
        if ($update_stmt->execute([$patient_id, $patient_name, $patient_age, $patient_gender, $condition, $symptoms, $medical_history, $current_medications, $referring_doctor, $referring_facility, $specialty, $urgency_level, $additional_notes, $referral_id, $user_id])) {
            // Success - redirect to refresh the page
            header("Location: referrals.php");
            exit();
        }
    }
}

// Fetch data for the page
require_once 'includes/referralsDashboard/referrals_data.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Referrals Dashboard</title>
    <link rel="stylesheet" href="styles/referrals/referrals.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <?php include 'includes/referralsDashboard/page_header.php'; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <?php include 'includes/referralsDashboard/stats_cards.php'; ?>
            </div>

            <!-- Incoming Referrals Section -->
            <div class="section">
                <h3 class="section-title">Incoming Referrals - Awaiting Your Response</h3>
                <?php include 'includes/referralsDashboard/incoming/incoming_referrals_table.php'; ?>
            </div>

            <!-- Outgoing Referrals Section -->
            <div class="section">
                <h3 class="section-title">Your Sent Referrals</h3>
                <?php include 'includes/referralsDashboard/outgoing/outgoing_referrals_table.php'; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="write_referral.php" class="btn btn-primary">Write New Referral</a>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script src="scripts/referrals/referrals.js"></script>
</body>
</html>