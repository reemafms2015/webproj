<?php
/* Start session */
session_start();

/* Connect to database */
include "db.php";

/* Check login */
if (!isset($_SESSION['userID']) || !isset($_SESSION['userType'])) {
    header("Location: login.html?error=Please log in first");
    exit();
}

$userID = (int) $_SESSION['userID'];

/* Check recipe id */
if (!isset($_GET['recipeID']) || $_GET['recipeID'] == "") {
    header("Location: myRecipes.php");
    exit();
}

$recipeID = (int) $_GET['recipeID'];

/* Make sure the recipe belongs to this user */
$checkQuery = "SELECT * FROM recipe WHERE id = $recipeID AND userID = $userID";
$checkResult = mysqli_query($conn, $checkQuery);

if (!$checkResult || mysqli_num_rows($checkResult) == 0) {
    header("Location: myRecipes.php");
    exit();
}

/* Delete related rows first */
mysqli_query($conn, "DELETE FROM likes WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM favourites WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM ingredients WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM instructions WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM comment WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM report WHERE recipeID = $recipeID");

/* Delete recipe */
mysqli_query($conn, "DELETE FROM recipe WHERE id = $recipeID AND userID = $userID");

/* Go back to my recipes page  */
header("Location: myRecipes.php");
exit();
?>