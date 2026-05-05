<?php
include(__DIR__ . '/../../api/db.php');

/* ================= FILTERS ================= */
$start = $_GET['start_date'] ?? '';
$end = $_GET['end_date'] ?? '';
$zone = $_GET['zone'] ?? '';
$type = $_GET['customer_type'] ?? '';
$meter_type = $_GET['meter_type'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";

if($start && $end){
    $where .= " AND r.reading_date BETWEEN '$start 00:00:00' AND '$end 23:59:59'";
}

if($zone){
    $where .= " AND m.zone = '$zone'";
}

if($type){
    $where .= " AND m.customer_type = '$type'";
}

if($meter_type){
    $where .= " AND m.meter_type = '$meter_type'";
}

if($search){
    $where .= " AND (m.serial_number LIKE '%$search%' OR m.customer_name LIKE '%$search%')";
}

/* ================= DATA ================= */
$sql = "
SELECT 
    m.serial_number,
    m.customer_name,
    m.zone,
    m.customer_type,
    m.meter_type,
    SUM(r.reading_value) as consumption
FROM meters m
LEFT JOIN meter_readings r ON m.id = r.meter_id
$where
GROUP BY m.id
ORDER BY consumption DESC
";

$result = $conn->query($sql);

/* ================= KPI ================= */
$total = 0;
$count = 0;

$data = [];

while($row = $result->fetch_assoc()){
    $total += $row['consumption'] ?? 0;
    $count++;
    $data[] = $row;
}

$avg = ($count > 0) ? round($total/$count,2) : 0;
?>

<div class="container">

<h2>📊 Custom Reports</h2>

<!-- FILTER CARD -->
<div class="card">

<form method="GET" class="filter-grid">

<input type="hidden" name="page" value="modules/reports/custom_reports.php">

<div>
<label>From Date</label>
<input type="date" name="start_date" value="<?= $start ?>">
</div>

<div>
<label>To Date</label>
<input type="date" name="end_date" value="<?= $end ?>">
</div>

<div>
<label>Zone</label>
<select name="zone">
<option value="">All</option>
<option <?= ($zone=="Town")?'selected':'' ?>>Town</option>
<option <?= ($zone=="Kasarani")?'selected':'' ?>>Kasarani</option>
<option <?= ($zone=="Mukuyuni")?'selected':'' ?>>Mukuyuni</option>
<option <?= ($zone=="Kilala")?'selected':'' ?>>Kilala</option>
</select>
</div>

<div>
<label>Customer Type</label>
<select name="customer_type">
<option value="">All</option>
<option <?= ($type=="Residential")?'selected':'' ?>>Residential</option>
<option <?= ($type=="Commercial")?'selected':'' ?>>Commercial</option>
<option <?= ($type=="Government Entities")?'selected':'' ?>>Government Entities</option>
</select>
</div>

<div>
<label>Meter Type</label>
<select name="meter_type">
<option value="">All</option>
<option <?= ($meter_type=="Smart Meter")?'selected':'' ?>>Smart Meter</option>
<option <?= ($meter_type=="Conventional Meter")?'selected':'' ?>>Conventional Meter</option>
</select>
</div>

<div>
<label>Search</label>
<input type="text" name="search" placeholder="Serial / Name" value="<?= $search ?>">
</div>

<div class="btn-group">
<button type="submit">Apply Filters</button>
<a href="dashboard.php?page=modules/reports/custom_reports.php" class="reset-btn">Reset</a>
</div>

</form>

</div>

<!-- KPI CARDS -->
<div class="cards">

<div class="card kpi">
<h4>Total Consumption</h4>
<p><?= number_format($total) ?> m³</p>
</div>

<div class="card kpi">
<h4>Records</h4>
<p><?= $count ?></p>
</div>

<div class="card kpi">
<h4>Average</h4>
<p><?= number_format($avg) ?> m³</p>
</div>

</div>

<!-- TABLE -->
<div class="card">

<table>
<tr>
<th>Serial</th>
<th>Customer</th>
<th>Zone</th>
<th>Type</th>
<th>Meter</th>
<th>Consumption</th>
</tr>

<?php if($count > 0): ?>
<?php foreach($data as $d): ?>
<tr>
<td><?= htmlspecialchars($d['serial_number']) ?></td>
<td><?= htmlspecialchars($d['customer_name']) ?></td>
<td><?= htmlspecialchars($d['zone']) ?></td>
<td><?= htmlspecialchars($d['customer_type']) ?></td>
<td><?= htmlspecialchars($d['meter_type']) ?></td>
<td><?= number_format($d['consumption']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="6" style="text-align:center;">No data found</td>
</tr>
<?php endif; ?>

</table>

</div>

</div>

<style>

/* ================= LAYOUT ================= */
.container{
    margin-left:240px;
    margin-top:70px;
    padding:20px;
}

/* ================= CARD ================= */
.card{
    background:white;
    padding:20px;
    border-radius:10px;
    margin-bottom:20px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}

/* ================= FILTER GRID ================= */
.filter-grid{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    gap:15px;
}

.filter-grid label{
    font-weight:600;
    color:#003366;
}

.filter-grid input,
.filter-grid select{
    width:100%;
    padding:10px;
    border-radius:6px;
    border:1px solid #ccc;
}

/* ================= BUTTONS ================= */
.btn-group{
    display:flex;
    align-items:end;
    gap:10px;
}

button{
    padding:10px 15px;
    background:#003366;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.reset-btn{
    padding:10px 15px;
    background:#6c757d;
    color:white;
    text-decoration:none;
    border-radius:6px;
}

/* ================= KPI ================= */
.cards{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

.kpi{
    flex:1;
    min-width:180px;
    text-align:center;
}

.kpi h4{
    color:#555;
}

.kpi p{
    font-size:22px;
    font-weight:bold;
    color:#003366;
}

/* ================= TABLE ================= */
table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:10px;
    border-bottom:1px solid #eee;
}

th{
    background:#003366;
    color:white;
}

tr:hover{
    background:#f1f7ff;
}

</style>