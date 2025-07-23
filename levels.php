<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// Fetch user's stats for the sidebar and main content
$sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, experience, level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

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

$active_page = 'levels.php'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Levels</title>
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
                     <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2">A.I. Advisor</h3>
                        <p class="text-sm">Spend proficiency points to permanently enhance your dominion's capabilities.</p>
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
                    <form action="levelup.php" method="POST" class="space-y-4">
                        <div class="content-box rounded-lg p-4 text-center">
                            <p>You currently have <span id="available-points" class="font-bold text-cyan-300 text-lg"><?php echo $user_stats['level_up_points']; ?></span> proficiency points available.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Strength -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-white">Strength (Offense)</h3>
                                <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['strength_points']; ?>%</span></p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <label class="text-sm">Add:</label>
                                    <input type="number" name="strength_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                                </div>
                            </div>
                            <!-- Constitution -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-white">Constitution (Defense)</h3>
                                <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['constitution_points']; ?>%</span></p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <label class="text-sm">Add:</label>
                                    <input type="number" name="constitution_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                                </div>
                            </div>
                            <!-- Wealth -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-white">Wealth (Income)</h3>
                                <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['wealth_points']; ?>%</span></p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <label class="text-sm">Add:</label>
                                    <input type="number" name="wealth_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                                </div>
                            </div>
                            <!-- Dexterity -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-white">Dexterity (Sentry/Spy)</h3>
                                <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['dexterity_points']; ?>%</span></p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <label class="text-sm">Add:</label>
                                    <input type="number" name="dexterity_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                                </div>
                            </div>
                            <!-- Charisma -->
                            <div class="content-box rounded-lg p-4">
                                <h3 class="font-title text-white">Charisma (Reduced Prices)</h3>
                                <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['charisma_points']; ?>%</span></p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <label class="text-sm">Add:</label>
                                    <input type="number" name="charisma_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                                </div>
                            </div>
                        </div>
                        <div class="content-box rounded-lg p-4 flex justify-between items-center mt-4">
                            <div>
                                <p>Total Points to Spend: <span id="total-spent" class="font-bold text-white">0</span></p>
                            </div>
                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Spend Points</button>
                        </div>
                    </form>
                </main>
            </div>
            </div> <!-- This closes the .main-bg div from navigation.php -->
        </div>
    </div>
    <script>
        lucide.createIcons();
        const availablePointsEl = document.getElementById('available-points');
        const totalSpentEl = document.getElementById('total-spent');
        const inputs = document.querySelectorAll('.point-input');
        
        function updateTotal() {
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            totalSpentEl.textContent = total;
            
            if (total > parseInt(availablePointsEl.textContent)) {
                totalSpentEl.classList.add('text-red-500');
            } else {
                totalSpentEl.classList.remove('text-red-500');
            }
        }
        
        inputs.forEach(input => input.addEventListener('input', updateTotal));

        const timerDisplay = document.getElementById('next-turn-timer');
        let totalSeconds = <?php echo $seconds_until_next_turn; ?>;
        const interval = setInterval(() => {
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Processing...";
                clearInterval(interval);
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?t=' + new Date().getTime();
                }, 1500); 
                return;
            }
            totalSeconds--;
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }, 1000);

        const timeDisplay = document.getElementById('dominion-time');
        let serverTime = new Date();
        serverTime.setUTCHours(<?php echo $now->format('H'); ?>, <?php echo $now->format('i'); ?>, <?php echo $now->format('s'); ?>);

        setInterval(() => {
            serverTime.setSeconds(serverTime.getSeconds() + 1);
            const hours = String(serverTime.getUTCHours()).padStart(2, '0');
            const minutes = String(serverTime.getUTCMinutes()).padStart(2, '0');
            const seconds = String(serverTime.getUTCSeconds()).padStart(2, '0');
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    </script>
</body>
</html>
