<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* =========================================
   HELPERS
========================================= */

function clean($value){
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table){

    $table = $conn->real_escape_string($table);

    $res = $conn->query("
        SHOW TABLES LIKE '$table'
    ");

    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column){

    if (!tableExists($conn, $table)) {
        return false;
    }

    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("
        SHOW COLUMNS FROM `$table`
        LIKE '$column'
    ");

    return $res && $res->num_rows > 0;
}

function getCol($conn, $table, $options){

    foreach ($options as $col) {

        if (columnExists($conn, $table, $col)) {
            return $col;
        }
    }

    return null;
}

function rowValue($row, $col){

    return $col && isset($row[$col])
        ? $row[$col]
        : '';
}

/* =========================================
   TABLE CHECK
========================================= */

if (!tableExists($conn, 'meters')) {

    die("
        <div class='page-content'>
            Meters table was not found.
        </div>
    ");
}

/* =========================================
   COLUMN MAPPING
========================================= */

$serialCol = getCol($conn, 'meters', [
    'serial_number',
    'meter_serial',
    'serial',
    'meter_no'
]);

$zoneCol = getCol($conn, 'meters', [
    'zone',
    'zone_name',
    'location'
]);

$statusCol = getCol($conn, 'meters', [
    'status',
    'meter_status'
]);

$typeCol = getCol($conn, 'meters', [
    'meter_type',
    'type'
]);

$modelCol = getCol($conn, 'meters', [
    'model',
    'meter_model'
]);

$customerCol = getCol($conn, 'meters', [
    'customer_name',
    'name'
]);

$customerTypeCol = getCol($conn, 'meters', [
    'customer_type'
]);

$installCol = getCol($conn, 'meters', [
    'installation_date',
    'date_installed',
    'created_at'
]);

/* =========================================
   FILTERS
========================================= */

$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$zone   = $_GET['zone'] ?? '';
$status = $_GET['status'] ?? '';
$type   = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";

/* =========================================
   DATE FILTERS
========================================= */

if ($from !== '' && $installCol) {

    $safeFrom = $conn->real_escape_string($from);

    $where .= "
        AND DATE(`$installCol`) >= '$safeFrom'
    ";
}

if ($to !== '' && $installCol) {

    $safeTo = $conn->real_escape_string($to);

    $where .= "
        AND DATE(`$installCol`) <= '$safeTo'
    ";
}

/* =========================================
   ZONE FILTER
========================================= */

if ($zone !== '' && $zoneCol) {

    $safeZone = $conn->real_escape_string($zone);

    $where .= "
        AND `$zoneCol` = '$safeZone'
    ";
}

/* =========================================
   STATUS FILTER
========================================= */

if ($status !== '' && $statusCol) {

    $safeStatus = $conn->real_escape_string($status);

    $where .= "
        AND `$statusCol` = '$safeStatus'
    ";
}

/* =========================================
   TYPE FILTER
========================================= */

if ($type !== '' && $typeCol) {

    $safeType = $conn->real_escape_string($type);

    $where .= "
        AND `$typeCol` = '$safeType'
    ";
}

/* =========================================
   SEARCH FILTER
========================================= */

if ($search !== '') {

    $safeSearch = $conn->real_escape_string($search);

    $searchParts = [];

    foreach ([
        $serialCol,
        $zoneCol,
        $statusCol,
        $typeCol,
        $modelCol,
        $customerCol,
        $customerTypeCol
    ] as $col) {

        if ($col) {

            $searchParts[] = "
                `$col` LIKE '%$safeSearch%'
            ";
        }
    }

    if (!empty($searchParts)) {

        $where .= "
            AND (
                " . implode(" OR ", $searchParts) . "
            )
        ";
    }
}

/* =========================================
   DATA
========================================= */

$meters = $conn->query("
    SELECT *
    FROM meters
    $where
    ORDER BY id DESC
");

$filteredMeters = $meters
    ? $meters->num_rows
    : 0;

/* =========================================
   DROPDOWNS
========================================= */

$zones = $zoneCol
    ? $conn->query("
        SELECT DISTINCT `$zoneCol` AS v
        FROM meters
        WHERE `$zoneCol` IS NOT NULL
        AND `$zoneCol`!=''
        ORDER BY `$zoneCol` ASC
    ")
    : null;

$statuses = $statusCol
    ? $conn->query("
        SELECT DISTINCT `$statusCol` AS v
        FROM meters
        WHERE `$statusCol` IS NOT NULL
        AND `$statusCol`!=''
        ORDER BY `$statusCol` ASC
    ")
    : null;

$types = $typeCol
    ? $conn->query("
        SELECT DISTINCT `$typeCol` AS v
        FROM meters
        WHERE `$typeCol` IS NOT NULL
        AND `$typeCol`!=''
        ORDER BY `$typeCol` ASC
    ")
    : null;

?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <!-- =========================================
         FILTERS
    ========================================= -->

    <form method="GET" class="filter-card no-print">

        <input
            type="hidden"
            name="page"
            value="<?= clean($_GET['page'] ?? 'modules/reports/metering_reports.php') ?>"
        >

        <div>

            <label>From</label>

            <input
                type="date"
                name="from"
                value="<?= clean($from) ?>"
            >

        </div>

        <div>

            <label>To</label>

            <input
                type="date"
                name="to"
                value="<?= clean($to) ?>"
            >

        </div>

        <div>

            <label>Zone</label>

            <select name="zone">

                <option value="">
                    All Zones
                </option>

                <?php if ($zones): ?>

                    <?php while($z = $zones->fetch_assoc()): ?>

                        <option
                            value="<?= clean($z['v']) ?>"
                            <?= $zone === $z['v'] ? 'selected' : '' ?>>

                            <?= clean($z['v']) ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

        </div>

        <div>

            <label>Status</label>

            <select name="status">

                <option value="">
                    All Statuses
                </option>

                <?php if ($statuses): ?>

                    <?php while($s = $statuses->fetch_assoc()): ?>

                        <option
                            value="<?= clean($s['v']) ?>"
                            <?= $status === $s['v'] ? 'selected' : '' ?>>

                            <?= clean($s['v']) ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

        </div>

        <div>

            <label>Meter Type</label>

            <select name="type">

                <option value="">
                    All Types
                </option>

                <?php if ($types): ?>

                    <?php while($t = $types->fetch_assoc()): ?>

                        <option
                            value="<?= clean($t['v']) ?>"
                            <?= $type === $t['v'] ? 'selected' : '' ?>>

                            <?= clean($t['v']) ?>

                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

        </div>

        <div class="search-box">

            <label>Search</label>

            <input
                type="text"
                name="search"
                value="<?= clean($search) ?>"
                placeholder="Serial, customer, model, zone..."
            >

        </div>

        <button type="submit">

            Apply Filters

        </button>

        <a
            href="dashboard.php?page=modules/reports/metering_reports.php"
            class="clear-btn">

            Reset

        </a>

        <button
            type="button"
            onclick="printFilteredReport()"
            class="print-btn">

            Print Data

        </button>

    </form>

    <!-- =========================================
         REPORT TABLE
    ========================================= -->

    <div id="printArea">

        <div class="table-panel">

            <div class="report-topic no-print">

                <h3 id="meteringReportTitle">
                    WOWASCO Metering Report
                </h3>

                <p id="meteringReportSummary">
                    Filtered metering records.
                </p>

            </div>

            <!-- =========================================
                 TOOLBAR
            ========================================= -->

            <div class="table-toolbar no-print">

                <div class="table-count">

                    Meter Records:

                    <strong>
                        <?= number_format($filteredMeters) ?>
                    </strong>

                </div>

                <div class="table-actions">

                    <div class="dropdown">

                        <button
                            type="button"
                            class="download-btn">

                            Download Report

                        </button>

                        <div class="dropdown-content">

                            <a href="#"
                               onclick="triggerExport('excel'); return false;">

                                Excel

                            </a>

                            <a href="#"
                               onclick="triggerExport('pdf'); return false;">

                                PDF

                            </a>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =========================================
                 TABLE
            ========================================= -->

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
    border-radius:18px;
    padding:20px;
    margin-bottom:22px;

    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:16px;

    border:1px solid var(--border);

    box-shadow:0 4px 16px rgba(15,23,42,0.05);
}

.filter-card label{
    display:block;
    margin-bottom:6px;
    font-size:13px;
    font-weight:600;
    color:#475569;
}

.filter-card input,
.filter-card select{
    min-width:170px;
    padding:11px 12px;
    border-radius:10px;
    border:1px solid var(--border);
    background:#f8fafc;
}

.search-box input{
    min-width:250px;
}

.download-btn,
.print-btn,
.filter-card button,
.clear-btn{
    border:none;
    background:var(--primary);
    color:#fff;
    padding:11px 18px;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    text-decoration:none;
}

.clear-btn{
    background:#64748b;
}

.table-panel{
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
    overflow-x:auto;
}

.report-topic{
    border-bottom:1px solid var(--border);
    margin-bottom:14px;
    padding-bottom:12px;
}

.report-topic h3{
    margin:0;
    color:var(--primary);
    font-size:17px;
}

.report-topic p{
    margin:5px 0 0;
    color:#64748b;
    font-size:12px;
    line-height:1.5;
}

.table-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:14px;
    flex-wrap:wrap;
}

.table-count{
    font-size:14px;
    color:#475569;
}

.table-count strong{
    color:var(--primary);
    font-size:18px;
}

.table-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:nowrap;
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
    min-width:180px;
    border-radius:12px;
    overflow:hidden;
    border:1px solid var(--border);
    box-shadow:0 12px 20px rgba(0,0,0,0.08);
    z-index:100;
}

.dropdown-content a{
    display:block;
    padding:12px 14px;
    color:#334155;
    text-decoration:none;
    font-size:13px;
}

.dropdown-content a:hover{
    background:#f8fafc;
}

.dropdown:hover .dropdown-content{
    display:block;
}

.dropdown:focus-within .dropdown-content{
    display:block;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

thead{
    background:#f8fafc;
}

th{
    padding:14px 12px;
    text-align:left;
    color:#fff;
    font-size:13px;
    font-weight:700;
    background:var(--primary);
    border-bottom:1px solid var(--primary);
    white-space:nowrap;
}

td{
    padding:14px 12px;
    border-bottom:1px solid #edf2f7;
    color:#475569;
}

tbody tr:hover{
    background:#f8fafc;
}

.dataTables_wrapper{
    margin-top:10px;
}

.dt-container .dt-layout-row:first-child{
    display:flex !important;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    margin:22px 0 34px;
    width:100%;
}

.dataTables_wrapper .dataTables_length{
    margin-bottom:18px !important;
}

.dt-container .dt-layout-row:first-child .dt-layout-cell{
    display:flex !important;
    align-items:center;
    width:auto !important;
}

.dt-container .dt-layout-row:first-child .dt-layout-cell:last-child{
    justify-content:flex-end;
    margin-left:auto;
    width:100% !important;
    margin-top:6px;
}

.dt-container .dt-layout-row:first-child .dt-layout-cell:first-child{
    justify-content:flex-start;
    width:100% !important;
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
    display:flex;
    align-items:center;
    gap:8px;
    white-space:nowrap;
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

.dt-search input,
.dataTables_filter input{
    width:210px;
    max-width:100%;
}

.dt-button{
    display:none !important;
}

@media(max-width:992px){

    .page-content{
        margin-left:0;
        padding:15px;
    }

    .filter-card{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-card input,
    .filter-card select{
        width:100%;
    }

    .search-box input{
        min-width:100%;
    }

    .table-toolbar{
        flex-direction:column;
        align-items:flex-start;
    }

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
        flex-wrap:wrap;
    }

    .dt-search input,
    .dataTables_filter input{
        width:100%;
    }
}

.table-actions .download-btn,
.table-actions .print-btn{
    width:auto;
    flex:0 0 auto;
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

let meteringTable;

function getDynamicTitle(){

    let title = 'WOWASCO Metering Report';

    const zone = $('select[name="zone"]').val();
    const status = $('select[name="status"]').val();
    const type = $('select[name="type"]').val();
    const from = $('input[name="from"]').val();
    const to = $('input[name="to"]').val();
    const search = $('input[name="search"]').val();

    if(zone){
        title += ' - Zone ' + zone;
    }

    if(status){
        title += ' - Status ' + status;
    }

    if(type){
        title += ' - Type ' + type;
    }

    if(from || to){
        title += ' - Period ' + (from || 'Start') + ' to ' + (to || 'Today');
    }

    if(search){
        title += ' - Search ' + search;
    }

    return title;
}

function getFilterSummary(){

    const from = $('input[name="from"]').val() || 'Any';
    const to = $('input[name="to"]').val() || 'Any';
    const zone = $('select[name="zone"]').val() || 'All';
    const status = $('select[name="status"]').val() || 'All';
    const type = $('select[name="type"]').val() || 'All';
    const search = $('input[name="search"]').val() || 'None';
    const tableSearch = meteringTable ? meteringTable.search() || 'None' : 'None';
    const filteredCount = meteringTable
        ? meteringTable.rows({ search:'applied' }).count()
        : <?= (int)$filteredMeters ?>;

    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'Filters: From ' + from + ' | To ' + to + ' | Zone ' + zone + ' | Status ' + status + ' | Type ' + type + ' | Search ' + search + ' | Table Search ' + tableSearch
    ].join('\n');
}

function getExportFilename(){

    return getDynamicTitle()
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');
}

$(document).ready(function(){

    meteringTable = $('#meteringTable').DataTable({

        pageLength: 10,

        lengthMenu: [10,25,50,100],

        ordering: true,

        searching: true,

        paging: true,

        responsive: true,

        dom: 'Blfrtip',

        buttons: [

            {
                extend: 'excelHtml5',

                title: function(){
                    return getDynamicTitle();
                },

                filename: function(){
                    return getExportFilename();
                },

                messageTop: function(){
                    return getFilterSummary();
                },

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

                title: function(){
                    return getDynamicTitle();
                },

                filename: function(){
                    return getExportFilename();
                },

                messageTop: function(){
                    return getFilterSummary();
                },

                orientation: 'landscape',

                pageSize: 'A4',

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
                },

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
            emptyTable: "No meter records found."
        }

    });

    $('#meteringReportTitle').text(getDynamicTitle());
    $('#meteringReportSummary').html(getFilterSummary().replace(/\n/g, '<br>'));

});

function triggerExport(type){

    if(type === 'excel'){

        meteringTable
            .button('.buttons-excel')
            .trigger();
    }

    if(type === 'pdf'){

        meteringTable
            .button('.buttons-pdf')
            .trigger();
    }
}

function printFilteredReport(){

    let headers = [];

    $('#meteringTable thead th').each(function(){

        headers.push($(this).text().trim());

    });

    let rows = meteringTable.rows({
        search:'applied',
        order:'applied',
        page:'all'
    }).data().toArray();

    let tableHtml = `
        <table>

            <thead>

                <tr>

                    ${headers.map(h => `<th>${h}</th>`).join('')}

                </tr>

            </thead>

            <tbody>

                ${
                    rows.length > 0

                    ? rows.map(row => `
                        <tr>
                            ${row.map(cell => `
                                <td>
                                    ${$('<div>').html(cell).text()}
                                </td>
                            `).join('')}
                        </tr>
                    `).join('')

                    :

                    `
                    <tr>

                        <td colspan="${headers.length}">
                            No filtered records found.
                        </td>

                    </tr>
                    `
                }

            </tbody>

        </table>
    `;

    let printWindow = window.open(
        '',
        '',
        'width=1200,height=800'
    );

    printWindow.document.write(`

        <!DOCTYPE html>

        <html>

        <head>

            <title>${getDynamicTitle()}</title>

            <style>

                body{
                    font-family:Arial,sans-serif;
                    padding:20px;
                    color:#1f2937;
                }

                .report-title{
                    border-bottom:2px solid #0a2a43;
                    padding-bottom:10px;
                    margin-bottom:15px;
                }

                .report-title h3{
                    margin:0 0 6px;
                    color:#0a2a43;
                }

                .report-title p{
                    margin:3px 0;
                    color:#475569;
                    font-size:12px;
                    line-height:1.5;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                    font-size:12px;
                }

                th,td{
                    border:1px solid #d1d5db;
                    padding:8px;
                    text-align:left;
                    vertical-align:top;
                }

                th{
                    background:#f1f5f9;
                    color:#0a2a43;
                }

                tbody tr:nth-child(even){
                    background:#f8fafc;
                }

            </style>

        </head>

        <body>

            <div class="report-title">
                <h3>${getDynamicTitle()}</h3>
                <p>${getFilterSummary().replace(/\n/g, '<br>')}</p>
            </div>

            ${tableHtml}

        </body>

        </html>

    `);

    printWindow.document.close();

    printWindow.focus();

    printWindow.print();
}

</script>
