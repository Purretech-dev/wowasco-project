<?php
include(__DIR__ . '/../../api/db.php');

/* ================= FILTERS ================= */

$search = $_GET['search'] ?? '';
$zone_filter = $_GET['zone'] ?? '';

/* ================= QUERY ================= */

$sql = "SELECT * FROM meters WHERE 1=1";

if(!empty($search)){
    $sql .= " AND serial_number LIKE '%$search%'";
}

if(!empty($zone_filter)){
    $sql .= " AND zone='$zone_filter'";
}

/* ================= PAGINATION ================= */

$limit = 5;

$page =
isset($_GET['page_num'])
? (int)$_GET['page_num']
: 1;

if($page < 1){
    $page = 1;
}

$offset = ($page - 1) * $limit;

/* ================= COUNT TOTAL ================= */

$countQuery = $conn->query($sql);

$total_records = $countQuery->num_rows;

$total_pages = ceil($total_records / $limit);

/* ================= FINAL QUERY ================= */

$sql .= " LIMIT $limit OFFSET $offset";

$meters = $conn->query($sql);

/* ================= COUNTS ================= */

$total = $conn->query("
SELECT COUNT(*) t FROM meters
")->fetch_assoc()['t'];

$inactive = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Inactive'
")->fetch_assoc()['t'];

$critical = 0;
$warning = 0;

/* ================= RISK ENGINE ================= */

function riskScore($m){

    $score = 0;

    if(($m['status'] ?? '') == 'Inactive'){
        $score += 50;
    }

    if(empty($m['zone'])){
        $score += 20;
    }

    if(($m['battery_level'] ?? 100) < 30){
        $score += 20;
    }

    if(($m['signal_strength'] ?? 100) < 40){
        $score += 20;
    }

    if(!empty($m['installation_date'])){

        $years =
        (time() - strtotime($m['installation_date']))
        / (365*24*60*60);

        if($years > 10){
            $score += 30;
        }
        elseif($years > 5){
            $score += 15;
        }
    }

    return min($score,100);
}

/* ================= ZONES ================= */

$zones = [];

$zoneResult = $conn->query("
SELECT DISTINCT zone
FROM meters
ORDER BY zone ASC
");

while($z = $zoneResult->fetch_assoc()){

    if(!empty($z['zone'])){
        $zones[] = $z['zone'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Meter Intelligence Center</title>

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

/* ================= TITLES ================= */

h2{
    margin:0 0 22px;
    font-size:24px;
    font-weight:700;
    color:#0f172a;
}

.section-title{
    margin:0 0 14px;
    font-size:16px;
    font-weight:700;
    color:#0f172a;
}

/* ================= KPI ================= */

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
}

.kpi{
    background:white;
    border-radius:16px;
    padding:20px;
    border:1px solid #e2e8f0;
    border-top:3px solid #1e7d4f;
}

.kpi h3{
    margin:0;
    font-size:30px;
    color:#0f172a;
}

.kpi p{
    margin-top:8px;
    font-size:13px;
    color:#64748b;
    font-weight:600;
}

/* ================= FILTERS ================= */

.filters{
    margin-top:24px;
    background:white;
    padding:18px;
    border-radius:16px;
    border:1px solid #e2e8f0;

    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

input,select{
    padding:10px 12px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
}

button{
    padding:10px 16px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    cursor:pointer;
    font-weight:600;
}

/* ================= GRID ================= */

.grid{
    display:grid;
    grid-template-columns:1fr 360px;
    gap:20px;
    margin-top:24px;
}

/* ================= PANELS ================= */

.panel{
    background:white;
    border-radius:16px;
    border:1px solid #e2e8f0;
    padding:20px;
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
    padding:14px;
    text-align:left;
    font-size:13px;
    border-bottom:2px solid #e2e8f0;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
}

tr:hover td{
    background:#fcfcfc;
}

/* ================= BADGES ================= */

.badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.critical{
    background:#fef2f2;
    color:#dc2626;
}

.warning{
    background:#fffbeb;
    color:#ca8a04;
}

.good{
    background:#ecfdf3;
    color:#15803d;
}

/* ================= DRILL DOWN ================= */

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:6px 10px;
    border-radius:8px;
    cursor:pointer;
}

.drilldown{
    display:none;
    background:#f8fafc;
}

.drill-card{
    padding:14px;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:10px;
}

.drill-item{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:10px;
}

.drill-item strong{
    display:block;
    font-size:12px;
    color:#64748b;
    margin-bottom:4px;
}

/* ================= ALERTS ================= */

.alert{
    background:#fff7ed;
    border-left:3px solid #f1c40f;
    padding:14px;
    border-radius:10px;
    margin-bottom:12px;
    font-size:13px;
}

/* ================= PAGINATION ================= */

.pagination{
    margin-top:20px;
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
}

.pagination a{
    padding:8px 12px;
    border-radius:8px;
    border:1px solid #dbe2ea;
    background:white;
    color:#334155;
    text-decoration:none;
    font-size:13px;
    font-weight:600;
    transition:0.2s ease;
}

.pagination a:hover{
    background:#f8fafc;
}

.pagination a.active{
    background:#ecfdf3;
    border-color:#22c55e;
    color:#15803d;
}

/* ================= RESPONSIVE ================= */

@media(max-width:1000px){

    .grid{
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

<h2>Smart Meter Intelligence Center</h2>

<!-- KPI -->

<div class="kpis">

<div class="kpi">
<h3><?= $total ?></h3>
<p>Total Meters</p>
</div>

<div class="kpi">
<h3><?= $inactive ?></h3>
<p>Inactive Meters</p>
</div>

<div class="kpi">
<h3><?= $critical ?></h3>
<p>Critical Risk</p>
</div>

<div class="kpi">
<h3><?= $warning ?></h3>
<p>Warning Risk</p>
</div>

</div>

<!-- FILTERS -->

<form
class="filters"
method="GET"
action="dashboard.php">
<input
type="hidden"
name="page"
value="modules/metering/meter_alerts.php">

<input
type="text"
name="search"
placeholder="Search Serial"
value="<?= htmlspecialchars($search) ?>">

<select name="zone">

<option value="">All Zones</option>

<?php foreach($zones as $zone): ?>

<option
value="<?= $zone ?>"
<?= ($zone_filter == $zone) ? 'selected' : '' ?>>

<?= $zone ?>

</option>

<?php endforeach; ?>

</select>

<button type="submit">
Filter
</button>

</form>

<!-- GRID -->

<div class="grid">

<!-- LEFT -->

<div class="panel">

<h3 class="section-title">
Meter Risk Intelligence
</h3>

<div class="table-wrapper">

<table>

<tr>
<th>Serial</th>
<th>Status</th>
<th>Risk</th>
<th>Level</th>
<th>Action</th>
</tr>

<?php while($m = $meters->fetch_assoc()):

$risk = riskScore($m);

if($risk > 70){

    $level = 'Critical';
    $class = 'critical';
    $critical++;

}
elseif($risk > 40){

    $level = 'Warning';
    $class = 'warning';
    $warning++;

}
else{

    $level = 'Good';
    $class = 'good';
}

?>

<tr>

<td><?= $m['serial_number'] ?></td>

<td><?= $m['status'] ?></td>

<td><?= $risk ?>/100</td>

<td>
<span class="badge <?= $class ?>">
<?= $level ?>
</span>
</td>

<td>

<button
class="expand-btn"
onclick="toggleDrill(<?= $m['id'] ?>)">

Details

</button>

</td>

</tr>

<tr
id="drill-<?= $m['id'] ?>"
class="drilldown">

<td colspan="5">

<div class="drill-card">

<div class="drill-item">
<strong>Zone</strong>
<?= $m['zone'] ?: 'Unassigned' ?>
</div>

<div class="drill-item">
<strong>Battery Level</strong>
<?= $m['battery_level'] ?>%
</div>

<div class="drill-item">
<strong>Signal Strength</strong>
<?= $m['signal_strength'] ?>%
</div>

<div class="drill-item">
<strong>Installation Date</strong>
<?= $m['installation_date'] ?>
</div>

<div class="drill-item">
<strong>Last Reading</strong>
<?= $m['last_reading_date'] ?: 'N/A' ?>
</div>

<div class="drill-item">
<strong>Technician</strong>
<?= $m['technician_assigned'] ?: 'Not Assigned' ?>
</div>

<div class="drill-item">
<strong>Maintenance</strong>
<?= $m['maintenance_status'] ?>
</div>

<div class="drill-item">
<strong>Recommended Action</strong>

<?php
if($risk > 70){
    echo "Immediate inspection";
}
elseif($risk > 40){
    echo "Monitor closely";
}
else{
    echo "No action required";
}
?>

</div>

</div>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>

<!-- PAGINATION -->

<div class="pagination">

<?php if($page > 1): ?>

<a href="dashboard.php?page=modules/metering/meter_alerts.php
&page_num=<?= $page-1 ?>
&search=<?= urlencode($search) ?>
&zone=<?= urlencode($zone_filter) ?>">

← Prev

</a>

<?php endif; ?>

<?php for($i=1; $i<=$total_pages; $i++): ?>

<a
href="dashboard.php?page=modules/metering/meter_alerts.php
&page_num=<?= $i ?>
&search=<?= urlencode($search) ?>
&zone=<?= urlencode($zone_filter) ?>"

class="<?= ($i == $page) ? 'active' : '' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

<?php if($page < $total_pages): ?>

<a href="dashboard.php?page=modules/metering/meter_alerts.php
&page_num=<?= $page+1 ?>
&search=<?= urlencode($search) ?>
&zone=<?= urlencode($zone_filter) ?>">

Next →

</a>

<?php endif; ?>

</div>

</div>

<!-- RIGHT -->

<div class="panel">

<h3 class="section-title">
🚨 Active Alerts
</h3>

<div class="alert">
High-risk inactive meters detected.
</div>

<div class="alert">
Several meters require maintenance review.
</div>

<div class="alert">
Unassigned zones affecting analytics quality.
</div>

</div>

</div>

</div>

<script>

function toggleDrill(id){

    let row =
    document.getElementById('drill-' + id);

    row.style.display =
    row.style.display === 'table-row'
    ? 'none'
    : 'table-row';
}

</script>

</body>
</html>