<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "root", "recipedb", 8889);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMessage = "";
$successMessage = "";

$recipeID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recipeID <= 0) {
    die("Invalid recipe ID");
}

$recipeSql = "SELECT * FROM recipe WHERE id = ?";
$recipeStmt = $conn->prepare($recipeSql);
$recipeStmt->bind_param("i", $recipeID);
$recipeStmt->execute();
$recipeResult = $recipeStmt->get_result();

if ($recipeResult->num_rows === 0) {
    die("Recipe not found");
}
$recipe = $recipeResult->fetch_assoc();
$recipeStmt->close();

$categories = [];
$catResult = $conn->query("SELECT id, categoryname FROM recipecategory ORDER BY id");
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

$ingredients = [];
$ingResult = $conn->query("SELECT * FROM ingredients WHERE recipeID = $recipeID");
while ($row = $ingResult->fetch_assoc()) {
    $ingredients[] = $row;
}

$instructions = [];
$instResult = $conn->query("SELECT * FROM instructions WHERE recipeID = $recipeID ORDER BY stepOrder");
while ($row = $instResult->fetch_assoc()) {
    $instructions[] = $row;
}

function getRandomDigits() {
    return rand(1000, 9999);
}

function cleanFileName($name) {
    $clean = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    return substr($clean, 0, 20);
}

