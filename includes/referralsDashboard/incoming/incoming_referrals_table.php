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
                        <button type="button" class="action-btn view-btn" onclick="toggleDetails(<?php echo $referral['id']; ?>)">View</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
                            <button type="submit" name="action" value="accept" class="action-btn accept-btn">Accept</button>
                        </form>
                        <button type="button" class="action-btn deny-btn" onclick="toggleForm('deny-form-<?php echo $referral['id']; ?>')">Deny</button>
                        
                        <!-- View Details -->
                        <?php include 'includes/referralsDashboard/incoming/incoming_view_details.php'; ?>
                        
                        <!-- Deny Form -->
                        <?php include 'includes/referralsDashboard/incoming/deny_form.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No incoming referrals awaiting your response.</p>
<?php endif; ?>