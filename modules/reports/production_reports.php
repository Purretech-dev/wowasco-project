<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value){
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
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

function money($amount){
    return "KSh " . number_format((float)$amount, 2);
}

/* ================= TABLE DETECTION ================= */

$pumpedTable = '';

foreach (['pumped_volumes', 'pumped_volume', 'water_production', 'production_volumes'] as $tbl) {
    if (tableExists($conn, $tbl)) {
        $pumpedTable = $tbl;
        break;
    }
}

$billTable = '';

foreach (['bills', 'billing', 'customer_bills', 'meter_bills'] as $tbl) {
    if (tableExists($conn, $tbl)) {
        $billTable = $tbl;
        break;
    }
}

if ($pumpedTable === '') {
    die("<div class='page-content'>No pumped volume table was found. Expected table: pumped_volumes, pumped_volume, water_production or production_volumes.</div>");
}

/* ================= COLUMN MAPPING ================= */

$pumpDateCol = getCol($conn, $pumpedTable, ['record_date','date_recorded','pumping_date','production_date','created_at']);
$pumpSourceCol = getCol($conn, $pumpedTable, ['source','source_name','water_source']);
$pumpZoneCol = getCol($conn, $pumpedTable, ['zone','zone_name']);
$pumpVolumeCol = getCol($conn, $pumpedTable, ['volume','pumped_volume','quantity','total_volume','litres','cubic_meters']);
$pumpStatusCol = getCol($conn, $pumpedTable, ['status']);

$billDateCol = $billTable ? getCol($conn, $billTable, ['billing_date','bill_date','date_recorded','created_at']) : null;
$billConsumptionCol = $billTable ? getCol($conn, $billTable, ['consumption','units_consumed','consumed_units','volume_consumed','billed_volume']) : null;
$billAmountCol = $billTable ? getCol($conn, $billTable, ['amount','bill_amount','total_amount']) : null;
$billMeterCol = $billTable ? getCol($conn, $billTable, ['meter_serial','serial_number','meter_no']) : null;
$billZoneCol = $billTable ? getCol($conn, $billTable, ['zone','zone_name']) : null;

$meterSerialCol = tableExists($conn, 'meters') ? getCol($conn, 'meters', ['serial_number','meter_serial','serial','meter_no']) : null;
$meterZoneCol = tableExists($conn, 'meters') ? getCol($conn, 'meters', ['zone','zone_name','location']) : null;

if (!$pumpVolumeCol) {
    die("<div class='page-content'>Pumped volume column was not found in $pumpedTable.</div>");
}

/* ================= FILTERS ================= */

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$zone = $_GET['zone'] ?? '';
$source = $_GET['source'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$hasFilter = ($from !== '' || $to !== '' || $zone !== '' || $source !== '' || $status !== '' || $search !== '');

$where = "WHERE 1=1";

if ($from !== '' && $pumpDateCol) {
    $safeFrom = $conn->real_escape_string($from);
    $where .= " AND DATE(`$pumpDateCol`) >= '$safeFrom'";
}

if ($to !== '' && $pumpDateCol) {
    $safeTo = $conn->real_escape_string($to);
    $where .= " AND DATE(`$pumpDateCol`) <= '$safeTo'";
}

if ($zone !== '' && $pumpZoneCol) {
    $safeZone = $conn->real_escape_string($zone);
    $where .= " AND `$pumpZoneCol` = '$safeZone'";
}

if ($source !== '' && $pumpSourceCol) {
    $safeSource = $conn->real_escape_string($source);
    $where .= " AND `$pumpSourceCol` = '$safeSource'";
}

if ($status !== '' && $pumpStatusCol) {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND `$pumpStatusCol` = '$safeStatus'";
}

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $searchParts = [];

    foreach ([$pumpSourceCol, $pumpZoneCol, $pumpStatusCol] as $col) {
        if ($col) {
            $searchParts[] = "`$col` LIKE '%$safeSearch%'";
        }
    }

    if (!empty($searchParts)) {
        $where .= " AND (" . implode(" OR ", $searchParts) . ")";
    }
}

/* ================= PUMPED DATA ================= */

$pumpedQuery = "
    SELECT *
    FROM `$pumpedTable`
    $where
    ORDER BY " . ($pumpDateCol ? "`$pumpDateCol` DESC" : "id DESC") . "
";

$pumpedResult = $conn->query($pumpedQuery);

/* ================= REPORT ROWS ================= */

$reportRows = [];

$totalPumped = 0;
$totalBilledConsumption = 0;
$totalRevenue = 0;

if ($pumpedResult) {
    while ($p = $pumpedResult->fetch_assoc()) {

        $recordDate = $pumpDateCol ? ($p[$pumpDateCol] ?? '') : '';
        $recordDay = $recordDate ? date('Y-m-d', strtotime($recordDate)) : '';
        $recordSource = $pumpSourceCol ? ($p[$pumpSourceCol] ?? '') : '';
        $recordZone = $pumpZoneCol ? ($p[$pumpZoneCol] ?? '') : '';
        $recordStatus = $pumpStatusCol ? ($p[$pumpStatusCol] ?? '') : '';
        $pumpedVolume = (float)($p[$pumpVolumeCol] ?? 0);

        $billedConsumption = 0;
        $revenue = 0;

        if ($billTable && $billConsumptionCol) {

            $billWhere = "WHERE 1=1";

            if ($recordDay && $billDateCol) {
                $safeDay = $conn->real_escape_string($recordDay);
                $billWhere .= " AND DATE(`$billDateCol`) = '$safeDay'";
            }

            if ($recordZone !== '') {
                $safeRecordZone = $conn->real_escape_string($recordZone);

                if ($billZoneCol) {
                    $billWhere .= " AND `$billZoneCol` = '$safeRecordZone'";
                } elseif ($billMeterCol && $meterSerialCol && $meterZoneCol && tableExists($conn, 'meters')) {
                    $billWhere .= " AND `$billMeterCol` IN (
                        SELECT `$meterSerialCol`
                        FROM meters
                        WHERE `$meterZoneCol` = '$safeRecordZone'
                    )";
                }
            }

            $billSql = "
                SELECT 
                    SUM(`$billConsumptionCol`) AS total_consumption
                    " . ($billAmountCol ? ", SUM(`$billAmountCol`) AS total_revenue" : ", 0 AS total_revenue") . "
                FROM `$billTable`
                $billWhere
            ";

            $billRes = $conn->query($billSql);

            if ($billRes) {
                $b = $billRes->fetch_assoc();
                $billedConsumption = (float)($b['total_consumption'] ?? 0);
                $revenue = (float)($b['total_revenue'] ?? 0);
            }
        }

        $nrwVolume = $pumpedVolume - $billedConsumption;
        $nrwPercent = $pumpedVolume > 0 ? round(($nrwVolume / $pumpedVolume) * 100, 2) : 0;
        $efficiency = $pumpedVolume > 0 ? round(($billedConsumption / $pumpedVolume) * 100, 2) : 0;

        $totalPumped += $pumpedVolume;
        $totalBilledConsumption += $billedConsumption;
        $totalRevenue += $revenue;

        $reportRows[] = [
            'date' => $recordDay,
            'source' => $recordSource,
            'zone' => $recordZone,
            'pumped' => $pumpedVolume,
            'billed' => $billedConsumption,
            'nrw_volume' => $nrwVolume,
            'nrw_percent' => $nrwPercent,
            'efficiency' => $efficiency,
            'revenue' => $revenue,
            'status' => $recordStatus
        ];
    }
}

