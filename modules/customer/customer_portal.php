<?php
session_start();
require_once "../../api/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    die("Access denied");
}

$customer_id = $_SESSION['customer_id'];

/* ================= DATA ================= */
$bills = $conn->query("SELECT * FROM bills WHERE customer_id=$customer_id ORDER BY created_at DESC");

$payments = $conn->query("SELECT * FROM payments WHERE customer_id=$customer_id ORDER BY payment_date DESC");

$consumption = $conn->query("SELECT month, units_used FROM consumption WHERE customer_id=$customer_id ORDER BY month ASC");

$schedule = $conn->query("SELECT * FROM rationing_schedule ORDER BY schedule_date DESC");

include("../../includes/sidebar.php");
include("../../includes/navbar.php");
include("../../includes/footer.php");
?>

<!-- (YOUR UI CODE STAYS SAME — ONLY DATA IS NOW SECURED) -->