// Field validation functions
function validateField(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    if (field.value.trim() === '') {
        showError(field, errorElement, 'This field is required');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validateEmail(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const email = field.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email === '') {
        showError(field, errorElement, 'Email is required');
        return false;
    } else if (!emailRegex.test(email)) {
        showError(field, errorElement, 'Please enter a valid email address');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validatePhone(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const phone = field.value.trim();
    
    if (phone !== '' && phone.length < 10) {
        showError(field, errorElement, 'Please enter a valid phone number');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validatePasswordStrength(field) {
    const strengthElement = field.parentElement.querySelector('.password-strength');
    const password = field.value;
    
    if (password === '') {
        strengthElement.style.display = 'none';
        return false;
    }
    
    strengthElement.style.display = 'block';
    
    let strength = 'Weak';
    let strengthClass = 'strength-weak';
    
    if (password.length >= 12) {
        strength = 'Strong';
        strengthClass = 'strength-strong';
    } else if (password.length >= 8) {
        strength = 'Medium';
        strengthClass = 'strength-medium';
    }
    
    strengthElement.textContent = strength;
    strengthElement.className = 'password-strength ' + strengthClass;
    
    return password.length >= 8;
}

function validatePasswordMatch() {
    const confirmField = document.getElementById('confirm_password');
    const newField = document.getElementById('new_password');
    const errorElement = confirmField.parentElement.querySelector('.field-error');
    
    if (confirmField.value !== '' && newField.value !== '' && confirmField.value !== newField.value) {
        showError(confirmField, errorElement, 'Passwords do not match');
        return false;
    } else {
        hideError(confirmField, errorElement);
        return true;
    }
}

function validatePasswordChange() {
    const currentField = document.getElementById('current_password');
    const newField = document.getElementById('new_password');
    const confirmField = document.getElementById('confirm_password');
    
    // If any password field has value, all should be validated
    if (currentField.value !== '' || newField.value !== '' || confirmField.value !== '') {
        if (currentField.value === '') {
            showError(currentField, currentField.parentElement.querySelector('.field-error') || createErrorElement(currentField), 'Current password is required');
            return false;
        }
        
        if (newField.value === '') {
            showError(newField, newField.parentElement.querySelector('.field-error') || createErrorElement(newField), 'New password is required');
            return false;
        }
        
        if (newField.value.length < 8) {
            showError(newField, newField.parentElement.querySelector('.field-error') || createErrorElement(newField), 'Password must be at least 8 characters');
            return false;
        }
        
        if (confirmField.value === '') {
            showError(confirmField, confirmField.parentElement.querySelector('.field-error') || createErrorElement(confirmField), 'Please confirm your password');
            return false;
        }
        
        if (confirmField.value !== newField.value) {
            showError(confirmField, confirmField.parentElement.querySelector('.field-error') || createErrorElement(confirmField), 'Passwords do not match');
            return false;
        }
    }
    
    return true;
}

function showError(field, errorElement, message) {
    if (!errorElement) {
        errorElement = createErrorElement(field);
    }
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    field.style.borderColor = '#e74c3c';
}

function hideError(field, errorElement) {
    if (errorElement) {
        errorElement.style.display = 'none';
    }
    field.style.borderColor = '#e1e5e9';
}

function createErrorElement(field) {
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.style.cssText = 'color: #e74c3c; font-size: 12px; margin-top: 5px;';
    field.parentElement.appendChild(errorElement);
    return errorElement;
}

// Form submission validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all required fields
    isValid = validateField(document.getElementById('first_name')) && isValid;
    isValid = validateField(document.getElementById('last_name')) && isValid;
    isValid = validateEmail(document.getElementById('email')) && isValid;
    isValid = validatePhone(document.getElementById('phone')) && isValid;
    isValid = validatePasswordChange() && isValid;
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = document.querySelector('.field-error[style="display: block;"]');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } else {
        // Show loading state
        const submitButton = document.getElementById('submitButton');
        submitButton.innerHTML = 'Saving Changes...';
        submitButton.disabled = true;
    }
});

// Auto-close messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);
    });

    // Add fade-in animation to sections
    const sections = document.querySelectorAll('.profile-section');
    sections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.1}s`;
        section.classList.add('fade-in');
    });
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