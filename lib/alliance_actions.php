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
    $upload_error_message = null;

    mysqli_begin_transaction($link);
    try {
        // Verify user is the leader of this alliance
        $sql_verify = "SELECT leader_id FROM alliances WHERE id = ? FOR UPDATE";
        $stmt_verify = mysqli_prepare($link, $sql_verify);
        mysqli_stmt_bind_param($stmt_verify, "i", $alliance_id);
        mysqli_stmt_execute($stmt_verify);
        $alliance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify));
        mysqli_stmt_close($stmt_verify);

        if (!$alliance_data || $alliance_data['leader_id'] != $user_id) {
            throw new Exception("You do not have permission to edit this alliance.");
        }

        // Avatar Upload Logic, similar to update_profile.php
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/alliances/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                if (!is_writable($upload_dir)) { throw new Exception("Permission Error: Directory 'uploads/alliances/' is not writable."); }

                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

                if ($_FILES['avatar']['size'] > 10000000) { throw new Exception("File is too large (Max 10MB)."); }
                if (!in_array($file_ext, $allowed_ext)) { throw new Exception("Invalid file type (JPG, PNG, GIF only)."); }

                $new_file_name = 'alliance_' . $alliance_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = '/uploads/alliances/' . $new_file_name;
                } else {
                    throw new Exception("Could not move uploaded file.");
                }
            } else {
                throw new Exception("An error occurred during file upload.");
            }
        }

        // Update database
        if ($avatar_path) {
            $sql_update = "UPDATE alliances SET description = ?, avatar_path = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssi", $description, $avatar_path, $alliance_id);
        } else {
            $sql_update = "UPDATE alliances SET description = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "si", $description, $alliance_id);
        }
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

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

header("location: /alliance.php");
exit;
?>