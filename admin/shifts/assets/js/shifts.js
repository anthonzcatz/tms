// Cashier Shift Reports Module

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let sessionDetailModal;

document.addEventListener('DOMContentLoaded', function () {
    sessionDetailModal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));
});

async function viewSessionDetail(sessionId) {
    document.getElementById('sessionDetailSubtitle').textContent = 'Loading...';
    document.getElementById('sessionDetailContent').innerHTML =
        '<div class="text-center py-5"><span class="fas fa-spinner fa-spin fs-3"></span><p class="mt-2 text-muted">Loading session details...</p></div>';
    sessionDetailModal.show();

    try {
        const res = await fetch(`${window.BASE_URL}/api/shifts?session_id=${sessionId}`);
        const result = await res.json();
        if (!result.success) {
            document.getElementById('sessionDetailContent').innerHTML = '<p class="text-danger text-center py-3">Failed to load session details.</p>';
            return;
        }

        const s = result.data.session;
        const txns = result.data.transactions;
        const payments = result.data.payments;

        document.getElementById('sessionDetailSubtitle').textContent =
            `${s.cashier_name} · ${s.branch_name ?? '—'} · ${new Date(s.started_at).toLocaleDateString()}`;

        let html = '';

        // Session summary
        const variance = parseFloat(s.cash_variance ?? 0);
        const varText = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
        const varClass = variance > 0.005 ? 'text-warning' : variance < -0.005 ? 'text-danger' : 'text-success';
        const varLabel = Math.abs(variance) < 0.005 ? 'Balanced' : variance < 0 ? 'Short' : 'Over';

        html += `
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
              <div class="text-muted small">Opening Cash</div>
              <div class="fw-bold fs-5">₱${fmt(s.starting_cash)}</div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
              <div class="text-muted small">Total Sales</div>
              <div class="fw-bold fs-5 text-success">₱${fmt(s.total_sales)}</div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
              <div class="text-muted small">Expected / Actual Cash</div>
              <div class="fw-bold">₱${fmt(s.expected_cash)} / ₱${fmt(s.actual_cash)}</div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
              <div class="text-muted small">Variance</div>
              <div class="fw-bold fs-5 ${varClass}">${varText}
                <span class="badge bg-soft-${Math.abs(variance) < 0.005 ? 'success text-success' : variance < 0 ? 'danger text-danger' : 'warning text-warning'} ms-1 small">${varLabel}</span>
              </div>
            </div>
          </div>
        </div>`;

        // Payment breakdown
        if (payments && payments.length > 0) {
            html += '<h6 class="fw-bold mb-2"><span class="fas fa-money-bill-wave me-2 text-success"></span>Payment Breakdown</h6>';
            html += '<div class="table-responsive mb-4"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Method</th><th>Type</th><th class="text-end">Amount</th></tr></thead><tbody>';
            payments.forEach(p => {
                html += `<tr>
                    <td>${p.method_name}</td>
                    <td><span class="badge bg-soft-primary text-primary">${p.method_type}</span></td>
                    <td class="text-end fw-semibold text-success">₱${fmt(p.total_amount)}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        }

        // Transactions
        if (txns && txns.length > 0) {
            html += `<h6 class="fw-bold mb-2"><span class="fas fa-receipt me-2 text-primary"></span>Transactions (${txns.length})</h6>`;
            html += '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Code</th><th>Service</th><th>Qty</th><th class="text-end">Total</th><th>Time</th></tr></thead><tbody>';
            txns.forEach(t => {
                html += `<tr>
                    <td><code>${t.transaction_code}</code></td>
                    <td>${t.service_type_name ?? '—'}</td>
                    <td>${t.quantity}</td>
                    <td class="text-end text-success fw-semibold">₱${fmt(t.total_amount)}</td>
                    <td class="text-muted small">${new Date(t.created_at).toLocaleTimeString()}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="text-muted text-center py-3">No transactions recorded in this session.</p>';
        }

        document.getElementById('sessionDetailContent').innerHTML = html;
    } catch (e) {
        document.getElementById('sessionDetailContent').innerHTML = '<p class="text-danger text-center py-3">Error loading session details.</p>';
    }
}

function showToast(type, title, message) {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = `custom-toast alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top:80px;right:20px;z-index:9999;min-width:350px;max-width:450px;box-shadow:0 4px 12px rgba(0,0,0,.15);border-radius:8px;';
    const icon = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
    toast.innerHTML = `<div class="d-flex align-items-center"><span class="fas ${icon} me-3 fs-4"></span><div class="flex-grow-1"><strong class="d-block">${title}</strong><span class="d-block text-sm">${message}</span></div><button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 150); }, 4000);
}
