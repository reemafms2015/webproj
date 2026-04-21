<?php
$conn = mysqli_connect("localhost", "root", "root", "recipedb (2)", 8889);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
