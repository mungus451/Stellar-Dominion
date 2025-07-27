<?php
// alliance.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';

// Fetch user's alliance status
$sql_user_alliance = "SELECT alliance_id, alliance_rank FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($link, $sql_user_alliance);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_alliance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_alliance_data['alliance_id'];
$alliance_rank = $user_alliance_data['alliance_rank'];
$alliance = null;
$members = [];
$forum_posts = [];
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'roster'; // Default to roster tab

if ($alliance_id) {
    // User is in an alliance, fetch its details
    $sql_alliance = "SELECT a.*, u.character_name as leader_name FROM alliances a JOIN users u ON a.leader_id = u.id WHERE a.id = ?";
    $stmt_alliance = mysqli_prepare($link, $sql_alliance);
    mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
    mysqli_stmt_execute($stmt_alliance);
    $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
    mysqli_stmt_close($stmt_alliance);

    // Fetch members
    $sql_members = "SELECT id, character_name, level, alliance_rank, net_worth, last_updated FROM users WHERE alliance_id = ? ORDER BY FIELD(alliance_rank, 'Leader', 'Lieutenant', 'Member'), net_worth DESC";
    $stmt_members = mysqli_prepare($link, $sql_members);
    mysqli_stmt_bind_param($stmt_members, "i", $alliance_id);
    mysqli_stmt_execute($stmt_members);
    $result_members = mysqli_stmt_get_result($stmt_members);
    while($row = mysqli_fetch_assoc($result_members)){ $members[] = $row; }
    mysqli_stmt_close($stmt_members);

    // Fetch forum posts
    $sql_forum = "SELECT p.id, p.post_content, p.post_time, p.is_pinned, u.character_name, u.avatar_path FROM alliance_forum_posts p JOIN users u ON p.user_id = u.id WHERE p.alliance_id = ? ORDER BY p.is_pinned DESC, p.post_time DESC LIMIT 50";
    $stmt_forum = mysqli_prepare($link, $sql_forum);
    mysqli_stmt_bind_param($stmt_forum, "i", $alliance_id);
    mysqli_stmt_execute($stmt_forum);
    $result_forum = mysqli_stmt_get_result($stmt_forum);
    while($row = mysqli_fetch_assoc($result_forum)){ $forum_posts[] = $row; }
    mysqli_stmt_close($stmt_forum);

} else {
    // User is not in an alliance, fetch a list of all alliances
    $sql_alliances = "SELECT a.id, a.name, a.tag, (SELECT COUNT(*) FROM users WHERE alliance_id = a.id) as member_count FROM alliances a ORDER BY member_count DESC";
    $alliances_list = mysqli_query($link, $sql_alliances);
}
mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Alliance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
    <div class="container mx-auto p-4 md:p-8">
        <?php include_once 'includes/navigation.php'; ?>
        <main class="space-y-4">
            <?php if(isset($_SESSION['alliance_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_error']); unset($_SESSION['alliance_error']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_message']); unset($_SESSION['alliance_message']); ?>
                </div>
            <?php endif; ?>
            <?php if ($alliance): ?>
                <div class="content-box rounded-lg p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($alliance['avatar_path'] ?? 'assets/img/default_alliance.png'); ?>" alt="Alliance Avatar" class="w-24 h-24 rounded-lg border-2 border-cyan-400 object-cover">
                            <div>
                                <h1 class="font-title text-4xl text-white">[<?php echo htmlspecialchars($alliance['tag']); ?>] <?php echo htmlspecialchars($alliance['name']); ?></h1>
                                <p class="text-gray-400">Led by: <?php echo htmlspecialchars($alliance['leader_name']); ?></p>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-2">
                            <?php if ($alliance_rank === 'Leader'): ?>
                                <a href="edit_alliance.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Edit Profile</a>
                            <?php endif; ?>
                            <form action="lib/alliance_actions.php" method="POST" onsubmit="return confirm('Are you sure you want to leave this alliance?');">
                                <input type="hidden" name="action" value="leave">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg">Leave Alliance</button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="font-title text-cyan-400">Alliance Charter</h3>
                        <p class="mt-2 text-sm italic prose max-w-none"><?php echo nl2br(htmlspecialchars($alliance['description'])); ?></p>
                    </div>
                </div>

                <div class="border-b border-gray-600">
                    <nav class="flex space-x-4">
                        <a href="?tab=roster" class="py-2 px-4 <?php echo $current_tab == 'roster' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400'; ?>">Member Roster</a>
                        <a href="?tab=forum" class="py-2 px-4 <?php echo $current_tab == 'forum' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400'; ?>">Alliance Forum</a>
                    </nav>
                </div>

                <div id="roster-content" class="<?php if ($current_tab !== 'roster') echo 'hidden'; ?>">
                    <div class="content-box rounded-lg p-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Name</th><th class="p-2">Level</th><th class="p-2">Rank</th><th class="p-2">Net Worth</th><th class="p-2">Status</th>
                                        <?php if ($alliance_rank === 'Leader'): ?><th class="p-2 text-right">Manage</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="p-2 text-white font-bold"><?php echo htmlspecialchars($member['character_name']); ?></td>
                                        <td class="p-2"><?php echo $member['level']; ?></td>
                                        <td class="p-2"><?php echo htmlspecialchars($member['alliance_rank']); ?></td>
                                        <td class="p-2"><?php echo number_format($member['net_worth']); ?></td>
                                        <td class="p-2"><?php echo (time() - strtotime($member['last_updated']) < 900) ? '<span class="text-green-400">Online</span>' : '<span class="text-gray-500">Offline</span>'; ?></td>
                                        <?php if ($alliance_rank === 'Leader' && $member['id'] !== $user_id): ?>
                                            <td class="p-2 text-right">
                                                <form action="lib/alliance_actions.php" method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="promote">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                    <button type="submit" class="text-green-400 hover:text-green-300 text-xs">Promote</button>
                                                </form>
                                                |
                                                <form action="lib/alliance_actions.php" method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="demote">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                    <button type="submit" class="text-yellow-400 hover:text-yellow-300 text-xs">Demote</button>
                                                </form>
                                                |
                                                <form action="lib/alliance_actions.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to kick this member?');">
                                                    <input type="hidden" name="action" value="kick">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                    <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Kick</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="forum-content" class="<?php if ($current_tab !== 'forum') echo 'hidden'; ?>">
                    <div class="content-box rounded-lg p-4 space-y-4">
                        <form action="lib/alliance_actions.php" method="POST">
                            <input type="hidden" name="action" value="post_forum">
                            <textarea name="post_content" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2" placeholder="Post a message to the alliance..."></textarea>
                            <div class="text-right mt-2">
                                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-6 rounded-lg">Post</button>
                            </div>
                        </form>
                        <hr class="border-gray-600">
                        <div class="space-y-4">
                            <?php foreach ($forum_posts as $post): ?>
                            <div class="flex space-x-3 <?php if ($post['is_pinned']) echo 'bg-cyan-900/20 p-3 rounded-lg'; ?>">
                                <img src="<?php echo htmlspecialchars($post['avatar_path'] ?? 'https://via.placeholder.com/48'); ?>" class="w-12 h-12 rounded-full flex-shrink-0 object-cover">
                                <div class="flex-grow">
                                    <div class="flex justify-between items-center">
                                        <p class="font-bold text-white"><?php echo htmlspecialchars($post['character_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $post['post_time']; ?> UTC</p>
                                    </div>
                                    <p class="text-sm prose max-w-none mt-1"><?php echo nl2br(htmlspecialchars($post['post_content'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="content-box rounded-lg p-6 text-center">
                    <h1 class="font-title text-3xl text-white">Forge Your Allegiance</h1>
                    <p class="mt-2">You are currently unaligned. Join an existing alliance or spend 1,000,000 Credits to forge your own.</p>
                    <a href="create_alliance.php" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Create Alliance</a>
                </div>
                <div class="content-box rounded-lg p-4">
                    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Join an Alliance</h3>
                    </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>