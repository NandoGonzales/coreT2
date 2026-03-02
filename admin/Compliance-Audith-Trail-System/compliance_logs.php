<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

// Enforce RBAC for this page
checkPermission('compliance_logs');

// Include layout
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --brand-success: #10b981;
        --brand-warning: #f59e0b;
        --brand-danger: #ef4444;
        --brand-info: #3b82f6;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: #f9fafb;
    }

    /* Enhanced Header */
    .page-header {
        background: linear-gradient(135deg, var(--brand-primary) 0%, #047857 100%);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        color: white;
    }

    .page-header h4 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }

    .page-header .subtitle {
        opacity: 0.9;
        font-size: 0.95rem;
        margin-top: 0.25rem;
    }

    /* Enhanced Filter Section */
    .filter-section {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
    }

    .filter-section .form-label {
        font-weight: 600;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .filter-section .form-control,
    .filter-section .form-select {
        border: 1.5px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    /* Summary Cards */
    .summary-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border-radius: 1rem;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: var(--shadow-md);
        color: white;
        user-select: none;
    }
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
    }
    .summary-icon { font-size: 2.25rem; opacity: 0.9; }
    .summary-count { font-size: 2rem; font-weight: 800; line-height: 1; }
    .summary-label { font-size: 0.85rem; font-weight: 600; opacity: 0.9; margin-top: 0.25rem; }

    .compliant-card    { background: linear-gradient(135deg, #059669, #047857); }
    .noncompliant-card { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .pending-card      { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .review-card       { background: linear-gradient(135deg, #3b82f6, #2563eb); }

    /* Modal header colors */
    .modal-header.compliant-header    { background: linear-gradient(135deg,#059669,#047857); color:white; }
    .modal-header.noncompliant-header { background: linear-gradient(135deg,#ef4444,#dc2626); color:white; }
    .modal-header.pending-header      { background: linear-gradient(135deg,#f59e0b,#d97706); color:white; }
    .modal-header.review-header       { background: linear-gradient(135deg,#3b82f6,#2563eb); color:white; }

    #modalLogsTable thead { background: #1f2937; }
    #modalLogsTable thead th { color: white; font-size: 0.8rem; padding: 0.75rem; border: none; }
    #modalLogsTable tbody td { font-size: 0.82rem; }

    /* Enhanced Table */
    .table-card {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
    }

    .table-card .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f3f4f6;
    }

    .table-card .table-title {
        font-weight: 700;
        color: #111827;
        font-size: 1.125rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #recordInfo {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead {
        background: #1f2937 !important;
    }

    .table thead th {
        color: white !important;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 1rem 0.75rem;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f3f4f6;
    }

    .table tbody tr:hover {
        background: #f9fafb;
        transform: scale(1.005);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table tbody td {
        padding: 0.875rem 0.75rem;
        font-size: 0.875rem;
        color: #374151;
        vertical-align: middle;
    }

    .table .badge {
        padding: 0.375rem 0.75rem;
        font-weight: 600;
        font-size: 0.75rem;
        border-radius: 0.5rem;
    }

    /* Enhanced Buttons */
    .btn {
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn:active {
        transform: translateY(0);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
    }

    .btn-outline-light {
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
    }

    .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: white;
        color: white;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #f3f4f6;
    }

    .pagination {
        margin-bottom: 0;
    }

    .pagination .page-link {
        border: 1.5px solid #e5e7eb;
        color: var(--brand-primary);
        margin: 0 0.125rem;
        border-radius: 0.375rem;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s;
    }

    .pagination .page-link:hover {
        background: var(--brand-primary);
        color: white;
        border-color: var(--brand-primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--brand-primary);
        border-color: var(--brand-primary);
        color: white;
    }

    .pagination .page-item.disabled .page-link {
        background: #f3f4f6;
        color: #9ca3af;
        border-color: #e5e7eb;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
        }

        .filter-section {
            padding: 1rem;
        }

        .table-card {
            padding: 1rem;
        }
    }
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

            <!-- Enhanced Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-shield-check me-2"></i>Compliance & Audit Trail Logs</h4>
                        <p class="subtitle mb-0">Monitor system compliance and track all audit activities</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="exportCsvBtn" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </button>
                        <button id="exportPdfBtn" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <button id="reloadBtn" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Reload
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <form id="filterForm" class="row g-3 align-items-end" onsubmit="return false;">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="User, action, module...">
                    </div>
                    <div class="col-md-2">
                        <label for="start" class="form-label">Start Date</label>
                        <input type="date" id="start" name="start" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="end" class="form-label">End Date</label>
                        <input type="date" id="end" name="end" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Compliant">Compliant</option>
                            <option value="Non-Compliant">Non-Compliant</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="rowsPerPage" class="form-label">Rows per page</label>
                        <select id="rowsPerPage" name="rowsPerPage" class="form-select">
                            <option value="10">10 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="filterBtn" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Status Summary Cards -->
            <div class="row g-3 mb-4" id="statusSummaryCards">
                <div class="col-md-3">
                    <div class="summary-card compliant-card" onclick="openStatusModal('Compliant')">
                        <div class="summary-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="summary-body">
                            <div class="summary-count" id="count-Compliant">—</div>
                            <div class="summary-label">Compliant</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card noncompliant-card" onclick="openStatusModal('Non-Compliant')">
                        <div class="summary-icon"><i class="bi bi-x-circle-fill"></i></div>
                        <div class="summary-body">
                            <div class="summary-count" id="count-Non-Compliant">—</div>
                            <div class="summary-label">Non-Compliant</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card pending-card" onclick="openStatusModal('Pending')">
                        <div class="summary-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="summary-body">
                            <div class="summary-count" id="count-Pending">—</div>
                            <div class="summary-label">Pending</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card review-card" onclick="openStatusModal('Under Review')">
                        <div class="summary-icon"><i class="bi bi-eye-fill"></i></div>
                        <div class="summary-body">
                            <div class="summary-count" id="count-Under-Review">—</div>
                            <div class="summary-label">Under Review</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Detail Modal -->
            <div class="modal fade" id="statusModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header" id="statusModalHeader">
                            <h5 class="modal-title" id="statusModalTitle">
                                <i class="bi bi-list-ul me-2"></i>Logs
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="modalLogsTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Module</th>
                                            <th>Description</th>
                                            <th>Date/Time</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalLogsBody">
                                        <tr><td colspan="7" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <span id="modalRecordInfo" class="text-muted small"></span>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Logs Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Audit Trail Logs</span>
                    </h6>
                    <span id="recordInfo">Showing 0 to 0 of 0 entries</span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover" id="logsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date/Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="text-muted small">
                        <span id="recordInfoBottom"></span>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="logsPagination"></ul>
                    </nav>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        const tbody          = document.querySelector('#logsTable tbody');
        const pagination     = document.getElementById('logsPagination');
        const recordInfo     = document.getElementById('recordInfo');
        const recordInfoBottom = document.getElementById('recordInfoBottom');
        const searchInput    = document.getElementById('search');
        const startInput     = document.getElementById('start');
        const endInput       = document.getElementById('end');
        const statusInput    = document.getElementById('status');
        const rowsInput      = document.getElementById('rowsPerPage');
        const exportPdfBtn   = document.getElementById('exportPdfBtn');
        const exportCsvBtn   = document.getElementById('exportCsvBtn');
        const reloadBtn      = document.getElementById('reloadBtn');

        let currentPage    = 1;
        let currentLimit   = 10;
        let currentFilters = {};

        // ── Helpers ──────────────────────────────────────────────────
        function toastError(msg) { Toast.fire({ icon: 'error', title: msg }); }
        function toastSuccess(msg) { Toast.fire({ icon: 'success', title: msg }); }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '-';
        }

        async function downloadFile(url, fallbackFilename) {
            const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok) throw new Error('Download request failed.');

            if (contentType.includes('application/json')) {
                const payload = await response.json();
                throw new Error(payload.msg || payload.message || 'Export failed.');
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = fallbackFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(objectUrl);
        }

        // ── Load Summary Cards ────────────────────────────────────────
        function loadSummary() {
            const params = new URLSearchParams({
                action: 'status_summary',
                search: searchInput.value || '',
                start:  startInput.value  || '',
                end:    endInput.value    || ''
            });

            fetch('compliance_logs_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const s = data.summary;
                    document.getElementById('count-Compliant').textContent     = s['Compliant']     ?? 0;
                    document.getElementById('count-Non-Compliant').textContent  = s['Non-Compliant']  ?? 0;
                    document.getElementById('count-Pending').textContent       = s['Pending']       ?? 0;
                    document.getElementById('count-Under-Review').textContent  = s['Under Review']  ?? 0;
                }
            })
            .catch(() => {});
        }

        // ── Open Status Modal ─────────────────────────────────────────
        window.openStatusModal = function (status) {
            const headerEl = document.getElementById('statusModalHeader');
            const titleEl  = document.getElementById('statusModalTitle');

            const colorMap = {
                'Compliant':    ['compliant-header',    'bi-check-circle-fill', 'Compliant Logs'],
                'Non-Compliant':['noncompliant-header',  'bi-x-circle-fill',     'Non-Compliant Logs'],
                'Pending':      ['pending-header',       'bi-hourglass-split',   'Pending Logs'],
                'Under Review': ['review-header',        'bi-eye-fill',          'Under Review Logs']
            };

            const [cls, icon, label] = colorMap[status] || ['', 'bi-list-ul', status + ' Logs'];
            headerEl.className = 'modal-header ' + cls;
            titleEl.innerHTML  = `<i class="bi ${icon} me-2"></i>${label}`;

            const modal      = new bootstrap.Modal(document.getElementById('statusModal'));
            const modalBody  = document.getElementById('modalLogsBody');
            const modalInfo  = document.getElementById('modalRecordInfo');

            modalBody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';
            modalInfo.textContent = '';
            modal.show();

            const params = new URLSearchParams({
                action: 'list',
                page:   1,
                limit:  100,
                search: searchInput.value || '',
                start:  startInput.value  || '',
                end:    endInput.value    || '',
                status: status
            });

            fetch('compliance_logs_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success' || !data.rows.length) {
                    modalBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No ${escapeHtml(status)} logs found</td></tr>`;
                    modalInfo.textContent = 'No records found';
                    return;
                }

                modalBody.innerHTML = '';
                data.rows.forEach((r, i) => {
                    const actionRaw = r.action_type || '';
                    const cleanAction = actionRaw
                        .replace(' (High Risk)', '')
                        .replace(' (Medium Risk)', '')
                        .replace(' (Low Risk)', '');

                    let riskBadge = '';
                    if (actionRaw.includes('High Risk'))   riskBadge = '<span class="badge ms-1" style="background:#dc2626;font-size:0.65rem;">🔴 High</span>';
                    else if (actionRaw.includes('Medium Risk')) riskBadge = '<span class="badge ms-1" style="background:#ea580c;font-size:0.65rem;">🟠 Med</span>';
                    else if (actionRaw.includes('Low Risk'))    riskBadge = '<span class="badge ms-1" style="background:#ca8a04;font-size:0.65rem;">🟡 Low</span>';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${i + 1}</td>
                        <td>${escapeHtml(r.full_name || r.username || 'System')}</td>
                        <td><small>${escapeHtml(cleanAction)}${riskBadge}</small></td>
                        <td><small>${escapeHtml(r.module_name)}</small></td>
                        <td><small>${escapeHtml(r.remarks || '-')}</small></td>
                        <td><small>${escapeHtml(r.action_time)}</small></td>
                        <td><small>${escapeHtml(r.ip_address || '-')}</small></td>
                    `;
                    modalBody.appendChild(tr);
                });

                modalInfo.textContent = `Showing ${data.rows.length} of ${data.total} ${status} records`;
            })
            .catch(() => {
                modalBody.innerHTML = '<tr><td colspan="7" class="text-danger text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load data</td></tr>';
            });
        };

        // ── Load Logs Table ───────────────────────────────────────────
        function loadLogs(page = 1) {
            currentPage  = page;
            currentLimit = parseInt(rowsInput.value);

            currentFilters = {
                action: 'list',
                page:   page,
                limit:  currentLimit,
                search: searchInput.value || '',
                start:  startInput.value  || '',
                end:    endInput.value    || '',
                status: statusInput.value || ''
            };

            const params = new URLSearchParams(currentFilters);

            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

            fetch('compliance_logs_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'error') {
                    toastError(data.msg || 'Failed to load logs');
                    tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center">Error loading data</td></tr>';
                    return;
                }

                tbody.innerHTML = '';
                const rows = data.rows || [];

                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-4"><i class="bi bi-inbox"></i> No logs found</td></tr>';
                    recordInfo.textContent      = 'No records found';
                    recordInfoBottom.textContent = '';
                } else {
                    const startRecord = ((currentPage - 1) * currentLimit) + 1;
                    const endRecord   = Math.min(startRecord + rows.length - 1, data.total);

                    rows.forEach((r, index) => {
                        const badgeClass =
                            r.compliance_status === 'Compliant'     ? 'bg-success' :
                            r.compliance_status === 'Non-Compliant' ? 'bg-danger' :
                            r.compliance_status === 'Pending'       ? 'bg-warning text-dark' :
                            'bg-info text-dark';

                        const actionText = r.action_type || '';
                        let riskBadge = '';
                        if (actionText.includes('(High Risk)'))   riskBadge = '<span class="badge ms-1" style="background:#dc2626;">🔴 High Risk</span>';
                        else if (actionText.includes('(Medium Risk)')) riskBadge = '<span class="badge ms-1" style="background:#ea580c;">🟠 Medium Risk</span>';
                        else if (actionText.includes('(Low Risk)'))    riskBadge = '<span class="badge ms-1" style="background:#ca8a04;">🟡 Low Risk</span>';

                        const cleanAction = actionText
                            .replace(' (High Risk)', '')
                            .replace(' (Medium Risk)', '')
                            .replace(' (Low Risk)', '');

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${startRecord + index}</td>
                            <td>${escapeHtml(r.full_name || r.username || 'System')}</td>
                            <td><small>${escapeHtml(cleanAction)}${riskBadge}</small></td>
                            <td><small>${escapeHtml(r.module_name)}</small></td>
                            <td class="text-start"><small>${escapeHtml(r.remarks || '-')}</small></td>
                            <td><span class="badge ${badgeClass}">${escapeHtml(r.compliance_status)}</span></td>
                            <td><small>${escapeHtml(r.action_time)}</small></td>
                            <td><small>${escapeHtml(r.ip_address || '-')}</small></td>
                        `;
                        tbody.appendChild(tr);
                    });

                    const infoText = `Showing ${startRecord} to ${endRecord} of ${data.total} entries`;
                    recordInfo.textContent      = infoText;
                    recordInfoBottom.textContent = infoText;
                }

                // Build pagination
                pagination.innerHTML = '';
                const totalPages = Math.max(1, Math.ceil((data.total || 0) / currentLimit));

                const prevLi = document.createElement('li');
                prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
                pagination.appendChild(prevLi);

                const maxPages = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
                let endPage   = Math.min(totalPages, startPage + maxPages - 1);
                if (endPage - startPage < maxPages - 1) startPage = Math.max(1, endPage - maxPages + 1);

                if (startPage > 1) {
                    const li = document.createElement('li');
                    li.className = 'page-item';
                    li.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
                    pagination.appendChild(li);
                    if (startPage > 2) {
                        const dots = document.createElement('li');
                        dots.className = 'page-item disabled';
                        dots.innerHTML = `<span class="page-link">...</span>`;
                        pagination.appendChild(dots);
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    const li = document.createElement('li');
                    li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                    pagination.appendChild(li);
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        const dots = document.createElement('li');
                        dots.className = 'page-item disabled';
                        dots.innerHTML = `<span class="page-link">...</span>`;
                        pagination.appendChild(dots);
                    }
                    const li = document.createElement('li');
                    li.className = 'page-item';
                    li.innerHTML = `<a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>`;
                    pagination.appendChild(li);
                }

                const nextLi = document.createElement('li');
                nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
                pagination.appendChild(nextLi);
            })
            .catch(error => {
                console.error('Error:', error);
                toastError('Failed to load data. Please try again.');
                tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load data</td></tr>';
            });
        }

        // ── Export PDF ────────────────────────────────────────────────
        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', async function (e) {
                e.preventDefault();

                const passwordPrompt = await Swal.fire({
                    title: 'Protect PDF Export',
                    text: 'Enter a password required to open the exported PDF file.',
                    input: 'password',
                    inputLabel: 'PDF Password',
                    inputPlaceholder: 'Enter at least 6 characters',
                    inputAttributes: { maxlength: 64, autocapitalize: 'off', autocorrect: 'off' },
                    showCancelButton: true,
                    confirmButtonText: 'Export PDF',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter a password with at least 6 characters.' : null
                });

                if (!passwordPrompt.isConfirmed) return;
                const pdfPassword = passwordPrompt.value;

                const params = new URLSearchParams({
                    export: 'pdf',
                    search: searchInput.value || '',
                    start:  startInput.value  || '',
                    end:    endInput.value    || '',
                    status: statusInput.value || '',
                    pdf_password: pdfPassword
                });

                const originalHTML = exportPdfBtn.innerHTML;
                exportPdfBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                exportPdfBtn.disabled = true;

                try {
                    await downloadFile('compliance_logs_action.php?' + params.toString(), `compliance_logs_${new Date().toISOString().split('T')[0]}.pdf`);
                    Swal.fire({ icon: 'success', title: 'PDF Exported', text: 'Use your password to open the file.', timer: 3000, showConfirmButton: false });
                } catch (error) {
                    toastError(error.message || 'Failed to export PDF.');
                }

                exportPdfBtn.innerHTML = originalHTML;
                exportPdfBtn.disabled = false;
            });
        }

        // ── Export CSV ────────────────────────────────────────────────
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', async function (e) {
                e.preventDefault();

                const passwordPrompt = await Swal.fire({
                    title: 'Protect CSV Export',
                    text: 'Enter a password to encrypt this CSV export in a ZIP file.',
                    input: 'password',
                    inputLabel: 'Export Password',
                    inputPlaceholder: 'At least 6 characters',
                    showCancelButton: true,
                    confirmButtonText: 'Export CSV',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter at least 6 characters.' : null
                });

                if (!passwordPrompt.isConfirmed) return;
                const pdfPassword = passwordPrompt.value;

                const params = new URLSearchParams({
                    export: 'csv',
                    search: searchInput.value || '',
                    start:  startInput.value  || '',
                    end:    endInput.value    || '',
                    status: statusInput.value || '',
                    pdf_password: pdfPassword
                });

                const originalHTML = exportCsvBtn.innerHTML;
                exportCsvBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
                exportCsvBtn.disabled = true;

                try {
                    await downloadFile(
                        'compliance_logs_action.php?' + params.toString(),
                        'compliance_logs_' + new Date().toISOString().split('T')[0] + (pdfPassword ? '.zip' : '.csv')
                    );
                    Swal.fire({ icon: 'success', title: 'CSV Exported', text: pdfPassword ? 'The ZIP file is password protected.' : 'File downloaded successfully.', timer: 3000, showConfirmButton: false });
                } catch (error) {
                    toastError(error.message || 'Failed to export CSV.');
                }

                exportCsvBtn.innerHTML = originalHTML;
                exportCsvBtn.disabled = false;
            });
        }

        // ── Event Listeners ───────────────────────────────────────────
        document.getElementById('filterBtn').addEventListener('click', e => {
            e.preventDefault();
            if (startInput.value && endInput.value && startInput.value > endInput.value) {
                return toastError('Start date must be before end date.');
            }
            loadLogs(1);
            loadSummary();
        });

        reloadBtn.addEventListener('click', () => {
            searchInput.value = '';
            startInput.value  = '';
            endInput.value    = '';
            statusInput.value = '';
            rowsInput.value   = '10';
            loadLogs(1);
            loadSummary();
        });

        rowsInput.addEventListener('change', () => { loadLogs(1); });

        searchInput.addEventListener('keypress', e => {
            if (e.key === 'Enter') { e.preventDefault(); loadLogs(1); loadSummary(); }
        });

        statusInput.addEventListener('change', () => { loadLogs(1); });

        pagination.addEventListener('click', e => {
            e.preventDefault();
            if (e.target.tagName === 'A' && !e.target.parentElement.classList.contains('disabled')) {
                const page = parseInt(e.target.dataset.page);
                if (page > 0) loadLogs(page);
            }
        });

        // ── Initial Load ──────────────────────────────────────────────
        loadLogs(1);
        loadSummary();
    });
</script>