$totalNRW = $totalPumped - $totalBilledConsumption;
$overallNRWPercent = $totalPumped > 0 ? round(($totalNRW / $totalPumped) * 100, 2) : 0;
$overallEfficiency = $totalPumped > 0 ? round(($totalBilledConsumption / $totalPumped) * 100, 2) : 0;
$revenuePerUnit = $totalPumped > 0 ? round($totalRevenue / $totalPumped, 2) : 0;

/* ================= FILTER VALUES ================= */

$zones = $pumpZoneCol ? $conn->query("SELECT DISTINCT `$pumpZoneCol` AS v FROM `$pumpedTable` WHERE `$pumpZoneCol` IS NOT NULL AND `$pumpZoneCol`!='' ORDER BY `$pumpZoneCol` ASC") : null;
$sources = $pumpSourceCol ? $conn->query("SELECT DISTINCT `$pumpSourceCol` AS v FROM `$pumpedTable` WHERE `$pumpSourceCol` IS NOT NULL AND `$pumpSourceCol`!='' ORDER BY `$pumpSourceCol` ASC") : null;
$statuses = $pumpStatusCol ? $conn->query("SELECT DISTINCT `$pumpStatusCol` AS v FROM `$pumpedTable` WHERE `$pumpStatusCol` IS NOT NULL AND `$pumpStatusCol`!='' ORDER BY `$pumpStatusCol` ASC") : null;

