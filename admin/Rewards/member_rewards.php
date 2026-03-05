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
:root { --brand:#059669; --brand-dark:#047857; }
body { background:#f9fafb; font-family:'Segoe UI',system-ui,sans-serif; }

.page-header {
    background: linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#854d0e 100%);
    padding:2rem; border-radius:1rem; margin-bottom:2rem;
    box-shadow:0 10px 15px -3px rgba(0,0,0,.15); color:white; position:relative; overflow:hidden;
}
.page-header::before {
    content:'\1F3C6'; position:absolute; right:2rem; top:50%; transform:translateY(-50%);
    font-size:6rem; opacity:.08; pointer-events:none;
}
.page-header h4 { margin:0; font-size:1.75rem; font-weight:700; }
.page-header .subtitle { opacity:.8; font-size:.9rem; margin-top:.25rem; }

/* Tier badges */
.tier-badge { display:inline-flex; align-items:center; gap:.3rem; padding:.3rem .8rem;
    border-radius:.5rem; font-size:.8rem; font-weight:700; }
.tier-Bronze   { background:#fef3c7; color:#92400e; }
.tier-Silver   { background:#f1f5f9; color:#475569; }
.tier-Gold     { background:#fef9c3; color:#854d0e; }
.tier-Platinum { background:#ede9fe; color:#5b21b6; }

/* Stat cards */
.stat-card { border-radius:1rem; padding:1.5rem; color:white;
    position:relative; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,.1); }
.stat-card::after { content:''; position:absolute; right:-20px; top:-20px;
    width:80px; height:80px; background:rgba(255,255,255,.1); border-radius:50%; }
.stat-card .val { font-size:2rem; font-weight:800; line-height:1; }
.stat-card .lbl { font-size:.8rem; opacity:.85; text-transform:uppercase; letter-spacing:.05em; margin-top:.25rem; }
.stat-card .ico { font-size:1.75rem; margin-bottom:.5rem; }
.sc-total    { background:linear-gradient(135deg,#f59e0b,#d97706); }
.sc-platinum { background:linear-gradient(135deg,#7c3aed,#5b21b6); }
.sc-gold     { background:linear-gradient(135deg,#d97706,#b45309); }
.sc-silver   { background:linear-gradient(135deg,#64748b,#475569); }

/* Table card */
.table-card { background:white; border-radius:1rem;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.1); border:1px solid #e5e7eb; overflow:hidden; }
.table-card-header { padding:1.25rem 1.5rem; border-bottom:2px solid #f3f4f6;
    display:flex; justify-content:space-between; align-items:center; }
.table-card-header h6 { margin:0; font-weight:700; font-size:1rem; }
.table thead th { background:#f8fafc; font-size:.78rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.04em; color:#6b7280;
    padding:.875rem 1rem; border-bottom:2px solid #e5e7eb; }
.table tbody td { padding:.875rem 1rem; font-size:.875rem;
    vertical-align:middle; border-bottom:1px solid #f3f4f6; }
.table tbody tr:hover { background:#f9fafb; }

/* Points bar */
.pts-bar-wrap { background:#f3f4f6; border-radius:999px; height:8px; width:120px; }
.pts-bar      { height:8px; border-radius:999px; transition:width .4s; }
.bar-Bronze   { background:#f59e0b; }
.bar-Silver   { background:#94a3b8; }
.bar-Gold     { background:#d97706; }
.bar-Platinum { background:#7c3aed; }

.action-btn { width:32px; height:32px; border-radius:.5rem; border:1.5px solid #e5e7eb;
    background:white; display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .2s; color:#6b7280; font-size:.85rem; }
.action-btn:hover { background:var(--brand); border-color:var(--brand); color:white; }
.action-btn.orange:hover { background:#f59e0b; border-color:#f59e0b; color:white; }

.filter-bar { background:white; border-radius:1rem; padding:1.25rem 1.5rem;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.1); border:1px solid #e5e7eb; margin-bottom:1.5rem; }
.filter-bar .form-control, .filter-bar .form-select {
    border:1.5px solid #e5e7eb; border-radius:.5rem; font-size:.875rem; }
.filter-bar .form-control:focus, .filter-bar .form-select:focus {
    border-color:var(--brand); box-shadow:0 0 0 3px rgba(5,150,105,.1); }

.pagination-row { padding:1rem 1.5rem; border-top:1px solid #f3f4f6;
    display:flex; justify-content:space-between; align-items:center; }
.page-info { font-size:.875rem; color:#6b7280; }
.btn-page { padding:.4rem .9rem; border-radius:.5rem; border:1.5px solid #e5e7eb;
    background:white; font-size:.875rem; font-weight:600; cursor:pointer;
    transition:all .2s; color:#374151; }
.btn-page:hover:not(:disabled) { border-color:var(--brand); color:var(--brand); }
.btn-page:disabled { opacity:.4; cursor:not-allowed; }

.log-item { display:flex; align-items:center; gap:.75rem; padding:.65rem .75rem;
    border-radius:.5rem; margin-bottom:.4rem; background:#f9fafb; border:1px solid #f3f4f6; }
.log-pts { font-weight:700; color:var(--brand); min-width:50px; text-align:right; }
.log-reason { font-size:.82rem; color:#374151; }
.log-date { font-size:.75rem; color:#9ca3af; margin-left:auto; white-space:nowrap; }
</style>

<div class="main-wrap">
<main class="main-content" id="main-content">
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4>&#x1F3C6; Member Rewards</h4>
                <p class="subtitle mb-0">Points, tiers, at benefits ng bawat member</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-light" onclick="syncAll()">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync All Payments
                </button>
                <button class="btn btn-sm btn-danger" onclick="applyPenalties()">
                    <i class="bi bi-dash-circle me-1"></i>Apply Penalties
                </button>
                <button class="btn btn-sm btn-warning" onclick="openManualAdd()">
                    <i class="bi bi-plus-circle me-1"></i>Manual Add Points
                </button>
            </div>
        </div>
    </div>

    <!-- Tier Guide -->
    <div class="row g-3 mb-4 align-items-stretch">
        <div class="col-6 col-md-3">
            <div class="stat-card sc-silver">
                <div class="ico">&#x1F949;</div>
                <div class="val" id="stat_bronze">—</div>
                <div class="lbl">Bronze Members</div>
                <div class="mt-2 small opacity-75">0–199 pts</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card sc-silver" style="background:linear-gradient(135deg,#94a3b8,#64748b)">
                <div class="ico">&#x1F948;</div>
                <div class="val" id="stat_silver">—</div>
                <div class="lbl">Silver Members</div>
                <div class="mt-2 small opacity-75">200–499 pts</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card sc-gold">
                <div class="ico">&#x1F947;</div>
                <div class="val" id="stat_gold">—</div>
                <div class="lbl">Gold Members</div>
                <div class="mt-2 small opacity-75">500–999 pts</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card sc-platinum">
                <div class="ico">&#x1F4CD;</div>
                <div class="val" id="stat_platinum">—</div>
                <div class="lbl">Platinum Members</div>
                <div class="mt-2 small opacity-75">1000+ pts</div>
            </div>
        </div>
    </div>

    <!-- Points legend -->
    <div class="filter-bar mb-3 py-3">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="fw-bold small text-muted">POINTS EARNED:</span>
            <span class="badge bg-success">+10 On-time Payment</span>
            <span class="badge bg-primary">+20 Early Payment Bonus</span>
            <span class="badge bg-warning text-dark">+50 Full Loan Completion</span>
            <span class="badge bg-secondary">+Custom Manual Add</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Search Member</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Pangalan...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small mb-1">Tier</label>
                <select id="tierFilter" class="form-select">
                    <option value="">All Tiers</option>
                    <option value="Bronze">&#x1F949; Bronze</option>
                    <option value="Silver">&#x1F948; Silver</option>
                    <option value="Gold">&#x1F947; Gold</option>
                    <option value="Platinum">&#x1F4CD; Platinum</option>
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
        <div class="table-card-header">
            <h6><i class="bi bi-trophy-fill me-2 text-warning"></i>Member Rewards Leaderboard</h6>
            <span id="recordCount" class="text-muted small"></span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Tier</th>
                        <th>Points</th>
                        <th>Progress</th>
                        <th>On-time Payments</th>
                        <th>Best Streak</th>
                        <th>Last Reward</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="rewardsTbody">
                    <tr><td colspan="9" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-warning me-2"></div>Loading...
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-row">
            <span class="page-info" id="pageInfo"></span>
            <div class="d-flex gap-2">
                <button class="btn-page" id="prevBtn" onclick="prevPage()" disabled>&#8592; Prev</button>
                <button class="btn-page" id="nextBtn" onclick="nextPage()" disabled>Next &#8594;</button>
            </div>
        </div>
    </div>

</div>
</main>
</div>

<!-- Log Modal -->
<div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:1rem;border:none;">
            <div class="modal-header text-white fw-bold" style="background:linear-gradient(135deg,#0f172a,#854d0e);border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Points History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="logModalBody">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>
<script>
let currentPage = 1, currentLimit = 20, totalRecords = 0;
let allRecords = [];
let filters = { search:'', tier:'' };

const tierEmoji = { Bronze:'&#x1F949;', Silver:'&#x1F948;', Gold:'&#x1F947;', Platinum:'&#x1F4CD;' };
const tierMax   = { Bronze:200, Silver:500, Gold:1000, Platinum:1000 };

function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

async function loadData() {
    const tbody = document.getElementById('rewardsTbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-warning me-2"></div>Loading...</td></tr>';

    const params = new URLSearchParams({
        action:'list', limit:currentLimit,
        offset:(currentPage-1)*currentLimit,
        search:filters.search, tier:filters.tier
    });

    const res  = await fetch('rewards_action.php?' + params);
    const data = await res.json();
    if (!data.success) { tbody.innerHTML='<tr><td colspan="9" class="text-center text-danger py-4">Error loading data</td></tr>'; return; }

    allRecords   = data.records || [];
    totalRecords = data.total   || 0;

    const st = data.stats || {};
    document.getElementById('stat_bronze').textContent   = st.bronze_cnt  || 0;
    document.getElementById('stat_silver').textContent   = st.silver      || 0;
    document.getElementById('stat_gold').textContent     = st.gold        || 0;
    document.getElementById('stat_platinum').textContent = st.platinum    || 0;

    const start = (currentPage-1)*currentLimit+1;
    const end   = Math.min(currentPage*currentLimit, totalRecords);
    document.getElementById('recordCount').textContent = totalRecords > 0 ? `Showing ${start}–${end} of ${totalRecords}` : 'No records';

    if (!allRecords.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Walang rewards data.</td></tr>';
    } else {
        tbody.innerHTML = '';
        allRecords.forEach((r, i) => {
            const tier    = r.tier || 'Bronze';
            const pts     = parseInt(r.points) || 0;
            const maxPts  = tierMax[tier] || 1000;
            const pct     = Math.min(100, Math.round((pts / maxPts) * 100));
            const nextTier = tier === 'Bronze' ? 'Silver' : tier === 'Silver' ? 'Gold' : tier === 'Gold' ? 'Platinum' : '★ Max';
            const streak  = parseInt(r.consecutive_on_time) || 0;
            const best    = parseInt(r.best_streak) || 0;

            const tr = document.createElement('tr');
            tr.innerHTML =
                `<td class="text-muted small fw-bold">${start+i}</td>`+
                `<td><div class="fw-semibold">${esc(r.full_name)}</div><div class="small text-muted">${esc(r.email||'')}</div></td>`+
                `<td><span class="tier-badge tier-${tier}">${tierEmoji[tier]||''} ${tier}</span></td>`+
                `<td><span class="fw-bold fs-6">${pts.toLocaleString()}</span> <span class="text-muted small">pts</span></td>`+
                `<td>
                    <div class="pts-bar-wrap"><div class="pts-bar bar-${tier}" style="width:${pct}%"></div></div>
                    <div class="small text-muted mt-1">${pct}% → ${nextTier}</div>
                </td>`+
                `<td class="text-center"><span class="fw-bold text-success">${r.total_on_time_payments}</span></td>`+
                `<td class="text-center"><span class="badge bg-info text-dark">${best} streak</span></td>`+
                `<td class="small text-muted">${r.last_reward_date ? r.last_reward_date.substring(0,10) : '—'}</td>`+
                `<td class="text-center">
                    <button class="action-btn orange me-1" onclick="openManualAdd(${r.member_id},'${esc(r.full_name)}')" title="Add Points"><i class="bi bi-plus-circle"></i></button>
                    <button class="action-btn" onclick="viewLog(${r.member_id},'${esc(r.full_name)}')" title="View History"><i class="bi bi-clock-history"></i></button>
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

function clearFilters() {
    filters = { search:'', tier:'' };
    document.getElementById('searchInput').value = '';
    document.getElementById('tierFilter').value  = '';
    currentPage = 1; loadData();
}

async function viewLog(memberId, name) {
    document.getElementById('logModalBody').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>';
    new bootstrap.Modal(document.getElementById('logModal')).show();

    const res  = await fetch(`rewards_action.php?action=log&member_id=${memberId}`);
    const data = await res.json();

    if (!data.success || !data.logs.length) {
        document.getElementById('logModalBody').innerHTML = `<p class="text-muted text-center py-3">Walang points history para kay ${esc(name)}.</p>`;
        return;
    }

    const html = `<h6 class="fw-bold mb-3">&#x1F3C6; ${esc(name)}</h6>` +
        data.logs.map(l => `
            <div class="log-item">
                <i class="bi bi-star-fill text-warning"></i>
                <div>
                    <div class="log-reason">${esc(l.reason)}</div>
                    <div class="small text-muted">by ${esc(l.recorded_by_name)}</div>
                </div>
                <span class="log-pts">+${l.points}</span>
                <span class="log-date">${(l.created_at||'').substring(0,10)}</span>
            </div>`).join('');

    document.getElementById('logModalBody').innerHTML = html;
}

async function openManualAdd(memberId = null, name = null) {
    // Build member dropdown if no specific member
    let memberHtml = '';
    if (!memberId) {
        const res  = await fetch('rewards_action.php?action=list&limit=100&offset=0');
        const data = await res.json();
        const opts = (data.records||[]).map(r => `<option value="${r.member_id}">${esc(r.full_name)}</option>`).join('');
        memberHtml = `<label class="form-label fw-semibold small">Member</label>
                      <select class="form-select mb-3" id="swal_member">${opts}</select>`;
    }

    const { value: formValues } = await Swal.fire({
        title: '&#x2795; Add Points Manually',
        html: `${memberHtml}
               ${memberId ? `<div class="fw-bold mb-3 text-success">&#x1F3C6; ${name}</div>` : ''}
               <label class="form-label fw-semibold small">Points to Add</label>
               <input id="swal_pts" class="swal2-input" type="number" min="1" max="500" value="10" placeholder="Points (max 500)">
               <label class="form-label fw-semibold small mt-2">Reason</label>
               <input id="swal_reason" class="swal2-input" placeholder="Reason for adding points">`,
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Add Points',
        preConfirm: () => {
            const mid    = memberId || parseInt(document.getElementById('swal_member')?.value);
            const pts    = parseInt(document.getElementById('swal_pts').value);
            const reason = document.getElementById('swal_reason').value.trim();
            if (!pts || pts < 1)   { Swal.showValidationMessage('Points must be > 0'); return false; }
            if (!reason)           { Swal.showValidationMessage('Reason is required'); return false; }
            return { member_id: mid, points: pts, reason };
        }
    });

    if (!formValues) return;

    const res  = await fetch('rewards_action.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'manual_add', ...formValues })
    });
    const data = await res.json();

    if (data.success) {
        await Swal.fire({
            icon:'success',
            title:'Points Added!',
            html:`<b>+${formValues.points}</b> points added!<br>New Total: <b>${data.new_points}</b> pts<br>Tier: <b>${data.new_tier}</b>`,
            timer:2500, showConfirmButton:false
        });
        loadData();
    } else {
        Swal.fire('Error', data.message, 'error');
    }
}

async function applyPenalties() {
    const confirm = await Swal.fire({
        title: '⚠️ Apply Penalties?',
        html: `
            <div class="text-start">
                <p>Mag-a-apply ng point deductions sa mga:</p>
                <ul>
                    <li>🔴 <b>-30 pts</b> — Hindi nagbayad ng <b>2 months</b></li>
                    <li>🚫 <b>-50 pts</b> — Hindi nagbayad ng <b>3+ months</b></li>
                    <li>🔄 <b>Streak reset</b> — Walang active loan (inactive)</li>
                </ul>
                <small class="text-muted">1x per month lang mag-a-apply ang penalty sa bawat member.</small>
            </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-dash-circle"></i> Yes, Apply Penalties',
        cancelButtonText: 'Cancel'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({ title: 'Applying penalties...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('rewards_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_penalties' })
        });
        const data = await res.json();

        if (data.success) {
            const details = (data.details || []).map(d => `<li>${d}</li>`).join('');
            Swal.fire({
                icon: data.deducted_count > 0 ? 'warning' : 'info',
                title: data.deducted_count > 0 ? `Penalties Applied!` : 'No Penalties Needed',
                html: data.deducted_count > 0
                    ? `<b>${data.deducted_count}</b> member(s) penalized.<br>Total deducted: <b>-${data.total_deducted} pts</b>
                       ${details ? '<hr><ul class="text-start small">' + details + '</ul>' : ''}`
                    : 'Walang member na kailangang parusahan ngayon.',
                confirmButtonColor: '#ef4444'
            });
            loadData();
        } else {
            Swal.fire('Failed', data.message || 'Unknown error', 'error');
        }
    } catch(e) {
        Swal.fire('Error', e.message, 'error');
    }
}

async function syncAll() {
    const confirm = await Swal.fire({
        title: 'Sync All Payments?',
        html: 'Ibe-base sa lahat ng repayment records at mag-a-award ng missing points.<br><small class="text-muted">Ligtas — hindi mag-do-double award.</small>',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#059669',
        confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Yes, Sync Now'
    });
    if (!confirm.isConfirmed) return;

    Swal.fire({ title:'Syncing...', text:'Sandali lang...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 90000); // 90s timeout

        const res = await fetch('rewards_action.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'sync_all' }),
            signal: controller.signal
        });
        clearTimeout(timeout);

        const text = await res.text();
        let data;
        try { data = JSON.parse(text); }
        catch(e) { throw new Error('Invalid server response: ' + text.substring(0,200)); }

        if (data.success) {
            const pts = data.total_points_awarded || 0;
            const cnt = data.synced_count || 0;
            Swal.fire({
                icon: cnt > 0 ? 'success' : 'info',
                title: cnt > 0 ? 'Sync Complete!' : 'Already Up to Date',
                html: cnt > 0
                    ? `Na-award ang points sa <b>${cnt}</b> payment(s).<br>Total Points: <b>+${pts}</b>`
                    : 'Lahat ng payments ay may rewards na.',
                confirmButtonColor:'#059669'
            });
            loadData();
        } else {
            Swal.fire('Sync Failed', data.message || 'Unknown error', 'error');
        }
    } catch(e) {
        if (e.name === 'AbortError') {
            Swal.fire('Timeout', 'Sync took too long. Try again.', 'warning');
        } else {
            Swal.fire('Error', e.message, 'error');
        }
    }
}

// Filters
function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

document.getElementById('searchInput').addEventListener('input', debounce(e=>{
    filters.search = e.target.value.trim(); currentPage=1; loadData();
}, 400));
['tierFilter','limitSelect'].forEach(id=>{
    document.getElementById(id).addEventListener('change', e=>{
        if (id==='limitSelect') currentLimit=parseInt(e.target.value);
        else filters[id.replace('Filter','')] = e.target.value;
        currentPage=1; loadData();
    });
});

loadData();
</script>