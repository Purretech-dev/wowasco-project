<?php
include(__DIR__ . '/../../api/db.php');

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ===================== DATA ===================== */
$meters = $conn->query("SELECT * FROM meters");

/* ===================== COUNTS ===================== */
$inactive = 0;
$old = 0;
$missing = 0;

$result = $conn->query("SELECT COUNT(*) as c FROM meters WHERE status='Inactive'");
if ($result) $inactive = $result->fetch_assoc()['c'];

$result = $conn->query("
SELECT COUNT(*) as c FROM meters 
WHERE installation_date <= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
");
if ($result) $old = $result->fetch_assoc()['c'];

$result = $conn->query("
SELECT COUNT(*) as c FROM meters 
WHERE zone='' OR zone IS NULL
");
if ($result) $missing = $result->fetch_assoc()['c'];

$total_alerts = $inactive + $old + $missing;

/* ===================== RISK ENGINE ===================== */
function riskScore($status, $install_date, $zone){
    $score = 0;

    if($status == "Inactive") $score += 50;
    if(empty($zone)) $score += 20;

    if(!empty($install_date)){
        $years = (time() - strtotime($install_date)) / (365*24*60*60);
        if($years > 10) $score += 30;
        elseif($years > 5) $score += 15;
    }

    return min($score,100);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>WOWASCO Meter Intelligence Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
body{
    font-family: Arial;
    margin:0;
    background:#f6f7f9;
    color:#2c3e50;
}

.container{
    margin-left:220px;
    margin-top:70px;
    padding:20px;
}

@media (max-width:768px){
.container{margin-left:0;}
}

h2{color:#2c3e50;}

.kpi{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin-bottom:20px;
}

.kpi div{
    background:#fff;
    padding:14px;
    border-radius:10px;
    text-align:center;
    border:1px solid #eaeaea;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    margin-bottom:20px;
}

.card{
    background:#fff;
    padding:15px;
    border-radius:10px;
    border:1px solid #eee;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th{
    background:#2c3e50;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    border-bottom:1px solid #f0f0f0;
}

/* BADGES */
.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:6px;
    font-size:12px;
    font-weight:600;
}

.red{
    background:#fdecea;
    color:#c0392b;
}

.yellow{
    background:#fff8e1;
    color:#8a6d1e;
}

.green{
    background:#e9f7ef;
    color:#1e7d4f;
}

#map{
    height:400px;
    border-radius:10px;
}
</style>
</head>

<body>

<div class="container">

<h2>WOWASCO Smart Meter Intelligence Dashboard</h2>

<div class="kpi">
    <div><h3>Total Alerts</h3><p><?= $total_alerts ?></p></div>
    <div><h3>Inactive</h3><p><?= $inactive ?></p></div>
    <div><h3>Old</h3><p><?= $old ?></p></div>
    <div><h3>Missing</h3><p><?= $missing ?></p></div>
</div>

<div class="grid">

<div class="card">
<h3>📍 Meter Network Map</h3>
<div id="map"></div>
</div>

<div class="card">
<h3>📊 System Risk Overview</h3>
<canvas id="chart"></canvas>
</div>

</div>

<div class="card">
<h3>🧠 Smart Meter Risk Analysis</h3>

<table>
<thead>
<tr>
<th>Meter Serial</th>
<th>Status</th>
<th>Installation Date</th>
<th>Risk Score</th>
<th>Risk Level</th>
</tr>
</thead>

<tbody>

<?php if($meters && $meters->num_rows > 0): ?>
<?php while($m = $meters->fetch_assoc()): ?>

<?php
$risk = riskScore(
    $m['status'] ?? '',
    $m['installation_date'] ?? '',
    $m['zone'] ?? ''
);

if($risk > 70){
    $level = "Critical"; $class = "red";
}elseif($risk > 40){
    $level = "Warning"; $class = "yellow";
}else{
    $level = "Good"; $class = "green";
}
?>

<tr>
<td><?= $m['serial_number'] ?? '' ?></td>
<td><?= $m['status'] ?? '' ?></td>
<td><?= $m['installation_date'] ?? '' ?></td>
<td><?= $risk ?> / 100</td>
<td><span class="badge <?= $class ?>"><?= $level ?></span></td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5" style="text-align:center;">No meter data found</td></tr>
<?php endif; ?>

</tbody>
</table>

</div>

</div>

<script>
/* ================= UPDATED DOUGHNUT CHART ================= */
new Chart(document.getElementById('chart'), {
type:'doughnut',
data:{
labels:['Inactive','Old','Missing'],
datasets:[{
data:[<?= $inactive ?>,<?= $old ?>,<?= $missing ?>],

/* SOFT CORE COLORS */
backgroundColor:[
    '#2c3e50',   // dark blue (inactive)
    '#f1c40f',   // yellow (old)
    '#27ae60'    // green (missing)
],

/* BORDER HIGHLIGHT FOR CLARITY */
borderColor:[
    '#ffffff',   // clean separation
    '#ffffff',
    '#ffffff'
],
borderWidth:2
}]
},
options:{
    plugins:{
        legend:{
            labels:{
                color:'#2c3e50'
            }
        }
    }
}
});
</script>

<script>
var map = L.map('map').setView([-1.5, 37.6], 7);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
maxZoom:19
}).addTo(map);

L.marker([-1.5, 37.6]).addTo(map).bindPopup("Central Zone");
L.marker([-1.3, 37.2]).addTo(map).bindPopup("North Zone");
L.marker([-1.7, 37.9]).addTo(map).bindPopup("South Zone");
</script>

</body>
</html>