<div id="details-<?php echo $referral['id']; ?>" class="view-details">
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
        <button type="button" class="action-btn" onclick="toggleDetails(<?php echo $referral['id']; ?>)">Close</button>
        <?php if ($referral['status'] === 'declined'): ?>
            <button type="button" class="action-btn edit-btn" onclick="showEditForm(<?php echo $referral['id']; ?>)">Edit Referral</button>
            <button type="button" class="action-btn resend-btn" onclick="showResendForm(<?php echo $referral['id']; ?>)">Resend Referral</button>
        <?php endif; ?>
    </div>
</div>