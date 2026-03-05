<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

if (!in_array($_SESSION['userdata']['role'], ['Super Admin', 'Admin'])) {
    header('Location: ../../dashboard.php');
    exit;
}

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<style>
    :root {
        --brand-primary: #059669;
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
    }

    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f9fafb; }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        padding: 2rem; border-radius: 1rem; margin-bottom: 1.5rem;
        box-shadow: var(--shadow-lg); color: white;
    }
    .page-header h4  { margin: 0; font-size: 1.75rem; font-weight: 700; }
    .page-header .subtitle { opacity: 0.9; font-size: 0.95rem; margin-top: 0.25rem; }

    /* Summary Cards */
    .summary-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1.25rem 1.5rem; border-radius: 1rem; color: white;
        transition: all 0.25s ease; box-shadow: var(--shadow-md);
    }
    .summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }
    .summary-icon  { font-size: 2.25rem; opacity: 0.9; }
    .summary-count { font-size: 2rem; font-weight: 800; line-height: 1; }
    .summary-label { font-size: 0.85rem; font-weight: 600; opacity: 0.9; margin-top: 0.25rem; }
    .card-pending  { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .card-approved { background: linear-gradient(135deg,#059669,#047857); }
    .card-rejected { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .card-total    { background: linear-gradient(135deg,#3b82f6,#2563eb); }

    /* Filter Section */
    .filter-section {
        background: white; padding: 1.5rem; border-radius: 1rem;
        margin-bottom: 1.5rem; box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .filter-section .form-label { font-weight: 600; color: #374151; font-size: 0.875rem; }
    .filter-section .form-control,
    .filter-section .form-select {
        border: 1.5px solid #e5e7eb; border-radius: 0.5rem;
        padding: 0.625rem 0.875rem; font-size: 0.875rem; transition: all 0.2s;
    }
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
    }

    /* Table Card */
    .table-card {
        background: white; padding: 1.5rem; border-radius: 1rem;
        box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .table-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 2px solid #f3f4f6;
    }
    .table-title { font-weight: 700; color: #111827; font-size: 1.125rem; margin: 0; }
    .table-wrapper { overflow-x: auto; border-radius: 0.75rem; border: 1px solid #e5e7eb; }
    .table { margin-bottom: 0; }
    .table thead { background: #1f2937 !important; }
    .table thead th {
        color: #1f2937 !important; font-weight: 700; font-size: 0.8rem;
        padding: 1rem 0.75rem; border: none; text-transform: uppercase; letter-spacing: 0.025em;
    }
    .table tbody tr { transition: all 0.2s ease; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background: #f0fdf4 !important; }
    .table tbody td { padding: 0.875rem 0.75rem; font-size: 0.875rem; color: #374151; vertical-align: middle; }

    /* Badges */
    .badge-pill { padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.75rem; }
    .badge-pending  { background:#fef3c7; color:#92400e; }
    .badge-approved { background:#d1fae5; color:#065f46; }
    .badge-rejected { background:#fee2e2; color:#991b1b; }
    .badge-profile  { background:#dbeafe; color:#1e40af; }
    .badge-creation { background:#d1fae5; color:#065f46; }
    .badge-term     { background:#fef3c7; color:#92400e; }
    .badge-removal  { background:#fee2e2; color:#991b1b; }
    .badge-role     { background:#ede9fe; color:#5b21b6; }

    /* Buttons */
    .btn { border-radius: 0.5rem; font-weight: 600; transition: all 0.2s ease; }
    .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn-outline-light { border: 2px solid rgba(255,255,255,0.5); color: white; }
    .btn-outline-light:hover { background: rgba(255,255,255,0.2); border-color: white; color: white; }

    /* Comparison Grid (inside modal) */
    .comparison-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .data-box { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; }
    .data-box.new-data { background: #ecfdf5; border-color: #10b981; }
    .data-box-title { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: #6b7280; margin-bottom: 0.75rem; }
    .data-item { margin-bottom: 0.4rem; font-size: 0.85rem; }
    .data-label { color: #6b7280; font-weight: 500; display: inline-block; min-width: 90px; }
    .data-value { color: #111827; font-weight: 600; }
    .diff-highlight { background: #fef08a; padding: 0 3px; border-radius: 2px; }

    /* Pagination */
    .pagination-wrapper {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #f3f4f6; flex-wrap: wrap; gap: 0.5rem;
    }
    .pagination { margin-bottom: 0; }
    .pagination .page-link {
        border: 1.5px solid #e5e7eb; color: #059669; margin: 0 0.125rem;
        border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; padding: 0.5rem 0.75rem; transition: all 0.2s;
    }
    .pagination .page-link:hover { background: #059669; color: white; border-color: #059669; }
    .pagination .page-item.active .page-link  { background: #059669; border-color: #059669; color: white; }
    .pagination .page-item.disabled .page-link { background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }
</style>

<div class="main-wrap">
<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-shield-check me-2"></i>Approval Requests</h4>
                <p class="subtitle mb-0">Review and action profile update and account requests</p>
            </div>
            <button class="btn btn-sm btn-outline-light" onclick="loadRequests()">
                <i class="bi bi-arrow-clockwise me-1"></i>Reload
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-card card-pending">
                <div class="summary-icon">⏳</div>
                <div><div class="summary-count" id="cnt_pending">—</div><div class="summary-label">Pending</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-approved">
                <div class="summary-icon">✅</div>
                <div><div class="summary-count" id="cnt_approved">—</div><div class="summary-label">Approved</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-rejected">
                <div class="summary-icon">❌</div>
                <div><div class="summary-count" id="cnt_rejected">—</div><div class="summary-label">Rejected</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-total">
                <div class="summary-icon">📋</div>
                <div><div class="summary-count" id="cnt_total">—</div><div class="summary-label">Total</div></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="Search name, username...">
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
                    <option value="profile_update">Profile Update</option>
                    <option value="user_creation">New User</option>
                    <option value="termination">Termination</option>
                    <option value="removal">Removal</option>
                    <option value="role_permission_add">Role Add</option>
                    <option value="role_permission_edit">Role Edit</option>
                    <option value="role_permission_delete">Role Delete</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rows per page</label>
                <select class="form-select" id="rowsPerPage">
                    <option value="10">10 rows</option>
                    <option value="25">25 rows</option>
                    <option value="50">50 rows</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-success w-100" onclick="applyFilters()">
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

    <!-- Table -->
    <div class="table-card">
        <div class="table-header">
            <h5 class="table-title"><i class="bi bi-list-check me-2 text-success"></i>Request List</h5>
            <span class="text-muted small" id="recordInfo">Loading...</span>
        </div>
        <div class="table-wrapper">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Requested By</th>
                        <th>Target User</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="reqTableBody">
                    <tr><td colspan="8" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...
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

<!-- View Details Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:.75rem;">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#047857);color:white;border-radius:.75rem .75rem 0 0;">
                <h6 class="modal-title fw-bold" id="detailModalTitle"><i class="bi bi-eye me-2"></i>Request Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailModalBody"></div>
            <div class="modal-footer" id="detailModalFooter"></div>
        </div>
    </div>
</div>

<!-- Approve API Key Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:.75rem;">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#047857);color:white;border-radius:.75rem .75rem 0 0;">
                <h6 class="modal-title fw-bold"><i class="bi bi-key me-2"></i>Admin API Key Required</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning small mb-3" id="approveModalInfo"></div>
                <label class="form-label fw-semibold small">Administrative API Key <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="approveApiKey" placeholder="Enter API Key">
                <input type="hidden" id="approveReqId">
                <input type="hidden" id="approveReqType">
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="doApprove()"><i class="bi bi-check-circle me-1"></i>Verify & Approve</button>
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
                <div class="alert alert-danger small mb-3" id="rejectModalInfo"></div>
                <label class="form-label fw-semibold small">Reason for Rejection <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rejectReason" rows="3" placeholder="Explain why this request is being rejected..."></textarea>
                <input type="hidden" id="rejectReqId">
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="doReject()"><i class="bi bi-x-circle me-1"></i>Confirm Reject</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
// ── State ──────────────────────────────────────────────────
let allRequests  = [];
let currentPage  = 1;
let rowsPerPage  = 10;

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

const TYPE_LABELS = {
    profile_update:          { label: 'Profile Update',    cls: 'badge-profile' },
    user_creation:           { label: 'New User',          cls: 'badge-creation' },
    termination:             { label: 'Termination',       cls: 'badge-term' },
    removal:                 { label: 'Removal',           cls: 'badge-removal' },
    role_permission_add:     { label: 'Role Add',          cls: 'badge-role' },
    role_permission_edit:    { label: 'Role Edit',         cls: 'badge-role' },
    role_permission_delete:  { label: 'Role Delete',       cls: 'badge-removal' },
};

// ── Load ───────────────────────────────────────────────────
function loadRequests() {
    const tbody = document.getElementById('reqTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...</td></tr>';
    document.getElementById('recordInfo').textContent = 'Loading...';

    fetch('approval_action.php?action=get_pending', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(data.msg)}</td></tr>`;
                document.getElementById('recordInfo').textContent = 'Error';
                return;
            }

            // Store all, apply filters client-side
            allRequests = data.requests || [];
            applyFilters();
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load requests.</td></tr>';
            document.getElementById('recordInfo').textContent = 'Error';
        });
}

function applyFilters() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPage').value) || 10;
    currentPage = 1;

    const search = document.getElementById('filterSearch').value.trim().toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const type   = document.getElementById('filterType').value;

    const filtered = allRequests.filter(r => {
        const name = (r.full_name || r.request_data_parsed?.full_name || '').toLowerCase();
        const uname = (r.username || r.request_data_parsed?.username || '').toLowerCase();
        const by = (r.requested_by_name || '').toLowerCase();

        if (search && !name.includes(search) && !uname.includes(search) && !by.includes(search)) return false;
        if (status && r.status !== status) return false;
        if (type   && r.request_type !== type) return false;
        return true;
    });

    // Count summary
    const counts = { pending: 0, approved: 0, rejected: 0 };
    allRequests.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++; });
    document.getElementById('cnt_pending').textContent  = counts.pending;
    document.getElementById('cnt_approved').textContent = counts.approved;
    document.getElementById('cnt_rejected').textContent = counts.rejected;
    document.getElementById('cnt_total').textContent    = allRequests.length;

    renderPage(filtered);
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterType').value   = '';
    document.getElementById('rowsPerPage').value  = '10';
    applyFilters();
}

// ── Render ─────────────────────────────────────────────────
function renderPage(filtered) {
    const tbody = document.getElementById('reqTableBody');
    const total  = filtered.length;
    const pages  = Math.max(1, Math.ceil(total / rowsPerPage));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * rowsPerPage;
    const end   = Math.min(start + rowsPerPage, total);
    const slice = filtered.slice(start, end);

    document.getElementById('recordInfo').textContent =
        total > 0 ? `Showing ${start + 1} to ${end} of ${total} request(s)` : '0 requests found';

    if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No requests found</td></tr>';
        document.getElementById('paginationWrapper').style.display = 'none';
        return;
    }

    tbody.innerHTML = slice.map((r, i) => {
        const rowNum   = start + i + 1;
        const typeInfo = TYPE_LABELS[r.request_type] || { label: r.request_type, cls: 'badge-profile' };
        const parsed   = r.request_data_parsed || {};

        const targetName  = r.full_name  || parsed.full_name  || 'New User';
        const targetUname = r.username   || parsed.username   || '—';

        const statusBadge = {
            pending:  '<span class="badge-pill badge-pending">⏳ Pending</span>',
            approved: '<span class="badge-pill badge-approved">✅ Approved</span>',
            rejected: '<span class="badge-pill badge-rejected">❌ Rejected</span>',
        }[r.status] || r.status;

        const dateStr = r.created_at ? r.created_at.substring(0, 16) : '—';

        let actions = '';
        if (r.status === 'pending') {
            actions = `
                <button class="btn btn-sm btn-success me-1" onclick="openApprove(${r.request_id},'${esc(r.request_type)}','${esc(targetName)}')">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
                <button class="btn btn-sm btn-danger me-1" onclick="openReject(${r.request_id},'${esc(targetName)}')">
                    <i class="bi bi-x-circle"></i> Reject
                </button>`;
        }
        actions += `<button class="btn btn-sm btn-outline-secondary" onclick="viewDetails(${r.request_id})">
            <i class="bi bi-eye"></i>
        </button>`;

        return `<tr>
            <td class="text-muted small">${rowNum}</td>
            <td class="small fw-semibold">${esc(r.requested_by_name || '—')}</td>
            <td>
                <div class="fw-semibold small">${esc(targetName)}</div>
                <div class="text-muted" style="font-size:.75rem;">@${esc(targetUname)}</div>
            </td>
            <td><span class="badge-pill ${typeInfo.cls}">${typeInfo.label}</span></td>
            <td class="small">${dateStr}</td>
            <td>${statusBadge}</td>
            <td class="small">${esc(r.reviewed_by_name || '—')}</td>
            <td class="text-center">${actions}</td>
        </tr>`;
    }).join('');

    // Store filtered for pagination
    window._filteredRequests = filtered;
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
    const filtered = window._filteredRequests || [];
    const pages = Math.ceil(filtered.length / rowsPerPage);
    if (p < 1 || p > pages) return;
    currentPage = p;
    renderPage(filtered);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── View Details ───────────────────────────────────────────
function viewDetails(id) {
    const r = allRequests.find(x => x.request_id == id);
    if (!r) return;

    const parsed   = r.request_data_parsed  || {};
    const current  = r.current_data_parsed  || {};
    const typeInfo = TYPE_LABELS[r.request_type] || { label: r.request_type, cls: 'badge-profile' };

    document.getElementById('detailModalTitle').innerHTML =
        `<i class="bi bi-eye me-2"></i>Request #${r.request_id} — <span class="badge-pill ${typeInfo.cls}">${typeInfo.label}</span>`;

    const fields = ['username','full_name','email','role','status'];

    let body = `<div class="mb-3">
        <div class="d-flex gap-2 flex-wrap mb-2">
            <span class="badge bg-secondary">Requested by: <strong>${esc(r.requested_by_name || '—')}</strong></span>
            <span class="badge bg-secondary">Date: ${r.created_at?.substring(0,16) || '—'}</span>
        </div>
    </div>`;

    if (r.request_type === 'termination') {
        body += `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>User is requesting to <strong>Deactivate</strong> their account.</div>`;
    } else if (r.request_type === 'removal') {
        body += `<div class="alert alert-danger"><i class="bi bi-trash-fill me-2"></i>Request to <strong>permanently delete</strong> this user from the system.</div>`;
    } else if (r.request_type === 'user_creation') {
        body += `<div class="alert alert-success mb-3"><i class="bi bi-person-plus-fill me-2"></i>Request to create a <strong>new user</strong> account.</div>
        <div class="data-box new-data">
            <div class="data-box-title">New User Details</div>
            ${fields.map(f => `<div class="data-item"><span class="data-label">${f.replace('_',' ')}:</span> <span class="data-value">${esc(parsed[f] || '—')}</span></div>`).join('')}
        </div>`;
    } else if (r.request_type.startsWith('role_permission')) {
        const permFields = ['role_id','module','can_view','can_add','can_edit','can_delete'];
        body += `<div class="data-box new-data">
            <div class="data-box-title">Permission Details</div>
            ${permFields.map(f => {
                const v = parsed[f];
                const display = v === 1 ? '<span class="text-success fw-bold">Yes</span>' : (v === 0 ? '<span class="text-danger fw-bold">No</span>' : esc(v ?? '—'));
                return `<div class="data-item"><span class="data-label">${f.replace(/_/g,' ')}:</span> <span class="data-value">${display}</span></div>`;
            }).join('')}
        </div>`;
    } else {
        body += `<div class="comparison-grid">
            <div class="data-box">
                <div class="data-box-title">Current Info</div>
                ${fields.map(f => `<div class="data-item"><span class="data-label">${f.replace('_',' ')}:</span> <span class="data-value">${esc(current[f] || '—')}</span></div>`).join('')}
            </div>
            <div class="data-box new-data">
                <div class="data-box-title">Requested Changes</div>
                ${fields.map(f => {
                    const isDiff = parsed[f] && parsed[f] !== current[f];
                    return `<div class="data-item"><span class="data-label">${f.replace('_',' ')}:</span> <span class="data-value ${isDiff?'diff-highlight':''}">${esc(parsed[f] || '—')}</span></div>`;
                }).join('')}
            </div>
        </div>`;
    }

    if (r.review_notes) {
        body += `<div class="mt-3 p-3 rounded" style="background:#f3f4f6;border:1px solid #e5e7eb;">
            <div class="small fw-bold text-muted mb-1">Review Notes:</div>
            <div class="small">${esc(r.review_notes)}</div>
        </div>`;
    }

    document.getElementById('detailModalBody').innerHTML = body;

    let footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
    if (r.status === 'pending') {
        footer = `
            <button class="btn btn-success" onclick="bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();openApprove(${r.request_id},'${esc(r.request_type)}','${esc(r.full_name||'')}')">
                <i class="bi bi-check-circle me-1"></i>Approve
            </button>
            <button class="btn btn-danger" onclick="bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();openReject(${r.request_id},'${esc(r.full_name||'')}')">
                <i class="bi bi-x-circle me-1"></i>Reject
            </button>
            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>`;
    }
    document.getElementById('detailModalFooter').innerHTML = footer;

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// ── Approve ────────────────────────────────────────────────
function openApprove(id, type, name) {
    document.getElementById('approveReqId').value   = id;
    document.getElementById('approveReqType').value = type;
    document.getElementById('approveApiKey').value  = '';
    document.getElementById('approveModalInfo').innerHTML =
        `Approving <strong>${esc(type.replace(/_/g,' '))}</strong> request for <strong>${esc(name)}</strong>.<br>
        <small class="text-muted">Enter the administrative API key to proceed.</small>`;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function doApprove() {
    const id     = document.getElementById('approveReqId').value;
    const apiKey = document.getElementById('approveApiKey').value.trim();

    if (!apiKey) { Swal.fire('Required', 'Please enter the API key.', 'warning'); return; }

    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('request_id', id);
    fd.append('review_notes', 'Approved by Admin');
    fd.append('api_key', apiKey);

    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('approval_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            bootstrap.Modal.getInstance(document.getElementById('approveModal'))?.hide();
            if (res.status === 'success') {
                Swal.fire('Approved! ✅', res.msg, 'success');
                loadRequests();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to approve.', 'error'));
}

// ── Reject ─────────────────────────────────────────────────
function openReject(id, name) {
    document.getElementById('rejectReqId').value   = id;
    document.getElementById('rejectReason').value  = '';
    document.getElementById('rejectModalInfo').innerHTML =
        `Rejecting request from <strong>${esc(name)}</strong>. Please provide a reason.`;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function doReject() {
    const id     = document.getElementById('rejectReqId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { Swal.fire('Required', 'Please provide a reason for rejection.', 'warning'); return; }

    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('request_id', id);
    fd.append('review_notes', reason);

    fetch('approval_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.hide();
            if (res.status === 'success') {
                Swal.fire('Rejected', res.msg, 'info');
                loadRequests();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Failed to reject.', 'error'));
}

// ── Init ───────────────────────────────────────────────────
document.getElementById('filterSearch').addEventListener('keyup', e => {
    if (e.key === 'Enter') applyFilters();
});
loadRequests();
</script>