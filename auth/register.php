<?php
require_once "../api/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // create customer record
    $conn->query("INSERT INTO customers (name, email) VALUES ('$name', '$email')");
    $customer_id = $conn->insert_id;

    // create user account
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role, customer_id)
        VALUES (?, ?, ?, 'customer', ?)
    ");

    $stmt->bind_param("sssi", $name, $email, $password, $customer_id);
    $stmt->execute();

    echo "Registration successful. You can now log in.";
}
?>

<form method="POST">
<h2>Customer Registration</h2>
<input name="name" placeholder="Full Name" required>
<input name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Register</button>
</form>