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