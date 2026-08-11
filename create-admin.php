<?php
include 'db.php';

$email = 'admin@example.com';

// Check if the admin already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "Admin user already exists.";
} else {
    $hashedPassword = password_hash("admin123", PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["Admin", $email, $hashedPassword, "admin"]);
    echo "Admin user created.";
}
?>
