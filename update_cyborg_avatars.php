<?php
/**
 * comprehensive_cyborg_fix.php
 *
 * This is a robust, one-time utility script to find and correct any invalid
 * avatar paths for all Cyborg users. It fixes missing paths, typos, or any
 * other incorrect entries.
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

echo "<h1>Starting comprehensive avatar fix for all Cyborgs...</h1>";

require_once __DIR__ . '/lib/db_config.php';

// The one and only correct path for the Cyborg avatar
$correct_path = 'assets/img/cyborg.png';

// Select all Cyborg users whose path is NOT the correct one.
// This includes NULL paths, empty strings, and any incorrect values like the typo.
$sql_select = "SELECT id, character_name, avatar_path FROM users WHERE race = 'Cyborg' AND (avatar_path != ? OR avatar_path IS NULL)";
$stmt_select = mysqli_prepare($link, $sql_select);
mysqli_stmt_bind_param($stmt_select, "s", $correct_path);
mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);

if ($result && mysqli_num_rows($result) > 0) {
    $user_count = mysqli_num_rows($result);
    echo "<p>Found " . $user_count . " Cyborg users with an invalid avatar path.</p><hr>";
    $updated_count = 0;

    // Loop through each user and update their path to the correct one
    while ($user = mysqli_fetch_assoc($result)) {
        $sql_update = "UPDATE users SET avatar_path = ? WHERE id = ?";
        if ($stmt_update = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "si", $correct_path, $user['id']);
            if (mysqli_stmt_execute($stmt_update)) {
                echo "Fixed user: " . htmlspecialchars($user['character_name']) . " (ID: " . $user['id'] . "). Old path was: '" . htmlspecialchars($user['avatar_path']) . "'<br>";
                $updated_count++;
            } else {
                echo "Error updating user ID " . $user['id'] . ": " . mysqli_stmt_error($stmt_update) . "<br>";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
    echo "<hr><h2>Update complete. Total users fixed: " . $updated_count . "</h2>";
} else {
    echo "<h2>No Cyborg users required an avatar fix. All paths appear to be correct.</h2>";
}

mysqli_stmt_close($stmt_select);
mysqli_close($link);
?>