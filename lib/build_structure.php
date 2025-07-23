<?php
/**
 * build_structure.php
 *
 * This script handles the server-side logic for building a new structure. It validates
 * the request from structures.php, re-checks player resources and requirements,
 * and updates the database within a secure transaction.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";

// --- INPUT VALIDATION ---
// Get the target structure level from the POST data.
$target_level = isset($_POST['structure_level']) ? (int)$_POST['structure_level'] : 0;
if ($target_level <= 0) {
    header("location: structures.php");
    exit;
}

// --- GAME DATA: STRUCTURE DEFINITIONS ---
// This array must be identical to the one in structures.php to ensure
// the cost and requirement checks are accurate.
$structures = [
    1 => ['level_req' => 1, 'cost' => 50000],
    2 => ['level_req' => 5, 'cost' => 250000],
    3 => ['level_req' => 10, 'cost' => 1000000],
    4 => ['level_req' => 15, 'cost' => 5000000],
    5 => ['level_req' => 20, 'cost' => 20000000]
];

// Check if the requested structure level is valid.
if (!isset($structures[$target_level])) {
    header("location: structures.php");
    exit;
}
$structure_to_build = $structures[$target_level];


// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get the player's current data, locking the row for update.
    $sql_get_user = "SELECT credits, level, structure_level FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // --- SERVER-SIDE VALIDATION ---
    // Re-verify all conditions on the server to prevent manipulation.
    // 1. Check if the player is trying to build the very next structure in the sequence.
    // 2. Check if the player meets the character level requirement.
    // 3. Check if the player has enough credits.
    if ($user['structure_level'] != $target_level - 1 || 
        $user['level'] < $structure_to_build['level_req'] || 
        $user['credits'] < $structure_to_build['cost']) {
        // If any check fails, throw an exception to stop the process and roll back the transaction.
        throw new Exception("Player does not meet the requirements to build this structure.");
    }

    // --- EXECUTE UPDATE ---
    // If all checks pass, deduct the cost and update the player's structure level.
    $sql_update = "UPDATE users SET credits = credits - ?, structure_level = ? WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iii", $structure_to_build['cost'], $target_level, $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Commit the transaction to make the changes permanent.
    mysqli_commit($link);
    
    // Set a success message to be displayed on the structures page.
    $_SESSION['build_message'] = "Structure built successfully!";

} catch (Exception $e) {
    // If any error occurred, roll back all database changes.
    mysqli_rollback($link);
    // You could log the error here: error_log($e->getMessage());
    // Set an error message for the user.
    $_SESSION['build_message'] = "Error building structure. Please try again.";
}

// Redirect the user back to the structures page.
header("location: structures.php");
exit;
?>
