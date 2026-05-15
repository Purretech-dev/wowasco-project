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

if (!tableExists($conn, 'meters')) {
    die("<div class='page-content'>Meters table was not found.</div>");
}

if (!tableExists($conn, 'pumped_volume_entries')) {
    die("<div class='page-content'>Pumped volume table was not found.</div>");
}

if (!tableExists($conn, 'meter_readings')) {
    die("<div class='page-content'>Meter readings table was not found.</div>");
}

$serialCol = getCol($conn, 'meters', ['serial_number','meter_serial','serial','meter_no']);
$zoneCol = getCol($conn, 'meters', ['zone','zone_name','location']);
$statusCol = getCol($conn, 'meters', ['status','meter_status']);
$customerCol = getCol($conn, 'meters', ['customer_name','name']);
$customerTypeCol = getCol($conn, 'meters', ['customer_type','type']);

if (!$serialCol) {
    die("<div class='page-content'>Meter serial column was not found.</div>");
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$zone = $_GET['zone'] ?? '';
$customer_type = $_GET['customer_type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

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
$rows = [];

if ($reportRows) {
    while($row = $reportRows->fetch_assoc()) {
        $rows[] = $row;
    }
}

$filteredRecords = count($rows);

$zones = $zoneCol
    ? $conn->query("SELECT DISTINCT `$zoneCol` AS v FROM meters WHERE `$zoneCol` IS NOT NULL AND `$zoneCol`!='' ORDER BY `$zoneCol` ASC")
    : null;

$statuses = $statusCol
    ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM meters WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC")
    : null;

$types = $customerTypeCol
    ? $conn->query("SELECT DISTINCT `$customerTypeCol` AS v FROM meters WHERE `$customerTypeCol` IS NOT NULL AND `$customerTypeCol`!='' ORDER BY `$customerTypeCol` ASC")
    : null;
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Production Reports</h2>
            <p>Filtered production, billed consumption, NRW, revenue water, zones and customer category reporting.</p>
        </div>
    </div>

    <form method="GET" class="filter-card no-print">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'modules/reports/production_reports.php') ?>">

        <div class="filter-field">
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div class="filter-field">
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div class="filter-field">
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

        <div class="filter-field">
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

        <div class="filter-field">
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

        <div class="filter-field search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Serial, customer, zone...">
        </div>

        <div class="filter-actions">
            <button type="submit">Apply Filters</button>
            <a href="dashboard.php?page=modules/reports/production_reports.php" class="clear-btn">Reset</a>
        </div>
    </form>

    <div class="table-panel">
        <div class="table-toolbar no-print">
            <div class="table-count">
                <span>Production Records</span>
                <strong><?= number_format($filteredRecords) ?></strong>
            </div>

            <div class="table-actions">
                <div class="dropdown">
                    <button type="button" class="download-btn">Download Report &#9662;</button>

                    <div class="dropdown-content">
                        <a href="#" onclick="triggerExport('excel'); return false;">Excel</a>
                        <a href="#" onclick="triggerExport('pdf'); return false;">PDF</a>
                    </div>
                </div>

                <button type="button" onclick="printFilteredReport()" class="print-btn">Print Data</button>
            </div>
        </div>

        <div class="table-wrap">
            <table id="productionTable" class="display nowrap">
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
                            <td><?= number_format((int)$r['entry_count']) ?></td>
                            <td><?= number_format((float)$r['produced_volume'], 2) ?> m³</td>
                            <td><?= number_format((float)$r['billed_volume'], 2) ?> m³</td>
                            <td><?= number_format((float)$r['nrw_volume'], 2) ?> m³</td>
                            <td><?= number_format((float)$r['nrw_percent'], 2) ?>%</td>
                            <td><?= number_format((float)$r['revenue_water_percent'], 2) ?>%</td>
                            <td><?= clean($r['last_pumped_date'] ?: 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
:root{
    --primary:#0a2a43;
    --primary-soft:#123d5d;
    --border:#dbe3ee;
    --muted:#64748b;
    --text:#334155;
    --bg:#f4f7fb;
    --white:#ffffff;
}

*{
    box-sizing:border-box;
}

.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:24px;
    background:var(--bg);
    min-height:calc(100vh - 135px);
    font-family:'Segoe UI', Tahoma, sans-serif;
    color:var(--text);
}

.module-header{
    background:var(--white);
    border-radius:8px;
    padding:18px 20px;
    margin-bottom:16px;
    border:1px solid var(--border);
    border-left:5px solid var(--primary);
}

.module-header h2{
    margin:0;
    font-size:22px;
    line-height:1.2;
    color:var(--primary);
}

.module-header p{
    margin:6px 0 0;
    color:var(--muted);
    font-size:14px;
    line-height:1.5;
}

.filter-card{
    background:var(--white);
    border-radius:8px;
    padding:16px;
    margin-bottom:16px;
    display:grid;
    grid-template-columns:repeat(6, minmax(145px, 1fr));
    gap:12px;
    align-items:end;
    border:1px solid var(--border);
}

.filter-field{
    min-width:0;
}

.filter-field label{
    display:block;
    margin-bottom:5px;
    font-size:13px;
    font-weight:600;
    color:#475569;
}

.filter-field input,
.filter-field select{
    width:100%;
    min-height:40px;
    padding:9px 10px;
    border-radius:7px;
    border:1px solid var(--border);
    background:#f8fafc;
    color:var(--text);
    font-size:13px;
    outline:none;
}

.filter-field input:focus,
.filter-field select:focus,
.dt-search input:focus,
.dt-length select:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(10,42,67,0.12);
}

