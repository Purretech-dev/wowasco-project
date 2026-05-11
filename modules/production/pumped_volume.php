<?php
include(__DIR__ . '/../../api/db.php');

include(__DIR__ . '/../../includes/sidebar.php');
include(__DIR__ . '/../../includes/navbar.php');

/* ================= FILTER VARIABLES ================= */

$search = $_GET['search'] ?? '';
$zone_filter = $_GET['zone'] ?? '';
$customer_type_filter = $_GET['customer_type'] ?? '';

/* ================= SEARCH FILTER ================= */

$search_sql = "";

/* SERIAL FILTER */

if(!empty($search)){

    $safe =
    $conn->real_escape_string($search);

    $search_sql .=
    " AND m.serial_number = '$safe'";
}

/* ZONE FILTER */

if(!empty($zone_filter)){

    $zone_safe =
    $conn->real_escape_string($zone_filter);

    $search_sql .=
    " AND m.zone = '$zone_safe'";
}

/* CUSTOMER TYPE FILTER */

if(!empty($customer_type_filter)){

    $type_safe =
    $conn->real_escape_string($customer_type_filter);

    $search_sql .=
    " AND m.customer_type = '$type_safe'";
}

/* ================= DATE FILTER ================= */

$start = $_GET['start_date'] ?? '';
$end = $_GET['end_date'] ?? '';

$filter_sql = "";

if($start && $end){

    $filter_sql =
    " AND r.reading_date
      BETWEEN '$start 00:00:00'
      AND '$end 23:59:59'";
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

/* ================= MAIN QUERY ================= */

$base_sql = "
SELECT
m.id,
m.serial_number,
m.customer_name,
m.zone,
m.customer_type,
SUM(r.consumption) as pumped_volume

FROM meters m

LEFT JOIN meter_readings r
ON m.id = r.meter_id

WHERE 1=1
$search_sql
$filter_sql

GROUP BY m.id
";

/* ================= TOTAL COUNT ================= */

$count_result = $conn->query($base_sql);

$total_records = $count_result->num_rows;

$total_pages = ceil($total_records / $limit);

/* ================= FINAL QUERY ================= */

$sql = $base_sql . "
ORDER BY pumped_volume DESC
LIMIT $limit OFFSET $offset
";

$result = $conn->query($sql);

/* ================= TYPE ANALYSIS ================= */

$type_sql = "
SELECT
m.customer_type,
SUM(r.consumption) as total_volume

FROM meters m

LEFT JOIN meter_readings r
ON m.id = r.meter_id

WHERE 1=1
$search_sql
$filter_sql

GROUP BY m.customer_type
";

$type_result = $conn->query($type_sql);

/* ================= ZONE ANALYSIS ================= */

$zone_sql = "
SELECT
m.zone,
SUM(r.consumption) as total_volume

FROM meters m

LEFT JOIN meter_readings r
ON m.id = r.meter_id

WHERE 1=1
$search_sql
$filter_sql

GROUP BY m.zone
";

$zone_result = $conn->query($zone_sql);

$zone_data = [];

while($z = $zone_result->fetch_assoc()){

    $zone_data[$z['zone'] ?: 'Unassigned']
    = $z['total_volume'];
}

/* ================= TYPE DATA ================= */

$total_volume_all = 0;

$type_data = [];

while($row = $type_result->fetch_assoc()){

    $type_data[$row['customer_type'] ?: 'Unknown']
    = $row['total_volume'];

    $total_volume_all += $row['total_volume'];
}

/* ================= KPI DATA ================= */

$activeMeters = $conn->query("
SELECT COUNT(*) t
FROM meters
WHERE status='Active'
")->fetch_assoc()['t'];

$highestZone =
!empty($zone_data)
? array_keys($zone_data, max($zone_data))[0]
: 'N/A';

$highestType =
!empty($type_data)
? array_keys($type_data, max($type_data))[0]
: 'N/A';
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Pumped Volumes - WOWASCO</title>

<style>

body{
    font-family:'Segoe UI',system-ui,sans-serif;
    background:#f8fafc;
    margin:0;
    color:#1e293b;
    -webkit-font-smoothing:antialiased;
}

/* ================= LAYOUT ================= */

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
    padding-bottom:120px;
}

/* ================= TITLES ================= */

h2{
    font-size:24px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:22px;
    text-align:center;
}

h3{
    font-size:16px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:18px;
}

/* ================= KPI CARDS ================= */

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
    margin-bottom:24px;
}

.kpi-card{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e2e8f0;
    border-left:4px solid #2563eb;
    box-shadow:0 2px 8px rgba(15,23,42,0.04);
}

.kpi-card h3{
    margin:0;
    font-size:28px;
    color:#0f172a;
}

.kpi-card p{
    margin-top:8px;
    font-size:13px;
    color:#64748b;
    font-weight:600;
}

/* ================= CARDS ================= */

