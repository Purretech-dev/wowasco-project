```php
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

function rowValue($row, $col){
    return $col && isset($row[$col]) ? $row[$col] : '';
}

function money($amount){
    return "KSh " . number_format((float)$amount, 2);
}

if (!tableExists($conn, 'assets')) {
    die("<div class='page-content'>Assets table was not found.</div>");
}

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

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$search = $_GET['search'] ?? '';

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

$assets = $conn->query("
    SELECT *
    FROM assets
    $where
    ORDER BY id DESC
");

$reportRows = [];

if ($assets) {
    while ($a = $assets->fetch_assoc()) {
        $assetValue = $valueCol ? (float)rowValue($a, $valueCol) : 0;
        $netValue = $netValueCol ? (float)rowValue($a, $netValueCol) : $assetValue;

        $reportRows[] = [
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

$filteredRecords = count($reportRows);

$types = $typeCol
    ? $conn->query("SELECT DISTINCT `$typeCol` AS v FROM assets WHERE `$typeCol` IS NOT NULL AND `$typeCol`!='' ORDER BY `$typeCol` ASC")
    : null;

$statuses = $statusCol
    ? $conn->query("SELECT DISTINCT `$statusCol` AS v FROM assets WHERE `$statusCol` IS NOT NULL AND `$statusCol`!='' ORDER BY `$statusCol` ASC")
    : null;

$locations = $locationCol
    ? $conn->query("SELECT DISTINCT `$locationCol` AS v FROM assets WHERE `$locationCol` IS NOT NULL AND `$locationCol`!='' ORDER BY `$locationCol` ASC")
    : null;
?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<div class="page-content">

    <div class="module-header no-print">
        <div>
            <h2>Asset Reports</h2>
            <p>Filtered asset register reporting for asset value, net value, status, type, location and purchase date.</p>
        </div>
    </div>

    <form method="GET" class="filter-card no-print">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'modules/reports/asset_reports.php') ?>">

        <div class="filter-field">
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div class="filter-field">
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <div class="filter-field">
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

        <div class="filter-field">
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

        <div class="filter-field search-box">
            <label>Search</label>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Asset name, serial, type, location...">
        </div>

        <div class="filter-actions">
            <button type="submit">Apply Filters</button>
            <a href="dashboard.php?page=modules/reports/asset_reports.php" class="clear-btn">Reset</a>
        </div>
    </form>

    <div class="table-panel">
        <div class="table-toolbar no-print">
            <div class="table-count">
                <span>Asset Records</span>
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
            <table id="assetTable" class="display nowrap">
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

#assetTable{
    width:100% !important;
    border-collapse:separate;
    border-spacing:0;
    font-size:14px;
    background:#fff;
}

#assetTable thead th,
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

#assetTable thead th:first-child{
    border-top-left-radius:6px;
}

#assetTable thead th:last-child{
    border-top-right-radius:6px;
}

#assetTable thead th.dt-orderable-asc span.dt-column-order:before,
#assetTable thead th.dt-orderable-desc span.dt-column-order:after,
#assetTable thead th.dt-ordering-asc span.dt-column-order:before,
#assetTable thead th.dt-ordering-desc span.dt-column-order:after{
    color:#fff !important;
    opacity:0.85 !important;
}

#assetTable tbody td,
table.dataTable.display > tbody > tr > td{
    padding:12px 12px !important;
    border-bottom:1px solid #edf2f7 !important;
    color:#475569 !important;
    vertical-align:middle;
    white-space:nowrap;
}

#assetTable tbody tr:nth-child(even){
    background:#f8fafc;
}

#assetTable tbody tr:hover{
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
let assetTable;

function getDynamicTitle(){
    let title = 'WOWASCO Asset Report';

    const type = $('select[name="type"]').val();
    const status = $('select[name="status"]').val();
    const location = $('select[name="location"]').val();

    if(type) title += ' - Type ' + type;
    if(status) title += ' - Status ' + status;
    if(location) title += ' - Location ' + location;

    return title;
}

function getFilterSummaryLines(){
    const from = $('input[name="from"]').val() || 'Any';
    const to = $('input[name="to"]').val() || 'Any';
    const type = $('select[name="type"]').val() || 'All';
    const status = $('select[name="status"]').val() || 'All';
    const location = $('select[name="location"]').val() || 'All';
    const search = $('input[name="search"]').val() || 'None';
    const tableSearch = assetTable ? (assetTable.search() || 'None') : 'None';
    const filteredCount = assetTable ? assetTable.rows({ search:'applied' }).count() : <?= (int)$filteredRecords ?>;

    return [
        'Generated: ' + new Date().toLocaleString(),
        'Filtered Records: ' + filteredCount,
        'From: ' + from + ' | To: ' + to,
        'Type: ' + type + ' | Status: ' + status + ' | Location: ' + location,
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
    assetTable = $('#assetTable').DataTable({
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
            emptyTable: "No asset records found for the selected filters."
        }
    });
});

function triggerExport(type){
    if(!assetTable){
        alert('The report table is still loading. Please try again.');
        return;
    }

    if(type === 'excel'){
        assetTable.button('.buttons-excel').trigger();
    }

    if(type === 'pdf'){
        assetTable.button('.buttons-pdf').trigger();
    }
}

function escapeHtml(value){
    return $('<div>').text(value ?? '').html();
}

function printFilteredReport(){
    if(!assetTable){
        alert('The report table is still loading. Please try again.');
        return;
    }

    let headers = [];

    $('#assetTable thead th').each(function(){
        headers.push($(this).text().trim());
    });

    let rows = assetTable.rows({
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
```