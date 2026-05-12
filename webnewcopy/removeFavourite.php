<?php

/* Start session */
session_start();

/* Connect to database */
include "db.php";

/* Check if user is logged in */
if (!isset($_SESSION['userID'])) {
    echo "false";
    exit();
}

/* Get logged in user ID */
$userID = (int) $_SESSION['userID'];

/* Get recipe ID sent by AJAX */
$recipeID = isset($_POST['recipeID']) ? (int) $_POST['recipeID'] : 0;

/* Check recipe ID */
if ($recipeID <= 0) {
    echo "false";
    exit();
}

/* Delete recipe from favourites table */
$deleteQuery = "
    DELETE FROM favourites
    WHERE userID = $userID
    AND recipeID = $recipeID
";

/* Execute delete query */
$deleteResult = mysqli_query($conn, $deleteQuery);

/* Return result to AJAX */
if ($deleteResult) {
    echo "true";
} else {
    echo "false";
}
?>
