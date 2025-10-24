// Write Referral Form JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    const referralForm = document.getElementById('referralForm');
    const urgencyOptions = document.querySelectorAll('.urgency-option');
    const urgencyLevelInput = document.getElementById('urgencyLevel');
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize urgency level selector
    initUrgencySelector();
    
    // Initialize auto-fill for referring doctor
    autoFillReferringDoctor();
});

// Form validation
function initFormValidation() {
    const form = document.getElementById('referralForm');
    
    form.addEventListener('submit', function(event) {
        if (!validateForm()) {
            event.preventDefault();
            showFormErrors();
        }
    });
    
    // Real-time validation
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
    });
}

function validateForm() {
    let isValid = true;
    const form = document.getElementById('referralForm');
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    // Validate password confirmation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    if (password && confirmPassword && password.value !== confirmPassword.value) {
        showFieldError(confirmPassword, 'Passwords do not match');
        isValid = false;
    }
    
    // Validate consent
    const consent = document.getElementById('consent');
    if (consent && !consent.checked) {
        showFieldError(consent, 'You must agree to the terms and conditions');
        isValid = false;
    }
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }
    
    // Phone validation
    if (field.type === 'tel' && value) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
            showFieldError(field, 'Please enter a valid phone number');
            return false;
        }
    }
    
    // Age validation
    if (field.name === 'patient_age' && value) {
        const age = parseInt(value);
        if (age < 0 || age > 150) {
            showFieldError(field, 'Please enter a valid age');
            return false;
        }
    }
    
    clearFieldError(field);
    return true;
}

function showFieldError(field, message) {
    clearFieldError(field);
    field.classList.add('error');
    
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    field.parentNode.appendChild(errorElement);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

function showFormErrors() {
    const firstErrorField = document.querySelector('.error');
    if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstErrorField.focus();
    }
}

// Urgency level selector
function initUrgencySelector() {
    const urgencyOptions = document.querySelectorAll('.urgency-option');
    const urgencyLevelInput = document.getElementById('urgencyLevel');
    
    urgencyOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            urgencyOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Update hidden input value
            const level = this.getAttribute('data-level');
            urgencyLevelInput.value = level;
            
            // Visual feedback
            updateUrgencyVisuals(level);
        });
    });
}

function updateUrgencyVisuals(level) {
    const urgencyOptions = document.querySelectorAll('.urgency-option');
    
    urgencyOptions.forEach(option => {
        option.classList.remove('routine', 'urgent', 'emergency');
        if (option.getAttribute('data-level') === level) {
            option.classList.add(level);
        }
    });
}

// Auto-fill referring doctor with user's name
function autoFillReferringDoctor() {
    const referringDoctorField = document.getElementById('referring_doctor');
    if (referringDoctorField && !referringDoctorField.value) {
        // This would typically get the user's name from the session or make an API call
        const userName = document.querySelector('.user-info span')?.textContent.replace('Welcome, ', '');
        if (userName) {
            referringDoctorField.value = userName;
        }
    }
}

// Dynamic form interactions
function toggleAdditionalFields() {
    const symptomsField = document.getElementById('symptoms');
    const medicalHistoryField = document.getElementById('medical_history');
    const medicationsField = document.getElementById('current_medications');
    
    // Show/hide additional fields based on condition complexity
    const conditionField = document.getElementById('condition');
    if (conditionField) {
        conditionField.addEventListener('change', function() {
            const condition = this.value.toLowerCase();
            const isComplex = condition.includes('severe') || condition.includes('critical') || condition.includes('emergency');
            
            [symptomsField, medicalHistoryField, medicationsField].forEach(field => {
                if (field) {
                    field.closest('.form-group').style.display = isComplex ? 'block' : 'block'; // Always show for now
                }
            });
        });
    }
}

// Character counters for text areas
function initCharacterCounters() {
    const textAreas = document.querySelectorAll('textarea[maxlength]');
    
    textAreas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.textContent = `0/${maxLength}`;
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            counter.textContent = `${currentLength}/${maxLength}`;
            
            if (currentLength > maxLength * 0.9) {
                counter.classList.add('warning');
            } else {
                counter.classList.remove('warning');
            }
        });
    });
}

// Form saving as draft
function initDraftSaving() {
    const saveDraftBtn = document.querySelector('.btn-secondary');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function() {
            saveFormAsDraft();
        });
    }
    
    // Auto-save every 30 seconds
    setInterval(autoSaveDraft, 30000);
}

function saveFormAsDraft() {
    const formData = new FormData(document.getElementById('referralForm'));
    const draftData = {};
    
    formData.forEach((value, key) => {
        draftData[key] = value;
    });
    
    localStorage.setItem('referralDraft', JSON.stringify(draftData));
    showNotification('Draft saved successfully', 'success');
}

function autoSaveDraft() {
    const form = document.getElementById('referralForm');
    if (form.checkValidity()) {
        saveFormAsDraft();
    }
}

function loadDraft() {
    const draftData = localStorage.getItem('referralDraft');
    if (draftData) {
        const data = JSON.parse(draftData);
        Object.keys(data).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = data[key];
            }
        });
        showNotification('Draft loaded successfully', 'info');
    }
}

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Initialize all functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initFormValidation();
    initUrgencySelector();
    autoFillReferringDoctor();
    toggleAdditionalFields();
    initCharacterCounters();
    initDraftSaving();
    
    // Load draft if exists
    if (localStorage.getItem('referralDraft')) {
        if (confirm('Would you like to load your saved draft?')) {
            loadDraft();
        }
    }
});