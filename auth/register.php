<?php
session_start();
// This path is correct. It goes UP from 'auth' and then DOWN into 'lib'.
require_once __DIR__ . '/../lib/db_config.php';

$email = trim($_POST['email']);
$character_name = trim($_POST['characterName']);
$password = trim($_POST['password']);
$race = trim($_POST['race']);
$class = trim($_POST['characterClass']);

if(empty($email) || empty($character_name) || empty($password) || empty($race) || empty($class)) {
    die("Please fill all required fields.");
}

// Set avatar path based on race
$avatar_path = '';
switch ($race) {
    case 'Human':
        $avatar_path = 'assets/img/human.png';
        break;
    case 'Cyborg':
        $avatar_path = 'assets/img/cybord.png';
        break;
    case 'Mutant':
        $avatar_path = 'assets/img/mutant.png';
        break;
    case 'The Shade':
        $avatar_path = 'assets/img/shade.png';
        break;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (email, character_name, password_hash, race, class, credits, untrained_citizens, level_up_points, avatar_path) VALUES (?, ?, ?, ?, ?, 100000, 1000, 1, ?)";

if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "sssssiss", $email, $character_name, $password_hash, $race, $class, $avatar_path);

    if(mysqli_stmt_execute($stmt)){
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = mysqli_insert_id($link);
        $_SESSION["character_name"] = $character_name;
        
        header("location: /dashboard.php");
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