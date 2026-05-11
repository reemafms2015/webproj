<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID']) || !isset($_SESSION['userType'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Recipe ID is missing.");
}

$recipeID = $_GET['id'];
$userID = $_SESSION['userID'];
$userType = $_SESSION['userType'];

$sqlRecipe = "SELECT recipe.*, user.firstName, user.lastName, user.photoFileName AS userPhoto, recipecategory.categoryname
FROM recipe
JOIN user ON recipe.userID = user.id
JOIN recipecategory ON recipe.categoryID = recipecategory.id
WHERE recipe.id = ?";

$stmtRecipe = mysqli_prepare($conn, $sqlRecipe);
mysqli_stmt_bind_param($stmtRecipe, "i", $recipeID);
mysqli_stmt_execute($stmtRecipe);
$resultRecipe = mysqli_stmt_get_result($stmtRecipe);

if (mysqli_num_rows($resultRecipe) == 0) {
    die("Recipe not found.");
}

$recipe = mysqli_fetch_assoc($resultRecipe);

$sqlIngredients = "SELECT * FROM ingredients WHERE recipeID = ?";
$stmtIngredients = mysqli_prepare($conn, $sqlIngredients);
mysqli_stmt_bind_param($stmtIngredients, "i", $recipeID);
mysqli_stmt_execute($stmtIngredients);
$ingredientsResult = mysqli_stmt_get_result($stmtIngredients);

$sqlInstructions = "SELECT * FROM instructions WHERE recipeID = ? ORDER BY stepOrder ASC";
$stmtInstructions = mysqli_prepare($conn, $sqlInstructions);
mysqli_stmt_bind_param($stmtInstructions, "i", $recipeID);
mysqli_stmt_execute($stmtInstructions);
$instructionsResult = mysqli_stmt_get_result($stmtInstructions);

$sqlComments = "SELECT comment.*, user.firstName, user.lastName, user.photoFileName
FROM comment
JOIN user ON comment.userID = user.id
WHERE comment.recipeID = ?
ORDER BY comment.id DESC";
$stmtComments = mysqli_prepare($conn, $sqlComments);
mysqli_stmt_bind_param($stmtComments, "i", $recipeID);
mysqli_stmt_execute($stmtComments);
$commentsResult = mysqli_stmt_get_result($stmtComments);

$isCreator = ($userID == $recipe['userID']);

$sqlFavourite = "SELECT * FROM favourites WHERE userID = ? AND recipeID = ?";
$stmtFavourite = mysqli_prepare($conn, $sqlFavourite);
mysqli_stmt_bind_param($stmtFavourite, "ii", $userID, $recipeID);
mysqli_stmt_execute($stmtFavourite);
$favouriteResult = mysqli_stmt_get_result($stmtFavourite);
$isFavourite = mysqli_num_rows($favouriteResult) > 0;

$sqlLike = "SELECT * FROM likes WHERE userID = ? AND recipeID = ?";
$stmtLike = mysqli_prepare($conn, $sqlLike);
mysqli_stmt_bind_param($stmtLike, "ii", $userID, $recipeID);
mysqli_stmt_execute($stmtLike);
$likeResult = mysqli_stmt_get_result($stmtLike);
$isLiked = mysqli_num_rows($likeResult) > 0;

$sqlReport = "SELECT * FROM report WHERE userID = ? AND recipeID = ?";
$stmtReport = mysqli_prepare($conn, $sqlReport);
mysqli_stmt_bind_param($stmtReport, "ii", $userID, $recipeID);
mysqli_stmt_execute($stmtReport);
$reportResult = mysqli_stmt_get_result($stmtReport);
$isReported = mysqli_num_rows($reportResult) > 0;

