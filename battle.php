<?php
/**
 * battle.php
 *
 * This page is where players train their military and economic units. It displays
 * the player's current resources (credits and untrained citizens) and provides a
 * form to specify how many of each unit type they wish to train.
 *
 * The form submission is handled by 'train.php'.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
// Fetch the user's core stats for the sidebar display.
$sql_user_stats = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
$stmt_user_stats = mysqli_prepare($link, $sql_user_stats);
mysqli_stmt_bind_param($stmt_user_stats, "i", $user_id);
mysqli_stmt_execute($stmt_user_stats);
$user_stats_result = mysqli_stmt_get_result($stmt_user_stats);
$user_stats = mysqli_fetch_assoc($user_stats_result);
mysqli_stmt_close($stmt_user_stats);

// Fetch the user's current unit counts to display alongside the training inputs.
$sql_resources = "SELECT untrained_citizens, credits, soldiers, guards, sentries, spies, workers FROM users WHERE id = ?";
if($stmt_resources = mysqli_prepare($link, $sql_resources)){
    mysqli_stmt_bind_param($stmt_resources, "i", $user_id);
    mysqli_stmt_execute($stmt_resources);
    $result = mysqli_stmt_get_result($stmt_resources);
    $user_resources = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_resources);
}
mysqli_close($link);


// --- GAME DATA ---
// Define the base credit cost for each trainable unit.
// This is used for display purposes on this page. The actual cost calculation,
// including charisma discounts, is handled in 'train.php'.
$unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];

// --- TIMER CALCULATIONS ---
$turn_interval_minutes = 10;
$last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
$now = new DateTime('now', new DateTimeZone('UTC'));
$time_since_last_update = $now->getTimestamp() - $last_updated->getTimestamp();
$seconds_into_current_turn = $time_since_last_update % ($turn_interval_minutes * 60);
$seconds_until_next_turn = ($turn_interval_minutes * 60) - $seconds_into_current_turn;
if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
$minutes_until_next_turn = floor($seconds_until_next_turn / 60);
$seconds_remainder = $seconds_until_next_turn % 60;

// --- PAGE IDENTIFICATION ---
$active_page = 'battle.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Training</title>
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

                <!-- Main Content: Training Form -->
                <main class="lg:col-span-3">
                    <form action="train.php" method="POST" class="space-y-4">
                        <?php
                        // Display any training-related error messages (e.g., "Not enough credits")
                        // that were set in 'train.php' and stored in the session.
                        if(isset($_SESSION['training_error'])):
                        ?>
                            <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                                <?php echo $_SESSION['training_error']; unset($_SESSION['training_error']); // Unset the error after displaying it. ?>
                            </div>
                        <?php endif; ?>

                        <!-- Unit Training Sections -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Economy</h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">Worker</p>
                                    <p class="text-xs">Cost: <?php echo number_format($unit_costs['workers']); ?> Credits</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs">Owned: <?php echo number_format($user_resources['workers']); ?></p>
                                    <input type="number" name="workers" min="0" placeholder="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Offense</h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">Soldier</p>
                                    <p class="text-xs">Cost: <?php echo number_format($unit_costs['soldiers']); ?> Credits</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs">Owned: <?php echo number_format($user_resources['soldiers']); ?></p>
                                    <input type="number" name="soldiers" min="0" placeholder="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Defense</h3>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <p class="font-bold text-white">Guard</p>
                                    <p class="text-xs">Cost: <?php echo number_format($unit_costs['guards']); ?> Credits</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs">Owned: <?php echo number_format($user_resources['guards']); ?></p>
                                    <input type="number" name="guards" min="0" placeholder="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">Sentry</p>
                                    <p class="text-xs">Cost: <?php echo number_format($unit_costs['sentries']); ?> Credits</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs">Owned: <?php echo number_format($user_resources['sentries']); ?></p>
                                    <input type="number" name="sentries" min="0" placeholder="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Infiltration</h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">Spy</p>
                                    <p class="text-xs">Cost: <?php echo number_format($unit_costs['spies']); ?> Credits</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs">Owned: <?php echo number_format($user_resources['spies']); ?></p>
                                    <input type="number" name="spies" min="0" placeholder="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1">
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4 text-center">
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Train Units</button>
                        </div>
                    </form>
                </main>
            </div>
            </div> <!-- This closes the .main-bg div from navigation.php -->
        </div>
    </div>
    <script src="js/main.js" defer></script>
</body>
</html>
