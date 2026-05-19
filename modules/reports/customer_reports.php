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

function caseDate($row, $cols) {
    foreach ($cols as $col) {
        if ($col && !empty($row[$col])) return $row[$col];
    }
    return '';
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$caseType = $_GET['case_type'] ?? '';
$status = $_GET['status'] ?? '';
$zone = $_GET['zone'] ?? '';
$search = $_GET['search'] ?? '';

$reportRows = [];
$statusOptions = [];
$zoneOptions = [];

function addOption(&$options, $value) {
    $value = trim((string)$value);
    if ($value !== '') $options[$value] = $value;
}

function includeByFilters($row, $from, $to, $caseType, $status, $zone, $search) {
    if ($caseType !== '' && $row['case_type'] !== $caseType) return false;
    if ($status !== '' && strcasecmp($row['status'], $status) !== 0) return false;
    if ($zone !== '' && strcasecmp($row['zone'], $zone) !== 0) return false;

    $rowDate = substr((string)$row['record_date'], 0, 10);
    if ($from !== '' && $rowDate !== '' && $rowDate < $from) return false;
    if ($to !== '' && $rowDate !== '' && $rowDate > $to) return false;

    if ($search !== '') {
        $haystack = strtolower(implode(' ', $row));
        if (strpos($haystack, strtolower($search)) === false) return false;
    }

    return true;
}

if (tableExists($conn, 'customers')) {
    $nameCol = getCol($conn, 'customers', ['name','customer_name']);
    $phoneCol = getCol($conn, 'customers', ['phone','contact','customer_phone']);
    $emailCol = getCol($conn, 'customers', ['email']);
    $idCol = getCol($conn, 'customers', ['id_number','national_id']);
    $zoneCol = getCol($conn, 'customers', ['zone','zone_name','location']);
    $typeCol = getCol($conn, 'customers', ['customer_type','type']);
    $dateCol = getCol($conn, 'customers', ['created_at','registered_at']);

    $res = $conn->query("SELECT * FROM customers ORDER BY id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $row = [
                'case_type' => 'Registered Customer',
                'reference' => 'CUST-' . ($r['id'] ?? ''),
                'customer_name' => rowValue($r, $nameCol),
                'contact' => rowValue($r, $phoneCol),
                'email' => rowValue($r, $emailCol),
                'id_number' => rowValue($r, $idCol),
                'zone' => rowValue($r, $zoneCol),
                'category' => rowValue($r, $typeCol),
                'priority' => 'N/A',
                'status' => 'Registered',
                'assigned_staff' => 'N/A',
                'record_date' => rowValue($r, $dateCol)
            ];

            addOption($statusOptions, $row['status']);
            addOption($zoneOptions, $row['zone']);
            if (includeByFilters($row, $from, $to, $caseType, $status, $zone, $search)) $reportRows[] = $row;
        }
    }
}

if (tableExists($conn, 'meter_applications')) {
    $refCol = getCol($conn, 'meter_applications', ['application_ref','reference']);
    $nameCol = getCol($conn, 'meter_applications', ['customer_name','name']);
    $contactCol = getCol($conn, 'meter_applications', ['contact','phone','customer_phone']);
    $idCol = getCol($conn, 'meter_applications', ['id_number','national_id']);
    $zoneCol = getCol($conn, 'meter_applications', ['zone','zone_name']);
    $typeCol = getCol($conn, 'meter_applications', ['customer_type','meter_type']);
    $statusCol = getCol($conn, 'meter_applications', ['status']);
    $staffCol = getCol($conn, 'meter_applications', ['assigned_staff']);
    $dateCol = getCol($conn, 'meter_applications', ['created_at','reviewed_at','updated_at']);

    $res = $conn->query("SELECT * FROM meter_applications ORDER BY id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $row = [
                'case_type' => 'Meter Application',
                'reference' => rowValue($r, $refCol) ?: 'APP-' . ($r['id'] ?? ''),
                'customer_name' => rowValue($r, $nameCol),
                'contact' => rowValue($r, $contactCol),
                'email' => '',
                'id_number' => rowValue($r, $idCol),
                'zone' => rowValue($r, $zoneCol),
                'category' => rowValue($r, $typeCol),
                'priority' => 'N/A',
                'status' => rowValue($r, $statusCol) ?: 'Pending',
                'assigned_staff' => rowValue($r, $staffCol) ?: 'Unassigned',
                'record_date' => rowValue($r, $dateCol)
            ];

            addOption($statusOptions, $row['status']);
            addOption($zoneOptions, $row['zone']);
            if (includeByFilters($row, $from, $to, $caseType, $status, $zone, $search)) $reportRows[] = $row;
        }
    }
}

