// Search users function
function searchUsers(query, referralId) {
    const resultsContainer = document.getElementById('search_results_' + referralId);
    const selectedUserDiv = document.getElementById('selected_user_' + referralId);
    const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
    const submitButton = document.getElementById('resend_submit_' + referralId);
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    const filteredUsers = availableUsers.filter(user => {
        const fullName = user.first_name + ' ' + user.last_name;
        return fullName.toLowerCase().includes(query.toLowerCase()) || 
               user.email.toLowerCase().includes(query.toLowerCase());
    });
    
    displaySearchResults(filteredUsers, referralId);
}

// Display search results
function displaySearchResults(users, referralId) {
    const resultsContainer = document.getElementById('search_results_' + referralId);
    resultsContainer.innerHTML = '';
    
    if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="search-result-item">No users found</div>';
    } else {
        users.forEach(user => {
            const userElement = document.createElement('div');
            userElement.className = 'search-result-item';
            userElement.innerHTML = `
                <div class="user-display-name">${user.first_name} ${user.last_name}</div>
                <div class="user-email">${user.email}</div>
            `;
            userElement.onclick = () => selectUser(user, referralId);
            resultsContainer.appendChild(userElement);
        });
    }
    
    resultsContainer.style.display = 'block';
}

// Select user from search results
function selectUser(user, referralId) {
    const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
    const selectedUserDiv = document.getElementById('selected_user_' + referralId);
    const selectedUserName = document.getElementById('selected_user_name_' + referralId);
    const searchInput = document.getElementById('user_search_' + referralId);
    const resultsContainer = document.getElementById('search_results_' + referralId);
    const submitButton = document.getElementById('resend_submit_' + referralId);
    
    userIdInput.value = user.id;
    selectedUserName.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
    selectedUserDiv.style.display = 'block';
    searchInput.value = `${user.first_name} ${user.last_name}`;
    resultsContainer.style.display = 'none';
    submitButton.disabled = false;
}

// Toggle view details
function toggleDetails(elementId) {
    const element = document.getElementById(elementId);
    if (element.style.display === 'block') {
        element.style.display = 'none';
    } else {
        // Hide all other details first
        document.querySelectorAll('.view-details').forEach(detail => {
            detail.style.display = 'none';
        });
        element.style.display = 'block';
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Toggle form visibility
function toggleForm(formId) {
    const form = document.getElementById(formId);
    if (form.style.display === 'block') {
        form.style.display = 'none';
        // Reset search fields when closing
        if (formId.includes('resend-form')) {
            const referralId = formId.split('_').pop();
            resetSearchFields(referralId);
        }
    } else {
        // Hide all other forms first
        document.querySelectorAll('.edit-form').forEach(form => {
            form.style.display = 'none';
        });
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Reset search fields
function resetSearchFields(referralId) {
    const userIdInput = document.getElementById('new_receiving_user_id_' + referralId);
    const selectedUserDiv = document.getElementById('selected_user_' + referralId);
    const searchInput = document.getElementById('user_search_' + referralId);
    const resultsContainer = document.getElementById('search_results_' + referralId);
    const submitButton = document.getElementById('resend_submit_' + referralId);
    
    userIdInput.value = '';
    selectedUserDiv.style.display = 'none';
    searchInput.value = '';
    resultsContainer.style.display = 'none';
    submitButton.disabled = true;
}

// Toggle custom date inputs
function toggleCustomDates() {
    const dateRange = document.getElementById('date_range').value;
    const customDates = document.getElementById('custom_dates');
    customDates.style.display = dateRange === 'custom' ? 'flex' : 'none';
}

// Show edit form from view details
function showEditForm(referralId) {
    toggleDetails('outgoing-' + referralId);
    setTimeout(() => {
        toggleForm('edit-form-' + referralId);
    }, 300);
}

// Show resend form from view details
function showResendForm(referralId) {
    toggleDetails('outgoing-' + referralId);
    setTimeout(() => {
        toggleForm('resend-form-' + referralId);
    }, 300);
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.matches('.search-input')) {
        document.querySelectorAll('.search-results').forEach(container => {
            container.style.display = 'none';
        });
    }
});

// Add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    // Add fade-in animation to elements
    const elements = document.querySelectorAll('.stat-card, .section');
    elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
        element.classList.add('fade-in');
    });

    // Add confirmation for destructive actions
    const denyButtons = document.querySelectorAll('.confirm-deny-btn');
    denyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to decline this referral? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Auto-close messages after 5 seconds
    const messages = document.querySelectorAll('.message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);
    });

    // Initialize custom date toggle
    toggleCustomDates();
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