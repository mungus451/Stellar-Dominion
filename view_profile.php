<?php
session_start();
require_once "lib/db_config.php";
date_default_timezone_set('UTC');

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$viewer_id = $is_logged_in ? $_SESSION['id'] : 0;
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id <= 0) { header("location: /attack.php"); exit; }

// --- DATA FETCHING for the profile being viewed ---
$sql_profile = "SELECT u.*, a.name as alliance_name, a.tag as alliance_tag 
                FROM users u 
                LEFT JOIN alliances a ON u.alliance_id = a.id 
                WHERE u.id = ?";
$stmt_profile = mysqli_prepare($link, $sql_profile);
mysqli_stmt_bind_param($stmt_profile, "i", $profile_id);
mysqli_stmt_execute($stmt_profile);
$profile_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_profile));
mysqli_stmt_close($stmt_profile);

if (!$profile_data) { header("location: /attack.php"); exit; }

// Fetch viewer's data to check for alliance match
$viewer_data = null;
if ($is_logged_in) {
    $sql_viewer = "SELECT alliance_id FROM users WHERE id = ?";
    $stmt_viewer = mysqli_prepare($link, $sql_viewer);
    mysqli_stmt_bind_param($stmt_viewer, "i", $viewer_id);
    mysqli_stmt_execute($stmt_viewer);
    $viewer_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_viewer));
    mysqli_stmt_close($stmt_viewer);
}

// Determine if the attack interface should be shown
$is_same_alliance = ($viewer_data && $profile_data['alliance_id'] && $viewer_data['alliance_id'] === $profile_data['alliance_id']);
$can_attack = $is_logged_in && ($viewer_id != $profile_id) && !$is_same_alliance;

$army_size = $profile_data['soldiers'] + $profile_data['guards'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Stellar Dominion - Profile of <?php echo htmlspecialchars($profile_data['character_name']); ?></title>
    </head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed">
        <div class="container mx-auto p-4 md:p-8">
            <main class="content-box rounded-lg p-6 mt-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 space-y-4">
                        <?php if ($can_attack): ?>
                        <div class="bg-gray-800 rounded-lg p-4">
                             <h3 class="font-title text-lg text-red-400">Engage Target</h3>
                            <form action="lib/process_attack.php" method="POST" class="flex items-center justify-between mt-2">
                                <input type="hidden" name="defender_id" value="<?php echo $profile_data['id']; ?>">
                                <div class="text-sm">
                                    <label for="attack_turns">Attack Turns (1-10):</label>
                                    <input type="number" id="attack_turns" name="attack_turns" min="1" max="10" value="1" class="bg-gray-900 border border-gray-600 rounded-md w-20 text-center p-1 ml-2">
                                </div>
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">Launch Attack</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>