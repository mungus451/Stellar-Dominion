<?php
/**
 * lib/alliance_actions.php
 *
 * Handles all server-side logic for alliance management, including
 * creation, editing, joining, leaving, member management, and forum posts.
 */
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

// Fetch user's current alliance status for permission checks
$sql_user_check = "SELECT alliance_id, alliance_rank FROM users WHERE id = ?";
$stmt_check = mysqli_prepare($link, $sql_user_check);
mysqli_stmt_bind_param($stmt_check, "i", $user_id);
mysqli_stmt_execute($stmt_check);
$user_alliance_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
mysqli_stmt_close($stmt_check);
$user_alliance_id = $user_alliance_info['alliance_id'] ?? null;
$user_alliance_rank = $user_alliance_info['alliance_rank'] ?? null;


// The main logic does not need to be wrapped in a single transaction
// as each action is independent and redirects.
// We will manage transactions per action.

try {
    // --- ACTION: CREATE ALLIANCE ---
    if ($action === 'create') {
        // ... existing create code ...
    }

    // --- ACTION: EDIT ALLIANCE ---
    if ($action === 'edit') {
        // ... existing edit code ...
    }

    // --- ACTION: LEAVE ALLIANCE ---
    if ($action === 'leave') {
        mysqli_begin_transaction($link);
        if (!$user_alliance_id) { throw new Exception("You are not in an alliance."); }
        if ($user_alliance_rank === 'Leader') { throw new Exception("You must pass leadership to another member before leaving."); }

        $sql = "UPDATE users SET alliance_id = NULL, alliance_rank = NULL WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($link);
        $_SESSION['alliance_message'] = "You have successfully left the alliance.";
        header("location: /alliance.php");
        exit;
    }

    // --- ACTION: POST TO FORUM (CORRECTED) ---
    if ($action === 'post_forum') {
        mysqli_begin_transaction($link);
        $post_content = trim($_POST['post_content']);
        if (!$user_alliance_id) { throw new Exception("You must be in an alliance to post."); }
        if (empty($post_content)) { throw new Exception("Post content cannot be empty."); }

        $sql = "INSERT INTO alliance_forum_posts (alliance_id, user_id, post_content) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $user_alliance_id, $user_id, $post_content);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to save post. Please try again.");
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($link); // Commit the transaction BEFORE redirecting

        $_SESSION['alliance_message'] = "Your message has been posted.";
        header("location: /alliance.php?tab=forum");
        exit;
    }

    // --- LEADER ACTIONS: KICK, PROMOTE, DEMOTE ---
    if (in_array($action, ['kick', 'promote', 'demote'])) {
        mysqli_begin_transaction($link);
        if ($user_alliance_rank !== 'Leader') { throw new Exception("You do not have permission to manage members."); }
        $member_id = (int)$_POST['member_id'];
        if ($member_id === $user_id) { throw new Exception("You cannot manage yourself."); }

        // Fetch member's current rank to validate action
        $sql_member = "SELECT alliance_rank FROM users WHERE id = ? AND alliance_id = ?";
        $stmt_member = mysqli_prepare($link, $sql_member);
        mysqli_stmt_bind_param($stmt_member, "ii", $member_id, $user_alliance_id);
        mysqli_stmt_execute($stmt_member);
        $member_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_member));
        mysqli_stmt_close($stmt_member);
        if (!$member_info) { throw new Exception("Member not found in your alliance."); }
        $member_current_rank = $member_info['alliance_rank'];

        $new_rank = null;
        if ($action === 'kick') {
            $sql = "UPDATE users SET alliance_id = NULL, alliance_rank = NULL WHERE id = ?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "i", $member_id);
        } else {
            if ($action === 'promote' && $member_current_rank === 'Member') { $new_rank = 'Lieutenant'; }
            if ($action === 'demote' && $member_current_rank === 'Lieutenant') { $new_rank = 'Member'; }
            if ($new_rank) {
                $sql = "UPDATE users SET alliance_rank = ? WHERE id = ?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "si", $new_rank, $member_id);
            } else {
                throw new Exception("Invalid promotion/demotion action.");
            }
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($link);
    }

    header("location: /alliance.php");

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['alliance_error'] = "Error: " . $e->getMessage();
    // Determine the redirect based on the action
    $redirect_page = '/alliance.php';
    if ($action === 'create') $redirect_page = '/create_alliance.php';
    if ($action === 'edit') $redirect_page = '/edit_alliance.php';
    if ($action === 'post_forum') $redirect_page = '/alliance.php?tab=forum';
    header("location: " . $redirect_page);
}
exit;
?>