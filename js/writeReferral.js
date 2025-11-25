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

function validateAge(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const age = parseInt(field.value);
    
    if (field.value.trim() === '') {
        showError(field, errorElement, 'Age is required');
        return false;
    } else if (isNaN(age) || age < 0 || age > 150) {
        showError(field, errorElement, 'Please enter a valid age (0-150)');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validateTextarea(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    hideError(field, errorElement);
    return true;
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

// Urgency level selection
function selectUrgency(level) {
    const options = document.querySelectorAll('.urgency-option');
    options.forEach(option => {
        option.classList.remove('selected');
    });
    
    const selectedOption = document.querySelector(`.urgency-${level}`);
    selectedOption.classList.add('selected');
    
    const hiddenInput = document.getElementById('urgency_level');
    hiddenInput.value = level;
    
    // Validate the field
    validateField(hiddenInput);
}

// User search functionality
function searchUsers(query) {
    const resultsContainer = document.getElementById('userResults');
    const userSearchInput = document.getElementById('userSearch');
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    const filteredUsers = availableUsers.filter(user => {
        const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
        const email = user.email.toLowerCase();
        const searchTerm = query.toLowerCase();
        
        return fullName.includes(searchTerm) || email.includes(searchTerm);
    });
    
    displayUserResults(filteredUsers);
}

function displayUserResults(users) {
    const resultsContainer = document.getElementById('userResults');
    
    if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">No users found</div>';
    } else {
        resultsContainer.innerHTML = users.map(user => `
            <div class="user-result" onclick="selectUser(${user.id}, '${user.first_name} ${user.last_name}', '${user.email}')">
                <div class="user-name">${user.first_name} ${user.last_name}</div>
                <div class="user-email">${user.email}</div>
            </div>
        `).join('');
    }
    
    resultsContainer.style.display = 'block';
}

function selectUser(userId, userName, userEmail) {
    const receivingUserIdInput = document.getElementById('receiving_user_id');
    const userSearchInput = document.getElementById('userSearch');
    const selectedUserDiv = document.getElementById('selectedUser');
    const selectedUserName = document.getElementById('selectedUserName');
    const selectedUserEmail = document.getElementById('selectedUserEmail');
    const resultsContainer = document.getElementById('userResults');
    
    // Set the hidden input value
    receivingUserIdInput.value = userId;
    
    // Update selected user display
    selectedUserName.textContent = userName;
    selectedUserEmail.textContent = userEmail;
    selectedUserDiv.style.display = 'block';
    
    // Clear search input and hide results
    userSearchInput.value = '';
    resultsContainer.style.display = 'none';
    
    // Validate the field
    validateField(receivingUserIdInput);
}

function clearUserSelection() {
    const receivingUserIdInput = document.getElementById('receiving_user_id');
    const selectedUserDiv = document.getElementById('selectedUser');
    const userSearchInput = document.getElementById('userSearch');
    
    receivingUserIdInput.value = '';
    selectedUserDiv.style.display = 'none';
    userSearchInput.value = '';
    
    // Clear validation
    const errorElement = receivingUserIdInput.parentElement.querySelector('.field-error');
    hideError(receivingUserIdInput, errorElement);
}

// Consent validation
function validateConsent() {
    const consentCheckbox = document.getElementById('consent');
    const errorElement = document.querySelector('#consent').closest('.form-section').querySelector('.field-error');
    
    if (!consentCheckbox.checked) {
        showError(consentCheckbox, errorElement, 'You must provide consent to submit the referral');
        return false;
    } else {
        hideError(consentCheckbox, errorElement);
        return true;
    }
}

// Form submission validation
document.getElementById('referralForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all required fields
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (field.type === 'checkbox') {
            if (!validateConsent()) isValid = false;
        } else if (field.type === 'number') {
            if (!validateAge(field)) isValid = false;
        } else if (field.tagName === 'SELECT') {
            if (!validateField(field)) isValid = false;
        } else if (field.id === 'receiving_user_id') {
            if (!validateField(field)) isValid = false;
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
        submitButton.innerHTML = 'Submitting Referral...';
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

    // Initialize urgency level if already selected
    const urgencyLevel = document.getElementById('urgency_level').value;
    if (urgencyLevel) {
        selectUrgency(urgencyLevel);
    }

    // Initialize selected user if already set
    const receivingUserId = document.getElementById('receiving_user_id').value;
    if (receivingUserId) {
        const selectedUser = availableUsers.find(user => user.id == receivingUserId);
        if (selectedUser) {
            selectUser(selectedUser.id, `${selectedUser.first_name} ${selectedUser.last_name}`, selectedUser.email);
        }
    }

    // Close user results when clicking outside
    document.addEventListener('click', function(e) {
        const userSearchContainer = document.querySelector('.user-search-container');
        if (!userSearchContainer.contains(e.target)) {
            document.getElementById('userResults').style.display = 'none';
        }
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

function validateAge(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    const age = parseInt(field.value);
    
    if (field.value.trim() === '') {
        showError(field, errorElement, 'Age is required');
        return false;
    } else if (isNaN(age) || age < 0 || age > 150) {
        showError(field, errorElement, 'Please enter a valid age (0-150)');
        return false;
    } else {
        hideError(field, errorElement);
        return true;
    }
}

function validateTextarea(field) {
    const errorElement = field.parentElement.querySelector('.field-error');
    hideError(field, errorElement);
    return true;
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

// Urgency level selection
function selectUrgency(level) {
    const options = document.querySelectorAll('.urgency-option');
    options.forEach(option => {
        option.classList.remove('selected');
    });
    
    const selectedOption = document.querySelector(`.urgency-${level}`);
    selectedOption.classList.add('selected');
    
    const hiddenInput = document.getElementById('urgency_level');
    hiddenInput.value = level;
    
    // Validate the field
    validateField(hiddenInput);
}

// User search functionality
function searchUsers(query) {
    const resultsContainer = document.getElementById('userResults');
    const userSearchInput = document.getElementById('userSearch');
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    const filteredUsers = availableUsers.filter(user => {
        const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
        const email = user.email.toLowerCase();
        const searchTerm = query.toLowerCase();
        
        return fullName.includes(searchTerm) || email.includes(searchTerm);
    });
    
    displayUserResults(filteredUsers);
}

function displayUserResults(users) {
    const resultsContainer = document.getElementById('userResults');
    
    if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="no-results">No users found</div>';
    } else {
        resultsContainer.innerHTML = users.map(user => `
            <div class="user-result" onclick="selectUser(${user.id}, '${user.first_name} ${user.last_name}', '${user.email}')">
                <div class="user-name">${user.first_name} ${user.last_name}</div>
                <div class="user-email">${user.email}</div>
            </div>
        `).join('');
    }
    
    resultsContainer.style.display = 'block';
}

function selectUser(userId, userName, userEmail) {
    const receivingUserIdInput = document.getElementById('receiving_user_id');
    const userSearchInput = document.getElementById('userSearch');
    const selectedUserDiv = document.getElementById('selectedUser');
    const selectedUserName = document.getElementById('selectedUserName');
    const selectedUserEmail = document.getElementById('selectedUserEmail');
    const resultsContainer = document.getElementById('userResults');
    
    // Set the hidden input value
    receivingUserIdInput.value = userId;
    
    // Update selected user display
    selectedUserName.textContent = userName;
    selectedUserEmail.textContent = userEmail;
    selectedUserDiv.style.display = 'block';
    
    // Clear search input and hide results
    userSearchInput.value = '';
    resultsContainer.style.display = 'none';
    
    // Validate the field
    validateField(receivingUserIdInput);
}

function clearUserSelection() {
    const receivingUserIdInput = document.getElementById('receiving_user_id');
    const selectedUserDiv = document.getElementById('selectedUser');
    const userSearchInput = document.getElementById('userSearch');
    
    receivingUserIdInput.value = '';
    selectedUserDiv.style.display = 'none';
    userSearchInput.value = '';
    
    // Clear validation
    const errorElement = receivingUserIdInput.parentElement.querySelector('.field-error');
    hideError(receivingUserIdInput, errorElement);
}

// Consent validation
function validateConsent() {
    const consentCheckbox = document.getElementById('consent');
    const errorElement = document.querySelector('#consent').closest('.form-section').querySelector('.field-error');
    
    if (!consentCheckbox.checked) {
        showError(consentCheckbox, errorElement, 'You must provide consent to submit the referral');
        return false;
    } else {
        hideError(consentCheckbox, errorElement);
        return true;
    }
}

// Form submission validation
document.getElementById('referralForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate all required fields
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (field.type === 'checkbox') {
            if (!validateConsent()) isValid = false;
        } else if (field.type === 'number') {
            if (!validateAge(field)) isValid = false;
        } else if (field.tagName === 'SELECT') {
            if (!validateField(field)) isValid = false;
        } else if (field.id === 'receiving_user_id') {
            if (!validateField(field)) isValid = false;
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
        submitButton.innerHTML = 'Submitting Referral...';
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

    // Initialize urgency level if already selected
    const urgencyLevel = document.getElementById('urgency_level').value;
    if (urgencyLevel) {
        selectUrgency(urgencyLevel);
    }

    // Initialize selected user if already set
    const receivingUserId = document.getElementById('receiving_user_id').value;
    if (receivingUserId) {
        const selectedUser = availableUsers.find(user => user.id == receivingUserId);
        if (selectedUser) {
            selectUser(selectedUser.id, `${selectedUser.first_name} ${selectedUser.last_name}`, selectedUser.email);
        }
    }

    // Close user results when clicking outside
    document.addEventListener('click', function(e) {
        const userSearchContainer = document.querySelector('.user-search-container');
        if (!userSearchContainer.contains(e.target)) {
            document.getElementById('userResults').style.display = 'none';
        }
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