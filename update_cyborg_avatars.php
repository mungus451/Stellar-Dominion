<?php
/**
 * fix_cyborg_paths.php
 *
 * This is a one-time utility script to correct the avatar path for any existing
 * Cyborg users who were assigned an incorrect path due to a typo.
 *
 * To use this script, run it once from your server's command line or by
 * navigating to it in a web browser.
 *
 * It is recommended to delete this file after you have successfully run it.
 */

// Set full error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Starting script to fix incorrect Cyborg avatar paths...</h1>";

require_once __DIR__ . '/lib/db_config.php';

// The incorrect path that was being saved
$incorrect_path = 'assets/img/cybord.png';
// The correct path
$correct_path = 'assets/img/cyborg.png';

// Select Cyborg users who have the incorrect avatar path
$sql_select = "SELECT id, character_name FROM users WHERE race = 'Cyborg' AND avatar_path = ?";
$stmt_select = mysqli_prepare($link, $sql_select);
mysqli_stmt_bind_param($stmt_select, "s", $incorrect_path);
mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);

if ($result && mysqli_num_rows($result) > 0) {
    $user_count = mysqli_num_rows($result);
    echo "<p>Found " . $user_count . " Cyborg users with the incorrect path.</p>";
    $updated_count = 0;

    // Loop through each user and update their path
    while ($user = mysqli_fetch_assoc($result)) {
        $sql_update = "UPDATE users SET avatar_path = ? WHERE id = ?";
        if ($stmt_update = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "si", $correct_path, $user['id']);
            if (mysqli_stmt_execute($stmt_update)) {
                echo "Successfully updated user: " . htmlspecialchars($user['character_name']) . " (ID: " . $user['id'] . ")<br>";
                $updated_count++;
            } else {
                echo "Error updating user ID " . $user['id'] . ": " . mysqli_stmt_error($stmt_update) . "<br>";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
    echo "<h2>Update complete. Total users fixed: " . $updated_count . "</h2>";
} else {
    echo "<h2>No users found with the incorrect Cyborg avatar path.</h2>";
}

mysqli_stmt_close($stmt_select);
mysqli_close($link);
?>