.search-box{
    grid-column:span 2;
}

.filter-actions{
    display:flex;
    flex-direction:row;
    gap:8px;
    align-items:center;
    justify-content:flex-start;
    white-space:nowrap;
}

.download-btn,
.print-btn,
.filter-card button,
.clear-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:40px;
    border:none;
    background:var(--primary);
    color:#fff;
    padding:10px 14px;
    border-radius:7px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    text-decoration:none;
    line-height:1;
    white-space:nowrap;
}

.download-btn:hover,
.print-btn:hover,
.filter-card button:hover{
    background:var(--primary-soft);
}

.clear-btn{
    background:#64748b;
}

.clear-btn:hover{
    background:#475569;
}

.table-panel{
    overflow:visible;
}

.table-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:12px;
    flex-wrap:nowrap;
    position:relative;
    z-index:20;
}

.table-count{
    display:flex;
    align-items:center;
    gap:8px;
    color:#475569;
    font-size:14px;
    min-width:0;
    white-space:nowrap;
}

.table-count strong{
    color:var(--primary);
    font-size:18px;
}

.table-actions{
    display:flex;
    flex-direction:row;
    gap:8px;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:nowrap;
    white-space:nowrap;
}

.dropdown{
    position:relative;
    display:inline-block;
}

.dropdown-content{
    display:none;
    position:absolute;
    right:0;
    top:calc(100% + 6px);
    background:#fff;
    min-width:150px;
    border-radius:8px;
    overflow:hidden;
    border:1px solid var(--border);
    box-shadow:0 12px 22px rgba(0,0,0,0.14);
    z-index:9999;
}

.dropdown-content a{
    display:block;
    padding:11px 13px;
    color:#334155;
    text-decoration:none;
    font-size:13px;
    background:#fff;
}

.dropdown-content a:hover{
    background:#f8fafc;
}

.dropdown:hover .dropdown-content,
.dropdown:focus-within .dropdown-content{
    display:block;
}

.table-wrap{
    width:100%;
    overflow-x:auto;
    background:#fff;
    border:1px solid var(--border);
    border-radius:8px;
    padding:12px;
}

.dt-container,
.dataTables_wrapper{
    width:100%;
    margin:0;
}

.dt-container .dt-layout-row:first-child{
    display:flex !important;
    flex-direction:row !important;
    justify-content:space-between !important;
    align-items:center !important;
    gap:16px !important;
    flex-wrap:nowrap !important;
    margin-bottom:12px !important;
    overflow-x:auto;
    padding-bottom:2px;
}

.dt-container .dt-layout-cell{
    min-width:0;
}

.dt-container .dt-layout-cell.dt-layout-start{
    flex:0 0 auto;
}

.dt-container .dt-layout-cell.dt-layout-end{
    flex:0 0 auto;
    margin-left:auto;
}

.dt-length,
.dt-search{
    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;
    gap:8px !important;
    margin:0 !important;
    white-space:nowrap !important;
}

.dt-length label,
.dt-search label{
    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;
    gap:8px !important;
    margin:0 !important;
    color:#475569 !important;
    font-size:13px !important;
    white-space:nowrap !important;
}

