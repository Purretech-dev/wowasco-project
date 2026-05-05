<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

include(__DIR__ . '/../../api/db.php');

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed.");
}

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

/* ================= FILTER STATE ================= */
$isFiltered = isset($_GET['month']) || isset($_GET['year']);

$allowedYear = "2026";
$allowedMonth = "04";

$hasData = ($year == $allowedYear && $month == $allowedMonth);

$totalProduction = 0;
$totalBilled = 0;
$data = [];

if($hasData){

    $production = [
        ["date"=>"2026-04-01","value"=>12500],
        ["date"=>"2026-04-02","value"=>9800],
        ["date"=>"2026-04-03","value"=>14000],
        ["date"=>"2026-04-10","value"=>11000],
        ["date"=>"2026-04-20","value"=>13500],
    ];

    $totalProduction = array_sum(array_column($production,'value'));

    $result = $conn->query("
        SELECT serial_number, customer_type, zone
        FROM meters
        LIMIT 50
    ");

    if($result){
        while($row = $result->fetch_assoc()){
            $consumption = rand(6000, 12000);
            $row['consumption'] = $consumption;
            $totalBilled += $consumption;
            $data[] = $row;
        }
    }

    $loss = max(0, $totalProduction - $totalBilled);
    $lossPercent = ($totalProduction > 0)
        ? round(($loss / $totalProduction) * 100, 2)
        : 0;

} else {
    $loss = 0;
    $lossPercent = 0;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Production Comparison</title>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}

.container{
    margin-left:240px;
    margin-top:70px;
    padding:20px;
    padding-bottom:120px;
}

/* FILTER */
.filter-box{
    background:white;
    padding:15px;
    border-radius:8px;
    margin-bottom:15px;
}

.filter-form{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.filter-form select,
.filter-form button{
    padding:10px;
    border-radius:6px;
    border:1px solid #ccc;
}

.filter-form button{
    background:#003366;
    color:white;
    border:none;
    cursor:pointer;
}

/* CARDS */
.cards{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:15px;
}

.card{
    background:white;
    padding:12px;
    border-radius:8px;
    min-width:180px;
    box-shadow:0 1px 5px rgba(0,0,0,0.06);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th,td{
    padding:10px;
    border:1px solid #eee;
}

th{
    background:#0a2a43;
    color:white;
}

/* ALERT */
.alert{
    margin-top:15px;
    padding:12px;
    background:#fdecea;
    color:#c0392b;
    border-radius:6px;
    font-weight:bold;
}

/* INFO */
.info{
    margin-top:15px;
    padding:12px;
    background:#fff3cd;
    color:#856404;
    border-radius:6px;
    font-weight:bold;
}

/* BACK BUTTON */
.back-btn{
    display:inline-block;
    margin-top:12px;
    padding:10px 15px;
    background:#6c757d;
    color:white;
    text-decoration:none;
    border-radius:6px;
}
</style>
</head>

<body>

<div class="container">

<h2>📊 Monthly Production Comparison</h2>

<!-- FILTER -->
<div class="filter-box">

<form method="GET" action="/wowasco-system/modules/production/production_comparison.php">

<label>Month:</label>
<select name="month">
<?php
for($m=1;$m<=12;$m++){
    $val = str_pad($m,2,"0",STR_PAD_LEFT);
    $selected = ($val == $month) ? "selected" : "";
    echo "<option value='$val' $selected>$val</option>";
}
?>
</select>

<label>Year:</label>
<select name="year">
<?php
for($y=2025;$y<=2026;$y++){
    $selected = ($y == $year) ? "selected" : "";
    echo "<option value='$y' $selected>$y</option>";
}
?>
</select>

<button type="submit">Apply</button>

</form>

</div>

<!-- MESSAGE -->
<?php if(!$hasData): ?>

<div class="info">
ℹ No production data available for <?= $month ?>/<?= $year ?>.  
Only April 2026 currently contains pumping records.
</div>

<a href="/wowasco-system/dashboard.php" class="back-btn">
← Back to Dashboard
</a>

<?php else: ?>

<!-- KPI -->
<div class="cards">

<div class="card">
<strong>Total Production</strong><br>
<?= number_format($totalProduction) ?> m³
</div>

<div class="card">
<strong>Total Billed</strong><br>
<?= number_format($totalBilled) ?> m³
</div>

<div class="card">
<strong>Water Loss</strong><br>
<?= number_format($loss) ?> m³
</div>

<div class="card">
<strong>Loss %</strong><br>
<?= $lossPercent ?>%
</div>

</div>

<!-- TABLE -->
<table>
<tr>
<th>Meter Serial</th>
<th>Customer Type</th>
<th>Zone</th>
<th>Consumption</th>
</tr>

<?php foreach($data as $d): ?>
<tr>
<td><?= $d['serial_number'] ?></td>
<td><?= $d['customer_type'] ?></td>
<td><?= $d['zone'] ?></td>
<td><?= number_format($d['consumption']) ?></td>
</tr>
<?php endforeach; ?>

</table>

<!-- ALERT (FIXED LOGIC) -->
<?php if($isFiltered && $lossPercent > 25): ?>
<div class="alert">
⚠ High Water Loss Detected for <?= $month ?>/<?= $year ?> – Investigate System Efficiency
</div>
<?php endif; ?>

<a href="/wowasco-system/dashboard.php" class="back-btn">
← Back to Dashboard
</a>

<?php endif; ?>

</div>

</body>
</html>