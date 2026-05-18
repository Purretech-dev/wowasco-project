<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

function clean($value){
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table){
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column){
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function getCol($conn, $table, $options){
    foreach ($options as $col) {
        if (columnExists($conn, $table, $col)) return $col;
    }
    return null;
}

function classifyNRW($percent){
    if ($percent <= 10) return ['Efficient', 'good'];
    if ($percent <= 20) return ['Moderate', 'warning'];
    if ($percent <= 30) return ['High Loss', 'danger'];
    return ['Critical NRW', 'critical'];
}

if (!tableExists($conn, 'meters')) {
    die("<div class='page-content'>Meters table was not found.</div>");
}

if (!tableExists($conn, 'pumped_volume_entries')) {
    die("<div class='page-content'>Pumped volume table was not found.</div>");
}

if (!tableExists($conn, 'meter_readings')) {
    die("<div class='page-content'>Meter readings table was not found.</div>");
}

/* ================= COLUMN MAPPING ================= */

$serialCol = getCol($conn, 'meters', ['serial_number','meter_serial','serial','meter_no']);
$zoneCol = getCol($conn, 'meters', ['zone','zone_name','location']);
$statusCol = getCol($conn, 'meters', ['status','meter_status']);
$customerCol = getCol($conn, 'meters', ['customer_name','name']);
$customerTypeCol = getCol($conn, 'meters', ['customer_type','type']);

if (!$serialCol) {
    die("<div class='page-content'>Meter serial column was not found.</div>");
}

/* ================= FILTERS ================= */

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$zone = $_GET['zone'] ?? '';
$customer_type = $_GET['customer_type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$hasFilter = (
    $from !== date('Y-m-01') ||
    $to !== date('Y-m-t') ||
    $zone !== '' ||
    $customer_type !== '' ||
    $status !== '' ||
    $search !== ''
);

$where = "WHERE 1=1";

if ($zone !== '' && $zoneCol) {
    $safeZone = $conn->real_escape_string($zone);
    $where .= " AND m.`$zoneCol` = '$safeZone'";
}

if ($customer_type !== '' && $customerTypeCol) {
    $safeType = $conn->real_escape_string($customer_type);
    $where .= " AND m.`$customerTypeCol` = '$safeType'";
}

if ($status !== '' && $statusCol) {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND m.`$statusCol` = '$safeStatus'";
}

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $parts = [];

    foreach ([$serialCol, $customerCol, $zoneCol, $customerTypeCol, $statusCol] as $col) {
        if ($col) {
            $parts[] = "m.`$col` LIKE '%$safeSearch%'";
        }
    }

    if (!empty($parts)) {
        $where .= " AND (" . implode(" OR ", $parts) . ")";
    }
}

$safeFrom = $conn->real_escape_string($from);
$safeTo = $conn->real_escape_string($to);

/* ================= MAIN REPORT DATA ================= */

$sql = "
SELECT
    m.id AS meter_id,
    m.`$serialCol` AS serial_number,
    " . ($customerCol ? "m.`$customerCol`" : "''") . " AS customer_name,
    " . ($zoneCol ? "m.`$zoneCol`" : "''") . " AS zone_name,
    " . ($customerTypeCol ? "m.`$customerTypeCol`" : "''") . " AS customer_type,
    " . ($statusCol ? "m.`$statusCol`" : "''") . " AS meter_status,

    COALESCE(p.entry_count,0) AS entry_count,
    COALESCE(p.produced_volume,0) AS produced_volume,
    COALESCE(p.last_pumped_date,'') AS last_pumped_date,
    COALESCE(r.billed_volume,0) AS billed_volume,

    GREATEST(COALESCE(p.produced_volume,0) - COALESCE(r.billed_volume,0),0) AS nrw_volume,

    CASE
        WHEN COALESCE(p.produced_volume,0) > 0
        THEN ROUND((GREATEST(COALESCE(p.produced_volume,0) - COALESCE(r.billed_volume,0),0) / COALESCE(p.produced_volume,0)) * 100, 2)
        ELSE 0
    END AS nrw_percent,

    CASE
        WHEN COALESCE(p.produced_volume,0) > 0
        THEN ROUND((COALESCE(r.billed_volume,0) / COALESCE(p.produced_volume,0)) * 100, 2)
        ELSE 0
    END AS revenue_water_percent

