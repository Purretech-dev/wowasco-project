<?php
session_start();
require_once "../../api/db.php";

if ($_SESSION['role'] !== 'super_admin') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role)
        VALUES (?, ?, ?, 'staff')
    ");

    $stmt->bind_param("sss", $name, $email, $password);
    $stmt->execute();

    echo "Staff created successfully";
}
?>

<form method="POST">
<h2>Create Staff</h2>
<input name="name" placeholder="Name">
<input name="email" placeholder="Email">
<input type="password" name="password" placeholder="Password">
<button type="submit">Create</button>
</form>