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
    :root {
        --brand-primary: #0d6efd;
        --brand-hover:   #0a58ca;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
    }

    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f9fafb; }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        padding: 2rem; border-radius: 1rem; margin-bottom: 1.5rem;
        box-shadow: var(--shadow-lg); color: white;
    }
    .page-header h4 { margin: 0; font-size: 1.75rem; font-weight: 700; letter-spacing: -0.025em; }
    .page-header .subtitle { opacity: 0.9; font-size: 0.95rem; margin-top: 0.25rem; }

    /* Filter Section */
    .filter-section {
        background: white; padding: 1.5rem; border-radius: 1rem;
        margin-bottom: 1.5rem; box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .filter-section .form-label { font-weight: 600; color: #374151; font-size: 0.875rem; margin-bottom: 0.5rem; }
    .filter-section .form-control,
    .filter-section .form-select {
        border: 1.5px solid #e5e7eb; border-radius: 0.5rem;
        padding: 0.625rem 0.875rem; font-size: 0.875rem; transition: all 0.2s;
    }
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
    }

    /* Summary Cards */
    .summary-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1.25rem 1.5rem; border-radius: 1rem;
        transition: all 0.25s ease; box-shadow: var(--shadow-md); color: white;
    }
    .summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }
    .summary-icon  { font-size: 2.25rem; opacity: 0.9; }
    .summary-count { font-size: 2rem; font-weight: 800; line-height: 1; }
    .summary-label { font-size: 0.85rem; font-weight: 600; opacity: 0.9; margin-top: 0.25rem; }
    .pending-card  { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .approved-card { background: linear-gradient(135deg,#059669,#047857); }
    .rejected-card { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .total-card    { background: linear-gradient(135deg,#0d6efd,#0a58ca); }

    /* Table Card */
    .table-card {
        background: white; padding: 1.5rem; border-radius: 1rem;
        box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .table-card .table-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 2px solid #f3f4f6;
    }
    .table-title {
        font-weight: 700; color: #111827; font-size: 1.125rem; margin: 0;
        display: flex; align-items: center; gap: 0.5rem;
    }
    #recordInfo { color: #6b7280; font-size: 0.875rem; font-weight: 500; }
    .table-wrapper { overflow-x: auto; border-radius: 0.75rem; border: 1px solid #e5e7eb; }
    .table { margin-bottom: 0; }
    .table thead { background: #1f2937 !important; }
    .table thead th {
        color: white !important; font-weight: 700; font-size: 0.8rem;
        padding: 1rem 0.75rem; border: none; text-transform: uppercase; letter-spacing: 0.025em;
    }
    .table tbody tr { transition: all 0.2s ease; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background: #eff6ff !important; }
    .table tbody td { padding: 0.875rem 0.75rem; font-size: 0.875rem; color: #374151; vertical-align: middle; }

    /* Badges */
    .badge-pending  { background: #fef3c7; color: #92400e; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }
    .badge-approved { background: #d1fae5; color: #065f46; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }
    .badge-rejected { background: #fee2e2; color: #991b1b; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }
    .badge-role     { background: #dbeafe; color: #1e40af; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }
    .badge-position { background: #f3e8ff; color: #6b21a8; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }

    /* Buttons */
    .btn { border-radius: 0.5rem; font-weight: 600; transition: all 0.2s ease; }
    .btn:hover   { transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn-outline-light { border: 2px solid rgba(255,255,255,0.5); color: white; }
    .btn-outline-light:hover { background: rgba(255,255,255,0.2); border-color: white; color: white; }

    /* Submit form card */
    .form-card {
        background: white; padding: 1.5rem; border-radius: 1rem;
        box-shadow: var(--shadow-md); border: 1px solid #e5e7eb; margin-bottom: 1.5rem;
    }
    .type-btn {
        border: 2px solid #e5e7eb; border-radius: 0.75rem; padding: 0.75rem 1.25rem;
        cursor: pointer; text-align: center; transition: all 0.2s; background: white;
    }
    .type-btn:hover, .type-btn.active {
        border-color: #0d6efd; background: #eff6ff; color: #0d6efd;
    }
    .type-btn .icon { font-size: 1.5rem; display: block; margin-bottom: 0.3rem; }

    /* Pagination */
    .pagination-wrapper {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #f3f4f6;
        flex-wrap: wrap; gap: 0.5rem;
    }
    .pagination { margin-bottom: 0; }
    .pagination .page-link {
        border: 1.5px solid #e5e7eb; color: var(--brand-primary); margin: 0 0.125rem;
        border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; padding: 0.5rem 0.75rem; transition: all 0.2s;
    }
    .pagination .page-link:hover { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }
    .pagination .page-item.active .page-link { background: var(--brand-primary); border-color: var(--brand-primary); color: white; }
    .pagination .page-item.disabled .page-link { background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }
</style>

<div class="main-wrap">
<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-award me-2"></i>Staff Promotions</h4>
                <p class="subtitle mb-0">
                    <?= $is_admin
                        ? 'Review and manage staff promotion and position change requests'
                        : 'Submit a role promotion or position change request' ?>
                </p>
            </div>
            <?php if ($is_admin): ?>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-light" onclick="loadRequests()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Reload
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-card pending-card">
                <div class="summary-icon">⏳</div>
                <div>
                    <div class="summary-count" id="cnt_pending">—</div>
                    <div class="summary-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card approved-card">
                <div class="summary-icon">✅</div>
                <div>
                    <div class="summary-count" id="cnt_approved">—</div>
                    <div class="summary-label">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card rejected-card">
                <div class="summary-icon">❌</div>
                <div>
                    <div class="summary-count" id="cnt_rejected">—</div>
                    <div class="summary-label">Rejected</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card total-card">
                <div class="summary-icon">📋</div>
                <div>
                    <div class="summary-count" id="cnt_total">—</div>
                    <div class="summary-label">Total Requests</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-section">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="Search staff name, reason...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="pending">⏳ Pending</option>
                    <option value="approved">✅ Approved</option>
                    <option value="rejected">❌ Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" id="filterType">
                    <option value="">All Types</option>
                    <option value="role_promotion">🏅 Role Promotion</option>
                    <option value="position_change">🪪 Position Change</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rows per page</label>
                <select class="form-select" id="rowsPerPage">
                    <option value="10">10 rows</option>
                    <option value="25">25 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" onclick="loadRequests()">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <?php if (!$is_admin): ?>
    <!-- Staff: Submit Request Form -->
    <div class="form-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-send me-2 text-primary"></i>Submit a New Request</h6>
        <div class="row g-3 mb-4" id="typeSelector">
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
        <div id="roleFields" class="mb-3">
            <label class="form-label fw-semibold">Requested Role</label>
            <select class="form-select" id="requested_role">
                <option value="Admin">Admin</option>
                <option value="Super Admin">Super Admin</option>
            </select>
        </div>
        <div id="positionFields" class="mb-3" style="display:none;">
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
        <div class="mb-3">
            <label class="form-label fw-semibold">Reason / Justification <span class="text-danger">*</span></label>
            <textarea class="form-control" id="req_reason" rows="3" placeholder="Explain why you are requesting this change..."></textarea>
        </div>
        <button class="btn btn-primary" onclick="submitRequest()">
            <i class="bi bi-send me-2"></i>Submit Request
        </button>
    </div>
    <?php endif; ?>

    <!-- Requests Table -->
    <div class="table-card">
        <div class="table-header">
            <h5 class="table-title">
                <i class="bi bi-list-check text-primary"></i>
                <?= $is_admin ? 'All Promotion Requests' : 'My Requests' ?>
            </h5>
            <span id="recordInfo">Loading...</span>
        </div>
        <div class="table-wrapper">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($is_admin): ?><th>Staff</th><?php endif; ?>
                        <th>Type</th>
                        <th>Request</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Status</th>
                        <?php if ($is_admin): ?><th>Reviewed By</th><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="reqTableBody">
                    <tr><td colspan="9" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-wrapper" id="paginationWrapper" style="display:none;">
            <div id="paginationInfo" class="text-muted small"></div>
            <ul class="pagination" id="pagination"></ul>
        </div>
    </div>

</div>
</main>
</div>

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
                <button class="btn btn-success" onclick="doApprove()"><i class="bi bi-check-circle me-1"></i>Approve</button>
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
                <button class="btn btn-danger" onclick="doReject()"><i class="bi bi-x-circle me-1"></i>Reject</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;

// ── State ──────────────────────────────────────────────────
let allRequests = [];
let currentPage = 1;
let rowsPerPage  = 10;

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

// ── Clear Filters ──────────────────────────────────────────
function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterType').value   = '';
    document.getElementById('rowsPerPage').value  = '10';
    loadRequests();
}

// ── Submit Request (Staff) ─────────────────────────────────
function submitRequest() {
    const type   = document.getElementById('req_type').value;
    const reason = document.getElementById('req_reason').value.trim();
    if (!reason) { Swal.fire('Missing Info', 'Please provide a reason.', 'warning'); return; }

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

// ── Load Requests from server ──────────────────────────────
function loadRequests() {
    rowsPerPage  = parseInt(document.getElementById('rowsPerPage').value) || 10;
    currentPage  = 1;

    const tbody = document.getElementById('reqTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
    document.getElementById('recordInfo').textContent = 'Loading...';

    let url = 'promotion_action.php?action=get_requests';
    const status = document.getElementById('filterStatus').value;
    const type   = document.getElementById('filterType').value;
    if (status) url += '&status=' + encodeURIComponent(status);
    if (type)   url += '&type='   + encodeURIComponent(type);

    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${esc(data.msg)}</td></tr>`;
                document.getElementById('recordInfo').textContent = 'Error loading data';
                return;
            }

            // Update summary cards
            if (IS_ADMIN && data.counts) {
                document.getElementById('cnt_pending').textContent  = data.counts.pending  || 0;
                document.getElementById('cnt_approved').textContent = data.counts.approved || 0;
                document.getElementById('cnt_rejected').textContent = data.counts.rejected || 0;
                document.getElementById('cnt_total').textContent    = (data.requests || []).length;
            }

            // Apply client-side search filter
            const search = document.getElementById('filterSearch').value.trim().toLowerCase();
            allRequests = (data.requests || []).filter(r => {
                if (!search) return true;
                return (r.staff_name  || '').toLowerCase().includes(search)
                    || (r.reason      || '').toLowerCase().includes(search)
                    || (r.staff_username || '').toLowerCase().includes(search);
            });

            renderPage();
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load requests.</td></tr>';
            document.getElementById('recordInfo').textContent = 'Error';
        });
}

// ── Render current page ────────────────────────────────────
function renderPage() {
    const tbody  = document.getElementById('reqTableBody');
    const total  = allRequests.length;
    const pages  = Math.max(1, Math.ceil(total / rowsPerPage));
    if (currentPage > pages) currentPage = pages;

    const start  = (currentPage - 1) * rowsPerPage;
    const end    = Math.min(start + rowsPerPage, total);
    const slice  = allRequests.slice(start, end);

    document.getElementById('recordInfo').textContent =
        total > 0 ? `Showing ${start + 1} to ${end} of ${total} request(s)` : '0 requests found';

    if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No requests found</td></tr>';
        document.getElementById('paginationWrapper').style.display = 'none';
        return;
    }

    tbody.innerHTML = slice.map((r, i) => {
        const rowNum = start + i + 1;

        const typeLabel = r.request_type === 'role_promotion'
            ? '<span class="badge-role">🏅 Role Promotion</span>'
            : '<span class="badge-position">🪪 Position Change</span>';

        const requestDetail = r.request_type === 'role_promotion'
            ? `<span class="text-muted small">${esc(r.current_role || '—')}</span> → <strong>${esc(r.requested_role || '—')}</strong>`
            : `<span class="text-muted small">${esc(r.current_position || '—')}</span> → <strong>${esc(r.requested_position || '—')}</strong>`;

        const statusBadge = {
            pending:  '<span class="badge-pending">⏳ Pending</span>',
            approved: '<span class="badge-approved">✅ Approved</span>',
            rejected: '<span class="badge-rejected">❌ Rejected</span>'
        }[r.status] || `<span class="badge bg-secondary">${esc(r.status)}</span>`;

        const dateStr = r.request_date ? r.request_date.substring(0, 10) : '—';

        let actions = '—';
        if (IS_ADMIN && r.status === 'pending') {
            actions = `
                <button class="btn btn-sm btn-success me-1" onclick="openApprove(${r.request_id},'${esc(r.staff_name)}','${esc(r.requested_role || r.requested_position)}')">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="openReject(${r.request_id},'${esc(r.staff_name)}')">
                    <i class="bi bi-x-circle"></i> Reject
                </button>`;
        } else if (IS_ADMIN && r.admin_notes) {
            actions = `<small class="text-muted fst-italic">${esc(r.admin_notes)}</small>`;
        }

        return `<tr>
            <td class="text-muted small">${rowNum}</td>
            ${IS_ADMIN ? `<td>
                <div class="fw-semibold small">${esc(r.staff_name || '—')}</div>
                <div class="text-muted" style="font-size:.75rem;">@${esc(r.staff_username || '—')}</div>
            </td>` : ''}
            <td>${typeLabel}</td>
            <td>${requestDetail}</td>
            <td class="small text-muted" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${esc(r.reason)}">${esc(r.reason)}</td>
            <td class="small">${dateStr}</td>
            <td>${statusBadge}</td>
            ${IS_ADMIN ? `<td class="small">${esc(r.reviewer_name || '—')}</td><td class="text-center">${actions}</td>` : ''}
        </tr>`;
    }).join('');

    // Pagination
    renderPagination(total, pages);
}

function renderPagination(total, pages) {
    const wrapper = document.getElementById('paginationWrapper');
    const ul      = document.getElementById('pagination');
    const info    = document.getElementById('paginationInfo');
    const start   = (currentPage - 1) * rowsPerPage + 1;
    const end     = Math.min(currentPage * rowsPerPage, total);

    info.textContent = `Showing ${start}–${end} of ${total} entries`;

    if (pages <= 1) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = 'flex';

    let html = `<li class="page-item ${currentPage===1?'disabled':''}">
        <a class="page-link" href="#" onclick="goPage(${currentPage-1});return false;">«</a></li>`;

    for (let p = 1; p <= pages; p++) {
        if (pages > 7 && p > 2 && p < pages - 1 && Math.abs(p - currentPage) > 2) {
            if (p === 3 || p === pages - 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            continue;
        }
        html += `<li class="page-item ${p===currentPage?'active':''}">
            <a class="page-link" href="#" onclick="goPage(${p});return false;">${p}</a></li>`;
    }
    html += `<li class="page-item ${currentPage===pages?'disabled':''}">
        <a class="page-link" href="#" onclick="goPage(${currentPage+1});return false;">»</a></li>`;
    ul.innerHTML = html;
}

function goPage(p) {
    const pages = Math.ceil(allRequests.length / rowsPerPage);
    if (p < 1 || p > pages) return;
    currentPage = p;
    renderPage();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

// ── Approve / Reject ───────────────────────────────────────
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
            if (res.success) { Swal.fire('Approved! ✅', res.msg, 'success'); loadRequests(); }
            else Swal.fire('Error', res.msg, 'error');
        })
        .catch(() => Swal.fire('Error', 'Failed to approve.', 'error'));
}

function openReject(id, name) {
    document.getElementById('rejectReqId').value = id;
    document.getElementById('rejectNotes').value  = '';
    document.getElementById('rejectInfo').innerHTML = `Rejecting request from <strong>${esc(name)}</strong>. Please provide a reason.`;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function doReject() {
    const id    = document.getElementById('rejectReqId').value;
    const notes = document.getElementById('rejectNotes').value.trim();
    if (!notes) { Swal.fire('Required', 'Please provide a reason for rejection.', 'warning'); return; }
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('request_id', id);
    fd.append('admin_notes', notes);
    fetch('promotion_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.hide();
            if (res.success) { Swal.fire('Rejected', res.msg, 'info'); loadRequests(); }
            else Swal.fire('Error', res.msg, 'error');
        })
        .catch(() => Swal.fire('Error', 'Failed to reject.', 'error'));
}

// ── Init ───────────────────────────────────────────────────
document.getElementById('filterSearch').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') loadRequests();
});
loadRequests();
</script>