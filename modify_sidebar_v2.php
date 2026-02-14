<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$lines = file($file);
$new_lines = [];

$in_desktop_submenu = false;
$in_mobile_submenu = false;

foreach ($lines as $line) {
    // 1. Compliance & Audit (Desktop and Mobile)
    if (strpos($line, 'Compliance-Audith-Trail-System/compliance_logs.php') !== false) {
        $indent = str_repeat(' ', strpos($line, '<a'));
        $new_lines[] = "        <?php if (\$_SESSION['userdata']['role'] !== 'Staff'): ?>\n";
        $new_lines[] = $line;
    }
    else if (strpos($line, '<span>Compliance & Audit</span>') !== false) {
        $new_lines[] = $line;
    }
    else if (strpos($line, '</a>') !== false && count($new_lines) > 0 && strpos($new_lines[count($new_lines) - 3], 'Staff') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "        <?php endif; ?>\n";
    }
    // 2. User Management Submenu Items
    else if (strpos($line, 'id="userManagementSubmenu"') !== false) {
        $new_lines[] = $line;
        $in_desktop_submenu = true;
    }
    else if (strpos($line, 'id="userManagementSubmenuMobile"') !== false) {
        $new_lines[] = $line;
        $in_mobile_submenu = true;
    }
    else if (($in_desktop_submenu || $in_mobile_submenu) && strpos($line, 'user_management.php') !== false) {
        $new_lines[] = "                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n";
        $new_lines[] = $line;
    }
    else if (($in_desktop_submenu || $in_mobile_submenu) && strpos($line, 'permission_logs.php') !== false) {
        $new_lines[] = $line;
    }
    else if (($in_desktop_submenu || $in_mobile_submenu) && strpos($line, '</a>') !== false && count($new_lines) > 1 && strpos($new_lines[count($new_lines) - 2], 'permission_logs.php') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "                <?php endif; ?>\n";
        if ($in_desktop_submenu)
            $in_desktop_submenu = false;
        if ($in_mobile_submenu)
            $in_mobile_submenu = false;
    }
    else {
        $new_lines[] = $line;
    }
}

file_put_contents($file, implode('', $new_lines));
echo "SUCCESS: Sidebar visibility updated.";
?>
