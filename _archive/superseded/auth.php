<?php
session_start();
include "../library/conn.php"; // Adjust path if necessary

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Fetch user by email
    $query = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    // Check if user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        // Redirect to dashboard (you can change to home.php or admin.php)
        header("Location: ../dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: ../login.php");
        exit();
    }
}
?>
