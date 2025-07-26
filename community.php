<?php
// --- SESSION SETUP ---
session_start();
date_default_timezone_set('UTC');
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Initialize variables for logged-in users
$user_stats = null;
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));

if ($is_logged_in) {
    require_once "lib/db_config.php";
    $user_id = $_SESSION['id'];

    // --- DATA FETCHING ---
    // Fetch user stats for sidebar
    $sql = "SELECT credits, untrained_citizens, level, attack_turns, last_updated FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
    mysqli_close($link);

    // --- TIMER CALCULATIONS ---
    if ($user_stats) {
        $turn_interval_minutes = 10;
        $last_updated = new DateTime($user_stats['last_updated'], new DateTimeZone('UTC'));
        $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
        if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
        $minutes_until_next_turn = floor($seconds_until_next_turn / 60);
        $seconds_remainder = $seconds_until_next_turn % 60;
    }
}

// --- PAGE IDENTIFICATION ---
$active_page = 'community.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stellar Dominion - News</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php if ($is_logged_in): ?>
                <?php include_once 'includes/navigation.php'; ?>
            <?php else: ?>
                <header class="bg-dark-translucent backdrop-blur-md border-b border-cyan-400/20 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center">
                        <a href="index.html" class="text-3xl font-bold tracking-wider font-title text-cyan-400">STELLAR DOMINION</a>
                        <nav class="hidden md:flex space-x-8 text-lg">
                            <a href="index.html#features" class="hover:text-cyan-300 transition-colors">Features</a>
                            <a href="index.html#gameplay" class="hover:text-cyan-300 transition-colors">Gameplay</a>
                            <a href="community.php" class="hover:text-cyan-300 transition-colors">Community</a>
                        </nav>
                        <button id="mobile-menu-button" class="md:hidden focus:outline-none">
                            <i data-lucide="menu" class="text-white"></i>
                        </button>
                    </div>
                    <div id="mobile-menu" class="hidden md:hidden bg-dark-translucent mt-4 rounded-lg">
                        <nav class="flex flex-col items-center space-y-4 px-6 py-4">
                            <a href="index.html#features" class="hover:text-cyan-300 transition-colors">Features</a>
                            <a href="index.html#gameplay" class="hover:text-cyan-300 transition-colors">Gameplay</a>
                            <a href="community.php" class="hover:text-cyan-300 transition-colors">Community</a>
                        </nav>
                    </div>
                </header>
            <?php endif; ?>

            <div class="grid grid-cols-1 <?php if ($is_logged_in) echo 'lg:grid-cols-4'; ?> gap-4">
                <?php if ($is_logged_in && $user_stats): ?>
                <aside class="lg:col-span-1 space-y-4">
                    <?php include 'includes/advisor.php'; ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex justify-between"><span>Credits:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['credits']); ?></span></li>
                            <li class="flex justify-between"><span>Untrained Citizens:</span> <span class="text-white font-semibold"><?php echo number_format($user_stats['untrained_citizens']); ?></span></li>
                            <li class="flex justify-between"><span>Level:</span> <span class="text-white font-semibold"><?php echo $user_stats['level']; ?></span></li>
                            <li class="flex justify-between"><span>Attack Turns:</span> <span class="text-white font-semibold"><?php echo $user_stats['attack_turns']; ?></span></li>
                            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                                <span>Next Turn In:</span>
                                <span id="next-turn-timer" class="text-cyan-300 font-bold" data-seconds-until-next-turn="<?php echo $seconds_until_next_turn; ?>"><?php echo sprintf('%02d:%02d', $minutes_until_next_turn, $seconds_remainder); ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span>Dominion Time:</span>
                                <span id="dominion-time" class="text-white font-semibold" data-hours="<?php echo $now->format('H'); ?>" data-minutes="<?php echo $now->format('i'); ?>" data-seconds="<?php echo $now->format('s'); ?>"><?php echo $now->format('H:i:s'); ?></span>
                            </li>
                        </ul>
                    </div>
                </aside>
                <?php endif; ?>

                <main class="<?php echo $is_logged_in ? 'lg:col-span-3' : 'col-span-1'; ?> space-y-6">
                    <div class="content-box rounded-lg p-6">
                        <h3 class="font-title text-2xl text-cyan-400 mb-4 border-b border-gray-600 pb-2">Development Newsfeed</h3>
                        
                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Major Upgrade System Overhaul & New Structures!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-25</p>
                            <p class="text-gray-300">We've completely refactored the empire structure system! All permanent empire upgrades have been moved to the Structures page and categorized for clarity. This new, flexible system allows us to add more diverse and interesting upgrade paths in the future. Check out the new Offense, Defense, Economy, and Population upgrade trees and start specializing your empire today!</p>
                        </div>

                        <div class="mb-8 pb-4 border-b border-gray-700">
                            <h4 class="font-title text-xl text-yellow-400">Galactic Rankings and Enhanced Attack List</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-24</p>
                            <p class="text-gray-300">The attack page has been revamped to better reflect the state of the galaxy. We've introduced a new comprehensive ranking algorithm that considers experience, population, and military victories to score commanders. The target list now proudly displays player avatars, race, class, and online status, allowing for more strategic target selection.</p>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="font-title text-xl text-yellow-400">Player Avatars & Banking System Introduced!</h4>
                            <p class="text-xs text-gray-500 mb-2">Posted: 2025-07-23</p>
                            <p class="text-gray-300">Commanders can now express their identity! You can now upload a custom avatar on your Profile page. Default racial avatars have been assigned to all existing commanders. In addition, the Interstellar Bank is now open for business. Protect your hard-earned credits from plunder by depositing them daily. Be strategic—deposits are limited!</p>
                        </div>
                    </div>

                    <div class="content-box rounded-lg p-6 text-center">
                         <h3 class="font-title text-2xl text-cyan-400 mb-2">Join the Community</h3>
                         <p class="mb-4">Connect with other commanders, discuss strategies, and get the latest updates on our official Discord server.</p>
                         <a href="https://discord.com/channels/1397295425777696768/1397295426415235214" target="_blank" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition-colors text-lg">
                             <i data-lucide="message-square" class="mr-3"></i>
                             Join Discord
                         </a>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            if(mobileMenuButton) {
                mobileMenuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            lucide.createIcons();
        });
    </script>
</body>
</html>