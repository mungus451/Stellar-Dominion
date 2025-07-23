<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

require_once "db_config.php";
date_default_timezone_set('UTC');

// Input Validation
$attacker_id = $_SESSION["id"];
$defender_id = isset($_POST['defender_id']) ? (int)$_POST['defender_id'] : 0;
$attack_turns = isset($_POST['attack_turns']) ? (int)$_POST['attack_turns'] : 0;
if ($defender_id <= 0 || $attack_turns < 1 || $attack_turns > 10) { header("location: attack.php"); exit; }

// Level Up Function
function check_and_process_levelup($user_id, $link) {
    $sql = "SELECT level, experience FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $xp_for_next_level = $user['level'] * 1000;

    if ($user['experience'] >= $xp_for_next_level) {
        $points_gained = 1; // 1 point per level up
        $sql_levelup = "UPDATE users SET level = level + 1, experience = experience - ?, level_up_points = level_up_points + ? WHERE id = ?";
        $stmt_levelup = mysqli_prepare($link, $sql_levelup);
        mysqli_stmt_bind_param($stmt_levelup, "iii", $xp_for_next_level, $points_gained, $user_id);
        mysqli_stmt_execute($stmt_levelup);
        mysqli_stmt_close($stmt_levelup);
    }
}

// Battle Logic
mysqli_begin_transaction($link);
try {
    // Get attacker's data including new strength points
    $sql_attacker = "SELECT character_name, attack_turns, soldiers, credits, strength_points FROM users WHERE id = ? FOR UPDATE";
    $stmt_attacker = mysqli_prepare($link, $sql_attacker);
    mysqli_stmt_bind_param($stmt_attacker, "i", $attacker_id);
    mysqli_stmt_execute($stmt_attacker);
    $attacker = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_attacker));
    mysqli_stmt_close($stmt_attacker);

    // Get defender's data including new constitution points
    $sql_defender = "SELECT character_name, guards, credits, constitution_points FROM users WHERE id = ? FOR UPDATE";
    $stmt_defender = mysqli_prepare($link, $sql_defender);
    mysqli_stmt_bind_param($stmt_defender, "i", $defender_id);
    mysqli_stmt_execute($stmt_defender);
    $defender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_defender));
    mysqli_stmt_close($stmt_defender);

    if ($attacker['attack_turns'] < $attack_turns) { throw new Exception("Not enough attack turns."); }

    // Battle Calculation with stat bonuses
    $attacker_base_damage = 0;
    for ($i = 0; $i < $attacker['soldiers'] * $attack_turns; $i++) { $attacker_base_damage += rand(8, 12); }
    $strength_bonus = 1 + ($attacker['strength_points'] * 0.01);
    $attacker_damage = floor($attacker_base_damage * $strength_bonus);

    $defender_base_damage = 0;
    for ($i = 0; $i < $defender['guards']; $i++) { $defender_base_damage += rand(8, 12); }
    $constitution_bonus = 1 + ($defender['constitution_points'] * 0.01);
    $defender_damage = floor($defender_base_damage * $constitution_bonus);

    // XP Calculation
    $attacker_xp_gained = floor($attacker_damage / 10);
    $defender_xp_gained = floor($defender_damage / 10);

    $credits_stolen = 0;
    $outcome = 'defeat';

    if ($attacker_damage > $defender_damage) {
        $outcome = 'victory';
        $steal_percentage = min(0.1 * $attack_turns, 1.0);
        $credits_stolen = floor($defender['credits'] * $steal_percentage);
        
        $sql_update_attacker = "UPDATE users SET credits = credits + ?, experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_attacker);
        mysqli_stmt_bind_param($stmt_update, "iii", $credits_stolen, $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $sql_update_defender = "UPDATE users SET credits = credits - ?, experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_defender);
        mysqli_stmt_bind_param($stmt_update, "iii", $credits_stolen, $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    } else {
        // Defeat - still award XP
        $sql_update_attacker = "UPDATE users SET experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_attacker);
        mysqli_stmt_bind_param($stmt_update, "ii", $attacker_xp_gained, $attacker_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        $sql_update_defender = "UPDATE users SET experience = experience + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update_defender);
        mysqli_stmt_bind_param($stmt_update, "ii", $defender_xp_gained, $defender_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
    
    $sql_deduct_turns = "UPDATE users SET attack_turns = attack_turns - ? WHERE id = ?";
    $stmt_deduct = mysqli_prepare($link, $sql_deduct_turns);
    mysqli_stmt_bind_param($stmt_deduct, "ii", $attack_turns, $attacker_id);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    check_and_process_levelup($attacker_id, $link);
    check_and_process_levelup($defender_id, $link);

    // Log the battle
    $sql_log = "INSERT INTO battle_logs (attacker_id, defender_id, attacker_name, defender_name, outcome, credits_stolen, attack_turns_used, attacker_damage, defender_damage, attacker_xp_gained, defender_xp_gained) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = mysqli_prepare($link, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisssiiiiii", 
        $attacker_id, $defender_id, $attacker['character_name'], $defender['character_name'], 
        $outcome, $credits_stolen, $attack_turns, $attacker_damage, $defender_damage, $attacker_xp_gained, $defender_xp_gained
    );
    mysqli_stmt_execute($stmt_log);
    $battle_log_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt_log);

    mysqli_commit($link);
    header("location: battle_report.php?id=" . $battle_log_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['attack_result'] = "Attack failed: " . $e->getMessage();
    header("location: attack.php");
    exit;
}
?>
