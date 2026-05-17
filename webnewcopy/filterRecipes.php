<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode([]);
    exit();
}

$categoryID = isset($_GET['categoryID']) && $_GET['categoryID'] !== ""
    ? (int) $_GET['categoryID']
    : null;

$recipesQuery = "
    SELECT 
  recipe.id,
  recipe.name,
  recipe.photoFileName,
  recipecategory.categoryname,
  user.firstName,
  user.lastName,
  user.photoFileName AS creatorPhoto,
  COUNT(likes.recipeID) AS totalLikes
  FROM recipe
  JOIN recipecategory ON recipe.categoryID = recipecategory.id
  JOIN user ON recipe.userID = user.id
  LEFT JOIN likes ON recipe.id = likes.recipeID
";

if ($categoryID) {
    $recipesQuery .= " WHERE recipe.categoryID = $categoryID ";
}

$recipesQuery .= "
    GROUP BY recipe.id
    ORDER BY recipe.id DESC
";

$result = mysqli_query($conn, $recipesQuery);

$recipes = [];

while ($row = mysqli_fetch_assoc($result)) {

 if (!empty($row['photoFileName'])) {

    if (file_exists(__DIR__ . "/uploads/images/" . $row['photoFileName'])) {

        $recipePhoto = "uploads/images/" . $row['photoFileName'];

    } else {

        $recipePhoto = "images/recipes/" . $row['photoFileName'];
    }

} else {

    $recipePhoto = "";
}

$creatorPhoto =
    !empty($row['creatorPhoto']) &&
    file_exists(__DIR__ . "/images/users/" . $row['creatorPhoto'])
    ? "images/users/" . $row['creatorPhoto']
    : "images/users/profile.png";

    $recipes[] = [
        "id" => $row['id'],
        "name" => $row['name'],
        "photoFileName" => $recipePhoto,
        "creatorPhoto" => $creatorPhoto,
        "creatorName" => $row['firstName'] . " " . $row['lastName'],
        "likes" => $row['totalLikes'],
        "category" => $row['categoryname']
    ];
}

echo json_encode($recipes);
?>
