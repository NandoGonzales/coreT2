<?php
// ===========================
// navbar.php - Profile with Edit & Approval System
// ===========================
if (session_status() == PHP_SESSION_NONE) session_start();

/**
 * Auto-detect base url up to "/admin"
 * Works even if current page is like:
 * /coreT2/admin/Loan-Portfolio-Risk-Management/page.php
 * base_url becomes:
 * /coreT2/admin
 */
if (!isset($base_url)) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos = strpos($script, '/admin');
    $base_url = ($pos !== false) ? substr($script, 0, $pos + 6) : '/admin';
}

/**
 * Your approval_action.php is located here:
 * /admin/User-Management-Role-Based-Access/approval_action.php
 */
$approval_action_url = $base_url . '/User-Management-Role-Based-Access/approval_action.php';

// Get current user info
$user_id     = $_SESSION['userdata']['user_id'] ?? 0;
$user_name   = $_SESSION['userdata']['full_name'] ?? 'User';
$user_role   = $_SESSION['userdata']['role'] ?? 'Member';
$user_email  = '';
$user_photo  = '';
$user_phone  = '';
$user_company = '';

if ($user_id && isset($conn)) {
    $stmt = $conn->prepare("SELECT email, profile_photo, phone, company FROM users WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $user_email = $res['email'] ?? '';
    $user_photo = $res['profile_photo'] ?? '';
    $user_phone = $res['phone'] ?? '';
    $user_company = $res['company'] ?? '';
    $stmt->close();
}

// Get initials
$name_parts = array_filter(explode(' ', trim($user_name)));
if (count($name_parts) > 0) {
    $first_initial = substr($name_parts[0], 0, 1);
    $last_initial = isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '';
    $initials = strtoupper($first_initial . $last_initial);
} else {
    $initials = 'U';
}

// Check if Super Admin
$isSuperAdmin = ($user_role === 'Super Admin');
?>

<style>
/* (KEEP YOUR CSS AS IS — I DID NOT CHANGE IT) */
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --sidebar-width: 18rem;
    }
    body { padding-top: 0; }
    .top-header {
        background: #fff;
        height: 4rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        z-index: 1020;
        transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .main-wrap.expanded .top-header { left: 0; }
    @media (max-width: 767px) { .top-header { left: 0 !important; } }
    .pill {
        font-size: 0.75rem;
        font-weight: 700;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
        color: #495057;
        white-space: nowrap;
        font-family: 'Monaco', 'Courier New', monospace;
    }
    .btn-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e9ecef;
        background: #fff;
        color: #6c757d;
        transition: all 0.3s ease;
        position: relative;
    }
    .btn-icon:hover {
        background: #f8f9fa;
        border-color: var(--brand-primary);
        color: var(--brand-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    .notif-dot {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: #ef4444;
        border: 2px solid #fff;
        animation: pulse-dot 2s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.8; }
    }
    .avatar {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 999px;
        overflow: hidden;
        border: 2px solid #e9ecef;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        color: var(--brand-primary);
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .user-dropdown-btn {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 1rem;
        padding: 0.5rem 0.75rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .user-dropdown-btn:hover {
        background: #f8f9fa;
        border-color: var(--brand-primary);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }
    .user-dropdown-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
    }
    .user-info { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.2; }
    .user-name { font-weight: 700; color: #1f2937; font-size: 0.9rem; }
    .user-role-badge { text-transform: uppercase; font-size: 0.625rem; font-weight: 600; color: #6b7280; letter-spacing: 0.05em; }
    .dropdown-menu {
        border: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border-radius: 1rem;
        padding: 0.5rem;
        min-width: 14rem;
        margin-top: 0.5rem !important;
    }
    .dropdown-item {
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
    }
    .dropdown-item:hover { background: #f3f4f6; transform: translateX(4px); }
    .dropdown-item i { font-size: 1.1rem; width: 1.25rem; }
    .dropdown-divider { margin: 0.5rem 0; opacity: 0.1; }
    .dropdown-item.text-danger:hover { background: #fee2e2; color: #dc2626 !important; }
    .vr { opacity: 0.2; }

    /* your remaining CSS untouched... */
</style>

<div class="main-wrap">
    <header class="top-header d-flex align-items-center justify-content-between px-3 px-sm-4">
        <div class="d-flex align-items-center gap-2"></div>

        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <span id="real-time-clock" class="pill d-none d-sm-inline">--:--:--</span>

            <div class="dropdown">
                <button class="btn btn-icon position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="notif-dot"></span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle text-primary"></i> New loan application</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-check-circle text-success"></i> Payment received</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-exclamation-triangle text-warning"></i> Compliance alert</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center small" href="#">View all notifications</a></li>
                </ul>
            </div>

            <div class="vr d-none d-sm-block"></div>

            <div class="dropdown">
                <button class="user-dropdown-btn" type="button" id="userDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <span class="avatar" id="navbarAvatar">
                        <?php if ($user_photo): ?>
                            <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </span>
                    <span class="user-info d-none d-md-flex">
                        <span class="user-name" id="navbarUsername"><?= htmlspecialchars($user_name) ?></span>
                        <span class="user-role-badge"><?= htmlspecialchars($user_role) ?></span>
                    </span>
                    <i class="bi bi-chevron-down text-secondary" style="font-size: 0.75rem;"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="bi bi-person-circle"></i> Edit Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="p-4 p-sm-4" style="padding-top: calc(4rem + 1.5rem) !important;"></main>
</div>

<!-- (KEEP YOUR MODALS HTML AS IS — unchanged) -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
    const userData = {
        userId: <?= (int)$user_id ?>,
        fullName: '<?= addslashes($user_name) ?>',
        email: '<?= addslashes($user_email) ?>',
        phone: '<?= addslashes($user_phone) ?>',
        company: '<?= addslashes($user_company) ?>',
        photo: '<?= addslashes($user_photo) ?>'
    };

    // Parse name
    const nameParts = userData.fullName.split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';

    // Real-time clock
    const clockEl = document.getElementById('real-time-clock');
    function pad(n) { return String(n).padStart(2, '0'); }
    function updateClock() {
        const d = new Date();
        if (clockEl) clockEl.textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Populate form when modal opens
    document.getElementById('profileModal')?.addEventListener('show.bs.modal', function() {
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value = lastName;
        document.getElementById('editEmail').value = userData.email;
        document.getElementById('editPhone').value = userData.phone;
    });

    // Photo Upload (unchanged)
    const photoInput = document.getElementById('profilePhotoInput');
    const photoUploadAvatar = document.getElementById('photoUploadAvatar');
    const btnRemovePhoto = document.getElementById('btnRemovePhoto');

    if (photoInput) {
        photoInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) { alert('Please select an image file'); return; }
            if (file.size > 5 * 1024 * 1024) { alert('Image size must be less than 5MB'); return; }

            photoUploadAvatar.classList.add('uploading');

            try {
                const formData = new FormData();
                formData.append('profile_photo', file);

                const response = await fetch('<?= $base_url ?>/inc/upload_profile_photo.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    const newPhotoUrl = result.photo_url + '?t=' + Date.now();

                    const editAvatarImg = document.getElementById('photoUploadAvatarImg');
                    const editAvatarInitials = document.getElementById('photoUploadAvatarInitials');

                    if (editAvatarImg) {
                        editAvatarImg.src = newPhotoUrl;
                    } else if (editAvatarInitials) {
                        editAvatarInitials.outerHTML = `<img src="${newPhotoUrl}" alt="Profile" id="photoUploadAvatarImg">`;
                    }

                    const navbarAvatar = document.getElementById('navbarAvatar');
                    if (navbarAvatar) navbarAvatar.innerHTML = `<img src="${newPhotoUrl}" alt="Profile">`;

                    btnRemovePhoto.style.display = 'inline-block';
                    alert('Profile photo updated successfully!');
                } else {
                    alert('Error: ' + (result.message || 'Failed to upload photo'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Error uploading photo. Please try again.');
            } finally {
                photoUploadAvatar.classList.remove('uploading');
                photoInput.value = '';
            }
        });
    }

    // Save Profile Changes
    document.getElementById('btnSaveProfileChanges')?.addEventListener('click', async function() {
        const newFirstName = document.getElementById('editFirstName').value.trim();
        const newLastName  = document.getElementById('editLastName').value.trim();
        const newEmail     = document.getElementById('editEmail').value.trim();
        const newPhone     = document.getElementById('editPhone').value.trim();

        if (!newFirstName || !newLastName || !newEmail) {
            alert('Please fill in all required fields (First Name, Last Name, Email)');
            return;
        }

        const fullName = `${newFirstName} ${newLastName}`;
        this.disabled = true;
        this.textContent = 'Saving...';

        try {
            if (isSuperAdmin) {
                // Super Admin: Direct update
                const response = await fetch('<?= $base_url ?>/inc/update_profile_direct.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userData.userId,
                        full_name: fullName,
                        email: newEmail,
                        phone: newPhone
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Profile updated successfully!');
                    userData.fullName = fullName;
                    userData.email = newEmail;
                    userData.phone = newPhone;
                    document.getElementById('navbarUsername').textContent = fullName;
                    bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + (result.msg || 'Failed to update profile'));
                }
            } else {
                // Staff/Admin: Send for approval
                const requestData = JSON.stringify({
                    full_name: fullName,
                    email: newEmail,
                    phone: newPhone
                });

                const fd = new FormData();
                fd.append('action', 'submit_request');
                fd.append('user_id', userData.userId);
                fd.append('request_type', 'profile_update');
                fd.append('request_data', requestData);

                // ✅ FIXED PATH HERE
                const response = await fetch('<?= $approval_action_url ?>', {
                    method: 'POST',
                    body: fd
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Profile changes sent to Super Admin for approval!');
                    bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                } else {
                    alert('Error: ' + (result.msg || 'Failed to send approval request'));
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving profile. Please try again.');
        } finally {
            this.disabled = false;
            this.textContent = isSuperAdmin ? 'Save Changes' : 'Send for Approval';
        }
    });
});
</script>
