<?php
$file = 'C:\\Users\\NARTEA\\Documents\\coreT2\\admin\\inc\\sidebar.php';
if (!file_exists($file)) {
    die("ERROR: File not found at $file\n");
}

$lines = file($file);
$new_lines = [];
$changes = 0;

$in_comp = false;
$in_user_mgmt_desktop = false;
$in_user_mgmt_mobile = false;
$desktop_submenu_found = false;
$mobile_submenu_found = false;

foreach ($lines as $index => $line) {
    // 1. Compliance (Desktop & Mobile)
    if (strpos($line, 'Compliance-Audith-Trail-System/compliance_logs.php') !== false) {
        echo "Found Compliance link at line " . ($index + 1) . "\n";
        $new_lines[] = "        <?php if (\$_SESSION['userdata']['role'] !== 'Staff'): ?>\n";
        $new_lines[] = $line;
        $in_comp = true;
        $changes++;
        continue;
    }
    if ($in_comp && strpos($line, '</a>') !== false) {
        echo "Found closing </a> for Compliance at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        $new_lines[] = "        <?php endif; ?>\n";
        $in_comp = false;
        $changes++;
        continue;
    }

    // 2. User Management Desktop
    if (strpos($line, 'id="userManagementSubmenu"') !== false) {
        echo "Found userManagementSubmenu ID at line " . ($index + 1) . "\n";
        $desktop_submenu_found = true;
    }
    if ($desktop_submenu_found && strpos($line, '<div class="submenu-items">') !== false) {
        echo "Found submenu-items for desktop at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        $new_lines[] = "                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n";
        $in_user_mgmt_desktop = true;
        $desktop_submenu_found = false;
        $changes++;
        continue;
    }
    if ($in_user_mgmt_desktop && strpos($line, 'permission_logs.php') !== false) {
        echo "Found permission_logs for desktop at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        continue;
    }
    if ($in_user_mgmt_desktop && strpos($line, '</a>') !== false && count($new_lines) > 0 && strpos($new_lines[count($new_lines) - 1], 'permission_logs.php') !== false) {
        echo "Found end of restricted desktop links at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        $new_lines[] = "                <?php endif; ?>\n";
        $in_user_mgmt_desktop = false;
        $changes++;
        continue;
    }

    // 3. User Management Mobile
    if (strpos($line, 'id="userManagementSubmenuMobile"') !== false) {
        echo "Found userManagementSubmenuMobile ID at line " . ($index + 1) . "\n";
        $mobile_submenu_found = true;
    }
    if ($mobile_submenu_found && strpos($line, '<div class="submenu-items">') !== false) {
        echo "Found submenu-items for mobile at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        $new_lines[] = "                <?php if (\$_SESSION['userdata']['role'] === 'Super Admin'): ?>\n";
        $in_user_mgmt_mobile = true;
        $mobile_submenu_found = false;
        $changes++;
        continue;
    }
    if ($in_user_mgmt_mobile && strpos($line, 'permission_logs.php') !== false) {
        echo "Found permission_logs for mobile at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        continue;
    }
    if ($in_user_mgmt_mobile && strpos($line, '</a>') !== false && count($new_lines) > 0 && strpos($new_lines[count($new_lines) - 1], 'permission_logs.php') !== false) {
        echo "Found end of restricted mobile links at line " . ($index + 1) . "\n";
        $new_lines[] = $line;
        $new_lines[] = "                <?php endif; ?>\n";
        $in_user_mgmt_mobile = false;
        $changes++;
        continue;
    }

    $new_lines[] = $line;
}

if ($changes > 0) {
    if (file_put_contents($file, implode('', $new_lines))) {
        echo "SUCCESS: $changes changes applied to $file\n";
    }
    else {
        echo "ERROR: Failed to write to $file\n";
    }
}
else {
    echo "WARNING: No changes were identified. Strings might not match.\n";
}
?>
