// ============================================================================
// HR4 INTEGRATION CODE
// ============================================================================
const linkModal = new bootstrap.Modal(document.getElementById('linkModal'));
let allHR4Employees = [];

function loadHR4Employees() {
  const tbody = document.getElementById('hr4TableBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading HR4 data...</td></tr>';
  
  fetch('hr4_employee_list.php')
    .then(r => r.json())
    .then(resp => {
      if (resp.status === 'success') {
        allHR4Employees = resp.data;
        renderHR4Table();
      } else {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${resp.msg}</td></tr>`;
      }
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to connect to directory.</td></tr>';
    });
}

function renderHR4Table() {
  const tbody = document.getElementById('hr4TableBody');
  if (!tbody) return;
  const search = document.getElementById('hr4SearchInput').value.toLowerCase();
  
  tbody.innerHTML = '';
  
  const filtered = allHR4Employees.filter(e => {
    return !search || [e.full_name, e.hr4_employee_id, e.department, e.job_title].some(v => v && v.toLowerCase().includes(search));
  });

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No matching employees found.</td></tr>';
    return;
  }

  filtered.forEach(e => {
    const linkStatus = e.linked_user_id 
      ? `<span class="badge bg-success" title="Linked to ${escapeHtml(e.linked_username)}"><i class="fas fa-check-circle me-1"></i>Linked</span>`
      : `<span class="badge bg-secondary"><i class="fas fa-unlink me-1"></i>Unlinked</span>`;

    let actions = '';
    if (e.linked_user_id) {
      actions = `<button class="btn btn-sm btn-outline-danger unlinkBtn" data-id="${e.hr4_employee_id}" title="Unlink Account"><i class="fas fa-unlink"></i></button>`;
    } else {
      actions = `
        <button class="btn btn-sm btn-outline-primary linkBtn" data-id="${e.hr4_employee_id}" data-name="${escapeHtml(e.full_name)}" title="Link to Existing User"><i class="fas fa-link"></i></button>
        <button class="btn btn-sm btn-outline-success createFromEmpBtn" data-id="${e.hr4_employee_id}" data-name="${escapeHtml(e.full_name)}" data-email="${escapeHtml(e.email)}" title="Create User from Employee"><i class="fas fa-user-plus"></i></button>
      `;
    }

    tbody.innerHTML += `
      <tr>
        <td><code class="small">${escapeHtml(e.hr4_employee_id)}</code></td>
        <td class="fw-bold">${escapeHtml(e.full_name)}</td>
        <td><small>${escapeHtml(e.department)}</small></td>
        <td><small>${escapeHtml(e.job_title)}</small></td>
        <td><small>${escapeHtml(e.work_location)}</small></td>
        <td class="text-center"><span class="badge bg-light text-dark border">${escapeHtml(e.hr_status)}</span></td>
        <td class="text-center">${linkStatus}</td>
        <td class="text-center">${actions}</td>
      </tr>
    `;
  });
}

// Sync Button
if (document.getElementById('syncHR4Btn')) {
  document.getElementById('syncHR4Btn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    
    fetch('sync_hr4_employees.php')
      .then(r => r.json())
      .then(resp => {
        if (resp.status === 'success') {
          Swal.fire('Synced!', resp.msg, 'success');
          loadHR4Employees();
        } else {
          Swal.fire('Sync Failed', resp.msg, 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'Connection failed', 'error'))
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      });
  });
}

// HR4 Table Interactions
const hr4Tbody = document.getElementById('hr4TableBody');
if (hr4Tbody) {
  hr4Tbody.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;

    const empId = btn.dataset.id;
    const empName = btn.dataset.name;

    if (btn.classList.contains('unlinkBtn')) {
      Swal.fire({
        title: 'Unlink Employee?',
        text: 'This will remove the connection between CoreT2 user and HR4 employee record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Unlink'
      }).then(res => {
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('action', 'unlink');
          fd.append('hr4_employee_id', empId);
          fetch('hr4_link_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
              if (resp.status === 'success') {
                Swal.fire('Unlinked', resp.msg, 'success');
                loadHR4Employees();
              } else {
                Swal.fire('Error', resp.msg, 'error');
              }
            });
        }
      });
    }

    if (btn.classList.contains('linkBtn')) {
      document.getElementById('link_hr4_id').value = empId;
      document.getElementById('link_emp_name').value = empName;
      document.getElementById('link_emp_id_box').textContent = "HR4 ID: " + empId;
      
      // Load unlinked users
      const select = document.getElementById('link_user_select');
      select.innerHTML = '<option value="">-- Choose User --</option>';
      
      // Get unlinked users from allUsers
      const linkedUserIds = allHR4Employees.map(e => parseInt(e.linked_user_id)).filter(id => !isNaN(id));
      const unlinkedUsers = allUsers.filter(u => !linkedUserIds.includes(parseInt(u.user_id)));
      
      unlinkedUsers.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.user_id;
        opt.textContent = `${u.full_name} (${u.username})`;
        select.appendChild(opt);
      });
      
      linkModal.show();
    }

    if (btn.classList.contains('createFromEmpBtn')) {
      const email = btn.dataset.email;
      const firstName = empName.split(' ')[0] || '';
      
      currentUserId = null;
      document.getElementById('userForm').reset();
      document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Create User from Employee';
      
      document.getElementById('full_name').value = empName;
      document.getElementById('email').value = (email && email !== 'null') ? email : '';
      document.getElementById('username').value = firstName.toLowerCase() + empId.replace('EMP-','');
      
      document.getElementById('password').setAttribute('required','required');
      document.getElementById('passwordRequired').style.display = 'inline';
      document.getElementById('passwordHelp').style.display = 'none';
      
      userModal.show();
    }
  });
}

const confirmLinkBtn = document.getElementById('confirmLinkBtn');
if (confirmLinkBtn) {
  confirmLinkBtn.addEventListener('click', function() {
    const userId = document.getElementById('link_user_select').value;
    const hr4Id = document.getElementById('link_hr4_id').value;
    
    if (!userId) {
      Swal.fire('Selection Required', 'Please select a user to link.', 'warning');
      return;
    }
    
    const fd = new FormData();
    fd.append('action', 'link');
    fd.append('user_id', userId);
    fd.append('hr4_employee_id', hr4Id);
    
    fetch('hr4_link_action.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(resp => {
        if (resp.status === 'success') {
          Swal.fire('Linked!', resp.msg, 'success');
          linkModal.hide();
          loadHR4Employees();
        } else {
          Swal.fire('Error', resp.msg, 'error');
        }
      });
  });
}

if (document.getElementById('hr4SearchInput')) {
  document.getElementById('hr4SearchInput').addEventListener('input', renderHR4Table);
}

// Initialize HR4 on page load
loadHR4Employees();