function getUploadPath($type) {
    if ($type === 'image') {
        $dir = 'uploads/images/';
    } elseif ($type === 'video') {
        $dir = 'uploads/videos/';
    } else {
        $dir = 'uploads/';
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

$videoOption = 'none';
$videoUrl = '';
if (!empty($recipe['videoFilePath'])) {
    if (filter_var($recipe['videoFilePath'], FILTER_VALIDATE_URL)) {
        $videoOption = 'url';
        $videoUrl = $recipe['videoFilePath'];
    } else {
        $videoOption = 'upload';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateRecipe'])) {
    
    $name = trim($_POST['name']);
    $categoryID = (int)$_POST['categoryID'];
    $description = trim($_POST['description']);
    $videoOptionSelected = $_POST['videoOption'] ?? 'none';
    $videoUrlInput = $_POST['videoUrl'] ?? '';
    
    $newIngredients = [];
    if (!empty($_POST['ingredientName'])) {
        foreach ($_POST['ingredientName'] as $i => $ingName) {
            if (!empty($ingName) && !empty($_POST['ingredientQuantity'][$i])) {
                $newIngredients[] = [
                    'name' => $conn->real_escape_string($ingName),
                    'quantity' => $conn->real_escape_string($_POST['ingredientQuantity'][$i])
                ];
            }
        }
    }
    
    $newInstructions = [];
    if (!empty($_POST['stepDescription'])) {
        foreach ($_POST['stepDescription'] as $i => $stepDesc) {
            if (!empty($stepDesc)) {
                $newInstructions[] = [
                    'title' => !empty($_POST['stepTitle'][$i]) ? $conn->real_escape_string($_POST['stepTitle'][$i]) : '',
                    'description' => $conn->real_escape_string($stepDesc)
                ];
            }
        }
    }
    
    $photoFileName = $recipe['photoFileName'];
    $photoError = false;
    
    if (isset($_FILES['photoFileName']) && $_FILES['photoFileName']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = getUploadPath('image');
        
        $fileTmpPath = $_FILES['photoFileName']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['photoFileName']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 5 * 1024 * 1024;
        
        if ($_FILES['photoFileName']['size'] > $maxFileSize) {
            $errorMessage = "❌ Image file is too large! Maximum size is 5MB.";
            $photoError = true;
        } elseif (!in_array($fileExtension, $allowedExtensions)) {
            $errorMessage = "❌ Invalid image format! Allowed: JPG, PNG, GIF, WebP";
            $photoError = true;
        } else {
            $cleanName = cleanFileName($name);
            $randomDigits = getRandomDigits();
            $newFileName = $cleanName . '_' . $randomDigits . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                if (!empty($recipe['photoFileName']) && file_exists($recipe['photoFileName'])) {
                    unlink($recipe['photoFileName']);
                }
                $photoFileName = $destPath;
            } else {
                $errorMessage = "❌ Failed to upload image.";
                $photoError = true;
            }
        }
    } 
    elseif (empty($recipe['photoFileName']) || !file_exists($recipe['photoFileName'])) {
        $errorMessage = "❌ Photo is required! You must have a recipe image.";
        $photoError = true;
    }
    
    $videoFilePath = $recipe['videoFilePath'];
    
    if (!$photoError && empty($errorMessage)) {
        if ($videoOptionSelected === 'none') {
            if (!empty($recipe['videoFilePath']) && file_exists($recipe['videoFilePath']) && strpos($recipe['videoFilePath'], 'http') !== 0) {
                unlink($recipe['videoFilePath']);
            }
            $videoFilePath = '';
        } 
        elseif ($videoOptionSelected === 'url' && !empty($videoUrlInput)) {
            if (!empty($recipe['videoFilePath']) && file_exists($recipe['videoFilePath']) && strpos($recipe['videoFilePath'], 'http') !== 0) {
                unlink($recipe['videoFilePath']);
            }
            if (strpos($videoUrlInput, '?') !== false) {
                $videoFilePath = $videoUrlInput . '&recipe_id=' . $recipeID;
            } else {
                $videoFilePath = $videoUrlInput . '?recipe_id=' . $recipeID;
            }
        }
        elseif ($videoOptionSelected === 'upload' && isset($_FILES['videoFile']) && $_FILES['videoFile']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = getUploadPath('video');
            
            $fileTmpPath = $_FILES['videoFile']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['videoFile']['name'], PATHINFO_EXTENSION));
            $allowedVideoExtensions = ['mp4', 'avi', 'mov', 'mpeg', 'webm'];
            $maxVideoSize = 50 * 1024 * 1024;
            
            if ($_FILES['videoFile']['size'] > $maxVideoSize) {
                $errorMessage = "❌ Video file is too large! Maximum size is 50MB.";
            } elseif (!in_array($fileExtension, $allowedVideoExtensions)) {
                $errorMessage = "❌ Invalid video format! Allowed: MP4, AVI, MOV, MPEG, WebM";
            } else {
                $cleanName = cleanFileName($name);
                $randomDigits = getRandomDigits();
                $newFileName = $cleanName . '_video_' . $randomDigits . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    if (!empty($recipe['videoFilePath']) && file_exists($recipe['videoFilePath']) && strpos($recipe['videoFilePath'], 'http') !== 0) {
                        unlink($recipe['videoFilePath']);
                    }
                    $videoFilePath = $destPath;
                }
            }
        }
    }
    
    if (empty($errorMessage)) {
        $updateSql = "UPDATE recipe SET 
                      name = '" . $conn->real_escape_string($name) . "', 
                      categoryID = $categoryID, 
                      description = '" . $conn->real_escape_string($description) . "', 
                      photoFileName = '" . $conn->real_escape_string($photoFileName) . "', 
                      videoFilePath = '" . $conn->real_escape_string($videoFilePath) . "' 
                      WHERE id = $recipeID";
        
        if ($conn->query($updateSql)) {
            
            $conn->query("DELETE FROM ingredients WHERE recipeID = $recipeID");
            if (!empty($newIngredients)) {
                $stmt = $conn->prepare("INSERT INTO ingredients (recipeID, ingredientName, ingredientQuantity) VALUES (?, ?, ?)");
                foreach ($newIngredients as $ing) {
                    $stmt->bind_param("iss", $recipeID, $ing['name'], $ing['quantity']);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $conn->query("DELETE FROM instructions WHERE recipeID = $recipeID");
            if (!empty($newInstructions)) {
                $stmt = $conn->prepare("INSERT INTO instructions (recipeID, step, stepOrder) VALUES (?, ?, ?)");
                foreach ($newInstructions as $i => $inst) {
                    $stepOrder = $i + 1;
                    $fullStep = !empty($inst['title']) ? $inst['title'] . ': ' . $inst['description'] : $inst['description'];
                    $stmt->bind_param("isi", $recipeID, $fullStep, $stepOrder);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $successMessage = "✅ Recipe updated successfully! Redirecting...";
            
            $recipe['name'] = $name;
            $recipe['categoryID'] = $categoryID;
            $recipe['description'] = $description;
            $recipe['photoFileName'] = $photoFileName;
            $recipe['videoFilePath'] = $videoFilePath;
            
            $ingredients = $newIngredients;
            
            $instructions = [];
            foreach ($newInstructions as $i => $inst) {
                $instructions[] = [
                    'step' => (!empty($inst['title']) ? $inst['title'] . ': ' : '') . $inst['description'],
                    'stepOrder' => $i + 1
                ];
            }
            
            if (empty($videoFilePath)) {
                $videoOption = 'none';
                $videoUrl = '';
            } elseif (filter_var($videoFilePath, FILTER_VALIDATE_URL)) {
                $videoOption = 'url';
                $videoUrl = $videoFilePath;
            } else {
                $videoOption = 'upload';
            }
            
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'myrecipes.php';
                    }, 2000);
                  </script>";
            
        } else {
            $errorMessage = "❌ Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Recipe - Little Chefs</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Comic+Neue:wght@700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #FF9AA2;
      --secondary: #FFDAC1;
      --mint: #B5EAD7;
      --lavender: #C7CEEA;
      --yellow: #FFE5B4;
      --dark: #5D576B;
      --white: #ffffff;
      --border-radius: 16px;
      --box-shadow: 0 8px 25px rgba(93, 87, 107, 0.1);
      --transition: all 0.3s ease;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; }

    body {
      font-family: 'Nunito', sans-serif;
      background: linear-gradient(135deg, var(--secondary), var(--lavender));
      color: var(--dark);
      line-height: 1.6;
    }

    .container { min-height: 100vh; display: flex; flex-direction: column; }

    header {
      background: linear-gradient(to right, var(--primary), var(--lavender));
      padding: 15px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-left { display: flex; align-items: center; gap: 15px; }
    .logo { width: 130px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15)); }
    header h1 { font-family: 'Comic Neue', cursive; font-size: 2.4rem; color: white; }

    nav a {
      margin-left: 25px;
      color: white;
      text-decoration: none;
      font-weight: 700;
      cursor: pointer;
      padding-bottom: 5px;
    }
    nav a:hover { color: var(--yellow); }

    .main-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 20px;
    }

    .page-header {
      width: 100%;
      max-width: 1000px;
      margin-bottom: 30px;
      text-align: center;
    }

    .page-header h2 {
      font-family: 'Comic Neue', cursive;
      font-size: 2.8rem;
      color: var(--dark);
      margin-bottom: 10px;
      text-shadow: 2px 2px 0 var(--yellow);
    }

    .page-header p {
      font-size: 1.3rem;
      background: white;
      padding: 15px 30px;
      border-radius: 50px;
      display: inline-block;
      border: 4px solid var(--mint);
      box-shadow: var(--box-shadow);
    }

    .recipe-container {
      max-width: 1000px;
      width: 100%;
      background-color: white;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      overflow: hidden;
      border: 6px solid var(--mint);
    }

    .success-message {
      background-color: rgba(181, 234, 215, 0.3);
      padding: 25px;
      border-radius: 14px;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      border: 3px solid var(--mint);
    }

    .error-message {
      background-color: rgba(255, 154, 162, 0.2);
      padding: 25px;
      border-radius: 14px;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      border: 3px solid var(--primary);
    }

    .success-message i, .error-message i { margin-right: 15px; font-size: 2rem; }
    .success-message i { color: var(--mint); }
    .error-message i { color: var(--primary); }

    .recipe-form { padding: 40px; background-color: #FFF9F5; }

    .form-section {
      margin-bottom: 35px;
      padding: 25px;
      border-radius: 14px;
      background-color: white;
      border-left: 6px solid var(--mint);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      transition: var(--transition);
    }

    .form-section:hover {
      transform: translateY(-5px);
      border-left-color: var(--primary);
    }

    .section-title {
      font-family: 'Comic Neue', cursive;
      font-size: 1.8rem;
      color: var(--dark);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }

    .section-title i {
      margin-right: 12px;
      color: var(--primary);
      font-size: 1.5rem;
      background: var(--yellow);
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .form-group { margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; font-weight: 600; }
    .required { color: var(--primary); }

    input[type="text"], input[type="url"], textarea, select {
      width: 100%;
      padding: 15px 18px;
      border: 2px solid var(--secondary);
      border-radius: 10px;
      font-size: 1rem;
      font-family: 'Nunito', sans-serif;
      background-color: #FFFEFC;
    }

    input:focus, textarea:focus, select:focus {
      border-color: var(--primary);
      outline: none;
    }

    textarea { min-height: 120px; resize: vertical; }

    .ingredient-row, .step-row {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
      align-items: flex-start;
    }

    .ingredient-name, .step-number { flex: 2; }
    .ingredient-amount { flex: 1; }
    .step-content { flex: 3; }

    .add-btn, .remove-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 24px;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      font-size: 1rem;
      font-family: 'Comic Neue', cursive;
    }

    .add-btn {
      background-color: var(--mint);
      color: var(--dark);
      margin-top: 10px;
    }
    .add-btn:hover { background-color: #9CDBC4; transform: translateY(-3px); }

    .remove-btn {
      background-color: var(--primary);
      color: white;
      padding: 10px 18px;
    }
    .remove-btn:hover { background-color: #FF8A94; }

    .step-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 70px;
      padding-top: 28px;
    }

    .upload-area {
      border: 3px dashed var(--secondary);
      border-radius: 14px;
      padding: 35px 25px;
      text-align: center;
      cursor: pointer;
      transition: var(--transition);
      background-color: #FFFCF9;
    }
    .upload-area:hover { border-color: var(--primary); background-color: rgba(255, 154, 162, 0.05); }
    .upload-area i { font-size: 3rem; color: var(--primary); margin-bottom: 15px; }

    .current-image { margin-top: 15px; text-align: center; }
    .current-image img { max-width: 200px; border-radius: 12px; border: 3px solid var(--secondary); }

    .video-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .video-option { position: relative; }
    .video-option input[type="radio"] { position: absolute; opacity: 0; }
    .video-option label {
      display: block;
      padding: 18px;
      background-color: #FFFCF9;
      border-radius: 12px;
      text-align: center;
      cursor: pointer;
      border: 2px solid var(--secondary);
      font-weight: 500;
    }
    .video-option input[type="radio"]:checked + label {
      border-color: var(--primary);
      background-color: rgba(255, 154, 162, 0.1);
      color: var(--primary);
    }

    .video-input { display: none; margin-top: 20px; }
    .video-input.active { display: block; }

    .form-actions {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-top: 40px;
      padding-top: 30px;
      border-top: 3px dashed var(--secondary);
    }

    .submit-btn, .cancel-btn {
      padding: 18px 40px;
      font-size: 1.2rem;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 700;
      min-width: 180px;
      font-family: 'Comic Neue', cursive;
    }

    .submit-btn {
      background: linear-gradient(to right, var(--primary), var(--lavender));
      color: white;
    }
    .submit-btn:hover { transform: translateY(-5px); }

    .cancel-btn {
      background-color: #E0E0E0;
      color: var(--dark);
    }

    .counter { font-size: 0.85rem; color: #888; text-align: right; margin-top: 8px; }
    footer { background: var(--mint); padding: 25px; text-align: center; font-weight: 600; }

    @media (max-width: 768px) {
      header { flex-direction: column; gap: 15px; }
      .ingredient-row, .step-row { flex-direction: column; }
      .step-controls { width: 100%; justify-content: flex-start; padding-top: 0; }
      .form-actions { flex-direction: column; }
      .page-header h2 { font-size: 2.2rem; }
    }
  </style>
</head>
<body>

<div class="container">
  <header>
    <div class="header-left">
      <img src="photo.png" alt="Kids Recipes Logo" class="logo">
      <h1>LittleChefs</h1>
    </div>
    <nav>
      <a href="index.php">Home</a>
      <a href="#">Users</a>
      <a href="#">Admins</a>
    </nav>
  </header>

  <div class="main-wrapper">
    <div class="page-header">
      <h2><i class="fas fa-edit"></i> Edit Recipe</h2>
      <p>Update your recipe details and make it even better! ✨</p>
    </div>

    <div class="recipe-container">
      
      <?php if ($successMessage): ?>
      <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $successMessage; ?></span>
      </div>
      <?php endif; ?>
      
      <?php if ($errorMessage): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?php echo $errorMessage; ?></span>
      </div>
      <?php endif; ?>

      <form class="recipe-form" id="recipeForm" method="POST" enctype="multipart/form-data" style="<?php echo $successMessage ? 'display: none;' : 'display: block;'; ?>">
        <input type="hidden" name="updateRecipe" value="1">
        <input type="hidden" name="recipeID" value="<?php echo $recipeID; ?>">
        
        <div class="form-section">
          <h2 class="section-title"><i class="fas fa-info-circle"></i> Recipe Information</h2>

          <div class="form-group">
            <label for="name">Recipe Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($recipe['name']); ?>" required>
            <div class="counter"><span id="nameCount"><?php echo strlen($recipe['name']); ?></span>/50 characters</div>
          </div>

          <div class="form-group">
            <label for="categoryID">Recipe Category <span class="required">*</span></label>
            <select id="categoryID" name="categoryID" required>
              <option value="">Select a category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $recipe['categoryID']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($category['categoryname']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="description">Recipe Description <span class="required">*</span></label>
            <textarea id="description" name="description" required><?php echo htmlspecialchars($recipe['description']); ?></textarea>
            <div class="counter"><span id="descCount"><?php echo strlen($recipe['description']); ?></span>/200 characters</div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="section-title"><i class="fas fa-camera"></i> Recipe Picture <span class="required">*</span></h2>

          <?php if (!empty($recipe['photoFileName']) && file_exists($recipe['photoFileName'])): ?>
          <div class="current-image" id="currentImage">
            <p><strong>Current Image:</strong></p>
            <img src="<?php echo htmlspecialchars($recipe['photoFileName']); ?>" alt="Current recipe image">
            <div class="image-actions" style="margin-top: 10px;">
              <button type="button" class="change-image-btn" id="changeImageBtn" style="background-color: var(--lavender); color: var(--dark); padding: 8px 16px; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-exchange-alt"></i> Change Image
              </button>
            </div>
          </div>
          <?php endif; ?>

          <div class="form-group" id="uploadSection" style="<?php echo (!empty($recipe['photoFileName']) && file_exists($recipe['photoFileName'])) ? 'display: none;' : 'display: block; margin-top: 20px;'; ?>">
            <label>Upload Recipe Image <span class="required">*</span></label>
            <div class="upload-area" id="uploadArea">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Click to upload image</p>
              <span>Max size: 5MB (JPG, PNG, GIF, WebP)</span>
            </div>
            <input type="file" id="recipeImage" name="photoFileName" accept="image/*" style="display: none;">
            <div id="file-name"></div>
          </div>
          <p id="photoErrorMsg" style="color: red; margin-top: 10px; display: none;">⚠️ Photo is required!</p>
        </div>

        <div class="form-section">
          <h2 class="section-title"><i class="fas fa-list-ul"></i> Ingredients</h2>

          <div id="ingredientsContainer">
            <?php if (empty($ingredients)): ?>
            <div class="ingredient-row">
              <div class="form-group ingredient-name">
                <label>Ingredient Name <span class="required">*</span></label>
                <input type="text" name="ingredientName[]" placeholder="e.g., Banana 🍌" required>
              </div>
              <div class="form-group ingredient-amount">
                <label>Amount <span class="required">*</span></label>
                <input type="text" name="ingredientQuantity[]" placeholder="e.g., 1 medium" required>
              </div>
            </div>
            <?php else: ?>
              <?php foreach ($ingredients as $ingredient): ?>
              <div class="ingredient-row">
                <div class="form-group ingredient-name">
                  <label>Ingredient Name <span class="required">*</span></label>
                  <input type="text" name="ingredientName[]" value="<?php echo htmlspecialchars($ingredient['ingredientName']); ?>" required>
                </div>
                <div class="form-group ingredient-amount">
                  <label>Amount <span class="required">*</span></label>
                  <input type="text" name="ingredientQuantity[]" value="<?php echo htmlspecialchars($ingredient['ingredientQuantity']); ?>" required>
                </div>
                <div class="step-controls">
                  <button type="button" class="remove-btn remove-ingredient">
                    <i class="fas fa-trash"></i> Remove
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <button type="button" class="add-btn" id="addIngredient">
            <i class="fas fa-plus"></i> Add More Ingredients
          </button>
        </div>

        <div class="form-section">
          <h2 class="section-title"><i class="fas fa-list-ol"></i> Instructions</h2>

          <div id="stepsContainer">
            <?php if (empty($instructions)): ?>
            <div class="step-row">
              <div class="form-group step-number">
                <label>Step #<span class="step-counter">1</span></label>
                <input type="text" name="stepTitle[]" placeholder="Step title (optional)">
              </div>
              <div class="form-group step-content">
                <label>Step Description <span class="required">*</span></label>
                <textarea name="stepDescription[]" placeholder="Write step in a fun way for kids! 🎯" rows="2" required></textarea>
              </div>
            </div>
            <?php else: ?>
              <?php foreach ($instructions as $index => $instruction): 
                $stepText = $instruction['step'];
                $stepTitle = '';
                $stepDesc = $stepText;
                if (strpos($stepText, ': ') !== false) {
                    $stepTitle = substr($stepText, 0, strpos($stepText, ': '));
                    $stepDesc = substr($stepText, strpos($stepText, ': ') + 2);
                }
              ?>
              <div class="step-row">
                <div class="form-group step-number">
                  <label>Step #<span class="step-counter"><?php echo $index + 1; ?></span></label>
                  <input type="text" name="stepTitle[]" value="<?php echo htmlspecialchars($stepTitle); ?>" placeholder="Step title (optional)">
                </div>
                <div class="form-group step-content">
                  <label>Step Description <span class="required">*</span></label>
                  <textarea name="stepDescription[]" rows="2" required><?php echo htmlspecialchars($stepDesc); ?></textarea>
                </div>
                <div class="step-controls">
                  <button type="button" class="remove-btn remove-step">
                    <i class="fas fa-trash"></i> Remove
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <button type="button" class="add-btn" id="addStep">
            <i class="fas fa-plus"></i> Add Another Step
          </button>
        </div>

        <div class="form-section">
          <h2 class="section-title"><i class="fas fa-video"></i> Recipe Video (Optional)</h2>

          <div class="video-options">
            <div class="video-option">
              <input type="radio" id="videoNone" name="videoOption" value="none" <?php echo $videoOption === 'none' ? 'checked' : ''; ?>>
              <label for="videoNone"><i class="fas fa-times-circle"></i> No Video</label>
            </div>
            <div class="video-option">
              <input type="radio" id="videoURL" name="videoOption" value="url" <?php echo $videoOption === 'url' ? 'checked' : ''; ?>>
              <label for="videoURL"><i class="fas fa-link"></i> Video Link</label>
            </div>
            <div class="video-option">
              <input type="radio" id="videoUpload" name="videoOption" value="upload" <?php echo $videoOption === 'upload' ? 'checked' : ''; ?>>
              <label for="videoUpload"><i class="fas fa-upload"></i> Upload Video</label>
            </div>
          </div>

          <div class="video-input" id="urlInput" style="display: <?php echo $videoOption === 'url' ? 'block' : 'none'; ?>;">
            <div class="form-group">
              <label for="videoUrl">Video URL</label>
              <input type="url" id="videoUrl" name="videoUrl" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars($videoUrl); ?>">
            </div>
          </div>

          <div class="video-input" id="uploadInput" style="display: <?php echo $videoOption === 'upload' ? 'block' : 'none'; ?>;">
            <div class="form-group">
              <label for="videoFile">Video File</label>
              <input type="file" id="videoFile" name="videoFile" accept="video/*">
              <div class="counter">Max size: 50MB (MP4, AVI, MOV, MPEG, WebM)</div>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" name="updateRecipe" class="submit-btn" id="submitBtn">
            <i class="fas fa-save"></i> Update Recipe
          </button>
          <button type="button" class="cancel-btn" id="cancelBtn">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <footer>
    © 2026 Kids Recipes — Made with 💖 for little ones
  </footer>
</div>

<script>
  const recipeForm = document.getElementById('recipeForm');
  const ingredientsContainer = document.getElementById('ingredientsContainer');
  const stepsContainer = document.getElementById('stepsContainer');
  const addIngredientBtn = document.getElementById('addIngredient');
  const addStepBtn = document.getElementById('addStep');
  const uploadArea = document.getElementById('uploadArea');
  const recipeImage = document.getElementById('recipeImage');
  const fileNameDisplay = document.getElementById('file-name');
  const submitBtn = document.getElementById('submitBtn');
  const photoErrorMsg = document.getElementById('photoErrorMsg');

  const nameCount = document.getElementById('nameCount');
  const descCount = document.getElementById('descCount');
  const recipeNameInput = document.getElementById('name');
  const recipeDescInput = document.getElementById('description');

  const videoOptions = document.querySelectorAll('input[name="videoOption"]');
  const urlInput = document.getElementById('urlInput');
  const uploadInput = document.getElementById('uploadInput');

  let hasExistingPhoto = <?php echo (!empty($recipe['photoFileName']) && file_exists($recipe['photoFileName'])) ? 'true' : 'false'; ?>;
  let newPhotoSelected = false;

  if (recipeNameInput) {
    recipeNameInput.addEventListener('input', function() {
      nameCount.textContent = this.value.length;
      nameCount.style.color = this.value.length > 50 ? '#FF9AA2' : '#B5EAD7';
    });
  }

  if (recipeDescInput) {
    recipeDescInput.addEventListener('input', function() {
      descCount.textContent = this.value.length;
      descCount.style.color = this.value.length > 200 ? '#FF9AA2' : '#B5EAD7';
    });
  }

  if (uploadArea) {
    uploadArea.addEventListener('click', function() { if (recipeImage) recipeImage.click(); });
  }

  if (recipeImage) {
    recipeImage.addEventListener('change', function() {
      if (this.files.length > 0) {
        newPhotoSelected = true;
        if (fileNameDisplay) {
          fileNameDisplay.innerHTML = `<i class="fas fa-check-circle"></i> Selected: <strong>${this.files[0].name}</strong>`;
          fileNameDisplay.style.color = 'green';
        }
        if (uploadArea) uploadArea.style.borderColor = '#B5EAD7';
        if (photoErrorMsg) photoErrorMsg.style.display = 'none';
      }
    });
  }

  if (recipeForm) {
    recipeForm.addEventListener('submit', function(e) {
      if (!hasExistingPhoto && !newPhotoSelected) {
        e.preventDefault();
        if (photoErrorMsg) photoErrorMsg.style.display = 'block';
        if (uploadArea) {
          uploadArea.style.borderColor = '#FF9AA2';
          uploadArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
      }
    });
  }

  let ingredientCount = <?php echo count($ingredients) > 0 ? count($ingredients) : 1; ?>;

  if (addIngredientBtn) {
    addIngredientBtn.addEventListener('click', function() {
      ingredientCount++;
      const newRow = document.createElement('div');
      newRow.className = 'ingredient-row';
      newRow.innerHTML = `
        <div class="form-group ingredient-name">
          <label>Ingredient Name <span class="required">*</span></label>
          <input type="text" name="ingredientName[]" placeholder="e.g., Cheese 🧀" required>
        </div>
        <div class="form-group ingredient-amount">
          <label>Amount <span class="required">*</span></label>
          <input type="text" name="ingredientQuantity[]" placeholder="e.g., 2 slices" required>
        </div>
        <div class="step-controls">
          <button type="button" class="remove-btn remove-ingredient">
            <i class="fas fa-trash"></i> Remove
          </button>
        </div>
      `;
      ingredientsContainer.appendChild(newRow);
      newRow.querySelector('.remove-ingredient').addEventListener('click', function() {
        ingredientsContainer.removeChild(newRow);
        ingredientCount--;
      });
    });
  }

  let stepCount = <?php echo count($instructions) > 0 ? count($instructions) : 1; ?>;

  function updateStepNumbers() {
    document.querySelectorAll('.step-counter').forEach((counter, idx) => { counter.textContent = idx + 1; });
  }

  if (addStepBtn) {
    addStepBtn.addEventListener('click', function() {
      stepCount++;
      const newRow = document.createElement('div');
      newRow.className = 'step-row';
      newRow.innerHTML = `
        <div class="form-group step-number">
          <label>Step #<span class="step-counter">${stepCount}</span></label>
          <input type="text" name="stepTitle[]" placeholder="Step title (optional)">
        </div>
        <div class="form-group step-content">
          <label>Step Description <span class="required">*</span></label>
          <textarea name="stepDescription[]" placeholder="Write step in a fun way for kids! 🎯" rows="2" required></textarea>
        </div>
        <div class="step-controls">
          <button type="button" class="remove-btn remove-step">
            <i class="fas fa-trash"></i> Remove
          </button>
        </div>
      `;
      stepsContainer.appendChild(newRow);
      newRow.querySelector('.remove-step').addEventListener('click', function() {
        stepsContainer.removeChild(newRow);
        stepCount--;
        updateStepNumbers();
      });
      updateStepNumbers();
    });
  }

  if (videoOptions.length) {
    videoOptions.forEach(option => {
      option.addEventListener('change', function() {
        if (urlInput) urlInput.style.display = 'none';
        if (uploadInput) uploadInput.style.display = 'none';
        if (this.value === 'url' && urlInput) urlInput.style.display = 'block';
        else if (this.value === 'upload' && uploadInput) uploadInput.style.display = 'block';
      });
    });
  }

  const cancelBtn = document.getElementById('cancelBtn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function() { window.location.href = 'myrecipes.php'; });
  }

  const changeImageBtn = document.getElementById('changeImageBtn');
  const currentImage = document.getElementById('currentImage');
  const uploadSection = document.getElementById('uploadSection');

  if (changeImageBtn) {
    changeImageBtn.addEventListener('click', function() {
      if (currentImage) currentImage.style.display = 'none';
      if (uploadSection) uploadSection.style.display = 'block';
      hasExistingPhoto = false;
    });
  }

  document.querySelectorAll('.remove-ingredient').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('.ingredient-row');
      if (row && ingredientCount > 1) {
        ingredientsContainer.removeChild(row);
        ingredientCount--;
      }
    });
  });

  document.querySelectorAll('.remove-step').forEach(btn => {
    btn.addEventListener('click', function() {
      const row = this.closest('.step-row');
      if (row && stepCount > 1) {
        stepsContainer.removeChild(row);
        stepCount--;
        updateStepNumbers();
      }
    });
  });
</script>

</body>
</html>
