<?php
$file = 'c:/Users/NARTEA/Documents/coreT2/admin/inc/sidebar.php';
$lines = file($file);
$new_lines = [];

$in_comp = false;
$in_user_mgmt_desktop = false;
$in_user_mgmt_mobile = false;
$desktop_submenu_found = false;
$mobile_submenu_found = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    // 1. Compliance (Desktop & Mobile)
    if (strpos($line, 'Compliance-Audith-Trail-System/compliance_logs.php') !== false) {
        $new_lines[] = "        <?php if (\$_SESSION['userdata']['role'] !== 'Staff'): ?>\n";
        $new_lines[] = $line;
        $in_comp = true;
        continue;
    }
    if ($in_comp && strpos($line, '</a>') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "        <?php endif; ?>\n";
        $in_comp = false;
        continue;
    }

    // 2. User Management Desktop
    if (strpos($line, 'id="userManagementSubmenu"') !== false) {
        $desktop_submenu_found = true;
    }
    if ($desktop_submenu_found && strpos($line, '<div class="submenu-items">') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n";
        $in_user_mgmt_desktop = true;
        $desktop_submenu_found = false;
        continue;
    }
    // We want to hide Users, Role Perms, Perm Logs but KEEP Approval Requests.
    // So we wrap the first 3 links.
    if ($in_user_mgmt_desktop && strpos($line, 'permission_logs.php') !== false) {
        $new_lines[] = $line;
        continue;
    }
    if ($in_user_mgmt_desktop && strpos($line, '</a>') !== false && strpos($new_lines[count($new_lines) - 1], 'permission_logs.php') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "                <?php endif; ?>\n";
        $in_user_mgmt_desktop = false;
        continue;
    }

    // 3. User Management Mobile
    if (strpos($line, 'id="userManagementSubmenuMobile"') !== false) {
        $mobile_submenu_found = true;
    }
    if ($mobile_submenu_found && strpos($line, '<div class="submenu-items">') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n";
        $in_user_mgmt_mobile = true;
        $mobile_submenu_found = false;
        continue;
    }
    if ($in_user_mgmt_mobile && strpos($line, 'permission_logs.php') !== false) {
        $new_lines[] = $line;
        continue;
    }
    if ($in_user_mgmt_mobile && strpos($line, '</a>') !== false && strpos($new_lines[count($new_lines) - 1], 'permission_logs.php') !== false) {
        $new_lines[] = $line;
        $new_lines[] = "                <?php endif; ?>\n";
        $in_user_mgmt_mobile = false;
        continue;
    }

    $new_lines[] = $line;
}

file_put_contents($file, implode('', $new_lines));
echo "SUCCESS: Sidebar updated line-by-line.";
?>