if (tableExists($conn, 'customer_enquiries')) {
    $refCol = getCol($conn, 'customer_enquiries', ['enquiry_ref','reference']);
    $nameCol = getCol($conn, 'customer_enquiries', ['customer_name','name']);
    $contactCol = getCol($conn, 'customer_enquiries', ['contact','phone']);
    $emailCol = getCol($conn, 'customer_enquiries', ['email']);
    $typeCol = getCol($conn, 'customer_enquiries', ['enquiry_type','subject']);
    $statusCol = getCol($conn, 'customer_enquiries', ['status']);
    $staffCol = getCol($conn, 'customer_enquiries', ['assigned_staff']);
    $dateCol = getCol($conn, 'customer_enquiries', ['created_at','updated_at']);

    $res = $conn->query("SELECT * FROM customer_enquiries ORDER BY id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $row = [
                'case_type' => 'Enquiry',
                'reference' => rowValue($r, $refCol) ?: 'ENQ-' . ($r['id'] ?? ''),
                'customer_name' => rowValue($r, $nameCol),
                'contact' => rowValue($r, $contactCol),
                'email' => rowValue($r, $emailCol),
                'id_number' => '',
                'zone' => '',
                'category' => rowValue($r, $typeCol),
                'priority' => 'N/A',
                'status' => rowValue($r, $statusCol) ?: 'Submitted',
                'assigned_staff' => rowValue($r, $staffCol) ?: 'Unassigned',
                'record_date' => rowValue($r, $dateCol)
            ];

            addOption($statusOptions, $row['status']);
            if (includeByFilters($row, $from, $to, $caseType, $status, $zone, $search)) $reportRows[] = $row;
        }
    }
}

if (tableExists($conn, 'customer_complaints')) {
    $refCol = getCol($conn, 'customer_complaints', ['complaint_ref','reference']);
    $nameCol = getCol($conn, 'customer_complaints', ['customer_name','name']);
    $contactCol = getCol($conn, 'customer_complaints', ['contact','phone']);
    $zoneCol = getCol($conn, 'customer_complaints', ['zone','zone_name']);
    $typeCol = getCol($conn, 'customer_complaints', ['complaint_type','subject']);
    $priorityCol = getCol($conn, 'customer_complaints', ['priority']);
    $statusCol = getCol($conn, 'customer_complaints', ['status']);
    $staffCol = getCol($conn, 'customer_complaints', ['assigned_staff']);
    $dateCol = getCol($conn, 'customer_complaints', ['created_at','updated_at','due_date']);

    $res = $conn->query("SELECT * FROM customer_complaints ORDER BY id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $row = [
                'case_type' => 'Complaint',
                'reference' => rowValue($r, $refCol) ?: 'CMP-' . ($r['id'] ?? ''),
                'customer_name' => rowValue($r, $nameCol),
                'contact' => rowValue($r, $contactCol),
                'email' => '',
                'id_number' => '',
                'zone' => rowValue($r, $zoneCol),
                'category' => rowValue($r, $typeCol),
                'priority' => rowValue($r, $priorityCol) ?: 'N/A',
                'status' => rowValue($r, $statusCol) ?: 'Submitted',
                'assigned_staff' => rowValue($r, $staffCol) ?: 'Unassigned',
                'record_date' => rowValue($r, $dateCol)
            ];

            addOption($statusOptions, $row['status']);
            addOption($zoneOptions, $row['zone']);
            if (includeByFilters($row, $from, $to, $caseType, $status, $zone, $search)) $reportRows[] = $row;
        }
    }
}

ksort($statusOptions);
ksort($zoneOptions);

