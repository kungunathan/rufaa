<form method="POST" id="resend-form-<?php echo $referral['id']; ?>" class="edit-form">
    <input type="hidden" name="resend_referral" value="1">
    <input type="hidden" name="original_referral_id" value="<?php echo $referral['id']; ?>">
    <div class="form-group">
        <label>Select New Recipient *</label>
        <select name="new_receiving_user_id" required>
            <option value="">Select new recipient</option>
            <?php foreach ($available_users as $user): ?>
                <option value="<?php echo $user['id']; ?>">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="action-links">
        <button type="submit" class="action-btn confirm-resend-btn">Resend Referral</button>
        <button type="button" class="action-btn" onclick="toggleForm('resend-form-<?php echo $referral['id']; ?>')">Cancel</button>
    </div>
</form>