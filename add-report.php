<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $recipeID = $_POST['recipeID'];
    $userID = $_SESSION['userID'];

    $sqlCheck = "SELECT * FROM report WHERE userID = ? AND recipeID = ?";
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmtCheck, "ii", $userID, $recipeID);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);

    if (mysqli_num_rows($resultCheck) == 0) {
        $sqlInsert = "INSERT INTO report (userID, recipeID) VALUES (?, ?)";
        $stmtInsert = mysqli_prepare($conn, $sqlInsert);
        mysqli_stmt_bind_param($stmtInsert, "ii", $userID, $recipeID);
        mysqli_stmt_execute($stmtInsert);
    }

    header("Location: view-recipe.php?id=$recipeID&msg=Recipe reported successfully");
    exit();
}

header("Location: index.php");
exit();
?>
