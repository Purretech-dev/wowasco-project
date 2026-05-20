<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/db.php';

/* ================= CREATE TABLE IF NOT EXISTS ================= */

$conn->query("
CREATE TABLE IF NOT EXISTS pumped_volume_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    pumped_date DATE NOT NULL,
    volume_m3 DECIMAL(12,2) NOT NULL DEFAULT 0,
    source_type VARCHAR(50) DEFAULT 'Manual Entry',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (meter_id),
    INDEX (pumped_date)
)
");

/* ================= HELPERS ================= */

function safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ================= FILTERS ================= */

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$zone_filter = trim($_GET['zone'] ?? '');
$type_filter = trim($_GET['customer_type'] ?? '');

$filterApplied = ($zone_filter !== '' || $type_filter !== '');
$scopeLabel = $filterApplied ? 'Filtered Dataset' : 'All Production Dataset';

/* ================= DATE RANGE ================= */

$startDate = $year . '-' . $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

/* ================= DYNAMIC FILTER ================= */

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if($zone_filter !== ''){
    $where .= " AND m.zone = ? ";
    $params[] = $zone_filter;
    $types .= "s";
}

if($type_filter !== ''){
    $where .= " AND m.customer_type = ? ";
    $params[] = $type_filter;
    $types .= "s";
}

/* ================= PRODUCTION FROM PUMPED VOLUME ================= */

$productionSql = "
    SELECT
        COALESCE(SUM(p.volume_m3),0) AS total_production
    FROM pumped_volume_entries p
    INNER JOIN meters m
        ON p.meter_id = m.id
    $where
    AND p.pumped_date BETWEEN ? AND ?
";

$productionParams = $params;
$productionTypes = $types . "ss";
$productionParams[] = $startDate;
$productionParams[] = $endDate;

$productionStmt = $conn->prepare($productionSql);

if(!empty($productionParams)){
    $productionStmt->bind_param($productionTypes, ...$productionParams);
}

$productionStmt->execute();

$totalProduction = (float)($productionStmt->get_result()->fetch_assoc()['total_production'] ?? 0);

/* ================= BILLING FROM METER READINGS ================= */

$billingSql = "
    SELECT
        COALESCE(SUM(r.consumption),0) AS total_billed
    FROM meter_readings r
    INNER JOIN meters m
        ON r.meter_id = m.id
    $where
    AND DATE(r.reading_date) BETWEEN ? AND ?
";

$billingParams = $params;
$billingTypes = $types . "ss";
$billingParams[] = $startDate;
$billingParams[] = $endDate;

$billingStmt = $conn->prepare($billingSql);

if(!empty($billingParams)){
    $billingStmt->bind_param($billingTypes, ...$billingParams);
}

$billingStmt->execute();

$totalBilled = (float)($billingStmt->get_result()->fetch_assoc()['total_billed'] ?? 0);

/* ================= NRW / WATER LOSS ================= */

$waterLoss = max(0, $totalProduction - $totalBilled);

$nrwPercent = ($totalProduction > 0)
    ? round(($waterLoss / $totalProduction) * 100, 2)
    : 0;

$revenueWaterPercent = ($totalProduction > 0)
    ? round(($totalBilled / $totalProduction) * 100, 2)
    : 0;

if($nrwPercent <= 10){
    $lossClass = "Efficient";
    $lossBadge = "good";
}
elseif($nrwPercent <= 20){
    $lossClass = "Moderate";
    $lossBadge = "warning";
}
elseif($nrwPercent <= 30){
    $lossClass = "High Loss";
    $lossBadge = "danger";
}
else{
    $lossClass = "Critical NRW";
    $lossBadge = "critical";
}

/* ================= METERS IN CURRENT SCOPE ================= */

$meterSql = "
    SELECT
        COUNT(*) AS total_meters,
        SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS active_meters,
        SUM(CASE WHEN status='Inactive' THEN 1 ELSE 0 END) AS inactive_meters
    FROM meters m
    $where
";

$meterStmt = $conn->prepare($meterSql);

if(!empty($params)){
    $meterStmt->bind_param($types, ...$params);
}

$meterStmt->execute();
$meterStats = $meterStmt->get_result()->fetch_assoc();

