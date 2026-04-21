<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['userType']) || $_SESSION['userType'] != 'admin') {
    header("Location: login.php?msg=You must log in as admin first");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: admin.php?msg=Invalid request");
    exit();
}

$recipeID = $_POST['recipeID'];
$userID   = $_POST['userID'];
$reportID = $_POST['reportID'];
$action   = $_POST['action'];

if ($action != "block" && $action != "dismiss") {
    header("Location: admin.php?msg=Invalid action selected");
    exit();
}

if ($action == "dismiss") {
    $sqlDeleteReport = "DELETE FROM report WHERE id = ?";
    $stmtDeleteReport = mysqli_prepare($conn, $sqlDeleteReport);
    mysqli_stmt_bind_param($stmtDeleteReport, "i", $reportID);
    mysqli_stmt_execute($stmtDeleteReport);

    header("Location: admin.php?msg=Report dismissed successfully");
    exit();
}

if ($action == "block") {
    $sqlUser = "SELECT * FROM user WHERE id = ?";
    $stmtUser = mysqli_prepare($conn, $sqlUser);
    mysqli_stmt_bind_param($stmtUser, "i", $userID);
    mysqli_stmt_execute($stmtUser);
    $resultUser = mysqli_stmt_get_result($stmtUser);

    if (mysqli_num_rows($resultUser) == 0) {
        header("Location: admin.php?msg=User not found");
        exit();
    }

    $user = mysqli_fetch_assoc($resultUser);

    $sqlInsertBlocked = "INSERT INTO blockeduser (firstName, lastName, emailAddress) VALUES (?, ?, ?)";
    $stmtInsertBlocked = mysqli_prepare($conn, $sqlInsertBlocked);
    mysqli_stmt_bind_param($stmtInsertBlocked, "sss", $user['firstName'], $user['lastName'], $user['emailAddress']);
    mysqli_stmt_execute($stmtInsertBlocked);

    $sqlRecipes = "SELECT * FROM recipe WHERE userID = ?";
    $stmtRecipes = mysqli_prepare($conn, $sqlRecipes);
    mysqli_stmt_bind_param($stmtRecipes, "i", $userID);
    mysqli_stmt_execute($stmtRecipes);
    $resultRecipes = mysqli_stmt_get_result($stmtRecipes);

    while ($recipe = mysqli_fetch_assoc($resultRecipes)) {
        $currentRecipeID = $recipe['id'];

        if (!empty($recipe['photoFileName']) && file_exists($recipe['photoFileName'])) {
            unlink($recipe['photoFileName']);
        }

        if (!empty($recipe['videoFilePath']) && file_exists($recipe['videoFilePath'])) {
            unlink($recipe['videoFilePath']);
        }

        $sqlDeleteIngredients = "DELETE FROM ingredients WHERE recipeID = ?";
        $stmtDeleteIngredients = mysqli_prepare($conn, $sqlDeleteIngredients);
        mysqli_stmt_bind_param($stmtDeleteIngredients, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteIngredients);

        $sqlDeleteInstructions = "DELETE FROM instructions WHERE recipeID = ?";
        $stmtDeleteInstructions = mysqli_prepare($conn, $sqlDeleteInstructions);
        mysqli_stmt_bind_param($stmtDeleteInstructions, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteInstructions);

        $sqlDeleteComments = "DELETE FROM comment WHERE recipeID = ?";
        $stmtDeleteComments = mysqli_prepare($conn, $sqlDeleteComments);
        mysqli_stmt_bind_param($stmtDeleteComments, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteComments);

        $sqlDeleteLikes = "DELETE FROM likes WHERE recipeID = ?";
        $stmtDeleteLikes = mysqli_prepare($conn, $sqlDeleteLikes);
        mysqli_stmt_bind_param($stmtDeleteLikes, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteLikes);

        $sqlDeleteFavourites = "DELETE FROM favourites WHERE recipeID = ?";
        $stmtDeleteFavourites = mysqli_prepare($conn, $sqlDeleteFavourites);
        mysqli_stmt_bind_param($stmtDeleteFavourites, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteFavourites);

        $sqlDeleteReportsByRecipe = "DELETE FROM report WHERE recipeID = ?";
        $stmtDeleteReportsByRecipe = mysqli_prepare($conn, $sqlDeleteReportsByRecipe);
        mysqli_stmt_bind_param($stmtDeleteReportsByRecipe, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteReportsByRecipe);

        $sqlDeleteRecipe = "DELETE FROM recipe WHERE id = ?";
        $stmtDeleteRecipe = mysqli_prepare($conn, $sqlDeleteRecipe);
        mysqli_stmt_bind_param($stmtDeleteRecipe, "i", $currentRecipeID);
        mysqli_stmt_execute($stmtDeleteRecipe);
    }

    $sqlDeleteUserComments = "DELETE FROM comment WHERE userID = ?";
    $stmtDeleteUserComments = mysqli_prepare($conn, $sqlDeleteUserComments);
    mysqli_stmt_bind_param($stmtDeleteUserComments, "i", $userID);
    mysqli_stmt_execute($stmtDeleteUserComments);

    $sqlDeleteUserLikes = "DELETE FROM likes WHERE userID = ?";
    $stmtDeleteUserLikes = mysqli_prepare($conn, $sqlDeleteUserLikes);
    mysqli_stmt_bind_param($stmtDeleteUserLikes, "i", $userID);
    mysqli_stmt_execute($stmtDeleteUserLikes);

    $sqlDeleteUserFavourites = "DELETE FROM favourites WHERE userID = ?";
    $stmtDeleteUserFavourites = mysqli_prepare($conn, $sqlDeleteUserFavourites);
    mysqli_stmt_bind_param($stmtDeleteUserFavourites, "i", $userID);
    mysqli_stmt_execute($stmtDeleteUserFavourites);

    $sqlDeleteUserReports = "DELETE FROM report WHERE userID = ?";
    $stmtDeleteUserReports = mysqli_prepare($conn, $sqlDeleteUserReports);
    mysqli_stmt_bind_param($stmtDeleteUserReports, "i", $userID);
    mysqli_stmt_execute($stmtDeleteUserReports);

    if (!empty($user['photoFileName']) && file_exists($user['photoFileName'])) {
        unlink($user['photoFileName']);
    }

    $sqlDeleteUser = "DELETE FROM user WHERE id = ?";
    $stmtDeleteUser = mysqli_prepare($conn, $sqlDeleteUser);
    mysqli_stmt_bind_param($stmtDeleteUser, "i", $userID);
    mysqli_stmt_execute($stmtDeleteUser);

    $sqlDeleteReport = "DELETE FROM report WHERE id = ?";
    $stmtDeleteReport = mysqli_prepare($conn, $sqlDeleteReport);
    mysqli_stmt_bind_param($stmtDeleteReport, "i", $reportID);
    mysqli_stmt_execute($stmtDeleteReport);

    header("Location: admin.php?msg=User blocked and all related data deleted successfully");
    exit();
}
?>
