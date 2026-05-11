<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: signin.html?error=Invalid request");
    exit();
}

$firstName = trim($_POST["firstName"] ?? "");
$lastName = trim($_POST["lastName"] ?? "");
$emailAddress = trim($_POST["emailAddress"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

if (
    $firstName === "" ||
    $lastName === "" ||
    $emailAddress === "" ||
    $password === "" ||
    $confirmPassword === ""
) {
    header("Location: signin.html?error=Please fill in all fields");
    exit();
}

if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    header("Location: signin.html?error=Invalid email address");
    exit();
}

if ($password !== $confirmPassword) {
    header("Location: signin.html?error=Passwords do not match");
    exit();
}

// Check if email already exists in User table
$checkUserSql = "SELECT id FROM user WHERE emailAddress = ?";
$checkUserStmt = $conn->prepare($checkUserSql);

if (!$checkUserStmt) {
    header("Location: signin.html?error=Database error");
    exit();
}

$checkUserStmt->bind_param("s", $emailAddress);
$checkUserStmt->execute();
$userResult = $checkUserStmt->get_result();

if ($userResult->num_rows > 0) {
    header("Location: signin.html?error=This email is already registered");
    exit();
}

// Check if email exists in BlockedUser table
$checkBlockedSql = "SELECT id FROM blockeduser WHERE emailAddress = ?";
$checkBlockedStmt = $conn->prepare($checkBlockedSql);

if (!$checkBlockedStmt) {
    header("Location: signin.html?error=Database error");
    exit();
}

$checkBlockedStmt->bind_param("s", $emailAddress);
$checkBlockedStmt->execute();
$blockedResult = $checkBlockedStmt->get_result();

if ($blockedResult->num_rows > 0) {
    header("Location: signin.html?error=This email is blocked and cannot register");
    exit();
}

// Default photo
$photoFileName = "default.png";
$uploadDirectory = "images/users/";

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

// Upload photo if provided
if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === 0) {
    $tmpName = $_FILES["photo"]["tmp_name"];
    $originalName = basename($_FILES["photo"]["name"]);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];

    if (in_array($extension, $allowedExtensions)) {
        $tempUniqueName = "user_" . time() . "_" . rand(1000, 9999) . "." . $extension;

        if (move_uploaded_file($tmpName, $uploadDirectory . $tempUniqueName)) {
            $photoFileName = $tempUniqueName;
        }
    }
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$userType = "user";

// Insert user
$insertSql = "INSERT INTO user (userType, firstName, lastName, emailAddress, password, photoFileName)
              VALUES (?, ?, ?, ?, ?, ?)";
$insertStmt = $conn->prepare($insertSql);

if (!$insertStmt) {
    header("Location: signin.html?error=Database error");
    exit();
}

$insertStmt->bind_param(
    "ssssss",
    $userType,
    $firstName,
    $lastName,
    $emailAddress,
    $hashedPassword,
    $photoFileName
);

if (!$insertStmt->execute()) {
    header("Location: signin.html?error=Could not create account");
    exit();
}

$newUserId = $insertStmt->insert_id;

// Rename uploaded image to include user id
if ($photoFileName !== "default.png" && file_exists($uploadDirectory . $photoFileName)) {
    $oldPath = $uploadDirectory . $photoFileName;
    $extension = strtolower(pathinfo($photoFileName, PATHINFO_EXTENSION));
    $newPhotoFileName = "user_" . $newUserId . "." . $extension;
    $newPath = $uploadDirectory . $newPhotoFileName;

    if (rename($oldPath, $newPath)) {
        $photoFileName = $newPhotoFileName;

        $updatePhotoSql = "UPDATE user SET photoFileName = ? WHERE id = ?";
        $updatePhotoStmt = $conn->prepare($updatePhotoSql);
        if ($updatePhotoStmt) {
            $updatePhotoStmt->bind_param("si", $photoFileName, $newUserId);
            $updatePhotoStmt->execute();
        }
    }
}

// Session variables
$_SESSION["userID"] = $newUserId;
$_SESSION["userType"] = $userType;

// Redirect to user page
header("Location: user.php?success=Account created successfully");
exit();
?>
