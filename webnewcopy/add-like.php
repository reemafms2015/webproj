<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    echo "false";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['recipeID'])) {
    $recipeID = (int) $_POST['recipeID'];
    $userID = (int) $_SESSION['userID'];

    $sqlCheck = "SELECT * FROM likes WHERE userID = ? AND recipeID = ?";
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmtCheck, "ii", $userID, $recipeID);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);

    if (mysqli_num_rows($resultCheck) == 0) {
        $sqlInsert = "INSERT INTO likes (userID, recipeID) VALUES (?, ?)";
        $stmtInsert = mysqli_prepare($conn, $sqlInsert);
        mysqli_stmt_bind_param($stmtInsert, "ii", $userID, $recipeID);

        echo mysqli_stmt_execute($stmtInsert) ? "true" : "false";
    } else {
        echo "true";
    }
    exit();
}

echo "false";
exit();
?>
