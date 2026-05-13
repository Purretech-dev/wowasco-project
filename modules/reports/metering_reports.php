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

function rowValue($row, $col){
    return $col && isset($row[$col]) ? $row[$col] : '';
}

if (!tableExists($conn, 'meters')) {
    die("<div class='page-content'>Meters table was not found.</div>");
}

/* ================= COLUMN MAPPING ================= */

$serialCol = getCol($conn, 'meters', ['serial_number','meter_serial','serial','meter_no']);
$zoneCol = getCol($conn, 'meters', ['zone','zone_name','location']);
$statusCol = getCol($conn, 'meters', ['status','meter_status']);
$typeCol = getCol($conn, 'meters', ['meter_type','type']);
$modelCol = getCol($conn, 'meters', ['model','meter_model']);
$customerCol = getCol($conn, 'meters', ['customer_name','name']);
$customerTypeCol = getCol($conn, 'meters', ['customer_type']);
$installCol = getCol($conn, 'meters', ['installation_date','date_installed','created_at']);

/* ================= FILTERS ================= */

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$zone = $_GET['zone'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$hasFilter = ($from !== '' || $to !== '' || $zone !== '' || $status !== '' || $type !== '' || $search !== '');

$where = "WHERE 1=1";

if ($from !== '' && $installCol) {
    $safeFrom = $conn->real_escape_string($from);
    $where .= " AND DATE(`$installCol`) >= '$safeFrom'";
}

if ($to !== '' && $installCol) {
    $safeTo = $conn->real_escape_string($to);
    $where .= " AND DATE(`$installCol`) <= '$safeTo'";
}

if ($zone !== '' && $zoneCol) {
    $safeZone = $conn->real_escape_string($zone);
    $where .= " AND `$zoneCol` = '$safeZone'";
}

if ($status !== '' && $statusCol) {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND `$statusCol` = '$safeStatus'";
}

if ($type !== '' && $typeCol) {
    $safeType = $conn->real_escape_string($type);
    $where .= " AND `$typeCol` = '$safeType'";
}

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $searchParts = [];

    foreach ([$serialCol, $zoneCol, $statusCol, $typeCol, $modelCol, $customerCol, $customerTypeCol] as $col) {
        if ($col) {
            $searchParts[] = "`$col` LIKE '%$safeSearch%'";
        }
    }

    if (!empty($searchParts)) {
        $where .= " AND (" . implode(" OR ", $searchParts) . ")";
    }
}

/* ================= DATA ================= */

$meters = $conn->query("SELECT * FROM meters $where ORDER BY id DESC");

$totalMeters = countRows($conn, 'meters');
$filteredMeters = $meters ? $meters->num_rows : 0;

$activeMeters = $statusCol ? countRows($conn, 'meters', "`$statusCol`='Active'") : 0;
$inactiveMeters = $statusCol ? countRows($conn, 'meters', "`$statusCol`='Inactive'") : 0;
$faultyMeters = $statusCol ? countRows($conn, 'meters', "`$statusCol` IN ('Faulty','Damaged','Defective')") : 0;
$smartMeters = $typeCol ? countRows($conn, 'meters', "`$typeCol` LIKE '%Smart%'") : 0;
$conventionalMeters = $typeCol ? countRows($conn, 'meters', "`$typeCol` LIKE '%Conventional%'") : 0;
$unassignedMeters = $customerCol ? countRows($conn, 'meters', "(`$customerCol` IS NULL OR `$customerCol`='')") : 0;

$oldMeters = 0;
if ($installCol) {
    $oldMeters = countRows($conn, 'meters', "DATE(`$installCol`) <= DATE_SUB(CURDATE(), INTERVAL 8 YEAR)");
}

$zones = $zoneCol ? $conn->query("SELECT DISTINCT `$zoneCol` AS v FROM meters WHERE `$zoneCol` IS NOT NULL AND `$zoneCol`!='' ORDER BY `$zoneCol` ASC") : null;
$statuses = $statusCol ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM meters WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC") : null;
$types = $typeCol ? $conn->query("SELECT DISTINCT `$typeCol` AS v FROM meters WHERE `$typeCol` IS NOT NULL AND `$typeCol`!='' ORDER BY `$typeCol` ASC") : null;

$byZone = $zoneCol ? $conn->query("SELECT `$zoneCol` AS label, COUNT(*) AS total FROM meters GROUP BY `$zoneCol` ORDER BY total DESC LIMIT 10") : null;
$byStatus = $statusCol ? $conn->query("SELECT `$statusCol` AS label, COUNT(*) AS total FROM meters GROUP BY `$statusCol` ORDER BY total DESC") : null;
$byType = $typeCol ? $conn->query("SELECT `$typeCol` AS label, COUNT(*) AS total FROM meters GROUP BY `$typeCol` ORDER BY total DESC") : null;

