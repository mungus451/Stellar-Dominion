<?php
// lib/alliance_actions.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

mysqli_begin_transaction($link);
try {
    // Fetch user's current alliance and role info for permissions
    $sql_user_info = "SELECT u.alliance_id, u.alliance_role_id, ar.* FROM users u LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ? FOR UPDATE";
    $stmt_info = mysqli_prepare($link, $sql_user_info);
    mysqli_stmt_bind_param($stmt_info, "i", $user_id);
    mysqli_stmt_execute($stmt_info);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    mysqli_stmt_close($stmt_info);

    // --- ACTION: CREATE ALLIANCE ---
    if ($action === 'create') {
        // ... (existing code to create the alliance in `alliances` table)
        // AFTER creating the alliance and getting $alliance_id:
        
        // Create default roles
        $default_roles = [
            // name, order, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles
            ['Supreme Commander', 0, 0, 1, 1, 1, 1],
            ['Officer', 1, 1, 1, 1, 1, 0],
            ['Member', 2, 1, 0, 0, 0, 0],
            ['Recruit', 3, 1, 0, 0, 0, 0]
        ];
        $sql_role = "INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_role = mysqli_prepare($link, $sql_role);
        $sc_role_id = null;
        foreach($default_roles as $role) {
            mysqli_stmt_bind_param($stmt_role, "isiiiiii", $alliance_id, $role[0], $role[1], $role[2], $role[3], $role[4], $role[5], $role[6]);
            mysqli_stmt_execute($stmt_role);
            if ($role[0] === 'Supreme Commander') { $sc_role_id = mysqli_insert_id($link); }
        }
        mysqli_stmt_close($stmt_role);
        
        // Update the creator's role to Supreme Commander
        $sql_update_leader = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update_leader = mysqli_prepare($link, $sql_update_leader);
        mysqli_stmt_bind_param($stmt_update_leader, "iii", $alliance_id, $sc_role_id, $user_id);
        mysqli_stmt_execute($stmt_update_leader);
        mysqli_stmt_close($stmt_update_leader);
        // ...
    }

    // --- ACTION: APPLY TO ALLIANCE ---
    else if ($action === 'apply_to_alliance') {
        if ($user_info['alliance_id']) { throw new Exception("You are already in an alliance."); }
        $alliance_id = (int)$_POST['alliance_id'];
        $sql = "INSERT INTO alliance_applications (user_id, alliance_id, status) VALUES (?, ?, 'pending')";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Your application has been sent.";
    }

    // --- ACTION: APPROVE APPLICATION ---
    else if ($action === 'approve_application') {
        if (!$user_info['can_approve_membership']) { throw new Exception("You do not have permission."); }
        $application_id = (int)$_POST['application_id'];
        
        // Get applicant ID and the alliance's default 'Recruit' role ID
        $sql = "SELECT app.user_id, r.id as recruit_role_id FROM alliance_applications app JOIN alliance_roles r ON app.alliance_id = r.alliance_id WHERE app.id = ? AND r.name = 'Recruit' AND app.alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $application_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt);
        $app_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($app_data) {
            // Update user's alliance and role
            $sql_update = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iii", $user_info['alliance_id'], $app_data['recruit_role_id'], $app_data['user_id']);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            // Update application status
            $sql_app = "UPDATE alliance_applications SET status = 'approved' WHERE id = ?";
            $stmt_app = mysqli_prepare($link, $sql_app);
            mysqli_stmt_bind_param($stmt_app, "i", $application_id);
            mysqli_stmt_execute($stmt_app);
            mysqli_stmt_close($stmt_app);
        }
    }
    
    // --- ACTION: CREATE ROLE ---
    else if ($action === 'create_role') {
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission."); }
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        if (empty($name) || $order <= 0) { throw new Exception("Invalid role name or order."); }

        $sql = "INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_info['alliance_id'], $name, $order);
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Role created successfully.";
        header("location: /alliance_roles.php");
        exit;
    }

    // --- ACTION: UPDATE ROLE ---
    else if ($action === 'update_role') {
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission."); }
        $role_id = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        $permissions = $_POST['permissions'] ?? [];

        $sql = "UPDATE alliance_roles SET name = ?, `order` = ?, can_edit_profile = ?, can_approve_membership = ?, can_kick_members = ?, can_manage_roles = ? WHERE id = ? AND alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "siiiiiii", 
            $name, $order,
            $permissions['can_edit_profile'] ?? 0,
            $permissions['can_approve_membership'] ?? 0,
            $permissions['can_kick_members'] ?? 0,
            $permissions['can_manage_roles'] ?? 0,
            $role_id, $user_info['alliance_id']
        );
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Role updated successfully.";
        header("location: /alliance_roles.php");
        exit;
    }
    
    // Additional actions like deny_application, delete_role, assign_role, kick would go here...

    mysqli_commit($link);
    header("location: /alliance.php"); // Default redirect

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
    header("location: /alliance.php"); // Default error redirect
}
exit;
?>