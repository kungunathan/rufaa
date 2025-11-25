function validateField(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    if (field.value.trim() === '') {
        showError(errorElement, 'This field is required');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else {
        hideError(errorElement);
        field.classList.remove('input-error');
        field.classList.add('input-success');
        return true;
    }
}

function validateEmail(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const email = field.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email === '') {
        showError(errorElement, 'Email is required');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else if (!emailRegex.test(email)) {
        showError(errorElement, 'Please enter a valid email address (e.g., name@example.com)');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else {
        hideError(errorElement);
        field.classList.remove('input-error');
        field.classList.add('input-success');
        return true;
    }
}

function validatePhone(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const phone = field.value.trim();
    const phoneRegex = /^[0-9]{10}$/;
    
    // Remove any non-numeric characters
    const numericPhone = phone.replace(/[^0-9]/g, '');
    field.value = numericPhone; // Update field with only numbers
    
    if (numericPhone === '') {
        showError(errorElement, 'Phone number is required');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else if (!phoneRegex.test(numericPhone)) {
        showError(errorElement, 'Please enter exactly 10 digits (numbers only)');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else {
        hideError(errorElement);
        field.classList.remove('input-error');
        field.classList.add('input-success');
        return true;
    }
}

function validatePassword(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const strengthElement = field.parentElement.querySelector('.password-strength');
    const password = field.value;
    
    if (password === '') {
        showError(errorElement, 'Password is required');
        strengthElement.textContent = '';
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else if (password.length < 8) {
        showError(errorElement, 'Password must be at least 8 characters');
        strengthElement.textContent = 'Weak';
        strengthElement.className = 'password-strength strength-weak';
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else {
        hideError(errorElement);
        field.classList.remove('input-error');
        field.classList.add('input-success');
        
        // Simple password strength check
        let strength = 'Weak';
        let strengthClass = 'strength-weak';
        
        if (password.length >= 12 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
            strength = 'Strong';
            strengthClass = 'strength-strong';
        } else if (password.length >= 10) {
            strength = 'Medium';
            strengthClass = 'strength-medium';
        }
        
        strengthElement.textContent = strength;
        strengthElement.className = 'password-strength ' + strengthClass;
        return true;
    }
}

function validateConfirmPassword(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const confirmPassword = field.value;
    const password = document.getElementById('password').value;
    
    if (confirmPassword === '') {
        showError(errorElement, 'Please confirm your password');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else if (confirmPassword !== password) {
        showError(errorElement, 'Passwords do not match');
        field.classList.add('input-error');
        field.classList.remove('input-success');
        return false;
    } else {
        hideError(errorElement);
        field.classList.remove('input-error');
        field.classList.add('input-success');
        return true;
    }
}

function showError(errorElement, message) {
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

function hideError(errorElement) {
    errorElement.style.display = 'none';
}

// Form submission validation
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all fields
    isValid = validateField(document.getElementById('firstName')) && isValid;
    isValid = validateField(document.getElementById('lastName')) && isValid;
    isValid = validateEmail(document.getElementById('email')) && isValid;
    isValid = validatePhone(document.getElementById('phone')) && isValid;
    isValid = validatePassword(document.getElementById('password')) && isValid;
    isValid = validateConfirmPassword(document.getElementById('confirmPassword')) && isValid;
    
    // Validate terms
    const termsCheckbox = document.getElementById('terms');
    const termsError = termsCheckbox.parentElement.querySelector('.field-error') || 
                     (function() {
                         const errorDiv = document.createElement('div');
                         errorDiv.className = 'field-error';
                         errorDiv.style.cssText = 'color: #e74c3c; font-size: 0.8rem; margin-top: 5px;';
                         termsCheckbox.parentElement.appendChild(errorDiv);
                         return errorDiv;
                     })();
    
    if (!termsCheckbox.checked) {
        showError(termsError, 'You must agree to the terms and privacy policy');
        isValid = false;
    } else {
        hideError(termsError);
    }
    
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
        submitButton.innerHTML = 'Creating Account...';
        submitButton.disabled = true;
        document.getElementById('registrationForm').classList.add('loading');
    }
});

// Prevent non-numeric input in phone field
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Prevent paste of non-numeric characters in phone field
document.getElementById('phone').addEventListener('paste', function(e) {
    const pasteData = e.clipboardData.getData('text');
    if (!/^\d+$/.test(pasteData)) {
        e.preventDefault();
    }
});