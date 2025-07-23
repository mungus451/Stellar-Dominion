<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";

$user_id = $_SESSION['id'];

// Fetch attack logs
$sql_attacks = "SELECT id, defender_name, outcome, credits_stolen, battle_time FROM battle_logs WHERE attacker_id = ? ORDER BY battle_time DESC";
$stmt_attacks = mysqli_prepare($link, $sql_attacks);
mysqli_stmt_bind_param($stmt_attacks, "i", $user_id);
mysqli_stmt_execute($stmt_attacks);
$attack_logs = mysqli_stmt_get_result($stmt_attacks);

// Fetch defense logs
$sql_defenses = "SELECT id, attacker_name, outcome, credits_stolen, battle_time FROM battle_logs WHERE defender_id = ? ORDER BY battle_time DESC";
$stmt_defenses = mysqli_prepare($link, $sql_defenses);
mysqli_stmt_bind_param($stmt_defenses, "i", $user_id);
mysqli_stmt_execute($stmt_defenses);
$defense_logs = mysqli_stmt_get_result($stmt_defenses);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - War History</title>
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
                    <a href="attack.php" class="text-gray-400 hover:text-white px-3">Attack</a>
                    <a href="battle.php" class="text-gray-400 hover:text-white px-3">Training</a>
                    <a href="war_history.php" class="font-semibold text-white px-3">War History</a>
                </div>

                <div class="p-4 space-y-6">
                    <!-- Attack Log -->
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Attack Log</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr><th class="p-2">Outcome</th><th class="p-2">Attack on</th><th class="p-2">Credits Stolen</th><th class="p-2">Date</th><th class="p-2">Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($attack_logs)): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['outcome'] == 'victory' ? '<span class="text-green-400 font-bold">Victory</span>' : '<span class="text-red-400 font-bold">Defeat</span>'; ?></td>
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($log['defender_name']); ?></td>
                                        <td class="p-2 text-green-400">+<?php echo number_format($log['credits_stolen']); ?></td>
                                        <td class="p-2"><?php echo $log['battle_time']; ?></td>
                                        <td class="p-2"><a href="battle_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Defense Log -->
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Defense Log</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr><th class="p-2">Outcome</th><th class="p-2">Attack by</th><th class="p-2">Credits Lost</th><th class="p-2">Date</th><th class="p-2">Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($log = mysqli_fetch_assoc($defense_logs)): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2"><?php echo $log['outcome'] == 'defeat' ? '<span class="text-green-400 font-bold">Victory</span>' : '<span class="text-red-400 font-bold">Defeat</span>'; ?></td>
                                        <td class="p-2 font-bold text-white"><?php echo htmlspecialchars($log['attacker_name']); ?></td>
                                        <td class="p-2 text-red-400">-<?php echo number_format($log['credits_stolen']); ?></td>
                                        <td class="p-2"><?php echo $log['battle_time']; ?></td>
                                        <td class="p-2"><a href="battle_report.php?id=<?php echo $log['id']; ?>" class="text-cyan-400 hover:underline">View</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