FROM meters m

LEFT JOIN (
    SELECT
        meter_id,
        COUNT(id) AS entry_count,
        SUM(volume_m3) AS produced_volume,
        MAX(pumped_date) AS last_pumped_date
    FROM pumped_volume_entries
    WHERE pumped_date BETWEEN '$safeFrom' AND '$safeTo'
    GROUP BY meter_id
) p ON p.meter_id = m.id

LEFT JOIN (
    SELECT
        meter_id,
        SUM(consumption) AS billed_volume
    FROM meter_readings
    WHERE DATE(reading_date) BETWEEN '$safeFrom' AND '$safeTo'
    GROUP BY meter_id
) r ON r.meter_id = m.id

$where

ORDER BY produced_volume DESC, nrw_percent DESC
";

$reportRows = $conn->query($sql);

/* ================= KPI CALCULATIONS ================= */

$totalMeters = 0;
$totalProduced = 0;
$totalBilled = 0;
$totalNRW = 0;
$totalEntries = 0;
$activeMeters = 0;

$zoneMap = [];
$typeMap = [];
$riskFlags = [];

$rows = [];

if ($reportRows) {
    while($row = $reportRows->fetch_assoc()) {
        $rows[] = $row;

        $totalMeters++;
        $totalProduced += (float)$row['produced_volume'];
        $totalBilled += (float)$row['billed_volume'];
        $totalNRW += (float)$row['nrw_volume'];
        $totalEntries += (int)$row['entry_count'];

        if (strtolower(trim($row['meter_status'])) === 'active') {
            $activeMeters++;
        }

        $z = $row['zone_name'] ?: 'Unassigned';
        $t = $row['customer_type'] ?: 'Unknown';

        if (!isset($zoneMap[$z])) {
            $zoneMap[$z] = ['label'=>$z, 'meters'=>0, 'produced'=>0, 'billed'=>0, 'nrw'=>0];
        }

        $zoneMap[$z]['meters']++;
        $zoneMap[$z]['produced'] += (float)$row['produced_volume'];
        $zoneMap[$z]['billed'] += (float)$row['billed_volume'];
        $zoneMap[$z]['nrw'] += (float)$row['nrw_volume'];

        if (!isset($typeMap[$t])) {
            $typeMap[$t] = ['label'=>$t, 'meters'=>0, 'produced'=>0, 'billed'=>0, 'nrw'=>0];
        }

        $typeMap[$t]['meters']++;
        $typeMap[$t]['produced'] += (float)$row['produced_volume'];
        $typeMap[$t]['billed'] += (float)$row['billed_volume'];
        $typeMap[$t]['nrw'] += (float)$row['nrw_volume'];
    }
}

$nrwPercent = $totalProduced > 0 ? round(($totalNRW / $totalProduced) * 100, 2) : 0;
$revenueWaterPercent = $totalProduced > 0 ? round(($totalBilled / $totalProduced) * 100, 2) : 0;
$avgProduction = $totalMeters > 0 ? round($totalProduced / $totalMeters, 2) : 0;

[$nrwClass, $nrwClassName] = classifyNRW($nrwPercent);

$zoneData = array_values($zoneMap);
$typeData = array_values($typeMap);

usort($zoneData, fn($a,$b) => $b['produced'] <=> $a['produced']);
usort($typeData, fn($a,$b) => $b['produced'] <=> $a['produced']);

$topZone = $zoneData[0]['label'] ?? 'N/A';
$topType = $typeData[0]['label'] ?? 'N/A';

