<?php
/**
 * update_profile.php
 *
 * Handles form submissions from profile.php for updating avatar and biography.
 */

session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.html"); exit; }
require_once "db_config.php";

$user_id = $_SESSION['id'];
$biography = isset($_POST['biography']) ? trim($_POST['biography']) : '';

// --- Avatar Upload Logic ---
$avatar_path = null;
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $upload_dir = __DIR__ . '/../uploads/avatars/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_name = $_FILES['avatar']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (in_array($file_ext, $allowed_ext) && $_FILES['avatar']['size'] < 10000000) { // 10MB limit
        $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
            // --- BEGIN CHANGE: Store path with leading slash ---
            $avatar_path = '/uploads/avatars/' . $new_file_name;
            // --- END CHANGE ---
        }
    }
}

// --- Database Update ---
mysqli_begin_transaction($link);
try {
    if ($avatar_path) {
        // If a new avatar was uploaded, update both bio and avatar path
        $sql = "UPDATE users SET biography = ?, avatar_path = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $biography, $avatar_path, $user_id);
    } else {
        // If no new avatar, only update the biography
        $sql = "UPDATE users SET biography = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "si", $biography, $user_id);
    }
    
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_commit($link);
    
    $_SESSION['profile_message'] = "Profile updated successfully!";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['profile_message'] = "Error updating profile. Please try again.";
}

header("location: /profile.php");
exit;
?>