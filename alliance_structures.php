<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";
require_once "lib/game_data.php"; // Contains structure definitions

$user_id = $_SESSION['id'];
$active_page = 'alliance_structures.php'; // For navigation highlighting

// Fetch user's alliance and role to check permissions
// ... (similar permission check as alliance_roles.php)

// Fetch alliance data, including bank balance
// Fetch current alliance structures and bank transaction logs
// ...

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance Structures</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once 'includes/navigation.php'; ?>
        <main class="space-y-4">
            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                <p class="text-lg">Current Funds: <span class="font-bold text-yellow-300"><?php echo number_format($alliance['bank_credits']); ?> Credits</span></p>
            </div>

            <div class="content-box rounded-lg p-6">
                <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($alliance_structures_definitions as $key => $structure): ?>
                        <div class="bg-gray-800 p-4 rounded-lg">
                            <h3 class="font-bold text-white"><?php echo $structure['name']; ?></h3>
                            <p class="text-sm text-gray-400"><?php echo $structure['description']; ?></p>
                            <p class="text-sm mt-2">Bonus: <span class="text-cyan-300"><?php echo $structure['bonus_text']; ?></span></p>
                            <p class="text-sm">Cost: <span class="text-yellow-300"><?php echo number_format($structure['cost']); ?></span></p>
                            <div class="mt-3">
                                <?php // Logic to show current level or purchase button for leaders ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="content-box rounded-lg p-6">
                 <h3 class="font-title text-xl text-cyan-400 border-b border-gray-600 pb-2 mb-3">Recent Bank Activity</h3>
                 <?php // Table to display recent bank logs ?>
            </div>
        </main>
    </div>
</div>
</body>
</html>