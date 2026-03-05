<?php
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
include(__DIR__ . '/../inc/footer.php');
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once __DIR__ . '/../inc/check_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_role = $_SESSION['userdata']['role'] ?? 'Staff';
?>

<style>
    :root {
        --green: #059669;
        --green-dark: #047857;
        --blue: #2563eb;
        --blue-light: #eff6ff;
        --red: #ef4444;
        --yellow: #f59e0b;
        --purple: #7c3aed;
    }

    /* ── Pipeline Steps ───────────────────────────────── */
    .pipeline-bar {
        display: flex;
        align-items: center;
        gap: 0;
        background: #fff;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        overflow-x: auto;
    }

    .pipeline-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .35rem;
        min-width: 90px;
        cursor: pointer;
        transition: transform .2s;
    }

    .pipeline-step:hover {
        transform: translateY(-2px);
    }

    .pipeline-step.active .step-icon {
        background: var(--green);
        color: #fff;
        box-shadow: 0 0 0 4px #d1fae5;
    }

    .pipeline-step.active .step-label {
        color: var(--green);
        font-weight: 700;
    }

    .step-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #f3f4f6;
        color: #9ca3af;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        transition: all .2s;
    }

    .step-label {
        font-size: .7rem;
        font-weight: 600;
        color: #6b7280;
        text-align: center;
    }

    .step-count {
        font-size: .65rem;
        background: #e5e7eb;
        color: #374151;
        border-radius: 999px;
        padding: .1rem .4rem;
        font-weight: 700;
    }

    .step-count.has-items {
        background: var(--green);
        color: #fff;
    }

    .pipeline-divider {
        flex: 1;
        height: 2px;
        background: #e5e7eb;
        min-width: 20px;
    }

    /* ── Cards / Table ────────────────────────────────── */
    .app-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        overflow: hidden;
    }

    .app-card .card-header-bar {
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f3f4f6;
    }

    .app-table th {
        background: #f9fafb;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b7280;
        font-weight: 700;
        padding: .75rem 1rem;
    }

    .app-table td {
        padding: .875rem 1rem;
        vertical-align: middle;
        font-size: .875rem;
    }

    .app-table tr {
        cursor: pointer;
        transition: background .15s;
    }

    .app-table tbody tr:hover {
        background: #f0fdf4;
    }

    /* ── Status Badges ────────────────────────────────── */
    .sbadge {
        display: inline-block;
        padding: .25rem .75rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .sbadge-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .sbadge-ci-progress {
        background: #dbeafe;
        color: #1e40af;
    }

    .sbadge-ci-passed {
        background: #d1fae5;
        color: #065f46;
    }

    .sbadge-ci-review {
        background: #fef9c3;
        color: #713f12;
    }

    .sbadge-ci-failed {
        background: #fee2e2;
        color: #991b1b;
    }

    .sbadge-evaluated {
        background: #ede9fe;
        color: #4c1d95;
    }

    .sbadge-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .sbadge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    /* ── Score Display ────────────────────────────────── */
    .score-ring {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        border: 4px solid;
    }

    .score-excellent {
        border-color: #059669;
        color: #059669;
        background: #ecfdf5;
    }

    .score-very-good {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }

    .score-good {
        border-color: #7c3aed;
        color: #7c3aed;
        background: #f5f3ff;
    }

    .score-fair {
        border-color: #f59e0b;
        color: #f59e0b;
        background: #fffbeb;
    }

    .score-poor {
        border-color: #ef4444;
        color: #ef4444;
        background: #fef2f2;
    }

    /* ── Modals ───────────────────────────────────────── */
    .modal-content {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
    }

    .modal-header {
        border: none;
        padding: 1.25rem 1.5rem .75rem;
    }

    .modal-footer {
        border: none;
        padding: .75rem 1.5rem 1.25rem;
    }

    /* ── Override Panel ───────────────────────────────── */
    .override-panel {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 2px solid #f59e0b;
        border-radius: .75rem;
        padding: 1.25rem;
    }

    .override-panel .override-title {
        font-weight: 700;
        color: #92400e;
        font-size: .875rem;
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: 1rem;
    }

    /* ── Score Slider ─────────────────────────────────── */
    input[type=range] {
        -webkit-appearance: none;
        width: 100%;
        height: 8px;
        border-radius: 4px;
        outline: none;
        cursor: pointer;
    }

    input[type=range]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--green);
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
    }

    /* ── CI Checklist ─────────────────────────────────── */
    .ci-check-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .75rem 1rem;
        border-radius: .5rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        margin-bottom: .5rem;
    }

    .ci-check-label {
        font-weight: 600;
        font-size: .875rem;
        color: #374151;
    }

    /* ── Action Buttons ───────────────────────────────── */
    .btn-approve {
        background: linear-gradient(135deg, #059669, #047857);
        color: #fff;
        border: none;
    }

    .btn-approve:hover {
        background: linear-gradient(135deg, #047857, #065f46);
        color: #fff;
    }

    .btn-reject {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        border: none;
    }

    .btn-reject:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff;
    }

    .btn-review {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        border: none;
    }

    .btn-review:hover {
        background: linear-gradient(135deg, #d97706, #b45309);
        color: #fff;
    }

    .page-header {
        background: linear-gradient(135deg, #059669, #047857);
        padding: 1.75rem 2rem;
        border-radius: 1rem;
        color: #fff;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 20px rgba(5, 150, 105, .3);
    }
</style>

<div class="main-wrap">
    <main class="p-4" style="padding-top: calc(4rem + 1.5rem) !important;">

        <!-- Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>Loan Application Process</h4>
                <div class="opacity-90 small mt-1">End-to-end loan processing — Application → CI → Evaluation → Decision</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn fw-bold px-4" id="syncCore1Btn"
                    style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.4);"
                    onclick="syncFromCore1()">
                    <i class="bi bi-arrow-repeat me-2"></i>Sync from Core 1
                </button>
                <button class="btn btn-light fw-bold px-4" onclick="openNewAppModal()">
                    <i class="bi bi-plus-circle-fill me-2"></i>New Application
                </button>
            </div>
        </div>

        <!-- Pipeline Bar -->
        <div class="pipeline-bar" id="pipelineBar">
            <?php
            $steps = [
                ['key' => 'Pending',       'icon' => 'bi-inbox-fill',          'label' => 'Pending',      'color' => '#f59e0b'],
                ['key' => 'CI In Progress', 'icon' => 'bi-person-badge-fill',    'label' => 'CI In Progress', 'color' => '#3b82f6'],
                ['key' => 'CI Passed',     'icon' => 'bi-check-circle-fill',    'label' => 'CI Passed',    'color' => '#10b981'],
                ['key' => 'CI For Review', 'icon' => 'bi-eye-fill',             'label' => 'CI For Review', 'color' => '#f59e0b'],
                ['key' => 'CI Failed',     'icon' => 'bi-x-circle-fill',        'label' => 'CI Failed',    'color' => '#ef4444'],
                ['key' => 'Evaluated',     'icon' => 'bi-robot',                'label' => 'Evaluated',    'color' => '#7c3aed'],
                ['key' => 'Approved',      'icon' => 'bi-trophy-fill',          'label' => 'Approved',     'color' => '#059669'],
                ['key' => 'Rejected',      'icon' => 'bi-dash-circle-fill',     'label' => 'Rejected',     'color' => '#ef4444'],
            ];
            foreach ($steps as $i => $s):
                if ($i > 0) echo '<div class="pipeline-divider"></div>';
            ?>
                <div class="pipeline-step" onclick="filterByStatus('<?= $s['key'] ?>')" data-status="<?= $s['key'] ?>">
                    <div class="step-icon"><i class="bi <?= $s['icon'] ?>"></i></div>
                    <div class="step-label"><?= $s['label'] ?></div>
                    <div class="step-count" id="cnt-<?= str_replace(' ', '_', $s['key']) ?>">0</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Bar -->
        <div class="app-card mb-4">
            <div class="card-header-bar">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="text" id="searchInput" class="form-control form-control-sm" style="width:220px;"
                        placeholder="🔍 Search name, code, type..." oninput="debounceLoad()">
                    <select id="statusFilter" class="form-select form-select-sm" style="width:180px;" onchange="loadApplications()">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="CI In Progress">CI In Progress</option>
                        <option value="CI Passed">CI Passed</option>
                        <option value="CI For Review">CI For Review</option>
                        <option value="CI Failed">CI Failed</option>
                        <option value="Evaluated">Evaluated</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadApplications()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="text-muted small" id="appCount">Loading...</div>
            </div>

            <div class="table-responsive">
                <table class="app-table table table-borderless mb-0">
                    <thead>
                        <tr>
                            <th>App Code</th>
                            <th>Member</th>
                            <th>Loan Type</th>
                            <th>Amount</th>
                            <th>AI Score</th>
                            <th>Status</th>
                            <th>CI Result</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="appTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL 1 — New Application
     ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="newAppModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#047857);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus-fill me-2"></i>New Loan Application</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Applicant (Member) <span class="text-danger">*</span></label>
                        <select id="newAppMember" class="form-select" required>
                            <option value="">— Select Member —</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Loan Type <span class="text-danger">*</span></label>
                        <select id="newAppType" class="form-select">
                            <option value="">— Select Type —</option>
                            <option>Regular Loan</option>
                            <option>Business Loan</option>
                            <option>Education Loan</option>
                            <option>Group Loan</option>
                            <option>Character Loan</option>
                            <option>Petty Cash Loan</option>
                            <option>Emergency Loan</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Principal Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" id="newAppAmount" class="form-control" placeholder="e.g. 50000" min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Interest Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" id="newAppRate" class="form-control" placeholder="e.g. 1.5" step="0.01" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Loan Term (months) <span class="text-danger">*</span></label>
                        <input type="number" id="newAppTerm" class="form-control" placeholder="e.g. 12" min="1">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Purpose / Remarks</label>
                        <textarea id="newAppPurpose" class="form-control" rows="2" placeholder="Layunin ng loan..."></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Collateral (if any)</label>
                        <input type="text" id="newAppCollateral" class="form-control" placeholder="e.g. Land title, Vehicle OR">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success px-4 fw-bold" onclick="submitApplication()">
                    <i class="bi bi-send-fill me-1"></i>Submit Application
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL 2 — Application Detail / Action Panel
     ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e3a5f,#1d4ed8);color:#fff;">
                <div>
                    <h5 class="modal-title mb-0" id="detailModalTitle">Application Detail</h5>
                    <small class="opacity-75" id="detailModalSub"></small>
                </div>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL 3 — CI Assignment
     ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="ciModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-person-badge-fill me-2"></i>Assign Credit Investigation</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ciAppId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Assign CI to:</label>
                    <select id="ciOfficer" class="form-select">
                        <option value="">— Select Officer —</option>
                    </select>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    Ang CI officer ay mag-iinvestigate ng background, capacity to pay, at character ng applicant.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary fw-bold px-4" onclick="assignCI()">
                    <i class="bi bi-check-circle-fill me-1"></i>Assign CI
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL 4 — Submit CI Feedback
     ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="ciFeedbackModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-clipboard2-check-fill me-2"></i>Credit Investigation Feedback</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ciFbAppId">

                <div class="ci-check-item">
                    <span class="ci-check-label"><i class="bi bi-search me-2 text-primary"></i>Background Check</span>
                    <select id="ciFbBackground" class="form-select form-select-sm" style="width:150px;">
                        <option value="Pending">Pending</option>
                        <option value="Passed">✅ Passed</option>
                        <option value="Failed">❌ Failed</option>
                    </select>
                </div>

                <div class="ci-check-item">
                    <span class="ci-check-label"><i class="bi bi-cash-coin me-2 text-success"></i>Capacity to Pay</span>
                    <select id="ciFbCapacity" class="form-select form-select-sm" style="width:150px;">
                        <option value="Pending">Pending</option>
                        <option value="Good">✅ Good</option>
                        <option value="Fair">⚠️ Fair</option>
                        <option value="Poor">❌ Poor</option>
                    </select>
                </div>

                <div class="ci-check-item">
                    <span class="ci-check-label"><i class="bi bi-person-check me-2 text-warning"></i>Character Check</span>
                    <select id="ciFbCharacter" class="form-select form-select-sm" style="width:150px;">
                        <option value="Pending">Pending</option>
                        <option value="Good">✅ Good</option>
                        <option value="Fair">⚠️ Fair</option>
                        <option value="Poor">❌ Poor</option>
                    </select>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">Overall CI Result <span class="text-danger">*</span></label>
                    <select id="ciFbResult" class="form-select">
                        <option value="Passed">✅ Passed — Proceed to Evaluation</option>
                        <option value="For Review">⚠️ For Review — Needs further checking</option>
                        <option value="Failed">❌ Failed — Reject application</option>
                    </select>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">CI Feedback / Remarks <span class="text-danger">*</span></label>
                    <textarea id="ciFbFeedback" class="form-control" rows="4"
                        placeholder="Ilagay ang mga natuklasan ng CI..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary fw-bold px-4" onclick="submitCI()">
                    <i class="bi bi-send-fill me-1"></i>Submit CI Feedback
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL 5 — Action Panel (Approve/Reject/Review)
     ════════════════════════════════════════════════════════ -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#065f46,#059669);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-check2-square me-2"></i>Loan Decision Panel</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="actionAppId">

                <!-- Score Summary -->
                <div id="actionScoreSummary" class="text-center mb-3 p-3 rounded-3 bg-light"></div>

                <!-- Approved Amount -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Approved Amount (₱)</label>
                    <input type="number" id="actionAmount" class="form-control" placeholder="Leave blank to use suggested amount">
                    <div class="form-text" id="actionAmountHint"></div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Decision Notes / Remarks</label>
                    <textarea id="actionNotes" class="form-control" rows="3"
                        placeholder="Dahilan ng desisyon..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3">
                    <button class="btn btn-approve flex-fill py-3 fw-bold rounded-3 fs-5"
                        onclick="takeAction('Approved')">
                        <i class="bi bi-check-circle-fill me-2"></i>✅ APPROVE
                    </button>
                    <button class="btn btn-review flex-fill py-3 fw-bold rounded-3"
                        onclick="takeAction('Pending')">
                        <i class="bi bi-hourglass-split me-2"></i>⏳ For Review
                    </button>
                    <button class="btn btn-reject flex-fill py-3 fw-bold rounded-3"
                        onclick="takeAction('Rejected')">
                        <i class="bi bi-x-circle-fill me-2"></i>❌ REJECT
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ACTION_URL = '/admin/Loan-Portfolio-Risk-Management/loan_process_action.php';
    let allApps = [];
    let debounceTimer;
    let currentAppId = null;

    // ── Sync from Core 1 ─────────────────────────────────
    async function syncFromCore1() {
        const btn = document.getElementById('syncCore1Btn');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Syncing...';

        const result = await Swal.fire({
            title: 'Sync from Core 1?',
            html: `<p class="text-muted small mb-0">Kukuha ng <strong>Pending</strong> loan applications mula sa Core 1 API.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sync',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
        });

        if (!result.isConfirmed) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Sync from Core 1';
            return;
        }

        const data = await apiFetch(ACTION_URL, {
            action: 'sync_core1'
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Sync from Core 1';

        if (data.success) {
            Swal.fire({
                icon: data.synced > 0 ? 'success' : 'info',
                title: data.synced > 0 ? `${data.synced} Application${data.synced > 1 ? 's' : ''} Synced!` : 'No New Applications',
                text: data.message,
                confirmButtonColor: '#059669'
            }).then(() => loadApplications());
        } else {
            showErr(data.error || 'Sync failed. Check Core 1 connection.');
        }
    }

    // ── On Load ──────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        loadApplications();
        loadMembersForDropdown();
        loadUsersForCI();
    });

    // ── Load Applications ─────────────────────────────────
    async function loadApplications() {
        const status = document.getElementById('statusFilter').value;
        const search = document.getElementById('searchInput').value;
        const url = `${ACTION_URL}?action=get_applications&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;

        document.getElementById('appTableBody').innerHTML =
            `<tr><td colspan="9" class="text-center py-4 text-muted">
            <div class="spinner-border spinner-border-sm me-2"></div>Loading...
         </td></tr>`;

        const data = await apiFetch(url);
        if (!data.success) return;

        allApps = data.data;

        // Update pipeline counts
        const counts = data.counts || {};
        document.querySelectorAll('.pipeline-step').forEach(el => {
            const st = el.dataset.status;
            const cnt = counts[st] || 0;
            const badge = document.getElementById('cnt-' + st.replace(/ /g, '_'));
            if (badge) {
                badge.textContent = cnt;
                badge.className = 'step-count' + (cnt > 0 ? ' has-items' : '');
            }
        });

        const total = allApps.length;
        document.getElementById('appCount').textContent = `Showing ${total} application${total !== 1 ? 's' : ''}`;

        if (!total) {
            document.getElementById('appTableBody').innerHTML =
                `<tr><td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                No applications found.
             </td></tr>`;
            return;
        }

        document.getElementById('appTableBody').innerHTML = allApps.map(a => {
            const score = a.final_score ?? a.ai_credit_score;
            const scoreBadge = score != null ?
                `<span class="fw-bold" style="color:${scoreColor(score)}">${score}</span>` :
                '<span class="text-muted small">—</span>';

            const ci = a.ci_result ?
                `<span class="sbadge ${ciBadgeClass(a.ci_result)}">${a.ci_result}</span>` :
                '<span class="text-muted small">—</span>';

            return `
        <tr onclick="openDetail(${a.app_id})">
            <td><code class="text-primary fw-bold">${esc(a.app_code)}</code></td>
            <td>
                <div class="fw-semibold">${esc(a.full_name)}</div>
                <div class="text-muted" style="font-size:.75rem;">${esc(a.member_code||'')}</div>
            </td>
            <td>${esc(a.loan_type||'—')}</td>
            <td class="fw-semibold">₱${Number(a.principal_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
            <td class="text-center">${scoreBadge}</td>
            <td><span class="sbadge ${statusBadgeClass(a.status)}">${esc(a.status)}</span></td>
            <td>${ci}</td>
            <td class="text-muted small">${formatDate(a.created_at)}</td>
            <td onclick="event.stopPropagation()">
                ${quickActions(a)}
            </td>
        </tr>`;
        }).join('');
    }

    function quickActions(a) {
        const btns = [];
        if (a.status === 'Pending')
            btns.push(`<button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="openCIModal(${a.app_id})">
            <i class="bi bi-person-badge-fill"></i> Assign CI</button>`);
        if (a.status === 'CI In Progress')
            btns.push(`<button class="btn btn-xs btn-outline-info py-0 px-2" onclick="openCIFeedback(${a.app_id})">
            <i class="bi bi-clipboard2-check"></i> CI Feedback</button>`);
        if (['CI Passed', 'CI For Review', 'Evaluated'].includes(a.status))
            btns.push(`<button class="btn btn-xs btn-outline-success py-0 px-2" onclick="openDetail(${a.app_id})">
            <i class="bi bi-play-circle-fill"></i> Process</button>`);
        if (!btns.length)
            btns.push(`<button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="openDetail(${a.app_id})">
            <i class="bi bi-eye-fill"></i> View</button>`);
        return btns.join(' ');
    }

    // ── Open Detail Modal ─────────────────────────────────
    async function openDetail(app_id) {
        currentAppId = app_id;
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        document.getElementById('detailModalBody').innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        modal.show();

        const data = await apiFetch(`${ACTION_URL}?action=get_detail&app_id=${app_id}`);
        if (!data.success) {
            showErr('Failed to load detail');
            return;
        }

        const a = data.app;
        const ci = data.ci;

        document.getElementById('detailModalTitle').textContent = `Application: ${a.app_code}`;
        document.getElementById('detailModalSub').textContent = `${a.full_name} | ${a.loan_type} | ₱${Number(a.principal_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}`;

        const score = a.final_score ?? a.ai_credit_score;
        const scoreHtml = score != null ? `
        <div class="score-ring ${scoreRingClass(score)} mx-auto">
            <div style="font-size:1.4rem;">${score}</div>
            <div style="font-size:.6rem;">/ 100</div>
        </div>
        <div class="mt-2 fw-bold" style="color:${scoreColor(score)};">${a.final_risk_category || a.ai_risk_category || '—'}</div>
        ${a.manual_score ? `<div class="small text-warning mt-1"><i class="bi bi-pencil-fill me-1"></i>Manually overridden</div>` : ''}
    ` : `<div class="text-muted">Not yet evaluated</div>`;

        const hasOverride = !!a.manual_score;
        const canEval = ['CI Passed', 'CI For Review', 'Evaluated'].includes(a.status);
        const canDecide = a.status === 'Evaluated';

        document.getElementById('detailModalBody').innerHTML = `
    <div class="row g-3">
        <!-- LEFT: Application Info -->
        <div class="col-lg-7">
            <!-- Member Info -->
            <div class="p-3 bg-light rounded-3 mb-3">
                <div class="fw-bold text-muted small text-uppercase mb-2">👤 Applicant Info</div>
                <div class="row g-2 small">
                    <div class="col-6"><strong>Name:</strong> ${esc(a.full_name)}</div>
                    <div class="col-6"><strong>Member Code:</strong> ${esc(a.member_code||'—')}</div>
                    <div class="col-6"><strong>Contact:</strong> ${esc(a.contact_no||'—')}</div>
                    <div class="col-12"><strong>Address:</strong> ${esc(a.address||'—')}</div>
                </div>
            </div>

            <!-- Loan Details -->
            <div class="p-3 bg-light rounded-3 mb-3">
                <div class="fw-bold text-muted small text-uppercase mb-2">📋 Loan Details</div>
                <div class="row g-2 small">
                    <div class="col-6"><strong>App Code:</strong> <code>${esc(a.app_code)}</code></div>
                    <div class="col-6"><strong>Status:</strong> <span class="sbadge ${statusBadgeClass(a.status)}">${esc(a.status)}</span></div>
                    <div class="col-6"><strong>Loan Type:</strong> ${esc(a.loan_type||'—')}</div>
                    <div class="col-6"><strong>Amount:</strong> <span class="fw-bold text-success">₱${Number(a.principal_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</span></div>
                    <div class="col-6"><strong>Interest Rate:</strong> ${a.interest_rate||0}% / month</div>
                    <div class="col-6"><strong>Term:</strong> ${a.loan_term||0} months</div>
                    <div class="col-6"><strong>Purpose:</strong> ${esc(a.purpose||'—')}</div>
                    <div class="col-6"><strong>Collateral:</strong> ${esc(a.collateral||'—')}</div>
                    <div class="col-12"><strong>Suggested Amount:</strong>
                        ${a.suggested_amount ? `<span class="fw-bold text-primary">₱${Number(a.suggested_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>` : '—'}
                    </div>
                </div>
            </div>

            <!-- CI Results -->
            ${ci ? `
            <div class="p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                <div class="fw-bold text-muted small text-uppercase mb-2">🔍 Credit Investigation</div>
                <div class="row g-2 small">
                    <div class="col-6"><strong>Officer:</strong> ${esc(ci.assigned_to_name||'—')}</div>
                    <div class="col-6"><strong>Result:</strong>
                        <span class="sbadge ${ciBadgeClass(ci.result)}">${esc(ci.result||'Pending')}</span>
                    </div>
                    <div class="col-4"><strong>Background:</strong> ${esc(ci.background_check)}</div>
                    <div class="col-4"><strong>Capacity:</strong> ${esc(ci.capacity_to_pay)}</div>
                    <div class="col-4"><strong>Character:</strong> ${esc(ci.character_check)}</div>
                    ${ci.ci_feedback ? `<div class="col-12"><strong>Feedback:</strong> <em>${esc(ci.ci_feedback)}</em></div>` : ''}
                </div>
            </div>` : `
            <div class="p-3 bg-light rounded-3 mb-3 text-muted small">
                <i class="bi bi-hourglass me-1"></i>CI not yet started.
                ${a.status === 'Pending' ? `<button class="btn btn-xs btn-outline-primary ms-2" onclick="bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide(); openCIModal(${a.app_id})">Assign CI Now</button>` : ''}
            </div>`}

            <!-- Override info -->
            ${hasOverride ? `
            <div class="p-3 rounded-3 mb-3" style="background:#fffbeb;border:1px solid #fde68a;">
                <div class="fw-bold small text-warning mb-1"><i class="bi bi-pencil-fill me-1"></i>Manual Override Applied</div>
                <div class="small">
                    <strong>Override Score:</strong> ${a.manual_score} (${esc(a.manual_risk_category)})<br>
                    <strong>Reason:</strong> ${esc(a.override_reason||'—')}<br>
                    <strong>By:</strong> ${esc(a.override_by_name||'—')} at ${formatDate(a.override_at)}
                </div>
            </div>` : ''}
        </div>

        <!-- RIGHT: Score + Actions -->
        <div class="col-lg-5">
            <!-- Score Display -->
            <div class="p-4 text-center rounded-3 mb-3" style="background:#f9fafb;border:1px solid #e5e7eb;">
                <div class="fw-bold text-muted small text-uppercase mb-3">🤖 AI Credit Score</div>
                ${scoreHtml}
            </div>

            <!-- Evaluation Actions -->
            ${canEval ? `
            <div class="mb-3">
                <button class="btn btn-outline-purple w-100 mb-2 fw-semibold"
                        style="border-color:#7c3aed;color:#7c3aed;"
                        onclick="runEvaluation(${a.app_id})">
                    <i class="bi bi-robot me-1"></i>Run AI Evaluation
                </button>
            </div>

            <!-- Manual Override Panel -->
            <div class="override-panel mb-3">
                <div class="override-title">
                    <i class="bi bi-pencil-square"></i>Manual Score Override
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-semibold">Score:</small>
                        <span class="fw-bold fs-5" id="sliderValue" style="color:${scoreColor(score||50)}">${score||50}</span>
                    </div>
                    <input type="range" min="0" max="100" value="${score||50}" id="overrideSlider"
                           oninput="updateSlider(this.value)">
                    <div class="d-flex justify-content-between text-muted" style="font-size:.65rem;">
                        <span>0 — Poor</span><span>55 — Fair</span><span>75 — Very Good</span><span>100 — Excellent</span>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Reason for Override <span class="text-danger">*</span></label>
                    <textarea id="overrideReason" class="form-control form-control-sm" rows="2"
                              placeholder="Bakit kailangan i-override ang AI score?"></textarea>
                </div>
                <button class="btn btn-warning btn-sm fw-bold w-100" onclick="applyOverride(${a.app_id})">
                    <i class="bi bi-pencil-fill me-1"></i>Apply Override
                </button>
            </div>` : ''}

            <!-- Decision Panel -->
            ${canDecide ? `
            <div class="p-3 rounded-3" style="background:#f0fdf4;border:2px solid #86efac;">
                <div class="fw-bold text-success small text-uppercase mb-3">⚡ Ready for Decision</div>
                <div class="d-grid gap-2">
                    <button class="btn btn-approve fw-bold" onclick="openActionModal(${a.app_id}, ${score||0}, ${a.suggested_amount||0})">
                        <i class="bi bi-play-circle-fill me-1"></i>Open Decision Panel
                    </button>
                </div>
            </div>` : ''}

            ${a.status === 'Approved' ? `
            <div class="p-3 rounded-3 text-center" style="background:#d1fae5;border:2px solid #6ee7b7;">
                <i class="bi bi-trophy-fill text-success fs-2"></i>
                <div class="fw-bold text-success mt-1">Loan Approved!</div>
                <div class="small text-muted mt-1 mb-2">Naka-queue na sa Disbursement Tracker</div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    ${a.loan_id ? `<a href="/admin/Loan-Portfolio-Risk-Management/index.php" class="btn btn-sm btn-success">
                        <i class="bi bi-wallet2 me-1"></i>View Loan #${a.loan_id}</a>` : ''}
                    <a href="/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php"
                       class="btn btn-sm btn-outline-success">
                        <i class="bi bi-send me-1"></i>Go to Disbursement</a>
                </div>
            </div>` : ''}

            ${a.status === 'Rejected' ? `
            <div class="p-3 rounded-3 text-center" style="background:#fee2e2;border:2px solid #fca5a5;">
                <i class="bi bi-x-circle-fill text-danger fs-2"></i>
                <div class="fw-bold text-danger mt-1">Application Rejected</div>
                <div class="small text-muted mt-1">${esc(a.action_notes||'—')}</div>
            </div>` : ''}
        </div>
    </div>`;
    }

    // ── Run AI Evaluation ─────────────────────────────────
    async function runEvaluation(app_id) {
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div>Running AI...';

        const data = await apiFetch(ACTION_URL, {
            action: 'run_evaluation',
            app_id
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-robot me-1"></i>Run AI Evaluation';

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'AI Evaluation Complete!',
                html: `Score: <strong>${data.ai_score}</strong> — <em>${data.ai_risk}</em><br>
                   Suggested: ₱${Number(data.suggested_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}`,
                confirmButtonColor: '#059669'
            }).then(() => {
                openDetail(app_id);
                loadApplications();
            });
        } else showErr(data.error);
    }

    // ── Override Slider ───────────────────────────────────
    function updateSlider(val) {
        const el = document.getElementById('sliderValue');
        if (el) {
            el.textContent = val;
            el.style.color = scoreColor(parseInt(val));
        }
        const slider = document.getElementById('overrideSlider');
        if (slider) {
            const pct = (val / 100) * 100;
            slider.style.background = `linear-gradient(to right, ${scoreColor(parseInt(val))} ${pct}%, #e5e7eb ${pct}%)`;
        }
    }

    async function applyOverride(app_id) {
        const score = document.getElementById('overrideSlider')?.value;
        const reason = document.getElementById('overrideReason')?.value?.trim();

        if (!reason) {
            showErr('Lagyan ng reason ang override.');
            return;
        }

        const result = await Swal.fire({
            title: 'Apply Manual Override?',
            html: `Override AI score to <strong>${score}</strong> (${scoreToRisk(parseInt(score))})?<br>
               <em class="text-muted small">${reason}</em>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Override',
            confirmButtonColor: '#f59e0b'
        });
        if (!result.isConfirmed) return;

        const data = await apiFetch(ACTION_URL, {
            action: 'manual_override',
            app_id,
            manual_score: score,
            override_reason: reason
        });
        if (data.success) {
            Swal.fire({
                    icon: 'success',
                    title: 'Override Applied!',
                    text: data.message,
                    confirmButtonColor: '#059669'
                })
                .then(() => {
                    openDetail(app_id);
                    loadApplications();
                });
        } else showErr(data.error);
    }

    // ── CI Modals ─────────────────────────────────────────
    function openCIModal(app_id) {
        document.getElementById('ciAppId').value = app_id;
        new bootstrap.Modal(document.getElementById('ciModal')).show();
    }

    async function assignCI() {
        const app_id = document.getElementById('ciAppId').value;
        const assigned_to = document.getElementById('ciOfficer').value;
        const data = await apiFetch(ACTION_URL, {
            action: 'assign_ci',
            app_id,
            assigned_to
        });
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('ciModal')).hide();
            Swal.fire({
                    icon: 'success',
                    title: 'CI Assigned!',
                    text: data.message,
                    confirmButtonColor: '#059669'
                })
                .then(() => loadApplications());
        } else showErr(data.error);
    }

    function openCIFeedback(app_id) {
        document.getElementById('ciFbAppId').value = app_id;
        new bootstrap.Modal(document.getElementById('ciFeedbackModal')).show();
    }

    async function submitCI() {
        const app_id = document.getElementById('ciFbAppId').value;
        const background = document.getElementById('ciFbBackground').value;
        const capacity = document.getElementById('ciFbCapacity').value;
        const character = document.getElementById('ciFbCharacter').value;
        const result = document.getElementById('ciFbResult').value;
        const ci_feedback = document.getElementById('ciFbFeedback').value.trim();

        if (!ci_feedback) {
            showErr('Lagyan ng feedback.');
            return;
        }

        const data = await apiFetch(ACTION_URL, {
            action: 'submit_ci',
            app_id,
            background_check: background,
            capacity_to_pay: capacity,
            character_check: character,
            result,
            ci_feedback
        });

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('ciFeedbackModal')).hide();
            Swal.fire({
                    icon: 'success',
                    title: 'CI Feedback Submitted!',
                    text: data.message,
                    confirmButtonColor: '#059669'
                })
                .then(() => loadApplications());
        } else showErr(data.error);
    }

    // ── Action Modal ──────────────────────────────────────
    function openActionModal(app_id, score, suggested) {
        document.getElementById('actionAppId').value = app_id;
        document.getElementById('actionAmount').value = suggested || '';
        document.getElementById('actionAmountHint').textContent =
            suggested ? `Suggested amount: ₱${Number(suggested).toLocaleString('en-PH',{minimumFractionDigits:2})}` : '';
        document.getElementById('actionScoreSummary').innerHTML =
            `<div class="score-ring ${scoreRingClass(score)} mx-auto mb-2" style="width:60px;height:60px;">
            <div style="font-size:1.1rem;">${score}</div>
         </div>
         <div class="fw-bold" style="color:${scoreColor(score)}">${scoreToRisk(score)}</div>`;
        new bootstrap.Modal(document.getElementById('actionModal')).show();
    }

    async function takeAction(decision) {
        const app_id = document.getElementById('actionAppId').value;
        const amount = document.getElementById('actionAmount').value;
        const notes = document.getElementById('actionNotes').value;

        const icons = {
            Approved: '✅',
            Rejected: '❌',
            Pending: '⏳'
        };
        const result = await Swal.fire({
            title: `${icons[decision]} ${decision}?`,
            text: `Sigurado ka bang ${decision.toLowerCase()} ang application na ito?`,
            icon: decision === 'Approved' ? 'success' : decision === 'Rejected' ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonText: `Yes, ${decision}`,
            confirmButtonColor: decision === 'Approved' ? '#059669' : decision === 'Rejected' ? '#ef4444' : '#f59e0b'
        });
        if (!result.isConfirmed) return;

        const data = await apiFetch(ACTION_URL, {
            action: 'take_action',
            app_id,
            decision,
            notes,
            approved_amount: amount
        });
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('actionModal'))?.hide();
            bootstrap.Modal.getInstance(document.getElementById('detailModal'))?.hide();

            let html = `<p>${esc(data.message)}</p>`;
            if (decision === 'Approved' && data.disb_id) {
                html += `
            <div class="mt-3 p-3 rounded-3" style="background:#d1fae5;border:1px solid #6ee7b7;">
                <div class="fw-bold text-success mb-2"><i class="bi bi-send-check-fill me-1"></i>Next Step: Disbursement</div>
                <p class="small text-muted mb-2">Ang loan ay naka-queue na sa Disbursement Tracker para i-release ng Finance Team.</p>
                <a href="/admin/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php"
                   class="btn btn-success btn-sm fw-bold">
                    <i class="bi bi-arrow-right-circle-fill me-1"></i>Go to Disbursement Tracker
                </a>
            </div>`;
            }

            Swal.fire({
                icon: 'success',
                title: decision === 'Approved' ? '✅ Loan Approved!' : 'Done!',
                html: html,
                confirmButtonColor: '#059669',
                confirmButtonText: 'OK'
            }).then(() => loadApplications());
        } else showErr(data.error);
    }

    // ── New Application ───────────────────────────────────
    function openNewAppModal() {
        new bootstrap.Modal(document.getElementById('newAppModal')).show();
    }

    async function submitApplication() {
        const member_id = document.getElementById('newAppMember').value;
        const loan_type = document.getElementById('newAppType').value;
        const principal_amount = document.getElementById('newAppAmount').value;
        const interest_rate = document.getElementById('newAppRate').value;
        const loan_term = document.getElementById('newAppTerm').value;
        const purpose = document.getElementById('newAppPurpose').value;
        const collateral = document.getElementById('newAppCollateral').value;

        if (!member_id || !loan_type || !principal_amount || !loan_term) {
            showErr('Kumpletuhin ang lahat ng required fields (*)');
            return;
        }

        const data = await apiFetch(ACTION_URL, {
            action: 'submit_application',
            member_id,
            loan_type,
            principal_amount,
            interest_rate,
            loan_term,
            purpose,
            collateral
        });

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('newAppModal')).hide();
            Swal.fire({
                    icon: 'success',
                    title: 'Application Submitted!',
                    text: data.message,
                    confirmButtonColor: '#059669'
                })
                .then(() => loadApplications());
        } else showErr(data.error);
    }

    // ── Filter by pipeline step ───────────────────────────
    function filterByStatus(status) {
        document.getElementById('statusFilter').value = status;
        document.querySelectorAll('.pipeline-step').forEach(el => {
            el.classList.toggle('active', el.dataset.status === status);
        });
        loadApplications();
    }

    // ── Load dropdowns ────────────────────────────────────
    async function loadMembersForDropdown() {
        const data = await apiFetch(`${ACTION_URL}?action=get_members`);
        if (!data.success) return;
        const sel = document.getElementById('newAppMember');
        data.members.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.member_id;
            opt.textContent = `${m.full_name} (${m.member_code||'—'})`;
            sel.appendChild(opt);
        });
    }

    async function loadUsersForCI() {
        const data = await apiFetch(`${ACTION_URL}?action=get_users`);
        if (!data.success) return;
        const sel = document.getElementById('ciOfficer');
        data.users.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.user_id;
            opt.textContent = `${u.full_name} (${u.role})`;
            sel.appendChild(opt);
        });
    }

    // ── Helpers ───────────────────────────────────────────
    // ── Session Management ────────────────────────────────
    const SESSION_TIMEOUT = 120; // must match PHP SESSION_TIMEOUT (seconds)
    const SESSION_WARN_AT = 30; // show warning this many seconds before expiry
    let sessionTimer = null;
    let sessionWarningShown = false;
    let remainingSeconds = SESSION_TIMEOUT;

    function startSessionCountdown(remaining = SESSION_TIMEOUT) {
        clearInterval(sessionTimer);
        remainingSeconds = remaining;
        sessionWarningShown = false;

        sessionTimer = setInterval(() => {
            remainingSeconds--;

            // Update navbar timer if present
            const el = document.getElementById('sessionCountdownDisplay');
            if (el) el.textContent = formatCountdown(remainingSeconds);

            if (remainingSeconds <= SESSION_WARN_AT && !sessionWarningShown) {
                sessionWarningShown = true;
                showSessionWarning();
            }

            if (remainingSeconds <= 0) {
                clearInterval(sessionTimer);
                forceSessionExpired();
            }
        }, 1000);
    }

    function resetSessionTimer() {
        remainingSeconds = SESSION_TIMEOUT;
        sessionWarningShown = false;
    }

    function formatCountdown(secs) {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    }

    function showSessionWarning() {
        Swal.fire({
            icon: 'warning',
            title: 'Session Expiring Soon',
            html: `<p style="color:#856404;font-weight:600;">Mag-e-expire ang iyong session sa <strong id="swalCountdown">${remainingSeconds}s</strong>.</p>
               <p style="color:#6c757d;font-size:.9rem;">I-click ang "Stay Logged In" para manatili.</p>`,
            confirmButtonText: 'Stay Logged In',
            confirmButtonColor: '#059669',
            showCancelButton: true,
            cancelButtonText: 'Logout',
            cancelButtonColor: '#ef4444',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                const countdown = document.getElementById('swalCountdown');
                const warningTimer = setInterval(() => {
                    if (countdown) countdown.textContent = remainingSeconds + 's';
                    if (remainingSeconds <= 0) clearInterval(warningTimer);
                }, 1000);
            }
        }).then(result => {
            if (result.isConfirmed) {
                // Ping server to keep session alive — use returned remaining time
                fetch('/admin/inc/update_session_activity.php', {
                        method: 'POST'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.session_valid && data.remaining > 0) {
                            startSessionCountdown(data.remaining);
                        } else {
                            // Server says expired already
                            forceSessionExpired();
                        }
                    })
                    .catch(() => startSessionCountdown(SESSION_TIMEOUT));
            } else {
                window.location.replace('/admin/logout.php');
            }
        });
    }

    function forceSessionExpired() {
        Swal.close();
        sessionStorage.clear();
        localStorage.removeItem('sessionActive');
        Swal.fire({
            icon: 'warning',
            title: 'Session Expired',
            html: '<p style="color:#856404;font-weight:bold;">Nag-expire ang iyong session dahil sa inactivity.</p><p style="color:#6c757d;font-size:.9rem;">Mag-login ulit para magpatuloy.</p>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            allowOutsideClick: false,
            allowEscapeKey: false,
        }).then(() => {
            window.location.replace('/admin/login.php');
        });
    }

    // Reset timer on any user activity
    ['click', 'keydown', 'mousemove', 'scroll'].forEach(evt => {
        document.addEventListener(evt, () => resetSessionTimer(), {
            passive: true
        });
    });

    // Start session countdown on page load
    document.addEventListener('DOMContentLoaded', () => {
        // Get remaining seconds from PHP session_info if available
        const phpRemaining = <?php echo $_SESSION['session_info']['remaining_seconds'] ?? SESSION_TIMEOUT; ?>;
        startSessionCountdown(phpRemaining > 0 ? phpRemaining : SESSION_TIMEOUT);
    });

    // ── apiFetch with session_expired handling ────────────
    async function apiFetch(url, postData = null) {
        try {
            const opts = postData ?
                {
                    method: 'POST',
                    body: new URLSearchParams(postData)
                } :
                {
                    method: 'GET'
                };
            const res = await fetch(url, opts);
            const data = await res.json();

            // ── Handle session expired from server ──
            if (data.session_expired) {
                clearInterval(sessionTimer);
                sessionStorage.clear();
                localStorage.removeItem('sessionActive');
                await Swal.fire({
                    icon: 'warning',
                    title: 'Session Expired',
                    html: '<p style="color:#856404;font-weight:bold;">Nag-expire ang iyong session.</p><p style="color:#6c757d;font-size:.9rem;">Mag-login ulit para magpatuloy.</p>',
                    confirmButtonText: 'Log In Again',
                    confirmButtonColor: '#3085d6',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });
                window.location.replace('/admin/login.php');
                return data;
            }

            // Reset timer on successful API call
            resetSessionTimer();
            return data;

        } catch (e) {
            return {
                success: false,
                error: e.message
            };
        }
    }

    function showErr(msg) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: msg,
            confirmButtonColor: '#ef4444'
        });
    }

    function debounceLoad() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadApplications, 400);
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(d).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function scoreColor(s) {
        if (s >= 85) return '#059669';
        if (s >= 75) return '#2563eb';
        if (s >= 65) return '#7c3aed';
        if (s >= 55) return '#f59e0b';
        return '#ef4444';
    }

    function scoreRingClass(s) {
        if (s >= 85) return 'score-excellent';
        if (s >= 75) return 'score-very-good';
        if (s >= 65) return 'score-good';
        if (s >= 55) return 'score-fair';
        return 'score-poor';
    }

    function scoreToRisk(s) {
        if (s >= 85) return 'Excellent';
        if (s >= 75) return 'Very Good';
        if (s >= 65) return 'Good';
        if (s >= 55) return 'Fair';
        return 'Poor';
    }

    function statusBadgeClass(s) {
        const map = {
            'Pending': 'sbadge-pending',
            'CI In Progress': 'sbadge-ci-progress',
            'CI Passed': 'sbadge-ci-passed',
            'CI For Review': 'sbadge-ci-review',
            'CI Failed': 'sbadge-ci-failed',
            'Evaluated': 'sbadge-evaluated',
            'Approved': 'sbadge-approved',
            'Rejected': 'sbadge-rejected',
        };
        return map[s] || 'sbadge-pending';
    }

    function ciBadgeClass(r) {
        if (r === 'Passed') return 'sbadge-ci-passed';
        if (r === 'For Review') return 'sbadge-ci-review';
        if (r === 'Failed') return 'sbadge-ci-failed';
        return 'sbadge-pending';
    }

        // ── Real-Time Polling: loans ──────────────────────────
        (function() {
            let lastPollTime = new Date().toISOString().replace('T', ' ').substring(0, 19);

            function pollNewLoans() {
                fetch('/admin/inc/poll_new_records.php?module=loans&since=' + encodeURIComponent(lastPollTime), {
                    credentials: 'same-origin', cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.session_expired) { window.location.replace('/admin/login.php'); return; }
                    if (!data.count || data.count === 0) return;

                    // Update poll time
                    lastPollTime = data.polled_at;

                    // Reload table silently
                    loadApplications();

                    // Toast per record
                    data.records.forEach(r => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: '📋 New Loan Application!',
                            html: `<b>${r.member_name || 'Unknown'}</b><br><small class="text-muted">${r.loan_code || r.app_code || r.transaction_type || r.request_type || ''} ${r.amount ? '₱' + parseFloat(r.amount).toLocaleString('en-PH', {minimumFractionDigits:2}) : ''}</small>`,
                            showConfirmButton: false,
                            timer: 7000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });
                    });
                })
                .catch(() => {});
            }

            // Poll every 30 seconds
            setInterval(pollNewLoans, 30000);
        })();

</script>