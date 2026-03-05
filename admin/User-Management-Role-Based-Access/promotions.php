<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

$current_role    = $_SESSION['userdata']['role'] ?? '';
$current_user_id = (int)($_SESSION['userdata']['user_id'] ?? 0);
$is_admin        = in_array($current_role, ['Super Admin', 'Admin']);

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<style>
:root { --brand: #0d6efd; }

.main-content {
    padding: 1.5rem;
    min-height: 100vh;
    background: #f8f9fa;
}


.page-header {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    padding: 1.75rem 2rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    color: white;
    box-shadow: 0 4px 15px rgba(13,110,253,0.3);
}
.page-header h4 { margin: 0; font-size: 1.6rem; font-weight: 700; }
.page-header .subtitle { opacity: .8; font-size: .9rem; margin-top: .25rem; }

.stat-card {
    background: white;
    border-radius: .75rem;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.stat-card .icon { font-size: 2rem; width: 48px; text-align: center; }
.stat-card .val  { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.stat-card .lbl  { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }

.table-card {
    background: white;
    border-radius: .75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}
.table-card-header {
    padding: 1rem 1.5rem;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
}
.table-card-header h6 { margin: 0; font-weight: 700; }

.badge-pending  { background: #fef9c3; color: #854d0e; }
.badge-approved { background: #dcfce7; color: #166534; }
.badge-rejected { background: #fee2e2; color: #991b1b; }

.filter-bar {
    background: white;
    border-radius: .75rem;
    padding: 1rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    border: 1px solid #e5e7eb;
    margin-bottom: 1rem;
}

/* Staff request card */
.request-form-card {
    background: white;
    border-radius: .75rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
}

.type-selector .type-btn {
    border: 2px solid #e5e7eb;
    border-radius: .75rem;
    padding: .75rem 1.25rem;
    cursor: pointer;
    text-align: center;
    transition: all .2s;
    background: white;
}
.type-selector .type-btn:hover,
.type-selector .type-btn.active {
    border-color: #0d6efd;
    background: #eff6ff;
    color: #0d6efd;
}
.type-selector .type-btn .icon { font-size: 1.5rem; display: block; margin-bottom: .3rem; }
</style>

<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4>🏅 Staff Promotions</h4>
                <p class="subtitle mb-0">
                    <?= $is_admin
                        ? 'Review and manage staff promotion and position change requests'
                        : 'Submit a role promotion or position change request' ?>
                </p>
            </div>
            <?php if ($is_admin): ?>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-light" onclick="loadRequests()">
                    <i class="bi bi-arrow-repeat me-1"></i>Refresh
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- Admin Summary Cards -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon">⏳</div>
                <div>
                    <div class="val text-warning" id="cnt_pending">—</div>
                    <div class="lbl">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon">✅</div>
                <div>
                    <div class="val text-success" id="cnt_approved">—</div>
                    <div class="lbl">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon">❌</div>
                <div>
                    <div class="val text-danger" id="cnt_rejected">—</div>
                    <div class="lbl">Rejected</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon">📋</div>
                <div>
                    <div class="val text-primary" id="cnt_total">—</div>
                    <div class="lbl">Total Requests</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar d-flex gap-3 flex-wrap align-items-end">
        <div>
            <label class="form-label fw-semibold small mb-1">Status</label>
            <select class="form-select form-select-sm" id="filterStatus" onchange="loadRequests()">
                <option value="">All Statuses</option>
                <option value="pending">⏳ Pending</option>
                <option value="approved">✅ Approved</option>
                <option value="rejected">❌ Rejected</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-semibold small mb-1">Type</label>
            <select class="form-select form-select-sm" id="filterType" onchange="loadRequests()">
                <option value="">All Types</option>
                <option value="role_promotion">🏅 Role Promotion</option>
                <option value="position_change">🪪 Position Change</option>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('filterStatus').value='';document.getElementById('filterType').value='';loadRequests();">
            Clear
        </button>
    </div>

    <?php else: ?>

    <!-- Staff: Submit Request Card -->
    <div class="request-form-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-send me-2 text-primary"></i>Submit a New Request</h6>

        <!-- Request Type Selector -->
        <div class="row g-3 type-selector mb-4" id="typeSelector">
            <div class="col-6">
                <div class="type-btn active" id="typeRoleBtn" onclick="setType('role_promotion')">
                    <span class="icon">🏅</span>
                    <div class="fw-bold small">Role Promotion</div>
                    <div class="text-muted" style="font-size:.75rem;">Request a promotion to Admin</div>
                </div>
            </div>
            <div class="col-6">
                <div class="type-btn" id="typePosBtn" onclick="setType('position_change')">
                    <span class="icon">🪪</span>
                    <div class="fw-bold small">Position Change</div>
                    <div class="text-muted" style="font-size:.75rem;">Change your job position/title</div>
                </div>
            </div>
        </div>

        <input type="hidden" id="req_type" value="role_promotion">

        <!-- Role Promotion Fields -->
        <div id="roleFields">
            <div class="mb-3">
                <label class="form-label fw-semibold">Requested Role</label>
                <select class="form-select" id="requested_role">
                    <option value="Admin">Admin</option>
                    <option value="Super Admin">Super Admin</option>
                </select>
            </div>
        </div>

        <!-- Position Change Fields -->
        <div id="positionFields" style="display:none;">
            <div class="mb-3">
                <label class="form-label fw-semibold">Requested Position</label>
                <select class="form-select" id="requested_position">
                    <option value="">-- Select Position --</option>
                    <optgroup label="Branch Operations">
                    <option value="Branch Manager">Branch Manager</option>
                    </optgroup>
                    <optgroup label="Loan Processing">
                    <option value="Loan Officer">Loan Officer</option>
                    <option value="Account Officer">Account Officer</option>
                    <option value="Loan Processor">Loan Processor</option>
                    <option value="Credit Investigator">Credit Investigator</option>
                    </optgroup>
                    <optgroup label="Collections &amp; Disbursement">
                    <option value="Collection Officer">Collection Officer</option>
                    <option value="Cashier">Cashier</option>
                    <option value="Teller">Teller</option>
                    </optgroup>
                    <optgroup label="Compliance &amp; Administration">
                    <option value="Compliance Officer">Compliance Officer</option>
                    <option value="System Administrator">System Administrator</option>
                    <option value="Bookkeeper">Bookkeeper</option>
                    </optgroup>
                    <optgroup label="Field &amp; Support">
                    <option value="Field Officer">Field Officer</option>
                    <option value="Customer Service">Customer Service</option>
                    <option value="Encoder">Encoder</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Reason / Justification <span class="text-danger">*</span></label>
            <textarea class="form-control" id="req_reason" rows="3"
                placeholder="Explain why you are requesting this change..."></textarea>
        </div>

        <button class="btn btn-primary" onclick="submitRequest()">
            <i class="bi bi-send me-2"></i>Submit Request
        </button>
    </div>

    <?php endif; ?>

    <!-- Requests Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h6><i class="bi bi-list-check me-2 text-primary"></i>
                <?= $is_admin ? 'All Promotion Requests' : 'My Requests' ?>
            </h6>
            <span class="text-muted small" id="reqCount"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <?php if ($is_admin): ?><th>Staff</th><?php endif; ?>
                        <th>Type</th>
                        <th>Request</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="text-center">Status</th>
                        <?php if ($is_admin): ?><th>Reviewed By</th><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="reqTableBody">
                    <tr><td colspan="8" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:.75rem;">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#047857);color:white;border-radius:.75rem .75rem 0 0;">
                <h6 class="modal-title fw-bold"><i class="bi bi-check-circle me-2"></i>Confirm Approval</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-success small mb-3" id="approveInfo"></div>
                <label class="form-label fw-semibold small">Review Notes (optional)</label>
                <textarea class="form-control" id="approveNotes" rows="2" placeholder="Add notes...">Approved</textarea>
                <input type="hidden" id="approveReqId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="doApprove()">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:.75rem;">
            <div class="modal-header" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:white;border-radius:.75rem .75rem 0 0;">
                <h6 class="modal-title fw-bold"><i class="bi bi-x-circle me-2"></i>Reject Request</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger small mb-3" id="rejectInfo"></div>
                <label class="form-label fw-semibold small">Reason for Rejection <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rejectNotes" rows="3" placeholder="Explain why this request is being rejected..."></textarea>
                <input type="hidden" id="rejectReqId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="doReject()">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

// ── Type Selector (Staff) ──────────────────────────────────
function setType(type) {
    document.getElementById('req_type').value = type;
    document.getElementById('typeRoleBtn').classList.toggle('active', type === 'role_promotion');
    document.getElementById('typePosBtn').classList.toggle('active', type === 'position_change');
    document.getElementById('roleFields').style.display     = type === 'role_promotion' ? 'block' : 'none';
    document.getElementById('positionFields').style.display = type === 'position_change' ? 'block' : 'none';
}

// ── Submit Request (Staff) ─────────────────────────────────
function submitRequest() {
    const type   = document.getElementById('req_type').value;
    const reason = document.getElementById('req_reason').value.trim();

    if (!reason) {
        Swal.fire('Missing Info', 'Please provide a reason for your request.', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('request_type', type);
    fd.append('reason', reason);

    if (type === 'role_promotion') {
        fd.append('requested_role', document.getElementById('requested_role').value);
    } else {
        const pos = document.getElementById('requested_position').value;
        if (!pos) { Swal.fire('Missing Info', 'Please select a position.', 'warning'); return; }
        fd.append('requested_position', pos);
    }

    Swal.fire({ title: 'Submitting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('promotion_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            Swal.close();
            if (res.success) {
                Swal.fire('Submitted! 🎉', res.msg, 'success');
                document.getElementById('req_reason').value = '';
                loadRequests();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to submit request.', 'error'));
}

// ── Load Requests ──────────────────────────────────────────
function loadRequests() {
    const tbody = document.getElementById('reqTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';

    let url = 'promotion_action.php?action=get_requests';
    if (IS_ADMIN) {
        const s = document.getElementById('filterStatus')?.value || '';
        const t = document.getElementById('filterType')?.value || '';
        if (s) url += '&status=' + encodeURIComponent(s);
        if (t) url += '&type=' + encodeURIComponent(t);
    }

    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${esc(data.msg)}</td></tr>`; return; }

            const reqs = data.requests || [];
            document.getElementById('reqCount').textContent = reqs.length + ' request(s)';

            if (IS_ADMIN && data.counts) {
                document.getElementById('cnt_pending').textContent  = data.counts.pending  || 0;
                document.getElementById('cnt_approved').textContent = data.counts.approved || 0;
                document.getElementById('cnt_rejected').textContent = data.counts.rejected || 0;
                document.getElementById('cnt_total').textContent    = reqs.length;
            }

            if (!reqs.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No requests found</td></tr>';
                return;
            }

            tbody.innerHTML = reqs.map(r => {
                const typeLabel = r.request_type === 'role_promotion'
                    ? '<span class="badge" style="background:#dbeafe;color:#1e40af;">🏅 Role Promotion</span>'
                    : '<span class="badge" style="background:#f3e8ff;color:#6b21a8;">🪪 Position Change</span>';

                const requestDetail = r.request_type === 'role_promotion'
                    ? `<span class="text-muted small">${esc(r.current_role)}</span> → <strong>${esc(r.requested_role)}</strong>`
                    : `<span class="text-muted small">${esc(r.current_position || '—')}</span> → <strong>${esc(r.requested_position)}</strong>`;

                const statusBadge = {
                    pending:  '<span class="badge badge-pending">⏳ Pending</span>',
                    approved: '<span class="badge badge-approved">✅ Approved</span>',
                    rejected: '<span class="badge badge-rejected">❌ Rejected</span>'
                }[r.status] || r.status;

                const dateStr = r.request_date ? r.request_date.substring(0, 10) : '—';

                let actions = '';
                if (IS_ADMIN && r.status === 'pending') {
                    actions = `
                        <button class="btn btn-sm btn-success" onclick="openApprove(${r.request_id}, '${esc(r.staff_name)}', '${esc(r.requested_role || r.requested_position)}')">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button class="btn btn-sm btn-danger ms-1" onclick="openReject(${r.request_id}, '${esc(r.staff_name)}')">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>`;
                } else if (IS_ADMIN && r.admin_notes) {
                    actions = `<small class="text-muted">${esc(r.admin_notes)}</small>`;
                }

                return `<tr>
                    ${IS_ADMIN ? `<td>
                        <div class="fw-semibold small">${esc(r.staff_name)}</div>
                        <div class="text-muted" style="font-size:.75rem;">@${esc(r.staff_username)}</div>
                    </td>` : ''}
                    <td>${typeLabel}</td>
                    <td>${requestDetail}</td>
                    <td class="small text-muted" style="max-width:200px;">${esc(r.reason)}</td>
                    <td class="small">${dateStr}</td>
                    <td class="text-center">${statusBadge}</td>
                    ${IS_ADMIN ? `<td class="small">${esc(r.reviewer_name || '—')}</td><td class="text-center">${actions}</td>` : ''}
                </tr>`;
            }).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Failed to load requests.</td></tr>';
        });
}

// ── Approve / Reject (Admin) ───────────────────────────────
function openApprove(id, name, to) {
    document.getElementById('approveReqId').value = id;
    document.getElementById('approveNotes').value  = 'Approved';
    document.getElementById('approveInfo').innerHTML =
        `Approving request for <strong>${esc(name)}</strong> → <strong>${esc(to)}</strong>.<br>
        <small>This will immediately update their account.</small>`;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function doApprove() {
    const id    = document.getElementById('approveReqId').value;
    const notes = document.getElementById('approveNotes').value.trim() || 'Approved';

    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('request_id', id);
    fd.append('admin_notes', notes);

    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('promotion_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            bootstrap.Modal.getInstance(document.getElementById('approveModal'))?.hide();
            if (res.success) {
                Swal.fire('Approved! ✅', res.msg, 'success');
                loadRequests();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to approve.', 'error'));
}

function openReject(id, name) {
    document.getElementById('rejectReqId').value = id;
    document.getElementById('rejectNotes').value  = '';
    document.getElementById('rejectInfo').innerHTML =
        `Rejecting request from <strong>${esc(name)}</strong>. Please provide a reason.`;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function doReject() {
    const id    = document.getElementById('rejectReqId').value;
    const notes = document.getElementById('rejectNotes').value.trim();

    if (!notes) {
        Swal.fire('Required', 'Please provide a reason for rejection.', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('request_id', id);
    fd.append('admin_notes', notes);

    fetch('promotion_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.hide();
            if (res.success) {
                Swal.fire('Rejected', res.msg, 'info');
                loadRequests();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to reject.', 'error'));
}

// ── Init ──────────────────────────────────────────────────
loadRequests();
</script>