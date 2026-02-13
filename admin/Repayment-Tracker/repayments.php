<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');

if (session_status() === PHP_SESSION_NONE) session_start();

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<style>
/* (KEEP YOUR CSS EXACTLY AS IS — I DID NOT CHANGE IT) */
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

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
                        <button id="reloadBtn" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Reload
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-title">Total Loans</div>
                        <div id="card_total_loans" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to view all</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="active">
                        <div class="stat-card-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-title">Active Loans</div>
                        <div id="card_active_loans" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="overdue">
                        <div class="stat-card-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-title">Overdue Loans</div>
                        <div id="card_overdue_loans" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="at_risk">
                        <div class="stat-card-icon"><i class="bi bi-shield-exclamation"></i></div>
                        <div class="stat-title">At Risk Loans</div>
                        <div id="card_at_risk_loans" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                    </div>
                </div>
            </div>

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
                            <option value="Defaulted">Defaulted</option>
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

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Loan Status Distribution</h6>
                        <div class="chart-container"><canvas id="loanStatusChart"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Risk Level Breakdown</h6>
                        <div class="chart-container"><canvas id="riskChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span id="tableTitle">Loan Portfolio Table</span>
                        <span id="filterIndicator" class="badge bg-info ms-2" style="display:none;"></span>
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

