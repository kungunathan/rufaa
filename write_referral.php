<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and active
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Validate user session and check if user is still active
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Check if user exists and is active
$user_stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ? AND is_active = 1");
$user_stmt->execute([$user_id]);

if ($user_stmt->rowCount() === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear remember token from database
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $update_stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $update_stmt->execute([$user_id]);
        setcookie('remember_token', '', time() - 3600, "/");
    }
    
    // Clear session
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch data
try {
    $alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $alert_stmt->execute([$user_id]);
    $alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    $alert_count = $alert_data['alert_count'] ?? 0;

    $users_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id != ? AND is_active = 1");
    $users_stmt->execute([$user_id]);
    $available_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Write referral data fetch error: " . $e->getMessage());
    $alert_count = 0;
    $available_users = [];
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = trim($_POST['patient_id']);
    $patient_name = trim($_POST['patient_name']);
    $patient_age = filter_input(INPUT_POST, 'patient_age', FILTER_VALIDATE_INT);
    $patient_gender = $_POST['patient_gender'];
    $condition = trim($_POST['condition']);
    $symptoms = trim($_POST['symptoms']);
    $medical_history = trim($_POST['medical_history']);
    $current_medications = trim($_POST['current_medications']);
    $referring_doctor = trim($_POST['referring_doctor']);
    $referring_facility = trim($_POST['referring_facility']);
    $receiving_user_id = filter_input(INPUT_POST, 'receiving_user_id', FILTER_VALIDATE_INT);
    $specialty = trim($_POST['specialty']);
    $urgency_level = $_POST['urgency_level'];
    $additional_notes = trim($_POST['additional_notes']);
    $consent = isset($_POST['consent']) ? true : false;

    // Validate required fields
    if (empty($patient_id) || empty($patient_name) || empty($patient_age) || empty($patient_gender) ||
        empty($condition) || empty($referring_doctor) || empty($referring_facility) ||
        empty($receiving_user_id) || empty($specialty) || empty($urgency_level) || !$consent) {
        $error = "Please fill in all required fields and provide consent.";
    } elseif ($patient_age < 0 || $patient_age > 150) {
        $error = "Please enter a valid age.";
    } else {
        try {
            // Generate unique referral code
            $referral_code = "REF" . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            
            // Insert referral
            $stmt = $pdo->prepare("INSERT INTO referrals (
                user_id, referral_code, patient_id, patient_name, patient_age, patient_gender,
                condition_description, symptoms, medical_history, current_medications,
                referring_doctor, referring_facility, receiving_user_id, specialty,
                urgency_level, additional_notes, status, type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'outgoing')");
            
            if ($stmt->execute([
                $user_id, $referral_code, $patient_id, $patient_name, $patient_age, $patient_gender,
                $condition, $symptoms, $medical_history, $current_medications,
                $referring_doctor, $referring_facility, $receiving_user_id, $specialty,
                $urgency_level, $additional_notes
            ])) {
                // Create alert for receiving user
                $alert_message = "New referral received from " . $user_name . " - " . $condition;
                $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'urgent')");
                $alert_stmt->execute([$receiving_user_id, $alert_message]);
                
                $success = "Referral submitted successfully! Referral Code: " . $referral_code . ". Waiting for acceptance.";
                
                // Clear form data
                $_POST = array();
            } else {
                $error = "Failed to submit referral. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Referral submission error: " . $e->getMessage());
            $error = "Database error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Write Referral</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            padding: 0 20px;
            color: white;
        }

        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        .nav-link span {
            margin-left: 10px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            color: #555;
        }

        .alert-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Form Container */
        .referral-form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f1f1f1;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            font-weight: 600;
        }

        /* Form Styles */
        .form-row {
            display: flex;
            gap: 25px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: #999;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        /* Checkbox Styles */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .checkbox-input {
            margin-top: 3px;
            width: 18px;
            height: 18px;
        }

        .checkbox-label {
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f1f1f1;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: transparent;
            color: #7f8c8d;
            border: 2px solid #bdc3c7;
        }

        .btn-secondary:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Field Error Styles */
        .field-error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .input-error {
            border-color: #e74c3c !important;
        }

        .input-success {
            border-color: #27ae60 !important;
        }

        /* Urgency Level Styles */
        .urgency-options {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .urgency-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .urgency-option:hover {
            border-color: #667eea;
            background-color: #f8f9fa;
        }

        .urgency-option.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .urgency-routine.selected {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .urgency-urgent.selected {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }

        .urgency-emergency.selected {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        /* User Search Styles */
        .user-search-container {
            position: relative;
        }

        .user-search-input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .user-search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .user-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e1e5e9;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-result {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.3s ease;
        }

        .user-result:hover {
            background-color: #f8f9fa;
        }

        .user-result:last-child {
            border-bottom: none;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .user-email {
            color: #666;
            font-size: 12px;
        }

        .selected-user {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .selected-user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-user-details .user-name {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .selected-user-details .user-email {
            font-size: 14px;
            color: #666;
        }

        .remove-selection {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .remove-selection:hover {
            background: #c0392b;
        }

        .no-results {
            padding: 12px;
            color: #666;
            text-align: center;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .logo span, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .urgency-options {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                min-width: auto;
                width: 100%;
            }

            .selected-user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .remove-selection {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .referral-form-container {
                padding: 20px;
            }
            
            .form-section {
                margin-bottom: 30px;
                padding-bottom: 20px;
            }
            
            .checkbox-group {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">Rufaa</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="referrals.php" class="nav-link">
                        <span>Referrals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="write_referral.php" class="nav-link active">
                        <span>Write Referral</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="report_issue.php" class="nav-link">
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

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Write New Referral</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge" title="<?php echo $alert_count; ?> unread alerts"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Referral Form -->
            <div class="referral-form-container">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="referralForm">
                    <!-- Patient Information Section -->
                    <div class="form-section">
                        <h2 class="section-title">Patient Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="patient_id">Patient ID</label>
                                <input type="text" id="patient_id" name="patient_id" class="form-input" 
                                       placeholder="Enter patient ID" 
                                       value="<?php echo htmlspecialchars($_POST['patient_id'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error">Patient ID is required</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label required" for="patient_name">Patient Name</label>
                                <input type="text" id="patient_name" name="patient_name" class="form-input" 
                                       placeholder="Enter patient full name" 
                                       value="<?php echo htmlspecialchars($_POST['patient_name'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error">Patient name is required</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="patient_age">Age</label>
                                <input type="number" id="patient_age" name="patient_age" class="form-input" 
                                       placeholder="Enter age" 
                                       value="<?php echo htmlspecialchars($_POST['patient_age'] ?? ''); ?>" 
                                       min="0" max="150" 
                                       required
                                       oninput="validateAge(this)">
                                <div class="field-error">Please enter a valid age (0-150)</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label required" for="patient_gender">Gender</label>
                                <select id="patient_gender" name="patient_gender" class="form-select" required onchange="validateField(this)">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo (isset($_POST['patient_gender']) && $_POST['patient_gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($_POST['patient_gender']) && $_POST['patient_gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (isset($_POST['patient_gender']) && $_POST['patient_gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="field-error">Please select gender</div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Information Section -->
                    <div class="form-section">
                        <h2 class="section-title">Medical Information</h2>
                        <div class="form-group">
                            <label class="form-label required" for="condition">Primary Condition</label>
                            <input type="text" id="condition" name="condition" class="form-input" 
                                   placeholder="Enter primary condition or diagnosis" 
                                   value="<?php echo htmlspecialchars($_POST['condition'] ?? ''); ?>" 
                                   required
                                   oninput="validateField(this)">
                            <div class="field-error">Primary condition is required</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="symptoms">Symptoms</label>
                            <textarea id="symptoms" name="symptoms" class="form-textarea" 
                                      placeholder="Describe symptoms, onset, duration, and severity"
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['symptoms'] ?? ''); ?></textarea>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medical_history">Medical History</label>
                            <textarea id="medical_history" name="medical_history" class="form-textarea" 
                                      placeholder="Relevant medical history, past surgeries, chronic conditions"
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="current_medications">Current Medications</label>
                            <textarea id="current_medications" name="current_medications" class="form-textarea" 
                                      placeholder="List current medications with dosages"
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['current_medications'] ?? ''); ?></textarea>
                            <div class="field-error"></div>
                        </div>
                    </div>

                    <!-- Referral Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">Referral Details</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="referring_doctor">Referring Doctor</label>
                                <input type="text" id="referring_doctor" name="referring_doctor" class="form-input" 
                                       placeholder="Enter your name" 
                                       value="<?php echo htmlspecialchars($_POST['referring_doctor'] ?? $user_name); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error">Referring doctor name is required</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label required" for="referring_facility">Referring Facility</label>
                                <input type="text" id="referring_facility" name="referring_facility" class="form-input" 
                                       placeholder="Enter facility name" 
                                       value="<?php echo htmlspecialchars($_POST['referring_facility'] ?? ''); ?>" 
                                       required
                                       oninput="validateField(this)">
                                <div class="field-error">Referring facility is required</div>
                            </div>
                        </div>
                        
                        <!-- User Search Interface -->
                        <div class="form-group">
                            <label class="form-label required">Refer To User</label>
                            <div class="user-search-container">
                                <input type="text" id="userSearch" class="user-search-input" 
                                       placeholder="Search for users by name or email..."
                                       oninput="searchUsers(this.value)">
                                <div class="search-icon">🔍</div>
                                <div id="userResults" class="user-results"></div>
                                <input type="hidden" id="receiving_user_id" name="receiving_user_id" value="<?php echo htmlspecialchars($_POST['receiving_user_id'] ?? ''); ?>" required>
                            </div>
                            <div id="selectedUser" class="selected-user" style="display: none;">
                                <div class="selected-user-info">
                                    <div class="selected-user-details">
                                        <div class="user-name" id="selectedUserName"></div>
                                        <div class="user-email" id="selectedUserEmail"></div>
                                    </div>
                                    <button type="button" class="remove-selection" onclick="clearUserSelection()">Remove</button>
                                </div>
                            </div>
                            <div class="field-error">Please select a receiving user</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="specialty">Required Specialty</label>
                            <select id="specialty" name="specialty" class="form-select" required onchange="validateField(this)">
                                <option value="">Select specialty</option>
                                <option value="cardiology" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == 'cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="orthopedics" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == 'orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                <option value="neurology" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == 'neurology') ? 'selected' : ''; ?>>Neurology</option>
                                <option value="surgery" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == 'surgery') ? 'selected' : ''; ?>>Surgery</option>
                                <option value="internal" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == 'internal') ? 'selected' : ''; ?>>Internal Medicine</option>
                            </select>
                            <div class="field-error">Please select a specialty</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Urgency Level</label>
                            <div class="urgency-options">
                                <div class="urgency-option urgency-routine <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'routine') ? 'selected' : ''; ?>" 
                                     onclick="selectUrgency('routine')">
                                    Routine
                                </div>
                                <div class="urgency-option urgency-urgent <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'urgent') ? 'selected' : ''; ?>" 
                                     onclick="selectUrgency('urgent')">
                                    Urgent
                                </div>
                                <div class="urgency-option urgency-emergency <?php echo (isset($_POST['urgency_level']) && $_POST['urgency_level'] == 'emergency') ? 'selected' : ''; ?>" 
                                     onclick="selectUrgency('emergency')">
                                    Emergency
                                </div>
                            </div>
                            <input type="hidden" id="urgency_level" name="urgency_level" value="<?php echo htmlspecialchars($_POST['urgency_level'] ?? ''); ?>" required>
                            <div class="field-error">Please select urgency level</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="additional_notes">Additional Notes</label>
                            <textarea id="additional_notes" name="additional_notes" class="form-textarea" 
                                      placeholder="Any additional information for the receiving doctor, specific concerns, or special instructions"
                                      oninput="validateTextarea(this)"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                            <div class="field-error"></div>
                        </div>
                    </div>

                    <!-- Consent Section -->
                    <div class="form-section">
                        <h2 class="section-title">Consent & Authorization</h2>
                        <div class="checkbox-group">
                            <input type="checkbox" id="consent" name="consent" class="checkbox-input" 
                                   required <?php echo (isset($_POST['consent']) && $_POST['consent']) ? 'checked' : ''; ?>>
                            <label for="consent" class="checkbox-label">
                                I confirm that I have obtained the patient's consent for this referral and that all information provided is accurate to the best of my knowledge. I understand that this referral will be shared with the selected healthcare provider.
                            </label>
                        </div>
                        <div class="field-error">You must provide consent to submit the referral</div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="submitButton">Submit Referral</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Available users data from PHP
        const availableUsers = <?php echo json_encode($available_users); ?>;

        // Field validation functions
        function validateField(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            if (field.value.trim() === '') {
                showError(field, errorElement, field.labels[0].textContent + ' is required');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validateAge(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            const age = parseInt(field.value);
            
            if (field.value.trim() === '') {
                showError(field, errorElement, 'Age is required');
                return false;
            } else if (isNaN(age) || age < 0 || age > 150) {
                showError(field, errorElement, 'Please enter a valid age (0-150)');
                return false;
            } else {
                hideError(field, errorElement);
                return true;
            }
        }

        function validateTextarea(field) {
            const errorElement = field.parentElement.querySelector('.field-error');
            hideError(field, errorElement);
            return true;
        }

        function showError(field, errorElement, message) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            field.classList.add('input-error');
            field.classList.remove('input-success');
        }

        function hideError(field, errorElement) {
            errorElement.style.display = 'none';
            field.classList.remove('input-error');
            field.classList.add('input-success');
        }

        // Urgency level selection
        function selectUrgency(level) {
            const options = document.querySelectorAll('.urgency-option');
            options.forEach(option => {
                option.classList.remove('selected');
            });
            
            const selectedOption = document.querySelector(`.urgency-${level}`);
            selectedOption.classList.add('selected');
            
            const hiddenInput = document.getElementById('urgency_level');
            hiddenInput.value = level;
            
            // Validate the field
            validateField(hiddenInput);
        }

        // User search functionality
        function searchUsers(query) {
            const resultsContainer = document.getElementById('userResults');
            const userSearchInput = document.getElementById('userSearch');
            
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            const filteredUsers = availableUsers.filter(user => {
                const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
                const email = user.email.toLowerCase();
                const searchTerm = query.toLowerCase();
                
                return fullName.includes(searchTerm) || email.includes(searchTerm);
            });
            
            displayUserResults(filteredUsers);
        }

        function displayUserResults(users) {
            const resultsContainer = document.getElementById('userResults');
            
            if (users.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">No users found</div>';
            } else {
                resultsContainer.innerHTML = users.map(user => `
                    <div class="user-result" onclick="selectUser(${user.id}, '${user.first_name} ${user.last_name}', '${user.email}')">
                        <div class="user-name">${user.first_name} ${user.last_name}</div>
                        <div class="user-email">${user.email}</div>
                    </div>
                `).join('');
            }
            
            resultsContainer.style.display = 'block';
        }

        function selectUser(userId, userName, userEmail) {
            const receivingUserIdInput = document.getElementById('receiving_user_id');
            const userSearchInput = document.getElementById('userSearch');
            const selectedUserDiv = document.getElementById('selectedUser');
            const selectedUserName = document.getElementById('selectedUserName');
            const selectedUserEmail = document.getElementById('selectedUserEmail');
            const resultsContainer = document.getElementById('userResults');
            
            // Set the hidden input value
            receivingUserIdInput.value = userId;
            
            // Update selected user display
            selectedUserName.textContent = userName;
            selectedUserEmail.textContent = userEmail;
            selectedUserDiv.style.display = 'block';
            
            // Clear search input and hide results
            userSearchInput.value = '';
            resultsContainer.style.display = 'none';
            
            // Validate the field
            validateField(receivingUserIdInput);
        }

        function clearUserSelection() {
            const receivingUserIdInput = document.getElementById('receiving_user_id');
            const selectedUserDiv = document.getElementById('selectedUser');
            const userSearchInput = document.getElementById('userSearch');
            
            receivingUserIdInput.value = '';
            selectedUserDiv.style.display = 'none';
            userSearchInput.value = '';
            
            // Clear validation
            const errorElement = receivingUserIdInput.parentElement.querySelector('.field-error');
            hideError(receivingUserIdInput, errorElement);
        }

        // Consent validation
        function validateConsent() {
            const consentCheckbox = document.getElementById('consent');
            const errorElement = document.querySelector('#consent').closest('.form-section').querySelector('.field-error');
            
            if (!consentCheckbox.checked) {
                showError(consentCheckbox, errorElement, 'You must provide consent to submit the referral');
                return false;
            } else {
                hideError(consentCheckbox, errorElement);
                return true;
            }
        }

        // Form submission validation
        document.getElementById('referralForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate all required fields
            const requiredFields = document.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (field.type === 'checkbox') {
                    if (!validateConsent()) isValid = false;
                } else if (field.type === 'number') {
                    if (!validateAge(field)) isValid = false;
                } else if (field.tagName === 'SELECT') {
                    if (!validateField(field)) isValid = false;
                } else if (field.id === 'receiving_user_id') {
                    if (!validateField(field)) isValid = false;
                } else {
                    if (!validateField(field)) isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.field-error[style="display: block;"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                // Show loading state
                const submitButton = document.getElementById('submitButton');
                submitButton.innerHTML = 'Submitting Referral...';
                submitButton.disabled = true;
            }
        });

        // Auto-close messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });

            // Add fade-in animation to sections
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.1}s`;
                section.classList.add('fade-in');
            });

            // Initialize urgency level if already selected
            const urgencyLevel = document.getElementById('urgency_level').value;
            if (urgencyLevel) {
                selectUrgency(urgencyLevel);
            }

            // Initialize selected user if already set
            const receivingUserId = document.getElementById('receiving_user_id').value;
            if (receivingUserId) {
                const selectedUser = availableUsers.find(user => user.id == receivingUserId);
                if (selectedUser) {
                    selectUser(selectedUser.id, `${selectedUser.first_name} ${selectedUser.last_name}`, selectedUser.email);
                }
            }

            // Close user results when clicking outside
            document.addEventListener('click', function(e) {
                const userSearchContainer = document.querySelector('.user-search-container');
                if (!userSearchContainer.contains(e.target)) {
                    document.getElementById('userResults').style.display = 'none';
                }
            });
        });

        // Add fade-in animation style
        const style = document.createElement('style');
        style.textContent = `
            .fade-in {
                animation: fadeIn 0.6s ease-in-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>