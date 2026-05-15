// Refund Confirmations Module JavaScript

let confirmCancellationModal;
let currentCancellationId = null;

// Initialize modal on page load
document.addEventListener('DOMContentLoaded', function() {
    confirmCancellationModal = new bootstrap.Modal(document.getElementById('confirmCancellationModal'));
    
    // Restore filters from localStorage
    restoreFilters();
});

// Toggle "How it works" section
function toggleHowItWorks() {
    const content = document.getElementById('howItWorksContent');
    const icon = document.getElementById('howItWorksIcon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Apply filters
function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const date = document.getElementById('filterDate').value;
    
    const rows = document.querySelectorAll('.cancellation-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const rowStatus = row.dataset.status;
        const rowDate = row.dataset.date;
        const rowSearch = row.dataset.search;
        
        const statusMatch = status === 'all' || rowStatus === status;
        const dateMatch = !date || rowDate === date;
        const searchMatch = !search || rowSearch.includes(search);
        
        if (statusMatch && dateMatch && searchMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const noResultsMsg = document.getElementById('noResultsMsg');
    if (visibleCount === 0) {
        noResultsMsg.classList.remove('d-none');
    } else {
        noResultsMsg.classList.add('d-none');
    }
    
    // Save filters to localStorage
    saveFilters();
}

// Reset filters
function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = 'pending';
    document.getElementById('filterDate').value = '';
    applyFilters();
}

// Save filters to localStorage
function saveFilters() {
    const filters = {
        search: document.getElementById('filterSearch').value,
        status: document.getElementById('filterStatus').value,
        date: document.getElementById('filterDate').value
    };
    localStorage.setItem('refundConfirmationsFilters', JSON.stringify(filters));
}

// Restore filters from localStorage
function restoreFilters() {
    const savedFilters = localStorage.getItem('refundConfirmationsFilters');
    if (savedFilters) {
        const filters = JSON.parse(savedFilters);
        document.getElementById('filterSearch').value = filters.search || '';
        document.getElementById('filterStatus').value = filters.status || 'pending';
        document.getElementById('filterDate').value = filters.date || '';
        applyFilters();
    }
}

// Open confirmation modal
function openConfirmModal(cancellationId, transactionCode, refundAmount, cancellationType, reason, requestedBy, passenger, origin, destination, requestedAt) {
    currentCancellationId = cancellationId;
    
    document.getElementById('modalTransactionCode').textContent = transactionCode;
    document.getElementById('modalRefundAmount').textContent = '₱' + refundAmount;
    document.getElementById('modalCancellationType').textContent = cancellationType;
    document.getElementById('modalPassenger').textContent = passenger;
    document.getElementById('modalRoute').textContent = (origin && destination) ? origin + ' → ' + destination : '-';
    document.getElementById('modalRequestedBy').textContent = requestedBy;
    document.getElementById('modalReason').textContent = reason || 'No reason provided';
    document.getElementById('modalRequestedAt').textContent = requestedAt;
    
    // Reset action and rejection reason
    document.getElementById('modalAction').value = 'approve';
    document.getElementById('modalRejectionReason').value = '';
    document.getElementById('rejectionReasonDiv').style.display = 'none';
    
    // Add event listener to action select
    document.getElementById('modalAction').onchange = function() {
        const rejectionDiv = document.getElementById('rejectionReasonDiv');
        if (this.value === 'reject') {
            rejectionDiv.style.display = 'block';
        } else {
            rejectionDiv.style.display = 'none';
        }
    };
    
    confirmCancellationModal.show();
}

// Submit cancellation decision
function submitCancellationDecision() {
    const action = document.getElementById('modalAction').value;
    const rejectionReason = document.getElementById('modalRejectionReason').value.trim();
    
    if (action === 'reject' && !rejectionReason) {
        showToast('danger', 'Error', 'Please provide a rejection reason.');
        return;
    }
    
    const data = {
        cancellation_id: currentCancellationId,
        action: action,
        rejection_reason: rejectionReason || null
    };
    
    fetch(window.BASE_URL + '/api/pos/cancellation-approval.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('success', 'Success', result.message);
            confirmCancellationModal.hide();
            // Reload page to refresh data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('danger', 'Error', result.error || 'Failed to process cancellation decision.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('danger', 'Error', 'Failed to process cancellation decision.');
    });
}

// Show toast notification
function showToast(type, title, message) {
    // Check if toast container exists, if not create it
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-primary';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-times-circle' : 'fa-info-circle';
    
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <span class="fas ${icon} me-2"></span>
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}
