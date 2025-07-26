<?php
/**
 * fix_cyborg_avatars.php
 *
 * This is a one-time maintenance script to correct the avatar path
 * for all Cyborg players affected by the 'cybord.png' typo in the
 * original registration script.
 */

require_once "lib/db_config.php";
echo "<h1>Cyborg Avatar Fix Script</h1>";

$incorrect_path = 'assets/img/cybord.png';
$correct_path = 'assets/img/cyborg.png';

$sql = "UPDATE users SET avatar_path = ? WHERE avatar_path = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ss", $correct_path, $incorrect_path);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        echo "<p>Successfully found and fixed <strong>" . $affected_rows . "</strong> players with the incorrect Cyborg avatar path.</p>";
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