<?php
// ===========================
// navbar.php - Modern Profile Modal with View/Edit Modes
// ===========================
if (session_status() == PHP_SESSION_NONE) session_start();

// Base URL (should match your project structure)
$base_url = $base_url ?? '/admin';

// Get current user info
$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_name = $_SESSION['userdata']['full_name'] ?? 'User';
$user_role = $_SESSION['userdata']['role'] ?? 'Member';
$user_email = '';
$user_photo = '';

if ($user_id && isset($conn)) {
    $stmt = $conn->prepare("SELECT email, profile_photo FROM users WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $user_email = $res['email'] ?? '';
    $user_photo = $res['profile_photo'] ?? '';
    $stmt->close();
}

// Get initials for avatar
$name_parts = array_filter(explode(' ', trim($user_name)));
if (count($name_parts) > 0) {
    $first_initial = substr($name_parts[0], 0, 1);
    $last_initial = isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '';
    $initials = strtoupper($first_initial . $last_initial);
} else {
    $initials = 'U';
}
?>

<!-- NAVBAR STYLES -->
<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --sidebar-width: 18rem;
    }

    body {
        padding-top: 0;
    }

    /* Header */
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

    .main-wrap.expanded .top-header {
        left: 0;
    }

    @media (max-width: 767px) {
        .top-header {
            left: 0 !important;
        }
    }

    /* Clock Pill */
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

    /* Icon Buttons */
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

    /* Notification Dot */
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
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.8;
        }
    }

    /* Avatar */
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

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* User Dropdown Button */
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

    .user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.2;
    }

    .user-name {
        font-weight: 700;
        color: #1f2937;
        font-size: 0.9rem;
    }

    .user-role-badge {
        text-transform: uppercase;
        font-size: 0.625rem;
        font-weight: 600;
        color: #6b7280;
        letter-spacing: 0.05em;
    }

    /* Dropdown Menu */
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

    .dropdown-item:hover {
        background: #f3f4f6;
        transform: translateX(4px);
    }

    .dropdown-item i {
        font-size: 1.1rem;
        width: 1.25rem;
    }

    .dropdown-divider {
        margin: 0.5rem 0;
        opacity: 0.1;
    }

    .dropdown-item.text-danger:hover {
        background: #fee2e2;
        color: #dc2626 !important;
    }

    /* Responsive Vertical Divider */
    .vr {
        opacity: 0.2;
    }

    /* ===========================
       MODERN PROFILE MODAL
       =========================== */
    
    .profile-modal .modal-dialog {
        max-width: 380px;
    }

    .profile-modal .modal-content {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .profile-modal .modal-header {
        background: #fff;
        border: none;
        padding: 1.25rem 1.5rem 0;
        position: relative;
    }

    .profile-modal .modal-title {
        font-weight: 700;
        font-size: 1.125rem;
        color: #1f2937;
    }

    .profile-modal .btn-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        opacity: 0.4;
        transition: opacity 0.2s;
    }

    .profile-modal .btn-close:hover {
        opacity: 1;
    }

    .profile-modal .modal-body {
        padding: 1.5rem;
        background: #fff;
    }

    .profile-modal .modal-footer {
        border: none;
        padding: 1rem 1.5rem 1.5rem;
        background: #fff;
    }

    /* Profile View Mode */
    #profileViewMode {
        text-align: center;
    }

    .profile-avatar-wrapper {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
    }

    .profile-avatar-large {
        width: 6rem;
        height: 6rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #6b7280;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        border: 3px solid #e5e7eb;
        position: relative;
        overflow: hidden;
        margin: 0 auto;
    }

    .profile-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-name-display {
        font-weight: 700;
        font-size: 1.25rem;
        color: #1f2937;
        margin-bottom: 0.25rem;
        margin-top: 0.5rem;
    }

    .profile-role-badge {
        background: #3b82f6;
        color: #fff;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        display: inline-block;
        letter-spacing: 0.025em;
    }

    .profile-location {
        color: #6b7280;
        font-size: 0.875rem;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
    }

    .profile-location i {
        font-size: 0.875rem;
    }

    .profile-timezone {
        background: #f9fafb;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin-top: 1.25rem;
        text-align: left;
    }

    .profile-timezone-label {
        font-size: 0.75rem;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        margin-bottom: 0.25rem;
    }

    .profile-timezone-value {
        font-size: 0.875rem;
        color: #1f2937;
        font-weight: 600;
    }

    .btn-edit-profile {
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.9375rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #1f2937;
        transition: all 0.2s;
    }

    .btn-edit-profile:hover {
        background: #f9fafb;
        border-color: #d1d5db;
    }

    /* Profile Edit Mode */
    #profileEditMode {
        display: none;
    }

    .photo-upload-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #f3f4f6;
        margin-bottom: 1.25rem;
    }

    .photo-upload-avatar {
        width: 4rem;
        height: 4rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #6b7280;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 2px solid #e5e7eb;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
    }

    .photo-upload-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-upload-controls {
        flex: 1;
    }

    .photo-upload-btns {
        display: flex;
        gap: 0.5rem;
    }

    .btn-photo-action {
        padding: 0.5rem 0.875rem;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-upload-photo {
        background: #3b82f6;
        color: #fff;
    }

    .btn-upload-photo:hover {
        background: #2563eb;
    }

    .btn-remove-photo {
        background: transparent;
        color: #6b7280;
        border: 1px solid #e5e7eb;
    }

    .btn-remove-photo:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .photo-upload-hint {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.5rem;
    }

    .form-section {
        margin-bottom: 1.25rem;
    }

    .form-section-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }

    .form-group-modern {
        margin-bottom: 1rem;
    }

    .form-group-modern:last-child {
        margin-bottom: 0;
    }

    .form-label-modern {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.375rem;
        display: block;
    }

    .form-control-modern {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        color: #1f2937;
        transition: all 0.2s;
        background: #fff;
    }

    .form-control-modern:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control-modern:disabled {
        background: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
    }

    .input-group-modern {
        position: relative;
    }

    .input-group-icon {
        position: absolute;
        right: 0.875rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.2s;
    }

    .input-group-icon:hover {
        color: #6b7280;
    }

    .form-control-modern.has-icon {
        padding-right: 2.5rem;
    }

    .account-details-section {
        background: #f9fafb;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .account-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
    }

    .account-detail-row:not(:last-child) {
        border-bottom: 1px solid #e5e7eb;
    }

    .account-detail-label {
        font-size: 0.8125rem;
        color: #6b7280;
        font-weight: 500;
    }

    .account-detail-value {
        font-size: 0.8125rem;
        color: #1f2937;
        font-weight: 600;
    }

    .delete-account-section {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 0.5rem;
        padding: 0.875rem 1rem;
        margin-top: 1.25rem;
    }

    .delete-account-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #991b1b;
        margin-bottom: 0.25rem;
    }

    .delete-account-text {
        font-size: 0.75rem;
        color: #7f1d1d;
        margin: 0;
    }

    .modal-footer-buttons {
        display: flex;
        gap: 0.75rem;
        width: 100%;
    }

    .modal-footer-buttons button {
        flex: 1;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.9375rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-cancel {
        background: #fff;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .btn-cancel:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .btn-save-changes {
        background: #3b82f6;
        color: #fff;
    }

    .btn-save-changes:hover {
        background: #2563eb;
    }

    .btn-save-changes:disabled {
        background: #93c5fd;
        cursor: not-allowed;
    }

    /* Loading Spinner */
    .upload-spinner {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
    }

    .uploading .upload-spinner {
        display: block;
    }

    .uploading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 999px;
    }

    /* Logout Modal */
    .logout-modal .modal-content {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    }

    .logout-modal .modal-header {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border: none;
        padding: 1.5rem;
    }

    .logout-modal .modal-title {
        color: #dc2626;
        font-weight: 700;
    }

    @keyframes pulse-logout {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    .pulse-icon {
        animation: pulse-logout 1.5s infinite;
    }
</style>

<!-- MAIN WRAPPER -->
<div class="main-wrap">
    <!-- HEADER -->
    <header class="top-header d-flex align-items-center justify-content-between px-3 px-sm-4">
        <div class="d-flex align-items-center gap-2">
            <!-- Empty space for alignment -->
        </div>

        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <!-- Real-time Clock -->
            <span id="real-time-clock" class="pill d-none d-sm-inline">--:--:--</span>

            <!-- Notification Bell -->
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

            <!-- User Dropdown -->
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
                            <i class="bi bi-person-circle"></i> My Profile
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

    <!-- CONTENT AREA -->
    <main class="p-4 p-sm-4" style="padding-top: calc(4rem + 1.5rem) !important;">
        <!-- Your page content goes here -->
    </main>
</div>

<!-- PROFILE MODAL -->
<div class="modal fade profile-modal" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- VIEW MODE -->
                <div id="profileViewMode">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar-large" id="profileAvatarView">
                            <?php if ($user_photo): ?>
                                <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" id="profileAvatarViewImg">
                            <?php else: ?>
                                <span id="profileAvatarViewInitials"><?= $initials ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-name-display" id="profileNameDisplay"><?= htmlspecialchars($user_name) ?></div>
                    <span class="profile-role-badge"><?= htmlspecialchars($user_role) ?></span>
                    
                    <div class="profile-location">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span>Quezon City, Philippines</span>
                    </div>

                    <div class="profile-timezone">
                        <div class="profile-timezone-label">Local time</div>
                        <div class="profile-timezone-value">06:10 (UTC+08:00) Philippine Time</div>
                    </div>
                </div>

                <!-- EDIT MODE -->
                <div id="profileEditMode">
                    <!-- Photo Upload -->
                    <div class="photo-upload-section">
                        <div class="photo-upload-avatar" id="photoUploadAvatar">
                            <?php if ($user_photo): ?>
                                <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" id="photoUploadAvatarImg">
                            <?php else: ?>
                                <span id="photoUploadAvatarInitials"><?= $initials ?></span>
                            <?php endif; ?>
                            <div class="upload-spinner">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Uploading...</span>
                                </div>
                            </div>
                        </div>
                        <div class="photo-upload-controls">
                            <div class="photo-upload-btns">
                                <label for="profilePhotoInput" class="btn-photo-action btn-upload-photo">
                                    Upload photo
                                </label>
                                <button type="button" class="btn-photo-action btn-remove-photo" id="btnRemovePhoto" <?= !$user_photo ? 'style="display:none;"' : '' ?>>
                                    Remove photo
                                </button>
                            </div>
                            <div class="photo-upload-hint">JPG, PNG or WEBP (max. 5MB)</div>
                        </div>
                    </div>
                    <input type="file" class="d-none" id="profilePhotoInput" accept="image/*">

                    <!-- Full Name -->
                    <div class="form-section">
                        <div class="form-section-title">Full name</div>
                        <div class="form-group-modern">
                            <label class="form-label-modern">First</label>
                            <input type="text" class="form-control-modern" id="editFirstName" placeholder="Stijn">
                        </div>
                        <div class="form-group-modern">
                            <label class="form-label-modern">Last</label>
                            <input type="text" class="form-control-modern" id="editLastName" placeholder="Hendrikse">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-section">
                        <div class="form-section-title">Email</div>
                        <div class="form-group-modern">
                            <input type="email" class="form-control-modern" id="editEmail" placeholder="stijn@dataui.org" value="<?= htmlspecialchars($user_email) ?>">
                        </div>
                    </div>

                    <!-- Company & Position -->
                    <div class="form-section">
                        <div class="form-section-title">Company</div>
                        <div class="form-group-modern">
                            <input type="text" class="form-control-modern" id="editCompany" placeholder="DataUI" disabled>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Position</div>
                        <div class="form-group-modern">
                            <input type="text" class="form-control-modern" value="<?= htmlspecialchars($user_role) ?>" disabled>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="form-section">
                        <div class="form-section-title">Phone number</div>
                        <div class="form-group-modern">
                            <input type="tel" class="form-control-modern" id="editPhone" placeholder="+1 (555) 000-0000" disabled>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="form-section">
                        <div class="form-section-title">Account details</div>
                        <div class="account-details-section">
                            <div class="account-detail-row">
                                <span class="account-detail-label">Log in email address</span>
                                <span class="account-detail-value"><?= htmlspecialchars($user_email) ?></span>
                            </div>
                            <div class="account-detail-row">
                                <span class="account-detail-label">Password</span>
                                <span class="account-detail-value">
                                    <div class="input-group-modern" style="display: inline-block; position: relative;">
                                        <input type="password" class="form-control-modern has-icon" id="editPassword" value="••••••••••" style="width: 140px; display: inline-block; padding: 0.25rem 2rem 0.25rem 0.5rem; font-size: 0.8125rem;">
                                        <i class="bi bi-eye input-group-icon" id="togglePassword" style="top: 50%; right: 0.5rem;"></i>
                                    </div>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Account -->
                    <div class="delete-account-section">
                        <div class="delete-account-title">Delete your account?</div>
                        <p class="delete-account-text">Remove all account and team assets. This action cannot be undone.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="footerViewMode" style="width: 100%;">
                    <button type="button" class="btn-edit-profile" id="btnEditProfile">Edit profile</button>
                </div>
                <div id="footerEditMode" style="display: none; width: 100%;">
                    <div class="modal-footer-buttons">
                        <button type="button" class="btn-cancel" id="btnCancelEdit">Close</button>
                        <button type="button" class="btn-save-changes" id="btnSaveChanges">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGOUT MODAL -->
<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Logout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-box-arrow-right display-3 text-danger mb-3 pulse-icon"></i>
                <h5>Are you sure you want to log out?</h5>
                <p class="text-muted mb-0">You will need to sign in again to access your account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= $base_url ?>/logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Real-time clock
        const clockEl = document.getElementById('real-time-clock');
        
        function pad(n) { 
            return String(n).padStart(2, '0'); 
        }
        
        function updateClock() {
            const d = new Date();
            const hours = pad(d.getHours());
            const minutes = pad(d.getMinutes());
            const seconds = pad(d.getSeconds());
            
            if (clockEl) {
                clockEl.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }
        
        updateClock();
        setInterval(updateClock, 1000);

        // Close dropdowns when modals open
        const profileModal = document.getElementById('profileModal');
        const logoutModal = document.getElementById('logoutModal');
        
        [profileModal, logoutModal].forEach(modalEl => {
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function() {
                    const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                    openDropdowns.forEach(dropdown => {
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                        if (bsDropdown) {
                            bsDropdown.hide();
                        }
                    });
                });
            }
        });

        // Profile Modal Mode Switching
        const profileViewMode = document.getElementById('profileViewMode');
        const profileEditMode = document.getElementById('profileEditMode');
        const footerViewMode = document.getElementById('footerViewMode');
        const footerEditMode = document.getElementById('footerEditMode');
        const btnEditProfile = document.getElementById('btnEditProfile');
        const btnCancelEdit = document.getElementById('btnCancelEdit');
        const btnSaveChanges = document.getElementById('btnSaveChanges');
        const editFirstName = document.getElementById('editFirstName');
        const editLastName = document.getElementById('editLastName');
        const editEmail = document.getElementById('editEmail');
        const editPassword = document.getElementById('editPassword');
        const togglePassword = document.getElementById('togglePassword');

        // Current user data
        const userData = {
            fullName: '<?= addslashes($user_name) ?>',
            email: '<?= addslashes($user_email) ?>',
            photo: '<?= addslashes($user_photo) ?>'
        };

        // Parse name into first and last
        const nameParts = userData.fullName.split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        // Switch to Edit Mode
        btnEditProfile.addEventListener('click', function() {
            profileViewMode.style.display = 'none';
            profileEditMode.style.display = 'block';
            footerViewMode.style.display = 'none';
            footerEditMode.style.display = 'block';

            // Populate fields
            editFirstName.value = firstName;
            editLastName.value = lastName;
            editEmail.value = userData.email;
        });

        // Switch to View Mode
        btnCancelEdit.addEventListener('click', function() {
            profileViewMode.style.display = 'block';
            profileEditMode.style.display = 'none';
            footerViewMode.style.display = 'block';
            footerEditMode.style.display = 'none';
        });

        // Reset to View Mode when modal closes
        profileModal.addEventListener('hidden.bs.modal', function() {
            profileViewMode.style.display = 'block';
            profileEditMode.style.display = 'none';
            footerViewMode.style.display = 'block';
            footerEditMode.style.display = 'none';
        });

        // Toggle Password Visibility
        togglePassword.addEventListener('click', function() {
            const type = editPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            editPassword.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });

        // Save Changes
        btnSaveChanges.addEventListener('click', async function() {
            const newFirstName = editFirstName.value.trim();
            const newLastName = editLastName.value.trim();
            const newEmail = editEmail.value.trim();
            const newPassword = editPassword.value.trim();

            if (!newFirstName || !newLastName || !newEmail) {
                alert('Please fill in all required fields');
                return;
            }

            const fullName = `${newFirstName} ${newLastName}`;

            // Disable button
            btnSaveChanges.disabled = true;
            btnSaveChanges.textContent = 'Saving...';

            try {
                const response = await fetch('<?= $base_url ?>/inc/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: fullName,
                        email: newEmail,
                        password: newPassword !== '••••••••••' ? newPassword : ''
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Update displayed data
                    userData.fullName = fullName;
                    userData.email = newEmail;

                    document.getElementById('profileNameDisplay').textContent = fullName;
                    document.getElementById('navbarUsername').textContent = fullName;

                    alert('Profile updated successfully!');
                    
                    // Switch back to view mode
                    btnCancelEdit.click();
                } else {
                    alert('Error: ' + (result.msg || 'Failed to update profile'));
                }
            } catch (error) {
                console.error('Update error:', error);
                alert('Error updating profile. Please try again.');
            } finally {
                btnSaveChanges.disabled = false;
                btnSaveChanges.textContent = 'Save Changes';
            }
        });

        // Photo Upload Handler
        const photoInput = document.getElementById('profilePhotoInput');
        const photoUploadAvatar = document.getElementById('photoUploadAvatar');
        const btnRemovePhoto = document.getElementById('btnRemovePhoto');
        
        if (photoInput) {
            photoInput.addEventListener('change', async function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size must be less than 5MB');
                    return;
                }

                // Show loading
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
                        
                        // Update edit mode avatar
                        const editAvatarImg = document.getElementById('photoUploadAvatarImg');
                        const editAvatarInitials = document.getElementById('photoUploadAvatarInitials');
                        
                        if (editAvatarImg) {
                            editAvatarImg.src = newPhotoUrl;
                        } else if (editAvatarInitials) {
                            editAvatarInitials.outerHTML = `<img src="${newPhotoUrl}" alt="Profile" id="photoUploadAvatarImg">`;
                        }

                        // Update view mode avatar
                        const viewAvatarImg = document.getElementById('profileAvatarViewImg');
                        const viewAvatarInitials = document.getElementById('profileAvatarViewInitials');
                        
                        if (viewAvatarImg) {
                            viewAvatarImg.src = newPhotoUrl;
                        } else if (viewAvatarInitials) {
                            viewAvatarInitials.outerHTML = `<img src="${newPhotoUrl}" alt="Profile" id="profileAvatarViewImg">`;
                        }

                        // Update navbar avatar
                        const navbarAvatar = document.getElementById('navbarAvatar');
                        if (navbarAvatar) {
                            navbarAvatar.innerHTML = `<img src="${newPhotoUrl}" alt="Profile">`;
                        }

                        // Show remove button
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

        // Remove Photo
        btnRemovePhoto.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove your profile photo?')) {
                // In a real implementation, you would call an API to remove the photo
                alert('Photo removal feature - implement API call here');
            }
        });
    });
</script>