if ($nrwPercent > 30) $riskFlags[] = "Critical NRW detected at {$nrwPercent}% of produced water.";
if ($nrwPercent > 20 && $nrwPercent <= 30) $riskFlags[] = "High water loss detected at {$nrwPercent}% of produced water.";
if ($totalProduced <= 0) $riskFlags[] = "No pumped production volume recorded for the selected period.";
if ($totalBilled <= 0 && $totalProduced > 0) $riskFlags[] = "Production exists but billed consumption is missing.";
if ($totalNRW > 0) $riskFlags[] = number_format($totalNRW,2) . " m³ is currently classified as non-revenue water.";
if ($activeMeters == 0) $riskFlags[] = "No active meters found in the selected scope.";
if (empty($riskFlags)) $riskFlags[] = "No major production reporting risk detected.";

/* ================= DROPDOWNS ================= */

$zones = $zoneCol ? $conn->query("SELECT DISTINCT `$zoneCol` AS v FROM meters WHERE `$zoneCol` IS NOT NULL AND `$zoneCol`!='' ORDER BY `$zoneCol` ASC") : null;
$statuses = $statusCol ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM meters WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC") : null;
$types = $customerTypeCol ? $conn->query("SELECT DISTINCT `$customerTypeCol` AS v FROM meters WHERE `$customerTypeCol` IS NOT NULL AND `$customerTypeCol`!='' ORDER BY `$customerTypeCol` ASC") : null;