.card{
    background:white;
    border-radius:16px;
    border:1px solid #e2e8f0;
    border-left:4px solid #2563eb;
    padding:22px;
    margin-bottom:22px;
    box-shadow:0 2px 8px rgba(15,23,42,0.04);
}

/* ================= FILTERS ================= */

.filter-form{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
}

.filter-form input,
.filter-form select{
    padding:10px 12px;
    border:1px solid #dbe2ea;
    border-radius:10px;
    font-size:13px;
    min-width:180px;
    background:white;
    color:#1e293b;
}

.filter-form button{
    padding:10px 18px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
}

/* ================= BUTTONS ================= */

.toggle-btn{
    background:white;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    margin-right:10px;
    margin-bottom:16px;
    font-weight:600;
    transition:0.2s ease;
}

.toggle-btn:hover{
    background:#f8fafc;
}

/* ACTIVE BUTTON */

.active-toggle{
    background:#eff6ff !important;
    border-color:#2563eb !important;
    color:#2563eb !important;
    box-shadow:0 0 0 2px rgba(37,99,235,0.08);
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
    font-weight:700;
    border-bottom:2px solid #e2e8f0;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
    color:#1e293b;
}

tr:hover td{
    background:#fcfcfc;
}

/* ================= DRILLDOWN ================= */

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:7px 12px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
}

.drilldown{
    display:none;
    background:#f8fafc;
}

.drill-card{
    padding:16px;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
}

.drill-item{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:12px;
}

.drill-item strong{
    display:block;
    font-size:12px;
    color:#64748b;
    margin-bottom:5px;
}

/* ================= PAGINATION ================= */

.pagination{
    margin-top:22px;
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
}

.pagination a.active{
    background:#eff6ff;
    border-color:#2563eb;
    color:#2563eb;
}

/* ================= HIDDEN ================= */

.hidden{
    display:none;
}

/* ================= BACK BUTTON ================= */

.back-btn{
    display:inline-block;
    margin-top:24px;
    padding:12px 18px;
    background:white;
    border:1px solid #dbe2ea;
    color:#334155;
    text-decoration:none;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
}

/* ================= RESPONSIVE ================= */

