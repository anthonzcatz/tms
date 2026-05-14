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
            `${s.cashier_name} · ${s.branch_name ?? '—'} · ${s.session_code || 'SES-' + s.session_id}`;

        let html = '';

        // Session info header
        const startedDate = new Date(s.started_at);
        const endedDate = s.ended_at ? new Date(s.ended_at) : null;
        const startedFmt = startedDate.toLocaleString('en-PH', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        const endedFmt = endedDate ? endedDate.toLocaleString('en-PH', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        }) : '—';

        const statusColor = {'OPEN': 'success', 'CLOSED': 'primary', 'RECONCILED': 'purple'}[s.status] || 'secondary';
        const statusIcon = {'OPEN': 'fa-circle', 'CLOSED': 'fa-check-circle', 'RECONCILED': 'fa-star'}[s.status] || 'fa-circle';

        html += `
        <div class="card mb-4">
          <div class="card-body py-3">
            <div class="row g-3 align-items-center">
              <div class="col-md-3">
                <div class="text-muted small mb-1">Status</div>
                <div class="fw-bold"><span class="badge bg-soft-${statusColor} text-${statusColor}"><span class="fas ${statusIcon} me-1"></span>${s.status}</span></div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small mb-1">Started</div>
                <div class="fw-bold">${startedFmt}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small mb-1">Ended</div>
                <div class="fw-bold">${endedFmt}</div>
              </div>
            </div>
          </div>
        </div>`;

        // Session summary
        const variance = parseFloat(s.cash_variance ?? 0);
        const varText = (variance >= 0 ? '+₱' : '-₱') + fmt(Math.abs(variance));
        const varClass = variance > 0.005 ? 'text-warning' : variance < -0.005 ? 'text-danger' : 'text-success';
        const varLabel = Math.abs(variance) < 0.005 ? 'Balanced' : variance < 0 ? 'Short' : 'Over';

        // Calculate expected cash if not set (for OPEN sessions)
        const expectedCash = parseFloat(s.expected_cash) > 0 ? s.expected_cash : (parseFloat(s.starting_cash) + parseFloat(s.total_sales));

        html += `
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="d-flex align-items-center">
                  <div class="icon-circle icon-circle-primary me-3"><span class="fas fa-wallet text-primary"></span></div>
                  <div>
                    <div class="text-muted small mb-1">Opening Cash</div>
                    <div class="fw-bold fs-5">₱${fmt(s.starting_cash)}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="d-flex align-items-center">
                  <div class="icon-circle icon-circle-success me-3"><span class="fas fa-chart-line text-success"></span></div>
                  <div>
                    <div class="text-muted small mb-1">Total Sales</div>
                    <div class="fw-bold fs-5 text-success">₱${fmt(s.total_sales)}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="text-muted small mb-1">Expected / Actual Cash</div>
                <div class="fw-bold">₱${fmt(expectedCash)} / ₱${fmt(s.actual_cash || 0)}</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card h-100">
              <div class="card-body py-3">
                <div class="text-muted small mb-1">Variance</div>
                <div class="fw-bold fs-5 ${varClass}">${varText}
                  <span class="badge bg-soft-${Math.abs(variance) < 0.005 ? 'success text-success' : variance < 0 ? 'danger text-danger' : 'warning text-warning'} ms-1 small">${varLabel}</span>
                </div>
              </div>
            </div>
          </div>
        </div>`;

        // Payment type breakdown - only show non-zero amounts
        const paymentTypes = [
            { label: 'Cash', value: s.total_cash, icon: 'fa-money-bill-wave', color: 'success' },
            { label: 'Bank Transfer', value: s.total_bank_transfer, icon: 'fa-university', color: 'info' },
            { label: 'E-Wallet', value: s.total_e_wallet, icon: 'fa-mobile-alt', color: 'primary' },
            { label: 'Charge', value: s.total_charge, icon: 'fa-credit-card', color: 'warning' },
            { label: 'Other', value: s.total_other, icon: 'fa-ellipsis-h', color: 'secondary' }
        ];
        const activePayments = paymentTypes.filter(p => parseFloat(p.value) > 0);

        if (activePayments.length > 0) {
            html += '<h6 class="fw-bold mb-3"><span class="fas fa-wallet me-2 text-primary"></span>Payment Type Breakdown</h6>';
            html += '<div class="row g-3 mb-4">';
            activePayments.forEach(p => {
                const colSize = activePayments.length > 3 ? 'col-6 col-md-4' : activePayments.length > 1 ? 'col-6 col-md-6' : 'col-12';
                html += `
                  <div class="${colSize}">
                    <div class="card h-100">
                      <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                          <div class="icon-circle icon-circle-${p.color} me-3"><span class="fas ${p.icon} text-${p.color}"></span></div>
                          <div>
                            <div class="text-muted small mb-1">${p.label}</div>
                            <div class="fw-semibold">₱${fmt(p.value)}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>`;
            });
            html += `
              <div class="${activePayments.length > 3 ? 'col-6 col-md-4' : activePayments.length > 1 ? 'col-6 col-md-6' : 'col-12'}">
                <div class="card h-100">
                  <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                      <div class="icon-circle icon-circle-success me-3"><span class="fas fa-chart-pie text-success"></span></div>
                      <div>
                        <div class="text-muted small mb-1">Total</div>
                        <div class="fw-bold text-success">₱${fmt(s.total_sales)}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>`;
        }

        // Notes if any
        if (s.notes) {
            html += `
            <div class="alert alert-info mb-4">
              <div class="fw-bold mb-1"><span class="fas fa-sticky-note me-2"></span>Notes</div>
              <div class="small">${s.notes}</div>
            </div>`;
        }

        // Reviewed by info
        if (s.reviewed_by_name || s.reviewed_at) {
            const reviewedAt = s.reviewed_at ? new Date(s.reviewed_at).toLocaleString('en-PH', { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            }) : '—';
            html += `
            <div class="alert alert-success mb-4">
              <div class="fw-bold mb-1"><span class="fas fa-user-check me-2"></span>Reviewed</div>
              <div class="small">
                By: ${s.reviewed_by_name || '—'}<br>
                At: ${reviewedAt}
              </div>
            </div>`;
        }

        // Payment breakdown
        if (payments && payments.length > 0) {
            html += '<h6 class="fw-bold mb-3"><span class="fas fa-money-bill-wave me-2 text-success"></span>Payment Breakdown</h6>';
            html += '<div class="table-responsive mb-4"><table class="table table-hover mb-0"><thead class="bg-light"><tr><th class="ps-3">Method</th><th>Type</th><th class="text-end pe-3">Amount</th></tr></thead><tbody>';
            payments.forEach(p => {
                html += `<tr>
                    <td class="ps-3">${p.method_name}</td>
                    <td><span class="badge bg-soft-primary text-primary">${p.method_type}</span></td>
                    <td class="text-end pe-3 fw-semibold text-success">₱${fmt(p.total_amount)}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        }

        // Transactions
        if (txns && txns.length > 0) {
            html += `<h6 class="fw-bold mb-3"><span class="fas fa-receipt me-2 text-primary"></span>Transactions (${txns.length})</h6>`;
            html += '<div class="table-responsive"><table class="table table-hover mb-0"><thead class="bg-light"><tr><th class="ps-3">Code</th><th>Service</th><th>Qty</th><th class="text-end">Total</th><th class="pe-3">Time</th></tr></thead><tbody>';
            txns.forEach(t => {
                html += `<tr>
                    <td class="ps-3"><code>${t.transaction_code}</code></td>
                    <td>${t.service_type_name ?? '—'}</td>
                    <td>${t.quantity}</td>
                    <td class="text-end fw-semibold text-success">₱${fmt(t.total_amount)}</td>
                    <td class="text-muted small pe-3">${new Date(t.created_at).toLocaleTimeString()}</td>
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
