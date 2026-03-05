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
        --brand: #7c3aed;
        --brand-dark: #5b21b6;
        --shadow-sm: 0 1px 2px rgba(0,0,0,.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,.1);
    }

    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f9fafb; }

    .page-header {
        background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
        padding: 2rem; border-radius: 1rem; margin-bottom: 1.5rem;
        box-shadow: var(--shadow-lg); color: white;
    }
    .page-header h4  { margin: 0; font-size: 1.75rem; font-weight: 700; }
    .page-header .subtitle { opacity: .9; font-size: .95rem; margin-top: .25rem; }

    /* Summary Cards */
    .summary-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1.25rem 1.5rem; border-radius: 1rem; color: white;
        transition: all .25s; box-shadow: var(--shadow-md); cursor: pointer;
    }
    .summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }
    .summary-icon  { font-size: 2.25rem; opacity: .9; }
    .summary-count { font-size: 2rem; font-weight: 800; line-height: 1; }
    .summary-label { font-size: .85rem; font-weight: 600; opacity: .9; margin-top: .25rem; }
    .card-total    { background: linear-gradient(135deg,#7c3aed,#5b21b6); }
    .card-today    { background: linear-gradient(135deg,#0d6efd,#0a58ca); }
    .card-staff    { background: linear-gradient(135deg,#059669,#047857); }
    .card-modules  { background: linear-gradient(135deg,#f59e0b,#d97706); }

    /* Filter Section */
    .filter-section {
        background: white; padding: 1.5rem; border-radius: 1rem;
        margin-bottom: 1.5rem; box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .filter-section .form-label { font-weight: 600; color: #374151; font-size: .875rem; }
    .filter-section .form-control,
    .filter-section .form-select {
        border: 1.5px solid #e5e7eb; border-radius: .5rem;
        padding: .625rem .875rem; font-size: .875rem; transition: all .2s;
    }
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1);
    }

    /* Table Card */
    .table-card {
        background: white; padding: 1.5rem; border-radius: 1rem;
        box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .table-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 2px solid #f3f4f6;
        flex-wrap: wrap; gap: .5rem;
    }
    .table-title { font-weight: 700; color: #111827; font-size: 1.125rem; margin: 0; }
    .table-wrapper { overflow-x: auto; border-radius: .75rem; border: 1px solid #e5e7eb; }
    .table { margin-bottom: 0; }
    .table thead { background: #1f2937 !important; }
    .table thead th {
        color: #1f2937 !important; font-weight: 700; font-size: .8rem;
        padding: 1rem .75rem; border: none; text-transform: uppercase; letter-spacing: .025em;
    }
    .table tbody tr { transition: all .2s; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background: #f5f3ff !important; }
    .table tbody td { padding: .875rem .75rem; font-size: .875rem; color: #374151; vertical-align: middle; }

    /* Badges */
    .bdg { padding: .3rem .65rem; border-radius: .4rem; font-weight: 600; font-size: .72rem; display: inline-block; }
    .bdg-auth     { background:#dbeafe; color:#1e40af; }
    .bdg-loan     { background:#d1fae5; color:#065f46; }
    .bdg-repay    { background:#fef3c7; color:#92400e; }
    .bdg-user     { background:#ede9fe; color:#5b21b6; }
    .bdg-savings  { background:#ecfdf5; color:#047857; }
    .bdg-disburse { background:#fee2e2; color:#991b1b; }
    .bdg-rewards  { background:#fdf4ff; color:#7e22ce; }
    .bdg-other    { background:#f3f4f6; color:#4b5563; }

    /* Status */
    .bdg-compliant    { background:#d1fae5; color:#065f46; }
    .bdg-noncompliant { background:#fee2e2; color:#991b1b; }
    .bdg-review       { background:#fef3c7; color:#92400e; }
    .bdg-pending      { background:#e0e7ff; color:#3730a3; }

    /* Staff Avatar */
    .staff-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: linear-gradient(135deg,#7c3aed,#5b21b6);
        color: white; font-weight: 700; font-size: .85rem;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #f3f4f6; flex-wrap: wrap; gap: .5rem;
    }
    .pagination { margin-bottom: 0; }
    .pagination .page-link {
        border: 1.5px solid #e5e7eb; color: #7c3aed; margin: 0 .125rem;
        border-radius: .375rem; font-weight: 600; font-size: .875rem; padding: .5rem .75rem; transition: all .2s;
    }
    .pagination .page-link:hover { background: #7c3aed; color: white; border-color: #7c3aed; }
    .pagination .page-item.active .page-link   { background: #7c3aed; border-color: #7c3aed; color: white; }
    .pagination .page-item.disabled .page-link { background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }

    /* Buttons */
    .btn { border-radius: .5rem; font-weight: 600; transition: all .2s; }
    .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn-outline-light { border: 2px solid rgba(255,255,255,.5); color: white; }
    .btn-outline-light:hover { background: rgba(255,255,255,.2); border-color: white; color: white; }
    .btn-export { background: linear-gradient(135deg,#059669,#047857); color: white; border: none; }
    .btn-export:hover { color: white; }
</style>

<div class="main-wrap">
<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-person-lines-fill me-2"></i>Staff Activity Monitor</h4>
                <p class="subtitle mb-0">Track all actions performed by staff members across all modules</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-export" onclick="exportCSV()">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                </button>
                <button class="btn btn-sm btn-outline-light" onclick="loadActivity()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Reload
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-card card-total">
                <div class="summary-icon">📋</div>
                <div><div class="summary-count" id="cnt_total">—</div><div class="summary-label">Total Activities</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-today">
                <div class="summary-icon">📅</div>
                <div><div class="summary-count" id="cnt_today">—</div><div class="summary-label">Today</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-staff">
                <div class="summary-icon">👥</div>
                <div><div class="summary-count" id="cnt_staff">—</div><div class="summary-label">Active Staff</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card card-modules">
                <div class="summary-icon">🗂️</div>
                <div><div class="summary-count" id="cnt_modules">—</div><div class="summary-label">Modules Used</div></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="Staff name, action, module...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Staff Member</label>
                <select class="form-select" id="filterStaff">
                    <option value="">All Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Module</label>
                <select class="form-select" id="filterModule">
                    <option value="">All Modules</option>
                    <option value="Authentication">Authentication</option>
                    <option value="Loan Portfolio">Loan Portfolio</option>
                    <option value="Repayments">Repayments</option>
                    <option value="Collection Monitoring">Collection Monitoring</option>
                    <option value="Savings Monitoring">Savings Monitoring</option>
                    <option value="Disbursement Tracker">Disbursement Tracker</option>
                    <option value="Member Rewards">Member Rewards</option>
                    <option value="User Management">User Management</option>
                    <option value="Promotions">Promotions</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" id="filterDate" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label">Rows</label>
                <select class="form-select" id="rowsPerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);border:none;" onclick="applyFilters()">
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
            <h5 class="table-title"><i class="bi bi-activity me-2 text-purple" style="color:#7c3aed;"></i>Staff Activity Log</h5>
            <span class="text-muted small" id="recordInfo">Loading...</span>
        </div>
        <div class="table-wrapper">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Staff</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody id="activityTableBody">
                    <tr><td colspan="8" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm me-2" style="color:#7c3aed;"></div>Loading...
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

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:.75rem;">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);color:white;border-radius:.75rem .75rem 0 0;">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Activity Detail</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailModalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
// ── State ──────────────────────────────────────────────────
let allRows     = [];
let filtered    = [];
let currentPage = 1;
let rowsPerPage = 10;

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

const MODULE_BADGE = {
    'Authentication':        'bdg-auth',
    'Loan Portfolio':        'bdg-loan',
    'Loan Process':          'bdg-loan',
    'Repayments':            'bdg-repay',
    'Collection Monitoring': 'bdg-repay',
    'Savings Monitoring':    'bdg-savings',
    'Disbursement Tracker':  'bdg-disburse',
    'Member Rewards':        'bdg-rewards',
    'User Management':       'bdg-user',
    'Promotions':            'bdg-user',
    'Compliance & Audit':    'bdg-other',
};

const STATUS_BADGE = {
    'Compliant':     'bdg-compliant',
    'Non-Compliant': 'bdg-noncompliant',
    'Under Review':  'bdg-review',
    'Pending':       'bdg-pending',
};

// ── Load ───────────────────────────────────────────────────
function loadActivity() {
    const tbody = document.getElementById('activityTableBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">
        <div class="spinner-border spinner-border-sm me-2" style="color:#7c3aed;"></div>Loading...</td></tr>`;
    document.getElementById('recordInfo').textContent = 'Loading...';

    fetch('staff_activity_action.php?action=list', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(data.msg)}</td></tr>`;
                return;
            }

            allRows = data.rows || [];

            // Populate staff filter
            const staffSel = document.getElementById('filterStaff');
            const seen = new Set();
            staffSel.innerHTML = '<option value="">All Staff</option>';
            allRows.forEach(r => {
                if (r.full_name && !seen.has(r.user_id)) {
                    seen.add(r.user_id);
                    staffSel.innerHTML += `<option value="${r.user_id}">${esc(r.full_name)}</option>`;
                }
            });

            // Update summary cards
            const today = new Date().toISOString().split('T')[0];
            const todayRows  = allRows.filter(r => r.action_time && r.action_time.startsWith(today));
            const staffSet   = new Set(allRows.map(r => r.user_id).filter(Boolean));
            const moduleSet  = new Set(allRows.map(r => r.module_name).filter(Boolean));

            document.getElementById('cnt_total').textContent   = allRows.length;
            document.getElementById('cnt_today').textContent   = todayRows.length;
            document.getElementById('cnt_staff').textContent   = staffSet.size;
            document.getElementById('cnt_modules').textContent = moduleSet.size;

            applyFilters();
        })
        .catch(e => {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Failed to load data.</td></tr>`;
            document.getElementById('recordInfo').textContent = 'Error';
        });
}

// ── Filters ────────────────────────────────────────────────
function applyFilters() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPage').value) || 10;
    currentPage = 1;

    const search = document.getElementById('filterSearch').value.trim().toLowerCase();
    const staff  = document.getElementById('filterStaff').value;
    const module = document.getElementById('filterModule').value;
    const date   = document.getElementById('filterDate').value;

    filtered = allRows.filter(r => {
        if (staff  && String(r.user_id) !== staff) return false;
        if (module && r.module_name !== module)    return false;
        if (date   && !(r.action_time || '').startsWith(date)) return false;
        if (search) {
            const hay = [r.full_name, r.action_type, r.module_name, r.remarks, r.ip_address].join(' ').toLowerCase();
            if (!hay.includes(search)) return false;
        }
        return true;
    });

    renderPage();
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStaff').value  = '';
    document.getElementById('filterModule').value = '';
    document.getElementById('filterDate').value   = '';
    document.getElementById('rowsPerPage').value  = '10';
    applyFilters();
}

// ── Render ─────────────────────────────────────────────────
function renderPage() {
    const tbody = document.getElementById('activityTableBody');
    const total  = filtered.length;
    const pages  = Math.max(1, Math.ceil(total / rowsPerPage));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * rowsPerPage;
    const end   = Math.min(start + rowsPerPage, total);
    const slice = filtered.slice(start, end);

    document.getElementById('recordInfo').textContent =
        total > 0 ? `Showing ${start+1} to ${end} of ${total} activit${total===1?'y':'ies'}` : '0 activities found';

    if (!slice.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No activities found</td></tr>`;
        document.getElementById('paginationWrapper').style.display = 'none';
        return;
    }

    tbody.innerHTML = slice.map((r, i) => {
        const rowNum    = start + i + 1;
        const initials  = (r.full_name || 'S').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
        const modCls    = MODULE_BADGE[r.module_name] || 'bdg-other';
        const staCls    = STATUS_BADGE[r.compliance_status] || 'bdg-pending';
        const details   = r.remarks ? (r.remarks.length > 60 ? r.remarks.substring(0,60)+'…' : r.remarks) : '—';
        const dateStr   = r.action_time ? r.action_time.substring(0,16) : '—';

        return `<tr style="cursor:pointer;" onclick="viewDetail(${r.audit_id})">
            <td class="text-muted small">${rowNum}</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="staff-avatar">${initials}</div>
                    <div>
                        <div class="fw-semibold small">${esc(r.full_name || 'System')}</div>
                        <div class="text-muted" style="font-size:.72rem;">@${esc(r.username || '—')}</div>
                    </div>
                </div>
            </td>
            <td class="small fw-semibold">${esc(r.action_type || '—')}</td>
            <td><span class="bdg ${modCls}">${esc(r.module_name || '—')}</span></td>
            <td class="small text-muted" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${esc(r.remarks)}">${esc(details)}</td>
            <td><span class="bdg ${staCls}">${esc(r.compliance_status || 'Pending')}</span></td>
            <td class="small">${dateStr}</td>
            <td class="small text-muted">${esc(r.ip_address || '—')}</td>
        </tr>`;
    }).join('');

    renderPagination(total, pages);
}

function renderPagination(total, pages) {
    const wrapper = document.getElementById('paginationWrapper');
    const ul      = document.getElementById('pagination');
    const info    = document.getElementById('paginationInfo');
    const start   = (currentPage-1)*rowsPerPage+1;
    const end     = Math.min(currentPage*rowsPerPage, total);

    info.textContent = `Showing ${start}–${end} of ${total} entries`;
    if (pages <= 1) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = 'flex';

    let html = `<li class="page-item ${currentPage===1?'disabled':''}">
        <a class="page-link" href="#" onclick="goPage(${currentPage-1});return false;">«</a></li>`;
    for (let p = 1; p <= pages; p++) {
        if (pages > 7 && p > 2 && p < pages-1 && Math.abs(p-currentPage) > 2) {
            if (p===3||p===pages-2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
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
    const pages = Math.ceil(filtered.length / rowsPerPage);
    if (p < 1 || p > pages) return;
    currentPage = p;
    renderPage();
    window.scrollTo({top:0,behavior:'smooth'});
}

// ── View Detail ────────────────────────────────────────────
function viewDetail(id) {
    const r = allRows.find(x => x.audit_id == id);
    if (!r) return;

    const initials = (r.full_name||'S').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
    const modCls   = MODULE_BADGE[r.module_name] || 'bdg-other';
    const staCls   = STATUS_BADGE[r.compliance_status] || 'bdg-pending';

    document.getElementById('detailModalBody').innerHTML = `
        <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#f5f3ff;border:1px solid #ede9fe;">
            <div class="staff-avatar" style="width:50px;height:50px;font-size:1.1rem;">${initials}</div>
            <div>
                <div class="fw-bold fs-6">${esc(r.full_name||'System')}</div>
                <div class="text-muted small">@${esc(r.username||'—')} &nbsp;•&nbsp; ID #${r.audit_id}</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">Action</div>
                <div class="fw-semibold">${esc(r.action_type||'—')}</div>
            </div>
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">Module</div>
                <span class="bdg ${modCls}">${esc(r.module_name||'—')}</span>
            </div>
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">Status</div>
                <span class="bdg ${staCls}">${esc(r.compliance_status||'Pending')}</span>
            </div>
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">IP Address</div>
                <div class="fw-semibold">${esc(r.ip_address||'—')}</div>
            </div>
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">Date & Time</div>
                <div class="fw-semibold">${esc(r.action_time||'—')}</div>
            </div>
            <div class="col-6">
                <div class="small text-muted fw-bold text-uppercase mb-1">Record ID</div>
                <div class="fw-semibold">${r.record_id ? '#'+r.record_id : '—'}</div>
            </div>
            <div class="col-12">
                <div class="small text-muted fw-bold text-uppercase mb-1">Details / Remarks</div>
                <div class="p-3 rounded small" style="background:#f9fafb;border:1px solid #e5e7eb;word-break:break-word;">
                    ${esc(r.remarks||'No details available.')}
                </div>
            </div>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// ── Export CSV ─────────────────────────────────────────────
function exportCSV() {
    const rows  = filtered.length ? filtered : allRows;
    const heads = ['#','Staff','Username','Action','Module','Details','Status','Date & Time','IP Address'];
    const lines = [heads.join(',')];

    rows.forEach((r, i) => {
        const cols = [
            i+1,
            `"${(r.full_name||'').replace(/"/g,'""')}"`,
            `"${(r.username||'').replace(/"/g,'""')}"`,
            `"${(r.action_type||'').replace(/"/g,'""')}"`,
            `"${(r.module_name||'').replace(/"/g,'""')}"`,
            `"${(r.remarks||'').replace(/"/g,'""')}"`,
            `"${(r.compliance_status||'').replace(/"/g,'""')}"`,
            `"${(r.action_time||'').replace(/"/g,'""')}"`,
            `"${(r.ip_address||'').replace(/"/g,'""')}"`,
        ];
        lines.push(cols.join(','));
    });

    const blob = new Blob(['\ufeff'+lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'staff_activity_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// ── Init ───────────────────────────────────────────────────
document.getElementById('filterSearch').addEventListener('keyup', e => {
    if (e.key === 'Enter') applyFilters();
});
loadActivity();
</script>