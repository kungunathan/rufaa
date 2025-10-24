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
                            <br><small style="color: #666;">Reason: <?php echo htmlspecialchars($referral['feedback']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y g:i A', strtotime($referral['created_at'])); ?></td>
                    <td>
                        <button type="button" class="action-btn view-btn" onclick="toggleDetails(<?php echo $referral['id']; ?>)">View</button>
                        
                        <?php if ($referral['status'] === 'declined'): ?>
                            <button type="button" class="action-btn edit-btn" onclick="toggleForm('edit-form-<?php echo $referral['id']; ?>')">Edit</button>
                            <button type="button" class="action-btn resend-btn" onclick="toggleForm('resend-form-<?php echo $referral['id']; ?>')">Resend</button>
                        <?php endif; ?>
                        
                        <!-- View Details -->
                        <?php include 'includes/referralsDashboard/outgoing/view_details.php'; ?>
                        
                        <!-- Edit Form -->
                        <?php include 'includes/referralsDashboard/outgoing/edit_form.php'; ?>
                        
                        <!-- Resend Form -->
                        <?php include 'includes/referralsDashboard/outgoing/resend_form.php'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>You haven't sent any referrals yet.</p>
<?php endif; ?>