$totalMeters = (int)($meterStats['total_meters'] ?? 0);
$activeMeters = (int)($meterStats['active_meters'] ?? 0);
$inactiveMeters = (int)($meterStats['inactive_meters'] ?? 0);

/* ================= ZONE ANALYTICS ================= */

$zoneSql = "
    SELECT
        COALESCE(NULLIF(m.zone,''),'Unassigned') AS zone_name,
        COALESCE(SUM(p.volume_m3),0) AS production_volume,
        COALESCE((
            SELECT SUM(r.consumption)
            FROM meter_readings r
            WHERE r.meter_id = m.id
            AND DATE(r.reading_date) BETWEEN ? AND ?
        ),0) AS billed_volume
    FROM meters m
    LEFT JOIN pumped_volume_entries p
        ON p.meter_id = m.id
        AND p.pumped_date BETWEEN ? AND ?
    $where
    GROUP BY zone_name
    ORDER BY production_volume DESC
";

$zoneParams = [$startDate, $endDate, $startDate, $endDate];
$zoneTypes = "ssss";

if(!empty($params)){
    $zoneParams = array_merge($zoneParams, $params);
    $zoneTypes .= $types;
}

$zoneStmt = $conn->prepare($zoneSql);
$zoneStmt->bind_param($zoneTypes, ...$zoneParams);
$zoneStmt->execute();
$zoneResult = $zoneStmt->get_result();

$zoneData = [];

while($z = $zoneResult->fetch_assoc()){

    $production = (float)$z['production_volume'];
    $billed = (float)$z['billed_volume'];
    $loss = max(0, $production - $billed);
    $lossPercent = $production > 0 ? round(($loss / $production) * 100, 2) : 0;

    $z['loss_volume'] = $loss;
    $z['loss_percent'] = $lossPercent;

    $zoneData[] = $z;
}

$highestZone = !empty($zoneData) ? $zoneData[0]['zone_name'] : 'N/A';

/* ================= CUSTOMER TYPE ANALYTICS ================= */

$typeSql = "
    SELECT
        COALESCE(NULLIF(m.customer_type,''),'Unknown') AS customer_type_name,
        COALESCE(SUM(p.volume_m3),0) AS production_volume,
        COALESCE((
            SELECT SUM(r.consumption)
            FROM meter_readings r
            WHERE r.meter_id = m.id
            AND DATE(r.reading_date) BETWEEN ? AND ?
        ),0) AS billed_volume
    FROM meters m
    LEFT JOIN pumped_volume_entries p
        ON p.meter_id = m.id
        AND p.pumped_date BETWEEN ? AND ?
    $where
    AND LOWER(TRIM(IFNULL(m.customer_type,''))) <> 'industrial'
    GROUP BY customer_type_name
    ORDER BY production_volume DESC
";

$typeParams = [$startDate, $endDate, $startDate, $endDate];
$typeTypes = "ssss";

if(!empty($params)){
    $typeParams = array_merge($typeParams, $params);
    $typeTypes .= $types;
}

$typeStmt = $conn->prepare($typeSql);
$typeStmt->bind_param($typeTypes, ...$typeParams);
$typeStmt->execute();
$typeResult = $typeStmt->get_result();

$typeData = [];

while($t = $typeResult->fetch_assoc()){

    $production = (float)$t['production_volume'];
    $billed = (float)$t['billed_volume'];
    $loss = max(0, $production - $billed);
    $lossPercent = $production > 0 ? round(($loss / $production) * 100, 2) : 0;

    $t['loss_volume'] = $loss;
    $t['loss_percent'] = $lossPercent;

    $typeData[] = $t;
}

/* ================= MONTHLY TREND ================= */

$trendData = [];

