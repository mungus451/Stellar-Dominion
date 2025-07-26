<?php
/**
 * fix_test2_avatar.php
 *
 * This is a final, targeted maintenance script to assign the correct 
 * avatar path to the user 'test2', whose avatar_path was likely NULL
 * or empty and was missed by previous scripts.
 */

require_once "lib/db_config.php";
echo "<h1>Targeted Avatar Fix for 'test2'</h1>";

$character_to_fix = 'test2';
$correct_path = 'assets/img/cyborg.png';

$sql = "UPDATE users SET avatar_path = ? WHERE character_name = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ss", $correct_path, $character_to_fix);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        if ($affected_rows > 0) {
            echo "<p>Successfully updated the avatar for: <strong>" . htmlspecialchars($character_to_fix) . "</strong>.</p>";
        } else {
            echo "<p>No update needed for: <strong>" . htmlspecialchars($character_to_fix) . "</strong> (Player not found or already has a path).</p>";
        }
    } else {
        echo "<p>Error executing the update: " . mysqli_error($link) . "</p>";
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<p>Error preparing statement: " . mysqli_error($link) . "</p>";
}

mysqli_close($link);
echo "<h2>Script finished.</h2>";

?>