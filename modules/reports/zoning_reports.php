<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

function clean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function getCol($conn, $table, $options) {
    foreach ($options as $col) {
        if (columnExists($conn, $table, $col)) return $col;
    }
    return null;
}

function rowValue($row, $col) {
    return $col && isset($row[$col]) ? $row[$col] : '';
}

function scalarCount($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

if (!tableExists($conn, 'zones')) {
    die("<div class='page-content'>Zones table was not found.</div>");
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';
$source = $_GET['source'] ?? '';
$maintenanceStatus = $_GET['maintenance_status'] ?? '';
$search = $_GET['search'] ?? '';

$zoneNameCol = getCol($conn, 'zones', ['zone_name','name']);
$zoneCodeCol = getCol($conn, 'zones', ['zone_code','code']);
$sourceNameCol = getCol($conn, 'zones', ['source_name','source']);
$officerCol = getCol($conn, 'zones', ['officer_in_charge','officer','staff_name']);
$statusCol = getCol($conn, 'zones', ['status']);
$notesCol = getCol($conn, 'zones', ['notes','description']);
$createdCol = getCol($conn, 'zones', ['created_at','updated_at']);

if (!$zoneNameCol) {
    die("<div class='page-content'>Zone name column was not found.</div>");
}

$where = "WHERE 1=1";

if ($from !== '' && $createdCol) {
    $safeFrom = $conn->real_escape_string($from);
    $where .= " AND DATE(`$createdCol`) >= '$safeFrom'";
}

if ($to !== '' && $createdCol) {
    $safeTo = $conn->real_escape_string($to);
    $where .= " AND DATE(`$createdCol`) <= '$safeTo'";
}

if ($status !== '' && $statusCol) {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND `$statusCol` = '$safeStatus'";
}

if ($source !== '' && $sourceNameCol) {
    $safeSource = $conn->real_escape_string($source);
    $where .= " AND `$sourceNameCol` = '$safeSource'";
}

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $parts = [];
    foreach ([$zoneNameCol, $zoneCodeCol, $sourceNameCol, $officerCol, $statusCol, $notesCol] as $col) {
        if ($col) $parts[] = "`$col` LIKE '%$safeSearch%'";
    }
    if (!empty($parts)) {
        $where .= " AND (" . implode(" OR ", $parts) . ")";
    }
}

$zones = $conn->query("SELECT * FROM zones $where ORDER BY `$zoneNameCol` ASC");

$reportRows = [];
$statusOptions = [];
$sourceOptions = [];
$maintenanceOptions = [];

if ($zones) {
    while ($z = $zones->fetch_assoc()) {
        $zoneId = (int)($z['id'] ?? 0);
        $zoneName = rowValue($z, $zoneNameCol);
        $safeZoneName = $conn->real_escape_string($zoneName);

        $meters = 0;
        if (tableExists($conn, 'meters') && columnExists($conn, 'meters', 'zone')) {
            $meters = scalarCount($conn, "SELECT COUNT(*) AS c FROM meters WHERE zone='$safeZoneName'");
        }

        $complaints = 0;
        if (tableExists($conn, 'customer_complaints') && columnExists($conn, 'customer_complaints', 'zone')) {
            $complaints = scalarCount($conn, "SELECT COUNT(*) AS c FROM customer_complaints WHERE zone='$safeZoneName'");
        }

        $activeSchedules = 0;
        if (tableExists($conn, 'zone_supply_schedule') && columnExists($conn, 'zone_supply_schedule', 'zone_id')) {
            $activeSchedules = scalarCount($conn, "SELECT COUNT(*) AS c FROM zone_supply_schedule WHERE zone_id=$zoneId AND (status='Active' OR status IS NULL)");
        }

        $maintenanceCount = 0;
        $latestMaintenanceStatus = 'N/A';
        if (tableExists($conn, 'zone_maintenance') && columnExists($conn, 'zone_maintenance', 'zone_id')) {
            $statusFilter = '';
            if ($maintenanceStatus !== '' && columnExists($conn, 'zone_maintenance', 'status')) {
                $safeMaint = $conn->real_escape_string($maintenanceStatus);
                $statusFilter = " AND status='$safeMaint'";
            }

            $maintenanceCount = scalarCount($conn, "SELECT COUNT(*) AS c FROM zone_maintenance WHERE zone_id=$zoneId $statusFilter");

            $maintRes = $conn->query("SELECT status FROM zone_maintenance WHERE zone_id=$zoneId ORDER BY id DESC LIMIT 1");
            if ($maintRes && $maintRes->num_rows > 0) {
                $latestMaintenanceStatus = $maintRes->fetch_assoc()['status'] ?? 'N/A';
            }
        }

        if ($maintenanceStatus !== '' && $maintenanceCount === 0) {
            continue;
        }

        $rationingCount = 0;
        if (tableExists($conn, 'water_rationing_schedule') && columnExists($conn, 'water_rationing_schedule', 'zone')) {
            $rationingCount = scalarCount($conn, "SELECT COUNT(*) AS c FROM water_rationing_schedule WHERE zone='$safeZoneName' AND (status='Active' OR status IS NULL)");
        }

        $row = [
            'zone_name' => $zoneName,
            'zone_code' => rowValue($z, $zoneCodeCol),
            'source_name' => rowValue($z, $sourceNameCol),
            'officer' => rowValue($z, $officerCol),
            'status' => rowValue($z, $statusCol) ?: 'N/A',
            'meters' => $meters,
            'complaints' => $complaints,
            'active_schedules' => $activeSchedules,
            'maintenance_cases' => $maintenanceCount,
            'latest_maintenance_status' => $latestMaintenanceStatus,
            'active_rationing' => $rationingCount,
            'created_at' => rowValue($z, $createdCol)
        ];

        if ($row['status'] !== 'N/A') $statusOptions[$row['status']] = $row['status'];
        if ($row['source_name'] !== '') $sourceOptions[$row['source_name']] = $row['source_name'];
        if ($latestMaintenanceStatus !== 'N/A') $maintenanceOptions[$latestMaintenanceStatus] = $latestMaintenanceStatus;

        $reportRows[] = $row;
    }
}

if (tableExists($conn, 'zone_maintenance') && columnExists($conn, 'zone_maintenance', 'status')) {
    $maintOpts = $conn->query("SELECT DISTINCT status AS v FROM zone_maintenance WHERE status IS NOT NULL AND status!='' ORDER BY status ASC");
    if ($maintOpts) {
        while ($m = $maintOpts->fetch_assoc()) {
            $maintenanceOptions[$m['v']] = $m['v'];
        }
    }
}

$statusList = $statusCol ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM zones WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC") : null;
if ($statusList) {
    while ($s = $statusList->fetch_assoc()) $statusOptions[$s['v']] = $s['v'];
}

$sourceList = $sourceNameCol ? $conn->query("SELECT DISTINCT `$sourceNameCol` AS v FROM zones WHERE `$sourceNameCol` IS NOT NULL AND `$sourceNameCol`!='' ORDER BY `$sourceNameCol` ASC") : null;
if ($sourceList) {
    while ($s = $sourceList->fetch_assoc()) $sourceOptions[$s['v']] = $s['v'];
}

ksort($statusOptions);
ksort($sourceOptions);
ksort($maintenanceOptions);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">
    <form method="GET" class="filter-card no-print">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'modules/reports/zoning_reports.php') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div>
            <label>Zone Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statusOptions as $option): ?>
                    <option value="<?= clean($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= clean($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Water Source</label>
            <select name="source">
                <option value="">All Sources</option>
                <?php foreach ($sourceOptions as $option): ?>
                    <option value="<?= clean($option) ?>" <?= $source === $option ? 'selected' : '' ?>><?= clean($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Maintenance</label>
            <select name="maintenance_status">
                <option value="">All Maintenance</option>
                <?php foreach ($maintenanceOptions as $option): ?>
                    <option value="<?= clean($option) ?>" <?= $maintenanceStatus === $option ? 'selected' : '' ?>><?= clean($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Zone, source, code, officer...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=modules/reports/zoning_reports.php">Reset</a>
        <button type="button" onclick="printFilteredReport()" class="print-btn">Print Data</button>
    </form>

    <div class="table-panel">
        <div class="table-toolbar no-print">
            <div class="table-count">
                Zone Records:
                <strong><?= number_format(count($reportRows)) ?></strong>
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

        <table id="zoningReportsTable" class="display">
            <thead>
                <tr>
                    <th>Zone</th>
                    <th>Code</th>
                    <th>Water Source</th>
                    <th>Officer</th>
                    <th>Status</th>
                    <th>Meters</th>
                    <th>Complaints</th>
                    <th>Schedules</th>
                    <th>Maintenance Cases</th>
                    <th>Latest Maintenance</th>
                    <th>Active Rationing</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td><?= clean($row['zone_name']) ?></td>
                        <td><?= clean($row['zone_code'] ?: 'N/A') ?></td>
                        <td><?= clean($row['source_name'] ?: 'Unassigned') ?></td>
                        <td><?= clean($row['officer'] ?: 'Unassigned') ?></td>
                        <td><?= clean($row['status']) ?></td>
                        <td><?= number_format($row['meters']) ?></td>
                        <td><?= number_format($row['complaints']) ?></td>
                        <td><?= number_format($row['active_schedules']) ?></td>
                        <td><?= number_format($row['maintenance_cases']) ?></td>
                        <td><?= clean($row['latest_maintenance_status']) ?></td>
                        <td><?= number_format($row['active_rationing']) ?></td>
                        <td><?= clean($row['created_at'] ?: 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
:root{
    --primary:#0a2a43;
    --border:#dbe3ee;
}

.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:24px;
    background:#f4f7fb;
    min-height:calc(100vh - 135px);
    font-family:'Segoe UI',Tahoma,sans-serif;
}

.filter-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
    margin-bottom:18px;
    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:12px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.filter-card label{
    display:block;
    margin-bottom:6px;
    font-size:13px;
    font-weight:700;
    color:#334155;
}

.filter-card input,
.filter-card select{
    min-width:155px;
    padding:10px 11px;
    border-radius:8px;
    border:1px solid var(--border);
    background:#fff;
    color:#334155;
    font-size:13px;
}

.search-box input{
    min-width:270px;
}

.download-btn,
.print-btn,
.filter-card button,
.clear-btn{
    border:none;
    background:var(--primary);
    color:#fff;
    padding:10px 15px;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
    font-weight:700;
    text-decoration:none;
    line-height:1.2;
}

.clear-btn{
    background:#64748b;
}

.table-panel{
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
    overflow-x:auto;
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
    color:var(--primary);
    font-size:18px;
}

.table-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
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
    min-width:150px;
    border:1px solid var(--border);
    border-radius:8px;
    box-shadow:0 8px 20px rgba(15,23,42,0.12);
    z-index:50;
    overflow:hidden;
}

.dropdown:hover .dropdown-content{
    display:block;
}

.dropdown-content a{
    display:block;
    padding:10px 12px;
    color:#334155;
    text-decoration:none;
    font-size:13px;
}

.dropdown-content a:hover{
    background:#f8fafc;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th{
    background:var(--primary) !important;
    color:#fff !important;
    text-align:left;
    padding:11px 10px;
    border-bottom:1px solid var(--primary) !important;
    white-space:nowrap;
}

td{
    padding:11px 10px;
    border-bottom:1px solid #edf2f7;
    color:#475569;
    vertical-align:top;
}

tbody tr:hover{
    background:#f8fafc;
}

.dt-button{
    display:none !important;
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

.dt-search input,
.dt-length select,
.dataTables_filter input,
.dataTables_length select{
    border:1px solid var(--border);
    border-radius:8px;
    padding:7px 8px;
    background:#fff;
}

@media(max-width:992px){
    .page-content{
        margin-left:0;
        padding:15px;
    }

    .filter-card,
    .table-toolbar,
    .table-actions{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-card input,
    .filter-card select,
    .filter-card button,
    .clear-btn,
    .download-btn,
    .print-btn{
        width:100%;
        box-sizing:border-box;
    }

    .search-box input{
        min-width:100%;
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
    margin:22px 0 34px;
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

.dt-container .dt-length,
.dataTables_wrapper .dataTables_length{
    margin-bottom:22px !important;
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

@media(max-width:992px){
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
let zoningReportsTable;

function getZoningTitle(){
    const status = $('select[name="status"]').val();
    const source = $('select[name="source"]').val();
    let title = 'WOWASCO Zoning Report';
    if(status){ title += ' - ' + status; }
    if(source){ title += ' - ' + source; }
    return title;
}

function getZoningFilterSummary(){
    const filteredCount = zoningReportsTable ? zoningReportsTable.rows({ search:'applied' }).count() : <?= (int)count($reportRows) ?>;
    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'Filters: From ' + ($('input[name="from"]').val() || 'Any') +
        ' | To ' + ($('input[name="to"]').val() || 'Any') +
        ' | Zone Status ' + ($('select[name="status"]').val() || 'All') +
        ' | Source ' + ($('select[name="source"]').val() || 'All') +
        ' | Maintenance ' + ($('select[name="maintenance_status"]').val() || 'All') +
        ' | Search ' + ($('input[name="search"]').val() || 'None') +
        ' | Table Search ' + (zoningReportsTable ? (zoningReportsTable.search() || 'None') : 'None')
    ].join('\n');
}

function getZoningFilename(){
    return getZoningTitle().replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
}

$(document).ready(function(){
    zoningReportsTable = $('#zoningReportsTable').DataTable({
        pageLength: 10,
        lengthMenu: [10,25,50,100],
        ordering: true,
        searching: true,
        paging: true,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: getZoningTitle,
                filename: getZoningFilename,
                messageTop: getZoningFilterSummary,
                exportOptions: {
                    columns: ':visible',
                    modifier: { search:'applied', order:'applied', page:'all' }
                }
            },
            {
                extend: 'pdfHtml5',
                title: getZoningTitle,
                filename: getZoningFilename,
                messageTop: getZoningFilterSummary,
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible',
                    modifier: { search:'applied', order:'applied', page:'all' }
                }
            }
        ],
        language: {
            search: "",
            searchPlaceholder: "",
            lengthMenu: "Show _MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ filtered records",
            emptyTable: "No zoning report records found."
        }
    });
});

function triggerExport(type){
    if(type === 'excel'){
        zoningReportsTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        zoningReportsTable.button('.buttons-pdf').trigger();
    }
}

function printFilteredReport(){
    const headers = [];
    $('#zoningReportsTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    const rows = zoningReportsTable.rows({
        search:'applied',
        order:'applied',
        page:'all'
    }).data().toArray();

    const tableHtml = `
        <table>
            <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
            <tbody>
                ${
                    rows.length > 0
                    ? rows.map(row => `<tr>${row.map(cell => `<td>${$('<div>').html(cell).text()}</td>`).join('')}</tr>`).join('')
                    : `<tr><td colspan="${headers.length}">No filtered records found.</td></tr>`
                }
            </tbody>
        </table>
    `;

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>WOWASCO Zoning Report</title>
            <style>
                body{font-family:Arial,sans-serif;padding:20px;color:#1f2937;}
                .report-title{border-bottom:2px solid #0a2a43;margin-bottom:15px;padding-bottom:10px;}
                h3{color:#0a2a43;margin:0 0 6px;}
                p{font-size:12px;color:#475569;margin:3px 0;line-height:1.5;}
                table{width:100%;border-collapse:collapse;font-size:10px;}
                th,td{border:1px solid #d1d5db;padding:6px;text-align:left;vertical-align:top;}
                th{background:#0a2a43;color:#fff;}
            </style>
        </head>
        <body>
            <div class="report-title">
                <h3>${getZoningTitle()}</h3>
                <p>${getZoningFilterSummary().replace(/\n/g, '<br>')}</p>
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
