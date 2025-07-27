<?php
// alliance.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";
$user_id = $_SESSION['id'];
$active_page = 'alliance.php';

// Fetch user's alliance status and role permissions
$sql_user_alliance = "
    SELECT u.alliance_id, u.alliance_role_id, ar.name as role_name, ar.is_deletable,
           ar.can_edit_profile, ar.can_approve_membership, ar.can_kick_members, ar.can_manage_roles
    FROM users u
    LEFT JOIN alliance_roles ar ON u.alliance_role_id = ar.id
    WHERE u.id = ?";
$stmt_user = mysqli_prepare($link, $sql_user_alliance);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_alliance_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$alliance_id = $user_alliance_data['alliance_id'];
$user_permissions = $user_alliance_data;
$alliance = null;
$members = [];
$roles = [];
$applications = [];
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

    // Fetch members and their roles
    $sql_members = "
        SELECT u.id, u.character_name, u.level, u.net_worth, u.last_updated, ar.name as role_name, ar.order
        FROM users u
        JOIN alliance_roles ar ON u.alliance_role_id = ar.id
        WHERE u.alliance_id = ? ORDER BY ar.order ASC, u.net_worth DESC";
    $stmt_members = mysqli_prepare($link, $sql_members);
    mysqli_stmt_bind_param($stmt_members, "i", $alliance_id);
    mysqli_stmt_execute($stmt_members);
    $result_members = mysqli_stmt_get_result($stmt_members);
    while($row = mysqli_fetch_assoc($result_members)){ $members[] = $row; }
    mysqli_stmt_close($stmt_members);
    
    // Fetch all roles for the "Assign Role" dropdown
    $sql_roles = "SELECT id, name FROM alliance_roles WHERE alliance_id = ? ORDER BY `order` ASC";
    $stmt_roles = mysqli_prepare($link, $sql_roles);
    mysqli_stmt_bind_param($stmt_roles, "i", $alliance_id);
    mysqli_stmt_execute($stmt_roles);
    $result_roles = mysqli_stmt_get_result($stmt_roles);
    while($row = mysqli_fetch_assoc($result_roles)){ $roles[] = $row; }
    mysqli_stmt_close($stmt_roles);

    // Fetch applications if user has permission
    if ($user_permissions['can_approve_membership']) {
        $sql_apps = "SELECT a.id, a.user_id, u.character_name, u.level, u.net_worth FROM alliance_applications a JOIN users u ON a.user_id = u.id WHERE a.alliance_id = ? AND a.status = 'pending'";
        $stmt_apps = mysqli_prepare($link, $sql_apps);
        mysqli_stmt_bind_param($stmt_apps, "i", $alliance_id);
        mysqli_stmt_execute($stmt_apps);
        $result_apps = mysqli_stmt_get_result($stmt_apps);
        while($row = mysqli_fetch_assoc($result_apps)){ $applications[] = $row; }
        mysqli_stmt_close($stmt_apps);
    }

} else {
    // User is not in an alliance, fetch a list of all alliances
    $sql_alliances = "SELECT a.id, a.name, a.tag, (SELECT COUNT(*) FROM users WHERE alliance_id = a.id) as member_count, (SELECT COUNT(*) FROM alliance_applications WHERE alliance_id = a.id AND user_id = ? AND status = 'pending') as has_applied FROM alliances a ORDER BY member_count DESC";
    $stmt_alliances = mysqli_prepare($link, $sql_alliances);
    mysqli_stmt_bind_param($stmt_alliances, "i", $user_id);
    mysqli_stmt_execute($stmt_alliances);
    $alliances_list = mysqli_stmt_get_result($stmt_alliances);
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

            <?php if ($alliance): // USER IS IN AN ALLIANCE ?>
                <div class="content-box rounded-lg p-6">
                    </div>

            <?php else: // USER IS NOT IN AN ALLIANCE ?>
                <div class="content-box rounded-lg p-6 text-center">
                    <h1 class="font-title text-3xl text-white">Forge Your Allegiance</h1>
                    <p class="mt-2">You are currently unaligned. Apply to an existing alliance or spend 1,000,000 Credits to forge your own.</p>
                    <a href="create_alliance.php" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg">Create Alliance</a>
                </div>
                <div class="content-box rounded-lg p-4">
                    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Join an Alliance</h3>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800"><tr><th class="p-2">Name</th><th class="p-2">Members</th><th class="p-2 text-right">Action</th></tr></thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($alliances_list)): ?>
                            <tr class="border-t border-gray-700">
                                <td class="p-2 text-white font-bold">[<?php echo htmlspecialchars($row['tag']); ?>] <?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="p-2"><?php echo $row['member_count']; ?> / 100</td>
                                <td class="p-2 text-right">
                                    <?php if($row['has_applied']): ?>
                                        <span class="text-yellow-400 text-xs italic">Application Pending</span>
                                    <?php elseif($row['member_count'] >= 100): ?>
                                        <span class="text-red-400 text-xs italic">Full</span>
                                    <?php else: ?>
                                        <form action="lib/alliance_actions.php" method="POST">
                                            <input type="hidden" name="action" value="apply_to_alliance">
                                            <input type="hidden" name="alliance_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">Apply</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>