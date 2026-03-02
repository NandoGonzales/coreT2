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
        --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
    }

    body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f9fafb; }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, var(--brand-primary) 0%, #047857 100%);
        padding: 2rem; border-radius: 1rem; margin-bottom: 2rem;
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
        box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
    }

    /* Summary Cards */
    .summary-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1.25rem 1.5rem; border-radius: 1rem; cursor: pointer;
        transition: all 0.25s ease; box-shadow: var(--shadow-md); color: white; user-select: none;
    }
    .summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }
    .summary-icon  { font-size: 2.25rem; opacity: 0.9; }
    .summary-count { font-size: 2rem; font-weight: 800; line-height: 1; }
    .summary-label { font-size: 0.85rem; font-weight: 600; opacity: 0.9; margin-top: 0.25rem; }
    .compliant-card    { background: linear-gradient(135deg,#059669,#047857); }
    .noncompliant-card { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .pending-card      { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .review-card       { background: linear-gradient(135deg,#3b82f6,#2563eb); }

    /* Status list modal header colors */
    .modal-header.compliant-header    { background: linear-gradient(135deg,#059669,#047857); color:white; }
    .modal-header.noncompliant-header { background: linear-gradient(135deg,#ef4444,#dc2626); color:white; }
    .modal-header.pending-header      { background: linear-gradient(135deg,#f59e0b,#d97706); color:white; }
    .modal-header.review-header       { background: linear-gradient(135deg,#3b82f6,#2563eb); color:white; }

    #modalLogsTable thead           { background: #1f2937; }
    #modalLogsTable thead th        { color: white; font-size: 0.8rem; padding: 0.75rem; border: none; }
    #modalLogsTable tbody td        { font-size: 0.82rem; }

    /* Table Card */
    .table-card {
        background: white; padding: 1.5rem; border-radius: 1rem;
        box-shadow: var(--shadow-md); border: 1px solid #e5e7eb;
    }
    .table-card .table-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 2px solid #f3f4f6;
    }
    .table-card .table-title {
        font-weight: 700; color: #111827; font-size: 1.125rem; margin: 0;
        display: flex; align-items: center; gap: 0.5rem;
    }
    #recordInfo { color: #6b7280; font-size: 0.875rem; font-weight: 500; }
    .table-wrapper { overflow-x: auto; border-radius: 0.75rem; border: 1px solid #e5e7eb; }
    .table { margin-bottom: 0; }
    .table thead { background: #1f2937 !important; }
    .table thead th {
        color: white !important; font-weight: 700; font-size: 0.875rem;
        padding: 1rem 0.75rem; border: none; text-transform: uppercase; letter-spacing: 0.025em;
    }

    /* Clickable rows */
    #logsTable tbody tr {
        transition: all 0.2s ease; border-bottom: 1px solid #f3f4f6; cursor: pointer;
    }
    #logsTable tbody tr:hover {
        background: #f0fdf4 !important;
        transform: scale(1.005);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .table tbody td { padding: 0.875rem 0.75rem; font-size: 0.875rem; color: #374151; vertical-align: middle; }
    .table .badge { padding: 0.375rem 0.75rem; font-weight: 600; font-size: 0.75rem; border-radius: 0.5rem; }

    /* Buttons */
    .btn { border-radius: 0.5rem; font-weight: 600; transition: all 0.2s ease; box-shadow: var(--shadow-sm); }
    .btn:hover   { transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn:active  { transform: translateY(0); }
    .btn-sm      { padding: 0.5rem 1rem; font-size: 0.875rem; }
    .btn-primary { background: linear-gradient(135deg,var(--brand-primary),#047857); border: none; }
    .btn-danger  { background: linear-gradient(135deg,#ef4444,#dc2626); border: none; }
    .btn-success { background: linear-gradient(135deg,#10b981,#059669); border: none; }
    .btn-outline-light { border: 2px solid rgba(255,255,255,0.5); color: white; }
    .btn-outline-light:hover { background: rgba(255,255,255,0.2); border-color: white; color: white; }

    /* Pagination */
    .pagination-wrapper {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #f3f4f6;
    }
    .pagination { margin-bottom: 0; }
    .pagination .page-link {
        border: 1.5px solid #e5e7eb; color: var(--brand-primary); margin: 0 0.125rem;
        border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem;
        padding: 0.5rem 0.75rem; transition: all 0.2s;
    }
    .pagination .page-link:hover           { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }
    .pagination .page-item.active .page-link  { background: var(--brand-primary); border-color: var(--brand-primary); color: white; }
    .pagination .page-item.disabled .page-link{ background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }

    /* ── Compliance Detail Modal styles ── */
    .cdm-info-label {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: #6b7280; margin-bottom: 0.2rem;
    }
    .cdm-info-value  { font-weight: 600; color: #111827; font-size: 0.875rem; }
    .cdm-section-title {
        font-weight: 700; font-size: 0.95rem; color: #1f2937;
        margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;
    }
    .rule-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.875rem 1rem;
        margin-bottom: 0.75rem;
    }
    .rule-card-gov {
        background: #eff6ff;
        border-left: 4px solid #2563eb;
    }
    .rule-card-company {
        background: #f0fdf4;
        border-left: 4px solid #059669;
    }
    .rule-code {
        display: inline-block; font-size: 0.68rem; font-weight: 700;
        padding: 0.1rem 0.45rem; border-radius: 0.25rem;
        text-transform: uppercase; letter-spacing: 0.07em;
    }
    .rule-code-gov     { color: #1d4ed8; background: #dbeafe; }
    .rule-code-company { color: #059669; background: #d1fae5; }
    .rule-regulator { font-size: 0.72rem; color: #6b7280; font-style: italic; }
    .rule-title  { font-weight: 700; color: #111827; font-size: 0.875rem; margin-bottom: 0.25rem; margin-top: 0.25rem; }
    .rule-desc   { color: #4b5563; font-size: 0.8rem; line-height: 1.6; margin-bottom: 0.25rem; }
    .rule-source { font-size: 0.73rem; color: #6b7280; font-style: italic; }
    .rules-section-header {
        font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        letter-spacing: 0.06em; padding: 0.5rem 0.75rem; border-radius: 0.375rem;
        margin-bottom: 0.6rem; margin-top: 0.5rem;
    }
    .rules-gov-header     { background: #dbeafe; color: #1d4ed8; }
    .rules-company-header { background: #d1fae5; color: #065f46; margin-top: 1rem; }

    .alert-compliant    { background:#d1fae5; border-color:#a7f3d0; color:#065f46; }
    .alert-noncompliant { background:#fee2e2; border-color:#fca5a5; color:#991b1b; }
    .alert-pending      { background:#fef3c7; border-color:#fde68a; color:#92400e; }
    .alert-underreview  { background:#dbeafe; border-color:#93c5fd; color:#1e40af; }

    /* ── Rules Guide Modal ── */
    .rg-tab-bar {
        display: flex; border-bottom: 2px solid #e5e7eb;
        background: #f9fafb; padding: 0 1rem; gap: 0.25rem;
    }
    .rg-tab {
        padding: 0.75rem 1.25rem; border: none; background: none;
        font-weight: 600; font-size: 0.875rem; color: #6b7280;
        border-bottom: 3px solid transparent; margin-bottom: -2px;
        cursor: pointer; transition: all 0.2s;
    }
    .rg-tab:hover  { color: #1d4ed8; }
    .rg-tab.active { color: #1d4ed8; border-bottom-color: #1d4ed8; }

    .rg-panel { animation: fadeIn 0.2s ease; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

    .rg-status-banner {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.25rem; border-radius: 0.75rem;
        margin-bottom: 1.25rem; color: white;
    }
    .rg-banner-compliant    { background: linear-gradient(135deg,#059669,#047857); }
    .rg-banner-noncompliant { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .rg-banner-underreview  { background: linear-gradient(135deg,#3b82f6,#2563eb); }
    .rg-banner-pending      { background: linear-gradient(135deg,#f59e0b,#d97706); }

    .rg-keywords-box {
        border-radius: 0.5rem; padding: 0.875rem 1rem;
        margin-bottom: 1.25rem; border: 1px solid #e5e7eb;
    }
    .rg-kw-compliant    { background:#f0fdf4; border-color:#a7f3d0; }
    .rg-kw-noncompliant { background:#fff1f2; border-color:#fecaca; }
    .rg-kw-underreview  { background:#eff6ff; border-color:#bfdbfe; }
    .rg-kw-pending      { background:#fffbeb; border-color:#fde68a; }

    .rg-kw-title {
        font-weight: 700; font-size: 0.78rem; text-transform: uppercase;
        letter-spacing: 0.05em; color: #374151; margin-bottom: 0.6rem;
    }
    .rg-kw-list { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .rg-kw {
        display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px;
        font-size: 0.75rem; font-weight: 600;
        background: white; color: #374151; border: 1px solid #d1d5db;
    }
    .rg-kw-danger  { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
    .rg-kw-review  { background:#dbeafe; color:#1e40af; border-color:#93c5fd; }
    .rg-kw-pending { background:#fef3c7; color:#92400e; border-color:#fcd34d; }

    .rg-section-title {
        font-weight: 700; font-size: 0.85rem; color: #1f2937;
        margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;
    }
    .rg-rule-card {
        border-radius: 0.5rem; padding: 0.875rem 1rem;
        margin-bottom: 0.6rem; border: 1px solid #e5e7eb;
    }
    .rg-rule-gov     { background:#eff6ff; border-left: 4px solid #2563eb; }
    .rg-rule-company { background:#f0fdf4; border-left: 4px solid #059669; }
    .rg-code {
        display: inline-block; font-size: 0.68rem; font-weight: 700;
        padding: 0.1rem 0.5rem; border-radius: 0.25rem;
        text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.3rem;
    }
    .rg-code-gov     { background:#dbeafe; color:#1d4ed8; }
    .rg-code-company { background:#d1fae5; color:#065f46; }
    .rg-rule-title   { font-weight: 700; color:#111827; font-size:0.875rem; margin-bottom:0.2rem; }
    .rg-agency       { font-weight: 400; color:#6b7280; font-size:0.8rem; }
    .rg-rule-desc    { color:#4b5563; font-size:0.8rem; line-height:1.6; }

    @media (max-width: 576px) {
        .rg-tab { padding: 0.6rem 0.75rem; font-size: 0.8rem; }
    }
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-shield-check me-2"></i>Compliance & Audit Trail Logs</h4>
                        <p class="subtitle mb-0">Monitor system compliance and track all audit activities</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button id="exportCsvBtn" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </button>
                        <button id="exportPdfBtn" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <button id="rulesGuideBtn" class="btn btn-sm btn-outline-light"
                                onclick="document.getElementById('rulesGuideModal') && new bootstrap.Modal(document.getElementById('rulesGuideModal')).show()">
                            <i class="bi bi-journal-bookmark-fill"></i> Rules Guide
                        </button>
                        <button id="reloadBtn" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Reload
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
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

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
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

            <!-- ════════════════════════════════════
                 Modal 1: Status List (card click)
                 ════════════════════════════════════ -->
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
                                            <th>#</th><th>User</th><th>Action</th>
                                            <th>Module</th><th>Description</th>
                                            <th>Date/Time</th><th>IP Address</th>
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

            <!-- ════════════════════════════════════
                 Modal 2: Compliance Detail (row click)
                 ════════════════════════════════════ -->
            <div class="modal fade" id="complianceDetailModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">

                        <div class="modal-header" id="cdm-header"
                             style="background:linear-gradient(135deg,#059669,#047857);color:white;">
                            <div>
                                <h5 class="modal-title mb-0">
                                    <i class="bi bi-shield-check me-2"></i>
                                    <span id="cdm-title">Compliance Details</span>
                                </h5>
                                <small id="cdm-subtitle" class="opacity-75"></small>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body p-0">

                            <!-- Loading -->
                            <div id="cdm-loading" class="text-center py-5">
                                <div class="spinner-border text-success"></div>
                                <p class="mt-2 text-muted small">Loading compliance details...</p>
                            </div>

                            <!-- Content -->
                            <div id="cdm-content" style="display:none;">

                                <!-- Record info -->
                                <div class="px-4 pt-4 pb-3 border-bottom bg-light">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-person-circle me-1"></i>User</div>
                                            <div class="cdm-info-value" id="cdm-user">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-clock me-1"></i>Date / Time</div>
                                            <div class="cdm-info-value" id="cdm-datetime">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-lightning me-1"></i>Action</div>
                                            <div class="cdm-info-value" id="cdm-action">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-grid me-1"></i>Module</div>
                                            <div class="cdm-info-value" id="cdm-module">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-card-text me-1"></i>Description</div>
                                            <div class="cdm-info-value" id="cdm-description">—</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="cdm-info-label"><i class="bi bi-router me-1"></i>IP Address</div>
                                            <div class="cdm-info-value font-monospace" id="cdm-ip">—</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status + Category -->
                                <div class="px-4 py-3 border-bottom">
                                    <div class="d-flex flex-wrap gap-3 align-items-center">
                                        <div>
                                            <div class="cdm-info-label mb-1">Compliance Status</div>
                                            <span id="cdm-status-badge" class="badge fs-6 px-3 py-2">—</span>
                                        </div>
                                        <div>
                                            <div class="cdm-info-label mb-1">Category</div>
                                            <span class="badge bg-secondary fs-6 px-3 py-2" id="cdm-category">—</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Why this status -->
                                <div class="px-4 py-3 border-bottom">
                                    <div class="cdm-section-title">
                                        <i class="bi bi-info-circle-fill text-primary"></i> Why this status?
                                    </div>
                                    <p class="mb-0 text-secondary" id="cdm-reason" style="line-height:1.7;font-size:0.875rem;"></p>
                                </div>

                                <!-- Recommended Action -->
                                <div class="px-4 py-3 border-bottom">
                                    <div class="cdm-section-title">
                                        <i class="bi bi-check2-square text-success"></i> Recommended Action
                                    </div>
                                    <div class="alert py-2 px-3 mb-0" id="cdm-recommended-alert">
                                        <span id="cdm-recommended" style="font-size:0.875rem;"></span>
                                    </div>
                                </div>

                                <!-- Applicable Rules -->
                                <div class="px-4 py-3">
                                    <div class="cdm-section-title">
                                        <i class="bi bi-journal-text text-warning"></i> Applicable Rules & Regulations
                                    </div>
                                    <div id="cdm-rules-list"></div>
                                </div>

                            </div><!-- /#cdm-content -->
                        </div><!-- /.modal-body -->

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ════ End Compliance Detail Modal ════ -->

            <!-- Logs Table -->
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
                                <th>#</th><th>User</th><th>Action</th><th>Module</th>
                                <th>Description</th><th>Status</th><th>Date/Time</th><th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="text-muted small"><span id="recordInfoBottom"></span></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="logsPagination"></ul></nav>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 Rules Guide Modal
                 ════════════════════════════════════════════════ -->
            <div class="modal fade" id="rulesGuideModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">

                        <div class="modal-header" style="background:linear-gradient(135deg,#1e3a5f,#1d4ed8);color:white;">
                            <div>
                                <h5 class="modal-title mb-0">
                                    <i class="bi bi-journal-bookmark-fill me-2"></i>Compliance Rules Guide
                                </h5>
                                <small class="opacity-75">Mga rules at policies para sa bawat compliance status</small>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body p-0">

                            <!-- Tabs -->
                            <div class="rg-tab-bar">
                                <button class="rg-tab active" onclick="rgShowTab('compliant', this)">
                                    <i class="bi bi-check-circle-fill me-1" style="color:#10b981;"></i> Compliant
                                </button>
                                <button class="rg-tab" onclick="rgShowTab('noncompliant', this)">
                                    <i class="bi bi-x-circle-fill me-1" style="color:#ef4444;"></i> Non-Compliant
                                </button>
                                <button class="rg-tab" onclick="rgShowTab('underreview', this)">
                                    <i class="bi bi-eye-fill me-1" style="color:#3b82f6;"></i> Under Review
                                </button>
                                <button class="rg-tab" onclick="rgShowTab('pending', this)">
                                    <i class="bi bi-hourglass-split me-1" style="color:#f59e0b;"></i> Pending
                                </button>
                            </div>

                            <!-- ── COMPLIANT ── -->
                            <div id="rg-compliant" class="rg-panel px-4 py-3">
                                <div class="rg-status-banner rg-banner-compliant">
                                    <i class="bi bi-check-circle-fill fs-4"></i>
                                    <div>
                                        <div class="fw-bold fs-6">✅ COMPLIANT</div>
                                        <div class="small opacity-90">Ang action ay maayos at walang violation. Naaayon sa lahat ng rules at policies.</div>
                                    </div>
                                </div>

                                <div class="rg-keywords-box rg-kw-compliant">
                                    <div class="rg-kw-title"><i class="bi bi-lightning-fill me-1"></i>Mga Action na nagiging COMPLIANT</div>
                                    <div class="rg-kw-list">
                                        <span class="rg-kw">Successful Login</span><span class="rg-kw">OTP Verified</span>
                                        <span class="rg-kw">Logout</span><span class="rg-kw">Create Record</span>
                                        <span class="rg-kw">View Report</span><span class="rg-kw">Export Data</span>
                                        <span class="rg-kw">Update Record</span><span class="rg-kw">Add Member</span>
                                        <span class="rg-kw">Approve Loan</span><span class="rg-kw">at iba pang normal na actions</span>
                                    </div>
                                </div>

                                <div class="rg-section-title"><i class="bi bi-bank me-2 text-primary"></i>Government Rules & Regulations</div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-10173</span>
                                    <div class="rg-rule-title">Data Privacy Act of 2012 <span class="rg-agency">— National Privacy Commission (NPC)</span></div>
                                    <div class="rg-rule-desc">Ang personal na impormasyon ng borrower at users ay dapat protektahan. Ang nag-access ay may tamang authorization at ginamit lang para sa tamang layunin.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">BSP-982</span>
                                    <div class="rg-rule-title">BSP IT Risk Management Guidelines <span class="rg-agency">— Bangko Sentral ng Pilipinas (BSP)</span></div>
                                    <div class="rg-rule-desc">Ang sistema ay gumagamit ng tamang authentication, access controls, at audit logging ayon sa BSP technology risk management requirements.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-9520</span>
                                    <div class="rg-rule-title">Philippine Cooperative Code <span class="rg-agency">— Cooperative Development Authority (CDA)</span></div>
                                    <div class="rg-rule-desc">Ang action ay naaayon sa governance, transparency, at operational requirements ng cooperative ayon sa RA 9520.</div>
                                </div>

                                <div class="rg-section-title mt-3"><i class="bi bi-building me-2 text-success"></i>Company Policies</div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">GEN-POL-001</span>
                                    <div class="rg-rule-title">General Compliance Policy</div>
                                    <div class="rg-rule-desc">Ang user ay kumilos ayon sa lahat ng company policies. Walang violations. Ginamit ang tamang role at access level.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">AUDIT-POL-001</span>
                                    <div class="rg-rule-title">Audit Trail and Accountability Policy</div>
                                    <div class="rg-rule-desc">Ang action ay naka-log nang maayos — may responsible user, timestamp, at details. Ito ay patunay ng compliant na paggamit ng sistema.</div>
                                </div>
                            </div>

                            <!-- ── NON-COMPLIANT ── -->
                            <div id="rg-noncompliant" class="rg-panel px-4 py-3" style="display:none;">
                                <div class="rg-status-banner rg-banner-noncompliant">
                                    <i class="bi bi-x-circle-fill fs-4"></i>
                                    <div>
                                        <div class="fw-bold fs-6">❌ NON-COMPLIANT</div>
                                        <div class="small opacity-90">May violation o failed action. Kailangang i-review at aksyunan agad.</div>
                                    </div>
                                </div>

                                <div class="rg-keywords-box rg-kw-noncompliant">
                                    <div class="rg-kw-title"><i class="bi bi-lightning-fill me-1"></i>Mga keywords na nagiging NON-COMPLIANT</div>
                                    <div class="rg-kw-list">
                                        <span class="rg-kw rg-kw-danger">failed</span><span class="rg-kw rg-kw-danger">wrong</span>
                                        <span class="rg-kw rg-kw-danger">invalid</span><span class="rg-kw rg-kw-danger">incorrect</span>
                                        <span class="rg-kw rg-kw-danger">unauthorized</span><span class="rg-kw rg-kw-danger">denied</span>
                                        <span class="rg-kw rg-kw-danger">blocked</span><span class="rg-kw rg-kw-danger">expired</span>
                                        <span class="rg-kw rg-kw-danger">error</span><span class="rg-kw rg-kw-danger">rejected</span>
                                        <span class="rg-kw rg-kw-danger">violation</span><span class="rg-kw rg-kw-danger">suspicious</span>
                                        <span class="rg-kw rg-kw-danger">brute</span><span class="rg-kw rg-kw-danger">banned</span>
                                        <span class="rg-kw rg-kw-danger">locked</span><span class="rg-kw rg-kw-danger">inactive</span>
                                        <span class="rg-kw rg-kw-danger">unknown user</span><span class="rg-kw rg-kw-danger">login failed</span>
                                        <span class="rg-kw rg-kw-danger">otp failed</span>
                                    </div>
                                </div>

                                <div class="rg-section-title"><i class="bi bi-bank me-2 text-primary"></i>Government Rules & Regulations</div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-10173</span>
                                    <div class="rg-rule-title">Data Privacy Act — Violation Risk <span class="rg-agency">— NPC</span></div>
                                    <div class="rg-rule-desc">Non-compliant actions na may kaugnayan sa personal data ay maaaring maglabag sa RA 10173. Ang data breaches at unauthorized access ay dapat i-report sa NPC sa loob ng 72 oras.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-8792</span>
                                    <div class="rg-rule-title">E-Commerce Act — Unauthorized Access <span class="rg-agency">— DTI</span></div>
                                    <div class="rg-rule-desc">Ang pag-access nang walang pahintulot sa computer systems at electronic records ay krimen ayon sa RA 8792. Paulit-ulit na violations ay dapat i-escalate sa law enforcement.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-9160</span>
                                    <div class="rg-rule-title">Anti-Money Laundering Act — Suspicious Activity <span class="rg-agency">— AMLC</span></div>
                                    <div class="rg-rule-desc">Ang hindi authorized na financial actions ay maaaring suspicious. Ang malalaking, irregular, o unauthorized na transaksyon ay dapat i-review at i-report sa AMLC kung kinakailangan.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">BSP-982</span>
                                    <div class="rg-rule-title">BSP IT Risk Management — Security Violation <span class="rg-agency">— BSP</span></div>
                                    <div class="rg-rule-desc">Ang failed authentication, unauthorized access attempts, at security violations ay dapat i-log at imbestigahan ayon sa BSP IT risk management requirements.</div>
                                </div>

                                <div class="rg-section-title mt-3"><i class="bi bi-building me-2 text-success"></i>Company Policies</div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">SEC-POL-001</span>
                                    <div class="rg-rule-title">User Authentication Policy — Violation</div>
                                    <div class="rg-rule-desc">Pagkatapos ng 5 consecutive na failed login attempts, automatic na nilo-lock ang account. Dapat abisuhan ang Security Officer para sa imbestigasyon.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">RBAC-POL-001</span>
                                    <div class="rg-rule-title">Role-Based Access Control — Unauthorized Access</div>
                                    <div class="rg-rule-desc">Ang pag-access sa modules o pagsasagawa ng actions na hindi allowed sa role ng user ay non-compliant. Dapat i-review ang permissions at gumawa ng corrective action.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">DATA-POL-001</span>
                                    <div class="rg-rule-title">Data Integrity Policy — Invalid or Incorrect Data</div>
                                    <div class="rg-rule-desc">Ang pag-submit ng invalid, incorrect, o incomplete na data ay labag sa Data Integrity Policy. Dapat itama ang records na may dokumentasyon. Paulit-ulit na violations ay maaaring magresulta sa disciplinary action.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">AUDIT-POL-002</span>
                                    <div class="rg-rule-title">Audit Trail — Permanent Record of Violations</div>
                                    <div class="rg-rule-desc">Lahat ng non-compliant actions ay permanenteng naka-record sa audit trail. Ang users ay accountable sa lahat ng actions na ginawa sa ilalim ng kanilang account.</div>
                                </div>
                            </div>

                            <!-- ── UNDER REVIEW ── -->
                            <div id="rg-underreview" class="rg-panel px-4 py-3" style="display:none;">
                                <div class="rg-status-banner rg-banner-underreview">
                                    <i class="bi bi-eye-fill fs-4"></i>
                                    <div>
                                        <div class="fw-bold fs-6">🔵 UNDER REVIEW</div>
                                        <div class="small opacity-90">Kailangan ng manual na pag-review ng authorized officer bago maging final ang desisyon.</div>
                                    </div>
                                </div>

                                <div class="rg-keywords-box rg-kw-underreview">
                                    <div class="rg-kw-title"><i class="bi bi-lightning-fill me-1"></i>Mga keywords na nagiging UNDER REVIEW</div>
                                    <div class="rg-kw-list">
                                        <span class="rg-kw rg-kw-review">large</span><span class="rg-kw rg-kw-review">high amount</span>
                                        <span class="rg-kw rg-kw-review">high-risk</span><span class="rg-kw rg-kw-review">high risk</span>
                                        <span class="rg-kw rg-kw-review">ai result</span><span class="rg-kw rg-kw-review">ai scored</span>
                                        <span class="rg-kw rg-kw-review">credit score</span><span class="rg-kw rg-kw-review">credit scoring</span>
                                        <span class="rg-kw rg-kw-review">ai decision</span><span class="rg-kw rg-kw-review">manual verification</span>
                                        <span class="rg-kw rg-kw-review">review needed</span><span class="rg-kw rg-kw-review">flagged</span>
                                        <span class="rg-kw rg-kw-review">override</span><span class="rg-kw rg-kw-review">bulk</span>
                                        <span class="rg-kw rg-kw-review">mass</span><span class="rg-kw rg-kw-review">role change</span>
                                        <span class="rg-kw rg-kw-review">permission change</span><span class="rg-kw rg-kw-review">high value</span>
                                    </div>
                                </div>

                                <div class="rg-section-title"><i class="bi bi-bank me-2 text-primary"></i>Government Rules & Regulations</div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">BSP-1048</span>
                                    <div class="rg-rule-title">BSP Lending Regulations — Loan Review Requirements <span class="rg-agency">— BSP</span></div>
                                    <div class="rg-rule-desc">Ang malalaking loans, AI-scored applications, at high-risk transactions ay kailangan ng manual review ng authorized officer bago mag-final approval ayon sa BSP lending regulations.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-9160</span>
                                    <div class="rg-rule-title">AMLA — High-Value Transaction Review <span class="rg-agency">— AMLC</span></div>
                                    <div class="rg-rule-desc">Ang mga transaksyon na PHP 500,000 pataas (covered transactions) ay dapat i-review at maaaring i-report sa AMLC. Ang suspicious transactions ay dapat i-report kahit anong halaga.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">BSP-1082</span>
                                    <div class="rg-rule-title">BSP Guidelines on AI Use — Human Oversight <span class="rg-agency">— BSP</span></div>
                                    <div class="rg-rule-desc">Ang AI-generated decisions sa financial services ay dapat laging may human oversight at review bago maging final. Ang AI results ay recommendations lang — hindi final decisions.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-10173</span>
                                    <div class="rg-rule-title">Data Privacy Act — AI Decision Review <span class="rg-agency">— NPC</span></div>
                                    <div class="rg-rule-desc">Ang automated decisions na nakakaapekto sa personal data — kasama ang AI credit scoring — ay dapat may human review para masiguro ang fairness at compliance sa data privacy rights.</div>
                                </div>

                                <div class="rg-section-title mt-3"><i class="bi bi-building me-2 text-success"></i>Company Policies</div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">AI-POL-002</span>
                                    <div class="rg-rule-title">Human Review of AI Decisions Policy</div>
                                    <div class="rg-rule-desc">Lahat ng AI credit scores at decisions ay dapat i-review ng authorized loan officer bago gumawa ng aksyon. Ang reviewer ay dapat mag-dokumento ng kanyang findings.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">LOAN-POL-002</span>
                                    <div class="rg-rule-title">Loan Approval Policy — High-Value Review</div>
                                    <div class="rg-rule-desc">Ang mga loans na nangangailangan ng special review ay dapat i-escalate sa tamang officer level batay sa halaga. Ang reviewing officer ay dapat mag-dokumento ng approval o rejection na may justification.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">USR-POL-002</span>
                                    <div class="rg-rule-title">Role Change Authorization — Review Required</div>
                                    <div class="rg-rule-desc">Ang role at permission changes ay kailangan ng review at written authorization ng System Administrator at Department Head bago maging final.</div>
                                </div>
                            </div>

                            <!-- ── PENDING ── -->
                            <div id="rg-pending" class="rg-panel px-4 py-3" style="display:none;">
                                <div class="rg-status-banner rg-banner-pending">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                    <div>
                                        <div class="fw-bold fs-6">🟠 PENDING</div>
                                        <div class="small opacity-90">Nagsimula na ang action pero hindi pa tapos o approved. Naghihintay ng susunod na hakbang.</div>
                                    </div>
                                </div>

                                <div class="rg-keywords-box rg-kw-pending">
                                    <div class="rg-kw-title"><i class="bi bi-lightning-fill me-1"></i>Mga keywords na nagiging PENDING</div>
                                    <div class="rg-kw-list">
                                        <span class="rg-kw rg-kw-pending">otp sent</span><span class="rg-kw rg-kw-pending">awaiting</span>
                                        <span class="rg-kw rg-kw-pending">waiting</span><span class="rg-kw rg-kw-pending">pending approval</span>
                                        <span class="rg-kw rg-kw-pending">disbursement request</span><span class="rg-kw rg-kw-pending">loan request</span>
                                        <span class="rg-kw rg-kw-pending">submitted</span><span class="rg-kw rg-kw-pending">queued</span>
                                        <span class="rg-kw rg-kw-pending">in progress</span><span class="rg-kw rg-kw-pending">processing</span>
                                        <span class="rg-kw rg-kw-pending">sent for approval</span>
                                    </div>
                                </div>

                                <div class="rg-section-title"><i class="bi bi-bank me-2 text-primary"></i>Government Rules & Regulations</div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">BSP-1048</span>
                                    <div class="rg-rule-title">BSP Lending Regulations — Processing Timeline <span class="rg-agency">— BSP</span></div>
                                    <div class="rg-rule-desc">Ang loan applications ay dapat maproseso sa loob ng makatwirang panahon. Ang pending applications ay hindi dapat pabayaan. Dapat abisuhan ang applicant tungkol sa status ng kanilang application.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-3765</span>
                                    <div class="rg-rule-title">Truth in Lending Act — Disclosure Before Release <span class="rg-agency">— BSP</span></div>
                                    <div class="rg-rule-desc">Bago ilabas ang loan mula sa pending status, lahat ng terms, interest rates, at fees ay dapat fully disclosed sa borrower sa sulat.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-gov">
                                    <span class="rg-code rg-code-gov">RA-9520</span>
                                    <div class="rg-rule-title">Philippine Cooperative Code — Member Rights <span class="rg-agency">— CDA</span></div>
                                    <div class="rg-rule-desc">Ang mga miyembro ay may karapatang malaman ang status ng kanilang loan applications at transactions. Ang pending items ay dapat resolbahin sa tamang panahon.</div>
                                </div>

                                <div class="rg-section-title mt-3"><i class="bi bi-building me-2 text-success"></i>Company Policies</div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">LOAN-POL-001</span>
                                    <div class="rg-rule-title">Loan Application Requirements — Pending Completion</div>
                                    <div class="rg-rule-desc">Ang pending loan applications ay naghihintay ng kumpleto na dokumentasyon o required approvals. Lahat ng required documents ay dapat i-submit bago maproseso ang application.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">SLA-POL-001</span>
                                    <div class="rg-rule-title">Service Level Agreement (SLA) — Processing Time</div>
                                    <div class="rg-rule-desc">Loan applications: 3 business days. Disbursements: 1 business day pagkatapos ng approval. OTP verification: 10 minuto. Ang hindi masunod ang SLA ay escalation sa supervisor.</div>
                                </div>
                                <div class="rg-rule-card rg-rule-company">
                                    <span class="rg-code rg-code-company">AUDIT-POL-001</span>
                                    <div class="rg-rule-title">Audit Trail — Pending Status Monitoring</div>
                                    <div class="rg-rule-desc">Lahat ng pending transactions ay nino-monitor ng sistema. Ang mga transaksyon na lumagpas sa SLA period ay automatic na ine-escalate sa responsible supervisor.</div>
                                </div>
                            </div>

                        </div><!-- /.modal-body -->

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg me-1"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ════ End Rules Guide Modal ════ -->

        </div>
    </main>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Rules Guide Tab Switcher ─────────────────────────────────────
    window.rgShowTab = function (tab, btn) {
        document.querySelectorAll('.rg-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.rg-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('rg-' + tab).style.display = '';
        btn.classList.add('active');
    };

    // ── Toast helper ────────────────────────────────────────────────
    const Toast = Swal.mixin({
        toast: true, position: 'top-end',
        showConfirmButton: false, timer: 3000, timerProgressBar: true
    });
    function toastError(msg)   { Toast.fire({ icon: 'error',   title: msg }); }
    function toastSuccess(msg) { Toast.fire({ icon: 'success', title: msg }); }

    // ── DOM refs ────────────────────────────────────────────────────
    const tbody            = document.querySelector('#logsTable tbody');
    const pagination       = document.getElementById('logsPagination');
    const recordInfo       = document.getElementById('recordInfo');
    const recordInfoBottom = document.getElementById('recordInfoBottom');
    const searchInput      = document.getElementById('search');
    const startInput       = document.getElementById('start');
    const endInput         = document.getElementById('end');
    const statusInput      = document.getElementById('status');
    const rowsInput        = document.getElementById('rowsPerPage');
    const exportPdfBtn     = document.getElementById('exportPdfBtn');
    const exportCsvBtn     = document.getElementById('exportCsvBtn');
    const reloadBtn        = document.getElementById('reloadBtn');

    let currentPage  = 1;
    let currentLimit = 10;

    // ── Escape HTML ─────────────────────────────────────────────────
    function escapeHtml(text) {
        const map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
        return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '-';
    }

    // ── File download helper ────────────────────────────────────────
    async function downloadFile(url, filename) {
        const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
        const ct  = res.headers.get('content-type') || '';
        if (!res.ok) throw new Error('Download request failed.');
        if (ct.includes('application/json')) {
            const p = await res.json();
            throw new Error(p.msg || p.message || 'Export failed.');
        }
        const blob = await res.blob();
        const ou   = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = ou; a.download = filename;
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(ou);
    }

    // ── Load Summary Cards ──────────────────────────────────────────
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
                document.getElementById('count-Compliant').textContent    = s['Compliant']    ?? 0;
                document.getElementById('count-Non-Compliant').textContent = s['Non-Compliant'] ?? 0;
                document.getElementById('count-Pending').textContent      = s['Pending']      ?? 0;
                document.getElementById('count-Under-Review').textContent  = s['Under Review']  ?? 0;
            }
        })
        .catch(() => {});
    }

    // ── Open Status List Modal (summary card click) ─────────────────
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

        const modal     = new bootstrap.Modal(document.getElementById('statusModal'));
        const modalBody = document.getElementById('modalLogsBody');
        const modalInfo = document.getElementById('modalRecordInfo');
        modalBody.innerHTML   = '<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';
        modalInfo.textContent = '';
        modal.show();

        const params = new URLSearchParams({
            action: 'list', page: 1, limit: 100,
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
                modalBody.innerHTML   = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No ${escapeHtml(status)} logs found</td></tr>`;
                modalInfo.textContent = 'No records found';
                return;
            }
            modalBody.innerHTML = '';
            data.rows.forEach((r, i) => {
                const raw    = r.action_type || '';
                const clean  = raw.replace(' (High Risk)','').replace(' (Medium Risk)','').replace(' (Low Risk)','');
                let risk = '';
                if (raw.includes('High Risk'))        risk = '<span class="badge ms-1" style="background:#dc2626;font-size:0.65rem;">🔴 High</span>';
                else if (raw.includes('Medium Risk')) risk = '<span class="badge ms-1" style="background:#ea580c;font-size:0.65rem;">🟠 Med</span>';
                else if (raw.includes('Low Risk'))    risk = '<span class="badge ms-1" style="background:#ca8a04;font-size:0.65rem;">🟡 Low</span>';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${i+1}</td>
                    <td>${escapeHtml(r.full_name||r.username||'System')}</td>
                    <td><small>${escapeHtml(clean)}${risk}</small></td>
                    <td><small>${escapeHtml(r.module_name)}</small></td>
                    <td><small>${escapeHtml(r.remarks||'-')}</small></td>
                    <td><small>${escapeHtml(r.action_time)}</small></td>
                    <td><small>${escapeHtml(r.ip_address||'-')}</small></td>
                `;
                modalBody.appendChild(tr);
            });
            modalInfo.textContent = `Showing ${data.rows.length} of ${data.total} ${status} records`;
        })
        .catch(() => {
            modalBody.innerHTML = '<tr><td colspan="7" class="text-danger text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load data</td></tr>';
        });
    };

    // ── Compliance Detail Modal config ──────────────────────────────
    const CDM_CFG = {
        'Compliant':    { bg:'linear-gradient(135deg,#059669,#047857)', badge:'bg-success',           alert:'alert-compliant',    icon:'bi-check-circle-fill' },
        'Non-Compliant':{ bg:'linear-gradient(135deg,#ef4444,#dc2626)', badge:'bg-danger',            alert:'alert-noncompliant', icon:'bi-x-circle-fill' },
        'Pending':      { bg:'linear-gradient(135deg,#f59e0b,#d97706)', badge:'bg-warning text-dark', alert:'alert-pending',      icon:'bi-hourglass-split' },
        'Under Review': { bg:'linear-gradient(135deg,#3b82f6,#2563eb)', badge:'bg-info text-dark',    alert:'alert-underreview',  icon:'bi-eye-fill' },
    };

    // ── Open Compliance Detail Modal (row click) ────────────────────
    window.openComplianceDetail = function (auditId) {
        const modal   = new bootstrap.Modal(document.getElementById('complianceDetailModal'));
        const loading = document.getElementById('cdm-loading');
        const content = document.getElementById('cdm-content');

        loading.style.display = '';
        content.style.display = 'none';
        modal.show();

        fetch(`compliance_logs_action.php?detail=1&id=${encodeURIComponent(auditId)}`)
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') {
                    loading.innerHTML = `<p class="text-danger py-4 text-center"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(data.msg || 'Failed to load details')}</p>`;
                    return;
                }

                const rec  = data.record;
                const comp = data.compliance;
                const cfg  = CDM_CFG[comp.status] || CDM_CFG['Compliant'];

                // Header
                document.getElementById('cdm-header').style.background = cfg.bg;
                document.getElementById('cdm-title').textContent    = comp.status + ' — Compliance Details';
                document.getElementById('cdm-subtitle').textContent = comp.category;

                // Record fields
                document.getElementById('cdm-user').textContent        = rec.user        || '—';
                document.getElementById('cdm-datetime').textContent    = rec.created_at  || '—';
                document.getElementById('cdm-action').textContent      = rec.action_type || '—';
                document.getElementById('cdm-module').textContent      = rec.module      || '—';
                document.getElementById('cdm-description').textContent = rec.description || '—';
                document.getElementById('cdm-ip').textContent          = rec.ip_address  || '—';

                // Status badge
                const sb = document.getElementById('cdm-status-badge');
                sb.className = `badge fs-6 px-3 py-2 ${cfg.badge}`;
                sb.innerHTML = `<i class="bi ${cfg.icon} me-1"></i>${escapeHtml(comp.status)}`;

                // Category
                document.getElementById('cdm-category').textContent = comp.category;

                // Reason
                document.getElementById('cdm-reason').textContent = comp.reason;

                // Recommended action
                const alertEl = document.getElementById('cdm-recommended-alert');
                alertEl.className = `alert py-2 px-3 mb-0 ${cfg.alert}`;
                document.getElementById('cdm-recommended').textContent = comp.recommended_action;

                // Rules — split into Government and Company sections
                const rulesList = document.getElementById('cdm-rules-list');
                rulesList.innerHTML = '';
                if (comp.rules && comp.rules.length) {
                    const govRules  = comp.rules.filter(r => r.type === 'government');
                    const compRules = comp.rules.filter(r => r.type === 'company');

                    function buildRuleCard(rule, isGov) {
                        const div = document.createElement('div');
                        div.className = 'rule-card ' + (isGov ? 'rule-card-gov' : 'rule-card-company');
                        div.innerHTML = `
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="rule-code ${isGov ? 'rule-code-gov' : 'rule-code-company'}">${escapeHtml(rule.code)}</span>
                                <span class="rule-regulator">${escapeHtml(rule.regulator || '')}</span>
                            </div>
                            <div class="rule-title">${escapeHtml(rule.title)}</div>
                            <div class="rule-desc">${escapeHtml(rule.description)}</div>
                            <div class="rule-source"><i class="bi bi-book me-1"></i>${escapeHtml(rule.source)}</div>
                        `;
                        return div;
                    }

                    if (govRules.length) {
                        const header = document.createElement('div');
                        header.className = 'rules-section-header rules-gov-header';
                        header.innerHTML = '<i class="bi bi-bank me-2"></i>Government Rules & Regulations (Philippines)';
                        rulesList.appendChild(header);
                        govRules.forEach(r => rulesList.appendChild(buildRuleCard(r, true)));
                    }

                    if (compRules.length) {
                        const header = document.createElement('div');
                        header.className = 'rules-section-header rules-company-header';
                        header.innerHTML = '<i class="bi bi-building me-2"></i>Company Policies';
                        rulesList.appendChild(header);
                        compRules.forEach(r => rulesList.appendChild(buildRuleCard(r, false)));
                    }
                } else {
                    rulesList.innerHTML = '<p class="text-muted small">No specific rules found for this action.</p>';
                }

                loading.style.display = 'none';
                content.style.display = '';
            })
            .catch(() => {
                loading.innerHTML = '<p class="text-danger py-4 text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load details.</p>';
            });
    };

    // ── Load Logs Table ─────────────────────────────────────────────
    function loadLogs(page = 1) {
        currentPage  = page;
        currentLimit = parseInt(rowsInput.value);

        const params = new URLSearchParams({
            action: 'list', page: page, limit: currentLimit,
            search: searchInput.value || '',
            start:  startInput.value  || '',
            end:    endInput.value    || '',
            status: statusInput.value || ''
        });

        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

        fetch('compliance_logs_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(res => { if (!res.ok) throw new Error('Network error'); return res.json(); })
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
                recordInfo.textContent       = 'No records found';
                recordInfoBottom.textContent = '';
                pagination.innerHTML = '';
                return;
            }

            const startRec = ((currentPage - 1) * currentLimit) + 1;
            const endRec   = Math.min(startRec + rows.length - 1, data.total);

            rows.forEach((r, index) => {
                const badgeClass =
                    r.compliance_status === 'Compliant'     ? 'bg-success' :
                    r.compliance_status === 'Non-Compliant' ? 'bg-danger' :
                    r.compliance_status === 'Pending'       ? 'bg-warning text-dark' :
                    'bg-info text-dark';

                const actionText = r.action_type || '';
                let riskBadge = '';
                if (actionText.includes('(High Risk)'))        riskBadge = '<span class="badge ms-1" style="background:#dc2626;">🔴 High Risk</span>';
                else if (actionText.includes('(Medium Risk)')) riskBadge = '<span class="badge ms-1" style="background:#ea580c;">🟠 Medium Risk</span>';
                else if (actionText.includes('(Low Risk)'))    riskBadge = '<span class="badge ms-1" style="background:#ca8a04;">🟡 Low Risk</span>';

                const cleanAction = actionText
                    .replace(' (High Risk)','').replace(' (Medium Risk)','').replace(' (Low Risk)','');

                const tr = document.createElement('tr');
                tr.title = 'Click to view compliance details';
                tr.addEventListener('click', () => openComplianceDetail(r.audit_id));
                tr.innerHTML = `
                    <td>${startRec + index}</td>
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

            const infoText = `Showing ${startRec} to ${endRec} of ${data.total} entries`;
            recordInfo.textContent       = infoText;
            recordInfoBottom.textContent = infoText;

            // Pagination
            pagination.innerHTML = '';
            const totalPages = Math.max(1, Math.ceil((data.total || 0) / currentLimit));

            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
            pagination.appendChild(prevLi);

            const maxP = 5;
            let sp = Math.max(1, currentPage - Math.floor(maxP / 2));
            let ep = Math.min(totalPages, sp + maxP - 1);
            if (ep - sp < maxP - 1) sp = Math.max(1, ep - maxP + 1);

            if (sp > 1) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
                pagination.appendChild(li);
                if (sp > 2) {
                    const d = document.createElement('li');
                    d.className = 'page-item disabled';
                    d.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(d);
                }
            }

            for (let i = sp; i <= ep; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                pagination.appendChild(li);
            }

            if (ep < totalPages) {
                if (ep < totalPages - 1) {
                    const d = document.createElement('li');
                    d.className = 'page-item disabled';
                    d.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(d);
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
        .catch(err => {
            console.error(err);
            toastError('Failed to load data. Please try again.');
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load data</td></tr>';
        });
    }

    // ── Export PDF ──────────────────────────────────────────────────
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            const prompt = await Swal.fire({
                title: 'Protect PDF Export',
                text: 'Enter a password required to open the exported PDF file.',
                input: 'password', inputLabel: 'PDF Password',
                inputPlaceholder: 'Enter at least 6 characters',
                inputAttributes: { maxlength: 64, autocapitalize: 'off', autocorrect: 'off' },
                showCancelButton: true, confirmButtonText: 'Export PDF', cancelButtonText: 'Cancel',
                inputValidator: v => (!v || v.trim().length < 6) ? 'Please enter a password with at least 6 characters.' : null
            });
            if (!prompt.isConfirmed) return;

            const params = new URLSearchParams({
                export: 'pdf', search: searchInput.value||'', start: startInput.value||'',
                end: endInput.value||'', status: statusInput.value||'',
                pdf_password: prompt.value
            });
            const orig = exportPdfBtn.innerHTML;
            exportPdfBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            exportPdfBtn.disabled  = true;
            try {
                await downloadFile('compliance_logs_action.php?' + params.toString(),
                    `compliance_logs_${new Date().toISOString().split('T')[0]}.pdf`);
                Swal.fire({ icon:'success', title:'PDF Exported', text:'Use your password to open the file.', timer:3000, showConfirmButton:false });
            } catch (err) { toastError(err.message || 'Failed to export PDF.'); }
            exportPdfBtn.innerHTML = orig;
            exportPdfBtn.disabled  = false;
        });
    }

    // ── Export CSV ──────────────────────────────────────────────────
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            const prompt = await Swal.fire({
                title: 'Protect CSV Export',
                text: 'Enter a password to encrypt this CSV export in a ZIP file.',
                input: 'password', inputLabel: 'Export Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true, confirmButtonText: 'Export CSV', cancelButtonText: 'Cancel',
                inputValidator: v => (!v || v.trim().length < 6) ? 'Please enter at least 6 characters.' : null
            });
            if (!prompt.isConfirmed) return;
            const pass = prompt.value;

            const params = new URLSearchParams({
                export: 'csv', search: searchInput.value||'', start: startInput.value||'',
                end: endInput.value||'', status: statusInput.value||'',
                pdf_password: pass
            });
            const orig = exportCsvBtn.innerHTML;
            exportCsvBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
            exportCsvBtn.disabled  = true;
            try {
                await downloadFile('compliance_logs_action.php?' + params.toString(),
                    'compliance_logs_' + new Date().toISOString().split('T')[0] + (pass ? '.zip' : '.csv'));
                Swal.fire({ icon:'success', title:'CSV Exported',
                    text: pass ? 'The ZIP file is password protected.' : 'File downloaded successfully.',
                    timer:3000, showConfirmButton:false });
            } catch (err) { toastError(err.message || 'Failed to export CSV.'); }
            exportCsvBtn.innerHTML = orig;
            exportCsvBtn.disabled  = false;
        });
    }

    // ── Event Listeners ─────────────────────────────────────────────
    document.getElementById('filterBtn').addEventListener('click', e => {
        e.preventDefault();
        if (startInput.value && endInput.value && startInput.value > endInput.value)
            return toastError('Start date must be before end date.');
        loadLogs(1); loadSummary();
    });

    reloadBtn.addEventListener('click', () => {
        searchInput.value = ''; startInput.value = ''; endInput.value = '';
        statusInput.value = ''; rowsInput.value  = '10';
        loadLogs(1); loadSummary();
    });

    rowsInput.addEventListener('change',  () => { loadLogs(1); });
    statusInput.addEventListener('change', () => { loadLogs(1); });

    searchInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') { e.preventDefault(); loadLogs(1); loadSummary(); }
    });

    pagination.addEventListener('click', e => {
        e.preventDefault();
        if (e.target.tagName === 'A' && !e.target.parentElement.classList.contains('disabled')) {
            const p = parseInt(e.target.dataset.page);
            if (p > 0) loadLogs(p);
        }
    });

    // ── Initial load ────────────────────────────────────────────────
    loadLogs(1);
    loadSummary();
});
</script>