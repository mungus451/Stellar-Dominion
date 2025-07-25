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
require_once "game_data.php"; // Include upgrade definitions

// Game Settings
$turn_interval_minutes = 10;
$attack_turns_per_turn = 2;
$credits_per_worker = 50;
$base_income_per_turn = 5000;

// Main Logic
// Fetch new upgrade columns along with other user data
$sql_select_users = "SELECT id, last_updated, workers, wealth_points, economy_upgrade_level, population_level FROM users"; 
$result = mysqli_query($link, $sql_select_users);

if ($result) {
    $users_processed = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        $last_updated = new DateTime($user['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            // Calculate total economic bonus from upgrades
            $total_economy_bonus_pct = 0;
            for ($i = 1; $i <= $user['economy_upgrade_level']; $i++) {
                $total_economy_bonus_pct += $upgrades['economy']['levels'][$i]['bonuses']['income'] ?? 0;
            }
            $economy_upgrade_multiplier = 1 + ($total_economy_bonus_pct / 100);

            // Calculate total population bonus from upgrades
            $citizens_per_turn = 1; // Base value
            for ($i = 1; $i <= $user['population_level']; $i++) {
                $citizens_per_turn += $upgrades['population']['levels'][$i]['bonuses']['citizens'] ?? 0;
            }
            
            // Calculate income with all bonuses
            $worker_income = $user['workers'] * $credits_per_worker;
            $total_base_income = $base_income_per_turn + $worker_income;
            $wealth_bonus = 1 + ($user['wealth_points'] * 0.01);
            $income_per_turn = floor(($total_base_income * $wealth_bonus) * $economy_upgrade_multiplier);
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