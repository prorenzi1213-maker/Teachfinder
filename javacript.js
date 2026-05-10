/**
 * TeachFinder Dashboard Logic
 * Handles real-time search, UI interactions, and currency formatting
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Real-time Tutor Search Filter
    const searchInput = document.getElementById('tutorSearch');
    const tutorItems = document.querySelectorAll('.tutor-item');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();

            tutorItems.forEach(item => {
                const name = item.querySelector('.tutor-name').textContent.toLowerCase();
                const subject = item.querySelector('.tutor-subject').textContent.toLowerCase();
                
                // Show/Hide with a simple CSS fade effect
                if (name.includes(term) || subject.includes(term)) {
                    item.style.display = "block";
                    item.style.opacity = "1";
                } else {
                    item.style.display = "none";
                    item.style.opacity = "0";
                }
            });
        });
    }

    // 2. Format Currency Inputs (For Checkout Page)
    const amountInput = document.getElementById('customAmount');
    if (amountInput) {
        amountInput.addEventListener('blur', function() {
            // Automatically format to 2 decimal places (e.g., 25 -> 25.00)
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    }

    // 3. Simple Notification Handler
    // Automatically hide Bootstrap alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // 4. Button Loading State
    // Adds a spinner to buttons when clicked to prevent double-submits
    const actionButtons = document.querySelectorAll('.btn-load');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Loading...`;
            
            // Optional: Re-enable if it's not a page redirect
            // setTimeout(() => { this.disabled = false; this.innerHTML = originalText; }, 3000);
        });
    });
});

/**
 * Utility Function: Show a quick Toast notification
 * Usage: showToast("Payment Successful!", "success");
 */
function showToast(message, type = 'info') {
    console.log(`[TeachFinder] ${type.toUpperCase()}: ${message}`);
    // You can integrate a library like Toastify or use native Bootstrap Toasts here
}