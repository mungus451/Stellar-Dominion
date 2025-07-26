<?php
/**
 * untrain.php
 *
 * This script handles the server-side logic for disbanding units for a partial refund.
 */

// --- SESSION AND DATABASE SETUP ---
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: /index.html"); exit; }
require_once "db_config.php";

// --- UNIT DEFINITIONS ---
$base_unit_costs = [
    'workers' => 100, 'soldiers' => 250, 'guards' => 250,
    'sentries' => 500, 'spies' => 1000,
];
$refund_rate = 0.75; // 3/4 refund

// --- INPUT PROCESSING AND VALIDATION ---
$units_to_untrain = [];
$total_citizens_to_return = 0;
foreach (array_keys($base_unit_costs) as $unit) {
    $amount = isset($_POST[$unit]) ? max(0, (int)$_POST[$unit]) : 0;
    if ($amount > 0) {
        $units_to_untrain[$unit] = $amount;
        $total_citizens_to_return += $amount;
    }
}

if ($total_citizens_to_return <= 0) {
    header("location: /battle.php?tab=disband");
    exit;
}

// --- TRANSACTIONAL DATABASE UPDATE ---
mysqli_begin_transaction($link);
try {
    // Get the player's current units, locking the row for the transaction.
    $sql_get_user = "SELECT workers, soldiers, guards, sentries, spies FROM users WHERE id = ? FOR UPDATE";
    $stmt = mysqli_prepare($link, $sql_get_user);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $user_units = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Server-side validation: Check if the player owns enough units to disband.
    $total_refund = 0;
    foreach ($units_to_untrain as $unit => $amount) {
        if ($user_units[$unit] < $amount) {
            throw new Exception("You do not have enough " . ucfirst($unit) . "s to disband.");
        }
        // Calculate the refund for this unit type
        $total_refund += floor($amount * $base_unit_costs[$unit] * $refund_rate);
    }

    // --- EXECUTE UPDATE ---
    $sql_update = "UPDATE users SET 
                    untrained_citizens = untrained_citizens + ?,
                    credits = credits + ?,
                    workers = workers - ?,
                    soldiers = soldiers - ?,
                    guards = guards - ?,
                    sentries = sentries - ?,
                    spies = spies - ?
                   WHERE id = ?";
    
    $stmt = mysqli_prepare($link, $sql_update);
    mysqli_stmt_bind_param($stmt, "iiiiiiii", 
        $total_citizens_to_return,
        $total_refund,
        $units_to_untrain['workers'] ?? 0,
        $units_to_untrain['soldiers'] ?? 0,
        $units_to_untrain['guards'] ?? 0,
        $units_to_untrain['sentries'] ?? 0,
        $units_to_untrain['spies'] ?? 0,
        $_SESSION["id"]
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($link);
    $_SESSION['training_message'] = "Units successfully disbanded. You received a refund of " . number_format($total_refund) . " credits.";

} catch (Exception $e) {
    mysqli_rollback($link);
    $_SESSION['training_error'] = "Error disbanding units: " . $e->getMessage();
}

// Redirect back to the disband tab.
header("location: /battle.php?tab=disband");
exit;
?>