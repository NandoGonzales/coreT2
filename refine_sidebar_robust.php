<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$content = file_get_contents($file);

// Ensure we handle both \n and \r\n
$content = str_replace("\r\n", "\n", $content);

// 1. Compliance & Audit (Desktop & Mobile)
// Pattern: Match the anchor tag for compliance logs
$comp_pattern = '/( *)<a href="<?= \$base_url \?>\/Compliance-Audith-Trail-System\/compliance_logs\.php".*?<span>Compliance & Audit<\/span>\s*<\/a>/s';
$content = preg_replace_callback($comp_pattern, function($m) {
    $indent = $m[1];
    return $indent . '<?php if ($_SESSION[\'userdata\'][\'role\'] !== \'Staff\'): ?>' . "\n" . 
           $m[0] . "\n" . 
           $indent . '<?php endif; ?>';
}, $content);

// 2. User Management Submenu (Desktop)
// Pattern: Match the submenu items block within #userManagementSubmenu
$user_mgmt_pattern = '/( *)<div class="collapse submenu-container" id="userManagementSubmenu">.*?<div class="submenu-items">\s*(.*?Permission Logs\s*<\/a>)/s';
$content = preg_replace_callback($user_mgmt_pattern, function($m) {
    $indent = $m[1];
    $inner_indent = "                "; // Standard indent for submenu items
    return $m[1] . '<div class="collapse submenu-container" id="userManagementSubmenu">' . "\n" .
           $indent . '    <div class="submenu-items">' . "\n" .
           $inner_indent . '<?php if ($_SESSION[\'userdata\'][\'role'] === \'Super Admin\'): ?>' . "\n" .
           $m[2] . "\n" .
           $inner_indent . '<?php endif; ?>';
}, $content);

// 3. User Management Submenu (Mobile)
// Pattern: Match the submenu items block within #userManagementSubmenuMobile
$user_mgmt_mobile_pattern = '/( *)<div class="collapse submenu-container" id="userManagementSubmenuMobile">.*?<div class="submenu-items">\s*(.*?Permission Logs\s*<\/a>)/s';
$content = preg_replace_callback($user_mgmt_mobile_pattern, function($m) {
    $indent = $m[1];
    $inner_indent = "                "; // Standard indent for submenu items
    return $m[1] . '<div class="collapse submenu-container" id="userManagementSubmenuMobile">' . "\n" .
           $indent . '    <div class="submenu-items">' . "\n" .
           $inner_indent . '<?php if ($_SESSION[\'userdata\'][\'role'] === \'Super Admin\'): ?>' . "\n" .
           $m[2] . "\n" .
           $inner_indent . '<?php endif; ?>';
}, $content);

file_put_contents($file, $content);
echo "SUCCESS: Sidebar visibility refined.";
?>
