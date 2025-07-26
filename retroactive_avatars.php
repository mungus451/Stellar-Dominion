<?php
/**
 * retroactive_avatars.php
 *
 * This is a one-time utility script to assign default avatars to any existing
 * users who do not currently have one set. It iterates through users with a
 * NULL or empty 'avatar_path' and assigns a default image based on their race.
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

echo "<h1>Starting retroactive avatar assignment...</h1>";

// Corrected path: Assumes this script is in the root and db_config.php is in /lib
require_once __DIR__ . '/lib/db_config.php';

// Select users who are missing an avatar path
$sql_select = "SELECT id, character_name, race FROM users WHERE avatar_path IS NULL OR avatar_path = ''";
$result = mysqli_query($link, $sql_select);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<p>Found " . mysqli_num_rows($result) . " users to update.</p>";
    $updated_count = 0;

    // Loop through each user
    while ($user = mysqli_fetch_assoc($result)) {
        $avatar_path = '';
        // Assign avatar based on race
        switch ($user['race']) {
            case 'Human':
                $avatar_path = 'assets/img/human.png';
                break;
            case 'Cyborg':
                $avatar_path = 'assets/img/cyborg.png'; 
                break;
            case 'Mutant':
                $avatar_path = 'assets/img/mutant.png';
                break;
            case 'The Shade':
                $avatar_path = 'assets/img/shade.png';
                break;
            default:
                // Optional: assign a generic default if race is not set or recognized
                $avatar_path = 'assets/img/default.png'; 
                break;
        }

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
    echo "<h2>Update complete. Total users updated: " . $updated_count . "</h2>";
} else {
    echo "<h2>No users needed an avatar update.</h2>";
}

mysqli_close($link);
?>