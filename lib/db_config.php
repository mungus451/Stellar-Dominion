<?php
// --- START: ADD THESE 3 LINES FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END: DEBUGGING LINES ---

/*
 * -----------------------------------------------------------------------------
 * Database Configuration
 * -----------------------------------------------------------------------------
 *
 * Instructions:
 * 1. Replace 'your_database_name' with the name of the database you create.
 * 2. Replace 'your_username' with the username for your database.
 * 3. Replace 'your_password' with the password for that user.
 *
 */

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'password');
define('DB_NAME', 'users');

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// ** NEW **: Set the connection timezone to UTC. This is a critical fix.
mysqli_query($link, "SET time_zone = '+00:00'");

?>