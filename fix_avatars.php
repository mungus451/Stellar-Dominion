<?php
/**
 * fix_avatars.php
 *
 * This is a one-time maintenance script to assign the correct default
 * avatar paths to players who are missing them.
 */

require_once "lib/db_config.php";
echo "<h1>Avatar Fix Script</h1>";

// Array of players to fix: 'character_name' => 'correct_avatar_path'
$players_to_fix = [
    'Bob'           => 'assets/img/human.png',
    'Boomer'        => 'assets/img/mutant.png',
    'BigDaddyMoney' => 'assets/img/shade.png',
    'beans'         => 'assets/img/cyborg.png'
];

$sql = "UPDATE users SET avatar_path = ? WHERE character_name = ?";

foreach ($players_to_fix as $name => $path) {
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $path, $name);
        
        if (mysqli_stmt_execute($stmt)) {
            // Check if any row was actually updated
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                echo "<p>Successfully updated avatar for: <strong>" . htmlspecialchars($name) . "</strong></p>";
            } else {
                echo "<p>No update needed for: <strong>" . htmlspecialchars($name) . "</strong> (Player not found or already has the correct path).</p>";
            }
        } else {
            echo "<p>Error updating " . htmlspecialchars($name) . ": " . mysqli_error($link) . "</p>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<p>Error preparing statement: " . mysqli_error($link) . "</p>";
        break; // Stop if the statement can't be prepared
    }
}

mysqli_close($link);
echo "<h2>Script finished.</h2>";
?>