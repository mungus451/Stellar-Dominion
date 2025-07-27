<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: index.html"); exit; }
require_once "lib/db_config.php";

// Fetch alliance data and verify the current user is the leader
// ... (Database query to get alliance data where leader_id = $_SESSION['id']) ...
// If not leader, redirect to alliance.php

$active_page = 'alliance.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stellar Dominion - Edit Alliance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="text-gray-400 antialiased">
<div class="min-h-screen bg-cover bg-center bg-fixed">
<div class="container mx-auto p-4 md:p-8">
    <?php include_once 'includes/navigation.php'; ?>
    <main class="content-box rounded-lg p-6 mt-4 max-w-2xl mx-auto">
        <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-4">Edit Alliance Profile</h1>
        <form action="lib/alliance_actions.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="alliance_id" value="<?php echo $alliance['id']; ?>">
            <div>
                <label class="font-semibold text-white">Current Avatar</label>
                <img src="<?php echo htmlspecialchars($alliance['avatar_path']); ?>" alt="Current Avatar" class="w-32 h-32 rounded-lg mt-1 border-2 border-gray-600">
            </div>
            <div>
                <label for="avatar" class="font-semibold text-white">New Avatar (Optional)</label>
                <input type="file" name="avatar" id="avatar" class="w-full text-sm mt-1">
            </div>
            <div>
                <label for="description" class="font-semibold text-white">Alliance Charter (Description)</label>
                <textarea name="description" id="description" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded-md p-2 mt-1"><?php echo htmlspecialchars($alliance['description']); ?></textarea>
            </div>
            <div class="text-right">
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-8 rounded-lg">Save Changes</button>
            </div>
        </form>
    </main>
</div>
</div>
</body>
</html>