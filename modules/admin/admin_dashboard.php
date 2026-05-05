<?php
session_start();

if ($_SESSION['role'] !== 'super_admin') {
    die("Access denied");
}
?>

<h1>Super Admin Dashboard</h1>
<a href="create_staff.php">Create Staff</a>
<a href="../customer/customer_portal.php">View System</a>