usort($reportRows, function($a, $b) {
    return strcmp((string)$b['record_date'], (string)$a['record_date']);
});
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">
    <form method="GET" class="filter-card no-print">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'modules/reports/customer_reports.php') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div>
            <label>Case Type</label>
            <select name="case_type">
                <option value="">All Customer Records</option>
                <?php foreach (['Registered Customer','Meter Application','Enquiry','Complaint'] as $type): ?>
                    <option value="<?= clean($type) ?>" <?= $caseType === $type ? 'selected' : '' ?>><?= clean($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statusOptions as $option): ?>
                    <option value="<?= clean($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= clean($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Zone</label>
            <select name="zone">
                <option value="">All Zones</option>
                <?php foreach ($zoneOptions as $option): ?>
                    <option value="<?= clean($option) ?>" <?= $zone === $option ? 'selected' : '' ?>><?= clean($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Customer, contact, reference, status...">
        </div>

        <button type="submit">Apply Filters</button>
        <a class="clear-btn" href="dashboard.php?page=modules/reports/customer_reports.php">Reset</a>
        <button type="button" onclick="printFilteredReport()" class="print-btn">Print Data</button>
    </form>

    <div class="table-panel">
        <div class="table-toolbar no-print">
            <div class="table-count">
                Customer Records:
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

        <table id="customerReportsTable" class="display">
            <thead>
                <tr>
                    <th>Record Type</th>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>ID Number</th>
                    <th>Zone</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned Staff</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td><?= clean($row['case_type']) ?></td>
                        <td><?= clean($row['reference']) ?></td>
                        <td><?= clean($row['customer_name'] ?: 'N/A') ?></td>
                        <td><?= clean($row['contact'] ?: 'N/A') ?></td>
                        <td><?= clean($row['email'] ?: 'N/A') ?></td>
                        <td><?= clean($row['id_number'] ?: 'N/A') ?></td>
                        <td><?= clean($row['zone'] ?: 'Unassigned') ?></td>
                        <td><?= clean($row['category'] ?: 'N/A') ?></td>
                        <td><?= clean($row['priority'] ?: 'N/A') ?></td>
                        <td><?= clean($row['status'] ?: 'N/A') ?></td>
                        <td><?= clean($row['assigned_staff'] ?: 'Unassigned') ?></td>
                        <td><?= clean($row['record_date'] ?: 'N/A') ?></td>
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
let customerReportsTable;

function getCustomerTitle(){
    const type = $('select[name="case_type"]').val();
    const zone = $('select[name="zone"]').val();
    let title = 'WOWASCO Customer Report';
    if(type){ title += ' - ' + type; }
    if(zone){ title += ' - ' + zone; }
    return title;
}

function getCustomerFilterSummary(){
    const filteredCount = customerReportsTable ? customerReportsTable.rows({ search:'applied' }).count() : <?= (int)count($reportRows) ?>;
    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'Filters: From ' + ($('input[name="from"]').val() || 'Any') +
        ' | To ' + ($('input[name="to"]').val() || 'Any') +
        ' | Type ' + ($('select[name="case_type"]').val() || 'All') +
        ' | Status ' + ($('select[name="status"]').val() || 'All') +
        ' | Zone ' + ($('select[name="zone"]').val() || 'All') +
        ' | Search ' + ($('input[name="search"]').val() || 'None') +
        ' | Table Search ' + (customerReportsTable ? (customerReportsTable.search() || 'None') : 'None')
    ].join('\n');
}

function getCustomerFilename(){
    return getCustomerTitle().replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
}

$(document).ready(function(){
    customerReportsTable = $('#customerReportsTable').DataTable({
        pageLength: 10,
        lengthMenu: [10,25,50,100],
        ordering: true,
        searching: true,
        paging: true,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: getCustomerTitle,
                filename: getCustomerFilename,
                messageTop: getCustomerFilterSummary,
                exportOptions: {
                    columns: ':visible',
                    modifier: { search:'applied', order:'applied', page:'all' }
                }
            },
            {
                extend: 'pdfHtml5',
                title: getCustomerTitle,
                filename: getCustomerFilename,
                messageTop: getCustomerFilterSummary,
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
            emptyTable: "No customer report records found."
        }
    });
});

function triggerExport(type){
    if(type === 'excel'){
        customerReportsTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        customerReportsTable.button('.buttons-pdf').trigger();
    }
}

function printFilteredReport(){
    const headers = [];
    $('#customerReportsTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    const rows = customerReportsTable.rows({
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
            <title>WOWASCO Customer Report</title>
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
                <h3>${getCustomerTitle()}</h3>
                <p>${getCustomerFilterSummary().replace(/\n/g, '<br>')}</p>
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
