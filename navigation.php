<?php
// This script generates the main and sub-navigation menus.
// It expects a variable named $active_page to be set before it's included.

$main_nav_links = [
    'HOME' => 'dashboard.php',
    'BATTLE' => 'battle.php',
    'STRUCTURES' => 'structures.php',
    'COMMUNITY' => '#', // Placeholder
    'SIGN OUT' => 'logout.php'
];

$sub_nav_links = [
    'HOME' => [
        'Dashboard' => 'dashboard.php',
        'Levels' => 'levels.php'
    ],
    'BATTLE' => [
        'Attack' => 'attack.php',
        'Training' => 'battle.php',
        'War History' => 'war_history.php'
    ],
    'STRUCTURES' => [
        // Placeholder for future pages
    ]
];

// Determine the active main category
$active_main_category = 'HOME'; // Default
if (in_array($active_page, ['battle.php', 'attack.php', 'war_history.php'])) {
    $active_main_category = 'BATTLE';
} elseif (in_array($active_page, ['structures.php'])) {
    $active_main_category = 'STRUCTURES';
}

?>
<header class="text-center mb-4">
    <h1 class="text-5xl font-title text-cyan-400" style="text-shadow: 0 0 8px rgba(6, 182, 212, 0.7);">STELLAR DOMINION</h1>
</header>

<div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-1">
    <nav class="flex justify-center space-x-4 md:space-x-8 bg-gray-900 p-3 rounded-t-md">
        <?php foreach ($main_nav_links as $title => $link): ?>
            <a href="<?php echo $link; ?>"
               class="nav-link <?php echo ($title == $active_main_category) ? 'active font-bold' : 'text-gray-400 hover:text-white'; ?> px-3 py-1 transition-all">
               <?php echo $title; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (isset($sub_nav_links[$active_main_category]) && !empty($sub_nav_links[$active_main_category])): ?>
    <div class="bg-gray-800 text-center p-2">
        <?php foreach ($sub_nav_links[$active_main_category] as $title => $link): ?>
             <a href="<?php echo $link; ?>"
                class="<?php echo ($link == $active_page) ? 'font-semibold text-white' : 'text-gray-400 hover:text-white'; ?> px-3">
                <?php echo $title; ?>
             </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>