$recipePhoto = !empty($recipe['photoFileName']) ? $recipe['photoFileName'] : "default-recipe.png";
$userPhoto = !empty($recipe['userPhoto']) ? $recipe['userPhoto'] : "default-user.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>View Recipe - Little Chefs</title>
<link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#FF9AA2;--accent:#FFDAC1;--green:#B5EAD7;--blue:#C7CEEA;--yellow:#FFE5B4;--dark:#5D576B;--light:#FFF9F5;--radius:16px;--shadow:0 8px 25px rgba(93, 87, 107, 0.10);--t: all 0.25s ease;}
*{margin:0;padding:0;box-sizing:border-box;} body{font-family:'Nunito', sans-serif;background: linear-gradient(135deg, var(--accent) 0%, var(--blue) 100%);min-height:100vh;color:var(--dark);} .site-header{background: linear-gradient(to right, var(--primary), var(--blue));padding: 15px 40px;display:flex;justify-content:space-between;align-items:center;margin:0;} .header-left{display:flex;align-items:center;gap:15px;} .logo{width:130px;height:auto;filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));} .site-header h1{font-family:'Comic Neue', cursive;font-size:2.4rem;color:white;} nav a{margin-left:25px;color:white;text-decoration:none;font-weight:700;cursor:pointer;padding-bottom:5px;position:relative;} nav a:hover{color:var(--yellow);} nav a.active{color:var(--yellow);border-bottom:3px solid var(--yellow);} .page-footer{background: var(--green);padding: 25px;text-align:center;font-weight:600;} .page-wrap{padding:20px;} .container{max-width: 1000px;margin: 0 auto;background:#fff;border-radius: var(--radius);box-shadow: var(--shadow);overflow:hidden;border: 6px solid var(--yellow);} .recipe-header{background: linear-gradient(to right, var(--primary), var(--blue));color:#fff;padding:28px 24px;position:relative;} .header-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;} .title-wrap h1{font-family:'Comic Neue', cursive;font-size: 2.4rem;line-height:1.15;text-shadow: 2px 2px 0 rgba(93,87,107,0.25);margin-bottom:6px;} .actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;} .action-btn{border:none;cursor:pointer;border-radius: 999px;padding: 12px 16px;font-weight:700;font-family:'Comic Neue', cursive;transition: var(--t);box-shadow: 0 5px 0 rgba(93,87,107,0.35);background: rgba(255,255,255,0.95);color: var(--dark);min-width: 150px;} .action-btn:hover{transform: translateY(-3px);box-shadow: 0 8px 0 rgba(93,87,107,0.35);} .action-btn.primary{background: #fff;border: 3px solid rgba(255,255,255,0.6);} .action-btn:disabled{opacity:0.6;cursor:not-allowed;transform:none;box-shadow: 0 5px 0 rgba(93,87,107,0.20);} .content{padding: 26px;background: var(--light);} .card{background:#fff;border-radius: 14px;padding: 20px;border-left: 6px solid var(--green);box-shadow: 0 4px 15px rgba(0,0,0,0.05);transition: var(--t);margin-bottom: 18px;} .card:hover{transform: translateY(-3px);border-left-color: var(--primary);box-shadow: 0 8px 20px rgba(0,0,0,0.07);} .section-title{font-family:'Comic Neue', cursive;font-size: 1.6rem;margin-bottom: 12px;} .recipe-top{display:grid;grid-template-columns: 320px 1fr;gap: 18px;align-items:start;} .recipe-photo{width:100%;border-radius: 14px;border: 4px solid var(--accent);box-shadow: 0 6px 18px rgba(0,0,0,0.08);display:block;background:#fff;} .info-grid{display:grid;gap: 12px;} .pill-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;} .pill{background: rgba(181,234,215,0.35);padding: 8px 12px;border-radius: 999px;font-weight: 800;font-size: 0.95rem;} .desc{background: #FFFEFC;border: 2px solid var(--accent);border-radius: 12px;padding: 14px;line-height: 1.65;} .creator{display:flex;align-items:center;gap:12px;flex-wrap:wrap;} .avatar{width:60px;height:60px;border-radius: 50%;object-fit:cover;border: 3px solid var(--accent);background:#fff;} .creator .name{font-weight: 900;font-size: 1.05rem;} .muted{opacity:0.85;font-size:0.95rem;} ul, ol{padding-left: 20px;line-height:1.8;} .comments-wrap{display:grid;gap:12px;} .comment-form{display:flex;gap:10px;flex-wrap:wrap;align-items:stretch;} .comment-form input{flex: 1;min-width: 240px;padding: 14px 14px;border-radius: 12px;border: 2px solid var(--accent);outline:none;font-size: 1rem;background:#FFFEFC;} .comment-form input:focus{border-color: var(--primary);box-shadow: 0 0 0 3px rgba(255,154,162,0.18);} .comment-form button{border:none;cursor:pointer;border-radius: 12px;padding: 14px 18px;font-weight: 900;font-family:'Comic Neue', cursive;background: var(--green);color: var(--dark);box-shadow: 0 5px 0 #8FD5B7;transition: var(--t);min-width: 150px;} .comment-form button:hover{transform: translateY(-3px);box-shadow: 0 8px 0 #8FD5B7;} .comment{border: 2px solid var(--accent);border-radius: 12px;padding: 12px;background:#fff;display:flex;gap:12px;align-items:flex-start;} .comment .avatar{width:46px;height:46px;border-width: 2px;} .comment .meta{display:flex;flex-direction:column;gap:4px;} .comment .meta .who{font-weight: 900;} .comment .meta .text{line-height:1.55;} .comment .meta .time{font-size:0.85rem;opacity:0.75;} .msg{background:#fff6d8;border:2px solid #ffe08a;padding:12px;border-radius:12px;margin-bottom:15px;font-weight:700;} @media (max-width: 820px){.recipe-top{ grid-template-columns: 1fr; }.actions{ justify-content:flex-start; }.action-btn{ width: 100%; min-width:auto; }.site-header{flex-direction:column;gap:15px;}.logo{width:110px;}}
</style>
</head>
<body>
<header class="site-header"><div class="header-left"><img src="photo.png" alt="Kids Recipes Logo" class="logo"><h1>LittleChefs</h1></div><nav><a href="index.php">Home</a><a href="user.php">Users</a><a href="admin.php">Admins</a></nav></header>
<div class="page-wrap"><div class="container">
<section class="recipe-header"><div class="header-row"><div class="title-wrap"><h1><?php echo $recipe['name']; ?></h1></div><div class="actions"><?php if (!$isCreator && $userType != 'admin') { ?>
<form action="add-favourite.php" method="POST" style="display:inline;"><input type="hidden" name="recipeID" value="<?php echo $recipeID; ?>"><button class="action-btn primary" type="submit" <?php if ($isFavourite) echo "disabled"; ?>><?php if ($isFavourite) { echo "Added to favourites"; } else { echo "Add to favourites"; } ?></button></form>
<form action="add-like.php" method="POST" style="display:inline;"><input type="hidden" name="recipeID" value="<?php echo $recipeID; ?>"><button class="action-btn primary" type="submit" <?php if ($isLiked) echo "disabled"; ?>><?php if ($isLiked) { echo "Liked"; } else { echo "Like recipe"; } ?></button></form>
<form action="add-report.php" method="POST" style="display:inline;"><input type="hidden" name="recipeID" value="<?php echo $recipeID; ?>"><button class="action-btn primary" type="submit" <?php if ($isReported) echo "disabled"; ?>><?php if ($isReported) { echo "Reported"; } else { echo "Report recipe"; } ?></button></form>
<?php } ?></div></div></section>
<main class="content">
<?php if (isset($_GET['msg'])) { echo '<div class="msg">' . $_GET['msg'] . '</div>'; } ?>
<section class="card"><div class="recipe-top"><img class="recipe-photo" src="<?php echo $recipePhoto; ?>" alt="Recipe Photo"/><div class="info-grid"><div><div class="section-title">Recipe Creator</div><div class="creator"><img class="avatar" src="<?php echo $userPhoto; ?>" alt="Creator photo"><div><div class="name"><?php echo $recipe['firstName'] . " " . $recipe['lastName']; ?></div><div class="muted">Recipe Creator</div></div></div></div><div><div class="section-title">Details</div><div class="pill-row"><div class="pill">Category: <?php echo $recipe['categoryname']; ?></div></div><div class="desc"><?php echo $recipe['description']; ?></div></div></div></div></section>
<section class="card"><div class="section-title">Ingredients</div><ul><?php while($ingredient = mysqli_fetch_assoc($ingredientsResult)) { ?><li><?php echo $ingredient['ingredientQuantity'] . " " . $ingredient['ingredientName']; ?></li><?php } ?></ul></section>
<section class="card"><div class="section-title">Instructions</div><ol><?php while($instruction = mysqli_fetch_assoc($instructionsResult)) { ?><li><?php echo $instruction['step']; ?></li><?php } ?></ol></section>
<section class="card"><div class="section-title">Comments</div><div class="comments-wrap"><form class="comment-form" action="add-comment.php" method="POST"><input type="hidden" name="recipeID" value="<?php echo $recipeID; ?>"><input type="text" name="comment" placeholder="Write a comment..." maxlength="200" required /><button type="submit">Add Comment</button></form><div id="commentsList"><?php while($comment = mysqli_fetch_assoc($commentsResult)) { $commentPhoto = !empty($comment['photoFileName']) ? $comment['photoFileName'] : "default-user.png"; ?><div class="comment"><img class="avatar" src="<?php echo $commentPhoto; ?>" alt="User photo"><div class="meta"><div class="who"><?php echo $comment['firstName'] . " " . $comment['lastName']; ?></div><div class="text"><?php echo $comment['comment']; ?></div><div class="time"><?php echo $comment['data']; ?></div></div></div><?php } ?></div></div></section>
</main></div></div>
<footer class="page-footer">© 2026 Kids Recipes — Made with 💖 for little ones</footer>
</body>
</html>
