<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";

// Fetch user's stats for display
$sql = "SELECT level, experience, level_up_points, strength_points, constitution_points, wealth_points, dexterity_points, charisma_points FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

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
                <!-- Main Navigation -->
                <nav class="flex justify-center space-x-4 md:space-x-8 bg-gray-900 p-3 rounded-t-md">
                    <a href="dashboard.php" class="nav-link active font-bold px-3 py-1 transition-all">HOME</a>
                    <a href="battle.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">BATTLE</a>
                    <a href="structures.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">STRUCTURES</a>
                    <a href="#" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">COMMUNITY</a>
                    <a href="logout.php" class="nav-link text-gray-400 hover:text-white px-3 py-1 transition-all">SIGN OUT</a>
                </nav>
                
                <!-- Sub-Navigation -->
                <div class="bg-gray-800 text-center p-2">
                    <a href="dashboard.php" class="text-gray-400 hover:text-white px-3">Dashboard</a>
                    <a href="levels.php" class="font-semibold text-white px-3">Levels</a>
                </div>

                <form action="levelup.php" method="POST" class="p-4 space-y-4">
                    <div class="content-box rounded-lg p-4 text-center">
                        <p>You currently have <span id="available-points" class="font-bold text-cyan-300 text-lg"><?php echo $user_stats['level_up_points']; ?></span> proficiency points available.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Strength -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-white">Strength (Offense)</h3>
                            <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['strength_points']; ?>%</span></p>
                            <div class="flex items-center space-x-2 mt-2">
                                <label for="strength_points" class="text-sm">Add:</label>
                                <input type="number" name="strength_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                            </div>
                        </div>
                        <!-- Constitution -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-white">Constitution (Defense)</h3>
                            <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['constitution_points']; ?>%</span></p>
                             <div class="flex items-center space-x-2 mt-2">
                                <label for="constitution_points" class="text-sm">Add:</label>
                                <input type="number" name="constitution_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                            </div>
                        </div>
                        <!-- Wealth -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-white">Wealth (Income)</h3>
                            <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['wealth_points']; ?>%</span></p>
                             <div class="flex items-center space-x-2 mt-2">
                                <label for="wealth_points" class="text-sm">Add:</label>
                                <input type="number" name="wealth_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                            </div>
                        </div>
                        <!-- Dexterity -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-white">Dexterity (Sentry/Spy)</h3>
                            <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['dexterity_points']; ?>%</span></p>
                             <div class="flex items-center space-x-2 mt-2">
                                <label for="dexterity_points" class="text-sm">Add:</label>
                                <input type="number" name="dexterity_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                            </div>
                        </div>
                         <!-- Charisma -->
                        <div class="content-box rounded-lg p-4">
                            <h3 class="font-title text-white">Charisma (Reduced Prices)</h3>
                            <p class="text-sm">Current Bonus: <span class="font-bold text-cyan-300"><?php echo $user_stats['charisma_points']; ?>%</span></p>
                             <div class="flex items-center space-x-2 mt-2">
                                <label for="charisma_points" class="text-sm">Add:</label>
                                <input type="number" name="charisma_points" min="0" value="0" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 point-input">
                            </div>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-4 flex justify-between items-center">
                        <div>
                            <p>Total Points to Spend: <span id="total-spent" class="font-bold text-white">0</span></p>
                        </div>
                        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg transition-colors">Spend Points</button>
                    </div>
                </form>
            </div>
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
    </script>
</body>
</html>
