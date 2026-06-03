<?php
require_once __DIR__ . '/../../includes/auth_guard.php';

if (!in_array(($_SESSION['role'] ?? ''), ['staff', 'super_admin'], true)) {
    die("Access denied");
}
?>

<h1>Staff Dashboard</h1>
<p>Operational modules go here</p>
