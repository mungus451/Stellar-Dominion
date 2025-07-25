<?php
/**
 * view_profile.php
 *
 * Displays a public profile for a selected user.
 * If the viewer is logged in, it provides an interface to attack the user.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$viewer_id = $is_logged_in ? $_SESSION['id'] : 0;
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id <= 0) {
    header("location: /attack.php"); // Or to a 404 page
    exit;
}

// --- DATA FETCHING for the profile being viewed ---
$sql = "SELECT id, character_name, email, race, class, credits, level, net_worth, last_updated, workers, wealth_points, soldiers, guards, sentries, spies, avatar_path, biography FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profile_data) {
    header("location: /attack.php"); // User not found, redirect
    exit;
}

// --- CALCULATIONS FOR DISPLAY ---
// Estimate current credits
$last_upd = new DateTime($profile_data['last_updated']);
$now = new DateTime();
$mins_since_upd = ($now->getTimestamp() - $last_upd->getTimestamp()) / 60;
$turns_to_proc = floor($mins_since_upd / 10);
$estimated_credits = $profile_data['credits'];
if ($turns_to_proc > 0) {
    $w_income = $profile_data['workers'] * 50;
    $b_income = 5000 + $w_income;
    $wlth_bonus = 1 + ($profile_data['wealth_points'] * 0.01);
    $inc_per_turn = floor($b_income * $wlth_bonus);
    $estimated_credits += $inc_per_turn * $turns_to_proc;
}

// Army Size
$army_size = $profile_data['soldiers'] + $profile_data['guards'];

// Determine if the attack interface should be shown
$can_attack = $is_logged_in && ($viewer_id != $profile_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - Profile of <?php echo htmlspecialchars($profile_data['character_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php
            // Show navigation only if the viewer is logged in
            if ($is_logged_in) {
                $active_page = 'attack.php'; // Highlight 'BATTLE' in the nav
                include_once 'includes/navigation.php';
            } else {
                // You could include a simpler public header here if desired
                echo '<header class="text-center mb-4"><h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STELLAR DOMINION</h1></header>';
            }
            ?>

            <main class="content-box rounded-lg p-6 mt-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-1 text-center">
                        <img src="<?php echo !empty($profile_data['avatar_path']) ? htmlspecialchars($profile_data['avatar_path']) : 'https://via.placeholder.com/200'; ?>" alt="Avatar" class="w-48 h-48 rounded-full mx-auto border-4 border-gray-600 object-cover shadow-lg">
                        <h2 class="font-title text-3xl text-white mt-4"><?php echo htmlspecialchars($profile_data['character_name']); ?></h2>
                        <p class="text-cyan-300"><?php echo htmlspecialchars(strtoupper($profile_data['race'])) . ' ' . htmlspecialchars(strtoupper($profile_data['class'])); ?></p>
                        <p class="text-sm mt-1">Level: <?php echo $profile_data['level']; ?></p>
                    </div>

                    <div class="md:col-span-2 space-y-4">
                        <?php if ($can_attack): ?>
                        <div class="bg-gray-800 rounded-lg p-4">
                             <h3 class="font-title text-lg text-red-400">Engage Target</h3>
                            <form action="process_attack.php" method="POST" class="flex items-center justify-between mt-2">
                                <input type="hidden" name="defender_id" value="<?php echo $profile_data['id']; ?>">
                                <div class="text-sm">
                                    <label for="attack_turns">Attack Turns (1-10):</label>
                                    <input type="number" id="attack_turns" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 ml-2">
                                </div>
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">Launch Attack</button>
                            </form>
                        </div>
                        <?php endif; ?>

                        <div class="bg-gray-800 rounded-lg p-4">
                             <h3 class="font-title text-lg text-cyan-400">Statistics</h3>
                             <ul class="mt-2 space-y-2 text-sm">
                                <li class="flex justify-between"><span>Army Size:</span> <span class="text-white font-semibold"><?php echo number_format($army_size); ?> units</span></li>
                                <li class="flex justify-between"><span>Workers:</span> <span class="text-white font-semibold"><?php echo number_format($profile_data['workers']); ?></span></li>
                                <li class="flex justify-between"><span>Credits (Est.):</span> <span class="text-white font-semibold"><?php echo number_format($estimated_credits); ?></span></li>
                                <li class="flex justify-between"><span>Net Worth:</span> <span class="text-white font-semibold"><?php echo number_format($profile_data['net_worth']); ?></span></li>
                             </ul>
                        </div>

                        <div class="bg-gray-800 rounded-lg p-4">
                            <h3 class="font-title text-lg text-cyan-400">Biography</h3>
                            <p class="mt-2 text-sm italic">
                                <?php echo !empty($profile_data['biography']) ? nl2br(htmlspecialchars($profile_data['biography'])) : 'This commander has not yet written their saga.'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </main>
            <?php if ($is_logged_in) echo '</div>'; // Closes .main-bg div from navigation.php ?>
        </div>
    </div>
    <?php if ($is_logged_in): ?>
    <script src="assets/js/main.js" defer></script>
    <?php endif; ?>
    <script>lucide.createIcons();</script>
</body>
</html>