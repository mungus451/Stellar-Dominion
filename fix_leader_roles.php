<?php
/**
 * fix_leader_roles.php
 *
 * This is a one-time maintenance script to assign the 'Supreme Commander' role
 * to all existing alliance leaders who were created before the role system was implemented.
 * This will fix the permission issue where leaders cannot access the roles page.
 */

require_once "lib/db_config.php";
echo "<h1>Alliance Leader Role Fix Script</h1>";

mysqli_begin_transaction($link);
try {
    // Get all alliances and their leader IDs
    $sql_alliances = "SELECT id, leader_id FROM alliances";
    $result_alliances = mysqli_query($link, $sql_alliances);
    
    if (!$result_alliances) {
        throw new Exception("Could not fetch alliances: " . mysqli_error($link));
    }

    $updated_leaders = 0;

    while ($alliance = mysqli_fetch_assoc($result_alliances)) {
        $alliance_id = $alliance['id'];
        $leader_id = $alliance['leader_id'];

        // Find the 'Supreme Commander' role ID for this specific alliance
        $sql_role = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Supreme Commander'";
        $stmt_role = mysqli_prepare($link, $sql_role);
        mysqli_stmt_bind_param($stmt_role, "i", $alliance_id);
        mysqli_stmt_execute($stmt_role);
        $result_role = mysqli_stmt_get_result($stmt_role);
        $role = mysqli_fetch_assoc($result_role);
        mysqli_stmt_close($stmt_role);

        if ($role && $leader_id) {
            $sc_role_id = $role['id'];

            // Update the leader's role in the users table only if it's not already set correctly
            $sql_update = "UPDATE users SET alliance_role_id = ? WHERE id = ? AND (alliance_role_id IS NULL OR alliance_role_id != ?)";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iii", $sc_role_id, $leader_id, $sc_role_id);
            mysqli_stmt_execute($stmt_update);
            
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                echo "<p>Updated leader ID " . htmlspecialchars($leader_id) . " for alliance ID " . htmlspecialchars($alliance_id) . " to Supreme Commander role.</p>";
                $updated_leaders++;
            }
            mysqli_stmt_close($stmt_update);
        } else {
             echo "<p>Skipping alliance ID " . htmlspecialchars($alliance_id) . ": Could not find Supreme Commander role or a leader is not assigned.</p>";
        }
    }

    mysqli_commit($link);
    echo "<h2>Script finished successfully.</h2>";
    echo "<p><strong>Total leaders updated: " . $updated_leaders . "</strong></p>";

} catch (Exception $e) {
    mysqli_rollback($link);
    echo "<h1>An error occurred!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>The database transaction was rolled back. No changes were saved.</p>";
}

mysqli_close($link);
?>