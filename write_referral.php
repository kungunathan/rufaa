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
    <link rel="stylesheet" href="css/writeReferral.css">
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
    </script>
    <script src="js/writeReferral.js"></script>
</body>
</html>