<?php
$conn = new mysqli("localhost", "root", "", "wowasco");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$customer_id = $_GET['customer_id'] ?? 1;

/* ================= DATA ================= */
$bills = $conn->query("
    SELECT * FROM bills 
    WHERE customer_id = $customer_id
    ORDER BY created_at DESC
");

$payments = $conn->query("
    SELECT * FROM payments 
    WHERE customer_id = $customer_id
    ORDER BY payment_date DESC
");

$consumption = $conn->query("
    SELECT month, units_used 
    FROM consumption 
    WHERE customer_id = $customer_id
    ORDER BY month ASC
");

if(isset($_POST['submit_complaint'])){

    $stmt = $conn->prepare("
        INSERT INTO complaints (customer_id, subject, message, status)
        VALUES (?, ?, ?, 'Pending')
    ");

    $stmt->bind_param(
        "iss",
        $customer_id,
        $_POST['subject'],
        $_POST['message']
    );

    $stmt->execute();

    $msg = "Complaint submitted successfully.";
}

$schedule = $conn->query("
    SELECT * FROM rationing_schedule
    ORDER BY schedule_date DESC
");

include(__DIR__ . '/../../includes/sidebar.php');
include(__DIR__ . '/../../includes/navbar.php');
include(__DIR__ . '/../../includes/footer.php');
?>

<!DOCTYPE html>
<html>
<head>
<title>Customer Self-Service Portal</title>

<style>
body {
    font-family:"Inter","Segoe UI",sans-serif;
    background:#f1f5f9;
    margin:0;
    color:#1e293b;
    line-height:1.5;
    letter-spacing:0.2px;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
}
/* ================= TYPOGRAPHY ENHANCEMENT ================= */

h1,h2,h3,h4,h5,h6{
    margin-top:0;
    color:#0f172a;
    font-weight:800;
    letter-spacing:-0.5px;
}

h3{
    font-size:20px;
    margin-bottom:18px;
}

p{
    font-size:14px;
    color:#475569;
}

.tab-btn{
    font-size:14px;
    font-weight:600;
    letter-spacing:0.2px;
}

table{
    font-size:14px;
}

th{
    font-size:13px;
    font-weight:700;
    letter-spacing:0.5px;
    text-transform:uppercase;
}

td{
    font-size:14px;
    font-weight:500;
    color:#334155;
}

input,
textarea{
    font-size:14px;
    font-weight:500;
    color:#1e293b;
}

input::placeholder,
textarea::placeholder{
    color:#94a3b8;
}

button{
    font-size:14px;
    font-weight:700;
    letter-spacing:0.3px;
}

.success,
.warning{
    font-size:14px;
    font-weight:700;
}

/* ================= LAYOUT ================= */
.page{
    margin-left:240px;
    margin-top:70px;
    margin-bottom:60px;
    padding:20px;
}

@media (max-width:768px){
.page{margin-left:0;}
}

/* ================= HEADER ================= */
.header-box {
    background: #ffffff;
    color: #333;
    padding: 15px;
    font-size: 18px;
    font-weight: bold;
    border-radius: 8px;
    margin-bottom:15px;
    border-left: 4px solid #2e7d32;
}

/* ================= TABS ================= */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap:wrap;
}

.tab-btn {
    padding: 10px 15px;
    background: #ffffff;
    border: 1px solid #ddd;
    cursor: pointer;
    border-radius: 6px;
    transition: 0.2s ease;
}

/* 🔵 BLUE HOVER EFFECT */
.tab-btn:hover {
    border-color: #1e88e5;
    background: #f5faff;
}

.tab-btn.active {
    border-bottom: 3px solid #2e7d32;
    font-weight: bold;
}

/* ================= PANELS ================= */
.panel {
    display: none;
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.panel.active {
    display: block;
}

/* ================= TABLE ================= */
table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f0f0f0;
    color: #333;
    font-weight: bold;
    border-bottom: 2px solid #e0e0e0;
}

/* 🔵 ROW HOVER EFFECT */
tr:hover td {
    background: #f0f7ff;
    transition: 0.2s;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

/* ================= FORM ================= */
input, textarea {
    width: 100%;
    padding: 10px;
    margin: 6px 0;
    border: 1px solid #ddd;
    border-radius: 6px;
}

/* ================= BUTTON ================= */
button {
    padding: 10px 15px;
    background: #ffffff;
    color: #333;
    border: 1px solid #ccc;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s ease;
}

/* 🔵 BUTTON HOVER */
button:hover {
    border-color: #1e88e5;
    background: #f5faff;
}

/* ================= ACCENTS ================= */
.success {
    color: #2e7d32;
    font-weight: bold;
}

.warning {
    color: #f9a825;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="page">

<div class="header-box">
Customer Self-Service Portal
</div>

<div class="tabs">
<button class="tab-btn active" onclick="openTab('bills')">Bills</button>
<button class="tab-btn" onclick="openTab('payments')">Payments</button>
<button class="tab-btn" onclick="openTab('consumption')">Consumption</button>
<button class="tab-btn" onclick="openTab('complaints')">Complaints</button>
<button class="tab-btn" onclick="openTab('rationing')">Rationing</button>
</div>

<?php if(isset($msg)): ?>
<p class="success"><?= $msg ?></p>
<?php endif; ?>

<!-- ================= BILLS ================= -->
<div id="bills" class="panel active">
<h3>📄 Bills</h3>

<table>
<tr><th>Month</th><th>Amount</th><th>Status</th></tr>

<?php while($b = $bills->fetch_assoc()): ?>
<tr>
<td><?= $b['bill_month'] ?></td>
<td><?= number_format($b['amount'],2) ?></td>
<td><?= $b['status'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ================= PAYMENTS ================= -->
<div id="payments" class="panel">
<h3>💳 Payment History</h3>

<table>
<tr><th>Date</th><th>Amount</th><th>Method</th></tr>

<?php while($p = $payments->fetch_assoc()): ?>
<tr>
<td><?= $p['payment_date'] ?></td>
<td><?= number_format($p['amount'],2) ?></td>
<td><?= $p['method'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ================= CONSUMPTION ================= -->
<div id="consumption" class="panel">
<h3>📊 Consumption Trends</h3>

<table>
<tr><th>Month</th><th>Units Used</th></tr>

<?php while($c = $consumption->fetch_assoc()): ?>
<tr>
<td><?= $c['month'] ?></td>
<td><?= $c['units_used'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ================= COMPLAINTS ================= -->
<div id="complaints" class="panel">
<h3>📢 Report Fault / Complaint</h3>

<form method="POST">
<input type="text" name="subject" placeholder="Subject" required>
<textarea name="message" placeholder="Describe the issue..." required></textarea>

<button type="submit" name="submit_complaint">Submit Complaint</button>
</form>

</div>

<!-- ================= RATIONING ================= -->
<div id="rationing" class="panel">
<h3>🚰 Water Rationing Schedule</h3>

<table>
<tr><th>Date</th><th>Area</th><th>Time</th><th>Status</th></tr>

<?php while($r = $schedule->fetch_assoc()): ?>
<tr>
<td><?= $r['schedule_date'] ?></td>
<td><?= $r['area'] ?></td>
<td><?= $r['time_slot'] ?></td>
<td><?= $r['status'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

</div>

<script>
function openTab(tabId){

    let panels = document.querySelectorAll(".panel");
    panels.forEach(p => p.classList.remove("active"));

    let buttons = document.querySelectorAll(".tab-btn");
    buttons.forEach(b => b.classList.remove("active"));

    document.getElementById(tabId).classList.add("active");
    event.target.classList.add("active");
}
</script>

</body>
</html>