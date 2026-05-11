<?php
require_once __DIR__ . '/../../api/db.php';

/* =====================================================
   SAFE SESSION START
===================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['username'] ?? 'Admin';

/* =====================================================
   SAVE / UPDATE ASSET
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $asset_name = trim($_POST['asset_name']);
    $asset_type = trim($_POST['asset_type']);
    $subtype = trim($_POST['subtype']);
    $location = trim($_POST['location']);
    $status = trim($_POST['status']);

    $asset_value = (float) $_POST['asset_value'];
    $purchase_date = $_POST['purchase_date'];

    $last_service_date = $_POST['last_service_date'];
    $next_service_date = $_POST['next_service_date'];

    $maintenance_cost = (float) $_POST['maintenance_cost'];
    $maintenance_notes = trim($_POST['maintenance_notes']);

    $useful_life = (int) $_POST['useful_life'];

    if ($asset_type === 'Smart Meter') {
        $serial_number = $_POST['meter_serial'] ?? '';
    } else {
        $serial_number = trim($_POST['manual_serial'] ?? '');
    }

    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($asset_value < 0) {
        die("Asset value cannot be negative");
    }

    if ($purchase_date > date('Y-m-d')) {
        die("Purchase date cannot be in the future");
    }

    /* =====================================================
       DUPLICATE SERIAL CHECK
    ===================================================== */

    $editId = $_POST['edit_id'] ?? 0;

    $check = $conn->prepare("
        SELECT id 
        FROM assets 
        WHERE serial_number=? 
        AND id != ?
    ");

    $check->bind_param("si", $serial_number, $editId);
    $check->execute();

    $dup = $check->get_result();

    if ($dup->num_rows > 0) {
        die("Duplicate serial number detected");
    }

    /* =====================================================
       DEPRECIATION
    ===================================================== */

    $today = new DateTime();
    $purchase = new DateTime($purchase_date);

    $years_used = $purchase->diff($today)->y;

    $annual_depreciation = $asset_value / max($useful_life, 1);

    $accumulated_depreciation = min(
        $annual_depreciation * $years_used,
        $asset_value
    );

    $current_value = $asset_value - $accumulated_depreciation;

    /* =====================================================
       IMAGE UPLOAD
    ===================================================== */

    $imagePath = '';

    if (!empty($_FILES['asset_image']['name'])) {

        $uploadDir = "../../uploads/assets/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['asset_image']['name']);

        $targetFile = $uploadDir . $fileName;

        move_uploaded_file(
            $_FILES['asset_image']['tmp_name'],
            $targetFile
        );

        $imagePath = $targetFile;
    }

    /* =====================================================
       QR CODE
    ===================================================== */

    $qr_code = 'AST-' . strtoupper(
        substr(md5($serial_number . time()), 0, 10)
    );

    /* =====================================================
       INSERT
    ===================================================== */

    $stmt = $conn->prepare("
        INSERT INTO assets (
            asset_name,
            asset_type,
            subtype,
            serial_number,
            qr_code,
            asset_image,
            location,
            status,
            asset_value,
            depreciated_value,
            net_value,
            useful_life,
            purchase_date,
            last_service_date,
            next_service_date,
            maintenance_cost,
            maintenance_notes,
            created_by,
            date_added
        )
        VALUES (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
        )
    ");

    $todayDate = date('Y-m-d');

    $stmt->bind_param(
        "sssssssssddisssdsss",
        $asset_name,
        $asset_type,
        $subtype,
        $serial_number,
        $qr_code,
        $imagePath,
        $location,
        $status,
        $asset_value,
        $accumulated_depreciation,
        $current_value,
        $useful_life,
        $purchase_date,
        $last_service_date,
        $next_service_date,
        $maintenance_cost,
        $maintenance_notes,
        $user,
        $todayDate
    );

    $stmt->execute();

    header("Location: add_asset.php?success=1");
    exit;
}

/* =====================================================
   LOAD DATA
===================================================== */

$assets = $conn->query("
    SELECT * 
    FROM assets 
    ORDER BY id DESC
");

$totalAssets = $conn->query("
    SELECT COUNT(*) total 
    FROM assets
")->fetch_assoc()['total'] ?? 0;

$activeAssets = $conn->query("
    SELECT COUNT(*) total 
    FROM assets 
    WHERE status='Active'
")->fetch_assoc()['total'] ?? 0;

$faultyAssets = $conn->query("
    SELECT COUNT(*) total 
    FROM assets 
    WHERE status='Faulty'
")->fetch_assoc()['total'] ?? 0;

$totalValue = $conn->query("
    SELECT SUM(asset_value) total 
    FROM assets
")->fetch_assoc()['total'] ?? 0;

$netValue = $conn->query("
    SELECT SUM(net_value) total 
    FROM assets
")->fetch_assoc()['total'] ?? 0;

/* =====================================================
   LOAD METERS
===================================================== */

$meters = $conn->query("
    SELECT serial_number, zone, status 
    FROM meters
");

$meterData = [];

while ($m = $meters->fetch_assoc()) {
    $meterData[] = $m;
}

/* =====================================================
   INCLUDE UI
===================================================== */

include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<!DOCTYPE html>
<html>
<head>

<title>WOWASCO Asset Management</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>

body{
    margin:0;
    background:#f4f7fb;
    font-family:Segoe UI;
}

.page{
    margin-left:240px;
    margin-top:70px;
    padding:25px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.title{
    font-size:28px;
    font-weight:700;
    color:#0b2d5c;
}

.add-btn{
    background:linear-gradient(135deg,#0b2d5c,#0f6b5f);
    color:white;
    border:none;
    padding:13px 20px;
    border-radius:12px;
    cursor:pointer;
    font-weight:600;
}

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card{
    background:white;
    border-radius:18px;
    padding:22px;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);
}

.table-box{
    background:white;
    border-radius:18px;
    padding:20px;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);
}

.search-box{
    margin-bottom:20px;
}

.search-box input{
    width:320px;
    padding:12px;
    border-radius:12px;
    border:1px solid #dbe2ea;
}

.table{
    width:100%;
    border-collapse:collapse;
}

.table th,
.table td{
    padding:14px;
    border-bottom:1px solid #eef2f7;
    text-align:left;
}

.asset-img{
    width:50px;
    height:50px;
    border-radius:10px;
    object-fit:cover;
}

.badge{
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
}

.active{
    background:#dcfce7;
    color:#15803d;
}

.faulty{
    background:#fee2e2;
    color:#b91c1c;
}

.inactive{
    background:#e2e8f0;
    color:#334155;
}

.placeholder-img{
    width:50px;
    height:50px;
    border-radius:10px;
    background:#e2e8f0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#64748b;
    font-size:18px;
}

</style>
</head>

<body>

<div class="page">

<div class="topbar">

<div class="title">
WOWASCO Asset Management Dashboard
</div>

<button class="add-btn">
<i class="fa fa-plus"></i> Add Asset
</button>

</div>

<div class="cards">

<div class="card">
<small>Total Assets</small>
<h2><?= $totalAssets ?></h2>
</div>

<div class="card">
<small>Active Assets</small>
<h2><?= $activeAssets ?></h2>
</div>

<div class="card">
<small>Faulty Assets</small>
<h2><?= $faultyAssets ?></h2>
</div>

<div class="card">
<small>Total Asset Value</small>
<h2>KES <?= number_format($totalValue) ?></h2>
</div>

<div class="card">
<small>Net Asset Value</small>
<h2>KES <?= number_format($netValue) ?></h2>
</div>

</div>

<div class="table-box">

<div class="search-box">
<input type="text"
id="searchInput"
placeholder="Search assets...">
</div>

<table class="table" id="assetTable">

<thead>
<tr>
<th>Image</th>
<th>Asset</th>
<th>Type</th>
<th>Serial</th>
<th>Location</th>
<th>Status</th>
<th>Value</th>
<th>Net Value</th>
<th>QR</th>
</tr>
</thead>

<tbody>

<?php while($a = $assets->fetch_assoc()): ?>

<tr>

<td>

<?php if(isset($a['asset_image']) && !empty($a['asset_image'])): ?>

<img 
src="<?= $a['asset_image'] ?>" 
class="asset-img"
>

<?php else: ?>

<div class="placeholder-img">
<i class="fa fa-image"></i>
</div>

<?php endif; ?>

</td>

<td><?= $a['asset_name'] ?? '' ?></td>

<td><?= $a['asset_type'] ?? '' ?></td>

<td><?= $a['serial_number'] ?? '' ?></td>

<td><?= $a['location'] ?? '' ?></td>

<td>

<span class="badge <?= strtolower($a['status'] ?? 'inactive') ?>">

<?= $a['status'] ?? 'Inactive' ?>

</span>

</td>

<td>
KES <?= number_format($a['asset_value'] ?? 0) ?>
</td>

<td>
KES <?= number_format($a['net_value'] ?? 0) ?>
</td>

<td>

<?php if(isset($a['qr_code']) && !empty($a['qr_code'])): ?>

<?= $a['qr_code'] ?>

<?php else: ?>

<span style="
color:#94a3b8;
font-style:italic;
">
Not Generated
</span>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<script>

let searchInput =
document.getElementById('searchInput');

searchInput.addEventListener('keyup', function(){

let value = this.value.toLowerCase();

let rows =
document.querySelectorAll(
'#assetTable tbody tr'
);

rows.forEach(row => {

row.style.display =
row.innerText.toLowerCase().includes(value)
? ''
: 'none';

});
});

</script>

</body>
</html>