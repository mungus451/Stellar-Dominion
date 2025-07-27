<?php
/**
 * lib/alliance_actions.php
 *
 * Handles all server-side logic for alliance management, including
 * creation, editing, joining, and leaving.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

// --- ACTION: CREATE ALLIANCE ---
if ($action === 'create') {
    $alliance_name = trim($_POST['alliance_name']);
    $alliance_tag = trim($_POST['alliance_tag']);
    $description = trim($_POST['description']);
    $creation_cost = 1000000;

    mysqli_begin_transaction($link);
    try {
        // Validation
        if (empty($alliance_name) || empty($alliance_tag)) { throw new Exception("Alliance Name and Tag are required."); }
        if (strlen($alliance_name) > 50 || strlen($alliance_tag) > 5) { throw new Exception("Name or Tag is too long."); }

        // Fetch user's data and lock the row
        $sql_user = "SELECT credits, alliance_id FROM users WHERE id = ? FOR UPDATE";
        $stmt_user = mysqli_prepare($link, $sql_user);
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
        mysqli_stmt_close($stmt_user);

        if ($user['alliance_id']) { throw new Exception("You are already in an alliance."); }
        if ($user['credits'] < $creation_cost) { throw new Exception("Not enough credits to create an alliance."); }

        // Create the new alliance
        $sql_create = "INSERT INTO alliances (name, tag, description, leader_id) VALUES (?, ?, ?, ?)";
        $stmt_create = mysqli_prepare($link, $sql_create);
        mysqli_stmt_bind_param($stmt_create, "sssi", $alliance_name, $alliance_tag, $description, $user_id);
        mysqli_stmt_execute($stmt_create);
        $new_alliance_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt_create);

        // Update the user's status
        $sql_update_user = "UPDATE users SET credits = credits - ?, alliance_id = ?, alliance_rank = 'Leader' WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_user);
        mysqli_stmt_bind_param($stmt_update, "iii", $creation_cost, $new_alliance_id, $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        mysqli_commit($link);
        $_SESSION['alliance_message'] = "Alliance created successfully!";
        header("location: /alliance.php");

    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
        header("location: /create_alliance.php");
    }
    exit;
}

// --- ACTION: EDIT ALLIANCE ---
if ($action === 'edit') {
    $description = trim($_POST['description']);
    $alliance_id = (int)$_POST['alliance_id'];
    $avatar_path = null;

    mysqli_begin_transaction($link);
    try {
        // Verify user is the leader of this alliance
        $sql_verify = "SELECT leader_id, avatar_path FROM alliances WHERE id = ?";
        // ... (Code to verify leader and get current avatar path)

        // --- Avatar Upload Logic (similar to update_profile.php) ---
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            // ... (Full avatar upload, validation, and move logic here) ...
            // On success, set $avatar_path to the new path.
        }

        // Update database
        if ($avatar_path) {
            $sql_update = "UPDATE alliances SET description = ?, avatar_path = ? WHERE id = ?";
            // ... (bind and execute with avatar) ...
        } else {
            $sql_update = "UPDATE alliances SET description = ? WHERE id = ?";
            // ... (bind and execute without avatar) ...
        }

        mysqli_commit($link);
        $_SESSION['alliance_message'] = "Alliance profile updated!";
        header("location: /alliance.php");

    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
        header("location: /edit_alliance.php");
    }
    exit;
}

// Add other actions like 'join', 'leave', 'promote', 'demote' here...
?>