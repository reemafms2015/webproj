<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html?error=Invalid request");
    exit();
}

$emailAddress = trim($_POST["emailAddress"] ?? "");
$password = $_POST["password"] ?? "";

if ($emailAddress === "" || $password === "") {
    header("Location: login.html?error=Please enter email and password");
    exit();
}

if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    header("Location: login.html?error=Invalid email address");
    exit();
}

// Check if blocked
$blockedSql = "SELECT id FROM blockeduser WHERE emailAddress = ?";
$blockedStmt = $conn->prepare($blockedSql);

if (!$blockedStmt) {
    header("Location: login.html?error=Database error");
    exit();
}

$blockedStmt->bind_param("s", $emailAddress);
$blockedStmt->execute();
$blockedResult = $blockedStmt->get_result();

if ($blockedResult->num_rows > 0) {
    header("Location: login.html?error=Your account is blocked");
    exit();
}

// Check user exists
$userSql = "SELECT id, userType, password FROM user WHERE emailAddress = ?";
$userStmt = $conn->prepare($userSql);

if (!$userStmt) {
    header("Location: login.html?error=Database error");
    exit();
}

$userStmt->bind_param("s", $emailAddress);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    header("Location: login.html?error=Incorrect email or password");
    exit();
}

$user = $userResult->fetch_assoc();

$storedPassword = $user["password"];

if (
    !password_verify($password, $storedPassword) &&
    $password !== $storedPassword
) {
    header("Location: login.html?error=Incorrect email or password");
    exit();
}

// Save session
$_SESSION["userID"] = $user["id"];
$_SESSION["userType"] = $user["userType"];

// Redirect based on type
if (strtolower($user["userType"]) === "admin") {
    header("Location: admin.html");
    exit();
} else {
    header("Location: user.php");
    exit();
}
?>
