<?php
/**
 * dashboard.php
 *
 * This is the main landing page for a logged-in user. It serves as a central hub
 * displaying a complete overview of the player's empire, including their resources,
 * derived combat and economic stats, and key game timers.
 *
 * A critical function of this page is to process any "overdue" turns for the player
 * right as they load the page, ensuring their resources are always up-to-date.
 */

// --- SESSION AND DATABASE SETUP ---
// Start the session to access logged-in user data.
session_start();
// Set the default timezone to UTC for consistent time calculations across the application.
date_default_timezone_set('UTC');
// If the user is not logged in, redirect them to the main page and stop script execution.
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
// Include the database configuration file to establish a connection.
require_once "db_config.php";

// --- CATCH-UP MECHANISM: PROCESS OVERDUE TURNS ---
// This block ensures that a player receives all the resources they've earned since their last activity.
// This is crucial for the idle-game aspect, as it simulates progress while the player is offline.
$user_id = $_SESSION["id"];
// Select the necessary data from the user's row to calculate earned resources.
$sql_check = "SELECT last_updated, workers, wealth_points FROM users WHERE id = ?";
if($stmt_check = mysqli_prepare($link, $sql_check)) {
    mysqli_stmt_bind_param($stmt_check, "i", $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $user_check_data = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($user_check_data) {
        // Define core game rules for resource generation.
        $turn_interval_minutes = 10;
        $attack_turns_per_turn = 2;
        $citizens_per_turn = 1;
        $credits_per_worker = 50;
        $base_income_per_turn = 5000;

        // Calculate the number of full 10-minute turns that have passed since the user's 'last_updated' timestamp.
        $last_updated = new DateTime($user_check_data['last_updated']);
        $now = new DateTime();
        $minutes_since_last_update = ($now->getTimestamp() - $last_updated->getTimestamp()) / 60;
        $turns_to_process = floor($minutes_since_last_update / $turn_interval_minutes);

        // If at least one turn has passed, calculate and award the resources.
        if ($turns_to_process > 0) {
            // Calculate total resources gained over the missed turns.
            $gained_attack_turns = $turns_to_process * $attack_turns_per_turn;
            $gained_citizens = $turns_to_process * $citizens_per_turn;
            
            // Calculate income, including the percentage bonus from the 'wealth_points' stat.
            $worker_income = $user_check_data['workers'] * $credits_per_worker;
            $total_base_income = $base_income_per_turn + $worker_income;
            $wealth_bonus = 1 + ($user_check_data['wealth_points'] * 0.01);
            $income_per_turn = floor($total_base_income * $wealth_bonus);
            $gained_credits = $income_per_turn * $turns_to_process;
            
            // Get the current UTC time to set as the new 'last_updated' timestamp.
            $current_utc_time_str = gmdate('Y-m-d H:i:s');

            // Prepare and execute the SQL query to add the gained resources and update the timestamp.
            $sql_update = "UPDATE users SET attack_turns = attack_turns + ?, untrained_citizens = untrained_citizens + ?, credits = credits + ?, last_updated = ? WHERE id = ?";
            if($stmt_update = mysqli_prepare($link, $sql_update)){
                mysqli_stmt_bind_param($stmt_update, "iiisi", $gained_attack_turns, $gained_citizens, $gained_credits, $current_utc_time_str, $user_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}
// --- END: CATCH-UP MECHANISM ---


// --- DATA FETCHING FOR DISPLAY ---
// Now that resources are updated, fetch the complete and current user data for display.
$character_data = [];
$sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $character_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);


// --- CALCULATE DERIVED STATS ---
// These are stats calculated from base values for a more meaningful display on the dashboard.
$strength_bonus = 1 + ($character_data['strength_points'] * 0.01);
$constitution_bonus = 1 + ($character_data['constitution_points'] * 0.01);
$offense_power = floor(($character_data['soldiers'] * 10) * $strength_bonus);
$defense_rating = floor(($character_data['guards'] * 10) * $constitution_bonus);
$fortification = $character_data['sentries'] * 10;
$infiltration = $character_data['spies'] * 10;
$worker_income = $character_data['workers'] * 50;
$total_base_income = 5000 + $worker_income;
$wealth_bonus = 1 + ($character_data['wealth_points'] * 0.01);
$credits_per_turn = floor($total_base_income * $wealth_bonus);

// --- TIMER CALCULATIONS ---
// This is the same logic as the catch-up mechanism, but its purpose here is to get the
// exact time remaining for the JavaScript countdown timer on the front end.
$turn_interval_minutes = 10;
$last_updated = new DateTime($character_data['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION ---
// This variable is used by 'navigation.php' to highlight the correct menu item.
$active_page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">
            
            <?php include_once 'navigation.php'; ?>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                <!-- Left Sidebar -->
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $character_data['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $character_data['attack_turns']; ?></span></li>
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

                <!-- Center Content -->
                <main class="lg:col-span-3 space-y-4">
                    <div class="content-box rounded-lg p-4 text-center">
                        <p class="font-semibold text-cyan-300">Welcome, Commander <?php echo htmlspecialchars($character_data['character_name']); ?> - <?php echo htmlspecialchars(strtoupper($character_data['race'])); ?> <?php echo htmlspecialchars(strtoupper($character_data['class'])); ?></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Dominion Stats -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Dominion Stats</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Workers:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['workers']); ?></span></li>
                                <li class="flex justify-between"><span>Income per Turn:</span> <span class="text-white font-semibold"><?php echo number_format($credits_per_turn); ?></span></li>
                                <li class="flex justify-between"><span>Net Worth:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['net_worth']); ?></span></li>
                                <li class="flex justify-between"><span>Fortification:</span> <span class="text-white font-semibold"><?php echo number_format($fortification); ?></span></li>
                                <li class="flex justify-between"><span>Infiltration:</span> <span class="text-white font-semibold"><?php echo number_format($infiltration); ?></span></li>
                            </ul>
                        </div>
                        <!-- Fleet Stats -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Fleet Stats</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Soldiers:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['soldiers']); ?></span></li>
                                <li class="flex justify-between"><span>Guards:</span> <span class="text-white font-semibold"><?php echo number_format($character_data['guards']); ?></span></li>
                                <li class="flex justify-between"><span>Offense Power:</span> <span class="text-white font-semibold"><?php echo number_format($offense_power); ?></span></li>
                                <li class="flex justify-between"><span>Defense Rating:</span> <span class="text-white font-semibold"><?php echo number_format($defense_rating); ?></span></li>
                            </ul>
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