.dt-length select{
    width:78px !important;
    min-width:78px !important;
    height:36px !important;
    padding:6px 8px !important;
    border:1px solid var(--border) !important;
    border-radius:7px !important;
    background:#fff !important;
    color:var(--text) !important;
}

.dt-search input{
    width:230px !important;
    min-width:230px !important;
    height:36px !important;
    padding:6px 9px !important;
    border:1px solid var(--border) !important;
    border-radius:7px !important;
    background:#fff !important;
    color:var(--text) !important;
}

.dt-info{
    color:var(--muted);
    font-size:13px;
    padding-top:12px;
}

.dt-paging{
    padding-top:12px;
}

.dt-paging button{
    border-radius:6px !important;
    border:1px solid var(--border) !important;
    background:#fff !important;
    color:var(--text) !important;
    padding:6px 10px !important;
    margin:0 2px !important;
}

.dt-paging button.current{
    background:var(--primary) !important;
    color:#fff !important;
    border-color:var(--primary) !important;
}

.dt-buttons{
    display:none !important;
}

#productionTable{
    width:100% !important;
    border-collapse:separate;
    border-spacing:0;
    font-size:14px;
    background:#fff;
}

#productionTable thead th,
table.dataTable.display > thead > tr > th{
    background:var(--primary) !important;
    color:#fff !important;
    font-size:13px;
    font-weight:700;
    padding:12px 12px !important;
    border-bottom:0 !important;
    text-align:left;
    vertical-align:middle;
    white-space:nowrap;
}

#productionTable thead th:first-child{
    border-top-left-radius:6px;
}

#productionTable thead th:last-child{
    border-top-right-radius:6px;
}

#productionTable thead th.dt-orderable-asc span.dt-column-order:before,
#productionTable thead th.dt-orderable-desc span.dt-column-order:after,
#productionTable thead th.dt-ordering-asc span.dt-column-order:before,
#productionTable thead th.dt-ordering-desc span.dt-column-order:after{
    color:#fff !important;
    opacity:0.85 !important;
}

#productionTable tbody td,
table.dataTable.display > tbody > tr > td{
    padding:12px 12px !important;
    border-bottom:1px solid #edf2f7 !important;
    color:#475569 !important;
    vertical-align:middle;
    white-space:nowrap;
}

#productionTable tbody tr:nth-child(even){
    background:#f8fafc;
}

#productionTable tbody tr:hover{
    background:#eef6fb !important;
}

@media(max-width:1200px){
    .filter-card{
        grid-template-columns:repeat(3, minmax(160px, 1fr));
    }

    .search-box{
        grid-column:span 2;
    }
}

