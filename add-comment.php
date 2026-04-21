<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recipeID = $_POST['recipeID'];
    $commentText = trim($_POST['comment']);
    $userID = $_SESSION['userID'];

    if ($commentText == "") {
        header("Location: view-recipe.php?id=$recipeID&msg=Comment cannot be empty");
        exit();
    }

    $today = date("Y-m-d");

    $sql = "INSERT INTO comment (recipeID, userID, comment, data) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiss", $recipeID, $userID, $commentText, $today);
    mysqli_stmt_execute($stmt);

    header("Location: view-recipe.php?id=$recipeID&msg=Comment added successfully");
    exit();
}

header("Location: index.html");
exit();
?>
