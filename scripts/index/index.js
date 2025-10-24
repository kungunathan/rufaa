// Home Dashboard JavaScript functionality

// Toggle mobile sidebar
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('active');
    }
});

// Handle alert badge clicks
document.addEventListener('DOMContentLoaded', function() {
    const alertBadge = document.querySelector('.alert-badge');
    if (alertBadge) {
        alertBadge.addEventListener('click', function() {
            // Navigate to alerts page or show alerts modal
            window.location.href = 'alerts.php';
        });
    }
    
    // Add click handlers for referral items
    const referralItems = document.querySelectorAll('.referral-item');
    referralItems.forEach(item => {
        item.addEventListener('click', function() {
            const referralCode = this.querySelector('.referral-code').textContent;
            // Navigate to referral details or show modal
            window.location.href = `referral_details.php?code=${referralCode}`;
        });
    });
});

// Refresh dashboard data periodically
function refreshDashboardData() {
    // This would typically make an AJAX call to update stats
    console.log('Refreshing dashboard data...');
}

// Auto-refresh every 5 minutes
setInterval(refreshDashboardData, 300000);

// Handle responsive behavior
function handleResize() {
    const sidebar = document.querySelector('.sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
    }
}

window.addEventListener('resize', handleResize);