@media(max-width:992px){
    .page-content{
        margin-left:0;
        padding:15px;
    }

    .filter-card{
        grid-template-columns:1fr;
    }

    .search-box{
        grid-column:auto;
    }

    .filter-actions{
        flex-direction:row;
        align-items:center;
    }

    .filter-card button,
    .clear-btn{
        flex:1 1 0;
    }

    .table-toolbar{
        flex-direction:column;
        align-items:stretch;
        gap:10px;
    }

    .table-count{
        justify-content:space-between;
    }

    .table-actions{
        width:100%;
        flex-direction:row;
        align-items:center;
        justify-content:flex-start;
    }

    .dropdown,
    .download-btn,
    .print-btn{
        flex:1 1 0;
    }

    .download-btn,
    .print-btn{
        width:100%;
    }

    .dropdown-content{
        left:0;
        right:auto;
        width:100%;
    }

    .dt-container .dt-layout-row:first-child{
        flex-direction:row !important;
        align-items:center !important;
        justify-content:space-between !important;
        flex-wrap:nowrap !important;
    }

    .dt-length,
    .dt-search,
    .dt-length label,
    .dt-search label{
        width:auto !important;
        flex-wrap:nowrap !important;
        white-space:nowrap !important;
    }

    .dt-search input{
        width:180px !important;
        min-width:180px !important;
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

function getDynamicTitle(){
    let title = 'WOWASCO Production Report';

    const zone = $('select[name="zone"]').val();
    const customerType = $('select[name="customer_type"]').val();
    const status = $('select[name="status"]').val();

    if(zone) title += ' - Zone ' + zone;
    if(customerType) title += ' - Customer Type ' + customerType;
    if(status) title += ' - Status ' + status;

    return title;
}

function getFilterSummaryLines(){
    const from = $('input[name="from"]').val() || 'Any';
    const to = $('input[name="to"]').val() || 'Any';
    const zone = $('select[name="zone"]').val() || 'All';
    const customerType = $('select[name="customer_type"]').val() || 'All';
    const status = $('select[name="status"]').val() || 'All';
    const search = $('input[name="search"]').val() || 'None';
    const tableSearch = productionTable ? (productionTable.search() || 'None') : 'None';
    const filteredCount = productionTable ? productionTable.rows({ search:'applied' }).count() : <?= (int)$filteredRecords ?>;

    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'Period: ' + from + ' to ' + to,
        'Zone: ' + zone + ' | Customer Type: ' + customerType + ' | Status: ' + status,
        'Search: ' + search + ' | Table Search: ' + tableSearch
    ];
}

function getFilterSummary(){
    return getFilterSummaryLines().join('\n');
}

function getExportFilename(){
    return getDynamicTitle()
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');
}

$(document).ready(function(){
    productionTable = $('#productionTable').DataTable({
        pageLength: 10,
        lengthMenu: [10,25,50,100],
        ordering: true,
        searching: true,
        paging: true,
        autoWidth: false,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: getDynamicTitle,
                filename: getExportFilename,
                messageTop: getFilterSummary,
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
                title: getDynamicTitle,
                filename: getExportFilename,
                messageTop: getFilterSummary,
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible',
                    modifier: {
                        search: 'applied',
                        order: 'applied',
                        page: 'all'
                    }
                },
                customize: function(doc){
                    doc.styles.title = {
                        fontSize: 14,
                        bold: true,
                        alignment: 'left',
                        margin: [0, 0, 0, 8]
                    };

                    if(doc.content[1] && doc.content[1].text){
                        doc.content[1].fontSize = 9;
                        doc.content[1].margin = [0, 0, 0, 10];
                    }

                    const tableNode = doc.content.find(item => item.table);
                    if(tableNode){
                        tableNode.layout = 'lightHorizontalLines';
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
    if(!productionTable){
        alert('The report table is still loading. Please try again.');
        return;
    }

    if(type === 'excel'){
        productionTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        productionTable.button('.buttons-pdf').trigger();
    }
}

function escapeHtml(value){
    return $('<div>').text(value ?? '').html();
}

function printFilteredReport(){
    if(!productionTable){
        alert('The report table is still loading. Please try again.');
        return;
    }

    let headers = [];

    $('#productionTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    let rows = productionTable.rows({
        search:'applied',
        order:'applied',
        page:'all'
    }).data().toArray();

    let headerHtml = `
        <div class="print-header">
            <h2>${escapeHtml(getDynamicTitle())}</h2>
            ${getFilterSummaryLines().map(line => `<p>${escapeHtml(line)}</p>`).join('')}
        </div>
    `;

    let tableHtml = `
        <table>
            <thead>
                <tr>${headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr>
            </thead>
            <tbody>
                ${
                    rows.length > 0
                    ? rows.map(row => `
                        <tr>
                            ${row.map(cell => `<td>${escapeHtml($('<div>').html(cell).text())}</td>`).join('')}
                        </tr>
                    `).join('')
                    : `<tr><td colspan="${headers.length}">No filtered records found.</td></tr>`
                }
            </tbody>
        </table>
    `;

    let printWindow = window.open('', '', 'width=1200,height=800');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${escapeHtml(getDynamicTitle())}</title>
            <style>
                body{
                    font-family:Arial,sans-serif;
                    padding:20px;
                    color:#1f2937;
                }

                .print-header{
                    border-bottom:2px solid #0a2a43;
                    margin-bottom:15px;
                    padding-bottom:10px;
                }

                .print-header h2{
                    margin:0 0 6px;
                    color:#0a2a43;
                    font-size:18px;
                }

                .print-header p{
                    margin:3px 0;
                    font-size:12px;
                    color:#475569;
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
                    background:#0a2a43;
                    color:#fff;
                }

                tbody tr:nth-child(even){
                    background:#f8fafc;
                }
            </style>
        </head>
        <body>
            ${headerHtml}
            ${tableHtml}
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();

    printWindow.onload = function(){
        printWindow.print();
    };
}
</script>