/* ================= BREAKDOWNS ================= */

$zoneBreakdown = [];
$sourceBreakdown = [];

foreach ($reportRows as $r) {
    $z = $r['zone'] ?: 'Unspecified';
    $s = $r['source'] ?: 'Unspecified';

    if (!isset($zoneBreakdown[$z])) {
        $zoneBreakdown[$z] = ['pumped' => 0, 'billed' => 0, 'nrw' => 0];
    }

    if (!isset($sourceBreakdown[$s])) {
        $sourceBreakdown[$s] = ['pumped' => 0, 'billed' => 0, 'nrw' => 0];
    }

    $zoneBreakdown[$z]['pumped'] += $r['pumped'];
    $zoneBreakdown[$z]['billed'] += $r['billed'];
    $zoneBreakdown[$z]['nrw'] += $r['nrw_volume'];

    $sourceBreakdown[$s]['pumped'] += $r['pumped'];
    $sourceBreakdown[$s]['billed'] += $r['billed'];
    $sourceBreakdown[$s]['nrw'] += $r['nrw_volume'];
}

uasort($zoneBreakdown, fn($a, $b) => $b['nrw'] <=> $a['nrw']);
uasort($sourceBreakdown, fn($a, $b) => $b['nrw'] <=> $a['nrw']);

/* ================= RISK FLAGS ================= */

$riskFlags = [];

if ($overallNRWPercent >= 40) {
    $riskFlags[] = "Critical NRW level detected at $overallNRWPercent%. Immediate leakage, illegal connection, metering and billing audit is recommended.";
} elseif ($overallNRWPercent >= 25) {
    $riskFlags[] = "High NRW level detected at $overallNRWPercent%. Investigate distribution losses and billing gaps.";
} else {
    $riskFlags[] = "NRW level is currently within a manageable range based on available data.";
}

if ($totalPumped > 0 && $totalBilledConsumption <= 0) {
    $riskFlags[] = "Pumped volume exists but billed consumption is missing. Check billing/consumption records.";
}

if ($totalRevenue <= 0 && $totalBilledConsumption > 0) {
    $riskFlags[] = "Consumption is recorded but revenue values are missing. Check billing amount records.";
}

if (count($reportRows) === 0) {
    $riskFlags[] = "No production records found for the selected filters.";
}

