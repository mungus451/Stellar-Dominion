<?php
date_default_timezone_set('UTC'); 
$log_file = __DIR__ . '/cron_log.txt';

function write_log($message) {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

write_log("Cron job started.");
require_once "db_config.php";

// --- GAME DATA: STRUCTURE DEFINITIONS ---
// This array is needed here to calculate bonuses. It must match structures.php
$structures = [
    1 => ['income_bonus' => 100, 'defense_bonus' => 0, 'fortification_bonus' => 0],
    2 => ['income_bonus' => 0, 'defense_bonus' => 5, 'fortification_bonus' => 0],
    3 => ['income_bonus' => 250, 'defense_bonus' => 0, 'fortification_bonus' => 0],
    4 => ['income_bonus' => 0, 'defense_bonus' => 10, 'fortification_bonus' => 500],
    5 => ['income_bonus' => 500, 'defense_bonus' => 15, 'fortification_bonus' => 1000]
];

// Game Settings
$turn_interval_minutes = 10;
$attack_turns_per_turn = 2;
$citizens_per_turn = 1;
$credits_per_worker = 50;
$base_income_per_turn = 5000;

// Main Logic
// Fetch structure_level along with other user data
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, structure_level FROM users"; 
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        $last_updated = new DateTime($user['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            // Calculate total income bonus from structures
            $structure_income_bonus = 0;
            for ($i = 1; $i <= $user['structure_level']; $i++) {
                if (isset($structures[$i])) {
                    $structure_income_bonus += $structures[$i]['income_bonus'];
                }
            }

            // Calculate income with wealth bonus AND structure bonus
            $worker_income = $user['workers'] * $credits_per_worker;
            $total_base_income = $base_income_per_turn + $worker_income + $structure_income_bonus;
            $wealth_bonus = 1 + ($user['wealth_points'] * 0.01);
            $income_per_turn = floor($total_base_income * $wealth_bonus);
            $gained_credits = $income_per_turn * $turns_to_process;
            
            $gained_attack_turns = $turns_to_process * $attack_turns_per_turn;
            $gained_citizens = $turns_to_process * $citizens_per_turn;
            $current_utc_time_str = gmdate('Y-m-d H:i:s');

            $sql_update = "UPDATE users SET 
                                attack_turns = attack_turns + ?,
                                untrained_citizens = untrained_citizens + ?,
                                credits = credits + ?,
                                last_updated = ?
                           WHERE id = ?";
            
            if($stmt = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user['id']);
                if(mysqli_stmt_execute($stmt)){
                    write_log("Processed {$turns_to_process} turn(s) for user ID {$user['id']}. Gained {$gained_credits} credits.");
                    $users_processed++;
                } else {
                    write_log("ERROR executing update for user ID {$user['id']}: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    $final_message = "Cron job finished. Processed {$users_processed} users.";
    write_log($final_message);
    echo $final_message;
} else {
    $error_message = "ERROR fetching users: " . mysqli_error($link);
    write_log($error_message);
    echo $error_message;
}
mysqli_close($link);
?>
