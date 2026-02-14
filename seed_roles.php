<?php
require_once(__DIR__ . '/initialize_coreT2.php');

function seed($conn)
{
    echo "Seeding user_roles...\n";
    $roles = [
        [1, 'Super Admin'],
        [2, 'Admin'],
        [3, 'Staff']
    ];
    foreach ($roles as $r) {
        $stmt = $conn->prepare("INSERT IGNORE INTO user_roles (role_id, role_name) VALUES (?, ?)");
        $stmt->bind_param("is", $r[0], $r[1]);
        $stmt->execute();
    }

    echo "Seeding role_permissions...\n";
    $perms = [
        [1, 'Users', 1, 1, 1, 1],
        [1, 'Role Permissions', 1, 1, 1, 1],
        [1, 'Permission Logs', 1, 1, 1, 1],
        [2, 'Users', 1, 0, 0, 0],
        [2, 'Role Permissions', 1, 0, 0, 0]
    ];
    foreach ($perms as $p) {
        $stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role_id, module_name, can_view, can_add, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiiii", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
        $stmt->execute();
    }
    echo "Done.\n";
}

seed($conn);
?>