?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Production & Non-Revenue Water Reports</h2>
            <p>Enterprise analysis of pumped volumes, billed consumption, NRW losses, revenue efficiency and water accountability.</p>
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
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'reports/production_reports') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div>
            <label>Source</label>
            <select name="source">
                <option value="">All Sources</option>
                <?php if ($sources): while($s = $sources->fetch_assoc()): ?>
                    <option value="<?= clean($s['v']) ?>" <?= $source === $s['v'] ? 'selected' : '' ?>>
                        <?= clean($s['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
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

        <?php if ($pumpStatusCol): ?>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php if ($statuses): while($st = $statuses->fetch_assoc()): ?>
                        <option value="<?= clean($st['v']) ?>" <?= $status === $st['v'] ? 'selected' : '' ?>>
                            <?= clean($st['v']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Source, zone, status...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=reports/production_reports">Reset</a>
    </form>

    <?php if (!$hasFilter): ?>
        <div class="filter-warning no-print">
            Please select at least one filter before downloading or printing.
        </div>
    <?php endif; ?>

    <div id="printArea">

        <div class="report-title">
            <h3>WOWASCO Production & Non-Revenue Water Report</h3>
            <p>
                Generated on <?= date('Y-m-d H:i:s') ?> |
                Filtered Records: <?= number_format(count($reportRows)) ?>
            </p>
            <p>
                Filters:
                From: <?= clean($from ?: 'Any') ?> |
                To: <?= clean($to ?: 'Any') ?> |
                Source: <?= clean($source ?: 'All') ?> |
                Zone: <?= clean($zone ?: 'All') ?> |
                Status: <?= clean($status ?: 'All') ?> |
                Search: <?= clean($search ?: 'None') ?>
            </p>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Total Pumped Volume</span>
                <strong><?= number_format($totalPumped, 2) ?></strong>
                <small>All filtered production</small>
            </div>

            <div class="kpi-card">
                <span>Billed Consumption</span>
                <strong><?= number_format($totalBilledConsumption, 2) ?></strong>
                <small>Matched billed volume</small>
            </div>

            <div class="kpi-card">
                <span>NRW Volume</span>
                <strong><?= number_format($totalNRW, 2) ?></strong>
                <small>Pumped minus billed</small>
            </div>

            <div class="kpi-card">
                <span>NRW Percentage</span>
                <strong><?= number_format($overallNRWPercent, 2) ?>%</strong>
                <small>Loss indicator</small>
            </div>

            <div class="kpi-card">
                <span>Billing Efficiency</span>
                <strong><?= number_format($overallEfficiency, 2) ?>%</strong>
                <small>Billed vs pumped</small>
            </div>

            <div class="kpi-card">
                <span>Total Revenue</span>
                <strong><?= money($totalRevenue) ?></strong>
                <small>From matched bills</small>
            </div>

            <div class="kpi-card">
                <span>Revenue Per Unit</span>
                <strong><?= money($revenuePerUnit) ?></strong>
                <small>Revenue / pumped volume</small>
            </div>

            <div class="kpi-card">
                <span>Report Records</span>
                <strong><?= number_format(count($reportRows)) ?></strong>
                <small>Filtered production entries</small>
            </div>
        </div>

        <div class="report-grid two">
            <div class="report-card">
                <h3>NRW by Zone</h3>

                <?php if (!empty($zoneBreakdown)): ?>
                    <?php foreach (array_slice($zoneBreakdown, 0, 10, true) as $zName => $v): ?>
                        <?php $pct = $v['pumped'] > 0 ? round(($v['nrw'] / $v['pumped']) * 100, 2) : 0; ?>
                        <div class="metric-row">
                            <span><?= clean($zName) ?></span>
                            <strong><?= number_format($v['nrw'], 2) ?> (<?= $pct ?>%)</strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No zone breakdown available.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>NRW by Source</h3>

                <?php if (!empty($sourceBreakdown)): ?>
                    <?php foreach (array_slice($sourceBreakdown, 0, 10, true) as $sName => $v): ?>
                        <?php $pct = $v['pumped'] > 0 ? round(($v['nrw'] / $v['pumped']) * 100, 2) : 0; ?>
                        <div class="metric-row">
                            <span><?= clean($sName) ?></span>
                            <strong><?= number_format($v['nrw'], 2) ?> (<?= $pct ?>%)</strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No source breakdown available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>NRW Risk Flags</h3>
            <div class="risk-list">
                <?php foreach($riskFlags as $risk): ?>
                    <div class="risk-item"><?= clean($risk) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>Detailed Production & NRW Register</h3>

            <table id="productionTable" class="display">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Zone</th>
                        <th>Pumped Volume</th>
                        <th>Billed Consumption</th>
                        <th>NRW Volume</th>
                        <th>NRW %</th>
                        <th>Billing Efficiency %</th>
                        <th>Revenue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $r): ?>
                        <tr>
                            <td><?= clean($r['date']) ?></td>
                            <td><?= clean($r['source']) ?></td>
                            <td><?= clean($r['zone']) ?></td>
                            <td><?= number_format($r['pumped'], 2) ?></td>
                            <td><?= number_format($r['billed'], 2) ?></td>
                            <td><?= number_format($r['nrw_volume'], 2) ?></td>
                            <td><?= number_format($r['nrw_percent'], 2) ?>%</td>
                            <td><?= number_format($r['efficiency'], 2) ?>%</td>
                            <td><?= money($r['revenue']) ?></td>
                            <td><?= clean($r['status']) ?></td>
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

.report-grid.two{
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
}

.report-card{
    padding:18px;
    margin-bottom:18px;
    overflow-x:auto;
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

.metric-row span{
    color:#475569;
}

.metric-row strong{
    color:#0a2a43;
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

.empty{
    color:#64748b;
    font-size:14px;
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
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>

<script>
const hasFilter = <?= $hasFilter ? 'true' : 'false' ?>;
let productionTable;

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
                title: 'WOWASCO Production and NRW Report',
                filename: 'WOWASCO_Production_NRW_Report',
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
                title: 'WOWASCO Production and NRW Report',
                filename: 'WOWASCO_Production_NRW_Report',
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
            search: "Search filtered data:",
            lengthMenu: "Show _MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ filtered records",
            emptyTable: "No production records found for the selected filters."
        }
    });
});

