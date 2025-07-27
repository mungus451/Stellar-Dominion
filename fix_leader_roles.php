<?php
/**
 * fix_leader_roles.php (v2)
 *
 * This is a one-time maintenance script to assign the 'Supreme Commander' role
 * to all existing alliance leaders.
 *
 * v2: This version is more robust. It will CREATE the default roles for any
 * existing alliance that is missing them, then assign the leader. This ensures
 * that alliances created before the roles feature was implemented are fully updated.
 */

require_once "lib/db_config.php";
echo "<h1>Alliance Leader Role Fix Script (v2)</h1>";

mysqli_begin_transaction($link);
try {
    // Get all alliances and their leader IDs
    $sql_alliances = "SELECT id, name, leader_id FROM alliances";
    $result_alliances = mysqli_query($link, $sql_alliances);
    
    if (!$result_alliances) {
        throw new Exception("Could not fetch alliances: " . mysqli_error($link));
    }

    $updated_leaders = 0;
    $roles_created_for_alliances = 0;

    $default_roles = [
        ['Supreme Commander', 0, 0, 1, 1, 1, 1],
        ['Officer', 1, 1, 1, 1, 1, 0],
        ['Member', 2, 1, 0, 0, 0, 0],
        ['Recruit', 3, 1, 0, 0, 0, 0]
    ];
    $sql_insert_role = "INSERT INTO alliance_roles (alliance_id, name, `order`, is_deletable, can_edit_profile, can_approve_membership, can_kick_members, can_manage_roles) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert_role = mysqli_prepare($link, $sql_insert_role);


    while ($alliance = mysqli_fetch_assoc($result_alliances)) {
        $alliance_id = $alliance['id'];
        $alliance_name = $alliance['name'];
        $leader_id = $alliance['leader_id'];

        if (!$leader_id) {
            echo "<p>Skipping alliance '".htmlspecialchars($alliance_name)."': No leader assigned.</p>";
            continue;
        }

        // Check if roles already exist for this alliance
        $sql_check_roles = "SELECT id FROM alliance_roles WHERE alliance_id = ? AND name = 'Supreme Commander'";
        $stmt_check = mysqli_prepare($link, $sql_check_roles);
        mysqli_stmt_bind_param($stmt_check, "i", $alliance_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $sc_role = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        $sc_role_id = null;

        if ($sc_role) {
            // Roles exist, just get the ID
            $sc_role_id = $sc_role['id'];
        } else {
            // Roles do NOT exist for this old alliance. Create them.
            echo "<p>Alliance '".htmlspecialchars($alliance_name)."' is missing roles. Creating default roles...</p>";
            foreach($default_roles as $role) {
                mysqli_stmt_bind_param($stmt_insert_role, "isiiiiii", $alliance_id, $role[0], $role[1], $role[2], $role[3], $role[4], $role[5], $role[6]);
                mysqli_stmt_execute($stmt_insert_role);
                if ($role[0] === 'Supreme Commander') {
                    $sc_role_id = mysqli_insert_id($link);
                }
            }
            $roles_created_for_alliances++;
            echo "<p>...roles created successfully.</p>";
        }

        // Now that we have the Supreme Commander role ID (either found or newly created), update the leader.
        if ($sc_role_id) {
            $sql_update = "UPDATE users SET alliance_role_id = ? WHERE id = ? AND (alliance_role_id IS NULL OR alliance_role_id != ?)";
            $stmt_update = mysqli_prepare($link, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "iii", $sc_role_id, $leader_id, $sc_role_id);
            mysqli_stmt_execute($stmt_update);
            
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                echo "<p>Assigned Supreme Commander role to leader of '".htmlspecialchars($alliance_name).".'</p>";
                $updated_leaders++;
            } else {
                echo "<p>Leader of '".htmlspecialchars($alliance_name)."' already has the correct role.</p>";
            }
            mysqli_stmt_close($stmt_update);
        } else {
             echo "<p>CRITICAL ERROR: Could not find or create Supreme Commander role for alliance '".htmlspecialchars($alliance_name)."'.</p>";
        }
    }
    mysqli_stmt_close($stmt_insert_role);


    mysqli_commit($link);
    echo "<h2>Script finished successfully.</h2>";
    echo "<p><strong>Default roles created for: " . $roles_created_for_alliances . " alliances.</strong></p>";
    echo "<p><strong>Total leaders updated: " . $updated_leaders . "</strong></p>";

} catch (Exception $e) {
    mysqli_rollback($link);
    echo "<h1>An error occurred!</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>The database transaction was rolled back. No changes were saved.</p>";
}

mysqli_close($link);
?>