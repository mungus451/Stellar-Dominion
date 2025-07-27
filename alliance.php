<?php
// alliance.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";
$user_id = $_SESSION['id'];
$active_page = 'alliance.php'; // For navigation

// Fetch user's alliance status
$sql_user_alliance = "SELECT alliance_id, alliance_rank FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $sql_user_alliance);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_alliance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$alliance_id = $user_alliance_data['alliance_id'];
$alliance_rank = $user_alliance_data['alliance_rank'];
$alliance = null;
$members = [];

if ($alliance_id) {
    // User is in an alliance, fetch its details and members
    $sql_alliance = "SELECT a.*, u.character_name as leader_name FROM alliances a JOIN users u ON a.leader_id = u.id WHERE a.id = ?";
    $stmt_alliance = mysqli_prepare($link, $sql_alliance);
    mysqli_stmt_bind_param($stmt_alliance, "i", $alliance_id);
    mysqli_stmt_execute($stmt_alliance);
    $alliance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_alliance));
    mysqli_stmt_close($stmt_alliance);

    $sql_members = "SELECT id, character_name, level, alliance_rank, net_worth FROM users WHERE alliance_id = ? ORDER BY FIELD(alliance_rank, 'Leader', 'Lieutenant', 'Member'), net_worth DESC";
    $stmt_members = mysqli_prepare($link, $sql_members);
    mysqli_stmt_bind_param($stmt_members, "i", $alliance_id);
    mysqli_stmt_execute($stmt_members);
    $result_members = mysqli_stmt_get_result($stmt_members);
    while($row = mysqli_fetch_assoc($result_members)){
        $members[] = $row;
    }
    mysqli_stmt_close($stmt_members);
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
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('assets/img/background.jpg');">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>

    <main class="lg:col-span-3 space-y-4">
        <?php if ($alliance): ?>
            <div class="content-box rounded-lg p-6">
                <div class="flex items-center space-x-4">
                    <img src="<?php echo htmlspecialchars($alliance['avatar_path']); ?>" alt="Alliance Avatar" class="w-24 h-24 rounded-lg border-2 border-cyan-400">
                    <div>
                        <h1 class="font-title text-4xl text-white">[<?php echo htmlspecialchars($alliance['tag']); ?>] <?php echo htmlspecialchars($alliance['name']); ?></h1>
                        <p class="text-gray-400">Led by: <?php echo htmlspecialchars($alliance['leader_name']); ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="font-title text-cyan-400">Alliance Charter</h3>
                    <p class="mt-2 text-sm italic"><?php echo nl2br(htmlspecialchars($alliance['description'])); ?></p>
                </div>
                 <?php if ($alliance_rank === 'Leader'): ?>
                    <div class="mt-4">
                        <a href="edit_alliance.php" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg">Edit Alliance Profile</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="content-box rounded-lg p-4">
                 <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Member Roster</h3>
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
</body>
</html>