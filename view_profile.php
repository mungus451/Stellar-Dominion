<?php
// --- SESSION AND DATABASE SETUP ---
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

if (!$profile_data) { header("location: /attack.php"); exit; } // Target not found

// Fetch viewer's data to check for alliance match and for sidebar stats
$viewer_data = null;
if ($is_logged_in) {
    $sql_viewer = "SELECT credits, untrained_citizens, level, attack_turns, last_updated, alliance_id FROM users WHERE id = ?";
    $stmt_viewer = mysqli_prepare($link, $sql_viewer);
    mysqli_stmt_bind_param($stmt_viewer, "i", $viewer_id);
    mysqli_stmt_execute($stmt_viewer);
    $viewer_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_viewer));
    mysqli_stmt_close($stmt_viewer);
}

mysqli_close($link);

// --- DERIVED STATS & CALCULATIONS for viewed profile ---
$army_size = $profile_data['soldiers'] + $profile_data['guards'] + $profile_data['sentries'] + $profile_data['spies'];
$last_seen_ts = strtotime($profile_data['last_updated']);
$is_online = (time() - $last_seen_ts) < 900; // 15 minute online threshold

// Determine if the attack interface should be shown
$is_same_alliance = ($viewer_data && $profile_data['alliance_id'] && $viewer_data['alliance_id'] === $profile_data['alliance_id']);
$can_attack = $is_logged_in && ($viewer_id != $profile_id) && !$is_same_alliance;

// Timer calculations for viewer
$minutes_until_next_turn = 0;
$seconds_remainder = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));
if($viewer_data) {
    $turn_interval_minutes = 10;
    $last_updated = new DateTime($viewer_data['last_updated'], new DateTimeZone('UTC'));
    $seconds_until_next_turn = ($turn_interval_minutes * 60) - (($now->getTimestamp() - $last_updated->getTimestamp()) % ($turn_interval_minutes * 60));
    if ($seconds_until_next_turn < 0) { $seconds_until_next_turn = 0; }
    $minutes_until_next_turn = floor($seconds_until_next_turn / 60);
    $seconds_remainder = $seconds_until_next_turn % 60;
}

// Page Identification
$active_page = 'attack.php'; // Keep the 'BATTLE' main nav active
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
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1742&q=80');">
        <div class="container mx-auto p-4 md:p-8">

            <?php if ($is_logged_in): ?>
                <?php include_once 'includes/navigation.php'; ?>
            <?php else: ?>
                <?php include_once 'includes/public_header.php'; ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 <?php if ($is_logged_in) echo 'lg:grid-cols-4'; ?> gap-4 <?php if ($is_### Fix Summary:

* **Restored Page Layout:** The `view_profile.php` file has been updated to include the standard header, navigation, sidebar, and styling, making it look and feel like the rest of the application.
* **Added Profile Details:** The page now correctly displays the target commander's key information, such as their avatar, level, race, class, alliance affiliation, army size, and biography.
* **Integrated Attack Box:** The "Engage Target" interface is now properly integrated into the page and is only displayed when you are logged in and able to attack the target (i.e., not yourself and not an alliance member).
* **Added Viewer Stats:** A sidebar has been added to show your own relevant stats (Credits, Attack Turns, etc.) for quick reference while scouting a target.

This fix resolves the visual bug and makes the scouting/attack page fully functional and consistent with the game's design.