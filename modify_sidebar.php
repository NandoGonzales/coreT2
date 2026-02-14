<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$content = file_get_contents($file);

// 1. Hide Compliance & Audit from Staff
// There are two occurrences (Desktop and Mobile)
$compliance_pattern = '/( +)<a href="<?= \$base_url \?>\/Compliance-Audith-Trail-System\/compliance_logs\.php"\n( +)class="btn menu-btn <?= get_active_class\(\'Compliance\', \$current_path\) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">\n( +)<span class="icon-box"><i class="bi bi-shield-check"><\/i><\/span>\n( +)<span>Compliance & Audit<\/span>\n( +)<\/a>/';

$compliance_replacement = '        <?php if ($_SESSION[\'userdata\'][\'role\'] !== \'Staff\'): ?>
$0
        <?php endif; ?>';

$content = preg_replace($compliance_pattern, $compliance_replacement, $content);

// 2. Hide Users, Permissions, Logs from Admin (Desktop)
$user_mgmt_desktop_pattern = '/(<div class="collapse submenu-container" id="userManagementSubmenu">\s+<div class="submenu-items">\s+)<a href="<?= \$base_url \?>\/User-Management-Role-Based-Access\/user_management\.php"/';
$user_mgmt_desktop_replacement = '$1<?php if ($_SESSION[\'userdata\'][\'role\'] === \'Super Admin\'): ?>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/user_management.php"';

$content = preg_replace($user_mgmt_desktop_pattern, $user_mgmt_desktop_replacement, $content);

// Add closing endif for desktop
$logs_desktop_pattern = '/(<a href="<?= \$base_url \?>\/User-Management-Role-Based-Access\/permission_logs\.php"\s+class="submenu-link <?= is_active\(\'permission_logs\.php\', \$current_path\) \? \'active\' : \'\' ?>">\s+Permission Logs\s+<\/a>)/';
$logs_desktop_replacement = '$1
                <?php endif; ?>';
$content = preg_replace($logs_desktop_pattern, $logs_desktop_replacement, $content, 1); // Only first occurrence (desktop)

file_put_contents($file, $content);
echo "SUCCESS: Sidebar visibility updated.";
?>