?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Production Reports</h2>
            <p>Filtered enterprise reports for pumped volume, billed consumption, NRW, zones and customer categories.</p>
        </div>

        <div class="header-actions">
            <div class="dropdown">
                <button type="button" class="download-btn">Download Report ▾</button>
                <div class="dropdown-content">
                    <a href="#" onclick="triggerExport('excel'); return false;">Excel</a>
                    <a href="#" onclick="triggerExport('pdf'); return false;">PDF</a>
                </div>
            </div>

            <button type="button" onclick="printFilteredReport()" class="print-btn">Print Data</button>
        </div>
    </div>

    <form method="GET" class="filter-card no-print">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'modules/reports/production_reports.php') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div>
            <label>Zone</label>
            <select name="zone">
                <option value="">All Zones</option>
                <?php if ($zones): while($z = $zones->fetch_assoc()): ?>
                    <option value="<?= clean($z['v']) ?>" <?= $zone === $z['v'] ? 'selected' : '' ?>>
                        <?= clean($z['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div>
            <label>Customer Type</label>
            <select name="customer_type">
                <option value="">All Customer Types</option>
                <?php if ($types): while($t = $types->fetch_assoc()): ?>
                    <option value="<?= clean($t['v']) ?>" <?= $customer_type === $t['v'] ? 'selected' : '' ?>>
                        <?= clean($t['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php if ($statuses): while($s = $statuses->fetch_assoc()): ?>
                    <option value="<?= clean($s['v']) ?>" <?= $status === $s['v'] ? 'selected' : '' ?>>
                        <?= clean($s['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Serial, customer, zone...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=modules/reports/production_reports.php">Reset</a>
        <button type="button" onclick="printFilteredReport()" class="print-btn">Print Data</button>
    </form>

    <?php if (!$hasFilter): ?>
        <div class="filter-warning no-print">
            Default report shows the current month. Apply filters before downloading or printing a specific report.
        </div>
    <?php endif; ?>

    <div id="printArea">

        <div class="table-panel">
            <div class="table-toolbar no-print">
                <div class="table-count">
                    Production Records:
                    <strong><?= number_format(count($rows)) ?></strong>
                </div>

                <div class="table-actions">
                    <div class="dropdown">
                        <button type="button" class="download-btn">Download Report</button>
                        <div class="dropdown-content">
                            <a href="#" onclick="triggerExport('excel'); return false;">Excel</a>
                            <a href="#" onclick="triggerExport('pdf'); return false;">PDF</a>
                        </div>
                    </div>
                </div>
            </div>

            <table id="productionTable" class="display">
                <thead>
                    <tr>
                        <th>Meter Serial</th>
                        <th>Customer</th>
                        <th>Zone</th>
                        <th>Customer Type</th>
                        <th>Status</th>
                        <th>Entries</th>
                        <th>Produced</th>
                        <th>Billed</th>
                        <th>NRW</th>
                        <th>NRW %</th>
                        <th>Revenue Water %</th>
                        <th>Last Entry</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><?= clean($r['serial_number']) ?></td>
                            <td><?= clean($r['customer_name'] ?: 'N/A') ?></td>
                            <td><?= clean($r['zone_name'] ?: 'Unassigned') ?></td>
                            <td><?= clean($r['customer_type'] ?: 'Unknown') ?></td>
                            <td><?= clean($r['meter_status'] ?: 'N/A') ?></td>
                            <td><?= number_format($r['entry_count']) ?></td>
                            <td><?= number_format($r['produced_volume'],2) ?> m³</td>
                            <td><?= number_format($r['billed_volume'],2) ?> m³</td>
                            <td><?= number_format($r['nrw_volume'],2) ?> m³</td>
                            <td><?= number_format($r['nrw_percent'],2) ?>%</td>
                            <td><?= number_format($r['revenue_water_percent'],2) ?>%</td>
                            <td><?= clean($r['last_pumped_date'] ?: 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>

    </div>

</div>

<style>
.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:20px;
    background:#f4f7fb;
    min-height:calc(100vh - 135px);
    font-family:Arial, sans-serif;
}

.module-header,
.filter-card,
.kpi-card,
.report-card,
.report-title{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
}

.module-header{
    padding:18px 20px;
    margin-bottom:18px;
    border-left:4px solid #0a2a43;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
}

.module-header h2{
    margin:0;
    color:#0a2a43;
    font-size:20px;
}

.module-header p{
    margin:6px 0 0;
    color:#64748b;
    font-size:14px;
}

.header-actions{
    display:flex;
    gap:8px;
    align-items:center;
}

.print-btn,
.download-btn,
.filter-card button,
.clear-btn{
    background:#0a2a43;
    color:#fff;
    border:none;
    padding:9px 13px;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
    text-decoration:none;
}

.clear-btn{
    background:#64748b;
}

.dropdown{
    position:relative;
    display:inline-block;
}

.dropdown-content{
    display:none;
    position:absolute;
    right:0;
    background:#fff;
    min-width:140px;
    border:1px solid #e5e7eb;
    border-radius:8px;
    box-shadow:0 6px 18px rgba(0,0,0,0.12);
    z-index:20;
}

.dropdown-content a{
    display:block;
    padding:10px 12px;
    text-decoration:none;
    color:#334155;
    font-size:13px;
}

.dropdown:hover .dropdown-content{
    display:block;
}

.filter-card{
    padding:14px;
    margin-bottom:18px;
    display:flex;
    align-items:end;
    gap:10px;
    flex-wrap:wrap;
}

.filter-card label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:#334155;
    margin-bottom:5px;
}

.filter-card input,
.filter-card select{
    padding:9px;
    border:1px solid #d1d5db;
    border-radius:6px;
    min-width:150px;
}

.search-box input{
    min-width:260px;
}

.filter-warning{
    background:#fff;
    border-left:4px solid #0a2a43;
    color:#334155;
    padding:12px 15px;
    border-radius:8px;
    margin-bottom:18px;
    font-size:14px;
}

.report-title{
    padding:14px 18px;
    margin-bottom:18px;
}

.report-title h3{
    margin:0;
    color:#0a2a43;
}

.report-title p{
    margin:5px 0 0;
    color:#64748b;
    font-size:13px;
}

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:12px;
    margin-bottom:18px;
}

.kpi-card{
    padding:15px;
}

.kpi-card span{
    display:block;
    color:#64748b;
    font-size:13px;
    margin-bottom:8px;
}

.kpi-card strong{
    display:block;
    color:#0a2a43;
    font-size:22px;
    margin-bottom:5px;
}

.kpi-card small{
    color:#64748b;
}

.report-grid{
    display:grid;
    gap:18px;
    margin-bottom:18px;
}

.report-grid.three{
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
}

.report-card{
    padding:18px;
    margin-bottom:18px;
    overflow-x:auto;
}

.table-panel{
    margin-bottom:18px;
    overflow-x:auto;
}

.table-panel h3{
    margin:0 0 14px;
    color:#0a2a43;
    font-size:17px;
}

.report-card h3{
    margin:0 0 14px;
    color:#0a2a43;
    font-size:17px;
}

.metric-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:9px 0;
    border-bottom:1px solid #e5e7eb;
    font-size:14px;
}

.metric-small{
    display:block;
    color:#64748b;
    font-size:12px;
    margin-bottom:7px;
}

.risk-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:10px;
}

.risk-item{
    background:#f8fafc;
    border-left:4px solid #0a2a43;
    border-radius:8px;
    padding:12px;
    color:#334155;
    font-size:14px;
}

.good{
    color:#15803d !important;
}

.warning{
    color:#a16207 !important;
}

.danger{
    color:#b91c1c !important;
}

.critical{
    color:#7f1d1d !important;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

th{
    background:#f8fafc;
    color:#334155;
    text-align:left;
    padding:10px;
    border-bottom:1px solid #e5e7eb;
}

td{
    padding:10px;
    border-bottom:1px solid #e5e7eb;
    color:#334155;
}

.dt-button{
    display:none !important;
}

.module-header,
.filter-warning{
    display:none !important;
}

.table-panel{
    background:#fff;
    border:1px solid #dbe3ee;
    border-radius:10px;
    padding:16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.table-toolbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    margin-bottom:14px;
    flex-wrap:wrap;
}

.table-count{
    color:#475569;
    font-size:14px;
}

.table-count strong{
    color:#0a2a43;
    font-size:18px;
}

.table-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

th{
    background:#0a2a43 !important;
    color:#fff !important;
    border-bottom:1px solid #0a2a43 !important;
    white-space:nowrap;
}

.dt-container .dt-layout-row:first-child,
.dt-container .dt-layout-row:first-child .dt-layout-cell,
.dt-container .dt-length,
.dt-container .dt-search,
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}

.dt-container .dt-search,
.dataTables_filter{
    margin-left:auto;
}

@media(max-width:900px){
    .page-content{
        margin-left:0;
    }

    .module-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .filter-card{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-card input,
    .filter-card select,
    .filter-card button,
    .clear-btn{
        width:100%;
        box-sizing:border-box;
    }

    .table-toolbar,
    .table-actions{
        align-items:stretch;
        flex-direction:column;
        width:100%;
    }

    .download-btn,
    .print-btn{
        width:100%;
    }
}

.table-actions{
    flex-wrap:nowrap;
}

.table-actions .download-btn,
.table-actions .print-btn{
    width:auto;
    flex:0 0 auto;
}

.dt-container .dt-layout-row:first-child{
    display:flex !important;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    margin:12px 0;
}

.dt-container .dt-layout-row:first-child .dt-layout-cell{
    display:flex !important;
    align-items:center;
    width:auto !important;
}

.dt-container .dt-layout-row:first-child .dt-layout-cell:last-child{
    justify-content:flex-end;
    margin-left:auto;
}

.dt-container .dt-length,
.dataTables_wrapper .dataTables_length{
    display:flex !important;
    align-items:center;
    gap:8px;
    float:none !important;
    margin:0 !important;
    width:auto !important;
}

.dt-container .dt-search,
.dataTables_wrapper .dataTables_filter{
    display:none !important;
}

.dt-container .dt-search label,
.dataTables_filter label{
    white-space:nowrap;
}

.dt-search input,
.dataTables_filter input{
    width:220px;
    max-width:100%;
}

@media(max-width:900px){
    .table-actions{
        flex-direction:row;
        flex-wrap:wrap;
        width:100%;
    }

    .dt-container .dt-layout-row:first-child,
    .dt-container .dt-layout-row:first-child .dt-layout-cell,
    .dt-container .dt-length,
    .dt-container .dt-search,
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter{
        width:100% !important;
        margin-left:0 !important;
        justify-content:flex-start;
    }

    .dt-search input,
    .dataTables_filter input{
        width:100%;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>

<script>
let productionTable;

function getProductionTitle(){
    return 'WOWASCO Production Report';
}

function getProductionFilterSummary(){
    const filteredCount = productionTable
        ? productionTable.rows({ search:'applied' }).count()
        : <?= count($rows) ?>;

    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'Filters: From <?= clean($from) ?> | To <?= clean($to) ?> | Zone <?= clean($zone ?: 'All') ?> | Customer Type <?= clean($customer_type ?: 'All') ?> | Status <?= clean($status ?: 'All') ?> | Search <?= clean($search ?: 'None') ?>'
    ].join('\n');
}

function getProductionFilename(){
    return getProductionTitle()
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');
}

$(document).ready(function(){
    productionTable = $('#productionTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        paging: true,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: getProductionTitle,
                filename: getProductionFilename,
                messageTop: getProductionFilterSummary,
                exportOptions: {
                    columns: ':visible',
                    modifier: {
                        search: 'applied',
                        order: 'applied',
                        page: 'all'
                    }
                }
            },
            {
                extend: 'pdfHtml5',
                title: getProductionTitle,
                filename: getProductionFilename,
                messageTop: getProductionFilterSummary,
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible',
                    modifier: {
                        search: 'applied',
                        order: 'applied',
                        page: 'all'
                    }
                }
            }
        ],
        language: {
            search: "",
            searchPlaceholder: "",
            lengthMenu: "Show _MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ filtered records",
            emptyTable: "No production report records found."
        }
    });
});