<div class="modal fade" id="viewLoanModal" tabindex="-1" aria-labelledby="viewLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;">
                <h5 class="modal-title" id="viewLoanModalLabel">
                    <i class="bi bi-eye me-2"></i> Loan Details & Payment History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <div id="loanDetailsContent">
                    <p class="text-center text-muted">Loading loan details...</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top:2px solid #f3f4f6;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let loanStatusChart, riskChart;
    let currentPage = 1, limit = 10;
    let currentFilters = { search:'', status:'', risk:'', type:'', cardFilter:'all' };
    let allLoans = [];

    const tbody = document.getElementById('loanRiskTbody');
    const paginationControls = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');
    const filterIndicator = document.getElementById('filterIndicator');

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ✅ SAFETY: If server returns HTML/Notice, still try to parse JSON
    async function safeJson(response) {
        const text = await response.text();
        const start = text.indexOf('{');
        const startArr = text.indexOf('[');
        let idx = start;
        if (startArr !== -1 && (startArr < start || start === -1)) idx = startArr;

        if (idx === -1) throw new Error('Server did not return JSON. It returned HTML/text.');
        return JSON.parse(text.slice(idx));
    }

    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        toast.style.cssText = 'min-width:300px;margin-bottom:10px;';
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function getDaysUntilDue(dueDate) {
        if (!dueDate || dueDate === '-') return 999;
        const due = new Date(dueDate);
        const today = new Date();
        const diffTime = due - today;
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    // ✅ FIX: send_ai_message.php needs loan_id + action=send
    async function sendEmailNotification(loanId, memberName, memberEmail) {
        if (!memberEmail) {
            showToast('❌ No email address for ' + escapeHtml(memberName), 'error');
            return;
        }

        if (!confirm(`Send AI email reminder to ${memberName} (${memberEmail})?`)) return;

        try {
            const response = await fetch('send_ai_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ loan_id: Number(loanId), action: 'send' })
            });

            const result = await safeJson(response);

            if (result.success) {
                showToast('✅ ' + (result.message || 'Message sent.'), 'success');
                const button = document.querySelector(`button[data-notify-loan="${loanId}"]`);
                if (button) {
                    button.innerHTML = '✅ Sent';
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-success');
                    button.disabled = true;
                }
            } else {
                showToast('❌ ' + (result.message || 'Failed.'), 'error');
            }
        } catch (error) {
            showToast('❌ Error: ' + error.message, 'error');
        }
    }

    function handleViewButtonClick(e) {
        const button = e.target.closest('.view-loan-btn');
        if (!button) return;

        const loan_id = button.dataset.id;
        const content = document.getElementById('loanDetailsContent');
        content.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p class="mt-2">Loading...</p></div>';

        const url = `../Loan-Portfolio-Risk-Management/loan_crud.php?loan_id=${loan_id}`;
        fetch(url)
            .then(async r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}: ${await r.text()}`);
                return r.json();
            })
            .then(res => {
                if (res.success && res.loan) {
                    const l = res.loan;
                    let html = `
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><strong>Loan ID:</strong> ${l.loan_id}</div>
                            <div class="col-md-6"><strong>Member:</strong> ${escapeHtml(l.member_name)} (ID: ${l.member_id})</div>
                            <div class="col-md-6"><strong>Type:</strong> ${escapeHtml(l.loan_type)}</div>
                            <div class="col-md-6"><strong>Status:</strong> <span class="badge bg-${
                                l.status === 'Active' ? 'success' :
                                l.status === 'Completed' ? 'info' :
                                l.status === 'Delinquent' ? 'danger' : 'warning'
                            }">${l.status}</span></div>
                            <div class="col-md-6"><strong>Principal:</strong> ₱${Number(l.principal_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
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
                                <td>₱${Number(s.amount_due).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                                <td>₱${Number(s.amount_paid).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                                <td>₱${balance.toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                                <td>${s.payment_date || '-'}</td>
                                <td><span class="badge ${badge}">${s.status}</span></td>
                            </tr>`;
                        });

                        html += '</tbody></table></div>';
                    } else {
                        html += '<p class="text-muted">No payment schedules available</p>';
                    }

                    content.innerHTML = html;
                    new bootstrap.Modal(document.getElementById('viewLoanModal')).show();
                } else {
                    content.innerHTML = '<p class="text-center text-danger">Failed to load loan details.</p>';
                }
            })
            .catch(err => {
                content.innerHTML = `<p class="text-center text-danger">Error: ${escapeHtml(err.message)}</p>`;
            });
    }

    function handleNotifyButtonClick(e) {
        const button = e.target.closest('.notify-btn');
        if (!button) return;

        const loanId = button.dataset.notifyLoan;
        const memberName = button.dataset.memberName;
        const memberEmail = button.dataset.memberEmail;

        sendEmailNotification(loanId, memberName, memberEmail);
    }

    function updateFilterIndicator() {
        const filterTexts = {
            all: '',
            active: 'Active Loans Only',
            overdue: 'Overdue Loans Only',
            at_risk: 'At Risk Loans Only'
        };

        if (currentFilters.cardFilter !== 'all') {
            filterIndicator.textContent = filterTexts[currentFilters.cardFilter];
            filterIndicator.style.display = 'inline-block';
            filterIndicator.className = 'badge ms-2 ' + (
                currentFilters.cardFilter === 'active' ? 'bg-success' :
                currentFilters.cardFilter === 'overdue' ? 'bg-warning text-dark' : 'bg-danger'
            );
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

    function renderPagination(current, total) {
        paginationControls.innerHTML = '';
        paginationInfo.textContent = total > 0 ? `Page ${current} of ${total}` : '';
        if (total <= 1) return;

        const prev = document.createElement('button');
        prev.textContent = 'Prev';
        prev.className = 'btn btn-sm btn-outline-primary';
        prev.disabled = current === 1;
        prev.onclick = () => { currentPage--; loadData(); };
        paginationControls.appendChild(prev);

        const next = document.createElement('button');
        next.textContent = 'Next';
        next.className = 'btn btn-sm btn-outline-primary';
        next.disabled = current === total;
        next.onclick = () => { currentPage++; loadData(); };
        paginationControls.appendChild(next);
    }

    function renderChartsAndTable(data) {
        document.getElementById('card_total_loans').textContent = data.summary?.total_loans || 0;
        document.getElementById('card_active_loans').textContent = data.summary?.active_loans || 0;
        document.getElementById('card_overdue_loans').textContent = data.summary?.overdue_loans || 0;
        document.getElementById('card_at_risk_loans').textContent = data.summary?.at_risk_loans || 0;

        if (loanStatusChart) loanStatusChart.destroy();
        if (data.loan_status?.labels?.length) {
            const ctx = document.getElementById('loanStatusChart');
            loanStatusChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: data.loan_status.labels, datasets: [{ label:'Number of Loans', data:data.loan_status.values }] },
                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
            });
        }

        if (riskChart) riskChart.destroy();
        if (data.risk_breakdown?.labels?.length) {
            const ctx = document.getElementById('riskChart');
            riskChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: data.risk_breakdown.labels, datasets: [{ data: data.risk_breakdown.values, borderWidth:2, borderColor:'#fff' }] },
                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } }, cutout:'60%' }
            });
        }

        const start = (currentPage - 1) * limit + 1;
        const end = Math.min(currentPage * limit, data.pagination?.total_records || 0);
        const total = data.pagination?.total_records || 0;
        document.getElementById('recordCount').textContent = total > 0 ? `Showing ${start}-${end} of ${total} records` : 'No records found';

        tbody.innerHTML = '';
        if (data.loans?.length) {
            data.loans.forEach(l => {
                const riskBadge = l.risk_level === 'High' ? 'bg-danger' : (l.risk_level === 'Medium' ? 'bg-warning text-dark' : 'bg-success');
                const statusBadge = l.status === 'Active' ? 'bg-success' : (l.status === 'Delinquent' ? 'bg-danger' : (l.status === 'Completed' ? 'bg-info' : 'bg-warning'));

                const daysUntilDue = getDaysUntilDue(l.next_due);
                const showNotifyButton = daysUntilDue <= 7 && l.email;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${l.loan_id}</td>
                    <td>${escapeHtml(l.member_name || 'N/A')}</td>
                    <td>${escapeHtml(l.loan_type || '-')}</td>
                    <td>₱${Number(l.principal_amount || 0).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                    <td>${l.interest_rate ?? '-'}%</td>
                    <td>${l.loan_term ?? '-'} mo</td>
                    <td>${l.start_date ?? '-'}</td>
                    <td>${l.end_date ?? '-'}</td>
                    <td><span class="badge ${statusBadge}">${escapeHtml(l.status || '-')}</span></td>
                    <td><span class="badge ${(Number(l.overdue_count) > 0) ? 'bg-danger' : 'bg-secondary'}">${l.overdue_count || 0}</span></td>
                    <td><span class="badge ${riskBadge}">${escapeHtml(l.risk_level || '-')}</span></td>
                    <td>${l.next_due || '-'}</td>
                    <td class="text-center">
                        ${showNotifyButton ? `
                        <button class="btn btn-sm btn-primary notify-btn"
                            data-notify-loan="${l.loan_id}"
                            data-member-name="${escapeHtml(l.member_name)}"
                            data-member-email="${escapeHtml(l.email)}"
                            title="Send AI email reminder">📧</button>
                        ` : '<small class="text-muted">-</small>'}
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info view-loan-btn" data-id="${l.loan_id}" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            tbody.removeEventListener('click', handleViewButtonClick);
            tbody.addEventListener('click', handleViewButtonClick);

            tbody.removeEventListener('click', handleNotifyButtonClick);
            tbody.addEventListener('click', handleNotifyButtonClick);

        } else {
            tbody.innerHTML = `<tr><td colspan="14" class="text-center text-muted">No loans found</td></tr>`;
        }

        renderPagination(data.pagination?.current_page || 1, data.pagination?.total_pages || 1);
    }

    function showError(message) {
        tbody.innerHTML = `<tr><td colspan="14" class="text-center text-danger">${escapeHtml(message)}</td></tr>`;
    }

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

        fetch(`ajax_repayments.php?${params.toString()}`)
            .then(async r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}: ${await r.text()}`);
                return safeJson(r);
            })
            .then(data => {
                if (data.error) throw new Error(data.message || 'Server error');
                allLoans = data.all_loans || data.loans || [];
                renderChartsAndTable(data);
                populateLoanTypes(data.loan_types || []);
                updateFilterIndicator();
            })
            .catch(err => showError('Failed to fetch data: ' + err.message));
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    }

    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', function () {
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
        currentFilters = { search:'', status:'', risk:'', type:'', cardFilter:'all' };
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('riskFilter').value = '';
        document.getElementById('typeFilter').value = '';
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        currentPage = 1;
        loadData();
    });

    document.getElementById('reloadBtn').addEventListener('click', loadData);

    // ✅ Keep your Export PDF code as-is if you want. (No change needed for the JSON issue)

    loadData();
});
</script>

<?php include(__DIR__ . '/../inc/footer.php'); ?>
