<?php
include(__DIR__ . '/../../api/db.php');

/* ================= FILTERS ================= */

$search = trim($_GET['search'] ?? '');
$zone_filter = trim($_GET['zone'] ?? '');
$risk_filter = trim($_GET['risk'] ?? '');

/* ================= HELPERS ================= */

function safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function riskScore($m){

    $score = 0;

    if(strtolower(trim($m['status'] ?? '')) === 'inactive'){
        $score += 50;
    }

    if(empty($m['zone'])){
        $score += 20;
    }

    if(isset($m['battery_level']) && (int)$m['battery_level'] < 30){
        $score += 20;
    }

    if(isset($m['signal_strength']) && (int)$m['signal_strength'] < 40){
        $score += 20;
    }

    if(!empty($m['installation_date'])){

        $installationTime = strtotime($m['installation_date']);

        if($installationTime){

            $years = (time() - $installationTime) / (365 * 24 * 60 * 60);

            if($years > 10){
                $score += 30;
            }
            elseif($years > 5){
                $score += 15;
            }
        }
    }

    if(!empty($m['last_reading_date'])){

        $lastReadingTime = strtotime($m['last_reading_date']);

        if($lastReadingTime){

            $daysSinceReading = (time() - $lastReadingTime) / (24 * 60 * 60);

            if($daysSinceReading > 60){
                $score += 20;
            }
            elseif($daysSinceReading > 30){
                $score += 10;
            }
        }
    }else{
        $score += 10;
    }

    if(strtolower(trim($m['maintenance_status'] ?? '')) === 'pending'){
        $score += 15;
    }

    return min($score, 100);
}

function riskLevel($score){

    if($score >= 70){
        return ['Critical', 'critical'];
    }

    if($score >= 40){
        return ['Warning', 'warning'];
    }

    return ['Good', 'good'];
}

function recommendedAction($score, $m){

    if($score >= 70){
        return "Immediate field inspection required. Validate meter status, signal, battery, and last reading.";
    }

    if($score >= 40){
        return "Monitor closely. Schedule preventive maintenance and confirm recent readings.";
    }

    return "No urgent action required. Continue normal monitoring.";
}

/* ================= FETCH METERS ================= */

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if($search !== ''){
    $where .= " AND serial_number LIKE ? ";
    $params[] = "%".$search."%";
    $types .= "s";
}

if($zone_filter !== ''){
    $where .= " AND zone = ? ";
    $params[] = $zone_filter;
    $types .= "s";
}

$baseSql = "SELECT * FROM meters $where ORDER BY id DESC";

$stmt = $conn->prepare($baseSql);

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$allResult = $stmt->get_result();

$allMeters = [];

while($row = $allResult->fetch_assoc()){

    $score = riskScore($row);
    [$level, $class] = riskLevel($score);

    $row['_risk_score'] = $score;
    $row['_risk_level'] = $level;
    $row['_risk_class'] = $class;
    $row['_recommended_action'] = recommendedAction($score, $row);

    if($risk_filter === '' || strtolower($level) === strtolower($risk_filter)){
        $allMeters[] = $row;
    }
}

/* ================= COUNTS ================= */

$total = count($allMeters);
$inactive = 0;
$critical = 0;
$warning = 0;
$good = 0;
$unassigned = 0;
$lowBattery = 0;
$lowSignal = 0;
$pendingMaintenance = 0;

foreach($allMeters as $m){

    if(strtolower(trim($m['status'] ?? '')) === 'inactive'){
        $inactive++;
    }

    if($m['_risk_level'] === 'Critical'){
        $critical++;
    }
    elseif($m['_risk_level'] === 'Warning'){
        $warning++;
    }
    else{
        $good++;
    }

    if(empty($m['zone'])){
        $unassigned++;
    }

    if(isset($m['battery_level']) && (int)$m['battery_level'] < 30){
        $lowBattery++;
    }

    if(isset($m['signal_strength']) && (int)$m['signal_strength'] < 40){
        $lowSignal++;
    }

    if(strtolower(trim($m['maintenance_status'] ?? '')) === 'pending'){
        $pendingMaintenance++;
    }
}

