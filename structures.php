<?php
/**
 * structures.php
 *
 * This page allows players to build and upgrade permanent structures that provide
 * passive bonuses to their empire, such as increased income or defensive capabilities.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }
require_once "db_config.php";
date_default_timezone_set('UTC');

$user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, structure_level FROM users WHERE id = ?";
if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
mysqli_close($link);


// --- GAME DATA: STRUCTURE DEFINITIONS ---
// This array now includes specific bonuses for income, defense, and fortification.
$structures = [
    1 => [
        'name' => 'Foundation Outpost',
        'level_req' => 1,
        'cost' => 50000,
        'income_bonus' => 100,
        'defense_bonus' => 0,
        'fortification_bonus' => 0,
        'flavor_text' => 'A basic frontier camp—brings in vital resources.'
    ],
    2 => [
        'name' => 'Recon Tower',
        'level_req' => 5,
        'cost' => 250000,
        'income_bonus' => 0,
        'defense_bonus' => 5, // Represents a 5% bonus
        'fortification_bonus' => 0,
        'flavor_text' => 'High perch for scouts; early warning and ranged cover.'
    ],
    3 => [
        'name' => 'Supply Depot',
        'level_req' => 10,
        'cost' => 1000000,
        'income_bonus' => 250,
        'defense_bonus' => 0,
        'fortification_bonus' => 0,
        'flavor_text' => 'Central hub for logistics and modest stockpiling.'
    ],
    4 => [
        'name' => 'Shield Wall',
        'level_req' => 15,
        'cost' => 5000000,
        'income_bonus' => 0,
        'defense_bonus' => 10, // Represents a 10% bonus
        'fortification_bonus' => 500,
        'flavor_text' => 'Reinforced barriers that repel assaults effectively.'
    ],
    5 => [
        'name' => 'Bastion Citadel',
        'level_req' => 20,
        'cost' => 20000000,
        'income_bonus' => 500,
        'defense_bonus' => 15, // Represents a 15% bonus
        'fortification_bonus' => 1000,
        'flavor_text' => 'A heavily fortified stronghold with integrated vaults.'
    ]
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
$active_page = 'structures.php';
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
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Structure Upgrades</h3>
                        <?php if(isset($_SESSION['build_message'])): ?>
                            <div class="bg-green-900 border border-green-500/50 text-green-300 p-3 rounded-md text-center mb-4">
                                <?php echo $_SESSION['build_message']; unset($_SESSION['build_message']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Structure</th>
                                        <th class="p-2">Level Req.</th>
                                        <th class="p-2">Bonuses</th>
                                        <th class="p-2">Cost</th>
                                        <th class="p-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($structures as $level => $structure): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2">
                                            <p class="font-bold text-white"><?php echo $structure['name']; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $structure['flavor_text']; ?></p>
                                        </td>
                                        <td class="p-2"><?php echo $structure['level_req']; ?></td>
                                        <td class="p-2 text-cyan-300">
                                            <?php 
                                                // Build the bonus description string dynamically
                                                $bonuses = [];
                                                if ($structure['income_bonus'] > 0) $bonuses[] = '+' . number_format($structure['income_bonus']) . ' C/Turn';
                                                if ($structure['defense_bonus'] > 0) $bonuses[] = '+' . $structure['defense_bonus'] . '% Defense';
                                                if ($structure['fortification_bonus'] > 0) $bonuses[] = '+' . number_format($structure['fortification_bonus']) . ' Fort';
                                                echo implode('<br>', $bonuses);
                                            ?>
                                        </td>
                                        <td class="p-2"><?php echo number_format($structure['cost']); ?></td>
                                        <td class="p-2">
                                            <?php
                                            if ($user_stats['structure_level'] >= $level) {
                                                echo '<span class="font-bold text-green-400">Owned</span>';
                                            } elseif ($user_stats['structure_level'] == $level - 1) {
                                                if ($user_stats['level'] >= $structure['level_req'] && $user_stats['credits'] >= $structure['cost']) {
                                                    echo '<form action="build_structure.php" method="POST">';
                                                    echo '<input type="hidden" name="structure_level" value="' . $level . '">';
                                                    echo '<button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Build</button>';
                                                    echo '</form>';
                                                } else {
                                                    echo '<button class="bg-gray-600 text-gray-400 font-bold py-1 px-3 rounded-md text-xs cursor-not-allowed">Unavailable</button>';
                                                }
                                            } else {
                                                echo '<span class="text-gray-500">Locked</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
