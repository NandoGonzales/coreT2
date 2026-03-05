<?php
// === Initialize database and session FIRST ===
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// === Include layout files AFTER database is ready ===
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<!-- Add Chart.js and jsPDF CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

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

    /* Enhanced Stat Cards */
    .stat-card {
        padding: 1.75rem;
        border-radius: 1rem;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border: 2px solid transparent;
        overflow: hidden;
        background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
        box-shadow: var(--shadow-md);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transform: translate(30%, -30%);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-xl);
    }

    .stat-card:hover::before {
        transform: translate(20%, -20%) scale(1.5);
    }

    .stat-card.active {
        border: 2px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2), var(--shadow-xl);
        transform: translateY(-8px) scale(1.05);
    }

    .stat-card.active::after {
        content: '✓ ACTIVE';
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.25);
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        letter-spacing: 0.05em;
    }

    .stat-card-icon {
        width: 3rem;
        height: 3rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-title {
        font-size: 0.875rem;
        opacity: 0.95;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2.25rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-hint {
        font-size: 0.75rem;
        opacity: 0.8;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Card color schemes */
    .stat-card[data-filter="all"] {
        --card-color-1: #3b82f6;
        --card-color-2: #2563eb;
    }

    .stat-card[data-filter="active"] {
        --card-color-1: #059669;
        --card-color-2: #047857;
    }

    .stat-card[data-filter="overdue"] {
        --card-color-1: #f59e0b;
        --card-color-2: #d97706;
    }

    .stat-card[data-filter="at_risk"] {
        --card-color-1: #ef4444;
        --card-color-2: #dc2626;
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

    /* Enhanced Chart Cards */
    .chart-card {
        padding: 1.5rem;
        border-radius: 1rem;
        background: #fff;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .chart-card h6 {
        font-weight: 700;
        color: #111827;
        font-size: 1rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-card h6::before {
        content: '';
        width: 4px;
        height: 1.25rem;
        background: var(--brand-primary);
        border-radius: 2px;
    }

    .chart-container {
        position: relative;
        height: 280px;
    }

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
        border-bottom: 2px solid #1f2937;
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

    #filterIndicator {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
    }

    #recordCount {
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
        background: linear-gradient(135deg, var(--brand-primary), #047857);
    }

    .table thead th {
        color: #1f2937 !important;
        font-weight: 600;
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

    .btn-outline-primary {
        border: 2px solid #3b82f6;
        color: #3b82f6;
    }

    .btn-outline-primary:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
    }

    .btn-outline-secondary {
        border: 2px solid #6b7280;
        color: #6b7280;
    }

    .btn-outline-secondary:hover {
        background: #6b7280;
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

    #paginationInfo {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    #paginationControls .btn {
        margin-left: 0.5rem;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
        }

        .filter-section {
            padding: 1rem;
        }
    }
</style>

<!-- ✅ FIXED: Added main-wrap wrapper to prevent sidebar overlap -->
<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">
            <!-- Enhanced Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4>Collection Monitoring & Recovery</h4>
                        <p class="subtitle mb-0">Track payment collections, monitor due dates, and manage recovery activities</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="exportPdfBtn" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <button id="exportExcelBtn" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
                        </button>
                        <button id="autoNotifyBtn" class="btn btn-sm btn-warning fw-bold" onclick="runAutoNotifications()" title="Send automatic due/overdue reminders">
                            <i class="bi bi-bell-fill me-1"></i>Auto Notify
                        </button>
                        <button id="reloadBtn" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Reload
                        </button>
                    </div>
                </div>
            </div>

            <!-- Email Records Toggle Button -->
            <div class="d-flex justify-content-end mb-2" id="emailRecordsToggleWrap">
                <button class="btn btn-sm btn-outline-info fw-bold" id="toggleEmailRecordsBtn" onclick="toggleEmailRecords()">
                    <i class="bi bi-envelope-check me-1"></i>Email Records
                </button>
            </div>

            <!-- Email Records Section (hidden by default) -->
            <div id="emailRecordsSection" style="display:none;" class="mb-4">
                <div class="table-card" style="background:white;padding:1.5rem;border-radius:1rem;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);border:1px solid #e5e7eb;">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2" style="border-bottom:2px solid #f3f4f6;">
                        <h6 class="fw-bold mb-0"><i class="bi bi-envelope-check-fill text-info me-2"></i>Email Notification Records</h6>
                        <button class="btn btn-sm btn-outline-secondary" onclick="loadEmailRecords()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                    </div>
                    <div class="table-wrapper" style="overflow-x:auto;border-radius:.75rem;border:1px solid #e5e7eb;">
                        <table class="table table-hover mb-0">
                            <thead style="background:#f9fafb;">
                                <tr>
                                    <th>Borrower</th>
                                    <th>Date Sent</th>
                                    <th>Sender</th>
                                    <th>Message Type</th>
                                    <th>Status</th>
                                    <th class="text-center">View</th>
                                </tr>
                            </thead>
                            <tbody id="emailRecordsTbody">
                                <tr><td colspan="6" class="text-center text-muted">Click "Refresh" to load records.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Enhanced Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="stat-title">Total Loans</div>
                        <div id="card_total_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to view all
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="active">
                        <div class="stat-card-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-title">Active Loans</div>
                        <div id="card_active_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="overdue">
                        <div class="stat-card-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-title">Overdue Loans</div>
                        <div id="card_overdue_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="at_risk">
                        <div class="stat-card-icon">
                            <i class="bi bi-shield-exclamation"></i>
                        </div>
                        <div class="stat-title">At Risk Loans</div>
                        <div id="card_at_risk_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search loans...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Delinquent">Delinquent</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Risk Level</label>
                        <select id="riskFilter" class="form-select">
                            <option value="">All Risks</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Loan Type</label>
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rows per page</label>
                        <select id="rowsPerPage" class="form-select">
                            <option value="10" selected>10 rows</option>
                            <option value="20">20 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="clearFilters" class="btn btn-outline-secondary w-100">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Loan Status Distribution</h6>
                        <div class="chart-container">
                            <canvas id="loanStatusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Risk Level Breakdown</h6>
                        <div class="chart-container">
                            <canvas id="riskChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Loan Portfolio Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span id="tableTitle">Loan Portfolio Table</span>
                        <span id="filterIndicator" class="badge bg-info ms-2" style="display: none;"></span>
                    </h6>
                    <span id="recordCount"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover" id="loanRiskTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Principal</th>
                                <th>Rate</th>
                                <th>Term</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Overdue</th>
                                <th>Risk</th>
                                <th>Next Due</th>
                                <th class="text-center">Notify</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="loanRiskTbody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div id="paginationInfo"></div>
                    <div id="paginationControls" class="btn-group"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- View Loan Modal -->
<div class="modal fade" id="viewLoanModal" tabindex="-1" aria-labelledby="viewLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem; border: none; box-shadow: var(--shadow-xl);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--brand-info), #2563eb); color: white; border-radius: 1rem 1rem 0 0;">
                <h5 class="modal-title" id="viewLoanModalLabel">
                    <i class="bi bi-eye me-2"></i> Loan Details & Payment History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="loanDetailsContent">
                    <p class="text-center text-muted">Loading loan details...</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 2px solid #f3f4f6;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for Notifications -->
<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let loanStatusChart, riskChart;
        let currentPage = 1,
            limit = 10;
        let currentFilters = {
            search: '',
            status: '',
            risk: '',
            type: '',
            cardFilter: 'all'
        };
        let allLoans = [];

        const tbody = document.getElementById('loanRiskTbody');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        const filterIndicator = document.getElementById('filterIndicator');

        // ✅ Helper function - defined FIRST
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ═══════════════════════════════════════════════════════════════
        // EMAIL NOTIFICATION FUNCTIONS
        // ═══════════════════════════════════════════════════════════════

        // ── Email Modal ───────────────────────────────────────
        let emailModalInstance = null;

        async function openEmailModal(loanId, memberName, memberEmail, loanCode, msgType) {
            if (!memberEmail) {
                Swal.fire({ icon:'warning', title:'No Email', text:`${memberName} walang email address sa records.`, confirmButtonColor:'#059669' });
                return;
            }

            // Show loading
            Swal.fire({ title:'Loading...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

            try {
                const res = await fetch('/admin/Repayment-Tracker/send_ai_message.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'preview', loan_id: parseInt(loanId) })
                });
                const data = await res.json();
                Swal.close();

                // Populate modal
                document.getElementById('emailModal_loanId').value    = loanId;
                document.getElementById('emailModal_memberName').textContent  = memberName;
                document.getElementById('emailModal_memberEmail').textContent = memberEmail;
                document.getElementById('emailModal_loanCode').textContent    = loanCode || loanId;

                // Set message type
                const typeSelect = document.getElementById('emailModal_msgType');
                typeSelect.value = data.message_type || msgType || '3_days_before';

                // Set message body
                const msgBody = data.ai_message?.message || data.ai_message || '';
                document.getElementById('emailModal_body').value = typeof msgBody === 'string' ? msgBody : JSON.stringify(msgBody);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('emailModal'));
                modal.show();

            } catch(e) {
                Swal.close();
                Swal.fire({ icon:'error', title:'Error', text:e.message, confirmButtonColor:'#ef4444' });
            }
        }

        async function sendEmailFromModal() {
            const loanId  = document.getElementById('emailModal_loanId').value;
            const msgType = document.getElementById('emailModal_msgType').value;
            const body    = document.getElementById('emailModal_body').value.trim();
            const sendBtn = document.getElementById('emailModal_sendBtn');

            if (!body) { Swal.fire({ icon:'warning', title:'Walang mensahe', text:'Ilagay ang message bago mag-send.', confirmButtonColor:'#059669' }); return; }

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div>Sending...';

            try {
                const res = await fetch('/admin/Repayment-Tracker/send_ai_message.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ action:'send', loan_id: parseInt(loanId), message_type: msgType, custom_message: body })
                });
                const data = await res.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('emailModal'))?.hide();
                    Swal.fire({ icon:'success', title:'Email Sent!', html:`<p>Naipadala na ang email kay <strong>${data.member_name}</strong>.</p>`, confirmButtonColor:'#059669' });
                    // Refresh email records if visible
                    if (document.getElementById('emailRecordsSection').style.display !== 'none') loadEmailRecords();
                    // Update button
                    const btn = document.querySelector(`button[data-notify-loan="${loanId}"]`);
                    if (btn) { btn.innerHTML = '<i class="bi bi-envelope-check-fill"></i>'; btn.classList.replace('btn-outline-primary','btn-success'); }
                } else {
                    Swal.fire({ icon:'error', title:'Send Failed', text: data.message, confirmButtonColor:'#ef4444' });
                }
            } catch(e) {
                Swal.fire({ icon:'error', title:'Error', text:e.message });
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Send Email';
            }
        }

        // ── Email Records ─────────────────────────────────────
        async function loadEmailRecords() {
            const tbody = document.getElementById('emailRecordsTbody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';
            try {
                const res  = await fetch('/admin/Repayment-Tracker/email_records.php');
                const data = await res.json();
                tbody.innerHTML = '';
                if (!data.records?.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><i class="bi bi-inbox"></i> Walang email records pa.</td></tr>';
                    return;
                }
                data.records.forEach(r => {
                    const statusBadge = r.status === 'sent' ? 'bg-success' : r.status === 'failed' ? 'bg-danger' : 'bg-warning text-dark';
                    const typeLabel = { '7_days_before':'Due Reminder (7d)', '3_days_before':'Due Reminder (3d)', 'due_today':'Due Today', 'overdue':'Overdue Notice', 'manual':'Manual', 'final_notice':'Final Notice' }[r.message_type] || r.message_type;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHtml(r.member_name||'—')}</td>
                        <td class="small text-muted">${escapeHtml(r.sent_at||'')}</td>
                        <td class="small">${escapeHtml(r.sent_by_name||'System')}</td>
                        <td><span class="badge bg-info">${escapeHtml(typeLabel)}</span></td>
                        <td><span class="badge ${statusBadge}">${escapeHtml(r.status)}</span></td>
                        <td>
                            <button class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.2rem .5rem;"
                                onclick="viewEmailContent('${r.message_id}')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>`;
                    tbody.appendChild(tr);
                });
            } catch(e) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${escapeHtml(e.message)}</td></tr>`;
            }
        }

        async function viewEmailContent(id) {
            // Show message content in Swal
            try {
                const res  = await fetch('/admin/Repayment-Tracker/email_records.php?id=' + id);
                const data = await res.json();
                if (data.record) {
                    Swal.fire({
                        title: 'Email Content',
                        html: `<div class="text-start small"><strong>To:</strong> ${escapeHtml(data.record.member_name)} &lt;${escapeHtml(data.record.member_email||'')}&gt;<br>
                               <strong>Type:</strong> ${escapeHtml(data.record.message_type)}<br>
                               <strong>Date:</strong> ${escapeHtml(data.record.sent_at)}<br><hr>
                               <pre style="white-space:pre-wrap;font-size:.8rem;max-height:300px;overflow:auto;">${escapeHtml(data.record.message_content)}</pre></div>`,
                        confirmButtonColor:'#059669', width:'600px'
                    });
                }
            } catch(e) {}
        }

        // Legacy wrapper (keep for compatibility)
        function sendEmailNotification(loanId, memberName, memberEmail) {
            openEmailModal(loanId, memberName, memberEmail, '', 'reminder');
        }

        function showToast(message, type) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            toast.style.cssText = 'min-width: 300px; margin-bottom: 10px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        function getDaysUntilDue(dueDate) {
            if (!dueDate || dueDate === '-') return 999;
            const due = new Date(dueDate);
            const today = new Date();
            const diffTime = due - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        }

        // ✅ Event delegation handler for view buttons
        function handleViewButtonClick(e) {
            const button = e.target.closest('.view-loan-btn');
            if (!button) return;

            const loan_id = button.dataset.id;
            console.log('Clicked View button for loan ID:', loan_id);

            const content = document.getElementById('loanDetailsContent');
            content.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p class="mt-2">Loading...</p></div>';

            const url = `../Loan-Portfolio-Risk-Management/loan_crud.php?loan_id=${loan_id}`;
            console.log('Fetching URL:', url);

            fetch(url)
                .then(r => {
                    console.log('Response status:', r.status);
                    if (!r.ok) {
                        return r.text().then(text => {
                            throw new Error(`HTTP ${r.status}: ${text}`);
                        });
                    }
                    return r.json();
                })
                .then(res => {
                    console.log('Response data:', res);

                    if (res.success && res.loan) {
                        const l = res.loan;
                        let html = `
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><strong>Loan ID:</strong> ${l.loan_id}</div>
                    <div class="col-md-6"><strong>Member:</strong> ${escapeHtml(l.member_name)} (ID: ${l.member_id})</div>
                    <div class="col-md-6"><strong>Type:</strong> ${escapeHtml(l.loan_type)}</div>
                    <div class="col-md-6"><strong>Status:</strong> <span class="badge bg-${l.status === 'Active' ? 'success' : l.status === 'Completed' ? 'info' : l.status === 'Delinquent' ? 'danger' : 'warning'}">${l.status}</span></div>
                    <div class="col-md-6"><strong>Principal:</strong> ₱${Number(l.principal_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                    <div class="col-md-6"><strong>Interest Rate:</strong> ${l.interest_rate}%</div>
                    <div class="col-md-6"><strong>Term:</strong> ${l.loan_term} months</div>
                    <div class="col-md-6"><strong>Start:</strong> ${l.start_date}</div>
                    <div class="col-md-6"><strong>End:</strong> ${l.end_date}</div>
                </div>`;

                        if (res.schedules && res.schedules.length > 0) {
                            html += `<h6 class="mb-2"><i class="bi bi-calendar-check me-1"></i> Payment Schedule & History</h6>
                    <div class="table-responsive"><table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Due Date</th>
                            <th>Amount Due</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                        </tr>
                    </thead><tbody>`;

                            res.schedules.forEach(s => {
                                const badge = s.status === 'Paid' ? 'bg-success' : s.status === 'Overdue' ? 'bg-danger' : 'bg-warning';
                                const balance = Number(s.amount_due) - Number(s.amount_paid);
                                html += `<tr>
                            <td>${s.due_date}</td>
                            <td>₱${Number(s.amount_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            <td>₱${Number(s.amount_paid).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            <td>₱${balance.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                            <td>${s.payment_date || '-'}</td>
                            <td><span class="badge ${badge}">${s.status}</span></td>
                        </tr>`;
                            });
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p class="text-muted">No payment schedules available</p>';
                        }

                        content.innerHTML = html;

                        // Show modal
                        const modalEl = document.getElementById('viewLoanModal');
                        const modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    } else {
                        content.innerHTML = '<p class="text-center text-danger">Failed to load loan details.</p>';
                    }
                })
                .catch(err => {
                    console.error('View loan error:', err);
                    content.innerHTML = `<p class="text-center text-danger">Error: ${err.message}</p>`;
                });
        }

        // ✅ Event delegation handler for notify buttons
        function handleNotifyButtonClick(e) {
            const button = e.target.closest('.notify-btn');
            if (!button) return;
            const loanId      = button.dataset.notifyLoan;
            const memberName  = button.dataset.memberName;
            const memberEmail = button.dataset.memberEmail;
            const loanCode    = button.dataset.loanCode;
            const msgType     = button.dataset.msgType;
            openEmailModal(loanId, memberName, memberEmail, loanCode, msgType);
        }

        // --- Load data ---
        function loadData() {
            const params = new URLSearchParams({
                page: currentPage,
                limit: limit,
                search: currentFilters.search,
                status: currentFilters.status,
                risk: currentFilters.risk,
                type: currentFilters.type,
                cardFilter: currentFilters.cardFilter
            });

            tbody.innerHTML = '<tr><td colspan="14" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

            fetch(`ajax_repayments.php?${params}`)
                .then(r => {
                    if (!r.ok) {
                        return r.text().then(text => {
                            throw new Error(`HTTP ${r.status}: ${text}`);
                        });
                    }
                    return r.json();
                })
                .then(data => {
                    console.log('Received data:', data);

                    if (data.error) {
                        throw new Error(data.message || 'Server error');
                    }

                    allLoans = data.all_loans || data.loans || [];
                    renderChartsAndTable(data);
                    populateLoanTypes(data.loan_types || []);
                    updateFilterIndicator();
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    showError('Failed to fetch data: ' + err.message);
                });
        }

        function showError(message) {
            tbody.innerHTML = `<tr><td colspan="14" class="text-center text-danger"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(message)}</td></tr>`;
        }

        function updateFilterIndicator() {
            const filterTexts = {
                'all': '',
                'active': 'Active Loans Only',
                'overdue': 'Overdue Loans Only',
                'at_risk': 'At Risk Loans Only'
            };

            if (currentFilters.cardFilter !== 'all') {
                filterIndicator.textContent = filterTexts[currentFilters.cardFilter];
                filterIndicator.style.display = 'inline-block';
                filterIndicator.className = 'badge ms-2 ' +
                    (currentFilters.cardFilter === 'active' ? 'bg-success' :
                        currentFilters.cardFilter === 'overdue' ? 'bg-warning text-dark' : 'bg-danger');
            } else {
                filterIndicator.style.display = 'none';
            }
        }

        function populateLoanTypes(types) {
            const typeFilter = document.getElementById('typeFilter');
            const currentValue = typeFilter.value;
            typeFilter.innerHTML = '<option value="">All Types</option>';
            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                typeFilter.appendChild(option);
            });
            typeFilter.value = currentValue;
        }

        function renderChartsAndTable(data) {
            // Summary Cards
            document.getElementById('card_total_loans').textContent = data.summary?.total_loans || 0;
            document.getElementById('card_active_loans').textContent = data.summary?.active_loans || 0;
            document.getElementById('card_overdue_loans').textContent = data.summary?.overdue_loans || 0;
            document.getElementById('card_at_risk_loans').textContent = data.summary?.at_risk_loans || 0;

            // Loan Status Chart
            if (loanStatusChart) loanStatusChart.destroy();

            if (data.loan_status && data.loan_status.labels && data.loan_status.labels.length > 0) {
                const ctx = document.getElementById('loanStatusChart');
                if (ctx) {
                    loanStatusChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.loan_status.labels,
                            datasets: [{
                                label: 'Number of Loans',
                                data: data.loan_status.values,
                                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d'],
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Risk Chart
            if (riskChart) riskChart.destroy();

            if (data.risk_breakdown && data.risk_breakdown.labels && data.risk_breakdown.labels.length > 0) {
                const ctx = document.getElementById('riskChart');
                if (ctx) {
                    const filteredLabels = [];
                    const filteredValues = [];
                    const filteredColors = [];
                    const colors = {
                        'Low': '#198754',
                        'Medium': '#ffc107',
                        'High': '#dc3545'
                    };

                    data.risk_breakdown.labels.forEach((label, index) => {
                        const value = data.risk_breakdown.values[index];
                        if (value > 0) {
                            filteredLabels.push(label);
                            filteredValues.push(value);
                            filteredColors.push(colors[label] || '#6c757d');
                        }
                    });

                    if (filteredLabels.length > 0) {
                        riskChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: filteredLabels,
                                datasets: [{
                                    data: filteredValues,
                                    backgroundColor: filteredColors,
                                    borderWidth: 2,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                },
                                cutout: '60%'
                            }
                        });
                    }
                }
            }

            // Record Count
            const start = (currentPage - 1) * limit + 1;
            const end = Math.min(currentPage * limit, data.pagination?.total_records || 0);
            const total = data.pagination?.total_records || 0;
            document.getElementById('recordCount').textContent =
                total > 0 ? `Showing ${start}-${end} of ${total} records` : 'No records found';

            // ✅ FIXED: Table with event delegation + NOTIFICATION BUTTONS
            tbody.innerHTML = '';
            if (data.loans && data.loans.length > 0) {
                data.loans.forEach(l => {
                    const riskBadge = l.risk_level === 'High' ? 'bg-danger' :
                        l.risk_level === 'Medium' ? 'bg-warning text-dark' : 'bg-success';
                    const statusBadge = l.status === 'Active' ? 'bg-success' :
                        l.status === 'Delinquent' ? 'bg-danger' :
                        l.status === 'Completed' ? 'bg-info' : 'bg-warning';

                    // Email button — always show if loan is active
                    const daysUntilDue = getDaysUntilDue(l.next_due);
                    const isOverdue = l.overdue_count > 0;
                    const autoMsgType = isOverdue ? 'overdue' : (daysUntilDue <= 3 ? 'due_today' : (daysUntilDue <= 7 ? '3_days_before' : '7_days_before'));
                    const showNotifyButton = l.email;

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${l.loan_id}</td>
                        <td>${escapeHtml(l.member_name || 'N/A')}</td>
                        <td>${escapeHtml(l.loan_type)}</td>
                        <td>₱${Number(l.principal_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                        <td>${l.interest_rate ?? '-'}%</td>
                        <td>${l.loan_term ?? '-'} mo</td>
                        <td>${l.start_date ?? '-'}</td>
                        <td>${l.end_date ?? '-'}</td>
                        <td><span class="badge ${statusBadge}">${l.status}</span></td>
                        <td><span class="badge ${l.overdue_count > 0 ? 'bg-danger' : 'bg-secondary'}">${l.overdue_count || 0}</span></td>
                        <td><span class="badge ${riskBadge}">${l.risk_level}</span></td>
                        <td>${l.next_due || '-'}</td>
                        <td class="text-center">
                            ${showNotifyButton ? `
                                <button class="btn btn-sm btn-outline-primary notify-btn"
                                    data-notify-loan="${l.loan_id}"
                                    data-member-name="${escapeHtml(l.member_name)}"
                                    data-member-email="${escapeHtml(l.email || '')}"
                                    data-loan-code="${escapeHtml(l.loan_code||'')}"
                                    data-msg-type="${autoMsgType}"
                                    title="Send Email Notification">
                                    <i class="bi bi-envelope-fill"></i>
                                </button>
                            ` : '<small class="text-muted">—</small>'}
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info view-loan-btn" data-id="${l.loan_id}" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // ✅ CRITICAL: Attach event listeners to tbody (event delegation)
                tbody.removeEventListener('click', handleViewButtonClick);
                tbody.addEventListener('click', handleViewButtonClick);

                tbody.removeEventListener('click', handleNotifyButtonClick);
                tbody.addEventListener('click', handleNotifyButtonClick);

            } else {
                const filterMsg = currentFilters.cardFilter !== 'all' ?
                    ` matching "${filterIndicator.textContent}"` : '';
                tbody.innerHTML = `<tr><td colspan="14" class="text-center text-muted"><i class="bi bi-inbox"></i> No loans found${filterMsg}</td></tr>`;
            }

            renderPagination(data.pagination?.current_page || 1, data.pagination?.total_pages || 1);
        }

        function renderPagination(current, total) {
            paginationControls.innerHTML = '';
            paginationInfo.textContent = total > 0 ? `Page ${current} of ${total}` : '';

            if (total <= 1) return;

            const prev = document.createElement('button');
            prev.textContent = 'Prev';
            prev.className = 'btn btn-sm btn-outline-primary';
            prev.disabled = current === 1;
            prev.onclick = () => {
                currentPage--;
                loadData();
            };
            paginationControls.appendChild(prev);

            const next = document.createElement('button');
            next.textContent = 'Next';
            next.className = 'btn btn-sm btn-outline-primary';
            next.disabled = current === total;
            next.onclick = () => {
                currentPage++;
                loadData();
            };
            paginationControls.appendChild(next);
        }

        // Clickable Stat Cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                const filter = this.dataset.filter;
                document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));

                if (currentFilters.cardFilter === filter) {
                    currentFilters.cardFilter = 'all';
                } else {
                    this.classList.add('active');
                    currentFilters.cardFilter = filter;
                }

                currentPage = 1;
                loadData();
            });
        });

        // Filter listeners
        document.getElementById('searchInput').addEventListener('input', debounce((e) => {
            currentFilters.search = e.target.value.trim();
            currentPage = 1;
            loadData();
        }, 500));

        document.getElementById('statusFilter').addEventListener('change', (e) => {
            currentFilters.status = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('riskFilter').addEventListener('change', (e) => {
            currentFilters.risk = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('typeFilter').addEventListener('change', (e) => {
            currentFilters.type = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('rowsPerPage').addEventListener('change', (e) => {
            limit = parseInt(e.target.value);
            currentPage = 1;
            loadData();
        });

        document.getElementById('clearFilters').addEventListener('click', () => {
            currentFilters = {
                search: '',
                status: '',
                risk: '',
                type: '',
                cardFilter: 'all'
            };
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('riskFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
            currentPage = 1;
            loadData();
        });

        document.getElementById('reloadBtn').addEventListener('click', () => loadData());

        // Export PDF
        document.getElementById('exportPdfBtn').addEventListener('click', async function() {
            const passwordPrompt = await Swal.fire({
                title: 'Protect PDF Export',
                text: 'Enter a password before exporting this PDF.',
                input: 'password',
                inputLabel: 'PDF Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true,
                confirmButtonText: 'Export PDF',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter at least 6 characters.' : null
            });

            if (!passwordPrompt.isConfirmed) return;
            const pdfPassword = passwordPrompt.value;

            const params = new URLSearchParams({
                export: 'pdf',
                search: currentFilters.search,
                status: currentFilters.status,
                risk: currentFilters.risk,
                type: currentFilters.type,
                cardFilter: currentFilters.cardFilter,
                pdf_password: pdfPassword
            });

            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            btn.disabled = true;

            try {
                const url = `ajax_repayments.php?${params.toString()}`;
                
                const response = await fetch(url);
                if (!response.ok) throw new Error('Export failed');
                
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Export failed');
                }

                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `repayment_tracker_${new Date().toISOString().split('T')[0]}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                a.remove();

                Swal.fire({
                    icon: 'success',
                    title: 'PDF Exported',
                    text: 'Use your entered password to open the PDF.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Failed',
                    text: error.message
                });
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });

        // Export Excel/CSV
        document.getElementById('exportExcelBtn').addEventListener('click', async function() {
            const passwordPrompt = await Swal.fire({
                title: 'Protect Excel Export',
                text: 'Enter a password to encrypt this Excel/CSV export in a ZIP file.',
                input: 'password',
                inputLabel: 'Export Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true,
                confirmButtonText: 'Export Excel',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter at least 6 characters.' : null
            });

            if (!passwordPrompt.isConfirmed) return;
            const pdfPassword = passwordPrompt.value;

            const params = new URLSearchParams({
                export: 'csv',
                search: currentFilters.search,
                status: currentFilters.status,
                risk: currentFilters.risk,
                type: currentFilters.type,
                cardFilter: currentFilters.cardFilter,
                pdf_password: pdfPassword
            });

            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            btn.disabled = true;

            try {
                const url = `ajax_repayments.php?${params.toString()}`;
                
                const response = await fetch(url);
                if (!response.ok) throw new Error('Export failed');
                
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Export failed');
                }

                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                const extension = pdfPassword ? 'zip' : 'csv';
                a.download = `repayment_tracker_${new Date().toISOString().split('T')[0]}.${extension}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                a.remove();

                Swal.fire({
                    icon: 'success',
                    title: 'Excel Exported',
                    text: pdfPassword ? 'The ZIP file is password protected.' : 'File downloaded successfully.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Failed',
                    text: error.message
                });
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func(...args), wait);
            };
        }


        // INITIAL LOAD
        loadData();

        // ── Expose to global scope (needed by onclick= attributes) ──
        window.openEmailModal     = openEmailModal;
        window.sendEmailFromModal = sendEmailFromModal;
        window.loadEmailRecords   = loadEmailRecords;
        window.viewEmailContent   = viewEmailContent;
    });
</script>

<!-- ── Email Compose Modal ── -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:1rem;border:none;box-shadow:0 20px 25px -5px rgba(0,0,0,.15);">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#059669,#047857);border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-fill me-2"></i>Send Email Notification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="emailModal_loanId">

                <!-- Recipient Info -->
                <div class="p-3 mb-3 rounded-3" style="background:#f0fdf4;border:1px solid #d1fae5;">
                    <div class="row g-2">
                        <div class="col-md-4"><span class="text-muted small">Borrower:</span><br><strong id="emailModal_memberName"></strong></div>
                        <div class="col-md-4"><span class="text-muted small">Email:</span><br><strong id="emailModal_memberEmail" class="text-success"></strong></div>
                        <div class="col-md-4"><span class="text-muted small">Loan:</span><br><strong id="emailModal_loanCode"></strong></div>
                    </div>
                </div>

                <!-- Message Type -->
                <div class="mb-3">
                    <label class="fw-bold mb-1">Message Type</label>
                    <select id="emailModal_msgType" class="form-select" style="border:1.5px solid #e5e7eb;border-radius:.5rem;">
                        <option value="7_days_before">📅 Due Reminder (7 days)</option>
                        <option value="3_days_before">⏰ Due Reminder (3 days)</option>
                        <option value="due_today">🔔 Due Today Notice</option>
                        <option value="overdue">⚠️ Overdue Notice</option>
                        <option value="final_notice">🚨 Final Notice</option>
                    </select>
                </div>

                <!-- Editable Message -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="fw-bold">Message</label>
                        <small class="text-muted">Pwede i-edit ang message bago i-send</small>
                    </div>
                    <textarea id="emailModal_body" class="form-control" rows="8"
                        style="border:1.5px solid #e5e7eb;border-radius:.5rem;font-size:.9rem;line-height:1.6;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="border-top:2px solid #f3f4f6;">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="emailModal_sendBtn" class="btn btn-success fw-bold px-4" onclick="sendEmailFromModal()">
                    <i class="bi bi-send-fill me-1"></i>Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function runAutoNotifications() {
    const result = await Swal.fire({
        title: 'Auto Email Notifications',
        html: `<p class="text-muted small">Magpapadala ng automatic reminders sa lahat ng borrowers na may:</p>
               <ul class="text-start small ms-3">
                   <li>Due sa loob ng 7 days</li>
                   <li>Due sa loob ng 3 days</li>
                   <li>Due ngayon</li>
                   <li>Overdue na payments</li>
               </ul>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-send-fill me-1"></i>Run Now',
        confirmButtonColor: '#f59e0b',
        cancelButtonText: 'Cancel'
    });
    if (!result.isConfirmed) return;

    const btn = document.getElementById('autoNotifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div>Sending...';

    try {
        const res  = await fetch('/admin/Repayment-Tracker/auto_email_notifications.php?action=run', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Done!' : 'Error',
            html: `<p>${data.message}</p>${data.sent > 0 ? `<div class="mt-2 p-2 rounded bg-light small">${data.log?.slice(0,5).map(l=>`✅ ${l.email} (${l.type})`).join('<br>')}</div>` : ''}`,
            confirmButtonColor: '#059669'
        });
        if (data.sent > 0 && document.getElementById('emailRecordsSection').style.display !== 'none') loadEmailRecords();
    } catch(e) {
        Swal.fire({ icon:'error', title:'Error', text:e.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i>Auto Notify';
    }
}

function toggleEmailRecords() {
    const section = document.getElementById('emailRecordsSection');
    const btn     = document.getElementById('toggleEmailRecordsBtn');
    const visible = section.style.display !== 'none';
    section.style.display = visible ? 'none' : 'block';
    btn.innerHTML = visible
        ? '<i class="bi bi-envelope-check me-1"></i>Email Records'
        : '<i class="bi bi-x-circle me-1"></i>Hide Email Records';
    if (!visible) loadEmailRecords();
}

        // ── Real-Time Polling: repayments ──────────────────────────
        (function() {
            let lastPollTime = new Date().toISOString().replace('T', ' ').substring(0, 19);

            function pollNewRepayments() {
                fetch('/admin/inc/poll_new_records.php?module=repayments&since=' + encodeURIComponent(lastPollTime), {
                    credentials: 'same-origin', cache: 'no-store'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.count || data.count === 0) return;
                    lastPollTime = data.polled_at;
                    loadData();
                    data.records.forEach(r => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: '💰 New Payment Recorded!',
                            html: `<b>${r.member_name || 'Unknown'}</b><br><small class="text-muted">${r.loan_code || ''} ${r.amount ? '₱' + parseFloat(r.amount).toLocaleString('en-PH', {minimumFractionDigits:2}) : ''}</small>`,
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

            setInterval(pollNewRepayments, 30000);
        })();

</script>

<?php include(__DIR__ . '/../inc/footer.php'); ?>