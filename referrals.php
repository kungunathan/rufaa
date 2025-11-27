<?php
session_start();
require_once 'config/database.php';

// Include DomPDF library
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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

// Handle referral actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Existing referral actions (accept, deny, resend, edit)
    if (isset($_POST['action'])) {
        $referral_id = filter_input(INPUT_POST, 'referral_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'];
        $feedback = trim($_POST['feedback'] ?? '');
        
        if ($referral_id && in_array($action, ['accept', 'deny'])) {
            try {
                if ($action === 'accept') {
                    $stmt = $pdo->prepare("UPDATE referrals SET status = 'accepted', responded_at = NOW() WHERE id = ? AND receiving_user_id = ? AND status = 'pending'");
                    $stmt->execute([$referral_id, $user_id]);
                    
                    // Create alert for the sender
                    $referral_info = $pdo->prepare("SELECT r.user_id as sender_id, r.referral_code FROM referrals r WHERE r.id = ?");
                    $referral_info->execute([$referral_id]);
                    $ref_data = $referral_info->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ref_data) {
                        $alert_message = "Your referral " . $ref_data['referral_code'] . " has been accepted by " . $user_name;
                        $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'info')");
                        $alert_stmt->execute([$ref_data['sender_id'], $alert_message]);
                    }
                    
                } elseif ($action === 'deny') {
                    $stmt = $pdo->prepare("UPDATE referrals SET status = 'declined', responded_at = NOW(), feedback = ? WHERE id = ? AND receiving_user_id = ? AND status = 'pending'");
                    $stmt->execute([$feedback, $referral_id, $user_id]);
                    
                    // Create alert for the sender
                    $referral_info = $pdo->prepare("SELECT r.user_id as sender_id, r.referral_code FROM referrals r WHERE r.id = ?");
                    $referral_info->execute([$referral_id]);
                    $ref_data = $referral_info->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ref_data) {
                        $alert_message = "Your referral " . $ref_data['referral_code'] . " has been declined by " . $user_name . ". Feedback: " . $feedback;
                        $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'warning')");
                        $alert_stmt->execute([$ref_data['sender_id'], $alert_message]);
                    }
                }
                
                $_SESSION['success_message'] = "Referral " . $action . "ed successfully.";
            } catch (PDOException $e) {
                error_log("Referral action error: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to process referral action.";
            }
        }
        
        header("Location: referrals.php");
        exit();
    }
    
    // Handle resend referral
    if (isset($_POST['resend_referral'])) {
        $original_referral_id = filter_input(INPUT_POST, 'original_referral_id', FILTER_VALIDATE_INT);
        $new_receiving_user_id = filter_input(INPUT_POST, 'new_receiving_user_id', FILTER_VALIDATE_INT);
        
        if ($original_referral_id && $new_receiving_user_id) {
            try {
                // Get original referral data
                $original_stmt = $pdo->prepare("SELECT * FROM referrals WHERE id = ? AND user_id = ?");
                $original_stmt->execute([$original_referral_id, $user_id]);
                $original_referral = $original_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($original_referral) {
                    // Generate new referral code
                    $new_referral_code = "REF" . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
                    
                    // Create new referral
                    $resend_stmt = $pdo->prepare("INSERT INTO referrals (
                        user_id, referral_code, condition_description, type, patient_id, patient_name, 
                        patient_age, patient_gender, symptoms, medical_history, current_medications, 
                        referring_doctor, referring_facility, receiving_user_id, specialty, 
                        urgency_level, additional_notes, status, original_referral_id
                    ) VALUES (?, ?, ?, 'outgoing', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                    
                    $resend_stmt->execute([
                        $user_id, $new_referral_code, $original_referral['condition_description'],
                        $original_referral['patient_id'], $original_referral['patient_name'],
                        $original_referral['patient_age'], $original_referral['patient_gender'],
                        $original_referral['symptoms'], $original_referral['medical_history'],
                        $original_referral['current_medications'], $original_referral['referring_doctor'],
                        $original_referral['referring_facility'], $new_receiving_user_id,
                        $original_referral['specialty'], $original_referral['urgency_level'],
                        $original_referral['additional_notes'], $original_referral_id
                    ]);
                    
                    // Create alert for new receiving user
                    $alert_message = "New referral received from " . $user_name . " - " . $original_referral['condition_description'];
                    $alert_stmt = $pdo->prepare("INSERT INTO alerts (user_id, message, alert_type) VALUES (?, ?, 'urgent')");
                    $alert_stmt->execute([$new_receiving_user_id, $alert_message]);
                    
                    $_SESSION['success_message'] = "Referral resent successfully.";
                }
            } catch (PDOException $e) {
                error_log("Resend referral error: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to resend referral.";
            }
        }
        
        header("Location: referrals.php");
        exit();
    }
    
    // Handle edit referral
    if (isset($_POST['edit_referral'])) {
        $referral_id = filter_input(INPUT_POST, 'referral_id', FILTER_VALIDATE_INT);
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
        $specialty = trim($_POST['specialty']);
        $urgency_level = $_POST['urgency_level'];
        $additional_notes = trim($_POST['additional_notes']);
        
        if ($referral_id && $patient_id && $patient_name && $patient_age && $patient_gender && $condition && $referring_doctor && $referring_facility && $specialty && $urgency_level) {
            try {
                $update_stmt = $pdo->prepare("UPDATE referrals SET 
                    patient_id = ?, patient_name = ?, patient_age = ?, patient_gender = ?, 
                    condition_description = ?, symptoms = ?, medical_history = ?, 
                    current_medications = ?, referring_doctor = ?, referring_facility = ?, 
                    specialty = ?, urgency_level = ?, additional_notes = ? 
                    WHERE id = ? AND user_id = ? AND status = 'declined'");
                
                if ($update_stmt->execute([
                    $patient_id, $patient_name, $patient_age, $patient_gender, $condition, 
                    $symptoms, $medical_history, $current_medications, $referring_doctor, 
                    $referring_facility, $specialty, $urgency_level, $additional_notes, 
                    $referral_id, $user_id
                ])) {
                    $_SESSION['success_message'] = "Referral updated successfully.";
                }
            } catch (PDOException $e) {
                error_log("Edit referral error: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to update referral.";
            }
        }
        
        header("Location: referrals.php");
        exit();
    }
    
    // Handle report generation and PDF download
    if (isset($_POST['generate_report'])) {
        $report_type = $_POST['report_type'] ?? 'user_activity';
        $date_range = $_POST['date_range'] ?? 'all_time';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        try {
            // Calculate date range conditions
            $date_conditions = "";
            $params = [$user_id, $user_id]; // For both user_id and receiving_user_id
            
             switch ($date_range) {
        case 'last_week':
            $date_conditions = " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'last_month':
            $date_conditions = " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'last_quarter':
            $date_conditions = " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $date_conditions = " AND DATE(r.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            break;
        // 'all_time' has no date conditions
    }
            
            // Fetch comprehensive report data
            $report_data = [];
            
            // User referral activity (both sent and received)
            $activity_stmt = $pdo->prepare("
                SELECT 
                    'outgoing' as type,
                    r.referral_code,
                    r.patient_name,
                    r.condition_description,
                    r.status,
                    r.urgency_level,
                    r.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as related_user_name,
                    r.feedback,
                    r.responded_at
                FROM referrals r 
                JOIN users u ON r.receiving_user_id = u.id 
                WHERE r.user_id = ? $date_conditions
                
                UNION ALL
                
                SELECT 
                    'incoming' as type,
                    r.referral_code,
                    r.patient_name,
                    r.condition_description,
                    r.status,
                    r.urgency_level,
                    r.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as related_user_name,
                    r.feedback,
                    r.responded_at
                FROM referrals r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.receiving_user_id = ? $date_conditions
                ORDER BY created_at DESC
            ");
            
            $activity_stmt->execute($params);
            $report_data['referral_activity'] = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary statistics
            $summary_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_referrals,
                    SUM(CASE WHEN r.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN r.status = 'declined' THEN 1 ELSE 0 END) as declined,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN r.urgency_level = 'emergency' THEN 1 ELSE 0 END) as emergency,
                    SUM(CASE WHEN r.urgency_level = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN r.urgency_level = 'routine' THEN 1 ELSE 0 END) as routine,
                    SUM(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN r.receiving_user_id = ? THEN 1 ELSE 0 END) as received_count
                FROM referrals r
                WHERE (r.user_id = ? OR r.receiving_user_id = ?) $date_conditions
            ");
            
            $summary_params = array_merge([$user_id, $user_id, $user_id, $user_id], array_slice($params, 2));
            $summary_stmt->execute($summary_params);
            $report_data['summary'] = $summary_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate PDF using DomPDF
            // Configure DomPDF options
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            $dompdf = new Dompdf($options);
            
            // Generate HTML content for PDF
            $html = generateReportHTML($report_data, $report_type, $date_range, $start_date, $end_date, $user_name);
            
            // Load HTML content
            $dompdf->loadHtml($html);
            
            // Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait');
            
            // Render PDF
            $dompdf->render();
            
            // Generate filename
            $filename = "referral_report_" . date('Y-m-d_H-i-s') . ".pdf";
            
            // Output PDF for download
            $dompdf->stream($filename, [
                'Attachment' => true
            ]);
            
            exit();
            
        } catch (PDOException $e) {
            error_log("Report generation error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to generate report: " . $e->getMessage();
            header("Location: referrals.php");
            exit();
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to generate PDF: " . $e->getMessage();
            header("Location: referrals.php");
            exit();
        }
    }
}

// Generate HTML content for the PDF report
function generateReportHTML($report_data, $report_type, $date_range, $start_date, $end_date, $user_name) {
    $report_type_labels = [
        'user_activity' => 'User Referral Activity Report',
        'referral_summary' => 'Referral Summary Report', 
        'detailed_analysis' => 'Detailed Referral Analysis Report'
    ];
    
    $date_range_labels = [
        'all_time' => 'All Time',
        'last_week' => 'Last 7 Days',
        'last_month' => 'Last 30 Days',
        'last_quarter' => 'Last 3 Months',
        'custom' => 'Custom Range'
    ];
    
    $date_range_text = $date_range_labels[$date_range] ?? 'All Time';
    if ($date_range === 'custom' && $start_date && $end_date) {
        $date_range_text = "From " . date('M j, Y', strtotime($start_date)) . " to " . date('M j, Y', strtotime($end_date));
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Referral Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #667eea;
            }
            .header h1 {
                color: #2c3e50;
                margin-bottom: 10px;
                font-size: 24px;
            }
            .meta-info {
                color: #7f8c8d;
                font-size: 14px;
                margin-bottom: 10px;
            }
            .section {
                margin-bottom: 25px;
            }
            .section h2 {
                color: #2c3e50;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 1px solid #ecf0f1;
                font-size: 18px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            .stat-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
                border-left: 4px solid #667eea;
            }
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #667eea;
                display: block;
            }
            .stat-label {
                font-size: 12px;
                color: #7f8c8d;
                text-transform: uppercase;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
                font-size: 12px;
            }
            th {
                background-color: #667eea;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .status-badge {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
                display: inline-block;
            }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-accepted { background: #d4edda; color: #155724; }
            .status-declined { background: #f8d7da; color: #721c24; }
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ecf0f1;
                text-align: center;
                color: #7f8c8d;
                font-size: 12px;
            }
            .no-data {
                text-align: center;
                color: #7f8c8d;
                font-style: italic;
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($report_type_labels[$report_type] ?? 'Referral Report'); ?></h1>
            <div class="meta-info">
                Generated for: <?php echo htmlspecialchars($user_name); ?><br>
                Date Range: <?php echo htmlspecialchars($date_range_text); ?><br>
                Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
            </div>
        </div>
        
        <?php if (isset($report_data['summary'])): ?>
        <div class="section">
            <h2>Summary Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['total_referrals'] ?? 0; ?></span>
                    <span class="stat-label">Total Referrals</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['sent_count'] ?? 0; ?></span>
                    <span class="stat-label">Sent</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['received_count'] ?? 0; ?></span>
                    <span class="stat-label">Received</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['accepted'] ?? 0; ?></span>
                    <span class="stat-label">Accepted</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['declined'] ?? 0; ?></span>
                    <span class="stat-label">Declined</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $report_data['summary']['pending'] ?? 0; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($report_data['referral_activity']) && !empty($report_data['referral_activity'])): ?>
        <div class="section">
            <h2>Referral Activity Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Referral Code</th>
                        <th>Patient</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Urgency</th>
                        <th>Related User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['referral_activity'] as $activity): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></td>
                        <td><?php echo $activity['type'] === 'outgoing' ? 'Sent' : 'Received'; ?></td>
                        <td><?php echo htmlspecialchars($activity['referral_code']); ?></td>
                        <td><?php echo htmlspecialchars($activity['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($activity['condition_description']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $activity['status']; ?>">
                                <?php echo ucfirst($activity['status']); ?>
                            </span>
                        </td>
                        <td><?php echo ucfirst($activity['urgency_level']); ?></td>
                        <td><?php echo htmlspecialchars($activity['related_user_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="section">
            <h2>Referral Activity</h2>
            <div class="no-data">No referral activity found for the selected criteria.</div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Generated by Rufaa Referral System</p>
            <p>Confidential - For authorized use only</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Fetch data for the page
try {
    // Fetch user alerts count
    $alert_stmt = $pdo->prepare("SELECT COUNT(*) as alert_count FROM alerts WHERE user_id = ? AND is_read = 0");
    $alert_stmt->execute([$user_id]);
    $alert_data = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    $alert_count = $alert_data['alert_count'] ?? 0;

    // Fetch incoming referrals (pending acceptance)
    $incoming_stmt = $pdo->prepare("
        SELECT r.*, u.first_name as sender_first_name, u.last_name as sender_last_name 
        FROM referrals r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.receiving_user_id = ? AND r.status = 'pending' 
        ORDER BY 
            CASE r.urgency_level 
                WHEN 'emergency' THEN 1 
                WHEN 'urgent' THEN 2 
                WHEN 'routine' THEN 3 
            END,
            r.created_at DESC
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

    // Fetch all active users for resending declined referrals
    $users_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id != ? AND is_active = 1");
    $users_stmt->execute([$user_id]);
    $available_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats - FIXED: Use proper database queries for accurate counts
    $total_sent_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM referrals WHERE user_id = ?");
    $total_sent_stmt->execute([$user_id]);
    $total_sent = $total_sent_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $accepted_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM referrals WHERE user_id = ? AND status = 'accepted'");
    $accepted_stmt->execute([$user_id]);
    $accepted_count = $accepted_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $declined_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM referrals WHERE user_id = ? AND status = 'declined'");
    $declined_stmt->execute([$user_id]);
    $declined_count = $declined_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $pending_incoming_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM referrals WHERE receiving_user_id = ? AND status = 'pending'");
    $pending_incoming_stmt->execute([$user_id]);
    $pending_incoming_count = $pending_incoming_stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (PDOException $e) {
    error_log("Referrals data fetch error: " . $e->getMessage());
    $alert_count = 0;
    $incoming_referrals = [];
    $outgoing_referrals = [];
    $available_users = [];
    $total_sent = 0;
    $accepted_count = 0;
    $declined_count = 0;
    $pending_incoming_count = 0;
}

// Check for messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
include 'referrals_html.php';
?>
