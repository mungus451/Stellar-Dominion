<?php
/**
 * manual_avatar_fix.php
 *
 * This is a targeted utility script to manually fix the avatar paths for
 * specific users ("test1" and "test2").
 *
 * It is recommended to delete this file after you have successfully run it.
 */

// Set full error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Starting manual avatar fix for 'test1' and 'test2'...</h1>";

require_once __DIR__ . '/lib/db_config.php';

// --- DEFINE THE TARGETS AND THE FIX ---
$target_users = ['test1', 'test2'];
$correct_path = 'assets/img/cyborg.png';

// --- PREPARE AND EXECUTE THE UPDATE ---
// The '?' placeholders prevent SQL injection
$placeholders = implode(',', array_fill(0, count($target_users), '?'));
$sql = "UPDATE users SET avatar_path = ? WHERE character_name IN ($placeholders)";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Dynamically create the type definition string (e.g., 'sss' for 3 variables)
    $types = 's' . str_repeat('s', count($target_users));
    
    // Combine the path and user names into a single array for binding
    $params = array_merge([$correct_path], $target_users);
    
    // Bind the parameters to the statement
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    // Execute the query
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        echo "<h2>Update complete.</h2>";
        echo "<p><b>" . $affected_rows . "</b> user profile(s) were updated.</p>";
        echo "<p>The avatars for 'test1' and 'test2' should now be fixed. Please clear your browser cache and check the leaderboards again.</p>";
    } else {
        echo "<h2>Error executing the update: " . mysqli_stmt_error($stmt) . "</h2>";
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<h2>Error preparing the SQL statement: " . mysqli_error($link) . "</h2>";
}

mysqli_close($link);
?>