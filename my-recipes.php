<?php
// تفعيل عرض الأخطاء للمساعدة في التصحيح (أزله بعد التأكد من العمل)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// التحقق من وجود مستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    die("❌ لم يتم تسجيل الدخول. يرجى إنشاء صفحة login.php أو تعيين user_id في الجلسة يدويًا للاختبار.");
}

$userID = $_SESSION['user_id'];

// اتصال بقاعدة البيانات مع تحديد المنفذ 8889
$host = 'localhost';
$port = 8889;
$user = 'root';
$password = '';     // اتركها فارغة إذا لم تضع كلمة مرور في MAMP
$database = 'recipedb';

$conn = new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    die("❌ فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// تعيين مجموعة المحارف لتجنب مشاكل العربية
$conn->set_charset("utf8");

// جلب الوصفات الخاصة بهذا المستخدم فقط
$stmt = $conn->prepare("SELECT id, name, description, photoFileName, videoFilePath FROM recipe WHERE userID = ? ORDER BY id DESC");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$recipes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// دوال مساعدة
function getIngredients($conn, $recipeID) {
    $stmt = $conn->prepare("SELECT ingredientName, ingredientQuantity FROM ingredients WHERE recipeID = ?");
    $stmt->bind_param("i", $recipeID);
    $stmt->execute();
    $res = $stmt->get_result();
    $ingredients = [];
    while ($row = $res->fetch_assoc()) {
        $ingredients[] = trim($row['ingredientQuantity'] . " " . $row['ingredientName']);
    }
    $stmt->close();
    return $ingredients;
}

function getInstructions($conn, $recipeID) {
    $stmt = $conn->prepare("SELECT step, stepOrder FROM instructions WHERE recipeID = ? ORDER BY stepOrder");
    $stmt->bind_param("i", $recipeID);
    $stmt->execute();
    $res = $stmt->get_result();
    $instructions = [];
    while ($row = $res->fetch_assoc()) {
        $instructions[] = $row['step'];
    }
    $stmt->close();
    return $instructions;
}

function countLikes($conn, $recipeID) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS likeCount FROM likes WHERE recipeID = ?");
    $stmt->bind_param("i", $recipeID);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row['likeCount'];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>وصفاتي - LittleChefs</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Comic+Neue:wght@700&display=swap" rel="stylesheet">
    <style>
        /* نفس الـ CSS الذي أرفقته أنت - لم يتغير شيء */
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .headerContent { display: flex; align-items: center; gap: 15px; }
        .logo { width: 130px; }
        header h1 { font-family: 'Comic Neue', cursive; font-size: 2.4rem; color: white; }
        nav a {
            margin-left: 25px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            padding-bottom: 5px;
        }
        nav a:hover, nav a.active { color: var(--yellowColor); border-bottom: 3px solid var(--yellowColor); }
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
        .pageHeader h2 { color: white; font-family: 'Comic Neue', cursive; font-size: 2.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: var(--accentColor); padding: 15px; text-align: center; }
        td { padding: 15px; vertical-align: top; border-bottom: 2px solid var(--lightColor); font-size: 0.95rem; }
        tr:hover { background-color: rgba(255, 218, 193, 0.3); }
        ul, ol { padding-left: 20px; }
        li { margin-bottom: 6px; }
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
        a { color: var(--darkColor); text-decoration: none; font-weight: 600; }
        a:hover { color: var(--primaryColor); text-decoration: underline; }
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
        .primaryButton:hover { transform: translateY(-3px); }
        .center { text-align: center; }
        .noVideoText { text-align: center; font-weight: 600; }
        .no-recipes {
            text-align: center;
            padding: 40px;
            font-size: 1.2rem;
            background: #fef9e6;
            border-radius: var(--borderRadius);
            margin: 20px;
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
<header>
    <div class="headerContent">
        <img src="photo.png" alt="Logo" class="logo">
        <h1>LittleChefs</h1>
    </div>
    <nav>
        <a href="index.html">Home</a>
        <a href="user.html" class="active">Users</a>
        <a href="admin.html">Admins</a>
    </nav>
</header>

<div class="card">
    <div class="pageHeader">
        <h2>وصفاتي الصحية للأطفال 👶🥦</h2>
    </div>
    <div class="card">
        <?php if (count($recipes) === 0): ?>
            <div class="no-recipes">
                😊 لم تقم بإضافة أي وصفة بعد. اضغط على الزر أدناه لمشاركة أول وصفة صحية لك!
            </div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>الوصفة</th>
                    <th>المكونات</th>
                    <th>الخطوات</th>
                    <th>الفيديو</th>
                    <th>الإعجابات</th>
                    <th>تعديل</th>
                    <th>حذف</th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $recipe):
                        $recipeID = $recipe['id'];
                        $ingredients = getIngredients($conn, $recipeID);
                        $instructions = getInstructions($conn, $recipeID);
                        $likesCount = countLikes($conn, $recipeID);
                        $photo = !empty($recipe['photoFileName']) ? "uploads/" . htmlspecialchars($recipe['photoFileName']) : "default-recipe.png";
                        $video = !empty($recipe['videoFilePath']) ? "uploads/" . htmlspecialchars($recipe['videoFilePath']) : null;
                    ?>
                    <tr>
                        <td>
                            <a href="view-recipe.php?id=<?= $recipeID ?>">
                                <img src="<?= $photo ?>" class="recipeImage" alt="<?= htmlspecialchars($recipe['name']) ?>">
                                <br><?= htmlspecialchars($recipe['name']) ?>
                            </a>
                         </td>
                        <td>
                            <?php if (empty($ingredients)): ?>
                                <em>لا توجد مكونات</em>
                            <?php else: ?>
                                <ul><?php foreach ($ingredients as $ing): ?><li><?= htmlspecialchars($ing) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                         </td>
                        <td>
                            <?php if (empty($instructions)): ?>
                                <em>لا توجد تعليمات</em>
                            <?php else: ?>
                                <ol><?php foreach ($instructions as $step): ?><li><?= htmlspecialchars($step) ?></li><?php endforeach; ?></ol>
                            <?php endif; ?>
                         </td>
                        <td class="noVideoText">
                            <?php if ($video): ?>
                                <a href="<?= $video ?>" target="_blank">🎥 مشاهدة الفيديو</a>
                            <?php else: ?>
                                لا يوجد فيديو
                            <?php endif; ?>
                        </td>
                        <td><?= $likesCount ?></td>
                        <td><a href="edit-recipe.php?recipeID=<?= $recipeID ?>">تعديل</a></td>
                        <td><a href="delete-recipe.php?id=<?= $recipeID ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الوصفة نهائياً؟');">حذف</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="center">
        <a href="add.html" class="primaryButton">➕ إضافة وصفة جديدة</a>
    </div>
</div>
<footer>
    © 2026 وصفات الأطفال — صنع بحب 💖 للصغار
</footer>
</body>
</html>
<?php $conn->close(); ?>
