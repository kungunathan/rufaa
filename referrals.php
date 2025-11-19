<?php
session_start();
require_once 'config/database.php';

// Include DomPDF at the top level
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
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN urgency_level = 'emergency' THEN 1 ELSE 0 END) as emergency,
                    SUM(CASE WHEN urgency_level = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN urgency_level = 'routine' THEN 1 ELSE 0 END) as routine,
                    SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN receiving_user_id = ? THEN 1 ELSE 0 END) as received_count
                FROM referrals 
                WHERE (user_id = ? OR receiving_user_id = ?) $date_conditions
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

/**
 * Generate HTML content for the PDF report
 */
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Referrals Dashboard</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .stat-icon {
            font-size: 24px;
        }

        /* Report Form Styles */
        .report-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 25px;
        }

        .report-form .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .report-form .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .report-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .report-form select,
        .report-form input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .report-form select:focus,
        .report-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Section Styles */
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
            font-weight: 600;
        }

        /* Table Styles */
        .referrals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .referrals-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .referrals-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 14px;
        }

        .referrals-table tr:hover {
            background-color: #f8f9fa;
        }

        .referrals-table tr:last-child td {
            border-bottom: none;
        }

        /* Action Buttons */
        .action-btn {
            padding: 8px 16px;
            margin: 2px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            min-width: 70px;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .view-btn { background: #17a2b8; color: white; }
        .edit-btn { background: #ffc107; color: black; }
        .resend-btn { background: #28a745; color: white; }
        .accept-btn { background: #28a745; color: white; }
        .deny-btn { background: #dc3545; color: white; }
        .confirm-deny-btn { background: #dc3545; color: white; }
        .confirm-edit-btn { background: #007bff; color: white; }
        .confirm-resend-btn { background: #28a745; color: white; }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-declined { background: #f8d7da; color: #721c24; }

        /* Urgency Indicators */
        .urgency-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .urgency-routine { background: #28a745; }
        .urgency-urgent { background: #ffc107; }
        .urgency-emergency { background: #dc3545; animation: blink 1s infinite; }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Form Styles */
        .edit-form {
            display: none;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .edit-form .form-group {
            margin-bottom: 20px;
        }

        .edit-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .edit-form input,
        .edit-form select,
        .edit-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .edit-form input:focus,
        .edit-form select:focus,
        .edit-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .edit-form .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .edit-form .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Search Box Styles */
        .search-container {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .search-result-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s ease;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .user-display-name {
            font-weight: 600;
            color: #333;
        }

        .user-email {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }

        .selected-user {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 4px;
            display: none;
        }

        .selected-user span {
            font-weight: 600;
            color: #155724;
        }

        /* View Details */
        .view-details {
            display: none;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-label {
            font-weight: 600;
            width: 200px;
            color: #333;
            flex-shrink: 0;
            font-size: 14px;
        }

        .detail-value {
            flex: 1;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .action-links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            text-align: center;
        }

        .action-links .action-btn {
            margin: 0 10px;
            padding: 10px 20px;
            min-width: 120px;
        }

        /* Main Action Buttons */
        .action-buttons {
            margin-top: 30px;
            text-align: center;
        }

        .btn {
            padding: 14px 28px;
            margin: 0 12px;
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
            min-width: 180px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: #7f8c8d;
            border: 2px solid #bdc3c7;
        }

        .btn-outline:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .edit-form .form-row,
            .report-form .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

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
            
            .referrals-table {
                display: block;
                overflow-x: auto;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                min-width: auto;
                width: 100%;
                margin: 0;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .section {
                padding: 20px;
            }
            
            .action-links {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .action-links .action-btn {
                margin: 0;
                width: 100%;
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
                    <a href="referrals.php" class="nav-link active">
                        <span>Referrals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="write_referral.php" class="nav-link">
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
                <h1 class="page-title">Referrals Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                    <?php if ($alert_count > 0): ?>
                        <div class="alert-badge" title="<?php echo $alert_count; ?> unread alerts"><?php echo $alert_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Stats Grid - FIXED: Using proper database counts -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $total_sent; ?></div>
                            <div class="stat-label">Total Referrals Sent</div>
                        </div>
                        <div class="stat-icon">📤</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $accepted_count; ?></div>
                            <div class="stat-label">Accepted</div>
                        </div>
                        <div class="stat-icon">✅</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $declined_count; ?></div>
                            <div class="stat-label">Declined</div>
                        </div>
                        <div class="stat-icon">❌</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $pending_incoming_count; ?></div>
                            <div class="stat-label">Pending Incoming</div>
                        </div>
                        <div class="stat-icon">⏳</div>
                    </div>
                </div>
            </div>

            <!-- Report Generation Section -->
            <div class="section">
                <h3 class="section-title">Generate Referral Report</h3>
                <form method="POST" class="report-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Report Type</label>
                            <select name="report_type" required>
                                <option value="user_activity">User Activity</option>
                                <option value="referral_summary">Referral Summary</option>
                                <option value="detailed_analysis">Detailed Analysis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date Range</label>
                            <select name="date_range" id="date_range" required onchange="toggleCustomDates()">
                                <option value="all_time">All Time</option>
                                <option value="last_week">Last 7 Days</option>
                                <option value="last_month">Last 30 Days</option>
                                <option value="last_quarter">Last 3 Months</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="custom_dates" class="form-row" style="display: none;">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="start_date">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" id="end_date">
                        </div>
                    </div>
                    
                    <div class="action-buttons" style="margin-top: 20px;">
                        <button type="submit" name="generate_report" class="btn btn-primary">Generate PDF Report</button>
                    </div>
                </form>
            </div>

            <!-- Incoming Referrals Section -->
            <div class="section">
                <h3 class="section-title">Incoming Referrals - Awaiting Your Response</h3>
                <?php if (count($incoming_referrals) > 0): ?>
                    <table class="referrals-table">
                        <thead>
                            <tr>
                                <th>Referral Code</th>
                                <th>From</th>
                                <th>Patient</th>
                                <th>Condition</th>
                                <th>Urgency</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incoming_referrals as $referral): ?>
                                <tr>
                                    <td class="referral-id"><?php echo htmlspecialchars($referral['referral_code']); ?></td>
                                    <td><?php echo htmlspecialchars($referral['sender_first_name'] . ' ' . $referral['sender_last_name']); ?></td>
                                    <td class="patient-name"><?php echo htmlspecialchars($referral['patient_name']); ?></td>
                                    <td class="referral-condition"><?php echo htmlspecialchars($referral['condition_description']); ?></td>
                                    <td>
                                        <span class="urgency-indicator urgency-<?php echo $referral['urgency_level']; ?>"></span>
                                        <?php echo ucfirst($referral['urgency_level']); ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($referral['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="action-btn view-btn" onclick="toggleDetails('incoming-<?php echo $referral['id']; ?>')">View</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                                            <button type="submit" name="action" value="accept" class="action-btn accept-btn" onclick="return confirm('Are you sure you want to accept this referral?')">Accept</button>
                                        </form>
                                        <button type="button" class="action-btn deny-btn" onclick="toggleForm('deny-form-<?php echo $referral['id']; ?>')">Deny</button>
                                        
                                        <!-- View Details -->
                                        <div id="incoming-<?php echo $referral['id']; ?>" class="view-details">
                                            <h4>Referral Details</h4>
                                            <div class="detail-row">
                                                <div class="detail-label">Patient ID:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['patient_id']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Age:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['patient_age']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Gender:</div>
                                                <div class="detail-value"><?php echo ucfirst($referral['patient_gender']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Symptoms:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['symptoms'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Medical History:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['medical_history'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Medications:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['current_medications'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Additional Notes:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['additional_notes'] ?: 'None'); ?></div>
                                            </div>
                                            <div class="action-links">
                                                <button type="button" class="action-btn" onclick="toggleDetails('incoming-<?php echo $referral['id']; ?>')">Close</button>
                                            </div>
                                        </div>
                                        
                                        <!-- Deny Form -->
                                        <form method="POST" id="deny-form-<?php echo $referral['id']; ?>" class="edit-form">
                                            <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                                            <input type="hidden" name="action" value="deny">
                                            <div class="form-group">
                                                <label>Reason for Declining:</label>
                                                <textarea name="feedback" placeholder="Please provide reason for declining this referral..." required style="width: 100%; height: 100px;"></textarea>
                                            </div>
                                            <div class="action-links">
                                                <button type="submit" class="action-btn confirm-deny-btn" onclick="return confirm('Are you sure you want to decline this referral?')">Confirm Deny</button>
                                                <button type="button" class="action-btn" onclick="toggleForm('deny-form-<?php echo $referral['id']; ?>')">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div>📭</div>
                        <p>No incoming referrals awaiting your response.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Outgoing Referrals Section -->
            <div class="section">
                <h3 class="section-title">Your Sent Referrals</h3>
                <?php if (count($outgoing_referrals) > 0): ?>
                    <table class="referrals-table">
                        <thead>
                            <tr>
                                <th>Referral Code</th>
                                <th>To</th>
                                <th>Patient</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th>Sent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outgoing_referrals as $referral): ?>
                                <tr>
                                    <td class="referral-id"><?php echo htmlspecialchars($referral['referral_code']); ?></td>
                                    <td><?php echo htmlspecialchars($referral['receiver_first_name'] . ' ' . $referral['receiver_last_name']); ?></td>
                                    <td class="patient-name"><?php echo htmlspecialchars($referral['patient_name']); ?></td>
                                    <td class="referral-condition"><?php echo htmlspecialchars($referral['condition_description']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $referral['status']; ?>">
                                            <?php echo ucfirst($referral['status']); ?>
                                        </span>
                                        <?php if ($referral['status'] === 'declined' && $referral['feedback']): ?>
                                            <br><small style="color: #666; display: block; margin-top: 5px;">Reason: <?php echo htmlspecialchars($referral['feedback']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($referral['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="action-btn view-btn" onclick="toggleDetails('outgoing-<?php echo $referral['id']; ?>')">View</button>
                                        
                                        <?php if ($referral['status'] === 'declined'): ?>
                                            <button type="button" class="action-btn edit-btn" onclick="toggleForm('edit-form-<?php echo $referral['id']; ?>')">Edit</button>
                                            <button type="button" class="action-btn resend-btn" onclick="toggleForm('resend-form-<?php echo $referral['id']; ?>')">Resend</button>
                                        <?php endif; ?>
                                        
                                        <!-- View Details -->
                                        <div id="outgoing-<?php echo $referral['id']; ?>" class="view-details">
                                            <h4>Referral Details</h4>
                                            <div class="detail-row">
                                                <div class="detail-label">Patient ID:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['patient_id']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Age:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['patient_age']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Gender:</div>
                                                <div class="detail-value"><?php echo ucfirst($referral['patient_gender']); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Symptoms:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['symptoms'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Medical History:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['medical_history'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Medications:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['current_medications'] ?: 'Not specified'); ?></div>
                                            </div>
                                            <div class="detail-row">
                                                <div class="detail-label">Additional Notes:</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($referral['additional_notes'] ?: 'None'); ?></div>
                                            </div>
                                            <div class="action-links">
                                                <button type="button" class="action-btn" onclick="toggleDetails('outgoing-<?php echo $referral['id']; ?>')">Close</button>
                                                <?php if ($referral['status'] === 'declined'): ?>
                                                    <button type="button" class="action-btn edit-btn" onclick="showEditForm(<?php echo $referral['id']; ?>)">Edit Referral</button>
                                                    <button type="button" class="action-btn resend-btn" onclick="showResendForm(<?php echo $referral['id']; ?>)">Resend Referral</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Form -->
                                        <form method="POST" id="edit-form-<?php echo $referral['id']; ?>" class="edit-form">
                                            <input type="hidden" name="edit_referral" value="1">
                                            <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Patient ID *</label>
                                                    <input type="text" name="patient_id" value="<?php echo htmlspecialchars($referral['patient_id']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Patient Name *</label>
                                                    <input type="text" name="patient_name" value="<?php echo htmlspecialchars($referral['patient_name']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Age *</label>
                                                    <input type="number" name="patient_age" value="<?php echo htmlspecialchars($referral['patient_age']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Gender *</label>
                                                    <select name="patient_gender" required>
                                                        <option value="male" <?php echo $referral['patient_gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                                        <option value="female" <?php echo $referral['patient_gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                                        <option value="other" <?php echo $referral['patient_gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Condition *</label>
                                                <input type="text" name="condition" value="<?php echo htmlspecialchars($referral['condition_description']); ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Symptoms</label>
                                                <textarea name="symptoms" placeholder="Describe symptoms"><?php echo htmlspecialchars($referral['symptoms']); ?></textarea>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Medical History</label>
                                                <textarea name="medical_history" placeholder="Relevant medical history"><?php echo htmlspecialchars($referral['medical_history']); ?></textarea>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Current Medications</label>
                                                <textarea name="current_medications" placeholder="List current medications"><?php echo htmlspecialchars($referral['current_medications']); ?></textarea>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Referring Doctor *</label>
                                                    <input type="text" name="referring_doctor" value="<?php echo htmlspecialchars($referral['referring_doctor']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Referring Facility *</label>
                                                    <input type="text" name="referring_facility" value="<?php echo htmlspecialchars($referral['referring_facility']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Specialty *</label>
                                                <select name="specialty" required>
                                                    <option value="cardiology" <?php echo $referral['specialty'] == 'cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                                    <option value="orthopedics" <?php echo $referral['specialty'] == 'orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                                    <option value="neurology" <?php echo $referral['specialty'] == 'neurology' ? 'selected' : ''; ?>>Neurology</option>
                                                    <option value="surgery" <?php echo $referral['specialty'] == 'surgery' ? 'selected' : ''; ?>>Surgery</option>
                                                    <option value="internal" <?php echo $referral['specialty'] == 'internal' ? 'selected' : ''; ?>>Internal Medicine</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Urgency Level *</label>
                                                <select name="urgency_level" required>
                                                    <option value="routine" <?php echo $referral['urgency_level'] == 'routine' ? 'selected' : ''; ?>>Routine</option>
                                                    <option value="urgent" <?php echo $referral['urgency_level'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                                    <option value="emergency" <?php echo $referral['urgency_level'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Additional Notes</label>
                                                <textarea name="additional_notes" placeholder="Any additional information"><?php echo htmlspecialchars($referral['additional_notes']); ?></textarea>
                                            </div>
                                            
                                            <div class="action-links">
                                                <button type="submit" class="action-btn confirm-edit-btn">Save Changes</button>
                                                <button type="button" class="action-btn" onclick="toggleForm('edit-form-<?php echo $referral['id']; ?>')">Cancel</button>
                                            </div>
                                        </form>
                                        
                                        <!-- Resend Form -->
                                        <form method="POST" id="resend-form-<?php echo $referral['id']; ?>" class="edit-form">
                                            <input type="hidden" name="resend_referral" value="1">
                                            <input type="hidden" name="original_referral_id" value="<?php echo $referral['id']; ?>">
                                            <input type="hidden" name="new_receiving_user_id" id="new_receiving_user_id_<?php echo $referral['id']; ?>" required>
                                            
                                            <div class="form-group">
                                                <label>Search for New Recipient *</label>
                                                <div class="search-container">
                                                    <input type="text" 
                                                           class="search-input" 
                                                           id="user_search_<?php echo $referral['id']; ?>" 
                                                           placeholder="Type to search for users by name or email..."
                                                           onkeyup="searchUsers(this.value, <?php echo $referral['id']; ?>)"
                                                           autocomplete="off">
                                                    <div class="search-results" id="search_results_<?php echo $referral['id']; ?>"></div>
                                                </div>
                                                <div class="selected-user" id="selected_user_<?php echo $referral['id']; ?>">
                                                    Selected: <span id="selected_user_name_<?php echo $referral['id']; ?>"></span>
                                                </div>
                                            </div>
                                            
                                            <div class="action-links">
                                                <button type="submit" class="action-btn confirm-resend-btn" id="resend_submit_<?php echo $referral['id']; ?>" disabled>Resend Referral</button>
                                                <button type="button" class="action-btn" onclick="toggleForm('resend-form-<?php echo $referral['id']; ?>')">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div>📤</div>
                        <p>You haven't sent any referrals yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="write_referral.php" class="btn btn-primary">Write New Referral</a>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        // Available users data for search
        const availableUsers = <?php echo json_encode($available_users); ?>;

        // Search users function
        function searchUsers(query, referralId) {
            const resultsContainer = document.getElementById('search_results_' + referralId);
            const selectedUserDiv = document.getElementById('selected_user_' + referralId);
            const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
            const submitButton = document.getElementById('resend_submit_' + referralId);
            
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            const filteredUsers = availableUsers.filter(user => {
                const fullName = user.first_name + ' ' + user.last_name;
                return fullName.toLowerCase().includes(query.toLowerCase()) || 
                       user.email.toLowerCase().includes(query.toLowerCase());
            });
            
            displaySearchResults(filteredUsers, referralId);
        }
        
        // Display search results
        function displaySearchResults(users, referralId) {
            const resultsContainer = document.getElementById('search_results_' + referralId);
            resultsContainer.innerHTML = '';
            
            if (users.length === 0) {
                resultsContainer.innerHTML = '<div class="search-result-item">No users found</div>';
            } else {
                users.forEach(user => {
                    const userElement = document.createElement('div');
                    userElement.className = 'search-result-item';
                    userElement.innerHTML = `
                        <div class="user-display-name">${user.first_name} ${user.last_name}</div>
                        <div class="user-email">${user.email}</div>
                    `;
                    userElement.onclick = () => selectUser(user, referralId);
                    resultsContainer.appendChild(userElement);
                });
            }
            
            resultsContainer.style.display = 'block';
        }
        
        // Select user from search results
        function selectUser(user, referralId) {
            const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
            const selectedUserDiv = document.getElementById('selected_user_' + referralId);
            const selectedUserName = document.getElementById('selected_user_name_' + referralId);
            const searchInput = document.getElementById('user_search_' + referralId);
            const resultsContainer = document.getElementById('search_results_' + referralId);
            const submitButton = document.getElementById('resend_submit_' + referralId);
            
            userIdInput.value = user.id;
            selectedUserName.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
            selectedUserDiv.style.display = 'block';
            searchInput.value = `${user.first_name} ${user.last_name}`;
            resultsContainer.style.display = 'none';
            submitButton.disabled = false;
        }
        
        // Toggle view details
        function toggleDetails(elementId) {
            const element = document.getElementById(elementId);
            if (element.style.display === 'block') {
                element.style.display = 'none';
            } else {
                // Hide all other details first
                document.querySelectorAll('.view-details').forEach(detail => {
                    detail.style.display = 'none';
                });
                element.style.display = 'block';
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Toggle form visibility
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            if (form.style.display === 'block') {
                form.style.display = 'none';
                // Reset search fields when closing
                if (formId.includes('resend-form')) {
                    const referralId = formId.split('_').pop();
                    resetSearchFields(referralId);
                }
            } else {
                // Hide all other forms first
                document.querySelectorAll('.edit-form').forEach(form => {
                    form.style.display = 'none';
                });
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // Reset search fields
        function resetSearchFields(referralId) {
            const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
            const selectedUserDiv = document.getElementById('selected_user_' + referralId);
            const searchInput = document.getElementById('user_search_' + referralId);
            const resultsContainer = document.getElementById('search_results_' + referralId);
            const submitButton = document.getElementById('resend_submit_' + referralId);
            
            userIdInput.value = '';
            selectedUserDiv.style.display = 'none';
            searchInput.value = '';
            resultsContainer.style.display = 'none';
            submitButton.disabled = true;
        }

        // Toggle custom date inputs
        function toggleCustomDates() {
            const dateRange = document.getElementById('date_range').value;
            const customDates = document.getElementById('custom_dates');
            customDates.style.display = dateRange === 'custom' ? 'flex' : 'none';
        }

        // Show edit form from view details
        function showEditForm(referralId) {
            toggleDetails('outgoing-' + referralId);
            setTimeout(() => {
                toggleForm('edit-form-' + referralId);
            }, 300);
        }

        // Show resend form from view details
        function showResendForm(referralId) {
            toggleDetails('outgoing-' + referralId);
            setTimeout(() => {
                toggleForm('resend-form-' + referralId);
            }, 300);
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.matches('.search-input')) {
                document.querySelectorAll('.search-results').forEach(container => {
                    container.style.display = 'none';
                });
            }
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to elements
            const elements = document.querySelectorAll('.stat-card, .section');
            elements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
                element.classList.add('fade-in');
            });

            // Add confirmation for destructive actions
            const denyButtons = document.querySelectorAll('.confirm-deny-btn');
            denyButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to decline this referral? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });

            // Auto-close messages after 5 seconds
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });

            // Initialize custom date toggle
            toggleCustomDates();
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