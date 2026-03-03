<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');
if (session_status() === PHP_SESSION_NONE) session_start();
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { --brand: #059669; --brand-dark: #047857; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f9fafb; }
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #065f46 100%);
        padding: 2rem; border-radius: 1rem; margin-bottom: 2rem;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); color: white;
        position: relative; overflow: hidden;
    }
    .page-header::before {
        content: '\2709'; position: absolute;
        right: 2rem; top: 50%; transform: translateY(-50%);
        font-size: 7rem; opacity: .06; pointer-events: none;
    }
    .page-header h4 { margin: 0; font-size: 1.75rem; font-weight: 700; }
    .page-header .subtitle { opacity: .8; font-size: .9rem; margin-top: .25rem; }

    .stat-card { border-radius: 1rem; padding: 1.5rem; color: white; position: relative;
        overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
    .stat-card::after { content: ''; position: absolute; right: -20px; top: -20px;
        width: 80px; height: 80px; background: rgba(255,255,255,.1); border-radius: 50%; }
    .stat-card .stat-icon { font-size: 1.75rem; margin-bottom: .5rem; opacity: .9; }
    .stat-card .stat-val  { font-size: 2rem; font-weight: 800; line-height: 1; }
    .stat-card .stat-lbl  { font-size: .8rem; opacity: .85; text-transform: uppercase;
        letter-spacing: .05em; margin-top: .25rem; }
    .stat-sent   { background: linear-gradient(135deg,#059669,#047857); }
    .stat-failed { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .stat-auto   { background: linear-gradient(135deg,#3b82f6,#2563eb); }
    .stat-manual { background: linear-gradient(135deg,#f59e0b,#d97706); }

    .filter-bar { background: white; border-radius: 1rem; padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); border: 1px solid #e5e7eb; margin-bottom: 1.5rem; }
    .filter-bar .form-control, .filter-bar .form-select {
        border: 1.5px solid #e5e7eb; border-radius: .5rem; font-size: .875rem; }
    .filter-bar .form-control:focus, .filter-bar .form-select:focus {
        border-color: var(--brand); box-shadow: 0 0 0 3px rgba(5,150,105,.1); }

    .table-card { background: white; border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); border: 1px solid #e5e7eb; overflow: hidden; }
    .table-card-header { padding: 1.25rem 1.5rem; border-bottom: 2px solid #f3f4f6;
        display: flex; justify-content: space-between; align-items: center; }
    .table-card-header h6 { margin: 0; font-weight: 700; font-size: 1rem; }

    .table thead th { background: #f8fafc; font-size: .8rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em; color: #6b7280;
        padding: .875rem 1rem; border-bottom: 2px solid #e5e7eb; }
    .table tbody td { padding: .875rem 1rem; font-size: .875rem;
        vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background: #f9fafb; }
    .table tbody tr:last-child td { border-bottom: none; }

    .badge-type { display: inline-flex; align-items: center; gap: .3rem;
        padding: .3rem .7rem; border-radius: .5rem; font-size: .75rem; font-weight: 600; }
    .type-7day    { background: #dbeafe; color: #1d4ed8; }
    .type-3day    { background: #e0e7ff; color: #4338ca; }
    .type-today   { background: #fef9c3; color: #854d0e; }
    .type-overdue { background: #fee2e2; color: #b91c1c; }
    .type-followup{ background: #fce7f3; color: #9d174d; }
    .type-manual  { background: #d1fae5; color: #065f46; }
    .type-final   { background: #fef3c7; color: #92400e; }
    .status-sent  { background: #d1fae5; color: #065f46; }
    .status-failed{ background: #fee2e2; color: #b91c1c; }

    .sender-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem;
        padding: .2rem .6rem; border-radius: 999px; background: #f3f4f6;
        color: #374151; font-weight: 500; }
    .sender-system { background: #dbeafe; color: #1e40af; }

    .view-btn { width: 32px; height: 32px; border-radius: .5rem; border: 1.5px solid #e5e7eb;
        background: white; display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all .2s; color: #6b7280; }
    .view-btn:hover { background: var(--brand); border-color: var(--brand); color: white; }

    .pagination-row { padding: 1rem 1.5rem; border-top: 1px solid #f3f4f6;
        display: flex; justify-content: space-between; align-items: center; }
    .pagination-row .page-info { font-size: .875rem; color: #6b7280; }
    .btn-page { padding: .4rem .9rem; border-radius: .5rem; border: 1.5px solid #e5e7eb;
        background: white; font-size: .875rem; font-weight: 600; cursor: pointer;
        transition: all .2s; color: #374151; }
    .btn-page:hover:not(:disabled) { border-color: var(--brand); color: var(--brand); }
    .btn-page:disabled { opacity: .4; cursor: not-allowed; }

    .msg-preview { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: .75rem;
        padding: 1rem; font-size: .875rem; white-space: pre-wrap;
        max-height: 320px; overflow-y: auto; line-height: 1.7; }
    .meta-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .meta-chip { background: #f3f4f6; border-radius: .5rem; padding: .35rem .75rem; font-size: .8rem; }
    .meta-chip strong { display: block; font-size: .7rem; color: #9ca3af; text-transform: uppercase; }
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-envelope-check-fill me-2"></i>Email Notification History</h4>
                        <p class="subtitle mb-0">Lahat ng naipadala at failed na email notifications</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="repayments.php" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-left me-1"></i>Collection Monitoring
                        </a>
                        <button class="btn btn-sm btn-outline-light" onclick="loadData()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-sent">
                        <div class="stat-icon">&#x2705;</div>
                        <div class="stat-val" id="stat_sent">&#8212;</div>
                        <div class="stat-lbl">Successfully Sent</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-failed">
                        <div class="stat-icon">&#x274C;</div>
                        <div class="stat-val" id="stat_failed">&#8212;</div>
                        <div class="stat-lbl">Failed</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-auto">
                        <div class="stat-icon"><i class="bi bi-robot"></i></div>
                        <div class="stat-val" id="stat_auto">&#8212;</div>
                        <div class="stat-lbl">Auto-sent by System</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card stat-manual">
                        <div class="stat-icon"><i class="bi bi-person-fill"></i></div>
                        <div class="stat-val" id="stat_manual">&#8212;</div>
                        <div class="stat-lbl">Manually Sent</div>
                    </div>
                </div>
            </div>

            <div class="filter-bar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1">Search Borrower</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Pangalan o email...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Message Type</label>
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="7_days_before">Due Reminder (7d)</option>
                            <option value="3_days_before">Due Reminder (3d)</option>
                            <option value="due_today">Due Today</option>
                            <option value="overdue">Overdue Notice</option>
                            <option value="overdue_followup">Overdue Follow-up</option>
                            <option value="final_notice">Final Notice</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All</option>
                            <option value="sent">Sent</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Date</label>
                        <input type="date" id="dateFilter" class="form-control">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-semibold small mb-1">Rows</label>
                        <select id="limitSelect" class="form-select">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">Clear</button>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-card-header">
                    <h6><i class="bi bi-table me-2 text-success"></i>Email Records</h6>
                    <span id="recordCount" class="text-muted small"></span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Borrower</th>
                                <th>Email Address</th>
                                <th>Loan</th>
                                <th>Message Type</th>
                                <th>Sent By</th>
                                <th>Date &amp; Time</th>
                                <th>Status</th>
                                <th class="text-center">View</th>
                            </tr>
                        </thead>
                        <tbody id="emailTbody">
                            <tr><td colspan="9" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-row">
                    <span class="page-info" id="pageInfo"></span>
                    <div class="d-flex gap-2">
                        <button class="btn-page" id="prevBtn" onclick="prevPage()" disabled>Prev</button>
                        <button class="btn-page" id="nextBtn" onclick="nextPage()" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMsgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:1rem;border:none;">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0f172a,#065f46);border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-open-fill me-2"></i>Email Content</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewMsgBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
let currentPage = 1, currentLimit = 20, totalRecords = 0;
let allRecords = [];
let filters = { search:'', type:'', status:'', date:'' };

const typeLabels = {
    '7_days_before'   : ['type-7day',     '&#128197; Due Reminder (7d)'],
    '3_days_before'   : ['type-3day',     '&#9200; Due Reminder (3d)'],
    'due_today'       : ['type-today',    '&#128276; Due Today'],
    'overdue'         : ['type-overdue',  '&#9888; Overdue Notice'],
    'overdue_followup': ['type-followup', '&#128308; Overdue Follow-up'],
    'final_notice'    : ['type-final',    '&#128680; Final Notice'],
    'manual'          : ['type-manual',   '&#9993; Manual'],
    'reminder'        : ['type-3day',     '&#128248; Reminder'],
};

function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

async function loadData() {
    const tbody = document.getElementById('emailTbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...</td></tr>';
    try {
        const params = new URLSearchParams({
            limit: currentLimit,
            offset: (currentPage-1)*currentLimit,
            search: filters.search,
            type: filters.type,
            status: filters.status,
            date: filters.date,
        });
        const res  = await fetch('email_records.php?' + params);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load');
        allRecords   = data.records || [];
        totalRecords = data.total  || 0;

        const sent   = allRecords.filter(r=>r.status==='sent').length;
        const failed = allRecords.filter(r=>r.status==='failed').length;
        const auto   = allRecords.filter(r=>!r.sent_by_name||r.sent_by_name==='System').length;
        const manual = allRecords.length - auto;
        document.getElementById('stat_sent').textContent   = data.stats?.sent   ?? sent;
        document.getElementById('stat_failed').textContent = data.stats?.failed ?? failed;
        document.getElementById('stat_auto').textContent   = data.stats?.auto   ?? auto;
        document.getElementById('stat_manual').textContent = data.stats?.manual ?? manual;

        renderTable();
        renderPagination();
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>'+esc(e.message)+'</td></tr>';
    }
}

function renderTable() {
    const tbody = document.getElementById('emailTbody');
    const start = (currentPage-1)*currentLimit + 1;
    const end   = Math.min(currentPage*currentLimit, totalRecords);
    document.getElementById('recordCount').textContent = totalRecords > 0 ? 'Showing '+start+'-'+end+' of '+totalRecords+' records' : 'No records';

    if (!allRecords.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Walang email records.</td></tr>';
        return;
    }
    tbody.innerHTML = '';
    allRecords.forEach((r, i) => {
        const [tc, tl] = typeLabels[r.message_type] || ['type-manual', r.message_type];
        const sc = r.status==='sent' ? 'status-sent' : 'status-failed';
        const si = r.status==='sent' ? '&#x2705;' : '&#x274C;';
        const sys = !r.sent_by_name || r.sent_by_name==='System';
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-muted small">'+(start+i)+'</td>'+
            '<td class="fw-semibold">'+esc(r.member_name)+'</td>'+
            '<td class="small text-muted">'+esc(r.member_email||'—')+'</td>'+
            '<td><span class="fw-bold text-success small">'+esc(r.loan_code||('#'+r.loan_id))+'</span></td>'+
            '<td><span class="badge-type '+tc+'">'+tl+'</span></td>'+
            '<td><span class="sender-badge '+(sys?'sender-system':'')+'">'+(sys?'<i class="bi bi-robot me-1"></i>System':'<i class="bi bi-person me-1"></i>'+esc(r.sent_by_name))+'</span></td>'+
            '<td class="small">'+esc(r.sent_at)+'</td>'+
            '<td><span class="badge-type '+sc+'">'+si+' '+(r.status==='sent'?'Sent':'Failed')+'</span></td>'+
            '<td class="text-center"><button class="view-btn" onclick="viewMessage('+r.message_id+')" title="View"><i class="bi bi-eye-fill" style="font-size:.85rem;"></i></button></td>';
        tbody.appendChild(tr);
    });
}

function renderPagination() {
    const totalPages = Math.ceil(totalRecords / currentLimit);
    document.getElementById('pageInfo').textContent = totalPages > 0 ? 'Page '+currentPage+' of '+totalPages : '';
    document.getElementById('prevBtn').disabled = currentPage <= 1;
    document.getElementById('nextBtn').disabled = currentPage >= totalPages;
}

function prevPage() { if (currentPage > 1) { currentPage--; loadData(); } }
function nextPage() { currentPage++; loadData(); }

async function viewMessage(id) {
    const r = allRecords.find(x => x.message_id == id);
    if (!r) return;
    const [tc, tl] = typeLabels[r.message_type] || ['type-manual', r.message_type];
    const sc = r.status==='sent' ? 'status-sent' : 'status-failed';
    document.getElementById('viewMsgBody').innerHTML =
        '<div class="meta-row">'+
        '<div class="meta-chip"><strong>Borrower</strong>'+esc(r.member_name)+'</div>'+
        '<div class="meta-chip"><strong>Email</strong>'+esc(r.member_email||'—')+'</div>'+
        '<div class="meta-chip"><strong>Loan</strong>'+esc(r.loan_code||('#'+r.loan_id))+'</div>'+
        '<div class="meta-chip"><strong>Date Sent</strong>'+esc(r.sent_at)+'</div>'+
        '<div class="meta-chip"><strong>Sent By</strong>'+esc(r.sent_by_name||'System')+'</div>'+
        '</div>'+
        '<div class="d-flex gap-2 mb-3">'+
        '<span class="badge-type '+tc+'">'+tl+'</span>'+
        '<span class="badge-type '+sc+'">'+(r.status==='sent'?'&#x2705; Sent':'&#x274C; Failed')+'</span>'+
        '</div>'+
        (r.error_message ? '<div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><strong>Error:</strong> '+esc(r.error_message)+'</div>' : '')+
        '<label class="fw-bold small text-muted text-uppercase mb-2 d-block">Message Content</label>'+
        '<div class="msg-preview" id="msgPreviewContent">'+esc(r.message_content||'(Loading...)')+'</div>';

    new bootstrap.Modal(document.getElementById('viewMsgModal')).show();

    try {
        const res  = await fetch('email_records.php?id=' + id);
        const data = await res.json();
        const el   = document.getElementById('msgPreviewContent');
        if (el && data.record?.message_content) el.textContent = data.record.message_content;
    } catch(e) {}
}

function clearFilters() {
    filters = { search:'', type:'', status:'', date:'' };
    ['searchInput','typeFilter','statusFilter','dateFilter'].forEach(id => document.getElementById(id).value = '');
    currentPage = 1;
    loadData();
}

function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(()=>fn(...a), ms); }; }

document.getElementById('searchInput').addEventListener('input', debounce(e => {
    filters.search = e.target.value.trim(); currentPage=1; loadData();
}, 400));
['typeFilter','statusFilter','dateFilter','limitSelect'].forEach(id => {
    document.getElementById(id).addEventListener('change', e => {
        if (id==='limitSelect') currentLimit = parseInt(e.target.value);
        else filters[id.replace('Filter','')] = e.target.value;
        currentPage=1; loadData();
    });
});

loadData();
</script>