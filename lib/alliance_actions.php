<?php
/**
 * lib/alliance_actions.php
 *
 * Handles all server-side logic for alliance management, including creation,
 * applications, member management, role management, purchasing structures,
 * and member-to-member transfers.
 * This is a unified script combining all functionalities.
 */
session_start();
// Ensure the user is logged in before proceeding with any actions.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Silently exit if not logged in to prevent exposing script existence.
    exit;
}

// Include necessary configuration and game data files.
require_once "db_config.php";
require_once "game_data.php"; // Required for structure costs and definitions

// Get the user ID from the session and the requested action from the POST data.
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';
$redirect_url = '/alliance.php'; // Set a default redirect URL.

// Begin a database transaction to ensure all operations are atomic (all succeed or all fail).
mysqli_begin_transaction($link);

try {
    // Fetch the current user's data and permissions.
    // Lock the row for update to prevent race conditions during the transaction.
    $sql_user_info = "SELECT u.credits, u.character_name, u.alliance_id, u.alliance_role_id, u.workers, u.soldiers, u.guards, u.sentries, u.spies, ar.* FROM users u LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id WHERE u.id = ? FOR UPDATE";
    $stmt_info = mysqli_prepare($link, $sql_user_info);
    mysqli_stmt_bind_param($stmt_info, "i", $user_id);
    mysqli_stmt_execute($stmt_info);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
    mysqli_stmt_close($stmt_info);

    if (!$user_info) {
        throw new Exception("Could not retrieve user data.");
    }

    // --- ACTION ROUTING ---
    // Route to the correct logic block based on the 'action' parameter.

    if ($action === 'create') {
        $redirect_url = '/create_alliance.php';
        if ($user_info['alliance_id']) {
            throw new Exception("You are already in an alliance.");
        }
        if ($user_info['credits'] < 1000000) {
            throw new Exception("You do not have the 1,000,000 Credits required to found an alliance.");
        }

        $name = trim($_POST['alliance_name']);
        $tag = trim($_POST['alliance_tag']);
        $description = trim($_POST['description']);
        if (empty($name) || empty($tag)) {
            throw new Exception("Alliance name and tag are required.");
        }

        // 1. Deduct creation cost from the user's credits.
        mysqli_query($link, "UPDATE users SET credits = credits - 1000000 WHERE id = $user_id");

        // 2. Create the new alliance.
        $sql = "INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name, $tag, $description, $user_id);
        mysqli_stmt_execute($stmt);
        $alliance_id = mysqli_insert_id($link);

        // 3. Create the default roles for the new alliance, including the 'can_manage_structures' permission.
        $default_roles = [
            // name, order, is_deletable, can_edit, can_approve, can_kick, can_manage_roles, can_manage_structures
            ['Supreme Commander', 0, 0, 1, 1, 1, 1, 1],
            ['Recruit', 1, 1, 0, 0, 0, 0, 0]
        ];
        $sql_role = "INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles, can_manage_structures) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_role = mysqli_prepare($link, $sql_role);
        $sc_role_id = null;
        foreach ($default_roles as $role) {
            mysqli_stmt_bind_param($stmt_role, "isiiiiiii", $alliance_id, $role[0], $role[1], $role[2], $role[3], $role[4], $role[5], $role[6], $role[7]);
            mysqli_stmt_execute($stmt_role);
            if ($role[0] === 'Supreme Commander') {
                $sc_role_id = mysqli_insert_id($link);
            }
        }

        // 4. Assign the creator to the new alliance with the 'Supreme Commander' role.
        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $alliance_id, $sc_role_id, $user_id);
        mysqli_stmt_execute($stmt_update);

        $_SESSION['alliance_message'] = "Alliance created successfully!";
        $redirect_url = '/alliance.php';

    } else if ($action === 'apply_to_alliance') {
        if ($user_info['alliance_id']) {
            throw new Exception("You are already in an alliance.");
        }
        $alliance_id = (int)$_POST['alliance_id'];

        // Check if the alliance is full.
        $sql_count = "SELECT COUNT(*) as member_count FROM users WHERE alliance_id = ?";
        $stmt_count = mysqli_prepare($link, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $alliance_id);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        if ($count_result['member_count'] >= 100) {
            throw new Exception("This alliance is full and cannot accept new members.");
        }

        // Check for an existing pending application.
        $sql_check = "SELECT id FROM alliance_applications WHERE user_id = ? AND alliance_id = ? AND status = 'pending'";
        $stmt_check = mysqli_prepare($link, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            throw new Exception("You have already applied to this alliance.");
        }

        // Create the application.
        $sql = "INSERT INTO alliance_applications (user_id, alliance_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $alliance_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Application sent successfully.";

    } else if ($action === 'approve_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!isset($user_info['can_approve_membership']) || !$user_info['can_approve_membership']) {
            throw new Exception("You do not have permission to approve members.");
        }
        $application_id = (int)$_POST['application_id'];

        // Fetch application details to validate.
        $sql_app = "SELECT user_id, alliance_id FROM alliance_applications WHERE id = ? AND status = 'pending'";
        $stmt_app = mysqli_prepare($link, $sql_app);
        mysqli_stmt_bind_param($stmt_app, "i", $application_id);
        mysqli_stmt_execute($stmt_app);
        $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_app));
        if (!$app || $app['alliance_id'] != $user_info['alliance_id']) {
            throw new Exception("Application not found or does not belong to your alliance.");
        }

        // Re-check member count before adding.
        $sql_count = "SELECT COUNT(*) as member_count FROM users WHERE alliance_id = ?";
        $stmt_count = mysqli_prepare($link, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $app['alliance_id']);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
        if ($count_result['member_count'] >= 100) {
            throw new Exception("Your alliance is full. You cannot approve this member.");
        }

        // Find the default 'Recruit' role ID.
        $sql_role = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Recruit'";
        $stmt_role = mysqli_prepare($link, $sql_role);
        mysqli_stmt_bind_param($stmt_role, "i", $app['alliance_id']);
        mysqli_stmt_execute($stmt_role);
        $role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_role));
        if (!$role) {
            throw new Exception("Default 'Recruit' role not found for this alliance.");
        }

        // Update the user's status to be in the alliance.
        $sql_update_user = "UPDATE users SET alliance_id = ?, alliance_role_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $app['alliance_id'], $role['id'], $app['user_id']);
        mysqli_stmt_execute($stmt_update);

        // Remove the processed application.
        $sql_delete_app = "DELETE FROM alliance_applications WHERE id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete_app);
        mysqli_stmt_bind_param($stmt_delete, "i", $application_id);
        mysqli_stmt_execute($stmt_delete);

        $_SESSION['alliance_message'] = "Member approved.";

    } else if ($action === 'deny_application') {
        $redirect_url = '/alliance.php?tab=applications';
        if (!isset($user_info['can_approve_membership']) || !$user_info['can_approve_membership']) {
            throw new Exception("You do not have permission to deny applications.");
        }
        $application_id = (int)$_POST['application_id'];

        // Delete the application, ensuring it belongs to the user's alliance.
        $sql_delete_app = "DELETE FROM alliance_applications WHERE id = ? AND alliance_id = ?";
        $stmt_delete = mysqli_prepare($link, $sql_delete_app);
        mysqli_stmt_bind_param($stmt_delete, "ii", $application_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_delete);

        $_SESSION['alliance_message'] = "Application denied.";

    } else if ($action === 'leave') {
        if (!$user_info['alliance_id']) {
            throw new Exception("You are not in an alliance.");
        }

        // Check if the user is the leader, who cannot leave.
        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        if ($alliance['leader_id'] == $user_id) {
            throw new Exception("Leaders cannot leave an alliance. You must first transfer leadership or disband the alliance.");
        }

        // Remove alliance info from the user's record.
        $sql_leave = "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE id = ?";
        $stmt_leave = mysqli_prepare($link, $sql_leave);
        mysqli_stmt_bind_param($stmt_leave, "i", $user_id);
        mysqli_stmt_execute($stmt_leave);
        $_SESSION['alliance_message'] = "You have left the alliance.";

    } else if ($action === 'disband') {
        $alliance_id = (int)$_POST['alliance_id'];
        $redirect_url = '/edit_alliance.php';

        // Verify the user is the leader of the alliance they are trying to disband.
        $sql_leader_check = "SELECT leader_id FROM alliances WHERE id = ?";
        $stmt_leader = mysqli_prepare($link, $sql_leader_check);
        mysqli_stmt_bind_param($stmt_leader, "i", $alliance_id);
        mysqli_stmt_execute($stmt_leader);
        $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_leader));
        if (!$alliance || $alliance['leader_id'] != $user_id) {
            throw new Exception("You do not have permission to disband this alliance.");
        }

        // Cascade delete: remove members, roles, applications, and finally the alliance itself.
        mysqli_query($link, "UPDATE users SET alliance_id = NULL, alliance_role_id = NULL WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_roles WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_applications WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_structures WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliance_bank_logs WHERE alliance_id = $alliance_id");
        mysqli_query($link, "DELETE FROM alliances WHERE id = $alliance_id");

        $_SESSION['alliance_message'] = "Alliance has been permanently disbanded.";
        $redirect_url = '/alliance.php';

    } else if ($action === 'create_role') {
        $redirect_url = '/alliance_roles.php';
        if (!isset($user_info['can_manage_roles']) || !$user_info['can_manage_roles']) {
            throw new Exception("You do not have permission to create roles.");
        }

        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        if (empty($name) || $order <= 0) {
            throw new Exception("Invalid role name or order.");
        }

        // Create a new role with default (zero) permissions.
        $sql = "INSERT INTO alliance_roles (alliance_id, name, `order`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_info['alliance_id'], $name, $order);
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' created successfully.";

    } else if ($action === 'update_role') {
        $redirect_url = '/alliance_roles.php';
        if (!isset($user_info['can_manage_roles']) || !$user_info['can_manage_roles']) {
            throw new Exception("You do not have permission to update roles.");
        }

        $role_id = (int)$_POST['role_id'];
        $name = trim($_POST['name']);
        $order = (int)$_POST['order'];
        $permissions = $_POST['permissions'] ?? [];

        // Map checkbox values from the form to integer flags for the database.
        $can_edit_profile = isset($permissions['can_edit_profile']) ? 1 : 0;
        $can_approve_membership = isset($permissions['can_approve_membership']) ? 1 : 0;
        $can_kick_members = isset($permissions['can_kick_members']) ? 1 : 0;
        $can_manage_roles = isset($permissions['can_manage_roles']) ? 1 : 0;
        $can_manage_structures = isset($permissions['can_manage_structures']) ? 1 : 0;

        $sql = "UPDATE alliance_roles SET name = ?, `order` = ?, can_edit_profile = ?, can_approve_membership = ?, can_kick_members = ?, can_manage_roles = ?, can_manage_structures = ? WHERE id = ? AND alliance_id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "siiiiiiii", $name, $order, $can_edit_profile, $can_approve_membership, $can_kick_members, $can_manage_roles, $can_manage_structures, $role_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt);
        $_SESSION['alliance_message'] = "Role '" . htmlspecialchars($name) . "' updated successfully.";

    } else if ($action === 'purchase_structure') {
        $redirect_url = '/alliance_structures.php';
        $structure_key = $_POST['structure_key'] ?? '';

        if (!isset($user_info['can_manage_structures']) || $user_info['can_manage_structures'] != 1) {
            throw new Exception("You do not have permission to purchase structures.");
        }
        if (!isset($alliance_structures_definitions[$structure_key])) {
            throw new Exception("Invalid structure specified.");
        }

        $structure_details = $alliance_structures_definitions[$structure_key];
        $cost = $structure_details['cost'];

        // Check if the alliance already owns this structure.
        $sql_check_owned = "SELECT id FROM alliance_structures WHERE alliance_id = ? AND structure_key = ?";
        $stmt_check = mysqli_prepare($link, $sql_check_owned);
        mysqli_stmt_bind_param($stmt_check, "is", $user_info['alliance_id'], $structure_key);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            throw new Exception("Your alliance already owns this structure.");
        }
        mysqli_stmt_close($stmt_check);

        // Check if the alliance bank has enough credits.
        $sql_get_bank = "SELECT bank_credits FROM alliances WHERE id = ? FOR UPDATE";
        $stmt_bank = mysqli_prepare($link, $sql_get_bank);
        mysqli_stmt_bind_param($stmt_bank, "i", $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_bank);
        $alliance_bank = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bank));
        mysqli_stmt_close($stmt_bank);
        if (!$alliance_bank || $alliance_bank['bank_credits'] < $cost) {
            throw new Exception("Your alliance does not have enough credits in the bank.");
        }

        // Deduct cost from the bank.
        $sql_deduct = "UPDATE alliances SET bank_credits = bank_credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $cost, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);

        // Add the structure to the alliance's owned structures.
        $sql_add = "INSERT INTO alliance_structures (alliance_id, structure_key, level) VALUES (?, ?, 1)";
        $stmt_add = mysqli_prepare($link, $sql_add);
        mysqli_stmt_bind_param($stmt_add, "is", $user_info['alliance_id'], $structure_key);
        mysqli_stmt_execute($stmt_add);
        mysqli_stmt_close($stmt_add);

        // Log the transaction.
        $log_desc = "Purchased " . $structure_details['name'] . " by " . $user_info['character_name'];
        $sql_log = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'purchase', ?, ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iiis", $user_info['alliance_id'], $user_id, $cost, $log_desc);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['alliance_message'] = "Successfully purchased " . $structure_details['name'] . "!";

    } else if ($action === 'donate_credits') {
        $redirect_url = '/alliance_bank.php';
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
        if ($amount <= 0) {
            throw new Exception("Invalid donation amount.");
        }
        if ($user_info['credits'] < $amount) {
            throw new Exception("Not enough credits to donate.");
        }

        // 1. Deduct from user
        $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $amount, $user_id);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);

        // 2. Add to alliance bank
        $sql_add = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
        $stmt_add = mysqli_prepare($link, $sql_add);
        mysqli_stmt_bind_param($stmt_add, "ii", $amount, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_add);
        mysqli_stmt_close($stmt_add);

        // 3. Log transaction
        $log_desc = "Donation from " . $user_info['character_name'];
        $sql_log = "INSERT INTO alliance_bank_logs (alliance_id, user_id, type, amount, description) VALUES (?, ?, 'deposit', ?, ?)";
        $stmt_log = mysqli_prepare($link, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iiis", $user_info['alliance_id'], $user_id, $amount, $log_desc);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        $_SESSION['alliance_message'] = "Successfully donated " . number_format($amount) . " credits to the alliance bank.";

    } else if ($action === 'transfer_credits') {
        $redirect_url = '/alliance_transfer.php';
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
        $fee = floor($amount * 0.02);
        $total_cost = $amount + $fee;

        if ($amount <= 0 || $recipient_id <= 0) {
            throw new Exception("Invalid amount or recipient.");
        }
        if ($user_info['credits'] < $total_cost) {
            throw new Exception("Insufficient credits to cover the transfer and the 2% fee.");
        }

        // 1. Deduct from sender
        $sql_deduct = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct = mysqli_prepare($link, $sql_deduct);
        mysqli_stmt_bind_param($stmt_deduct, "ii", $total_cost, $user_id);
        mysqli_stmt_execute($stmt_deduct);
        mysqli_stmt_close($stmt_deduct);

        // 2. Add to recipient
        $sql_add = "UPDATE users SET credits = credits + ? WHERE id = ? AND alliance_id = ?";
        $stmt_add = mysqli_prepare($link, $sql_add);
        mysqli_stmt_bind_param($stmt_add, "iii", $amount, $recipient_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_add);
        if (mysqli_stmt_affected_rows($stmt_add) == 0) {
            throw new Exception("Recipient not found or not in your alliance.");
        }
        mysqli_stmt_close($stmt_add);

        // 3. Add fee to alliance bank
        $sql_fee = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
        $stmt_fee = mysqli_prepare($link, $sql_fee);
        mysqli_stmt_bind_param($stmt_fee, "ii", $fee, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_fee);
        mysqli_stmt_close($stmt_fee);

        $_SESSION['alliance_message'] = "Successfully transferred " . number_format($amount) . " credits. A fee of " . number_format($fee) . " was paid to the alliance bank.";

    } else if ($action === 'transfer_units') {
        $redirect_url = '/alliance_transfer.php';
        $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
        $unit_type = $_POST['unit_type'] ?? '';
        $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;

        $unit_costs = ['workers' => 100, 'soldiers' => 250, 'guards' => 250, 'sentries' => 500, 'spies' => 1000];
        if ($amount <= 0 || $recipient_id <= 0 || !array_key_exists($unit_type, $unit_costs)) {
            throw new Exception("Invalid amount, recipient, or unit type.");
        }
        if ($user_info[$unit_type] < $amount) {
            throw new Exception("Not enough " . ucfirst($unit_type) . " to transfer.");
        }

        $fee = floor(($unit_costs[$unit_type] * $amount) * 0.02);
        if ($user_info['credits'] < $fee) {
            throw new Exception("Insufficient credits to pay the transfer fee of " . number_format($fee) . ".");
        }

        // 1. Deduct fee from sender
        $sql_deduct_fee = "UPDATE users SET credits = credits - ? WHERE id = ?";
        $stmt_deduct_fee = mysqli_prepare($link, $sql_deduct_fee);
        mysqli_stmt_bind_param($stmt_deduct_fee, "ii", $fee, $user_id);
        mysqli_stmt_execute($stmt_deduct_fee);
        mysqli_stmt_close($stmt_deduct_fee);

        // 2. Deduct units from sender
        $sql_deduct_units = "UPDATE users SET `$unit_type` = `$unit_type` - ? WHERE id = ?";
        $stmt_deduct_units = mysqli_prepare($link, $sql_deduct_units);
        mysqli_stmt_bind_param($stmt_deduct_units, "ii", $amount, $user_id);
        mysqli_stmt_execute($stmt_deduct_units);
        mysqli_stmt_close($stmt_deduct_units);

        // 3. Add units to recipient
        $sql_add_units = "UPDATE users SET `$unit_type` = `$unit_type` + ? WHERE id = ? AND alliance_id = ?";
        $stmt_add_units = mysqli_prepare($link, $sql_add_units);
        mysqli_stmt_bind_param($stmt_add_units, "iii", $amount, $recipient_id, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_add_units);
        if (mysqli_stmt_affected_rows($stmt_add_units) == 0) {
            throw new Exception("Recipient not found or not in your alliance.");
        }
        mysqli_stmt_close($stmt_add_units);

        // 4. Add fee to alliance bank
        $sql_fee = "UPDATE alliances SET bank_credits = bank_credits + ? WHERE id = ?";
        $stmt_fee = mysqli_prepare($link, $sql_fee);
        mysqli_stmt_bind_param($stmt_fee, "ii", $fee, $user_info['alliance_id']);
        mysqli_stmt_execute($stmt_fee);
        mysqli_stmt_close($stmt_fee);

        $_SESSION['alliance_message'] = "Successfully transferred " . number_format($amount) . " " . ucfirst($unit_type) . ". A fee of " . number_format($fee) . " credits was paid to the alliance bank.";
    }

    // If we reach this point without any exceptions, commit the transaction.
    mysqli_commit($link);

} catch (Exception $e) {
    // If any exception was thrown, roll back the entire transaction.
    mysqli_rollback($link);
    // Store the error message in the session to be displayed to the user.
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
}

// Redirect the user back to the appropriate page with a success or error message.
header("location: " . $redirect_url);
exit;
?>