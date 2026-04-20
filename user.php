<?php
/* Show errors for testing */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* Start session */
session_start();

/*Connect to database */
include "db.php";

/* Check login and user type*/
if (!isset($_SESSION['userID']) || !isset($_SESSION['userType'])) {
    header("Location: login.html?error=Please log in first");
    exit();
}

$userID = (int) $_SESSION['userID'];

/*Get user information 
 * Table: user */
$userQuery = "SELECT * FROM user WHERE id = $userID";
$userResult = mysqli_query($conn, $userQuery);

if (!$userResult || mysqli_num_rows($userResult) == 0) {
    header("Location: login.html?error=User not found");
    exit();
}

$user = mysqli_fetch_assoc($userResult);

if (strtolower($user['userType']) !== 'user') {
    header("Location: login.html?error=Only regular users can access this page");
    exit();
}

/*User photo*/
$userPhoto = !empty($user['photoFileName']) ? $user['photoFileName'] : "profile.png";

if (!file_exists(__DIR__ . "/" . $userPhoto)) {
    $userPhoto = "profile.png";
}

/* Count user recipes
   Table: recipe*/
$totalRecipesQuery = "SELECT COUNT(*) AS totalRecipes
                      FROM recipe
                      WHERE userID = $userID";
$totalRecipesResult = mysqli_query($conn, $totalRecipesQuery);
$totalRecipesRow = mysqli_fetch_assoc($totalRecipesResult);
$totalRecipes = $totalRecipesRow['totalRecipes'] ?? 0;

/* 
   Count all likes for all this user's recipes
   Tables: likes + recipe */
$totalLikesQuery = "SELECT COUNT(*) AS totalLikes
                    FROM likes
                    JOIN recipe ON likes.recipeID = recipe.id
                    WHERE recipe.userID = $userID";
$totalLikesResult = mysqli_query($conn, $totalLikesQuery);
$totalLikesRow = mysqli_fetch_assoc($totalLikesResult);
$totalLikes = $totalLikesRow['totalLikes'] ?? 0;

/* 
   Get categories for filter
   Table: recipecategory
   Column: categoryname */
$categoriesQuery = "SELECT * FROM recipecategory ORDER BY categoryname";
$categoriesResult = mysqli_query($conn, $categoriesQuery);

/* 
   GET  -> all recipes
   POST -> recipes of selected category
 */
$selectedCategory = "";
$recipesQuery = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['categoryID']) && $_POST['categoryID'] !== "") {
    $selectedCategory = (int) $_POST['categoryID'];

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
        WHERE recipe.categoryID = $selectedCategory
        GROUP BY recipe.id, recipe.name, recipe.photoFileName, recipecategory.categoryname,
                 user.firstName, user.lastName, user.photoFileName
        ORDER BY recipe.id DESC
    ";
} else {
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
        GROUP BY recipe.id, recipe.name, recipe.photoFileName, recipecategory.categoryname,
                 user.firstName, user.lastName, user.photoFileName
        ORDER BY recipe.id DESC
    ";
}

$recipesResult = mysqli_query($conn, $recipesQuery);

/* 
   Get favourite recipes of this user
   Table: favourites
 */
$favouritesQuery = "
    SELECT 
        recipe.id,
        recipe.name,
        recipe.photoFileName
    FROM favourites
    JOIN recipe ON favourites.recipeID = recipe.id
    WHERE favourites.userID = $userID
    ORDER BY recipe.id DESC
";
$favouritesResult = mysqli_query($conn, $favouritesQuery);

function getRecipePhoto($recipeName, $photoFileName) {
    if (!empty($photoFileName) && file_exists(__DIR__ . "/" . $photoFileName)) {
        return $photoFileName;
    }

    $name = strtolower(trim($recipeName));

    if ($name === "bananapancake") {
        return "banana-pancakes.png";
    }

    if ($name === "fruityogurtcups") {
        return "yogurt.png";
    }

    if ($name === "veggie omelette") {
        return "omelette.png";
    }

    return "photo.png";
}

