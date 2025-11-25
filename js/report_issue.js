// Field validation functions
function validateField(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    if (field.value.trim() === '') {
        showError(field, errorElement, field.labels[0].textContent + ' is required');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validateTextarea(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    if (field.hasAttribute('required') && field.value.trim() === '') {
        showError(field, errorElement, field.labels[0].textContent + ' is required');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function showError(field, errorElement, message) {
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    field.classList.add('input-error');
    field.classList.remove('input-success');
}

function hideError(field, errorElement) {
    errorElement.style.display = 'none';
    field.classList.remove('input-error');
    field.classList.add('input-success');
}

// Priority level selection
function selectPriority(level) {
    const options = document.querySelectorAll('.priority-option');
    options.forEach(option => {
        option.classList.remove('selected');
    });
    
    const selectedOption = document.querySelector(`.priority-${level}`);
    selectedOption.classList.add('selected');
    
    const hiddenInput = document.getElementById('priority_level');
    hiddenInput.value = level;
    
    // Validate the field
    validateField(hiddenInput);
}

// Form submission validation
document.getElementById('issueForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all required fields
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (field.tagName === 'SELECT') {
            if (!validateField(field)) isValid = false;
        } else if (field.tagName === 'TEXTAREA') {
            if (!validateTextarea(field)) isValid = false;
        } else {
            if (!validateField(field)) isValid = false;
        }
    });
    
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
        submitButton.innerHTML = 'Submitting Report...';
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
    const sections = document.querySelectorAll('.form-section');
    sections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.1}s`;
        section.classList.add('fade-in');
    });

    // Initialize priority level if already selected
    const priorityLevel = document.getElementById('priority_level').value;
    if (priorityLevel) {
        selectPriority(priorityLevel);
    }
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