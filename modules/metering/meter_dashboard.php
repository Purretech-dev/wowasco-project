<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

include(__DIR__ . '/../../api/db.php');

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed.");
}

/* ================= KPIs ================= */
$total = 0;
$active = 0;
$inactive = 0;

$res = $conn->query("SELECT COUNT(*) as t FROM meters");
if($res) $total = $res->fetch_assoc()['t'];

$res = $conn->query("SELECT COUNT(*) as t FROM meters WHERE status='Active'");
if($res) $active = $res->fetch_assoc()['t'];

$res = $conn->query("SELECT COUNT(*) as t FROM meters WHERE status='Inactive'");
if($res) $inactive = $res->fetch_assoc()['t'];

/* ================= ALERTS (FROM METERS) ================= */
$alerts = $conn->query("
SELECT serial_number, status, installation_date, zone
FROM meters
WHERE status='Inactive'
   OR installation_date <= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
   OR zone IS NULL OR zone=''
");

$alert_count = ($alerts) ? $alerts->num_rows : 0;

/* ================= CUSTOMER TYPES ================= */
$types = ["Government entities","Residential","Businesses","Personal"];
$typeCounts = [];

foreach($types as $type){
    $count = 0;
    $r = $conn->query("SELECT COUNT(*) as t FROM meters WHERE customer_type='$type'");
    if($r) $count = $r->fetch_assoc()['t'];
    $typeCounts[] = $count;
}

/* ================= 🔥 DUMMY CONSUMPTION DATA ================= */
$dates = [];
$values = [];

for($i = 6; $i >= 0; $i--){
    $dates[] = date("Y-m-d", strtotime("-$i days"));
    $values[] = rand(200, 800);
}

/* ================= METRICS ================= */
$efficiency = ($total > 0) ? round(($active / $total) * 100, 1) : 0;

$healthScore = 100;
if($inactive > ($total * 0.3)) $healthScore -= 30;
if($alert_count > 10) $healthScore -= 20;
if($efficiency < 70) $healthScore -= 20;

$healthStatus = "Stable";
if($healthScore < 70) $healthStatus = "Warning ⚠️";
if($healthScore < 50) $healthStatus = "Critical 🔴";
?>

<!DOCTYPE html>
<html>
<head>
<title>Smart Meter Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}

/* ✅ NAV + SIDEBAR FIX */
.container{
    margin-left:220px;
    margin-top:70px;
    padding:20px;
}

/* KPI CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
}

.card{
    background:white;
    padding:15px;
    border-radius:8px;
    text-align:center;
}

/* ALERT PANEL */
.alert-btn{
    background:red;
    color:white;
    padding:10px;
    border:none;
    width:100%;
    cursor:pointer;
}

.alert-panel{
    display:none;
    background:white;
    padding:10px;
    margin-top:10px;
}

/* ================= 🔥 CHART FIX ================= */
.chart-box{
    background:white;
    padding:15px;
    margin-top:20px;
    border-radius:8px;
}

/* 👇 THIS CONTROLS SIZE */
.chart-wrapper{
    width: 55%;          /* 🔥 reduce width here (try 40–60%) */
    max-width: 500px;
    margin: 0 auto;      /* center it */
}

.chart-wrapper canvas{
    width: 100% !important;
    height: 250px !important;
}
</style>
</head>

<body>

<div class="container">

<h2>📊 Smart Meter Dashboard</h2>

<!-- KPI -->
<div class="cards">

<div class="card"><h3><?= $total ?></h3><p>Total</p></div>
<div class="card"><h3><?= $active ?></h3><p>Active</p></div>
<div class="card"><h3><?= $inactive ?></h3><p>Inactive</p></div>
<div class="card"><h3><?= $efficiency ?>%</h3><p>Efficiency</p></div>

<div class="card">
<button class="alert-btn" onclick="toggleAlerts()">🚨 Alerts (<?= $alert_count ?>)</button>

<div id="alertPanel" class="alert-panel">
<?php if($alerts && $alerts->num_rows > 0): ?>
<?php while($a = $alerts->fetch_assoc()): ?>
<div>
<b><?= $a['serial_number'] ?></b><br>
<?= ucfirst($a['status']) ?> meter
</div>
<hr>
<?php endwhile; ?>
<?php else: ?>
No alerts
<?php endif; ?>
</div>

</div>

</div>

<!-- CHART -->
<div class="chart-box">
<h3>📈 Consumption Trend (Dummy Data)</h3>

<div class="chart-wrapper">
    <canvas id="line"></canvas>
</div>

</div>

</div>

<script>
function toggleAlerts(){
    let p=document.getElementById("alertPanel");
    p.style.display = (p.style.display==="block")?"none":"block";
}

new Chart(document.getElementById('line'), {
type:'line',
data:{
labels:<?= json_encode($dates) ?>,
datasets:[{
label:'Water Consumption',
data:<?= json_encode($values) ?>,
borderColor:'#007bff',
fill:false,
tension:0.3
}]
},
options:{
responsive:true,
maintainAspectRatio:false
}
});
</script>

</body>
</html>