$riskRate = $total > 0 ? round((($critical + $warning) / $total) * 100) : 0;

/* ================= PAGINATION ================= */

$limit = 5;

$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;

if($page < 1){
    $page = 1;
}

$total_records = count($allMeters);
$total_pages = max(1, ceil($total_records / $limit));

if($page > $total_pages){
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;

$pagedMeters = array_slice($allMeters, $offset, $limit);

/* ================= ZONES ================= */

$zones = [];

$zoneResult = $conn->query("
    SELECT DISTINCT zone
    FROM meters
    WHERE zone IS NOT NULL AND zone != ''
    ORDER BY zone ASC
");

while($z = $zoneResult->fetch_assoc()){
    $zones[] = $z['zone'];
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

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
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

.header-badge{
    background:#ecfdf3;
    color:#15803d;
    border:1px solid #bbf7d0;
    padding:10px 14px;
    border-radius:12px;
    font-size:13px;
    font-weight:700;
}

.section-title{
    margin:0 0 14px;
    font-size:16px;
    font-weight:800;
    color:#0f172a;
}

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
    gap:18px;
}

.kpi{
    background:white;
    border-radius:16px;
    padding:20px;
    border:1px solid #e2e8f0;
    border-left:5px solid #1e7d4f;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.kpi.blue{ border-left-color:#1e3a8a; }
.kpi.yellow{ border-left-color:#eab308; }
.kpi.red{ border-left-color:#dc2626; }

.kpi h3{
    margin:0;
    font-size:30px;
    color:#0f172a;
}

.kpi p{
    margin:8px 0 0;
    font-size:13px;
    color:#64748b;
    font-weight:700;
}

.kpi small{
    display:block;
    margin-top:8px;
    color:#94a3b8;
    font-size:12px;
}

.filters{
    margin-top:24px;
    background:white;
    padding:18px;
    border-radius:16px;
    border:1px solid #e2e8f0;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}

input,select{
    padding:11px 12px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
    min-width:170px;
}

button,.btn{
    padding:11px 16px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;
    font-size:13px;
    display:inline-block;
}

.btn-blue{ background:#1e3a8a; }

.btn-light{
    background:#f8fafc;
    color:#334155;
    border:1px solid #dbe2ea;
}

.grid{
    display:grid;
    grid-template-columns:1fr 360px;
    gap:20px;
    margin-top:24px;
}

.panel{
    background:white;
    border-radius:16px;
    border:1px solid #e2e8f0;
    padding:20px;
    box-shadow:0 4px 14px rgba(15,23,42,0.03);
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
    color:#334155;
    padding:14px;
    text-align:left;
    font-size:13px;
    border-bottom:2px solid #e2e8f0;
    white-space:nowrap;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
    vertical-align:top;
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

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:7px 10px;
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
    padding:11px;
}

.drill-item strong{
    display:block;
    font-size:12px;
    color:#64748b;
    margin-bottom:4px;
}

.alert{
    border-left:4px solid #facc15;
    padding:14px;
    border-radius:12px;
    margin-bottom:12px;
    font-size:13px;
    background:#fffbeb;
    color:#713f12;
}

.alert.red{
    background:#fef2f2;
    color:#991b1b;
    border-left-color:#dc2626;
}

.alert.green{
    background:#ecfdf3;
    color:#166534;
    border-left-color:#22c55e;
}

.alert.blue{
    background:#eff6ff;
    color:#1e3a8a;
    border-left-color:#1e3a8a;
}

.insight-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:14px;
    border-radius:12px;
    margin-top:14px;
    font-size:13px;
    color:#475569;
    line-height:1.7;
}

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
    font-weight:700;
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

.empty-state{
    background:#f8fafc;
    border:1px dashed #cbd5e1;
    padding:30px;
    border-radius:14px;
    text-align:center;
    color:#64748b;
}

/* ================= PRINT REPORT ONLY ================= */

.print-report{
    display:none;
}

@media print{

    body{
        background:white;
        color:#000;
    }

    body *{
        visibility:hidden !important;
    }

    .print-report,
    .print-report *{
        visibility:visible !important;
    }

    .print-report{
        display:block !important;
        position:absolute;
        left:0;
        top:0;
        width:100%;
        padding:20px;
        font-family:Arial, sans-serif;
    }

    .print-report h2{
        margin:0 0 5px;
        font-size:22px;
        color:#000;
    }

    .print-report p{
        margin:0 0 15px;
        font-size:12px;
        color:#333;
    }

    .print-report table{
        width:100%;
        border-collapse:collapse;
        font-size:11px;
    }

    .print-report th,
    .print-report td{
        border:1px solid #999;
        padding:7px;
        text-align:left;
        color:#000;
    }

    .print-report th{
        background:#eee !important;
        font-weight:bold;
    }

    .container,
    .filters,
    .kpis,
    .grid,
    .pagination,
    .page-header{
        display:none !important;
    }

    @page{
        size:A4 landscape;
        margin:12mm;
    }
}

@media(max-width:1000px){

    .grid{
        grid-template-columns:1fr;
    }

    .container{
        margin-left:0;
    }

    .drill-card{
        grid-template-columns:1fr;
    }
}

</style>
</head>

<body>

<!-- ================= PRINT-ONLY REPORT ================= -->

<div class="print-report">

    <h2>Smart Meter Risk Intelligence Report</h2>

    <p>
        Generated on <?= date('d M Y H:i') ?> |
        Total Records: <?= number_format($total_records) ?> |
        Critical: <?= number_format($critical) ?> |
        Warning: <?= number_format($warning) ?> |
        Good: <?= number_format($good) ?>
    </p>

    <table>

        <thead>
            <tr>
                <th>#</th>
                <th>Serial Number</th>
                <th>Customer</th>
                <th>Customer Type</th>
                <th>Zone</th>
                <th>Status</th>
                <th>Battery</th>
                <th>Signal</th>
                <th>Installation Date</th>
                <th>Last Reading</th>
                <th>Technician</th>
                <th>Maintenance</th>
                <th>Risk Score</th>
                <th>Risk Level</th>
                <th>Recommended Action</th>
            </tr>
        </thead>

        <tbody>

            <?php if(empty($allMeters)): ?>

                <tr>
                    <td colspan="15">No meter records found.</td>
                </tr>

            <?php else: ?>

                <?php $n = 1; foreach($allMeters as $pm): ?>

                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= safe($pm['serial_number'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['customer_name'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['customer_type'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['zone'] ?? 'Unassigned') ?></td>
                        <td><?= safe($pm['status'] ?? 'N/A') ?></td>
                        <td><?= isset($pm['battery_level']) ? safe($pm['battery_level']).'%' : 'N/A' ?></td>
                        <td><?= isset($pm['signal_strength']) ? safe($pm['signal_strength']).'%' : 'N/A' ?></td>
                        <td><?= safe($pm['installation_date'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['last_reading_date'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['technician_assigned'] ?? 'Not Assigned') ?></td>
                        <td><?= safe($pm['maintenance_status'] ?? 'N/A') ?></td>
                        <td><?= safe($pm['_risk_score']) ?>/100</td>
                        <td><?= safe($pm['_risk_level']) ?></td>
                        <td><?= safe($pm['_recommended_action']) ?></td>
                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<div class="container">

<div class="page-header">

    <div>
        <h2>Smart Meter Intelligence Center</h2>
        <p>Operational alerts, risk scoring, maintenance intelligence, and smart meter health monitoring.</p>
    </div>

    <div class="header-badge">
        Risk Exposure: <?= $riskRate ?>%
    </div>

</div>

<!-- KPI -->

<div class="kpis">

    <div class="kpi blue">
        <h3><?= number_format($total) ?></h3>
        <p>Total Filtered Meters</p>
        <small>Based on current search and zone filters</small>
    </div>

    <div class="kpi yellow">
        <h3><?= number_format($inactive) ?></h3>
        <p>Inactive Meters</p>
        <small>Requires operational follow-up</small>
    </div>

    <div class="kpi red">
        <h3><?= number_format($critical) ?></h3>
        <p>Critical Risk</p>
        <small>Immediate inspection recommended</small>
    </div>

    <div class="kpi">
        <h3><?= number_format($warning) ?></h3>
        <p>Warning Risk</p>
        <small>Preventive monitoring required</small>
    </div>

</div>

<!-- FILTERS -->

<form class="filters" method="GET" action="dashboard.php">

    <input type="hidden" name="page" value="modules/metering/meter_alerts.php">

    <input
        type="text"
        name="search"
        placeholder="Search Serial Number"
        value="<?= safe($search) ?>">

    <select name="zone">
        <option value="">All Zones</option>

        <?php foreach($zones as $zone): ?>
            <option value="<?= safe($zone) ?>" <?= ($zone_filter == $zone) ? 'selected' : '' ?>>
                <?= safe($zone) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="risk">
        <option value="">All Risk Levels</option>
        <option value="Critical" <?= ($risk_filter == 'Critical') ? 'selected' : '' ?>>Critical</option>
        <option value="Warning" <?= ($risk_filter == 'Warning') ? 'selected' : '' ?>>Warning</option>
        <option value="Good" <?= ($risk_filter == 'Good') ? 'selected' : '' ?>>Good</option>
    </select>

    <button type="submit">Filter</button>

    <a class="btn btn-light" href="dashboard.php?page=modules/metering/meter_alerts.php">
        Reset
    </a>

    <button type="button" class="btn-blue" onclick="window.print()">
        Print Report
    </button>

</form>

<!-- GRID -->

<div class="grid">

<!-- LEFT -->

<div class="panel">

<h3 class="section-title">Meter Risk Intelligence</h3>

<?php if(empty($pagedMeters)): ?>

    <div class="empty-state">
        No meters found for the selected filters.
    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<tr>
    <th>Serial</th>
    <th>Zone</th>
    <th>Status</th>
    <th>Risk</th>
    <th>Level</th>
    <th>Action</th>
</tr>

<?php foreach($pagedMeters as $m): ?>

<tr>

    <td>
        <strong><?= safe($m['serial_number'] ?? 'N/A') ?></strong>
    </td>

    <td>
        <?= !empty($m['zone']) ? safe($m['zone']) : '<span class="badge warning">Unassigned</span>' ?>
    </td>

    <td>
        <?= safe($m['status'] ?? 'N/A') ?>
    </td>

    <td>
        <strong><?= safe($m['_risk_score']) ?>/100</strong>
    </td>

    <td>
        <span class="badge <?= safe($m['_risk_class']) ?>">
            <?= safe($m['_risk_level']) ?>
        </span>
    </td>

    <td>
        <button type="button" class="expand-btn" onclick="toggleDrill(<?= (int)$m['id'] ?>)">
            Details
        </button>
    </td>

</tr>

<tr id="drill-<?= (int)$m['id'] ?>" class="drilldown">

<td colspan="6">

<div class="drill-card">

    <div class="drill-item">
        <strong>Customer</strong>
        <?= safe($m['customer_name'] ?? 'N/A') ?>
    </div>

    <div class="drill-item">
        <strong>Customer Type</strong>
        <?= safe($m['customer_type'] ?? 'N/A') ?>
    </div>

    <div class="drill-item">
        <strong>Battery Level</strong>
        <?= isset($m['battery_level']) ? safe($m['battery_level']).'%' : 'N/A' ?>
    </div>

    <div class="drill-item">
        <strong>Signal Strength</strong>
        <?= isset($m['signal_strength']) ? safe($m['signal_strength']).'%' : 'N/A' ?>
    </div>

    <div class="drill-item">
        <strong>Installation Date</strong>
        <?= safe($m['installation_date'] ?? 'N/A') ?>
    </div>

    <div class="drill-item">
        <strong>Last Reading</strong>
        <?= safe($m['last_reading_date'] ?? 'N/A') ?>
    </div>

    <div class="drill-item">
        <strong>Technician</strong>
        <?= safe($m['technician_assigned'] ?? 'Not Assigned') ?>
    </div>

    <div class="drill-item">
        <strong>Maintenance Status</strong>
        <?= safe($m['maintenance_status'] ?? 'N/A') ?>
    </div>

    <div class="drill-item" style="grid-column:1 / -1;">
        <strong>Recommended Action</strong>
        <?= safe($m['_recommended_action']) ?>
    </div>

</div>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<?php endif; ?>

<!-- PAGINATION -->

<div class="pagination">

<?php if($page > 1): ?>

<a href="dashboard.php?page=modules/metering/meter_alerts.php&page_num=<?= $page-1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&risk=<?= urlencode($risk_filter) ?>">
    ← Prev
</a>

<?php endif; ?>

<?php for($i=1; $i<=$total_pages; $i++): ?>

<a
href="dashboard.php?page=modules/metering/meter_alerts.php&page_num=<?= $i ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&risk=<?= urlencode($risk_filter) ?>"
class="<?= ($i == $page) ? 'active' : '' ?>">
    <?= $i ?>
</a>

<?php endfor; ?>

<?php if($page < $total_pages): ?>

<a href="dashboard.php?page=modules/metering/meter_alerts.php&page_num=<?= $page+1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&risk=<?= urlencode($risk_filter) ?>">
    Next →
</a>

<?php endif; ?>

</div>

</div>

<!-- RIGHT -->

<div class="panel">

<h3 class="section-title">🚨 Active Alerts</h3>

<?php if($critical > 0): ?>
    <div class="alert red">
        <strong><?= number_format($critical) ?> critical meter(s) detected.</strong><br>
        Immediate technical inspection is recommended.
    </div>
<?php endif; ?>

<?php if($warning > 0): ?>
    <div class="alert">
        <strong><?= number_format($warning) ?> warning meter(s) detected.</strong><br>
        Preventive maintenance and monitoring required.
    </div>
<?php endif; ?>

<?php if($lowBattery > 0): ?>
    <div class="alert">
        <strong><?= number_format($lowBattery) ?> meter(s) have low battery levels.</strong><br>
        Schedule battery replacement or field verification.
    </div>
<?php endif; ?>

<?php if($lowSignal > 0): ?>
    <div class="alert blue">
        <strong><?= number_format($lowSignal) ?> meter(s) have weak signal strength.</strong><br>
        Check network coverage, SIM status, or device position.
    </div>
<?php endif; ?>

<?php if($unassigned > 0): ?>
    <div class="alert">
        <strong><?= number_format($unassigned) ?> meter(s) have no assigned zone.</strong><br>
        Update zone mapping to improve reporting accuracy.
    </div>
<?php endif; ?>

<?php if($pendingMaintenance > 0): ?>
    <div class="alert">
        <strong><?= number_format($pendingMaintenance) ?> meter(s) have pending maintenance.</strong><br>
        Assign technicians and close maintenance actions.
    </div>
<?php endif; ?>

<?php if($critical == 0 && $warning == 0 && $lowBattery == 0 && $lowSignal == 0 && $unassigned == 0 && $pendingMaintenance == 0): ?>
    <div class="alert green">
        <strong>No major alerts detected.</strong><br>
        Meter operations are currently within acceptable limits.
    </div>
<?php endif; ?>

<div class="insight-box">

    <strong>Executive Insight</strong><br><br>

    Current risk exposure is <strong><?= $riskRate ?>%</strong>.
    The system has identified
    <strong><?= number_format($critical) ?></strong> critical meter(s),
    <strong><?= number_format($warning) ?></strong> warning meter(s), and
    <strong><?= number_format($good) ?></strong> healthy meter(s).

    <br><br>

    Priority should be given to inactive meters, low battery meters,
    weak signal meters, and meters without zone assignment.

</div>

<div class="insight-box">

    <strong>Recommended Operational Actions</strong><br><br>

    1. Inspect all critical meters immediately.<br>
    2. Review inactive meters for billing and technical faults.<br>
    3. Assign missing zones for better analytics.<br>
    4. Replace low battery meters before failure.<br>
    5. Follow up all pending maintenance cases.

</div>

</div>

</div>

</div>

<script>

function toggleDrill(id){

    let row = document.getElementById('drill-' + id);

    if(!row){
        return;
    }

    row.style.display =
    row.style.display === 'table-row'
    ? 'none'
    : 'table-row';
}

</script>

</body>
</html>