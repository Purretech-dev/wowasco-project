<?php
session_start();
require_once "../api/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['customer_id'] = $user['customer_id'];

        // ROUTING
        if ($user['role'] == 'customer') {
            header("Location: ../modules/customer/customer_portal.php");
        }

        elseif ($user['role'] == 'staff') {
            header("Location: ../modules/staff/staff_dashboard.php");
        }

        else {
            header("Location: ../modules/admin/admin_dashboard.php");
        }

        exit;

    } else {
        echo "Invalid login";
    }
}
?>

<form method="POST">
<h2>Login</h2>
<input name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>