@media(max-width:1000px){

    .container{
        margin-left:0;
    }

    .filter-form{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-form input,
    .filter-form select,
    .filter-form button{
        width:100%;
    }

    .drill-card{
        grid-template-columns:1fr;
    }
}

</style>
</head>

<body>

<div class="container">

<h2>📊 Pumped Volume Analytics</h2>

<!-- KPI CARDS -->

<div class="kpis">

<div class="kpi-card">
<h3><?= number_format($total_volume_all) ?></h3>
<p>Total Pumped Volume (m³)</p>
</div>

<div class="kpi-card">
<h3><?= $activeMeters ?></h3>
<p>Active Meters</p>
</div>

<div class="kpi-card">
<h3><?= htmlspecialchars($highestZone) ?></h3>
<p>Top Consumption Zone</p>
</div>

<div class="kpi-card">
<h3><?= htmlspecialchars($highestType) ?></h3>
<p>Top Customer Type</p>
</div>

</div>

<!-- FILTERS -->

<div class="card">

<form
method="GET"
action="dashboard.php"
class="filter-form">

<input
type="hidden"
name="page"
value="modules/production/pumped_volume.php">

<!-- SERIAL FILTER -->

<select name="search">

<option value="">
Select Meter Serial
</option>

<?php

$serials = $conn->query("
SELECT DISTINCT serial_number
FROM meters
ORDER BY serial_number ASC
");

while($s = $serials->fetch_assoc()):

?>

<option
value="<?= htmlspecialchars($s['serial_number']) ?>"
<?= ($search == $s['serial_number']) ? 'selected' : '' ?>>

<?= htmlspecialchars($s['serial_number']) ?>

</option>

<?php endwhile; ?>

</select>

<!-- ZONE FILTER -->

<select name="zone">

<option value="">
Select Zone
</option>

<?php

$zonesFilter = $conn->query("
SELECT DISTINCT zone
FROM meters
WHERE zone IS NOT NULL
AND zone != ''
ORDER BY zone ASC
");

while($z = $zonesFilter->fetch_assoc()):

?>

<option
value="<?= htmlspecialchars($z['zone']) ?>"
<?= ($zone_filter == $z['zone']) ? 'selected' : '' ?>>

<?= htmlspecialchars($z['zone']) ?>

</option>

<?php endwhile; ?>

</select>

<!-- CUSTOMER TYPE FILTER -->

<select name="customer_type">

<option value="">
Customer Type
</option>

<?php

$types = $conn->query("
SELECT DISTINCT customer_type
FROM meters
WHERE customer_type IS NOT NULL
AND customer_type != ''
ORDER BY customer_type ASC
");

while($t = $types->fetch_assoc()):

?>

<option
value="<?= htmlspecialchars($t['customer_type']) ?>"
<?= ($customer_type_filter == $t['customer_type']) ? 'selected' : '' ?>>

<?= htmlspecialchars($t['customer_type']) ?>

</option>

<?php endwhile; ?>

</select>

<!-- DATE FILTERS -->

<input
type="date"
name="start_date"
value="<?= htmlspecialchars($start) ?>">

<input
type="date"
name="end_date"
value="<?= htmlspecialchars($end) ?>">

<button type="submit">
Filter Analytics
</button>

</form>

</div>

<!-- TABLE SECTION -->

<div class="card">

<button
class="toggle-btn active-toggle"
onclick="toggleTable('customerTable', this)">

Consumption per Customer

</button>

<button
class="toggle-btn"
onclick="toggleTable('zoneTable', this)">

Consumption per Zone

</button>

<button
class="toggle-btn"
onclick="toggleTable('typeTable', this)">

Consumption per Customer Type

</button>

<!-- CUSTOMER TABLE -->

<div id="customerTable">

<div class="table-wrapper">

<table>

<tr>
<th>Meter Serial</th>
<th>Customer Name</th>
<th>Zone</th>
<th>Customer Type</th>
<th>Pumped Volume</th>
<th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>

<tr>

<td><?= htmlspecialchars($row['serial_number']) ?></td>

<td><?= htmlspecialchars($row['customer_name']) ?></td>

<td><?= htmlspecialchars($row['zone']) ?></td>

<td><?= htmlspecialchars($row['customer_type']) ?></td>

<td>
<?= number_format($row['pumped_volume'] ?? 0) ?> m³
</td>

<td>

<button
class="expand-btn"
onclick="toggleDrill(<?= $row['id'] ?>)">

Details

</button>

</td>

</tr>

<!-- DRILLDOWN -->

<tr
id="drill-<?= $row['id'] ?>"
class="drilldown">

<td colspan="6">

<div class="drill-card">

<div class="drill-item">
<strong>Meter Serial</strong>
<?= htmlspecialchars($row['serial_number']) ?>
</div>

<div class="drill-item">
<strong>Customer Name</strong>
<?= htmlspecialchars($row['customer_name']) ?>
</div>

<div class="drill-item">
<strong>Zone</strong>
<?= htmlspecialchars($row['zone']) ?>
</div>

<div class="drill-item">
<strong>Customer Type</strong>
<?= htmlspecialchars($row['customer_type']) ?>
</div>

<div class="drill-item">
<strong>Total Consumption</strong>
<?= number_format($row['pumped_volume'] ?? 0) ?> m³
</div>

<div class="drill-item">
<strong>Consumption Insight</strong>

<?php

$volume = $row['pumped_volume'] ?? 0;

if($volume > 5000){

    echo "High consumption meter";

}
elseif($volume > 2000){

    echo "Moderate consumption";

}
else{

    echo "Low consumption";
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

<a href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $page-1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>">

← Prev

</a>

<?php endif; ?>

<?php for($i=1; $i<=$total_pages; $i++): ?>

<a
href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $i ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>"

class="<?= ($i == $page) ? 'active' : '' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

<?php if($page < $total_pages): ?>

<a href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $page+1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>">

Next →

</a>

<?php endif; ?>

</div>

</div>

<!-- ZONE TABLE -->

<div id="zoneTable" class="hidden">

<div class="table-wrapper">

<table>

<tr>
<th>Zone</th>
<th>Total Volume</th>
</tr>

<?php foreach($zone_data as $zone => $volume): ?>

<tr>

<td><?= htmlspecialchars($zone) ?></td>

<td><?= number_format($volume) ?> m³</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<!-- CUSTOMER TYPE TABLE -->

<div id="typeTable" class="hidden">

<div class="table-wrapper">

<table>

<tr>
<th>Customer Type</th>
<th>Total Consumption</th>
<th>Percentage</th>
</tr>

<?php foreach($type_data as $type => $volume):

$percentage =
($total_volume_all > 0)
? round(($volume/$total_volume_all)*100,1)
: 0;

?>

<tr>

<td>
<?= htmlspecialchars($type ?: 'Unknown') ?>
</td>

<td>
<?= number_format($volume) ?> m³
</td>

<td>
<?= $percentage ?>%
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

</div>

<a
href="/wowasco-system/dashboard.php"
class="back-btn">

← Back

</a>

</div>

<script>

function toggleTable(id, btn){

    let customer =
    document.getElementById('customerTable');

    let zone =
    document.getElementById('zoneTable');

    let type =
    document.getElementById('typeTable');

    customer.style.display = 'none';
    zone.style.display = 'none';
    type.style.display = 'none';

    document.getElementById(id)
    .style.display = 'block';

    /* REMOVE ACTIVE STATE */

    let buttons =
    document.querySelectorAll('.toggle-btn');

    buttons.forEach(button => {
        button.classList.remove('active-toggle');
    });

    /* ADD ACTIVE STATE */

    btn.classList.add('active-toggle');
}

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