function getCreatorPhoto($firstName, $creatorPhoto) {
    if (!empty($creatorPhoto) && file_exists(__DIR__ . "/" . $creatorPhoto)) {
        return $creatorPhoto;
    }

    $name = strtolower(trim($firstName));

    if ($name === "sara") {
        return "sara.png";
    }

    if ($name === "rana") {
        return "profile.png";
    }

    if ($name === "rema") {
        return "profile.png";
    }

    return "profile.png";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Page</title>

<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Comic+Neue:wght@700&display=swap" rel="stylesheet">

<style>
:root {
    --primaryColor: #FF9AA2;
    --secondaryColor: #FFB7B2;
    --accentColor: #FFDAC1;
    --greenColor: #B5EAD7;
    --blueColor: #C7CEEA;
    --yellowColor: #FFE5B4;
    --darkColor: #5D576B;
    --lightColor: #FFF9F5;
    --borderRadius: 16px;
    --boxShadow: 0 8px 25px rgba(93, 87, 107, 0.1);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Nunito', sans-serif;
    background: linear-gradient(135deg, #FFDAC1 0%, #C7CEEA 100%);
    color: var(--darkColor);
    min-height: 100vh;
    margin: 0;
    padding: 0;
}

header {
    background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
    padding: 15px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.headerLeft {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo {
    width: 130px;
    height: auto;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));
}

header h1 {
    font-family: 'Comic Neue', cursive;
    font-size: 2.4rem;
    color: white;
    margin: 0;
}

nav a {
    margin-left: 25px;
    color: white;
    text-decoration: none;
    font-weight: 700;
    padding-bottom: 5px;
    cursor: pointer;
}

nav a:hover {
    color: var(--yellowColor);
}

nav a.active {
    color: var(--yellowColor);
    border-bottom: 3px solid var(--yellowColor);
}

.container {
    max-width: 1100px;
    margin: auto;
    padding: 20px;
    background-color: white;
    border-radius: var(--borderRadius);
    box-shadow: var(--boxShadow);
    border: 6px solid var(--yellowColor);
    overflow: hidden;
}

.pageHeader {
    background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
    color: white;
    padding: 30px;
    text-align: center;
}

.pageHeader h1 {
    font-family: 'Comic Neue', cursive;
    font-size: 2.4rem;
}

.section {
    padding: 30px;
}

.gridTwo {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.card {
    background-color: var(--lightColor);
    border-radius: var(--borderRadius);
    padding: 25px;
    box-shadow: var(--boxShadow);
}

.center {
    text-align: center;
}

.profileImg {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid var(--greenColor);
    object-fit: cover;
    margin-bottom: 10px;
}

select {
    padding: 12px 18px;
    border-radius: 30px;
    border: 2px solid var(--accentColor);
    font-weight: 600;
    margin-right: 10px;
}

.selectBtn {
    background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
    color: white;
    padding: 14px 32px;
    border-radius: 40px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: var(--transition);
}

.selectBtn:hover {
    transform: translateY(-3px);
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th {
    background-color: var(--accentColor);
    padding: 14px;
    font-weight: 700;
}

td {
    padding: 14px;
    border-bottom: 2px solid var(--lightColor);
    text-align: center;
}

.recipeImg {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 3px solid var(--accentColor);
    object-fit: cover;
}

.creatorImg {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: 2px solid var(--accentColor);
    object-fit: cover;
    margin-bottom: 6px;
}

a {
    color: var(--darkColor);
    text-decoration: none;
    font-weight: 600;
}

a:hover {
    color: var(--primaryColor);
    text-decoration: underline;
}

.mainBtn,
.logoutBtn {
    background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
    color: white;
    padding: 14px 32px;
    border-radius: 40px;
    font-weight: 700;
    display: inline-block;
    margin-top: 15px;
    transition: var(--transition);
}

.mainBtn:hover,
.logoutBtn:hover {
    transform: translateY(-3px);
}

.messageBox {
    margin-top: 20px;
    padding: 15px;
    background: white;
    border: 2px dashed var(--accentColor);
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
}

.siteFooter {
    background: var(--greenColor);
    padding: 25px;
    text-align: center;
    font-weight: 600;
    margin-top: 40px;
}
</style>
</head>

<body>

<header>
    <div class="headerLeft">
        <img src="photo.png" alt="Kids Recipes Logo" class="logo">
        <h1>LittleChefs</h1>
    </div>

    <nav>
        <a href="index.php">Home</a>
        <a href="user.php" class="active">Users</a>
        <a href="admin.php">Admins</a>
    </nav>
</header>

<div class="container">

    <div class="pageHeader">
        <h1>Welcome, <?php echo htmlspecialchars($user['firstName'] . " " . $user['lastName']); ?> 🌸</h1>
    </div>

    <div class="section">

        <!-- User Information -->
        <div class="gridTwo">
            <div class="card center">
                <img src="<?php echo htmlspecialchars($userPhoto); ?>" class="profileImg" alt="User Photo">
                <h3><?php echo htmlspecialchars($user['firstName'] . " " . $user['lastName']); ?></h3>
                <p><?php echo htmlspecialchars($user['emailAddress']); ?></p>
            </div>

            <div class="card center">
                <p><strong>Total Recipes</strong></p>
                <h2><?php echo $totalRecipes; ?></h2>

                <p><strong>Total Likes</strong></p>
                <h2><?php echo $totalLikes; ?></h2>

                <a href="myRecipes.php" class="mainBtn">🍽 My Recipes</a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="POST">
            <select name="categoryID">
                <option value="">All Categories</option>
                <?php while ($category = mysqli_fetch_assoc($categoriesResult)) { ?>
                    <option value="<?php echo $category['id']; ?>" <?php if ($selectedCategory == $category['id']) echo "selected"; ?>>
                        <?php echo htmlspecialchars($category['categoryname']); ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit" class="selectBtn">Filter</button>
        </form>

        <!-- All Recipes Table -->
        <div class="card">
            <h2>All Available Recipes</h2>

            <?php if ($recipesResult && mysqli_num_rows($recipesResult) > 0) { ?>
                <table>
                    <tr>
                        <th>Recipe</th>
                        <th>Photo</th>
                        <th>Recipe Creator</th>
                        <th>Likes</th>
                        <th>Category</th>
                    </tr>

                    <?php while ($recipe = mysqli_fetch_assoc($recipesResult)) { 
                        $recipePhoto = getRecipePhoto($recipe['name'], $recipe['photoFileName']);
                        $creatorPhoto = getCreatorPhoto($recipe['firstName'], $recipe['creatorPhoto']);
                    ?>
                        <tr>
                            <td>
                                <a href="viewRecipe.php?id=<?php echo $recipe['id']; ?>">
                                    <?php echo htmlspecialchars($recipe['name']); ?>
                                </a>
                            </td>
                            <td>
                                <img src="<?php echo htmlspecialchars($recipePhoto); ?>" class="recipeImg" alt="Recipe Photo">
                            </td>
                            <td>
                                <img src="<?php echo htmlspecialchars($creatorPhoto); ?>" class="creatorImg" alt="Creator Photo"><br>
                                <?php echo htmlspecialchars($recipe['firstName'] . " " . $recipe['lastName']); ?>
                            </td>
                            <td><?php echo $recipe['totalLikes']; ?></td>
                            <td><?php echo htmlspecialchars($recipe['categoryname']); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <div class="messageBox">No recipes found.</div>
            <?php } ?>
        </div>

        <!-- Favourite Recipes Table -->
        <div class="card">
            <h2>Favorite Recipes ❤️</h2>

            <?php if ($favouritesResult && mysqli_num_rows($favouritesResult) > 0) { ?>
                <table>
                    <tr>
                        <th>Recipe</th>
                        <th>Photo</th>
                        <th>Remove</th>
                    </tr>

                    <?php while ($fav = mysqli_fetch_assoc($favouritesResult)) { 
                        $favPhoto = getRecipePhoto($fav['name'], $fav['photoFileName']);
                    ?>
                        <tr>
                            <td>
                                <a href="viewRecipe.php?id=<?php echo $fav['id']; ?>">
                                    <?php echo htmlspecialchars($fav['name']); ?>
                                </a>
                            </td>
                            <td>
                                <img src="<?php echo htmlspecialchars($favPhoto); ?>" class="recipeImg" alt="Favourite Recipe Photo">
                            </td>
                            <td>
                                <a href="removeFavourite.php?recipeID=<?php echo $fav['id']; ?>">Remove</a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <div class="messageBox">You do not have any favourite recipes yet.</div>
            <?php } ?>
        </div>

        <!-- Sign Out -->
        <div class="center">
            <a href="logout.php" class="logoutBtn">Sign Out</a>
        </div>

    </div>
</div>

<div class="siteFooter">
    © 2026 Kids Recipes — Made with 💖 for little ones
</div>

</body>
</html>