<?php
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "recipedb";
$port = 8889;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