function triggerExport(type){
    if(type === 'excel'){
        productionTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        productionTable.button('.buttons-pdf').trigger();
    }
}

function printFilteredReport(){
    let headers = [];
    $('#productionTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    let rows = productionTable.rows({
        search: 'applied',
        order: 'applied',
        page: 'all'
    }).data().toArray();

    let tableHtml = `
        <table>
            <thead>
                <tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>
            </thead>
            <tbody>
                ${
                    rows.length > 0
                    ? rows.map(row => `
                        <tr>
                            ${row.map(cell => `<td>${$('<div>').html(cell).text()}</td>`).join('')}
                        </tr>
                    `).join('')
                    : `<tr><td colspan="${headers.length}">No records found.</td></tr>`
                }
            </tbody>
        </table>
    `;

    let printWindow = window.open('', '_blank', 'width=1200,height=800');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>WOWASCO Production Report</title>
            <style>
                body{
                    font-family:Arial, sans-serif;
                    padding:20px;
                    color:#1f2937;
                }

                h3{
                    color:#0a2a43;
                    margin-bottom:5px;
                }

                .report-title{
                    border-bottom:2px solid #0a2a43;
                    padding-bottom:10px;
                    margin-bottom:15px;
                }

                .report-title p{
                    font-size:12px;
                    color:#475569;
                    margin:4px 0;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                    font-size:10px;
                }

                th,td{
                    border:1px solid #d1d5db;
                    padding:6px;
                    text-align:left;
                    vertical-align:top;
                }

                th{
                    background:#f1f5f9;
                    color:#0a2a43;
                }
            </style>
        </head>
        <body>
            <div class="report-title">
                <h3>WOWASCO Production Report</h3>
                <p>${getProductionFilterSummary().replace(/\n/g, '<br>')}</p>
            </div>
            ${tableHtml}
        </body>
        </html>
    `);

    printWindow.document.close();

    printWindow.onload = function(){
        printWindow.focus();
        printWindow.print();
    };
}
</script>
