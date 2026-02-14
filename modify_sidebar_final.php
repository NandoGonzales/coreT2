<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$content = file_get_contents($file);

// Replace Compliance & Audit (Desktop and Mobile)
$content = preg_replace(
    '/\s*<a href="\<\?= \$base_url \?\>\/Compliance-Audith-Trail-System\/compliance_logs\.php".*?<span>Compliance & Audit<\/span>\s*<\/a>/s',
    "\n        <?php if (\$_SESSION['userdata']['role'] !== 'Staff'): ?>\n$0\n        <?php endif; ?>\n",
    $content
);

// Replace User Management Submenu Items (Desktop)
$content = preg_replace(
    '/(id="userManagementSubmenu".*?<div class="submenu-items">)\s*(<a href="\<\?= \$base_url \?\>\/User-Management-Role-Based-Access\/user_management\.php".*?Permission Logs\s+<\/a>)/s',
    "$1\n                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n$2\n                <?php endif; ?>",
    $content
);

// Replace User Management Submenu Items (Mobile)
$content = preg_replace(
    '/(id="userManagementSubmenuMobile".*?<div class="submenu-items">)\s*(<a href="\<\?= \$base_url \?\>\/User-Management-Role-Based-Access\/user_management\.php".*?Permission Logs\s+<\/a>)/s',
    "$1\n                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n$2\n                <?php endif; ?>",
    $content
);

if (file_put_contents($file, $content)) {
    echo "SUCCESS: Sidebar updated.";
}
else {
    echo "ERROR: Failed to update sidebar.";
}
?>
