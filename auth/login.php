<?php
session_start();
// Go UP one directory from /auth, then DOWN into /lib
require_once __DIR__ . '/../lib/db_config.php';

$email = trim($_POST['email']);
$password = trim($_POST['password']);

if(empty($email) || empty($password)) {
    die("Please enter email and password.");
}

$sql = "SELECT id, character_name, password_hash FROM users WHERE email = ?";

if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "s", $email);
    
    if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) == 1){
            mysqli_stmt_bind_result($stmt, $id, $character_name, $hashed_password);
            if(mysqli_stmt_fetch($stmt)){
                if(password_verify($password, $hashed_password)){
                    // This session_start() is a duplicate and can cause issues.
                    // session_start();
                    
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["character_name"] = $character_name;                            
                    
                    header("location: /dashboard.php");
                    exit;
                } else{
                    echo "Invalid password.";
                }
            }
        } else{
            echo "No account found with that email.";
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);