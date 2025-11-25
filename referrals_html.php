<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rufaa - Referrals Dashboard</title>
    <link rel="stylesheet" href="css/referrals.css">
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
    </script>
    <script src="js/referrals.js"></script>
</body>
</html>