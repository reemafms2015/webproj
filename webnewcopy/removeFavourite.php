<?php
session_start();
include "db.php";

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = (int) $_SESSION['userID'];
$recipeID = isset($_GET['recipeID']) ? (int) $_GET['recipeID'] : 0;

if ($recipeID > 0) {
    $deleteQuery = "DELETE FROM favourites
                    WHERE userID = $userID AND recipeID = $recipeID";
    mysqli_query($conn, $deleteQuery);
}

header("Location: user.php");
exit();
?>