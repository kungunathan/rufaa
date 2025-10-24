<form method="POST" id="deny-form-<?php echo $referral['id']; ?>" class="edit-form">
    <input type="hidden" name="referral_id" value="<?php echo $referral['id']; ?>">
    <input type="hidden" name="action" value="deny">
    <div class="form-group">
        <label>Reason for Declining:</label>
        <textarea name="feedback" placeholder="Please provide reason for declining this referral..." required style="width: 100%; height: 80px;"></textarea>
    </div>
    <div class="action-links">
        <button type="submit" class="action-btn confirm-deny-btn">Confirm Deny</button>
        <button type="button" class="action-btn" onclick="toggleForm('deny-form-<?php echo $referral['id']; ?>')">Cancel</button>
    </div>
</form>