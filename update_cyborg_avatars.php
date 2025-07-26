<?php
/**
 * update_cyborg_avatars.php
 *
 * This is a one-time utility script to assign the default Cyborg avatar to any existing
 * Cyborg users who do not currently have an avatar set. It iterates through users with a
 * NULL or empty 'avatar_path' and assigns the default image based on their race.
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

echo "<h1>Starting retroactive avatar assignment for Cyborgs...</h1>";

// Corrected path: Assumes this script is in the root and db_config.php is in /lib
require_once __DIR__ . '/lib/db_config.php';

// Select Cyborg users who are missing an avatar path
$sql_select = "SELECT id, character_name, race FROM users WHERE race = 'Cyborg' AND (avatar_path IS NULL OR avatar_path = '')";
$result = mysqli_query($link, $sql_select);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<p>Found " . mysqli_num_rows($result) . " Cyborg users to update.</p>";
    $updated_count = 0;

    // Loop through each user
    while ($user = mysqli_fetch_assoc($result)) {
        $avatar_path = 'assets/img/cyborg.png'; 

        // Prepare and execute the update statement
        $sql_update = "UPDATE users SET avatar_path = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_update)) {
            mysqli_stmt_bind_param($stmt, "si", $avatar_path, $user['id']);
            if (mysqli_stmt_execute($stmt)) {
                echo "Successfully updated user: " . htmlspecialchars($user['character_name']) . " (ID: " . $user['id'] . ")<br>";
                $updated_count++;
            } else {
                echo "Error updating user ID " . $user['id'] . ": " . mysqli_stmt_error($stmt) . "<br>";
            }
            mysqli_stmt_close($stmt);
        }
    }
    echo "<h2>Update complete. Total Cyborg users updated: " . $updated_count . "</h2>";
} else {
    echo "<h2>No Cyborg users needed an avatar update.</h2>";
}

mysqli_close($link);
?>