for($i = 1; $i <= 12; $i++){

    $mVal = str_pad($i, 2, '0', STR_PAD_LEFT);
    $sDate = $year . '-' . $mVal . '-01';
    $eDate = date('Y-m-t', strtotime($sDate));

    $trendProdSql = "
        SELECT COALESCE(SUM(p.volume_m3),0) AS total
        FROM pumped_volume_entries p
        INNER JOIN meters m ON p.meter_id = m.id
        $where
        AND p.pumped_date BETWEEN ? AND ?
    ";

    $trendParams = $params;
    $trendTypes = $types . "ss";
    $trendParams[] = $sDate;
    $trendParams[] = $eDate;

    $tpStmt = $conn->prepare($trendProdSql);

    if(!empty($trendParams)){
        $tpStmt->bind_param($trendTypes, ...$trendParams);
    }

    $tpStmt->execute();
    $prod = (float)($tpStmt->get_result()->fetch_assoc()['total'] ?? 0);

    $trendBillSql = "
        SELECT COALESCE(SUM(r.consumption),0) AS total
        FROM meter_readings r
        INNER JOIN meters m ON r.meter_id = m.id
        $where
        AND DATE(r.reading_date) BETWEEN ? AND ?
    ";

    $tbParams = $params;
    $tbTypes = $types . "ss";
    $tbParams[] = $sDate;
    $tbParams[] = $eDate;

    $tbStmt = $conn->prepare($trendBillSql);

    if(!empty($tbParams)){
        $tbStmt->bind_param($tbTypes, ...$tbParams);
    }

    $tbStmt->execute();
    $bill = (float)($tbStmt->get_result()->fetch_assoc()['total'] ?? 0);

    $loss = max(0, $prod - $bill);

    $trendData[] = [
        'month' => date('M', strtotime($sDate)),
        'production' => $prod,
        'billed' => $bill,
        'loss' => $loss
    ];
}

/* ================= DROPDOWNS ================= */

