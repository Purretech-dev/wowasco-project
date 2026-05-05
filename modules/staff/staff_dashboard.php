<?php
session_start();

if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'super_admin') {
    die("Access denied");
}
?>

<h1>Staff Dashboard</h1>
<p>Operational modules go here</p>