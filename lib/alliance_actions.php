<?php
// lib/alliance_actions.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { exit; }
require_once "db_config.php";
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';

// ACTION: Create Alliance
if ($action === 'create') {
    $alliance_name = trim($_POST['alliance_name']);
    $alliance_tag = trim($_POST['alliance_tag']);
    $creation_cost = 1000000;

    mysqli_begin_transaction($link);
    try {
        // Fetch user's credits and check if they are already in an alliance
        $sql_user = "SELECT credits, alliance_id FROM users WHERE id = ? FOR UPDATE";
        // ... (validation logic: check cost, name/tag length, if user is already in an alliance) ...

        // Create the new alliance
        $sql_create = "INSERT INTO alliances (name, tag, leader_id) VALUES (?, ?, ?)";
        // ... (execute insert) ...
        $new_alliance_id = mysqli_insert_id($link);

        // Update the user's status to Leader of the new alliance
        $sql_update_user = "UPDATE users SET credits = credits - ?, alliance_id = ?, alliance_rank = 'Leader' WHERE id = ?";
        // ... (execute update) ...

        mysqli_commit($link);
        $_SESSION['alliance_message'] = "Alliance created successfully!";
        header("location: /alliance.php");
    } catch (Exception $e) {
        mysqli_rollback($link);
        // ... (error handling) ...
    }
}

// ACTION: Edit Alliance
if ($action === 'edit') {
    // ... (logic for updating description and avatar, checking for leader permissions) ...
}
?>