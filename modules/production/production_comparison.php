<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

include(__DIR__ . '/../../api/db.php');

include(__DIR__ . '/../../includes/sidebar.php');
include(__DIR__ . '/../../includes/navbar.php');

/* ================= FILTERS ================= */

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$zone_filter = $_GET['zone'] ?? '';
$type_filter = $_GET['customer_type'] ?? '';

/* ================= SEARCH SQL ================= */

$search_sql = "";

if(!empty($zone_filter)){

    $safeZone =
    $conn->real_escape_string($zone_filter);

    $search_sql .=
    " AND m.zone='$safeZone'";
}

if(!empty($type_filter)){

    $safeType =
    $conn->real_escape_string($type_filter);

    $search_sql .=
    " AND m.customer_type='$safeType'";
}

/* ================= PRODUCTION ================= */

$production = [];

$resultProduction = $conn->query("
SELECT *
FROM production_records
WHERE MONTH(production_date)='$month'
AND YEAR(production_date)='$year'
");

while($p = $resultProduction->fetch_assoc()){
    $production[] = $p;
}

$totalProduction =
array_sum(array_column($production,'pumped_volume'));

/* ================= BILLING ================= */

$result = $conn->query("
SELECT
m.id,
m.serial_number,
m.customer_name,
m.customer_type,
m.zone,
SUM(r.consumption) total_consumption

FROM meters m

LEFT JOIN meter_readings r
ON m.id = r.meter_id

WHERE
MONTH(r.reading_date)='$month'
AND YEAR(r.reading_date)='$year'
$search_sql

GROUP BY m.id
");

$totalBilled = 0;

while($row = $result->fetch_assoc()){

    $totalBilled += $row['total_consumption'];
}

/* ================= LOSS ================= */

$loss =
max(0, $totalProduction - $totalBilled);

$lossPercent =
($totalProduction > 0)
? round(($loss/$totalProduction)*100,2)
: 0;

/* ================= CLASSIFICATION ================= */

if($lossPercent <= 10){

    $lossClass = "Efficient";

}
elseif($lossPercent <= 20){

    $lossClass = "Moderate";

}
elseif($lossPercent <= 30){

    $lossClass = "High";

}
else{

    $lossClass = "Critical";
}

/* ================= KPI ================= */

$activeMeters = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Active'
")->fetch_assoc()['t'];

$inactiveMeters = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Inactive'
")->fetch_assoc()['t'];

/* ================= ZONE ANALYTICS ================= */

$zoneData = [];

$zoneResult = $conn->query("
SELECT
m.zone,
SUM(r.consumption) total

FROM meters m

LEFT JOIN meter_readings r
ON m.id=r.meter_id

WHERE
MONTH(r.reading_date)='$month'
AND YEAR(r.reading_date)='$year'

GROUP BY m.zone
");

while($z = $zoneResult->fetch_assoc()){

    $zoneData[$z['zone'] ?: 'Unassigned']
    = $z['total'];
}

/* ================= TYPE ANALYTICS ================= */

$typeData = [];

$typeResult = $conn->query("
SELECT
m.customer_type,
SUM(r.consumption) total

FROM meters m

LEFT JOIN meter_readings r
ON m.id=r.meter_id

WHERE
MONTH(r.reading_date)='$month'
AND YEAR(r.reading_date)='$year'

GROUP BY m.customer_type
");

while($t = $typeResult->fetch_assoc()){

    $typeData[$t['customer_type'] ?: 'Unknown']
    = $t['total'];
}

/* ================= HIGHEST ================= */

$highestZone =
!empty($zoneData)
? array_keys($zoneData,max($zoneData))[0]
: 'N/A';

?>

<!DOCTYPE html>
<html>
<head>

<title>Production Intelligence Center</title>

<style>

body{
    font-family:'Segoe UI',sans-serif;
    margin:0;
    background:#f8fafc;
    color:#1e293b;
}

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
    padding-bottom:120px;
}

h2{
    margin:0 0 22px;
    font-size:24px;
    font-weight:700;
    color:#0f172a;
}

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
    margin-bottom:24px;
}

/* ================= KPI ================= */

.kpi{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e5e7eb;
    border-left:3px solid #cbd5e1;
    box-shadow:0 1px 4px rgba(15,23,42,0.03);
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
}

/* ================= PANELS ================= */

.panel{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e5e7eb;
    border-left:3px solid #cbd5e1;
    margin-bottom:22px;
    box-shadow:0 1px 4px rgba(15,23,42,0.03);
}

/* ================= FILTERS ================= */

.filters{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}

.filters select,
.filters button{
    padding:10px 12px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
}

.filters button{
    background:#334155;
    color:white;
    border:none;
    font-weight:600;
    cursor:pointer;
}

/* ================= ANALYTICS BUTTONS ================= */

.toggle-btn{
    background:white;
    border:1px solid #dbe2ea;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    color:#475569;
    transition:0.2s ease;
    min-width:160px;
}

.toggle-btn:hover{
    background:#f8fafc;
}

/* ACTIVE BUTTON */

.active-toggle{
    background:#f8fafc;
    border-color:#cbd5e1;
    color:#0f172a;
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
    padding:14px;
    text-align:left;
    font-size:13px;
    border-bottom:1px solid #e5e7eb;
    color:#334155;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
    color:#475569;
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
    background:#f8fafc;
    color:#475569;
    border:1px solid #e2e8f0;
}

/* REMOVE COLORS */

.good,
.warning,
.danger{
    background:#f8fafc;
    color:#475569;
    border:1px solid #e2e8f0;
}

/* ================= ALERTS ================= */

.alert{
    padding:14px;
    border-radius:10px;
    margin-top:14px;
    font-size:13px;
    border-left:3px solid #cbd5e1;
    background:#fafafa;
    color:#475569;
}

.hidden{
    display:none;
}

@media(max-width:1000px){

    .container{
        margin-left:0;
    }

    .filters{
        flex-direction:column;
        align-items:stretch;
    }

    .filters select,
    .filters button{
        width:100%;
    }
}

</style>
</head>

<body>

<div class="container">

<h2>Water Production Intelligence</h2>

<!-- FILTER PANEL -->

<div class="panel">

<form
method="GET"
action="dashboard.php"
class="filters">

<input
type="hidden"
name="page"
value="modules/production/production_comparison.php">

<select name="month">

<?php
for($m=1;$m<=12;$m++):

$val =
str_pad($m,2,"0",STR_PAD_LEFT);
?>

<option
value="<?= $val ?>"
<?= ($month==$val)?'selected':'' ?>>

<?= $val ?>

</option>

<?php endfor; ?>

</select>

<select name="year">

<?php
for($y=2025;$y<=2030;$y++):
?>

<option
value="<?= $y ?>"
<?= ($year==$y)?'selected':'' ?>>

<?= $y ?>

</option>

<?php endfor; ?>

</select>

<select name="zone">

<option value="">
All Zones
</option>

<?php

$zones = $conn->query("
SELECT zone_name
FROM zones
ORDER BY zone_name ASC
");

while($zone = $zones->fetch_assoc()):

?>

<option
value="<?= $zone['zone_name'] ?>"
<?= ($zone_filter==$zone['zone_name'])?'selected':'' ?>>

<?= $zone['zone_name'] ?>

</option>

<?php endwhile; ?>

</select>

<select name="customer_type">

<option value="">
Customer Type
</option>

<option value="Domestic"
<?= ($type_filter=='Domestic')?'selected':'' ?>>
Domestic
</option>

<option value="Government Entities"
<?= ($type_filter=='Government Entities')?'selected':'' ?>>
Government Entities
</option>

<option value="Commercial"
<?= ($type_filter=='Commercial')?'selected':'' ?>>
Commercial
</option>

<option value="Residential"
<?= ($type_filter=='Residential')?'selected':'' ?>>
Residential
</option>

</select>

<button type="submit">
Apply Filters
</button>

</form>

</div>

<!-- KPI -->

<div class="kpis">

<div class="kpi">
<h3><?= number_format($totalProduction) ?></h3>
<p>Total Production (m³)</p>
</div>

<div class="kpi">
<h3><?= number_format($totalBilled) ?></h3>
<p>Total Billed (m³)</p>
</div>

<div class="kpi">
<h3><?= number_format($loss) ?></h3>
<p>Water Loss (m³)</p>
</div>

<div class="kpi">
<h3><?= $lossPercent ?>%</h3>
<p>Loss Percentage</p>
</div>

<div class="kpi">
<h3><?= $highestZone ?></h3>
<p>Highest Consumption Zone</p>
</div>

<div class="kpi">
<h3><?= $activeMeters ?></h3>
<p>Active Meters</p>
</div>

<div class="kpi">
<h3><?= $inactiveMeters ?></h3>
<p>Inactive Meters</p>
</div>

<div class="kpi">
<h3>
<span class="badge">
<?= $lossClass ?>
</span>
</h3>

<p>System Efficiency</p>
</div>

</div>

<!-- ANALYTICS -->

<div class="panel">

<div style="
display:flex;
flex-wrap:wrap;
gap:12px;
margin-bottom:22px;
">

<button
class="toggle-btn active-toggle"
onclick="toggleTable('zoneTable',this)">

Zone Analytics

</button>

<button
class="toggle-btn"
onclick="toggleTable('typeTable',this)">

Customer Types

</button>

<button
class="toggle-btn"
onclick="toggleTable('lossTable',this)">

Loss Analytics

</button>

</div>

<!-- ZONE ANALYTICS -->

<div id="zoneTable">

<div class="table-wrapper">

<table>

<tr>
<th>Zone</th>
<th>Total Consumption</th>
<th>Performance</th>
</tr>

<?php foreach($zoneData as $zone=>$total): ?>

<tr>

<td>
<?= htmlspecialchars($zone) ?>
</td>

<td>
<?= number_format($total) ?> m³
</td>

<td>

<?php

if($total > 40000){

    echo '<span class="badge">
    High Demand
    </span>';

}
elseif($total > 20000){

    echo '<span class="badge">
    Stable
    </span>';

}
else{

    echo '<span class="badge">
    Low Activity
    </span>';
}

?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<!-- CUSTOMER TYPES -->

<div id="typeTable" class="hidden">

<div class="table-wrapper">

<table>

<tr>
<th>Customer Type</th>
<th>Total Consumption</th>
<th>Category Insight</th>
</tr>

<?php foreach($typeData as $type=>$total): ?>

<tr>

<td>
<?= htmlspecialchars($type) ?>
</td>

<td>
<?= number_format($total) ?> m³
</td>

<td>

<?php

if($type == 'Commercial'){

    echo 'Business Consumption';

}
elseif($type == 'Government Entities'){

    echo 'Public Sector Usage';

}
elseif($type == 'Domestic'){

    echo 'Residential Household Usage';

}
else{

    echo 'General Consumption';
}

?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<!-- LOSS ANALYTICS -->

<div id="lossTable" class="hidden">

<div class="alert">

<strong>System Efficiency:</strong>
<?= $lossClass ?>

<br><br>

<strong>Total Production:</strong>
<?= number_format($totalProduction) ?> m³

<br><br>

<strong>Total Billed Consumption:</strong>
<?= number_format($totalBilled) ?> m³

<br><br>

<strong>Water Loss:</strong>
<?= number_format($loss) ?> m³

<br><br>

<strong>Loss Percentage:</strong>
<?= $lossPercent ?>%

</div>

</div>

</div>

</div>

<script>

function toggleTable(id, btn){

    let sections = [
        'zoneTable',
        'typeTable',
        'lossTable'
    ];

    sections.forEach(section => {

        document
        .getElementById(section)
        .style.display = 'none';
    });

    document
    .getElementById(id)
    .style.display = 'block';

    let buttons =
    document.querySelectorAll('.toggle-btn');

    buttons.forEach(button => {
        button.classList.remove('active-toggle');
    });

    btn.classList.add('active-toggle');
}

</script>

</body>
</html>