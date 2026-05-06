<div class="overlay">

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

/* QUERY STRING FOR EXPORTS */
$queryString = http_build_query([
    'search' => $search,
    'customer_type' => $filter,
    'status' => $status_filter,
    'start_date' => $start_date,
    'end_date' => $end_date
]);

/* QUERY */
$sql = "SELECT * FROM meters WHERE 1=1";
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

/* BUTTONS (subtle accent theme) */
button{
    background:#003366;
    color:white;
    border:none;
    padding:6px 10px;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
}

button:hover{
    opacity:0.9;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th{
    background:#003366;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    border-bottom:1px solid #eee;
}

/* STATUS */
.status-badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
}

.active{background:#e6f7ec;color:#1e7e34;}
.inactive{background:#fdecea;color:#c0392b;}

/* DOWNLOAD DROPDOWN */
.download-wrapper{
    position:relative;
    display:inline-block;
}

.download-btn{
    border-left:3px solid #198754;
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
    color:#003366;
    border-left:3px solid transparent;
}

.download-menu a:hover{
    background:#f8f9fa;
    border-left:3px solid #f1c40f;
}

/* PAGINATION */
.pagination{
    margin-top:20px;
    text-align:center;
}

.pagination a{
    padding:6px 10px;
    margin:2px;
    background:white;
    color:#003366;
    text-decoration:none;
    border-radius:4px;
    border:1px solid #003366;
}

/* ACTIVE PAGE */
.pagination a.active{
    border-color:#198754;
    color:#198754;
    font-weight:bold;
}

/* ARROWS */
.pagination a.arrow{
    border-color:#f1c40f;
    color:#c49b00;
    font-weight:bold;
}
</style>
</head>

<body>

<div class="page-wrapper">
<div class="container">

<h2 style="text-align:center;">Smart Meter Status Report</h2>

<!-- FILTERS -->
<div class="controls">
<form id="filterForm">

<div class="form-row">

<input type="text" name="search" placeholder="Search Serial" value="<?php echo htmlspecialchars($search); ?>">

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
<input type="date" name="start_date" value="<?php echo $start_date; ?>">

<span>To:</span>
<input type="date" name="end_date" value="<?php echo $end_date; ?>">

<button type="submit">Filter</button>

<!-- DOWNLOAD -->
<div class="download-wrapper">
    <button type="button" class="download-btn" onclick="toggleDownload()">Download ▾</button>

    <div id="downloadMenu" class="download-menu">
        <a href="/wowasco-system/modules/metering/export_excel.php?<?php echo $queryString; ?>">
            Export to Excel
        </a>
        <a href="/wowasco-system/modules/metering/export_pdf.php?<?php echo $queryString; ?>">
            Export to PDF
        </a>
    </div>
</div>

<button type="button" onclick="window.print()">Print</button>

</div>

<input type="hidden" name="ajax" value="1">

</form>
</div>

<!-- TABLE -->
<div id="resultsArea">

<div class="summary">
Total Records: <?php echo $total_records;?>
</div>

<table>
<tr>
<th>Serial</th><th>Model</th><th>Name</th><th>ID</th>
<th>Phone</th><th>Alt</th><th>Type</th><th>Meter</th>
<th>Date</th><th>Zone</th><th>Status</th>
</tr>

<?php while($row=$result->fetch_assoc()):
$status=$row['status']??'Inactive';
$class=$status=='Active'?'active':'inactive';
?>

<tr>
<td><?= $row['serial_number'];?></td>
<td><?= $row['model'];?></td>
<td><?= $row['customer_name'];?></td>
<td><?= $row['national_id'];?></td>
<td><?= $row['customer_phone'];?></td>
<td><?= $row['alternative_phone'];?></td>
<td><?= $row['customer_type'];?></td>
<td><?= $row['meter_type'];?></td>
<td><?= $row['installation_date'];?></td>
<td><?= $row['zone'];?></td>
<td><span class="status-badge <?= $class;?>"><?= $status;?></span></td>
</tr>

<?php endwhile; ?>

</table>

<!-- PAGINATION (ALWAYS VISIBLE) -->
<div class="pagination">
<?php
$total_pages = ceil($total_records / $limit);

/* PREVIOUS */
if($page > 1){
    echo '<a class="arrow" href="?page_num='.($page-1).'&'.http_build_query($_GET).'">◀</a>';
}

/* PAGES */
for($i=1; $i<=$total_pages; $i++){
    $active = ($i == $page) ? "active" : "";
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
        const doc = new DOMParser().parseFromString(html,"text/html");
        document.getElementById("resultsArea").innerHTML =
        doc.querySelector("#resultsArea").innerHTML;
    });
}

document.getElementById("filterForm").addEventListener("submit", e=>{
    e.preventDefault();
    submitFilters(1);
});

document.addEventListener("click", e=>{
    if(e.target.closest(".pagination a")){
        e.preventDefault();
        let page = new URL(e.target.href).searchParams.get("page_num");
        submitFilters(page);
    }
});

/* DOWNLOAD */
function toggleDownload(){
    let menu = document.getElementById("downloadMenu");
    menu.style.display = menu.style.display === "block" ? "none" : "block";
}

document.addEventListener("click", e=>{
    if(!e.target.closest(".download-wrapper")){
        document.getElementById("downloadMenu").style.display = "none";
    }
});
</script>

</body>
</html>

<?php if($isAjax){ exit; } ?>