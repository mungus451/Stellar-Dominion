<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";

// Fetch user's level, xp, and points
$sql = "SELECT level, experience, level_up_points FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

$xp_for_next_level = $user_stats['level'] * 1000; // XP needed for next level

$active_page = 'structures.php'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Structures</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php include_once 'navigation.php'; ?>
            
            <div class="p-4"> <!-- Added padding to match other pages -->
                <form action="levelup.php" method="POST" class="space-y-4">
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Level Up</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-gray-800 p-3 rounded-md">
                                <p class="text-sm">Current Level</p>
                                <p class="text-lg font-bold text-white"><?php echo $user_stats['level']; ?></p>
                            </div>
                            <div class="bg-gray-800 p-3 rounded-md">
                                <p class="text-sm">Experience</p>
                                <p class="text-lg font-bold text-white"><?php echo number_format($user_stats['experience']); ?> / <?php echo number_format($xp_for_next_level); ?></p>
                            </div>
                            <div class="bg-gray-800 p-3 rounded-md">
                                <p class="text-sm">Available Points</p>
                                <p id="available-points" class="text-lg font-bold text-white"><?php echo $user_stats['level_up_points']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-4">
                         <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Spend Points</h3>
                         <p class="text-sm mb-4">Each point adds 1 unit. Distribute your available points below.</p>
                         <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label for="soldiers" class="font-bold text-white">Offense (Soldiers)</label>
                                <input type="number" id="soldiers" name="soldiers" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 point-input">
                            </div>
                             <div class="flex items-center justify-between">
                                <label for="guards" class="font-bold text-white">Defense (Guards)</label>
                                <input type="number" id="guards" name="guards" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 point-input">
                            </div>
                             <div class="flex items-center justify-between">
                                <label for="sentries" class="font-bold text-white">Fortification (Sentries)</label>
                                <input type="number" id="sentries" name="sentries" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 point-input">
                            </div>
                             <div class="flex items-center justify-between">
                                <label for="spies" class="font-bold text-white">Infiltration (Spies)</label>
                                <input type="number" id="spies" name="spies" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-24 text-center p-1 point-input">
                            </div>
                         </div>
                    </div>
                     <div class="content-box rounded-lg p-4 flex justify-between items-center">
                        <div>
                            <p>Total Points to Spend: <span id="total-spent" class="font-bold text-white">0</span></p>
                        </div>
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Upgrade Stats</button>
                    </div>
                </form>
            </div>
            </div> <!-- This closes the .main-bg div from navigation.php -->
        </div>
    </div>
    <script src="js/main.js" defer></script>
</body>
</html>