$riskFlags = [];
if ($inactiveMeters > 0) $riskFlags[] = "$inactiveMeters meters are inactive.";
if ($faultyMeters > 0) $riskFlags[] = "$faultyMeters meters are faulty, damaged, or defective.";
if ($unassignedMeters > 0) $riskFlags[] = "$unassignedMeters meters are not assigned to customers.";
if ($oldMeters > 0) $riskFlags[] = "$oldMeters meters have exceeded 8 years since installation.";
if ($totalMeters == 0) $riskFlags[] = "No meter records are available.";
if (empty($riskFlags)) $riskFlags[] = "No major metering risk detected.";
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Metering Reports</h2>
            <p>Filtered enterprise reports for meter inventory, zones, status, aging and customer allocation.</p>
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
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'reports/metering_reports') ?>">

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
            <label>Meter Type</label>
            <select name="type">
                <option value="">All Types</option>
                <?php if ($types): while($t = $types->fetch_assoc()): ?>
                    <option value="<?= clean($t['v']) ?>" <?= $type === $t['v'] ? 'selected' : '' ?>>
                        <?= clean($t['v']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Serial, customer, model, zone...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=reports/metering_reports">Reset</a>
    </form>

    <?php if (!$hasFilter): ?>
        <div class="filter-warning no-print">
            Please select at least one filter before downloading or printing.
        </div>
    <?php endif; ?>

    <div id="printArea">

        <div class="report-title">
            <h3>WOWASCO Metering Report</h3>
            <p>
                Generated on <?= date('Y-m-d H:i:s') ?> |
                Filtered Records: <?= number_format($filteredMeters) ?>
            </p>
            <p>
                Filters:
                From: <?= clean($from ?: 'Any') ?> |
                To: <?= clean($to ?: 'Any') ?> |
                Zone: <?= clean($zone ?: 'All') ?> |
                Status: <?= clean($status ?: 'All') ?> |
                Type: <?= clean($type ?: 'All') ?> |
                Search: <?= clean($search ?: 'None') ?>
            </p>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card"><span>Total Meters</span><strong><?= number_format($totalMeters) ?></strong><small><?= number_format($filteredMeters) ?> filtered</small></div>
            <div class="kpi-card"><span>Active Meters</span><strong><?= number_format($activeMeters) ?></strong><small>Operational</small></div>
            <div class="kpi-card"><span>Inactive Meters</span><strong><?= number_format($inactiveMeters) ?></strong><small>Needs review</small></div>
            <div class="kpi-card"><span>Smart Meters</span><strong><?= number_format($smartMeters) ?></strong><small>Smart stock</small></div>
            <div class="kpi-card"><span>Conventional</span><strong><?= number_format($conventionalMeters) ?></strong><small>Conventional stock</small></div>
            <div class="kpi-card"><span>Unassigned</span><strong><?= number_format($unassignedMeters) ?></strong><small>No customer attached</small></div>
            <div class="kpi-card"><span>Old Meters</span><strong><?= number_format($oldMeters) ?></strong><small>Over 8 years</small></div>
            <div class="kpi-card"><span>Faulty Meters</span><strong><?= number_format($faultyMeters) ?></strong><small>Fault status</small></div>
        </div>

        <div class="report-grid three">
            <div class="report-card">
                <h3>Meters by Zone</h3>
                <?php if ($byZone && $byZone->num_rows > 0): ?>
                    <?php while($z = $byZone->fetch_assoc()): ?>
                        <div class="metric-row"><span><?= clean($z['label']) ?></span><strong><?= number_format($z['total']) ?></strong></div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty">No zone breakdown available.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Meters by Status</h3>
                <?php if ($byStatus && $byStatus->num_rows > 0): ?>
                    <?php while($s = $byStatus->fetch_assoc()): ?>
                        <div class="metric-row"><span><?= clean($s['label']) ?></span><strong><?= number_format($s['total']) ?></strong></div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty">No status breakdown available.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Meters by Type</h3>
                <?php if ($byType && $byType->num_rows > 0): ?>
                    <?php while($t = $byType->fetch_assoc()): ?>
                        <div class="metric-row"><span><?= clean($t['label']) ?></span><strong><?= number_format($t['total']) ?></strong></div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty">No type breakdown available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>Metering Risk Flags</h3>
            <div class="risk-list">
                <?php foreach($riskFlags as $risk): ?>
                    <div class="risk-item"><?= clean($risk) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-card">
            <h3>Detailed Meter Register</h3>

            <table id="meteringTable" class="display">
                <thead>
                    <tr>
                        <th>Serial Number</th>
                        <th>Customer</th>
                        <th>Customer Type</th>
                        <th>Zone</th>
                        <th>Meter Type</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Installation Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($meters && $meters->num_rows > 0): ?>
                        <?php while($m = $meters->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean(rowValue($m, $serialCol)) ?></td>
                                <td><?= clean(rowValue($m, $customerCol)) ?></td>
                                <td><?= clean(rowValue($m, $customerTypeCol)) ?></td>
                                <td><?= clean(rowValue($m, $zoneCol)) ?></td>
                                <td><?= clean(rowValue($m, $typeCol)) ?></td>
                                <td><?= clean(rowValue($m, $modelCol)) ?></td>
                                <td><?= clean(rowValue($m, $statusCol)) ?></td>
                                <td><?= clean(rowValue($m, $installCol)) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
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
let meteringTable;

$(document).ready(function(){
    meteringTable = $('#meteringTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        paging: true,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'WOWASCO Metering Report',
                filename: 'WOWASCO_Metering_Report',
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
                title: 'WOWASCO Metering Report',
                filename: 'WOWASCO_Metering_Report',
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
            emptyTable: "No meter records found for the selected filters."
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
        meteringTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        meteringTable.button('.buttons-pdf').trigger();
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
    $('#meteringTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    let rows = meteringTable.rows({
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
            <title>WOWASCO Metering Report</title>
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
            <h3>Detailed Meter Register</h3>
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