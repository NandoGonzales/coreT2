<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$content = file_get_contents($file);

// 1. Wrap Compliance & Audit (Desktop)
$compliance_desktop_needle = '<a href="<?= $base_url ?>/Compliance-Audith-Trail-System/compliance_logs.php"
            class="btn menu-btn <?= get_active_class(\'Compliance\', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-shield-check"></i></span>
            <span>Compliance & Audit</span>
        </a>';

if (strpos($content, $compliance_desktop_needle) !== false) {
    $content = str_replace($compliance_desktop_needle, '<?php if ($_SESSION[\'userdata\'][\'role\'] !== \'Staff\'): ?>' . "\n        " . $compliance_desktop_needle . "\n        " . '<?php endif; ?>', $content);
}

// 2. Wrap User Management Submenu Items (Desktop)
$user_mgmt_desktop_needle = '<a href="<?= $base_url ?>/User-Management-Role-Based-Access/user_management.php"
                    class="submenu-link <?= is_active(\'user_management.php\', $current_path) ? \'active\' : \'\' ?>">
                    Users
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/role_permissions.php"
                    class="submenu-link <?= is_active(\'role_permissions.php\', $current_path) ? \'active\' : \'\' ?>">
                    Role Permissions
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/permission_logs.php"
                    class="submenu-link <?= is_active(\'permission_logs.php\', $current_path) ? \'active\' : \'\' ?>">
                    Permission Logs
                </a>';

if (strpos($content, $user_mgmt_desktop_needle) !== false) {
    $content = str_replace($user_mgmt_desktop_needle, '<?php if ($_SESSION[\'userdata\'][\'role\'] === \'Super Admin\'): ?>' . "\n                " . $user_mgmt_desktop_needle . "\n                " . '<?php endif; ?>', $content);
}

// 3. Wrap Compliance & Audit (Mobile) - Since it's the same HTML, str_replace might have already handled it if it's identical, 
// but often there's subtle differences. Let's check for the second one if it exists.
// Actually, in this file, the mobile section repeats the same links. 
// If str_replace found one, it might have missed the other if whitespace differs.

// Let's use a regex for a more robust approach for both occurrences.
$content = file_get_contents($file); // Reset to be sure

// Robust regex for compliance
$compliance_pattern = '/( +)<a href="<?= \$base_url \?>\/Compliance-Audith-Trail-System\/compliance_logs\.php".*?<span>Compliance & Audit<\/span>\s+<\/a>/s';
$content = preg_replace_callback($compliance_pattern, function ($m) {
    return $m[1] . '<?php if ($_SESSION[\'userdata\'][\'role\'] !== \'Staff\'): ?>' . "\n" . $m[0] . "\n" . $m[1] . '<?php endif; ?>';
}, $content);

// Robust regex for Desktop User Mgmt
$user_mgmt_pattern = '/(<div class="collapse submenu-container" id="userManagementSubmenu">.*?<div class="submenu-items">)\s+(<a href="<?= \$base_url \?>\/User-Management-Role-Based-Access\/user_management\.php".*?Permission Logs\s+<\/a>)/s';
$content = preg_replace($user_mgmt_pattern, '$1' . "\n                " . '<?php if ($_SESSION[\'userdata\'][\'role\'] === \'Super Admin\'): ?>' . "\n                " . '$2' . "\n                " . '<?php endif; ?>', $content);

// Robust regex for Mobile User Mgmt
$user_mgmt_mobile_pattern = '/(<div class="collapse submenu-container" id="userManagementSubmenuMobile">.*?<div class="submenu-items">)\s+(<a href="<?= \$base_url \?>\/User-Management-Role-Based-Access\/user_management\.php".*?Permission Logs\s+<\/a>)/s';
$content = preg_replace($user_mgmt_mobile_pattern, '$1' . "\n                " . '<?php if ($_SESSION[\'userdata\'][\'role\'] === \'Super Admin\'): ?>' . "\n                " . '$2' . "\n                " . '<?php endif; ?>', $content);

file_put_contents($file, $content);
echo "SUCCESS: Sidebar updated.";
?>
