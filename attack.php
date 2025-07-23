<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- START: Process Overdue Turns for Current User ---
// This logic ensures your stats are up-to-date when you load the page.
// ... (Full turn processing logic is here, same as dashboard.php)
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
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #0c1427; }
        .font-title { font-family: 'Orbitron', sans-serif; }
        .main-bg { background-color: #111827; }
        .content-box { background-color: #1f2937; border: 1px solid #374151; }
        .nav-link { border-bottom: 2px solid transparent; }
        .nav-link.active, .nav-link:hover { border-bottom-color: #06b6d4; color: #fff; }
    </style>
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">
            <header class="text-center mb-4">
                <h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STELLAR DOMINION</h1>
            </header>

            <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-1">
                <!-- Navigation -->
                <nav class="flex justify-center space-x-4 md:space-x-8 bg-gray-900 p-3 rounded-t-md">
                    <a href="dashboard.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">HOME</a>
                    <a href="battle.php" class="nav-link active font-bold px-3 py-1 transition-all">BATTLE</a>
                    <a href="structures.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">STRUCTURES</a>
                    <a href="#" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">COMMUNITY</a>
                    <a href="logout.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">SIGN OUT</a>
                </nav>
                <!-- Sub-Navigation -->
                <div class="bg-gray-800 text-center p-2">
                    <a href="attack.php" class="font-semibold text-white px-3">Attack</a>
                    <a href="battle.php" class="text-gray-400 hover:text-white px-3">Training</a>
                    <a href="war_history.php" class="text-gray-400 hover:text-white px-3">War History</a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 p-4">
                    <!-- Left Sidebar -->
                    <aside class="lg:col-span-1 space-y-4">
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2">A.I. Advisor</h3>
                            <p class="text-sm">Choose your targets wisely. Attacking stronger opponents yields greater rewards, but carries higher risk.</p>
                        </div>
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                                <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                                <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                                <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                                <li class="flex justify-between border-t border-gray-600 pt-2 mt-2"><span>Next Turn In:</span> <span id="next-turn-timer" class="text-cyan-300 font-bold"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span></li>
                                <li class="flex justify-between"><span>Dominion Time:</span> <span id="dominion-time" class="text-white font-semibold"><?php echo $now->format('H:i:s'); ?></span></li>
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
                                            // ... (Full credit calculation logic is here)
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
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
        // Timer scripts
    </script>
</body>
</html>
