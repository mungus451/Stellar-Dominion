<?php
/**
 * lib/alliance_actions.php
 *
 * Handles all server-side logic for alliance management.
 * v2: Corrected bind_param error and restructured for better error handling.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }
require_once "db_config.php";

$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';
$redirect_url = '/alliance.php'; // Default redirect URL

mysqli_begin_transaction($link);
try {
    // Fetch user's current alliance and role info for permissions
    $sql_user_info = "SELECT u.alliance_id, u.alliance_role_id, ar.* FROM users u LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ? FOR UPDATE";
    $stmt_info = mysqli_prepare($link, $sql_user_info);
    mysqli_stmt_bind_param($stmt_info, "i", $user_id);
    mysqli_stmt_execute($stmt_info);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    mysqli_stmt_close($stmt_info);

    if (!$user_info) { throw new Exception("Could not retrieve user data."); }

    // --- ACTION: CREATE ROLE ---
    if ($action === 'create_role') {
        $redirect_url = '/alliance_roles.php';
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission to create roles."); }
        
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        if (empty($name) || $order <= 0) { throw new Exception("Invalid role name or order."); }

        $sql = "INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_info['alliance_id'], $name, $order);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Database error: Could not create the role.");
        }
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' created successfully.";
    }

    // --- ACTION: UPDATE ROLE ---
    else if ($action === 'update_role') {
        $redirect_url = '/alliance_roles.php';
        if (!$user_info['can_manage_roles']) { throw new Exception("You do not have permission to update roles."); }
        
        $role_id = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        $permissions = $_POST['permissions'] ?? [];

        // FIX: Store permission values in variables before binding
        $can_edit_profile = isset($permissions['can_edit_profile']) ? 1 : 0;
        $can_approve_membership = isset($permissions['can_approve_membership']) ? 1 : 0;
        $can_kick_members = isset($permissions['can_kick_members']) ? 1 : 0;
        $can_manage_roles = isset($permissions['can_manage_roles']) ? 1 : 0;

        $sql = "UPDATE alliance_roles SET name = ?, `order` = ?, can_edit_profile = ?, can_approve_membership = ?, can_kick_members = ?, can_manage_roles = ? WHERE id = ? AND alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "siiiiiii", 
            $name, $order,
            $can_edit_profile, $can_approve_membership, $can_kick_members, $can_manage_roles,
            $role_id, $user_info['alliance_id']
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Database error: Could not update the role.");
        }
        mysqli_stmt_close($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' updated successfully.";
    }

    // Add other actions like 'delete_role', 'apply_to_alliance', etc. here as `else if` blocks...


    // If we get here, all operations in the 'try' block were successful.
    mysqli_commit($link);

} catch (Exception $e) {
    // An error occurred, roll back the transaction
    mysqli_rollback($link);
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
}

// Finally, perform the redirect.
header("location: " . $redirect_url);
exit;
?>