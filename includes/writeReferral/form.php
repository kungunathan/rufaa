<div class="referral-form-container">
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <div class="form-section">
            <h2 class="section-title">Patient Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="patient_id">Patient ID</label>
                    <input type="text" id="patient_id" name="patient_id" class="form-input" placeholder="Enter patient ID" value="<?php echo htmlspecialchars($_POST['patient_id']??'');?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="patient_name">Patient Name</label>
                    <input type="text" id="patient_name" name="patient_name" class="form-input" placeholder="Enter patient name" value="<?php echo htmlspecialchars($_POST['patient_name']??'');?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="patient_age">Age</label>
                    <input type="number" id="patient_age" name="patient_age" class="form-input" placeholder="Enter age" value="<?php echo htmlspecialchars($_POST['patient_age']??'');?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="patient_gender">Gender</label>
                    <select id="patient_gender" name="patient_gender" class="form-select" required>
                        <option value="">Select gender</option>
                        <option value="male" <?php echo (isset($_POST['patient_gender'])&&$_POST['patient_gender']=='male')?'selected':'';?>>Male</option>
                        <option value="female" <?php echo (isset($_POST['patient_gender'])&&$_POST['patient_gender']=='female')?'selected':'';?>>Female</option>
                        <option value="other" <?php echo (isset($_POST['patient_gender'])&&$_POST['patient_gender']=='other')?'selected':'';?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">Medical Information</h2>
            <div class="form-group">
                <label class="form-label" for="condition">Primary Condition</label>
                <input type="text" id="condition" name="condition" class="form-input" placeholder="Enter primary condition" value="<?php echo htmlspecialchars($_POST['condition']??'');?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="symptoms">Symptoms</label>
                <textarea id="symptoms" name="symptoms" class="form-textarea" placeholder="Describe symptoms"><?php echo htmlspecialchars($_POST['symptoms']??'');?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="medical_history">Medical History</label>
                <textarea id="medical_history" name="medical_history" class="form-textarea" placeholder="Relevant medical history"><?php echo htmlspecialchars($_POST['medical_history']??'');?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="current_medications">Current Medications</label>
                <textarea id="current_medications" name="current_medications" class="form-textarea" placeholder="List current medications"><?php echo htmlspecialchars($_POST['current_medications']??'');?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">Referral Details</h2>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="referring_doctor">Referring Doctor</label>
                    <input type="text" id="referring_doctor" name="referring_doctor" class="form-input" placeholder="Enter your name" value="<?php echo htmlspecialchars($_POST['referring_doctor']??$user_name);?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="referring_facility">Referring Facility</label>
                    <input type="text" id="referring_facility" name="referring_facility" class="form-input" placeholder="Enter facility name" value="<?php echo htmlspecialchars($_POST['referring_facility']??'');?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="receiving_user_id">Refer To User</label>
                <select id="receiving_user_id" name="receiving_user_id" class="form-select" required>
                    <option value="">Select receiving user</option>
                    <?php foreach($available_users as $user):?>
                    <option value="<?php echo $user['id'];?>" <?php echo (isset($_POST['receiving_user_id'])&&$_POST['receiving_user_id']==$user['id'])?'selected':'';?>>
                        <?php echo htmlspecialchars($user['first_name'].' '.$user['last_name'].' ('.$user['email'].')');?>
                    </option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="specialty">Required Specialty</label>
                <select id="specialty" name="specialty" class="form-select" required>
                    <option value="">Select specialty</option>
                    <option value="cardiology" <?php echo (isset($_POST['specialty'])&&$_POST['specialty']=='cardiology')?'selected':'';?>>Cardiology</option>
                    <option value="orthopedics" <?php echo (isset($_POST['specialty'])&&$_POST['specialty']=='orthopedics')?'selected':'';?>>Orthopedics</option>
                    <option value="neurology" <?php echo (isset($_POST['specialty'])&&$_POST['specialty']=='neurology')?'selected':'';?>>Neurology</option>
                    <option value="surgery" <?php echo (isset($_POST['specialty'])&&$_POST['specialty']=='surgery')?'selected':'';?>>Surgery</option>
                    <option value="internal" <?php echo (isset($_POST['specialty'])&&$_POST['specialty']=='internal')?'selected':'';?>>Internal Medicine</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="urgency_level">Urgency Level</label>
                <select id="urgency_level" name="urgency_level" class="form-select" required>
                    <option value="">Select urgency level</option>
                    <option value="routine" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='routine')?'selected':'';?>>Routine</option>
                    <option value="urgent" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='urgent')?'selected':'';?>>Urgent</option>
                    <option value="emergency" <?php echo (isset($_POST['urgency_level'])&&$_POST['urgency_level']=='emergency')?'selected':'';?>>Emergency</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="additional_notes">Additional Notes</label>
                <textarea id="additional_notes" name="additional_notes" class="form-textarea" placeholder="Any additional information for the receiving doctor"><?php echo htmlspecialchars($_POST['additional_notes']??'');?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">Consent & Authorization</h2>
            <div class="checkbox-group">
                <input type="checkbox" id="consent" name="consent" class="checkbox-input" required <?php echo (isset($_POST['consent'])&&$_POST['consent'])?'checked':'';?>>
                <label for="consent" class="checkbox-label">
                    I confirm that I have obtained the patient's consent for this referral and that all information provided is accurate to the best of my knowledge.
                </label>
            </div>
        </div>

        <div class="action-buttons">
            <button type="submit" class="btn btn-primary">Submit Referral</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
        </div>
    </form>
</div>