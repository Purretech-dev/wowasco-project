<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

include(__DIR__ . '/../../api/db.php');

/* ================= KPI DATA ================= */

$total = $conn->query("
SELECT COUNT(*) t
FROM meters
")->fetch_assoc()['t'];

$active = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Active'
")->fetch_assoc()['t'];

$inactive = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Inactive'
")->fetch_assoc()['t'];

$efficiency = ($total > 0)
? round(($active / $total) * 100,1)
: 0;

/* ================= ZONE ANALYTICS ================= */

$zones = [];
$zoneTotals = [];

$zoneQuery = $conn->query("
SELECT zone, COUNT(*) total
FROM meters
GROUP BY zone
ORDER BY total DESC
");

while($z = $zoneQuery->fetch_assoc()){

    $zones[] = $z['zone'] ?: 'Unassigned';
    $zoneTotals[] = $z['total'];
}

/* ================= MONTHLY INSTALLATIONS ================= */

$months = [];
$monthlyInstallations = [];

$monthly = $conn->query("
SELECT
MONTHNAME(installation_date) month,
COUNT(*) total
FROM meters
GROUP BY MONTH(installation_date)
ORDER BY MONTH(installation_date)
");

while($m = $monthly->fetch_assoc()){

    $months[] = $m['month'];
    $monthlyInstallations[] = $m['total'];
}

/* ================= RECENT METERS ================= */

$recentMeters = $conn->query("
SELECT
serial_number,
customer_name,
zone,
status,
installation_date
FROM meters
ORDER BY id DESC
LIMIT 5
");

/* ================= ALERTS ================= */

$alerts = $conn->query("
SELECT
serial_number,
status,
zone
FROM meters
WHERE status='Inactive'
LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>

<title>Smart Meter Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
    margin:0;
    background:#f8fafc;
    font-family:'Segoe UI',sans-serif;
    color:#1e293b;
}

/* ================= LAYOUT ================= */

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
}

/* ================= TYPOGRAPHY ================= */

h2{
    margin:0 0 24px;
    font-size:24px;
    font-weight:700;
    color:#0f172a;
    letter-spacing:0.3px;
}

.section-title{
    margin:0 0 16px;
    font-size:16px;
    font-weight:700;
    color:#0f172a;
}

/* ================= KPI CARDS ================= */

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
}

.card{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e2e8f0;
    border-top:3px solid #1e7d4f;
    box-shadow:0 2px 8px rgba(15,23,42,0.04);
}

.card h3{
    margin:0;
    font-size:30px;
    font-weight:700;
    color:#0f172a;
}

.card p{
    margin-top:8px;
    font-size:13px;
    font-weight:600;
    color:#64748b;
}

/* ================= DASHBOARD GRID ================= */

.dashboard-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
    margin-top:24px;
}

/* ================= PANELS ================= */

.panel{
    background:white;
    border-radius:16px;
    padding:20px;
    border:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(15,23,42,0.04);
}

/* ================= CHARTS ================= */

.chart-container{
    position:relative;
    height:300px;
}

/* ================= ALERTS ================= */

.alert{
    padding:14px;
    margin-bottom:12px;
    border-radius:12px;
    background:#fff7ed;
    border-left:3px solid #f1c40f;
}

.alert strong{
    display:block;
    color:#92400e;
    margin-bottom:4px;
    font-size:13px;
}

.alert span{
    font-size:12px;
    color:#78350f;
}

/* ================= TABLE ================= */

.table-wrapper{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#f8fafc;
    color:#334155;
    font-size:13px;
    font-weight:700;
    text-align:left;
    padding:14px;
    border-bottom:2px solid #e2e8f0;
}

td{
    padding:14px;
    font-size:13px;
    color:#1e293b;
    border-bottom:1px solid #f1f5f9;
}

tr:hover td{
    background:#fcfcfc;
}

/* ================= STATUS ================= */

.status{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.active{
    background:#ecfdf3;
    color:#15803d;
}

.inactive{
    background:#fef2f2;
    color:#dc2626;
}

/* ================= RESPONSIVE ================= */

@media(max-width:1000px){

    .dashboard-grid{
        grid-template-columns:1fr;
    }

    .container{
        margin-left:0;
    }
}

</style>
</head>

<body>

<div class="container">

<h2>📊 Smart Meter Dashboard</h2>

<!-- KPI CARDS -->

<div class="cards">

<div class="card">
<h3><?= $total ?></h3>
<p>Total Meters</p>
</div>

<div class="card">
<h3><?= $active ?></h3>
<p>Active Meters</p>
</div>

<div class="card">
<h3><?= $inactive ?></h3>
<p>Inactive Meters</p>
</div>

<div class="card">
<h3><?= $efficiency ?>%</h3>
<p>Efficiency Rate</p>
</div>

</div>

<!-- GRID -->

<div class="dashboard-grid">

<!-- LEFT SIDE -->

<div>

<!-- INSTALLATION TREND -->

<div class="panel">

<h3 class="section-title">
Monthly Installations
</h3>

<div class="chart-container">
<canvas id="installChart"></canvas>
</div>

</div>

<!-- RECENT METERS -->

<div class="panel" style="margin-top:20px;">

<h3 class="section-title">
Recent Meter Registrations
</h3>

<div class="table-wrapper">

<table>

<tr>
<th>Serial</th>
<th>Customer</th>
<th>Zone</th>
<th>Status</th>
<th>Purchase Date</th>
</tr>

<?php while($r = $recentMeters->fetch_assoc()): ?>

<tr>

<td><?= $r['serial_number'] ?></td>

<td><?= $r['customer_name'] ?></td>

<td><?= $r['zone'] ?></td>

<td>
<span class="status <?= strtolower($r['status']) ?>">
<?= $r['status'] ?>
</span>
</td>

<td><?= $r['installation_date'] ?></td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

</div>

<!-- RIGHT SIDE -->

<div>

<!-- ZONE DISTRIBUTION -->

<div class="panel">

<h3 class="section-title">
Zone Distribution
</h3>

<div class="chart-container">
<canvas id="zoneChart"></canvas>
</div>

</div>

<!-- ALERTS -->

<div class="panel" style="margin-top:20px;">

<h3 class="section-title">
System Alerts
</h3>

<?php if($alerts->num_rows > 0): ?>

<?php while($a = $alerts->fetch_assoc()): ?>

<div class="alert">

<strong>
<?= $a['serial_number'] ?>
</strong>

<span>
Inactive meter detected in
<?= $a['zone'] ?: 'Unknown Zone' ?>
</span>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert">

<strong>No Critical Alerts</strong>

<span>
All meters operating normally.
</span>

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

<script>

/* ================= INSTALLATION TREND ================= */

new Chart(document.getElementById('installChart'), {

type:'line',

data:{
labels:<?= json_encode($months) ?>,

datasets:[{

label:'Installations',

data:<?= json_encode($monthlyInstallations) ?>,

borderColor:'#1e7d4f',

backgroundColor:'rgba(30,125,79,0.05)',

fill:true,

tension:0.4,

borderWidth:2

}]
},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{
legend:{
display:false
}
},

scales:{

y:{
grid:{
color:'#f1f5f9'
}
},

x:{
grid:{
display:false
}
}

}

}

});

/* ================= ZONE CHART ================= */

new Chart(document.getElementById('zoneChart'), {

type:'doughnut',

data:{

labels:<?= json_encode($zones) ?>,

datasets:[{

data:<?= json_encode($zoneTotals) ?>,

backgroundColor:[
'#567566',
'#55460d',
'#94a3b8',
'#cbd5e1',
'#64748b',
'#193423'
],

borderWidth:1

}]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{
legend:{
position:'bottom'
}
}

}

});

</script>

</body>
</html>