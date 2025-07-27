<?php
/**
 * lib/alliance_actions.php
 *
 * Handles all server-side logic for alliance management.
 * v5: Unified all alliance actions into a single, comprehensive script.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }
require_once "db_config.php";

$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';
$redirect_url = '/alliance.php'; // Default redirect URL

mysqli_begin_transaction($link);
try {
    // Fetch user's current data for permissions and validation
    $sql_user_info = "SELECT u.credits, u.alliance_id, u.alliance_role_id, ar.* FROM users u LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ? FOR UPDATE";
    $stmt_info = mysqli_prepare($link, $sql_user_info);
    mysqli_stmt_bind_param($stmt_info, "i", $user_id);
    mysqli_stmt_execute($stmt_info);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    mysqli_stmt_close($stmt_info);

    if (!$user_info) { throw new Exception("Could not retrieve user data."); }

    // --- ACTION ROUTING ---

    if ($action === 'create') {
        $redirect_url = '/create_alliance.php';
        if ($user_info['alliance_id']) { throw new Exception("You are already in an alliance."); }
        if ($user_info['credits'] < 1000000) { throw new Exception("You do not have the 1,000,000 Credits required to found an alliance."); }
        
        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        $description = trim($_POST['description']);
        if (empty($name) || empty($tag)) { throw new Exception("Alliance name and tag are required."); }

        // Deduct cost and create alliance
        mysqli_query($link, "UPDATE users SET credits = credits - 1000000 WHERE id = $user_id");
        $sql = "INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name, $tag, $description, $user_id);
        mysqli_stmt_execute($stmt);
        $alliance_id = mysqli_insert_id($link);

        // Create default roles
        $default_roles = [['Supreme Commander',0,0,1,1,1,1],['Recruit',1,1,0,0,0,0]];
        $sql_role = "INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_role = mysqli_prepare($link, $sql_role);
        $sc_role_id = null;
        foreach ($default_roles as $role) {
            mysqli_stmt_bind_param($stmt_role, "isiiiiii", $alliance_id, $role[0], $role[1], $role[2], $role[3], $role[4], $role[5], $role[6]);
            mysqli_stmt_execute($stmt_role);
            if ($role[0] === 'Supreme Commander') {
                $sc_role_id = mysqli_insert_id($link);
            }
        }
        
        // Assign creator as Supreme Commander
        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $alliance_id, $sc_role_id, $user_id);
        mysqli_stmt_execute($stmt_update);
        
        $_SESSION['alliance_message'] = "Alliance created successfully!";
        $redirect_url = '/alliance.php';

    } else if ($action === 'apply_to_alliance') {
        if ($user_info['alliance_id']) { throw new Exception("You are already in an alliance."); }
        $alliance_id = (int)$_POST['alliance_id'];

        $sql_count = "SELECT COUNT(*) as member_count FROM users WHERE alliance_id = ?";
        $stmt_count = mysqli_prepare($link, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $alliance_id);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        if ($count_result['member_count'] >= 100) { throw new Exception("This alliance is full and cannot accept new members."); }

        $sql_check = "SELECT id FROM alliance_applications WHERE user_id = ? AND alliance_id = ? AND status = 'pending'";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) { throw new Exception("You have already applied to this alliance."); }

        $sql = "INSERT INTO alliance_applications (user_id, alliance_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Application sent successfully.";

    } else if ($action === 'approve_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!$user_info['can_approve_membership']) { throw new Exception("You do not have permission to approve members."); }
        $application_id = (int)$_POST['application_id'];

        $sql_app = "SELECT user_id, alliance_id FROM alliance_applications WHERE id = ? AND status = 'pending'";
        $stmt_app = mysqli_prepare($link, $sql_app);
        mysqli_stmt_bind_param($stmt_app, "i", $application_id);
        mysqli_stmt_execute($stmt_app);
        $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_app));
        if (!$app) { throw new Exception("Application not found."); }
        if ($app['alliance_id'] != $user_info['alliance_id']) { throw new Exception("Application does not belong to your alliance."); }
        
        $sql_count = "SELECT COUNT(*) as member_count FROM users WHERE alliance_id = ?";
        $stmt_count = mysqli_prepare($link, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $app['alliance_id']);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        if ($count_result['member_count'] >= 100) { throw new Exception("Your alliance is full. You cannot approve this member."); }

        $sql_role = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Recruit'";
        $stmt_role = mysqli_prepare($link, $sql_role);
        mysqli_stmt_bind_param($stmt_role, "i", $app['alliance_id']);
        mysqli_stmt_execute($stmt_role);
        $role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_role));
        if (!$role) { throw new Exception("Default 'Recruit' role not found for this alliance."); }

        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $app['alliance_id'], $role['id'], $app['user_id']);
        mysqli_stmt_execute($stmt_update);

        $sql_delete_app = "DELETE FROM alliance_applications WHERE id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete_app);
        mysqli_stmt_bind_param($stmt_delete, "i", $application_id);
        mysqli_stmt_execute($stmt_delete);

        $_SESSION['alliance_message'] = "Member approved.";

    } else if ($action === 'deny_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!$user_info['can_approve_membership']) { throw new Exception("You do not have permission to deny applications."); }
        $application_id = (int)$_POST['application_id'];

        $sql_delete_app = "DELETE FROM alliance_applications WHERE id = ? AND alliance_id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete_app);
        mysqli_stmt_bind_param($stmt_delete, "ii", $application_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        
        $_SESSION['alliance_message'] = "Application denied.";

    } else if ($action === 'leave') {
        if (!$user_info['alliance_id']) { throw new Exception("You are not in an alliance."); }
        
        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        if ($alliance['leader_id'] == $user_id) {
            throw new Exception("Leaders cannot leave an alliance. You must first transfer leadership or disband the alliance.");
        }

        $sql_leave = "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE id = ?";
        $stmt_leave = mysqli_prepare($link, $sql_leave);
        mysqli_stmt_bind_param($stmt_leave, "i", $user_id);
        mysqli_stmt_execute($stmt_leave);
        $_SESSION['alliance_message'] = "You have left the alliance.";

    } else if ($action === 'disband') {
        $alliance_id = (int)$_POST['alliance_id'];
        $redirect_url = '/edit_alliance.php';

        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $alliance_id);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        if (!$alliance || $alliance['leader_id'] != $user_id) {
            throw new Exception("You do not have permission to disband this alliance.");
        }

        $sql_remove_members = "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = ?";
        $stmt_remove = mysqli_prepare($link, $sql_remove_members);
        mysqli_stmt_bind_param($stmt_remove, "i", $alliance_id);
        mysqli_stmt_execute($stmt_remove);

        $sql_delete_roles = "DELETE FROM alliance_roles WHERE alliance_id = ?";
        $stmt_del_roles = mysqli_prepare($link, $sql_delete_roles);
        mysqli_stmt_bind_param($stmt_del_roles, "i", $alliance_id);
        mysqli_stmt_execute($stmt_del_roles);
        
        $sql_delete_apps = "DELETE FROM alliance_applications WHERE alliance_id = ?";
        $stmt_del_apps = mysqli_prepare($link, $sql_delete_apps);
        mysqli_stmt_bind_param($stmt_del_apps, "i", $alliance_id);
        mysqli_stmt_execute($stmt_del_apps);

        $sql_delete_alliance = "DELETE FROM alliances WHERE id = ?";
        $stmt_del_alliance = mysqli_prepare($link, $sql_delete_alliance);
        mysqli_stmt_bind_param($stmt_del_alliance, "i", $alliance_id);
        mysqli_stmt_execute($stmt_del_alliance);
        
        $_SESSION['alliance_message'] = "Alliance has been permanently disbanded.";
        $redirect_url = '/alliance.php';

    } else if ($action === 'create_role') {
        $redirect_url = '/alliance_roles.php';
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission to create roles."); }
        
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        if (empty($name) || $order <= 0) { throw new Exception("Invalid role name or order."); }

        $sql = "INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_info['alliance_id'], $name, $order);
        if (!mysqli_stmt_execute($stmt)) { throw new Exception("Database error: Could not create the role."); }
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' created successfully.";

    } else if ($action === 'update_role') {
        $redirect_url = '/alliance_roles.php';
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission to update roles."); }
        
        $role_id = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        $permissions = $_POST['permissions'] ?? [];

        $can_edit_profile = isset($permissions['can_edit_profile']) ? 1 : 0;
        $can_approve_membership = isset($permissions['can_approve_membership']) ? 1 : 0;
        $can_kick_members = isset($permissions['can_kick_members']) ? 1 : 0;
        $can_manage_roles = isset($permissions['can_manage_roles']) ? 1 : 0;

        $sql = "UPDATE alliance_roles SET name = ?, `order` = ?, can_edit_profile = ?, can_approve_membership = ?, can_kick_members = ?, can_manage_roles = ? WHERE id = ? AND alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "siiiiiii", $name, $order, $can_edit_profile, $can_approve_membership, $can_kick_members, $can_manage_roles, $role_id, $user_info['alliance_id']);
        if (!mysqli_stmt_execute($stmt)) { throw new Exception("Database error: Could not update the role."); }
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' updated successfully.";
    }

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
}

header("location: " . $redirect_url);
exit;
?>