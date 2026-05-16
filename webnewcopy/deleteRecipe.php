<?php
session_start();
include "db.php";

if (!isset($_SESSION['userID'])) {
    echo "false";
    exit();
}

$userID = (int) $_SESSION['userID'];

if (!isset($_POST['recipeID'])) {
    echo "false";
    exit();
}

$recipeID = (int) $_POST['recipeID'];

$checkQuery = "SELECT * FROM recipe WHERE id = $recipeID AND userID = $userID";
$checkResult = mysqli_query($conn, $checkQuery);

if (!$checkResult || mysqli_num_rows($checkResult) == 0) {
    echo "false";
    exit();
}

$recipe = mysqli_fetch_assoc($checkResult);

$photo = !empty($recipe['photoFileName']) ? "images/recipes/" . $recipe['photoFileName'] : "";
$video = !empty($recipe['videoFilePath']) ? "videos/" . $recipe['videoFilePath'] : "";

if (!empty($photo) && file_exists($photo)) {
    unlink($photo);
}

if (!empty($video) && file_exists($video)) {
    unlink($video);
}

mysqli_query($conn, "DELETE FROM likes WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM favourites WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM ingredients WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM instructions WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM comment WHERE recipeID = $recipeID");
mysqli_query($conn, "DELETE FROM report WHERE recipeID = $recipeID");

$deleteRecipe = mysqli_query($conn, "DELETE FROM recipe WHERE id = $recipeID AND userID = $userID");

if ($deleteRecipe) {
    echo "true";
} else {
    echo "false";
}
?>
