<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

$self = "/wowasco-system/modules/metering/meter_status.php";

$search = $_GET['search'] ?? "";
$filter = $_GET['customer_type'] ?? "";
$status_filter = $_GET['status'] ?? "";
$start_date = $_GET['start_date'] ?? "";
$end_date = $_GET['end_date'] ?? "";

$limit = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

/* AJAX FLAG */
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

/* ================= SOFT DELETE COLUMN CHECK ================= */

$checkColumn = $conn->query("SHOW COLUMNS FROM meters LIKE 'is_deleted'");

if($checkColumn->num_rows == 0){
    $conn->query("
        ALTER TABLE meters
        ADD is_deleted TINYINT(1) DEFAULT 0
    ");
}

/* ================= SOFT DELETE ================= */

if(isset($_POST['delete_id'])){

    $id = (int)$_POST['delete_id'];

    $stmt = $conn->prepare("
        UPDATE meters
        SET is_deleted = 1
        WHERE id = ?
    ");

    $stmt->bind_param("i",$id);
    $stmt->execute();

    header("Location: meter_status.php?deleted=1");
    exit;
}

/* QUERY STRING FOR EXPORTS */
$queryString = http_build_query([
    'search' => $search,
    'customer_type' => $filter,
    'status' => $status_filter,
    'start_date' => $start_date,
    'end_date' => $end_date
]);

/* QUERY */
$sql = "SELECT * FROM meters WHERE is_deleted = 0";

$params = [];
$types = "";

if(!empty($search)){
    $sql .= " AND serial_number LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if(!empty($filter)){
    $sql .= " AND customer_type=?";
    $params[] = $filter;
    $types .= "s";
}

if(!empty($status_filter)){
    $sql .= " AND status=?";
    $params[] = $status_filter;
    $types .= "s";
}

if(!empty($start_date) && !empty($end_date)){
    $sql .= " AND installation_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

/* COUNT */
$countStmt = $conn->prepare($sql);

if(!empty($params)){
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$total_records = $countResult->num_rows;

/* PAGINATION */
$sql .= " LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$types .= "ii";

$stmt = $conn->prepare($sql);

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Meter Status</title>

<style>

body{
    font-family:'Segoe UI';
    background:#eef3f8;
    margin:0;
}

/* =========================
   TYPOGRAPHY SYSTEM UPGRADE
========================= */

body{
    font-family:'Segoe UI', system-ui, sans-serif;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
    color:#1f2937;
}

h2{
    font-size:20px;
    font-weight:700;
    letter-spacing:0.3px;
    color:#0f172a;
    margin-bottom:18px;
}

.summary{
    font-size:13px;
    font-weight:600;
    color:#334155;
    margin-bottom:10px;
}

table{
    font-size:13.2px;
    font-weight:500;
    letter-spacing:0.2px;
}

th{
    font-size:13px;
    font-weight:700;
    letter-spacing:0.4px;
    text-transform:uppercase;
}

td{
    font-size:13px;
    font-weight:500;
    color:#1f2937;
}

input, select{
    font-family:inherit;
    font-size:13.2px;
    font-weight:500;
    letter-spacing:0.2px;
    color:#111827;
}

button{
    font-size:13px;
    font-weight:600;
    letter-spacing:0.3px;
}

.pagination a{
    font-size:13px;
    font-weight:500;
}

.status-badge{
    font-size:12px;
    font-weight:600;
    letter-spacing:0.2px;
}

.page-wrapper{
    margin-left:240px;
    padding-top:80px;
    min-height:100vh;
    display:flex;
    justify-content:center;
}

.container{
    width:100%;
    max-width:1200px;
    margin:30px;
}

.controls{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.06);
    margin-bottom:20px;
}

.form-row{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}

input,select{
    padding:6px 8px;
    border:1px solid #ccc;
    border-radius:6px;
    font-size:13px;
}

/* BUTTONS */
button{
    background:linear-gradient(135deg,#1e7d4f,#249c63);
    color:white;
    border:none;
    padding:7px 12px;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
    box-shadow:0 4px 10px rgba(30,125,79,0.12);
    transition:all 0.2s ease;
}

button:hover{
    transform:translateY(-1px);
    box-shadow:0 6px 14px rgba(30,125,79,0.18);
}

/* TABLE */

.table-wrapper{
    width:100%;
    overflow-x:auto;
    border-radius:14px;
    background:white;
    box-shadow:0 4px 12px rgba(0,0,0,0.04);
}

table{
    width:100%;
    min-width:1400px;
    border-collapse:collapse;
    background:white;
}

th{
    background:#f8fafc;
    color:#334155;
    padding:14px 12px;
    white-space:nowrap;
    text-align:left;
    border-bottom:2px solid #e2e8f0;
}

td{
    padding:14px 12px;
    border-bottom:1px solid #eee;
    white-space:nowrap;
}

/* STATUS */

.status-badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
}

.active{
    background:#e6f7ec;
    color:#1e7e34;
}

.inactive{
    background:#fdecea;
    color:#c0392b;
}

/* DOWNLOAD DROPDOWN */

.download-wrapper{
    position:relative;
    display:inline-block;
}

.download-btn{
    background:linear-gradient(135deg,#f1c40f,#f4d03f);
    color:#1f2937;
    border-left:3px solid #d4a017;
}

.download-menu{
    display:none;
    position:absolute;
    top:110%;
    left:0;
    background:white;
    min-width:180px;
    border-radius:6px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);
    overflow:hidden;
    z-index:1000;
}

.download-menu a{
    display:block;
    padding:10px;
    text-decoration:none;
    border-left:3px solid transparent;
    color:#334155;
    transition:0.2s ease;
}

.download-menu a:hover{
    background:#fffbeb;
    border-left:3px solid #f1c40f;
}

/* PAGINATION */

.pagination{
    margin-top:20px;
    text-align:center;
}

.pagination a{
    padding:7px 11px;
    margin:2px;
    background:white;
    color:#475569;
    text-decoration:none;
    border-radius:6px;
    border:1px solid #dbe2ea;
    transition:0.2s ease;
}

.pagination a.active{
    background:#ecfdf3;
    border-color:#22c55e;
    color:#15803d;
    font-weight:700;
}

.pagination a.arrow{
    background:#fffbeb;
    border-color:#facc15;
    color:#ca8a04;
    font-weight:700;
}

/* ================= ACTION MENU ================= */

.action-wrapper{
    position:relative;
}

.action-btn{
    width:36px;
    height:36px;
    border:none;
    border-radius:10px;
    background:#ffffff;
    border:1px solid #dbe2ea;
    cursor:pointer;
    font-size:18px;
    font-weight:700;
    color:#475569;
    transition:all 0.2s ease;
}

.action-btn:hover{
    background:#ecfdf3;
    border-color:#86efac;
    color:#15803d;
}

.action-menu{
    display:none;
    position:absolute;
    right:0;
    top:42px;
    width:180px;
    background:white;
    border-radius:14px;
    border:1px solid #e2e8f0;
    box-shadow:0 12px 30px rgba(15,23,42,0.12);
    overflow:hidden;
    z-index:999;
}

.action-menu a,
.action-menu button{
    width:100%;
    display:block;
    padding:12px 14px;
    background:none;
    border:none;
    text-align:left;
    text-decoration:none;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
    color:#334155;
}

.action-menu a:hover,
.action-menu button:hover{
    background:#fffbeb;
}
/* SOFT ENTERPRISE PANELS */

.controls,
.table-wrapper{
    border:1px solid #e8edf3;
}

tr:hover td{
    background:#fcfdfd;
}
/* ================= PRINT HEADER ================= */

.print-header{
    display:none;
}

.print-header h1{
    margin:0;
    font-size:24px;
    color:#0f172a;
}

.print-meta{
    margin-top:10px;
    display:flex;
    flex-wrap:wrap;
    gap:16px;
    font-size:12px;
    color:#475569;
}

/* ================= PRINT MODE ================= */

@media print{

    body{
        background:white;
        margin:0;
        padding:0;
    }

    .sidebar,
    .navbar,
    .controls,
    .pagination,
    .action-wrapper,
    .download-wrapper,
    button{
        display:none !important;
    }

    .page-wrapper{
        margin:0;
        padding:0;
    }

    .container{
        width:100%;
        max-width:100%;
        margin:0;
        padding:0;
        box-shadow:none;
        border:none;
    }

    .table-wrapper{
        overflow:visible;
        box-shadow:none;
        border:none;
    }

    table{
        width:100%;
        min-width:100%;
    }

    th{
        background:#f1f5f9 !important;
        color:#111827 !important;
        border:1px solid #dbe2ea;
    }

    td{
        border:1px solid #e5e7eb;
        color:#111827;
    }

    .print-header{
        display:block;
        margin-bottom:24px;
        border-bottom:2px solid #dbe2ea;
        padding-bottom:12px;
    }

    .summary{
        margin-bottom:14px;
        font-size:13px;
        color:#111827;
    }

    .status-badge{
        border:1px solid #d1d5db;
    }
}
/* HIDE ACTION COLUMN DURING PRINT */

table th:last-child,
table td:last-child{
    display:none;
}
</style>
</head>

<body>

<div class="overlay">

<div class="page-wrapper">
<div class="container">

<h2 style="text-align:center;">
Smart Meter Status Report
</h2>

<!-- FILTERS -->

<div class="controls">

<form id="filterForm">

<div class="form-row">

<input
type="text"
name="search"
placeholder="Search Serial"
value="<?php echo htmlspecialchars($search); ?>">

<select name="customer_type">
<option value="">All Types</option>
<option value="Residential">Residential</option>
<option value="Commercial">Commercial</option>
<option value="Government Entities">Government Entities</option>
</select>

<select name="status">
<option value="">All Status</option>
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
</select>

<span>From:</span>

<input
type="date"
name="start_date"
value="<?php echo $start_date; ?>">

<span>To:</span>

<input
type="date"
name="end_date"
value="<?php echo $end_date; ?>">

<button type="submit">
Filter
</button>

<!-- DOWNLOAD -->

<div class="download-wrapper">

<button
type="button"
class="download-btn"
onclick="toggleDownload()">

Download ▾

</button>

<div id="downloadMenu" class="download-menu">

<a href="/wowasco-system/modules/metering/export_excel.php?<?php echo $queryString; ?>">
Excel
</a>

<a href="/wowasco-system/modules/metering/export_pdf.php?<?php echo $queryString; ?>">
PDF
</a>

</div>

</div>

<button
type="button"
onclick="printFilteredReport()">

🖨 Print Report

</button>

</div>

<input type="hidden" name="ajax" value="1">

</form>

</div>


<!-- RESULTS -->

<div id="resultsArea">

<div class="print-header">

<h1>WOWASCO Smart Meter Status Report</h1>

<div class="print-meta">

<?php if(!empty($filter)): ?>
<span>
<strong>Customer Type:</strong>
<?= htmlspecialchars($filter); ?>
</span>
<?php endif; ?>

<?php if(!empty($status_filter)): ?>
<span>
<strong>Status:</strong>
<?= htmlspecialchars($status_filter); ?>
</span>
<?php endif; ?>

<?php if(!empty($search)): ?>
<span>
<strong>Serial Search:</strong>
<?= htmlspecialchars($search); ?>
</span>
<?php endif; ?>

<?php if(!empty($start_date) && !empty($end_date)): ?>
<span>
<strong>Date Range:</strong>
<?= htmlspecialchars($start_date); ?>
-
<?= htmlspecialchars($end_date); ?>
</span>
<?php endif; ?>

<span>
<strong>Generated:</strong>
<?= date('d M Y H:i'); ?>
</span>

</div>

</div>

<div class="summary">
Total Records: <?php echo $total_records;?>
</div>

<div class="table-wrapper">

<table>

<tr>
<th>Serial</th>
<th>Model</th>
<th>Name</th>
<th>ID</th>
<th>Phone</th>
<th>Alt</th>
<th>Type</th>
<th>Meter</th>
<th>Date purchased</th>
<th>Zone</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($row=$result->fetch_assoc()):

$status = $row['status'] ?? 'Inactive';

$class = $status == 'Active'
? 'active'
: 'inactive';

?>

<tr>

<td><?= $row['serial_number']; ?></td>
<td><?= $row['model']; ?></td>
<td><?= $row['customer_name']; ?></td>
<td><?= $row['national_id']; ?></td>
<td><?= $row['customer_phone']; ?></td>
<td><?= $row['alternative_phone']; ?></td>
<td><?= $row['customer_type']; ?></td>
<td><?= $row['meter_type']; ?></td>
<td><?= $row['installation_date']; ?></td>
<td><?= $row['zone']; ?></td>

<td>

<span class="status-badge <?= $class;?>">
<?= $status;?>
</span>

</td>

<td>

<div class="action-wrapper">

<button
class="action-btn"
onclick="toggleActionMenu(this)">

⋮

</button>

<div class="action-menu">

<a href="/wowasco-system/dashboard.php?page=modules/metering/meter_register.php&edit=<?= $row['id']; ?>">
Edit Meter
</a>

<form
method="POST"
onsubmit="return confirm('Move this meter to archive?')">

<input
type="hidden"
name="delete_id"
value="<?= $row['id']; ?>">

<button type="submit">
Soft Delete
</button>

</form>

</div>

</div>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>

<!-- PAGINATION -->

<div class="pagination">

<?php

$total_pages = ceil($total_records / $limit);

/* PREVIOUS */

if($page > 1){

echo '<a class="arrow" href="?page_num='.($page-1).'&'.http_build_query($_GET).'">◀</a>';

}

/* PAGES */

for($i=1; $i<=$total_pages; $i++){

$active = ($i == $page)
? "active"
: "";

echo '<a class="'.$active.'" href="?page_num='.$i.'&'.http_build_query($_GET).'">'.$i.'</a>';

}

/* NEXT */

if($page < $total_pages){

echo '<a class="arrow" href="?page_num='.($page+1).'&'.http_build_query($_GET).'">▶</a>';

}

?>

</div>

</div>

</div>
</div>

<script>

function submitFilters(page=1){

const form = document.getElementById("filterForm");

const data = new FormData(form);

data.set("page_num", page);
data.set("ajax", "1");

fetch("<?php echo $self; ?>?" + new URLSearchParams(data))

.then(res => res.text())

.then(html => {

const doc = new DOMParser()
.parseFromString(html,"text/html");

document.getElementById("resultsArea").innerHTML =
doc.querySelector("#resultsArea").innerHTML;

});

}

document.getElementById("filterForm")
.addEventListener("submit", e=>{

e.preventDefault();

submitFilters(1);

});

document.addEventListener("click", e=>{

if(e.target.closest(".pagination a")){

e.preventDefault();

let page = new URL(e.target.href)
.searchParams.get("page_num");

submitFilters(page);

}

});

/* DOWNLOAD */

function toggleDownload(){

let menu = document.getElementById("downloadMenu");

menu.style.display =
menu.style.display === "block"
? "none"
: "block";

}

document.addEventListener("click", e=>{

if(!e.target.closest(".download-wrapper")){

document.getElementById("downloadMenu")
.style.display = "none";

}

});

/* ================= ACTION MENU ================= */

function toggleActionMenu(btn){

let menu = btn.nextElementSibling;

document.querySelectorAll(".action-menu")
.forEach(m => {

if(m !== menu){
m.style.display = "none";
}

});

menu.style.display =
menu.style.display === "block"
? "none"
: "block";

}

document.addEventListener("click", function(e){

if(!e.target.closest(".action-wrapper")){

document.querySelectorAll(".action-menu")
.forEach(menu => {
menu.style.display = "none";
});

}

});
/* ================= PRINT FILTERED REPORT ================= */

function printFilteredReport(){
    window.print();
}

</script>

</body>
</html>

<?php if($isAjax){ exit; } ?>