$zones = $conn->query("
    SELECT DISTINCT zone
    FROM meters
    WHERE zone IS NOT NULL AND zone != ''
    ORDER BY zone ASC
");

$types = $conn->query("
    SELECT DISTINCT customer_type
    FROM meters
    WHERE customer_type IS NOT NULL AND customer_type != ''
    AND LOWER(TRIM(customer_type)) <> 'industrial'
    ORDER BY customer_type ASC
");

?>

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

.page-header{
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:22px;
}

.page-header h2{
    margin:0;
    font-size:26px;
    font-weight:800;
    color:#0f172a;
}

.page-header p{
    margin:6px 0 0;
    font-size:13px;
    color:#64748b;
}

.scope-badge{
    background:#eff6ff;
    color:#1e40af;
    border:1px solid #dbeafe;
    padding:10px 14px;
    border-radius:14px;
    font-size:13px;
    font-weight:800;
}

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
    margin-bottom:24px;
}

.kpi{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e5e7eb;
    border-left:5px solid #1e7d4f;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.kpi.blue{ border-left-color:#1e3a8a; }
.kpi.yellow{ border-left-color:#eab308; }
.kpi.red{ border-left-color:#dc2626; }

.kpi h3{
    margin:0;
    font-size:29px;
    color:#0f172a;
}

.kpi p{
    margin-top:8px;
    font-size:13px;
    color:#64748b;
    font-weight:700;
}

.panel{
    background:white;
    border-radius:16px;
    padding:22px;
    border:1px solid #e5e7eb;
    margin-bottom:22px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.panel-title{
    font-size:17px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:16px;
}

.filters{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}

.filters select,
.filters button,
.filters a{
    padding:11px 14px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
    text-decoration:none;
}

.filters button{
    background:#1e7d4f;
    color:white;
    border:none;
    font-weight:800;
    cursor:pointer;
}

.reset-btn{
    background:#f8fafc;
    color:#334155;
    font-weight:700;
}

.toggle-btn{
    background:white;
    border:1px solid #dbe2ea;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    font-weight:700;
    color:#475569;
    min-width:160px;
}

.active-toggle{
    background:#eff6ff;
    border-color:#2563eb;
    color:#1e40af;
}

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
    border-bottom:2px solid #e5e7eb;
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

.badge{
    padding:6px 11px;
    border-radius:20px;
    font-size:12px;
    font-weight:800;
    display:inline-block;
}

.good{
    background:#dcfce7;
    color:#15803d;
}

.warning{
    background:#fef9c3;
    color:#a16207;
}

.danger{
    background:#fee2e2;
    color:#b91c1c;
}

.critical{
    background:#7f1d1d;
    color:white;
}

.alert{
    padding:16px;
    border-radius:12px;
    margin-top:14px;
    font-size:14px;
    border-left:5px solid #1e3a8a;
    background:#f8fafc;
    color:#334155;
    line-height:1.8;
}

.nrw-box{
    background:#fef2f2;
    border-left:5px solid #dc2626;
    color:#7f1d1d;
}

.hidden{
    display:none;
}

.chart-wrap{
    height:330px;
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
    .filters button,
    .filters a{
        width:100%;
        box-sizing:border-box;
    }
}
</style>

<div class="container">

<div class="page-header">
    <div>
        <h2>Water Production & NRW Intelligence</h2>
        <p>Production, billed consumption, water loss, and non-revenue water analysis.</p>
    </div>

    <div class="scope-badge">
        <?= safe($scopeLabel) ?> | <?= safe(date('F Y', strtotime($startDate))) ?>
    </div>
</div>

<!-- FILTER PANEL -->

<div class="panel">

<div class="panel-title">Filter Production Comparison</div>

<form method="GET" action="dashboard.php" class="filters">

    <input type="hidden" name="page" value="modules/production/production_comparison.php">

    <select name="month">
        <?php for($m=1; $m<=12; $m++): ?>
            <?php $val = str_pad($m,2,"0",STR_PAD_LEFT); ?>
            <option value="<?= $val ?>" <?= ($month==$val)?'selected':'' ?>>
                <?= date('F', strtotime("2025-$val-01")) ?>
            </option>
        <?php endfor; ?>
    </select>

    <select name="year">
        <?php for($y=2024; $y<=2035; $y++): ?>
            <option value="<?= $y ?>" <?= ($year==$y)?'selected':'' ?>>
                <?= $y ?>
            </option>
        <?php endfor; ?>
    </select>

    <select name="zone">
        <option value="">All Zones</option>
        <?php while($zone = $zones->fetch_assoc()): ?>
            <option value="<?= safe($zone['zone']) ?>" <?= ($zone_filter==$zone['zone'])?'selected':'' ?>>
                <?= safe($zone['zone']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="customer_type">
        <option value="">All Customer Types</option>
        <?php while($type = $types->fetch_assoc()): ?>
            <option value="<?= safe($type['customer_type']) ?>" <?= ($type_filter==$type['customer_type'])?'selected':'' ?>>
                <?= safe($type['customer_type']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button type="submit">Apply Filters</button>

    <a class="reset-btn" href="dashboard.php?page=modules/production/production_comparison.php">
        Reset
    </a>

</form>

</div>

<!-- KPI -->

<div class="kpis">

    <div class="kpi blue">
        <h3><?= number_format($totalProduction,2) ?></h3>
        <p>Total Produced / Pumped Volume (m³)</p>
    </div>

    <div class="kpi">
        <h3><?= number_format($totalBilled,2) ?></h3>
        <p>Billed Consumption (m³)</p>
    </div>

    <div class="kpi red">
        <h3><?= number_format($waterLoss,2) ?></h3>
        <p>Water Loss / NRW Volume (m³)</p>
    </div>

    <div class="kpi red">
        <h3><?= $nrwPercent ?>%</h3>
        <p>Non-Revenue Water</p>
    </div>

    <div class="kpi yellow">
        <h3><?= $revenueWaterPercent ?>%</h3>
        <p>Revenue Water Ratio</p>
    </div>

    <div class="kpi">
        <h3><?= safe($highestZone) ?></h3>
        <p>Highest Production Zone</p>
    </div>

    <div class="kpi blue">
        <h3><?= number_format($activeMeters) ?></h3>
        <p>Active Meters in Scope</p>
    </div>

    <div class="kpi yellow">
        <h3><span class="badge <?= safe($lossBadge) ?>"><?= safe($lossClass) ?></span></h3>
        <p>NRW Classification</p>
    </div>

</div>

<!-- CHART -->

<div class="panel">
    <div class="panel-title">Production vs Billed Consumption vs NRW Trend</div>
    <div class="chart-wrap">
        <canvas id="trendChart"></canvas>
    </div>
</div>

<!-- ANALYTICS -->

<div class="panel">

<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:22px;">

    <button class="toggle-btn active-toggle" onclick="toggleTable('zoneTable',this)">
        Zone NRW Analytics
    </button>

    <button class="toggle-btn" onclick="toggleTable('typeTable',this)">
        Customer Type Analytics
    </button>

    <button class="toggle-btn" onclick="toggleTable('lossTable',this)">
        NRW Summary
    </button>

</div>

<div id="zoneTable">

<div class="table-wrapper">

<table>
<tr>
    <th>Zone</th>
    <th>Produced</th>
    <th>Billed</th>
    <th>NRW / Loss</th>
    <th>NRW %</th>
    <th>Status</th>
</tr>

<?php if(empty($zoneData)): ?>
<tr>
    <td colspan="6">No data found for the selected period.</td>
</tr>
<?php endif; ?>

<?php foreach($zoneData as $zone): ?>

<?php
$zp = (float)$zone['production_volume'];
$zb = (float)$zone['billed_volume'];
$zl = (float)$zone['loss_volume'];
$zPercent = (float)$zone['loss_percent'];

if($zPercent <= 10){
    $zClass = 'good';
    $zLabel = 'Efficient';
}
elseif($zPercent <= 20){
    $zClass = 'warning';
    $zLabel = 'Moderate';
}
elseif($zPercent <= 30){
    $zClass = 'danger';
    $zLabel = 'High Loss';
}
else{
    $zClass = 'critical';
    $zLabel = 'Critical NRW';
}
?>

<tr>
    <td><strong><?= safe($zone['zone_name']) ?></strong></td>
    <td><?= number_format($zp,2) ?> m³</td>
    <td><?= number_format($zb,2) ?> m³</td>
    <td><?= number_format($zl,2) ?> m³</td>
    <td><?= $zPercent ?>%</td>
    <td><span class="badge <?= $zClass ?>"><?= $zLabel ?></span></td>
</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<div id="typeTable" class="hidden">

<div class="table-wrapper">

<table>
<tr>
    <th>Customer Type</th>
    <th>Produced</th>
    <th>Billed</th>
    <th>NRW / Loss</th>
    <th>NRW %</th>
</tr>

<?php if(empty($typeData)): ?>
<tr>
    <td colspan="5">No customer type data found.</td>
</tr>
<?php endif; ?>

<?php foreach($typeData as $type): ?>
<tr>
    <td><strong><?= safe($type['customer_type_name']) ?></strong></td>
    <td><?= number_format($type['production_volume'],2) ?> m³</td>
    <td><?= number_format($type['billed_volume'],2) ?> m³</td>
    <td><?= number_format($type['loss_volume'],2) ?> m³</td>
    <td><?= $type['loss_percent'] ?>%</td>
</tr>
<?php endforeach; ?>

</table>

</div>

</div>

<div id="lossTable" class="hidden">

<div class="alert nrw-box">

<strong>Non-Revenue Water Summary</strong><br><br>

Total produced water for the selected period is
<strong><?= number_format($totalProduction,2) ?> m³</strong>.

<br><br>

Total billed consumption is
<strong><?= number_format($totalBilled,2) ?> m³</strong>.

<br><br>

The system has recorded
<strong><?= number_format($waterLoss,2) ?> m³</strong>
as non-revenue water, representing
<strong><?= $nrwPercent ?>%</strong>
of produced water.

<br><br>

Classification:
<span class="badge <?= safe($lossBadge) ?>">
<?= safe($lossClass) ?>
</span>

<br><br>

Recommended actions: inspect high-loss zones, compare production entries with meter readings,
verify inactive meters, investigate leakages, and validate unbilled consumption.

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const trendData = <?= json_encode($trendData); ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendData.map(row => row.month),
        datasets: [
            {
                label: 'Produced',
                data: trendData.map(row => Number(row.production)),
                backgroundColor: '#1e3a8a'
            },
            {
                label: 'Billed',
                data: trendData.map(row => Number(row.billed)),
                backgroundColor: '#1e7d4f'
            },
            {
                label: 'NRW / Loss',
                data: trendData.map(row => Number(row.loss)),
                backgroundColor: '#dc2626'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

function toggleTable(id, btn){

    let sections = [
        'zoneTable',
        'typeTable',
        'lossTable'
    ];

    sections.forEach(section => {
        document.getElementById(section).style.display = 'none';
    });

    document.getElementById(id).style.display = 'block';

    let buttons = document.querySelectorAll('.toggle-btn');

    buttons.forEach(button => {
        button.classList.remove('active-toggle');
    });

    btn.classList.add('active-toggle');
}
</script>
