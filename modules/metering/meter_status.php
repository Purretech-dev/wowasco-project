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

/* DELETE */
if(isset($_GET['delete_id'])){
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM meters WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    header("Location: $self?deleted=1");
    exit();
}

$deleted = isset($_GET['deleted']);

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
body{font-family:'Segoe UI';background:#eef3f8;margin:0;}

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

button{
    background:#003366;
    color:white;
    border:none;
    padding:6px 10px;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
}

.download-wrapper{position:relative;display:inline-block;}
.download-btn{background:#198754;color:white;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;}
.download-menu{
    display:none;position:absolute;background:white;min-width:160px;
    box-shadow:0 4px 10px rgba(0,0,0,0.15);border-radius:6px;z-index:999;
}
.download-menu a{display:block;padding:8px;text-decoration:none;color:#333;}
.download-menu a:hover{background:#f1f1f1;}

.print-btn{
    background:#6c757d;color:white;border:none;padding:6px 10px;border-radius:6px;
}

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

.status-badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
}

.active{background:#e6f7ec;color:#1e7e34;}
.inactive{background:#fdecea;color:#c0392b;}

.pagination{
    margin-top:20px;
    text-align:center;
}

.pagination a{
    padding:6px 10px;
    margin:2px;
    background:#003366;
    color:white;
    text-decoration:none;
    border-radius:4px;
}

.pagination a.active{
    background:#198754;
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
<div class="download-wrapper">
    <button type="button" class="download-btn" onclick="toggleDownload()">Download</button>

    <div id="downloadMenu" class="download-menu">

        <a href="/wowasco-system/modules/metering/export_excel.php?<?php echo $queryString; ?>">
            Excel
        </a>

        <a href="/wowasco-system/modules/metering/export_pdf.php?<?php echo $queryString; ?>">
            PDF
        </a>

    </div>
</div>

<button type="button" class="print-btn" onclick="window.print()">Print</button>

</div>

<input type="hidden" name="ajax" value="1">

</form>
</div>

<!-- AJAX AREA -->
<div id="resultsArea">

<?php if(!$isAjax): ?>
<div class="summary">
Total Records: <?php echo $total_records;?>
</div>
<?php endif; ?>

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
<td><?php echo $row['serial_number'];?></td>
<td><?php echo $row['model'];?></td>
<td><?php echo $row['customer_name'];?></td>
<td><?php echo $row['national_id'];?></td>
<td><?php echo $row['customer_phone'];?></td>
<td><?php echo $row['alternative_phone'];?></td>
<td><?php echo $row['customer_type'];?></td>
<td><?php echo $row['meter_type'];?></td>
<td><?php echo $row['installation_date'];?></td>
<td><?php echo $row['zone'];?></td>
<td><span class="status-badge <?php echo $class;?>"><?php echo $status;?></span></td>
</tr>

<?php endwhile; ?>

</table>

<?php if(!$isAjax): ?>
<div class="pagination">
<?php
$total_pages = ceil($total_records / $limit);

for($i=1; $i<=$total_pages; $i++):
?>
<a href="?page_num=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
<?php echo $i; ?>
</a>
<?php endfor; ?>
</div>
<?php endif; ?>

</div>

</div>
</div>

<script>

/* FILTER AJAX */
function submitFilters(page=1){

    const form = document.getElementById("filterForm");
    const data = new FormData(form);

    data.set("page_num", page);
    data.set("ajax", "1");

    fetch("<?php echo $self; ?>?" + new URLSearchParams(data))
    .then(res => res.text())
    .then(html => {

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, "text/html");

        const newContent = doc.querySelector("#resultsArea").innerHTML;

        document.getElementById("resultsArea").innerHTML = newContent;
    });
}

/* FORM SUBMIT */
document.getElementById("filterForm").addEventListener("submit", function(e){
    e.preventDefault();
    submitFilters(1);
});

/* PAGINATION */
document.addEventListener("click", function(e){
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

document.addEventListener("click", function(e){
    if(!e.target.closest(".download-wrapper")){
        document.getElementById("downloadMenu").style.display = "none";
    }
});

</script>

</body>
</html>

<?php
if($isAjax){
    exit;
}
?>