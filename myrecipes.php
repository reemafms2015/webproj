<?php
/* Show errors for testing */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* Start session */
session_start();

/* Connect to database */
include "db.php";

/* Check login and user type */
if (!isset($_SESSION['userID']) || !isset($_SESSION['userType'])) {
    header("Location: login.html?error=Please log in first");
    exit();
}

$userID = (int) $_SESSION['userID'];

/* Get logged in user */
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

/* Get this user's recipes with likes count */
$recipesQuery = "
    SELECT 
        recipe.id,
        recipe.name,
        recipe.description,
        recipe.photoFileName,
        recipe.videoFilePath,
        COUNT(likes.recipeID) AS totalLikes
    FROM recipe
    LEFT JOIN likes ON recipe.id = likes.recipeID
    WHERE recipe.userID = $userID
    GROUP BY recipe.id, recipe.name, recipe.description, recipe.photoFileName, recipe.videoFilePath
    ORDER BY recipe.id DESC
";
$recipesResult = mysqli_query($conn, $recipesQuery);

/* Use local image names if database image name is empty or missing */
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Recipes</title>

    <!-- Fonts -->
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
            --boxShadow: 0 8px 25px rgba(93, 87, 107, 0.12);
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
        }

        header {
            background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .headerContent {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 130px;
        }

        header h1 {
            font-family: 'Comic Neue', cursive;
            font-size: 2.4rem;
            color: white;
        }

        nav a {
            margin-left: 25px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            padding-bottom: 5px;
        }

        nav a:hover,
        nav a.active {
            color: var(--yellowColor);
            border-bottom: 3px solid var(--yellowColor);
        }

        .card {
            background-color: white;
            border-radius: var(--borderRadius);
            box-shadow: var(--boxShadow);
            padding: 25px;
            max-width: 1200px;
            margin: 0 auto 30px auto;
            border: 5px solid var(--yellowColor);
        }

        .pageHeader {
            background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
            padding: 40px 25px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }

        .pageHeader h2 {
            color: white;
            font-family: 'Comic Neue', cursive;
            font-size: 2.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: var(--accentColor);
            padding: 15px;
            text-align: center;
            font-weight: bold;
        }

        td {
            padding: 15px;
            vertical-align: top;
            border-bottom: 2px solid var(--lightColor);
            font-size: 0.95rem;
        }

        tr:hover {
            background-color: rgba(255, 218, 193, 0.3);
        }

        ul, ol {
            padding-left: 20px;
        }

        li {
            margin-bottom: 6px;
        }

        .recipeImage {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accentColor);
            margin: 10px auto 0 auto;
            display: block;
            background-color: white;
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

        .primaryButton {
            display: inline-block;
            background: linear-gradient(to right, var(--primaryColor), var(--blueColor));
            color: white;
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: bold;
            margin-top: 20px;
            transition: 0.3s;
        }

        .primaryButton:hover {
            transform: translateY(-3px);
        }

        .center {
            text-align: center;
        }

        .noVideoText {
            text-align: center;
            font-weight: 600;
        }

        .messageBox {
            margin-top: 20px;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            background-color: var(--lightColor);
            border-radius: 12px;
        }

        footer {
            width: 100%;
            background: var(--greenColor);
            padding: 25px;
            text-align: center;
            font-weight: 600;
            margin-top: 40px;
        }
    </style>
</head>

<body>
<!-- Header -->
<header>
    <div class="headerContent">
        <img src="photo.png" alt="Logo" class="logo">
        <h1>LittleChefs</h1>
    </div>
    <nav>
        <a href="index.html">Home</a>
        <a href="user.php" class="active">Users</a>
        <a href="admin.php">Admins</a>
    </nav>
</header>

<!-- Page Content -->
<div class="card">

    <div class="pageHeader">
        <h2>My Kids Healthy Recipes 👶🥦</h2>
    </div>

    <!-- My Recipes table -->
    <div class="card">
        <?php if ($recipesResult && mysqli_num_rows($recipesResult) > 0) { ?>
            <table>
                <tr>
                    <th>Recipe</th>
                    <th>Ingredients</th>
                    <th>Instructions</th>
                    <th>Video</th>
                    <th>Likes</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>

                <?php while ($recipe = mysqli_fetch_assoc($recipesResult)) { ?>
                    <?php
                    $recipeID = $recipe['id'];
                    $recipePhoto = getRecipePhoto($recipe['name'], $recipe['photoFileName']);

                    $ingredientsQuery = "SELECT * FROM ingredients WHERE recipeID = $recipeID ORDER BY id";
                    $ingredientsResult = mysqli_query($conn, $ingredientsQuery);

                    $instructionsQuery = "SELECT * FROM instructions WHERE recipeID = $recipeID ORDER BY stepOrder";
                    $instructionsResult = mysqli_query($conn, $instructionsQuery);
                    ?>
                    <tr>
                        <td>
                            <a href="view-recipe.php?id=<?php echo $recipeID; ?>">
                                <?php echo htmlspecialchars($recipe['name']); ?>
                            </a>
                            <img src="<?php echo htmlspecialchars($recipePhoto); ?>" class="recipeImage" alt="Recipe Image">
                        </td>

                        <td>
                            <?php if ($ingredientsResult && mysqli_num_rows($ingredientsResult) > 0) { ?>
                                <ul>
                                    <?php while ($ingredient = mysqli_fetch_assoc($ingredientsResult)) { ?>
                                        <li>
                                            <?php echo htmlspecialchars($ingredient['ingredientQuantity'] . " " . $ingredient['ingredientName']); ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p>No ingredients</p>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ($instructionsResult && mysqli_num_rows($instructionsResult) > 0) { ?>
                                <ol>
                                    <?php while ($instruction = mysqli_fetch_assoc($instructionsResult)) { ?>
                                        <li><?php echo htmlspecialchars($instruction['step']); ?></li>
                                    <?php } ?>
                                </ol>
                            <?php } else { ?>
                                <p>No instructions</p>
                            <?php } ?>
                        </td>

                        <td class="noVideoText">
                            <?php if (!empty($recipe['videoFilePath'])) { ?>
                                <a href="<?php echo htmlspecialchars($recipe['videoFilePath']); ?>" target="_blank">View Video</a>
                            <?php } else { ?>
                                No video
                            <?php } ?>
                        </td>

                        <td><?php echo $recipe['totalLikes']; ?></td>

                        <td>
                            <a href="edit.php?id=<?php echo $recipeID; ?>">Edit</a>
                        </td>

                        <td>
                            <a href="deleteRecipe.php?recipeID=<?php echo $recipeID; ?>" onclick="return confirm('Are you sure you want to delete this recipe?');">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        <?php } else { ?>
            <div class="messageBox">You do not have any recipes yet.</div>
        <?php } ?>
    </div>

    <div class="center">
        <a href="add.php" class="primaryButton">➕ Add New Recipe</a>
    </div>

</div>

<!-- Site Footer -->
<footer>
    © 2026 Kids Recipes — Made with 💖 for little ones
</footer>

</body>
</html>
