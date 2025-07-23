<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: index.html"); exit; }

require_once "db_config.php";

// Define base unit costs
$base_unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];

// Sanitize and get training amounts from POST
$units_to_train = [
    'workers' => isset($_POST['workers']) ? max(0, (int)$_POST['workers']) : 0,
    'soldiers' => isset($_POST['soldiers']) ? max(0, (int)$_POST['soldiers']) : 0,
    'guards' => isset($_POST['guards']) ? max(0, (int)$_POST['guards']) : 0,
    'sentries' => isset($_POST['sentries']) ? max(0, (int)$_POST['sentries']) : 0,
    'spies' => isset($_POST['spies']) ? max(0, (int)$_POST['spies']) : 0,
];

$total_citizens_needed = array_sum($units_to_train);

if ($total_citizens_needed <= 0) {
    header("location: battle.php");
    exit;
}

// --- Transactional Update ---
mysqli_begin_transaction($link);

try {
    // Get current user resources and charisma points
    $sql_get_user = "SELECT untrained_citizens, credits, charisma_points FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Calculate total cost with charisma discount
    $charisma_discount = 1 - ($user['charisma_points'] * 0.01);
    $total_credits_needed = 0;
    foreach ($units_to_train as $unit => $amount) {
        if ($amount > 0) {
            $discounted_cost = floor($base_unit_costs[$unit] * $charisma_discount);
            $total_credits_needed += $amount * $discounted_cost;
        }
    }

    // Check if user has enough resources
    if ($user['untrained_citizens'] < $total_citizens_needed || $user['credits'] < $total_credits_needed) {
        throw new Exception("Not enough resources to train units.");
    }

    // If checks pass, perform the update
    $sql_update = "UPDATE users SET 
                    untrained_citizens = untrained_citizens - ?,
                    credits = credits - ?,
                    workers = workers + ?,
                    soldiers = soldiers + ?,
                    guards = guards + ?,
                    sentries = sentries + ?,
                    spies = spies + ?
                   WHERE id = ?";
    
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iiiiiiii", 
        $total_citizens_needed, $total_credits_needed,
        $units_to_train['workers'], $units_to_train['soldiers'], $units_to_train['guards'],
        $units_to_train['sentries'], $units_to_train['spies'],
        $_SESSION["id"]
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($link);

} catch (Exception $e) {
    mysqli_rollback($link);
    die("Transaction failed: " . $e->getMessage());
}

header("location: battle.php");
exit;
?>
