<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= SAFE CHECK FOR SOFT DELETE COLUMN ================= */
$checkColumn = $conn->query("SHOW COLUMNS FROM assets LIKE 'is_deleted'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE assets ADD is_deleted TINYINT DEFAULT 0");
}

/* ================= SOFT DELETE ================= */
if (isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $conn->prepare("UPDATE assets SET is_deleted=1 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: view_asset.php?deleted=1");
    exit;
}

/* ================= FILTERS ================= */
$typeFilter = $_GET['type'] ?? '';
$subtypeFilter = $_GET['subtype'] ?? '';

$sql = "SELECT * FROM assets WHERE is_deleted=0";

if (!empty($typeFilter)) {
    $sql .= " AND asset_type='" . $conn->real_escape_string($typeFilter) . "'";
}

if (!empty($subtypeFilter)) {
    $sql .= " AND subtype='" . $conn->real_escape_string($subtypeFilter) . "'";
}

$sql .= " ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Assets Dashboard</title>

<style>
body{
    margin:0;
    font-family:"Segoe UI";
    background:#eef3fb;
}

/* HEADER */
.header{
    background:linear-gradient(135deg,#0b2d5c,#0b3d91);
    color:white;
    padding:18px 25px;
    font-size:20px;
    font-weight:700;
}

/* LAYOUT (SIDEBAR + NAVBAR COMPATIBLE) */
.container{
    padding:25px;
    margin-left:240px;
    margin-top:70px;
}

/* FILTER */
.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    background:white;
    padding:12px;
    border-radius:10px;
    margin-bottom:15px;
}

.filters select{
    padding:7px;
    border-radius:6px;
}

/* BUTTONS */
.btn{
    padding:8px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-weight:600;
}

.btn-primary{background:#0b3d91;color:white;}

/* TABLE */
.table-card{
    background:white;
    border-radius:12px;
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#0b2d5c;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    border-bottom:1px solid #eee;
}

/* ACTION */
.action-wrapper{
    position:relative;
}

.action-btn{
    background:#0b3d91;
    color:white;
    border:none;
    padding:6px 10px;
    border-radius:6px;
    cursor:pointer;
}

.dropdown-menu{
    display:none;
    position:absolute;
    right:0;
    top:35px;
    background:white;
    border-radius:8px;
    box-shadow:0 6px 15px rgba(0,0,0,0.15);
    min-width:140px;
    z-index:999;
}

.dropdown-menu a,
.dropdown-menu button{
    display:block;
    width:100%;
    padding:10px;
    border:none;
    background:none;
    text-align:left;
    cursor:pointer;
    font-size:13px;
    text-decoration:none;
    color:#333;
}

.dropdown-menu a:hover,
.dropdown-menu button:hover{
    background:#f1f6ff;
}

/* BACK */
.back{
    display:inline-block;
    margin-top:15px;
    padding:8px 12px;
    background:#0b2d5c;
    color:white;
    text-decoration:none;
    border-radius:6px;
}
</style>
</head>

<body>

<div class="header">Asset Management Dashboard</div>

<div class="container">

<?php if(isset($_GET['deleted'])): ?>
<div style="background:#d1fae5;color:#065f46;padding:10px;border-radius:8px;margin-bottom:10px;">
Asset moved to archive successfully
</div>
<?php endif; ?>

<!-- FILTER -->
<form method="GET" class="filters">

<select name="type">
    <option value="">All Types</option>
    <option value="Field Asset">Field Asset</option>
    <option value="Office Asset">Office Asset</option>
    <option value="Smart Meter">Smart Meter</option>
</select>

<select name="subtype">
    <option value="">All Subtypes</option>
    <option value="Fixed Asset">Fixed Asset</option>
    <option value="Digital Asset">Digital Asset</option>
</select>

<button class="btn btn-primary">Filter</button>
</form>

<!-- TABLE -->
<div class="table-card">
<table>

<tr>
<th>Name</th>
<th>Serial</th>
<th>Location</th>
<th>Asset Value</th>
<th>Current Value (5yr)</th>
<th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>

<?php
$assetValue = (float)$row['asset_value'];
$years = (new DateTime($row['purchase_date']))->diff(new DateTime())->y;

/* STRAIGHT-LINE DEPRECIATION (5 YEARS) */
$annualDepreciation = $assetValue / 5;
$currentValue = max(0, $assetValue - ($annualDepreciation * $years));
?>

<tr>
<td><?= $row['asset_name'] ?></td>
<td><?= $row['serial_number'] ?></td>
<td><?= $row['location'] ?></td>
<td><?= number_format($assetValue) ?></td>
<td><?= number_format($currentValue) ?></td>

<td>
<div class="action-wrapper">
<button class="action-btn" onclick="toggleMenu(this)">⋮</button>

<div class="dropdown-menu">

<!-- FIXED EDIT PATH (RELATIVE SAFE ROUTE) -->
<a href="modules/Assets/add_asset.php?edit=<?= $row['id'] ?>">Edit</a>

<form method="POST" onsubmit="return confirm('Move asset to archive?')">
    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
    <button type="submit">Delete</button>
</form>

</div>
</div>
</td>
</tr>

<?php endwhile; ?>

</table>
</div>

<a class="back" href="/wowasco/index.php">← Back</a>

</div>

<script>
function toggleMenu(btn){
    let menu = btn.nextElementSibling;

    document.querySelectorAll(".dropdown-menu").forEach(m=>{
        if(m !== menu) m.style.display = "none";
    });

    menu.style.display = menu.style.display === "block" ? "none" : "block";
}

document.addEventListener("click", function(e){
    if(!e.target.closest(".action-wrapper")){
        document.querySelectorAll(".dropdown-menu").forEach(m=>{
            m.style.display = "none";
        });
    }
});
</script>

</body>
</html>