// Add smooth animations and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });

    // Add click animation to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.href && this.href.includes('logout')) {
                if (!confirm('Are you sure you want to log out?')) {
                    e.preventDefault();
                }
            }
        });
    });

    // Auto-refresh page every 60 seconds to update alert counts
    setInterval(() => {
        location.reload();
    }, 60000);
    
    // Validate capacity form
    const capacityForm = document.querySelector('form[method="POST"]');
    if (capacityForm) {
        capacityForm.addEventListener('submit', function(e) {
            const total = parseInt(document.getElementById('total_capacity').value);
            const available = parseInt(document.getElementById('available_capacity').value);
            
            if (total < 0 || available < 0) {
                e.preventDefault();
                alert('Capacity values cannot be negative!');
                return false;
            }
            
            if (available > total) {
                e.preventDefault();
                alert('Available capacity cannot exceed total capacity!');
                return false;
            }
        });
    }
});

// Capacity modal functions
function openCapacityModal() {
    document.getElementById('capacityModal').style.display = 'flex';
}

function closeCapacityModal() {
    document.getElementById('capacityModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('capacityModal');
    if (e.target === modal) {
        closeCapacityModal();
    }
});

// Add fade-in animation
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