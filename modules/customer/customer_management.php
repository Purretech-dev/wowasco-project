<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= ISSUE UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_issue'])) {

    $stmt = $conn->prepare("
        UPDATE customer_complaints
        SET assigned_staff=?, status=?, escalation_reason=?, pending_reason=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssi",
        $_POST['assigned_staff'],
        $_POST['status'],
        $_POST['escalation_reason'],
        $_POST['pending_reason'],
        $_POST['issue_id']
    );

    $stmt->execute();
}

/* ================= APPLICATION APPROVAL (NEW) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'])) {

    $stmt = $conn->prepare("
        UPDATE smart_meter_applications
        SET status=?, rejection_reason=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssi",
        $_POST['status'],
        $_POST['rejection_reason'],
        $_POST['app_id']
    );

    $stmt->execute();
}

/* ================= DATA ================= */
$issues = $conn->query("
    SELECT cc.*, c.name, c.id_number
    FROM customer_complaints cc
    LEFT JOIN customers c ON c.id = cc.customer_id
    ORDER BY cc.id DESC
");

$applications = $conn->query("
    SELECT sma.*, c.name, c.id_number
    FROM smart_meter_applications sma
    LEFT JOIN customers c ON c.id = sma.customer_id
    ORDER BY sma.id DESC
");

$staff = $conn->query("SELECT id, name FROM users WHERE role='staff'");
?>

<!DOCTYPE html>
<html>
<head>
<title>Customer Management</title>

<style>
body{margin:0;font-family:Segoe UI;background:#f4f7f6;}
.page{margin-left:240px;margin-top:70px;padding:20px;}
.header{background:linear-gradient(90deg,#1e7d4f,#f4c430);color:white;padding:12px;border-radius:8px;margin-bottom:15px;}

.card{background:white;padding:15px;border-radius:10px;margin-bottom:15px;}

table{width:100%;border-collapse:collapse;font-size:13px;}
th{background:#1e7d4f;color:white;padding:8px;}
td{padding:8px;border-bottom:1px solid #eee;}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;}

.badge{padding:4px 8px;border-radius:5px;color:white;}
.pending{background:#f4c430;color:#000;}
.approved{background:#1e7d4f;}
.rejected{background:#c62828;}
</style>
</head>

<body>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<div class="page">

<div class="header">Customer Management (Issues + Applications)</div>

<!-- ================= SMART METER APPLICATIONS ================= -->
<div class="card">
<h3>Smart Meter Applications</h3>

<table>
<tr><th>Customer</th><th>ID</th><th>Status</th><th>Action</th></tr>

<?php while($a = $applications->fetch_assoc()): ?>
<tr>
<td><?= $a['name'] ?></td>
<td><?= $a['id_number'] ?></td>

<td>
<?php if($a['status']=='Pending'): ?>
<span class="badge pending">Pending</span>
<?php elseif($a['status']=='Approved'): ?>
<span class="badge approved">Approved</span>
<?php else: ?>
<span class="badge rejected">Rejected</span>
<?php endif; ?>
</td>

<td>
<form method="POST">
<input type="hidden" name="app_id" value="<?= $a['id'] ?>">

<select name="status">
<option>Pending</option>
<option>Approved</option>
<option>Rejected</option>
</select>

<input name="rejection_reason" placeholder="Rejection reason">

<button name="update_application">Update</button>
</form>
</td>

</tr>
<?php endwhile; ?>

</table>
</div>

<!-- ================= ISSUES ================= -->
<div class="card">
<h3>Customer Issues</h3>

<table>
<tr><th>Customer</th><th>Issue</th><th>Status</th></tr>

<?php while($i = $issues->fetch_assoc()): ?>
<tr>
<td><?= $i['name'] ?></td>
<td><?= $i['complaint'] ?></td>
<td><?= $i['status'] ?></td>
</tr>
<?php endwhile; ?>

</table>
</div>

</div>

</body>
</html>