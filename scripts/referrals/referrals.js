function toggleDetails(referralId) {
    const details = document.getElementById('details-' + referralId);
    details.style.display = details.style.display === 'none' ? 'block' : 'none';
}

function toggleForm(formId) {
    const form = document.getElementById(formId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function showEditForm(referralId) {
    // Close details view
    toggleDetails(referralId);
    // Open edit form
    toggleForm('edit-form-' + referralId);
}

function showResendForm(referralId) {
    // Close details view
    toggleDetails(referralId);
    // Open resend form
    toggleForm('resend-form-' + referralId);
}

// Hide all forms and details by default on page load
document.addEventListener('DOMContentLoaded', function() {
    const allForms = document.querySelectorAll('.edit-form, .view-details');
    allForms.forEach(form => {
        form.style.display = 'none';
    });
});