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
:root {
    --brand:#059669; --brand-dark:#047857;
    --warning:#f59e0b; --danger:#ef4444; --info:#3b82f6;
}
body { background:#f9fafb; font-family:'Segoe UI',system-ui,sans-serif; }

.page-header {
    background:linear-gradient(135deg,#0f172a 0%,#064e3b 60%,#059669 100%);
    padding:2rem; border-radius:1rem; margin-bottom:2rem;
    box-shadow:0 10px 15px -3px rgba(0,0,0,.15); color:white; position:relative; overflow:hidden;
}
.page-header::before {
    content:'\2705'; font-size:6rem; position:absolute; right:2rem; top:50%;
    transform:translateY(-50%); opacity:.06; pointer-events:none;
}
.page-header h4 { margin:0; font-size:1.75rem; font-weight:700; }
.page-header .subtitle { opacity:.85; font-size:.9rem; margin-top:.25rem; }

/* Stat Cards */
.stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
@media(max-width:768px){ .stat-row{ grid-template-columns:repeat(2,1fr); } }
.stat-card { padding:1.25rem; border-radius:.875rem; color:white;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.1); cursor:pointer; transition:transform .2s; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card .val { font-size:2rem; font-weight:800; line-height:1; }
.stat-card .lbl { font-size:.8rem; opacity:.85; text-transform:uppercase; letter-spacing:.05em; margin-top:.25rem; }
.sc-all         { background:linear-gradient(135deg,#1e40af,#1d4ed8); }
.sc-compliant   { background:linear-gradient(135deg,#059669,#047857); }
.sc-verify      { background:linear-gradient(135deg,#d97706,#b45309); }
.sc-incomplete  { background:linear-gradient(135deg,#dc2626,#b91c1c); }

/* Filter bar */
.filter-bar { background:white; border-radius:.875rem; padding:1.25rem 1.5rem;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.08); border:1px solid #e5e7eb; margin-bottom:1.5rem; }

/* Table */
.table-card { background:white; border-radius:.875rem;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.08); border:1px solid #e5e7eb; overflow:hidden; }
.table-card-hdr { padding:1.25rem 1.5rem; border-bottom:2px solid #f3f4f6;
    display:flex; justify-content:space-between; align-items:center; }
.table thead th { background:#f8fafc; font-size:.75rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.05em; color:#6b7280;
    padding:.875rem 1rem; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
.table tbody td { padding:.875rem 1rem; font-size:.875rem;
    vertical-align:middle; border-bottom:1px solid #f3f4f6; }
.table tbody tr:hover { background:#f9fafb; }

/* Status badges */
.status-badge { display:inline-flex; align-items:center; gap:.3rem;
    padding:.3rem .75rem; border-radius:999px; font-size:.78rem; font-weight:700; }
.status-Compliant        { background:#dcfce7; color:#166534; }
.status-For-Verification { background:#fef3c7; color:#92400e; }
.status-Incomplete       { background:#fee2e2; color:#991b1b; }

/* Checklist items */
.check-dots { display:flex; gap:.3rem; }
.check-dot { width:10px; height:10px; border-radius:50%; }
.dot-on  { background:#059669; }
.dot-off { background:#e5e7eb; }

/* Score bar */
.score-bar-wrap { background:#f3f4f6; border-radius:999px; height:8px; min-width:80px; }
.score-bar { height:8px; border-radius:999px; transition:width .4s; }

.action-btn { width:32px; height:32px; border-radius:.5rem; border:1.5px solid #e5e7eb;
    background:white; display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .2s; color:#6b7280; font-size:.85rem; }
.action-btn:hover { background:var(--brand); border-color:var(--brand); color:white; }
.action-btn.warn:hover { background:#f59e0b; border-color:#f59e0b; }

/* Checklist modal */
.checklist-item { display:flex; align-items:flex-start; gap:.75rem; padding:1rem;
    border-radius:.75rem; border:2px solid #e5e7eb; margin-bottom:.75rem;
    transition:all .2s; cursor:pointer; }
.checklist-item:hover { border-color:#059669; background:#f0fdf4; }
.checklist-item.checked { border-color:#059669; background:#f0fdf4; }
.checklist-item.checked .check-box { background:#059669; border-color:#059669; }
.checklist-item.checked .check-box::after { display:block; }
.check-box { width:22px; height:22px; border-radius:.375rem; border:2px solid #d1d5db;
    flex-shrink:0; margin-top:.1rem; position:relative; transition:all .2s; }
.check-box::after { content:'✓'; display:none; position:absolute; top:50%; left:50%;
    transform:translate(-50%,-50%); color:white; font-size:.85rem; font-weight:bold; }
.check-label { font-weight:600; font-size:.9rem; color:#1f2937; }
.check-desc  { font-size:.8rem; color:#6b7280; margin-top:.15rem; }
.check-auto  { font-size:.75rem; color:#059669; font-weight:600; margin-top:.25rem; }

.flow-steps { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.5rem; }
.flow-step { padding:.35rem .75rem; border-radius:.5rem; font-size:.78rem;
    font-weight:600; background:#f1f5f9; color:#64748b; border:1.5px solid #e2e8f0; }
.flow-step.done { background:#dcfce7; color:#166534; border-color:#86efac; }
.flow-step.pending { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
</style>

<div class="main-wrap">
<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-shield-check me-2"></i>Compliance Checker</h4>
                <p class="subtitle mb-0">6-point compliance checklist per loan — Compliant, For Verification, Incomplete</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-light" onclick="syncAll()">
                    <i class="bi bi-arrow-repeat me-1"></i>Auto-Check All Loans
                </button>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-row">
        <div class="stat-card sc-all" onclick="filterByStatus('')">
            <div class="lbl">Total Loans</div>
            <div class="val" id="stat_total">—</div>
        </div>
        <div class="stat-card sc-compliant" onclick="filterByStatus('Compliant')">
            <div class="lbl">✅ Compliant</div>
            <div class="val" id="stat_compliant">—</div>
            <div class="small opacity-75 mt-1">6/6 items</div>
        </div>
        <div class="stat-card sc-verify" onclick="filterByStatus('For Verification')">
            <div class="lbl">🔄 For Verification</div>
            <div class="val" id="stat_verify">—</div>
            <div class="small opacity-75 mt-1">3–5 items</div>
        </div>
        <div class="stat-card sc-incomplete" onclick="filterByStatus('Incomplete')">
            <div class="lbl">❌ Incomplete</div>
            <div class="val" id="stat_incomplete">—</div>
            <div class="small opacity-75 mt-1">0–2 items</div>
        </div>
    </div>

    <!-- Flow -->
    <div class="filter-bar mb-3 py-3">
        <div class="small fw-bold text-muted mb-2">COMPLIANCE FLOW:</div>
        <div class="flow-steps">
            <span class="flow-step">Loan Application</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Pending Loan List</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Credit Investigation</span>
            <span class="text-muted">→</span>
            <span class="flow-step">CI Feedback</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Loan Calculation</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Approve / Reject</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Disbursement</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Collection Monitoring</span>
            <span class="text-muted">→</span>
            <span class="flow-step">Email Notification</span>
            <span class="text-muted">→</span>
            <span class="flow-step done">✅ Compliance Check</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Search</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Member name or Loan code...">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small mb-1">Status</label>
                <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="Compliant">✅ Compliant</option>
                    <option value="For Verification">🔄 For Verification</option>
                    <option value="Incomplete">❌ Incomplete</option>
                </select>
            </div>
            <div class="col-md-2">
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

    <!-- Table -->
    <div class="table-card">
        <div class="table-card-hdr">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-success"></i>Loan Compliance Records</h6>
            <span id="recordCount" class="text-muted small"></span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Loan Code</th>
                        <th>Member</th>
                        <th>Loan Status</th>
                        <th>Checklist</th>
                        <th>Score</th>
                        <th>Compliance</th>
                        <th>Last Checked</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="complianceTbody">
                    <tr><td colspan="9" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
            <span class="text-muted small" id="pageInfo"></span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="prevBtn" onclick="prevPage()" disabled>← Prev</button>
                <button class="btn btn-sm btn-outline-secondary" id="nextBtn" onclick="nextPage()" disabled>Next →</button>
            </div>
        </div>
    </div>

</div>
</main>
</div>

<!-- Checklist Modal -->
<div class="modal fade" id="checklistModal" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:1rem;border:none;">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0f172a,#064e3b);border-radius:1rem 1rem 0 0;padding:1.25rem 1.5rem;">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-shield-check me-2"></i>Compliance Checklist</h5>
                    <div class="small opacity-75 mt-1" id="modal_loan_info">Loading...</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <!-- Status preview -->
                <div class="d-flex align-items-center justify-content-between mb-4 p-3 rounded-3" style="background:#f8fafc;border:1.5px solid #e5e7eb;">
                    <div>
                        <div class="small text-muted fw-semibold">COMPLIANCE STATUS</div>
                        <div id="modal_status_badge" class="mt-1"></div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted fw-semibold">SCORE</div>
                        <div class="fw-bold fs-4" id="modal_score_text">0/6</div>
                    </div>
                </div>

                <!-- Checklist items -->
                <div id="checklistItems">
                    <?php
                    $items = [
                        ['key'=>'complete_documents',  'label'=>'Complete Documents',    'desc'=>'Member has complete profile: name, address, contact, birthdate, email', 'icon'=>'bi-file-earmark-check'],
                        ['key'=>'valid_id',             'label'=>'Valid ID',              'desc'=>'Member has a registered email / valid identification on file',          'icon'=>'bi-person-badge'],
                        ['key'=>'ci_completed',         'label'=>'CI Completed',          'desc'=>'Credit Investigation has been conducted and recorded in the system',    'icon'=>'bi-search'],
                        ['key'=>'approved_loan',        'label'=>'Approved Loan',         'desc'=>'Loan has been reviewed and approved (status: Active/Approved)',         'icon'=>'bi-check-circle'],
                        ['key'=>'disbursement_record',  'label'=>'Disbursement Record',   'desc'=>'A disbursement record exists for this loan',                           'icon'=>'bi-cash-coin'],
                        ['key'=>'payment_records',      'label'=>'Payment Records',       'desc'=>'At least one repayment has been recorded for this loan',               'icon'=>'bi-receipt'],
                    ];
                    foreach($items as $item): ?>
                    <div class="checklist-item" id="item_<?= $item['key'] ?>" onclick="toggleCheck('<?= $item['key'] ?>')">
                        <div class="check-box" id="box_<?= $item['key'] ?>"></div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $item['icon'] ?> text-success"></i>
                                <span class="check-label"><?= $item['label'] ?></span>
                                <span class="check-auto d-none" id="auto_<?= $item['key'] ?>">● Auto-detected</span>
                            </div>
                            <div class="check-desc"><?= $item['desc'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Notes -->
                <div class="mt-3">
                    <label class="form-label fw-semibold small">Notes (optional)</label>
                    <textarea id="modal_notes" class="form-control" rows="2" placeholder="Additional remarks..."></textarea>
                </div>

            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button class="btn btn-outline-secondary" onclick="autoDetect()">
                    <i class="bi bi-magic me-1"></i>Auto-Detect
                </button>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success px-4" onclick="saveChecklist()">
                    <i class="bi bi-save me-1"></i>Save Compliance
                </button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>
<script>
let currentPage = 1, currentLimit = 20, totalRecords = 0;
let filters = { search:'', status:'' };
let currentLoan = null;

const checkState = {
    complete_documents: false, valid_id: false, ci_completed: false,
    approved_loan: false, disbursement_record: false, payment_records: false
};

const statusCfg = {
    'Compliant':        { cls:'status-Compliant',        icon:'✅', label:'Compliant' },
    'For Verification': { cls:'status-For-Verification', icon:'🔄', label:'For Verification' },
    'Incomplete':       { cls:'status-Incomplete',       icon:'❌', label:'Incomplete' },
};

function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

function computeStatus(checks) {
    const score = Object.values(checks).filter(Boolean).length;
    if (score >= 6) return { status:'Compliant',        score };
    if (score >= 3) return { status:'For Verification', score };
    return                  { status:'Incomplete',       score };
}

function updateModalStatus() {
    const { status, score } = computeStatus(checkState);
    const cfg = statusCfg[status];
    document.getElementById('modal_status_badge').innerHTML =
        `<span class="status-badge ${cfg.cls}">${cfg.icon} ${cfg.label}</span>`;
    document.getElementById('modal_score_text').textContent = score + '/6';
}

function toggleCheck(key) {
    checkState[key] = !checkState[key];
    const item = document.getElementById('item_' + key);
    const box  = document.getElementById('box_'  + key);
    item.classList.toggle('checked', checkState[key]);
    box.style.background     = checkState[key] ? '#059669' : '';
    box.style.borderColor    = checkState[key] ? '#059669' : '';
    box.style.setProperty('--show', checkState[key] ? 'block' : 'none');
    box.innerHTML = checkState[key] ? '<span style="color:white;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:bold;font-size:.85rem">✓</span>' : '';
    updateModalStatus();
}

function setCheck(key, val) {
    checkState[key] = Boolean(val);
    const item = document.getElementById('item_' + key);
    const box  = document.getElementById('box_'  + key);
    if (item) item.classList.toggle('checked', checkState[key]);
    if (box) {
        box.style.background  = checkState[key] ? '#059669' : '';
        box.style.borderColor = checkState[key] ? '#059669' : '';
        box.innerHTML = checkState[key] ? '<span style="color:white;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:bold;font-size:.85rem">✓</span>' : '';
    }
}

function resetChecklist() {
    Object.keys(checkState).forEach(k => {
        checkState[k] = false;
        setCheck(k, false);
        const autoEl = document.getElementById('auto_' + k);
        if (autoEl) autoEl.classList.add('d-none');
    });
    document.getElementById('modal_notes').value = '';
    updateModalStatus();
}

async function openChecklist(loan) {
    currentLoan = loan;
    resetChecklist();

    // Load existing checklist values
    ['complete_documents','valid_id','ci_completed','approved_loan','disbursement_record','payment_records'].forEach(k => {
        if (parseInt(loan[k])) setCheck(k, true);
    });
    document.getElementById('modal_notes').value = loan.notes || '';
    document.getElementById('modal_loan_info').textContent =
        `${loan.loan_code} — ${loan.full_name} (${loan.loan_status})`;
    updateModalStatus();

    new bootstrap.Modal(document.getElementById('checklistModal')).show();
}

async function autoDetect() {
    if (!currentLoan) return;
    const btn = document.querySelector('[onclick="autoDetect()"]');
    btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Detecting...';
    btn.disabled = true;

    try {
        const res  = await fetch('compliance_checker_action.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'auto_check', loan_id: currentLoan.loan_id })
        });
        const data = await res.json();
        if (data.success) {
            ['complete_documents','valid_id','ci_completed','approved_loan','disbursement_record','payment_records'].forEach(k => {
                setCheck(k, data[k]);
                const autoEl = document.getElementById('auto_' + k);
                if (autoEl) autoEl.classList.toggle('d-none', !data[k]);
            });
            updateModalStatus();
        } else {
            alert('Auto-detect failed: ' + data.message);
        }
    } catch(e) { alert('Error: ' + e.message); }
    finally {
        btn.innerHTML = '<i class="bi bi-magic me-1"></i>Auto-Detect';
        btn.disabled = false;
    }
}

async function saveChecklist() {
    if (!currentLoan) return;
    const btn = document.querySelector('[onclick="saveChecklist()"]');
    btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div>Saving...';
    btn.disabled = true;

    try {
        const payload = {
            action: 'save',
            loan_id: currentLoan.loan_id,
            member_id: currentLoan.member_id,
            notes: document.getElementById('modal_notes').value,
            ...Object.fromEntries(Object.entries(checkState).map(([k,v]) => [k, v ? 1 : 0]))
        };

        const res  = await fetch('compliance_checker_action.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('checklistModal'))?.hide();
            await Swal.fire({
                icon: data.compliance_status === 'Compliant' ? 'success' :
                      data.compliance_status === 'For Verification' ? 'warning' : 'error',
                title: data.compliance_status,
                html: `Score: <b>${data.checked_score}/6</b> items checked.<br>${data.message}`,
                timer: 2500, showConfirmButton: false
            });
            loadData();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch(e) { Swal.fire('Error', e.message, 'error'); }
    finally {
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Compliance';
        btn.disabled = false;
    }
}

async function syncAll() {
    const confirm = await Swal.fire({
        title: 'Auto-Check All Loans?',
        html: 'Ia-auto-detect ang compliance status ng lahat ng loans base sa database records.<br><small class="text-muted">Pwede mo pa ring i-edit manually after.</small>',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#059669',
        confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Yes, Check All'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({ title:'Checking all loans...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

    try {
        const res  = await fetch('compliance_checker_action.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'sync_all' })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ icon:'success', title:'Done!',
                html:`Auto-checked <b>${data.synced}</b> loans.`, confirmButtonColor:'#059669' });
            loadData();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch(e) { Swal.fire('Error', e.message, 'error'); }
}

function filterByStatus(s) {
    filters.status = s;
    document.getElementById('statusFilter').value = s;
    currentPage = 1; loadData();
}

async function loadData() {
    const tbody = document.getElementById('complianceTbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...</td></tr>';

    const params = new URLSearchParams({
        action:'list', limit:currentLimit,
        offset:(currentPage-1)*currentLimit,
        search:filters.search, status:filters.status
    });

    const res  = await fetch('compliance_checker_action.php?' + params);
    const data = await res.json();
    if (!data.success) { tbody.innerHTML='<tr><td colspan="9" class="text-center text-danger py-4">Error</td></tr>'; return; }

    totalRecords = data.total || 0;
    const st = data.stats || {};
    document.getElementById('stat_total').textContent      = st.total_loans      || 0;
    document.getElementById('stat_compliant').textContent  = st.compliant        || 0;
    document.getElementById('stat_verify').textContent     = st.for_verification || 0;
    document.getElementById('stat_incomplete').textContent = st.incomplete       || 0;

    const start = (currentPage-1)*currentLimit+1;
    const end   = Math.min(currentPage*currentLimit, totalRecords);
    document.getElementById('recordCount').textContent = totalRecords ? `Showing ${start}–${end} of ${totalRecords}` : '0 records';

    const keys = ['complete_documents','valid_id','ci_completed','approved_loan','disbursement_record','payment_records'];
    const icons = ['📄','🪪','🔍','✅','💰','💳'];

    if (!data.records?.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Walang records.</td></tr>';
    } else {
        tbody.innerHTML = '';
        data.records.forEach((r, i) => {
            const cs      = r.compliance_status || 'Incomplete';
            const cfg     = statusCfg[cs] || statusCfg['Incomplete'];
            const score   = parseInt(r.checked_score) || 0;
            const pct     = Math.round((score/6)*100);
            const barColor= cs==='Compliant' ? '#059669' : cs==='For Verification' ? '#f59e0b' : '#ef4444';
            const dots    = keys.map((k,idx) =>
                `<span class="check-dot ${parseInt(r[k]) ? 'dot-on':'dot-off'}" title="${icons[idx]} ${k.replace(/_/g,' ')}"></span>`
            ).join('');

            const tr = document.createElement('tr');
            tr.innerHTML =
                `<td class="text-muted small fw-bold">${start+i}</td>`+
                `<td><span class="fw-bold text-success">${esc(r.loan_code)}</span></td>`+
                `<td><div class="fw-semibold">${esc(r.full_name)}</div><div class="small text-muted">${esc(r.email||'')}</div></td>`+
                `<td><span class="badge bg-${r.loan_status==='Active'?'success':r.loan_status==='Completed'?'primary':'secondary'}">${esc(r.loan_status)}</span></td>`+
                `<td><div class="check-dots">${dots}</div><div class="small text-muted mt-1">${score}/6 items</div></td>`+
                `<td><div class="score-bar-wrap"><div class="score-bar" style="width:${pct}%;background:${barColor}"></div></div><div class="small text-muted mt-1">${pct}%</div></td>`+
                `<td><span class="status-badge ${cfg.cls}">${cfg.icon} ${cfg.label}</span></td>`+
                `<td class="small text-muted">${r.last_checked_at ? r.last_checked_at.substring(0,10) : '—'}<br>${r.last_checked_by_name ? '<span class="text-xs">by '+esc(r.last_checked_by_name)+'</span>' : ''}</td>`+
                `<td class="text-center">
                    <button class="action-btn warn" onclick='openChecklist(${JSON.stringify(r)})' title="Check Compliance"><i class="bi bi-clipboard2-check"></i></button>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('prevBtn').disabled = currentPage <= 1;
    document.getElementById('nextBtn').disabled = currentPage >= Math.ceil(totalRecords/currentLimit);
    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${Math.ceil(totalRecords/currentLimit)||1}`;
}

function prevPage() { if(currentPage>1){ currentPage--; loadData(); } }
function nextPage() { currentPage++; loadData(); }
function clearFilters() { filters={search:'',status:''}; document.getElementById('searchInput').value=''; document.getElementById('statusFilter').value=''; currentPage=1; loadData(); }

function debounce(fn,ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
document.getElementById('searchInput').addEventListener('input', debounce(e=>{ filters.search=e.target.value.trim(); currentPage=1; loadData(); },400));
document.getElementById('statusFilter').addEventListener('change', e=>{ filters.status=e.target.value; currentPage=1; loadData(); });
document.getElementById('limitSelect').addEventListener('change',  e=>{ currentLimit=parseInt(e.target.value); currentPage=1; loadData(); });

loadData();
</script>