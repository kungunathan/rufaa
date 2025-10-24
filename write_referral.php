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

// Fetch all registered users (except current user) for receiving referrals
$users_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id != ? AND is_active = TRUE");
$users_stmt->execute([$user_id]);
$available_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    $receiving_user_id = trim($_POST['receiving_user_id']);
    $specialty = trim($_POST['specialty']);
    $urgency_level = trim($_POST['urgency_level']);
    $additional_notes = trim($_POST['additional_notes']);
    $consent = isset($_POST['consent']) ? true : false;

    // Validate required fields
    if (empty($patient_id) || empty($patient_name) || empty($patient_age) || empty($patient_gender) || 
        empty($condition) || empty($referring_doctor) || empty($referring_facility) || 
        empty($receiving_user_id) || empty($specialty) || empty($urgency_level) || !$consent) {
        $error = "Please fill in all required fields and provide consent.";
    } else {
        // Generate referral code
        $referral_code = "REF" . date('ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Insert referral with status 'pending'
        $stmt = $pdo->prepare("INSERT INTO referrals (user_id, referral_code, patient_id, patient_name, patient_age, patient_gender, condition_description, symptoms, medical_history, current_medications, referring_doctor, referring_facility, receiving_user_id, specialty, urgency_level, additional_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt->execute([$user_id, $referral_code, $patient_id, $patient_name, $patient_age, $patient_gender, $condition, $symptoms, $medical_history, $current_medications, $referring_doctor, $referring_facility, $receiving_user_id, $specialty, $urgency_level, $additional_notes])) {
            
            // Create alert for the receiving user
            $referral_id = $pdo->lastInsertId();
            $alert_message = "New referral received from " . $user_name . " - " . $condition;
            
            $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'urgent')");
            $alert_stmt->execute([$receiving_user_id, $alert_message]);
            
            $success = "Referral submitted successfully! Referral Code: " . $referral_code . ". Waiting for acceptance.";
            
            // Clear form fields
            $_POST = array();
        } else {
            $error = "Failed to submit referral. Please try again.";
        }
    }
}

// Fetch user alerts count
$alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = FALSE");
$alert_stmt->execute([$user_id]);
$alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
$alert_count = $alert_data['alert_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Write a Referral</title>
    <link rel="stylesheet" href="styles/writeReferral.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Write a Referral</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php include 'includes/write_referral/form.php'; ?>   
        </div>
    </div>
</body>
</html>