function triggerExport(type){
    if(!hasFilter){
        alert("Please apply at least one filter before downloading.");
        return;
    }

    if(!confirm("Proceed using the currently filtered data only?")){
        return;
    }

    if(type === 'excel'){
        productionTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        productionTable.button('.buttons-pdf').trigger();
    }
}

function printFilteredReport(){
    if(!hasFilter){
        alert("Please apply at least one filter before printing.");
        return;
    }

    if(!confirm("Print report using the currently filtered data only?")){
        return;
    }

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

    let summaryHtml = document.querySelector('.report-title').outerHTML
        + document.querySelector('.kpi-grid').outerHTML
        + document.querySelector('.risk-list').outerHTML;

    let printWindow = window.open('', '_blank', 'width=1200,height=800');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>WOWASCO Production and NRW Report</title>
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

                .kpi-grid{
                    display:grid;
                    grid-template-columns:repeat(4,1fr);
                    gap:8px;
                    margin-bottom:16px;
                }

                .kpi-card{
                    border:1px solid #d1d5db;
                    padding:8px;
                    border-radius:6px;
                }

                .kpi-card span{
                    display:block;
                    font-size:11px;
                    color:#64748b;
                }

                .kpi-card strong{
                    display:block;
                    font-size:16px;
                    color:#0a2a43;
                }

                .kpi-card small{
                    font-size:10px;
                    color:#64748b;
                }

                .risk-list{
                    display:block;
                    margin-bottom:15px;
                }

                .risk-item{
                    border-left:3px solid #0a2a43;
                    padding:6px 8px;
                    margin-bottom:5px;
                    background:#f8fafc;
                    font-size:12px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                    font-size:11px;
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

                @media print{
                    body{
                        padding:10px;
                    }
                }
            </style>
        </head>
        <body>
            ${summaryHtml}
            <h3>Detailed Production and NRW Register</h3>
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