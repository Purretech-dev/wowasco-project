<?php
include(__DIR__ . '/../../api/db.php');

include(__DIR__ . '/../../includes/sidebar.php');
include(__DIR__ . '/../../includes/navbar.php');

/* ================= SEARCH ================= */
$search = $_GET['search'] ?? '';

$search_sql = "";
if(!empty($search)){
    $safe = $conn->real_escape_string($search);
    $search_sql = " AND (m.serial_number LIKE '%$safe%' OR m.zone LIKE '%$safe%')";
}

/* ================= FILTER ================= */
$start = $_GET['start_date'] ?? '';
$end = $_GET['end_date'] ?? '';

$filter_sql = "";
if($start && $end){
    $filter_sql = " AND r.reading_date BETWEEN '$start 00:00:00' AND '$end 23:59:59'";
}

/* ================= CUSTOMER DATA ================= */
$sql = "SELECT m.serial_number, m.customer_name,
               SUM(r.reading_value) as pumped_volume
        FROM meters m
        LEFT JOIN meter_readings r ON m.id = r.meter_id
        WHERE 1=1
        $search_sql
        $filter_sql
        GROUP BY m.id
        ORDER BY m.serial_number ASC";

$result = $conn->query($sql);

/* ================= TYPE ANALYSIS ================= */
$type_sql = "SELECT m.customer_type,
                    SUM(r.reading_value) as total_volume
             FROM meters m
             LEFT JOIN meter_readings r ON m.id = r.meter_id
             WHERE 1=1
             $search_sql
             $filter_sql
             GROUP BY m.customer_type";

$type_result = $conn->query($type_sql);

/* ================= ZONE DATA ================= */
$zone_sql = "SELECT m.zone,
                    SUM(r.reading_value) as total_volume
             FROM meters m
             LEFT JOIN meter_readings r ON m.id = r.meter_id
             WHERE 1=1
             $search_sql
             $filter_sql
             GROUP BY m.zone";

$zone_result = $conn->query($zone_sql);

$zone_data = [];
while($z = $zone_result->fetch_assoc()){
    $zone_data[$z['zone']] = $z['total_volume'];
}

$total_volume_all = 0;
$type_data = [];
while($row = $type_result->fetch_assoc()){
    $type_data[$row['customer_type']] = $row['total_volume'];
    $total_volume_all += $row['total_volume'];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Pumped Volumes - WOWASCO</title>

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: #eef3f8;
    margin: 0;
}

.container {
    margin-left: 220px;
    margin-top: 70px;
    padding: 20px;
    padding-bottom: 120px;
}

h2 {
    text-align: center;
    color: #003366;
}

.card {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

/* FILTER */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.filter-form input {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    min-width: 160px;
}

.filter-form button {
    padding: 10px 15px;
    background: #003366;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

th {
    background: #003366;
    color: white;
}

/* BUTTONS */
.toggle-btn {
    background: #003366;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 6px;
    cursor: pointer;
    margin-right: 10px;
}

/* HIDDEN */
.hidden {
    display: none;
}

/* BACK */
.back-btn {
    display: inline-block;
    margin-top: 30px;
    padding: 12px 18px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 6px;
}
</style>
</head>

<body>

<div class="container">

<h2>📊 Pumped Volumes Per Meter</h2>

<!-- FILTER -->
<div class="card">
<form method="GET" class="filter-form">

    <input type="text" name="search" placeholder="Search Serial or Zone"
           value="<?= htmlspecialchars($search) ?>">

    <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>">

    <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>">

    <button type="submit">Search</button>

</form>
</div>

<!-- CUSTOMER TYPE ANALYSIS -->
<div class="card">
<h3>Consumption by Customer Type</h3>

<?php foreach($type_data as $type => $volume):
    $percentage = ($total_volume_all > 0)
        ? round(($volume/$total_volume_all)*100,1)
        : 0;
?>
<div>
    <?= htmlspecialchars($type) ?>
    <span style="float:right;font-weight:bold;color:#003366;">
        <?= number_format($volume) ?> m³ (<?= $percentage ?>%)
    </span>
</div>
<?php endforeach; ?>

</div>

<!-- BUTTONS -->
<div class="card">

<button class="toggle-btn" onclick="toggleTable('customerTable')">
Consumption per Customer
</button>

<button class="toggle-btn" onclick="toggleTable('zoneTable')">
Consumption per Zone
</button>

<!-- CUSTOMER TABLE -->
<div id="customerTable" class="hidden">
<table>
<tr>
    <th>Meter Serial</th>
    <th>Customer Name</th>
    <th>Pumped Volume (m³)</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['serial_number']) ?></td>
    <td><?= htmlspecialchars($row['customer_name']) ?></td>
    <td><?= number_format($row['pumped_volume'] ?? 0) ?></td>
</tr>
<?php endwhile; ?>

</table>
</div>

<!-- ZONE TABLE -->
<div id="zoneTable" class="hidden">
<table>
<tr>
    <th>Zone</th>
    <th>Total Volume (m³)</th>
</tr>

<?php foreach($zone_data as $zone => $volume): ?>
<tr>
    <td><?= htmlspecialchars($zone) ?></td>
    <td><?= number_format($volume) ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

</div>

<a href="/wowasco-system/dashboard.php" class="back-btn">← Back</a>

</div>

<script>
function toggleTable(id){
    let el = document.getElementById(id);
    el.style.display = (el.style.display === "block") ? "none" : "block";
}
</script>

</body>
</html>