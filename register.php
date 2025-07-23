<?php
session_start();
require_once "db_config.php";

$email = trim($_POST['email']);
$character_name = trim($_POST['characterName']);
$password = trim($_POST['password']);
$race = trim($_POST['race']);
$class = trim($_POST['characterClass']);

if(empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
    die("Please fill all required fields.");
}

// Hash password for security
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Prepare an insert statement
// ** CHANGED **: New players start with 1 level up point at level 1.
$sql = "INSERT INTO users (email, character_name, password_hash, race, class, credits, level_up_points) VALUES (?, ?, ?, ?, ?, 10000, 1)";

if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "sssss", $email, $character_name, $password_hash, $race, $class);

    if(mysqli_stmt_execute($stmt)){
        // Registration successful, log the user in automatically
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = mysqli_insert_id($link);
        $_SESSION["character_name"] = $character_name;
        
        // Redirect to the dashboard
        header("location: dashboard.php");
        exit;
    } else {
        echo "ERROR: Could not execute query: $sql. " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
} else {
    echo "ERROR: Could not prepare query: $sql. " . mysqli_error($link);
}

mysqli_close($link);
?>
