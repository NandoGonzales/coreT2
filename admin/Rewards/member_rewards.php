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
                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#rewardsGuideModal">
                    <i class="bi bi-book me-1"></i>Rewards Guide
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


<!-- ══════════════════════════════════════════════════════ -->
<!-- Rewards Guidebook Modal                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="rewardsGuideModal" tabindex="-1" aria-labelledby="rewardsGuideLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="border-radius:1rem;border:none;">

            <!-- Header -->
            <div class="modal-header text-white fw-bold" style="background:linear-gradient(135deg,#0f172a,#1e40af);border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title"><i class="bi bi-book me-2"></i>&#x1F3C6; Member Rewards — Official Guide</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs px-4 pt-3 pb-0 bg-light" id="guideTabs" role="tablist" style="border-bottom:2px solid #e5e7eb;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">&#x1F4CB; Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-tiers" type="button">&#x1F3C5; Tiers & Benefits</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-streaks" type="button">&#x1F525; Streaks</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-penalties" type="button">&#x26A0;&#xFE0F; Penalties</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-faq" type="button">&#x2753; FAQ</button>
                    </li>
                </ul>

                <div class="tab-content p-4">

                    <!-- ── Tab 1: Overview ───────────────────────────── -->
                    <div class="tab-pane fade show active" id="tab-overview">
                        <h6 class="fw-bold text-primary mb-3">&#x1F4E2; What is the Member Rewards Program?</h6>
                        <p class="text-muted small mb-3">The Member Rewards Program recognizes members who consistently pay on time, complete their loans, and maintain good financial standing. Earn points, climb tiers, and unlock exclusive benefits.</p>

                        <h6 class="fw-bold mb-2">How to Earn Points</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Action</th>
                                        <th class="text-center">Points</th>
                                        <th>When Awarded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-success me-1">+10</span> On-Time Payment</td>
                                        <td class="text-center fw-bold text-success">+10 pts</td>
                                        <td class="small text-muted">Payment made on or before due date</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><span class="badge bg-primary me-1">+20</span> Early Payment Bonus</td>
                                        <td class="text-center fw-bold text-primary">+20 pts</td>
                                        <td class="small text-muted">Payment made before due date — stacks with on-time (+30 total)</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-warning text-dark me-1">+50</span> Full Loan Completion</td>
                                        <td class="text-center fw-bold text-warning">+50 pts</td>
                                        <td class="small text-muted">Upon completing all loan payments</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><span class="badge bg-secondary me-1">+?</span> Manual Admin Award</td>
                                        <td class="text-center fw-bold text-secondary">Up to +500 pts</td>
                                        <td class="small text-muted">Discretionary award by management</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="fw-bold mb-2">Tier Overview</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                                    <div class="card-body text-center p-3">
                                        <div style="font-size:2rem;">&#x1F949;</div>
                                        <div class="fw-bold" style="color:#92400e;">Bronze</div>
                                        <div class="small text-muted">0 – 199 pts</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#f1f5f9,#e2e8f0);">
                                    <div class="card-body text-center p-3">
                                        <div style="font-size:2rem;">&#x1F948;</div>
                                        <div class="fw-bold" style="color:#475569;">Silver</div>
                                        <div class="small text-muted">200 – 499 pts</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#fffbeb,#fef08a);">
                                    <div class="card-body text-center p-3">
                                        <div style="font-size:2rem;">&#x1F947;</div>
                                        <div class="fw-bold" style="color:#854d0e;">Gold</div>
                                        <div class="small text-muted">500 – 999 pts</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);">
                                    <div class="card-body text-center p-3">
                                        <div style="font-size:2rem;">&#x1F4CD;</div>
                                        <div class="fw-bold" style="color:#5b21b6;">Platinum</div>
                                        <div class="small text-muted">1,000+ pts</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Tab 2: Tiers & Benefits ───────────────────── -->
                    <div class="tab-pane fade" id="tab-tiers">

                        <!-- Bronze -->
                        <div class="card border-0 mb-3" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:#92400e;">&#x1F949; Bronze Tier — 0 to 199 points</h6>
                                <p class="small text-muted mb-2">Starting tier for all new members.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead><tr class="table-warning"><th>Benefit</th><th>Details</th></tr></thead>
                                        <tbody>
                                            <tr><td class="small">&#x1F4CA; Rewards Tracking</td><td class="small text-muted">Points and streak tracked from first payment</td></tr>
                                            <tr><td class="small">&#x1F3C6; Leaderboard Access</td><td class="small text-muted">See your ranking among all members</td></tr>
                                            <tr><td class="small">&#x1F4DC; Payment History</td><td class="small text-muted">Full history of on-time and early payments</td></tr>
                                            <tr><td class="small">&#x1F4C8; Progress Bar</td><td class="small text-muted">Visual tracker toward Silver tier</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Silver -->
                        <div class="card border-0 mb-3" style="background:linear-gradient(135deg,#f8fafc,#e2e8f0);">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:#475569;">&#x1F948; Silver Tier — 200 to 499 points</h6>
                                <p class="small text-muted mb-2">Consistent payer — earning trust with the cooperative.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead><tr class="table-secondary"><th>Benefit</th><th>Details</th></tr></thead>
                                        <tbody>
                                            <tr><td class="small">&#x1F4B0; Late Fee Waiver</td><td class="small text-muted">One (1) late fee waiver on your next loan</td></tr>
                                            <tr><td class="small">&#x1F525; Streak Recognition</td><td class="small text-muted">On-time streak displayed on member profile</td></tr>
                                            <tr><td class="small">&#x23F3; Priority Queue</td><td class="small text-muted">Faster processing on loan applications</td></tr>
                                            <tr><td class="small">&#x1F4C8; Progress Bar</td><td class="small text-muted">Visual tracker toward Gold tier</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Gold -->
                        <div class="card border-0 mb-3" style="background:linear-gradient(135deg,#fffbeb,#fef9c3);">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:#854d0e;">&#x1F947; Gold Tier — 500 to 999 points</h6>
                                <p class="small text-muted mb-2">Trusted member with excellent payment record.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead><tr class="table-warning"><th>Benefit</th><th>Details</th></tr></thead>
                                        <tbody>
                                            <tr><td class="small">&#x1F4B3; Service Fee Discount</td><td class="small text-muted">Discounted service fee on loan releases</td></tr>
                                            <tr><td class="small">&#x2705; Late Fee Waiver</td><td class="small text-muted">Automatic late fee waiver on qualifying payments</td></tr>
                                            <tr><td class="small">&#x26A1; Priority Processing</td><td class="small text-muted">Loan applications moved to front of queue</td></tr>
                                            <tr><td class="small">&#x1F9D1; Dedicated Support</td><td class="small text-muted">Dedicated staff support for account inquiries</td></tr>
                                            <tr><td class="small">&#x1F4C8; Progress Bar</td><td class="small text-muted">Visual tracker toward Platinum tier</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Platinum -->
                        <div class="card border-0" style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:#5b21b6;">&#x1F4CD; Platinum Tier — 1,000+ points</h6>
                                <p class="small text-muted mb-2">Elite member — top of the cooperative.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead><tr style="background:#7c3aed;color:white;"><th>Benefit</th><th>Details</th></tr></thead>
                                        <tbody>
                                            <tr><td class="small">&#x1F4B9; Interest Discount</td><td class="small text-muted">Exclusive interest rate discount on new loans</td></tr>
                                            <tr><td class="small">&#x1F7E2; Service Fee Waiver</td><td class="small text-muted">Full service fee waiver on qualifying releases</td></tr>
                                            <tr><td class="small">&#x2705; Auto Late Fee Waiver</td><td class="small text-muted">Automatic — no need to request</td></tr>
                                            <tr><td class="small">&#x1F451; VIP Priority</td><td class="small text-muted">Highest priority for all transactions</td></tr>
                                            <tr><td class="small">&#x1F3C6; Exclusive Recognition</td><td class="small text-muted">Featured on leaderboard as top member</td></tr>
                                            <tr><td class="small">&#x1F4E6; All Lower Tier Benefits</td><td class="small text-muted">All Bronze, Silver, and Gold benefits included</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Tab 3: Streaks ────────────────────────────── -->
                    <div class="tab-pane fade" id="tab-streaks">
                        <h6 class="fw-bold text-success mb-3">&#x1F525; Consecutive Payment Streaks</h6>
                        <p class="small text-muted mb-3">A streak is the number of consecutive on-time payments without missing a due date. Streaks are tracked separately from total points.</p>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-success">
                                    <tr>
                                        <th>Streak Length</th>
                                        <th class="text-center">Label</th>
                                        <th>What It Means</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold">1 – 4 payments</td>
                                        <td class="text-center"><span class="badge bg-secondary">Building</span></td>
                                        <td class="small text-muted">You're getting started — keep it up!</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="fw-semibold">5 – 9 payments</td>
                                        <td class="text-center"><span class="badge bg-info text-dark">On a Roll</span></td>
                                        <td class="small text-muted">Consistent payer — great financial habit</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">10 – 24 payments</td>
                                        <td class="text-center"><span class="badge bg-primary">Reliable</span></td>
                                        <td class="small text-muted">Trusted member with excellent payment record</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="fw-semibold">25+ payments</td>
                                        <td class="text-center"><span class="badge bg-warning text-dark">&#x1F451; Elite Payer</span></td>
                                        <td class="small text-muted">Outstanding member — top of the cooperative</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card border-success border-opacity-25 h-100">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-success">&#x1F4AA; Your Best Streak</h6>
                                        <p class="small text-muted mb-0">Your all-time best streak is permanently saved on your profile even if your current streak resets. This gives you permanent recognition for your historical payment discipline.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-danger border-opacity-25 h-100">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-danger">&#x26A0;&#xFE0F; What Resets Your Streak?</h6>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li>Missing a loan payment past the due date</li>
                                            <li>A late payment recorded by your collector</li>
                                            <li>Your account becomes inactive (no active loans)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Tab 4: Penalties ──────────────────────────── -->
                    <div class="tab-pane fade" id="tab-penalties">
                        <h6 class="fw-bold text-danger mb-3">&#x26A0;&#xFE0F; Point Deductions & Penalties</h6>
                        <p class="small text-muted mb-3">Penalties are applied to members who miss payments or become inactive. Applied at most <strong>once per month</strong> per member.</p>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Reason</th>
                                        <th class="text-center">Deduction</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold">Missed 2 months of payments</td>
                                        <td class="text-center"><span class="badge bg-danger">-30 pts</span></td>
                                        <td class="small text-muted">Active loan, no payments for 2 consecutive months</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="fw-semibold">Missed 3+ months of payments</td>
                                        <td class="text-center"><span class="badge bg-danger">-50 pts</span></td>
                                        <td class="small text-muted">Active loan, no payments for 3 or more months</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold">No active loan (inactive)</td>
                                        <td class="text-center"><span class="badge bg-warning text-dark">Streak Reset</span></td>
                                        <td class="small text-muted">Consecutive streak resets to 0 — total points NOT affected</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                            <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                            <div class="small">
                                <strong>Important:</strong> Points can never go below <strong>0</strong>. Streak reset does NOT reduce your total points. Your best streak record is never affected by resets.
                            </div>
                        </div>
                    </div>

                    <!-- ── Tab 5: FAQ ─────────────────────────────────── -->
                    <div class="tab-pane fade" id="tab-faq">
                        <h6 class="fw-bold text-primary mb-3">&#x2753; Frequently Asked Questions</h6>

                        <div class="accordion accordion-flush" id="faqAccordion">

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                        How do I check my current points and tier?
                                    </button>
                                </h2>
                                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Your points and tier badge are visible on the Member Rewards page. You can also ask any staff member to look up your current standing.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                        When do I receive my points?
                                    </button>
                                </h2>
                                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Points are automatically awarded when your payment is recorded in the system. On-time and early payments are processed immediately upon recording.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                        Can I lose my tier?
                                    </button>
                                </h2>
                                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Yes. If your points drop below the threshold for your current tier due to penalties, your tier will be downgraded. For example, if you are Silver (200 pts) and lose 30 points, you will drop back to Bronze.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                        Are early payment bonuses stacked with on-time points?
                                    </button>
                                </h2>
                                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Yes! Paying early gives you +10 (on-time) + +20 (early bonus) = <strong>+30 points total</strong> for that payment.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                        What happens when I fully pay off a loan?
                                    </button>
                                </h2>
                                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">You earn +50 bonus completion points on top of your regular on-time payment points for the final payment — the largest single-payment reward available.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                        How do I redeem my rewards like fee waivers?
                                    </button>
                                </h2>
                                <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Benefits like late fee waivers and service fee discounts are applied automatically when you are eligible. You do not need to manually request them.</div>
                                </div>
                            </div>

                            <div class="accordion-item border mb-2 rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                                        Can staff manually add or adjust my points?
                                    </button>
                                </h2>
                                <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body small text-muted">Yes. Management and admin staff can manually award up to 500 points per action for special recognition such as referrals, community participation, or exceptional circumstances.</div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- end tab-content -->
            </div><!-- end modal-body -->

            <div class="modal-footer bg-light" style="border-radius:0 0 1rem 1rem;">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>Microfinance EIS — Member Rewards Program v1.0</small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
<!-- End Rewards Guidebook Modal -->

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