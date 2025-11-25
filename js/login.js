function validateEmail(field) {
    const errorElement = document.getElementById('emailError');
    const email = field.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email === '') {
        showError(field, errorElement, 'Email is required');
        return false;
    } else if (!emailRegex.test(email)) {
        showError(field, errorElement, 'Please enter a valid email address');
        return false;
    } else {
        showSuccess(field, errorElement);
        return true;
    }
}

function validatePassword(field) {
    const errorElement = document.getElementById('passwordError');
    const password = field.value;
    
    if (password === '') {
        showError(field, errorElement, 'Password is required');
        return false;
    } else if (password.length < 6) {
        showError(field, errorElement, 'Password must be at least 6 characters');
        return false;
    } else {
        showSuccess(field, errorElement);
        return true;
    }
}

function showError(field, errorElement, message) {
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    field.classList.add('input-error');
    field.classList.remove('input-success');
}

function showSuccess(field, errorElement) {
    errorElement.style.display = 'none';
    field.classList.remove('input-error');
    field.classList.add('input-success');
}

// Form submission validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all fields
    isValid = validateEmail(document.getElementById('email')) && isValid;
    isValid = validatePassword(document.getElementById('password')) && isValid;
    
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
        submitButton.innerHTML = 'Signing In...';
        submitButton.disabled = true;
        document.getElementById('loginForm').classList.add('loading');
    }
});

// Auto-focus on email field
document.getElementById('email').focus();

// Add real-time validation
document.getElementById('email').addEventListener('input', function() {
    validateEmail(this);
});

document.getElementById('password').addEventListener('input', function() {
    validatePassword(this);
});