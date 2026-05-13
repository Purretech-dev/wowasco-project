<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

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

function countRows($conn, $table, $condition = "1=1"){
    if (!tableExists($conn, $table)) return 0;
    $res = $conn->query("SELECT COUNT(*) AS c FROM `$table` WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

function sumColumn($conn, $table, $column, $condition = "1=1"){
    if (!tableExists($conn, $table) || !columnExists($conn, $table, $column)) return 0;
    $res = $conn->query("SELECT SUM(`$column`) AS total FROM `$table` WHERE $condition");
    return $res ? (float)($res->fetch_assoc()['total'] ?? 0) : 0;
}

function money($amount){
    return "KSh " . number_format((float)$amount, 2);
}

function rowValue($row, $col){
    return $col && isset($row[$col]) ? $row[$col] : '';
}

if (!tableExists($conn, 'assets')) {
    die("<div class='page-content'>Assets table was not found.</div>");
}

/* ================= COLUMN MAPPING ================= */

$nameCol = getCol($conn, 'assets', ['asset_name','name']);
$typeCol = getCol($conn, 'assets', ['asset_type','type']);
$subtypeCol = getCol($conn, 'assets', ['subtype','asset_subtype']);
$serialCol = getCol($conn, 'assets', ['serial_number','serial','asset_serial']);
$locationCol = getCol($conn, 'assets', ['asset_location','location','zone']);
$statusCol = getCol($conn, 'assets', ['status','asset_status']);
$valueCol = getCol($conn, 'assets', ['asset_value','value','cost']);
$netValueCol = getCol($conn, 'assets', ['net_value','netvalue','current_value']);
$purchaseDateCol = getCol($conn, 'assets', ['purchase_date','date_purchased','installation_date','created_at']);
$deletedCol = getCol($conn, 'assets', ['is_deleted','deleted']);

$maintenanceTable = tableExists($conn, 'asset_maintenance') ? 'asset_maintenance' : (tableExists($conn, 'assets_maintenance') ? 'assets_maintenance' : '');
$maintenanceStatusCol = $maintenanceTable ? getCol($conn, $maintenanceTable, ['status','maintenance_status']) : null;

/* ================= FILTERS ================= */

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$search = $_GET['search'] ?? '';

$hasFilter = ($from !== '' || $to !== '' || $type !== '' || $status !== '' || $location !== '' || $search !== '');

$where = "WHERE 1=1";

if ($deletedCol) {
    $where .= " AND (`$deletedCol`=0 OR `$deletedCol` IS NULL)";
}

if ($from !== '' && $purchaseDateCol) {
    $safeFrom = $conn->real_escape_string($from);
    $where .= " AND DATE(`$purchaseDateCol`) >= '$safeFrom'";
}

if ($to !== '' && $purchaseDateCol) {
    $safeTo = $conn->real_escape_string($to);
    $where .= " AND DATE(`$purchaseDateCol`) <= '$safeTo'";
}

if ($type !== '' && $typeCol) {
    $safeType = $conn->real_escape_string($type);
    $where .= " AND `$typeCol` = '$safeType'";
}

if ($status !== '' && $statusCol) {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND `$statusCol` = '$safeStatus'";
}

if ($location !== '' && $locationCol) {
    $safeLocation = $conn->real_escape_string($location);
    $where .= " AND `$locationCol` = '$safeLocation'";
}

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $parts = [];

    foreach ([$nameCol, $typeCol, $subtypeCol, $serialCol, $locationCol, $statusCol] as $col) {
        if ($col) {
            $parts[] = "`$col` LIKE '%$safeSearch%'";
        }
    }

    if (!empty($parts)) {
        $where .= " AND (" . implode(" OR ", $parts) . ")";
    }
}

/* ================= DATA ================= */

$assets = $conn->query("SELECT * FROM assets $where ORDER BY id DESC");

$reportRows = [];
$totalFilteredValue = 0;
$totalFilteredNetValue = 0;

if ($assets) {
    while ($a = $assets->fetch_assoc()) {
        $assetValue = (float)rowValue($a, $valueCol);
        $netValue = $netValueCol ? (float)rowValue($a, $netValueCol) : $assetValue;

        $totalFilteredValue += $assetValue;
        $totalFilteredNetValue += $netValue;

        $reportRows[] = [
            'id' => $a['id'] ?? '',
            'name' => rowValue($a, $nameCol),
            'type' => rowValue($a, $typeCol),
            'subtype' => rowValue($a, $subtypeCol),
            'serial' => rowValue($a, $serialCol),
            'location' => rowValue($a, $locationCol),
            'status' => rowValue($a, $statusCol),
            'value' => $assetValue,
            'net_value' => $netValue,
            'purchase_date' => rowValue($a, $purchaseDateCol)
        ];
    }
}

/* ================= KPIs ================= */

$baseCondition = $deletedCol ? "(`$deletedCol`=0 OR `$deletedCol` IS NULL)" : "1=1";

$totalAssets = countRows($conn, 'assets', $baseCondition);
$activeAssets = $statusCol ? countRows($conn, 'assets', "$baseCondition AND `$statusCol`='Active'") : 0;
$inactiveAssets = $statusCol ? countRows($conn, 'assets', "$baseCondition AND `$statusCol`='Inactive'") : 0;
$maintenanceAssets = $statusCol ? countRows($conn, 'assets', "$baseCondition AND `$statusCol` IN ('Maintenance','Under Maintenance')") : 0;

$totalAssetValue = $valueCol ? sumColumn($conn, 'assets', $valueCol, $baseCondition) : 0;
$totalNetAssetValue = $netValueCol ? sumColumn($conn, 'assets', $netValueCol, $baseCondition) : $totalAssetValue;

$oldAssets = 0;
if ($purchaseDateCol) {
    $oldAssets = countRows($conn, 'assets', "$baseCondition AND DATE(`$purchaseDateCol`) <= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)");
}

$openMaintenance = 0;
if ($maintenanceTable && $maintenanceStatusCol) {
    $openMaintenance = countRows($conn, $maintenanceTable, "`$maintenanceStatusCol` IN ('Open','Pending','In Progress')");
}

/* ================= FILTER OPTIONS ================= */

$types = $typeCol ? $conn->query("SELECT DISTINCT `$typeCol` AS v FROM assets WHERE `$typeCol` IS NOT NULL AND `$typeCol`!='' ORDER BY `$typeCol` ASC") : null;
$statuses = $statusCol ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM assets WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC") : null;
$locations = $locationCol ? $conn->query("SELECT DISTINCT `$locationCol` AS v FROM assets WHERE `$locationCol` IS NOT NULL AND `$locationCol`!='' ORDER BY `$locationCol` ASC") : null;

/* ================= BREAKDOWNS ================= */

$byType = [];
$byLocation = [];
$byStatus = [];

foreach ($reportRows as $r) {
    $t = $r['type'] ?: 'Unspecified';
    $l = $r['location'] ?: 'Unspecified';
    $s = $r['status'] ?: 'Unspecified';

    if (!isset($byType[$t])) $byType[$t] = ['count'=>0,'value'=>0,'net'=>0];
    if (!isset($byLocation[$l])) $byLocation[$l] = ['count'=>0,'value'=>0,'net'=>0];
    if (!isset($byStatus[$s])) $byStatus[$s] = ['count'=>0,'value'=>0,'net'=>0];

    $byType[$t]['count']++;
    $byType[$t]['value'] += $r['value'];
    $byType[$t]['net'] += $r['net_value'];

    $byLocation[$l]['count']++;
    $byLocation[$l]['value'] += $r['value'];
    $byLocation[$l]['net'] += $r['net_value'];

    $byStatus[$s]['count']++;
    $byStatus[$s]['value'] += $r['value'];
    $byStatus[$s]['net'] += $r['net_value'];
}

uasort($byType, fn($a,$b) => $b['value'] <=> $a['value']);
uasort($byLocation, fn($a,$b) => $b['value'] <=> $a['value']);
uasort($byStatus, fn($a,$b) => $b['value'] <=> $a['value']);

/* ================= RISK FLAGS ================= */

$riskFlags = [];

if ($inactiveAssets > 0) $riskFlags[] = "$inactiveAssets assets are inactive and should be reviewed.";
if ($maintenanceAssets > 0) $riskFlags[] = "$maintenanceAssets assets are currently marked under maintenance.";
if ($openMaintenance > 0) $riskFlags[] = "$openMaintenance maintenance records are still open.";
if ($oldAssets > 0) $riskFlags[] = "$oldAssets assets are older than 5 years and may need replacement review.";
if ($totalAssets === 0) $riskFlags[] = "No asset records are available.";
if (empty($riskFlags)) $riskFlags[] = "No major asset risk detected from available data.";
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Asset Reports</h2>
            <p>Enterprise asset intelligence covering asset value, net value, status, location, maintenance exposure and replacement risk.</p>
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
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'reports/asset_reports') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div>
            <label>Asset Type</label>
            <select name="type">
                <option value="">All Types</option>
                <?php if ($types): while($t = $types->fetch_assoc()): ?>
                    <option value="<?= clean($t['v']) ?>" <?= $type === $t['v'] ? 'selected' : '' ?>>
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

        <div>
            <label>Location</label>
            <select name="location">
                <option value="">All Locations</option>
                <?php if ($locations): while($l = $locations->fetch_assoc()): ?>
                    <option value="<?= clean($l['v']) ?>" <?= $location === $l['v'] ? 'selected' : '' ?>>
                        <?= clean($l['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Asset name, serial, type, location...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=reports/asset_reports">Reset</a>
    </form>

    <?php if (!$hasFilter): ?>
        <div class="filter-warning no-print">
            Please select at least one filter before downloading or printing.
        </div>
    <?php endif; ?>

    <div id="printArea">

        <div class="report-title">
            <h3>WOWASCO Asset Report</h3>
            <p>
                Generated on <?= date('Y-m-d H:i:s') ?> |
                Filtered Records: <?= number_format(count($reportRows)) ?>
            </p>
            <p>
                Filters:
                From: <?= clean($from ?: 'Any') ?> |
                To: <?= clean($to ?: 'Any') ?> |
                Type: <?= clean($type ?: 'All') ?> |
                Status: <?= clean($status ?: 'All') ?> |
                Location: <?= clean($location ?: 'All') ?> |
                Search: <?= clean($search ?: 'None') ?>
            </p>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card"><span>Total Assets</span><strong><?= number_format($totalAssets) ?></strong><small><?= number_format(count($reportRows)) ?> filtered</small></div>
            <div class="kpi-card"><span>Total Asset Value</span><strong><?= money($totalAssetValue) ?></strong><small>Original value</small></div>
            <div class="kpi-card"><span>Net Asset Value</span><strong><?= money($totalNetAssetValue) ?></strong><small>Current net value</small></div>
            <div class="kpi-card"><span>Active Assets</span><strong><?= number_format($activeAssets) ?></strong><small>Operational assets</small></div>
            <div class="kpi-card"><span>Inactive Assets</span><strong><?= number_format($inactiveAssets) ?></strong><small>Needs review</small></div>
            <div class="kpi-card"><span>Under Maintenance</span><strong><?= number_format($maintenanceAssets) ?></strong><small>Status-based count</small></div>
            <div class="kpi-card"><span>Open Maintenance</span><strong><?= number_format($openMaintenance) ?></strong><small>Maintenance records</small></div>
            <div class="kpi-card"><span>Old Assets</span><strong><?= number_format($oldAssets) ?></strong><small>Over 5 years</small></div>
        </div>

        <div class="report-grid three">

            <div class="report-card">
                <h3>Assets by Type</h3>
                <?php if (!empty($byType)): ?>
                    <?php foreach (array_slice($byType, 0, 10, true) as $label => $v): ?>
                        <div class="metric-row">
                            <span><?= clean($label) ?> (<?= number_format($v['count']) ?>)</span>
                            <strong><?= money($v['value']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No type breakdown available.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Assets by Location</h3>
                <?php if (!empty($byLocation)): ?>
                    <?php foreach (array_slice($byLocation, 0, 10, true) as $label => $v): ?>
                        <div class="metric-row">
                            <span><?= clean($label) ?> (<?= number_format($v['count']) ?>)</span>
                            <strong><?= money($v['value']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No location breakdown available.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Assets by Status</h3>
                <?php if (!empty($byStatus)): ?>
                    <?php foreach (array_slice($byStatus, 0, 10, true) as $label => $v): ?>
                        <div class="metric-row">
                            <span><?= clean($label) ?> (<?= number_format($v['count']) ?>)</span>
                            <strong><?= money($v['value']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No status breakdown available.</p>
                <?php endif; ?>
            </div>

        </div>

        <div class="report-card">
            <h3>Asset Risk Flags</h3>
            <div class="risk-list">
                <?php foreach($riskFlags as $risk): ?>
                    <div class="risk-item"><?= clean($risk) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>Detailed Asset Register</h3>

            <table id="assetTable" class="display">
                <thead>
                    <tr>
                        <th>Asset Name</th>
                        <th>Type</th>
                        <th>Subtype</th>
                        <th>Serial</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Asset Value</th>
                        <th>Net Value</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $r): ?>
                        <tr>
                            <td><?= clean($r['name']) ?></td>
                            <td><?= clean($r['type']) ?></td>
                            <td><?= clean($r['subtype']) ?></td>
                            <td><?= clean($r['serial']) ?></td>
                            <td><?= clean($r['location']) ?></td>
                            <td><?= clean($r['status']) ?></td>
                            <td><?= money($r['value']) ?></td>
                            <td><?= money($r['net_value']) ?></td>
                            <td><?= clean($r['purchase_date']) ?></td>
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
let assetTable;

$(document).ready(function(){
    assetTable = $('#assetTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        paging: true,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'WOWASCO Asset Report',
                filename: 'WOWASCO_Asset_Report',
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
                title: 'WOWASCO Asset Report',
                filename: 'WOWASCO_Asset_Report',
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
            emptyTable: "No asset records found for the selected filters."
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
        assetTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        assetTable.button('.buttons-pdf').trigger();
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
    $('#assetTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    let rows = assetTable.rows({
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
            <title>WOWASCO Asset Report</title>
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
                    font-size:10px;
                }

                th,td{
                    border:1px solid #d1d5db;
                    padding:5px;
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
            ${summaryHtml}
            <h3>Detailed Asset Register</h3>
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