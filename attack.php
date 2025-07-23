<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- START: Process Overdue Turns for Current User ---
$sql_check = "SELECT last_updated, workers, wealth_points FROM users WHERE id = ?";
if($stmt_check = mysqli_prepare($link, $sql_check)) {
    mysqli_stmt_bind_param($stmt_check, "i", $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $user_check_data = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($user_check_data) {
        $turn_interval_minutes = 10;
        $attack_turns_per_turn = 2;
        $citizens_per_turn = 1;
        $credits_per_worker = 50;
        $base_income_per_turn = 5000;

        $last_updated = new DateTime($user_check_data['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        if ($turns_to_process > 0) {
            $gained_attack_turns = $turns_to_process * $attack_turns_per_turn;
            $gained_citizens = $turns_to_process * $citizens_per_turn;
            
            $worker_income = $user_check_data['workers'] * $credits_per_worker;
            $total_base_income = $base_income_per_turn + $worker_income;
            $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
            $income_per_turn = floor($total_base_income * $wealth_bonus);
            $gained_credits = $income_per_turn * $turns_to_process;
            
            $current_utc_time_str = gmdate('Y-m-d H:i:s');

            $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
            if($stmt_update = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt_update, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}
// --- END: Process Overdue Turns ---

// Fetch user's stats for the sidebar
$sql_user_stats = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats_result = mysqli_stmt_get_result($stmt_user_stats);
$user_stats = mysqli_fetch_assoc($user_stats_result);
mysqli_stmt_close($stmt_user_stats);

// Fetch all other users to display as potential targets
$sql_targets = "SELECT id, character_name, credits, level, last_updated, workers, wealth_points FROM users WHERE id != ?";
$stmt_targets = mysqli_prepare($link, $sql_targets);
mysqli_stmt_bind_param($stmt_targets, "i", $user_id);
mysqli_stmt_execute($stmt_targets);
$targets_result = mysqli_stmt_get_result($stmt_targets);

// Calculate time for the timer
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

$active_page = 'attack.php'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Attack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once 'navigation.php'; ?>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <!-- Left Sidebar -->
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>">
                                    <?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?>
                                </span>
                            </li>
                            <li class="flex justify-between">
                                <span>Dominion Time:</span>
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>">
                                    <?php echo $now->format('H:i:s'); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="lg:col-span-3">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Attack Users</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Commander</th>
                                        <th class="p-2">Credits</th>
                                        <th class="p-2">Level</th>
                                        <th class="p-2 text-center">Turns (1-10)</th>
                                        <th class="p-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($target = mysqli_fetch_assoc($targets_result)): ?>
                                    <?php
                                        // Calculate the target's current credits for display
                                        $turn_interval_minutes = 10;
                                        $credits_per_worker = 50;
                                        $base_income_per_turn = 5000;

                                        $target_last_updated = new DateTime($target['last_updated']);
                                        $now_for_target = new DateTime();
                                        $minutes_since_target_update = ($now_for_target->getTimestamp() - $target_last_updated->getTimestamp()) / 60;
                                        $target_turns_to_process = floor($minutes_since_target_update / $turn_interval_minutes);
                                        
                                        $target_current_credits = $target['credits'];
                                        if ($target_turns_to_process > 0) {
                                            $worker_income = $target['workers'] * $credits_per_worker;
                                            $total_base_income = $base_income_per_turn + $worker_income;
                                            $wealth_bonus = 1 + ($target['wealth_points'] * 0.01);
                                            $income_per_turn = floor($total_base_income * $wealth_bonus);
                                            $gained_credits = $income_per_turn * $target_turns_to_process;
                                            $target_current_credits += $gained_credits;
                                        }
                                    ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($target['character_name']); ?></td>
                                        <td class="p-2"><?php echo number_format($target_current_credits); ?></td>
                                        <td class="p-2"><?php echo $target['level']; ?></td>
                                        <form action="process_attack.php" method="POST">
                                            <input type="hidden" name="defender_id" value="<?php echo $target['id']; ?>">
                                            <td class="p-2 text-center">
                                                <input type="number" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1">
                                            </td>
                                            <td class="p-2 text-right">
                                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs">Attack</button>
                                            </td>
                                        </form>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
            </div> <!-- This closes the .main-bg div from navigation.php -->
        </div>
    </div>
    <script src="js/main.js" defer></script>
</body>
</html>
