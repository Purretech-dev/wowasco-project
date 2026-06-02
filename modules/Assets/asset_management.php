<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

/* =========================================================
   SAME PAGE REDIRECT HELPER
========================================================= */

function redirectToSamePage($message = ''){

    $currentPage = $_SERVER['REQUEST_URI'];

    echo "
    <script>
    " . (!empty($message) ? "alert(" . json_encode($message) . ");" : "") . "
    window.location.href = " . json_encode($currentPage) . ";
    </script>
    ";

    exit;
}

/* =========================================================
   CREATE MISSING COLUMNS SAFELY
========================================================= */

function ensureColumn($conn, $table, $column, $definition){

    $column = $conn->real_escape_string($column);
    $table = $conn->real_escape_string($table);

    $checkColumn = $conn->query("
        SHOW COLUMNS FROM `$table` LIKE '$column'
    ");

    if($checkColumn && $checkColumn->num_rows == 0){

        $conn->query("
            ALTER TABLE `$table`
            ADD `$column` $definition
        ");
    }
}

ensureColumn($conn, 'assets', 'is_deactivated', 'TINYINT(1) DEFAULT 0');
ensureColumn($conn, 'assets', 'model', 'VARCHAR(255) NULL');
ensureColumn($conn, 'assets', 'asset_subtype', 'VARCHAR(100) NULL');
ensureColumn($conn, 'assets', 'number_plate', 'VARCHAR(50) NULL');
ensureColumn($conn, 'assets', 'date_purchased', 'DATE NULL');
ensureColumn($conn, 'assets', 'status', 'VARCHAR(100) NULL');

function ensureDeactivationRequestsTable($conn){

    $conn->query("
        CREATE TABLE IF NOT EXISTS deactivation_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_type VARCHAR(30) NOT NULL,
            item_id INT NOT NULL,
            item_label VARCHAR(255) NULL,
            requested_by INT NULL,
            requester_name VARCHAR(150) NULL,
            request_reason TEXT NOT NULL,
            request_notes TEXT NULL,
            attachment_path VARCHAR(255) NULL,
            checker_id INT NULL,
            checker_name VARCHAR(150) NULL,
            checker_decision VARCHAR(50) NULL,
            checker_notes TEXT NULL,
            checked_at DATETIME NULL,
            approver_id INT NULL,
            approver_name VARCHAR(150) NULL,
            approver_decision VARCHAR(50) NULL,
            approver_notes TEXT NULL,
            approved_at DATETIME NULL,
            final_status VARCHAR(80) DEFAULT 'Pending Check',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(item_type),
            INDEX(item_id),
            INDEX(final_status)
        )
    ");
}

function saveDeactivationAttachment($fieldName){

    if(empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK){

        return '';
    }

    $allowed = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));

    if(!in_array($ext, $allowed, true)){

        return '';
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/wowasco-system/uploads/deactivation_requests/';

    if(!is_dir($uploadDir)){

        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'deactivation_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . $fileName;

    if(move_uploaded_file($_FILES[$fieldName]['tmp_name'], $target)){

        return 'uploads/deactivation_requests/' . $fileName;
    }

    return '';
}

ensureDeactivationRequestsTable($conn);

/* =========================================================
   DUPLICATE PROTECTION
========================================================= */

function duplicateAssetExists($conn, $field, $value, $excludeId = 0){

    $value = strtolower(trim((string)$value));

    if($value === ''){

        return false;
    }

    $sql = "
        SELECT id
        FROM assets
        WHERE LOWER(TRIM(IFNULL(`$field`,''))) = ?
    ";

    if($excludeId > 0){

        $sql .= " AND id <> ?";
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);

    if(!$stmt){

        return false;
    }

    if($excludeId > 0){

        $stmt->bind_param("si", $value, $excludeId);

    }else{

        $stmt->bind_param("s", $value);
    }

    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function duplicateAssetProfileExists($conn, $assetName, $model, $assetType, $location, $excludeId = 0){

    $assetName = strtolower(trim((string)$assetName));
    $model = strtolower(trim((string)$model));
    $assetType = strtolower(trim((string)$assetType));
    $location = strtolower(trim((string)$location));

    if($assetName === '' || $assetType === '' || $location === ''){

        return false;
    }

    $sql = "
        SELECT id
        FROM assets
        WHERE LOWER(TRIM(IFNULL(asset_name,''))) = ?
        AND LOWER(TRIM(IFNULL(model,''))) = ?
        AND LOWER(TRIM(IFNULL(asset_type,''))) = ?
        AND LOWER(TRIM(IFNULL(location,''))) = ?
    ";

    if($excludeId > 0){

        $sql .= " AND id <> ?";
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);

    if(!$stmt){

        return false;
    }

    if($excludeId > 0){

        $stmt->bind_param("ssssi", $assetName, $model, $assetType, $location, $excludeId);

    }else{

        $stmt->bind_param("ssss", $assetName, $model, $assetType, $location);
    }

    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function duplicateNamedAssetExists($conn, $assetName, $serialNumber, $numberPlate, $excludeId = 0){

    $assetName = strtolower(trim((string)$assetName));
    $serialNumber = strtolower(trim((string)$serialNumber));
    $numberPlate = strtolower(trim((string)$numberPlate));

    if($assetName === ''){

        return false;
    }

    $sql = "
        SELECT id
        FROM assets
        WHERE LOWER(TRIM(IFNULL(asset_name,''))) = ?
    ";

    $types = "s";
    $params = [$assetName];

    if($serialNumber !== ''){

        $sql .= " AND LOWER(TRIM(IFNULL(serial_number,''))) = ?";
        $types .= "s";
        $params[] = $serialNumber;
    }

    if($numberPlate !== ''){

        $sql .= " AND LOWER(TRIM(IFNULL(number_plate,''))) = ?";
        $types .= "s";
        $params[] = $numberPlate;
    }

    if($serialNumber === '' && $numberPlate === ''){

        return false;
    }

    if($excludeId > 0){

        $sql .= " AND id <> ?";
        $types .= "i";
        $params[] = $excludeId;
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);

    if(!$stmt){

        return false;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

/* =========================================================
   REGISTER ASSET
========================================================= */

if(isset($_POST['register_asset'])){

    $asset_name = trim($_POST['asset_name'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $asset_type = trim($_POST['asset_type'] ?? '');
    $asset_subtype = trim($_POST['asset_subtype'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $number_plate = trim($_POST['number_plate'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $asset_value = floatval($_POST['asset_value'] ?? 0);
    $net_value = floatval($_POST['net_value'] ?? 0);

    $date_purchased = $_POST['date_purchased'] ?? null;
    $status = trim($_POST['status'] ?? 'Operational');

    if(duplicateAssetExists($conn, 'serial_number', $serial_number)){

        redirectToSamePage('Duplicate asset blocked. This serial number is already registered.');
    }

    if(duplicateAssetExists($conn, 'number_plate', $number_plate)){

        redirectToSamePage('Duplicate asset blocked. This number plate is already registered.');
    }

    if(duplicateAssetProfileExists($conn, $asset_name, $model, $asset_type, $location)){

        redirectToSamePage('Duplicate asset blocked. An asset with the same name, model, type and location already exists.');
    }

    if(duplicateNamedAssetExists($conn, $asset_name, $serial_number, $number_plate)){

        redirectToSamePage('Duplicate asset blocked. This asset name is already registered with the same serial number or number plate.');
    }

    $stmt = $conn->prepare("
        INSERT INTO assets
        (
            asset_name,
            model,
            asset_type,
            asset_subtype,
            serial_number,
            number_plate,
            location,
            asset_value,
            net_value,
            date_purchased,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if(!$stmt){

        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssddss",
        $asset_name,
        $model,
        $asset_type,
        $asset_subtype,
        $serial_number,
        $number_plate,
        $location,
        $asset_value,
        $net_value,
        $date_purchased,
        $status
    );

    if($stmt->execute()){

        redirectToSamePage('Asset registered successfully.');

    }else{

        die("Insert failed: " . $stmt->error);
    }
}

/* =========================================================
   UPDATE ASSET
========================================================= */

if(isset($_POST['update_asset'])){

    $id = intval($_POST['asset_id'] ?? 0);

    $asset_name = trim($_POST['asset_name'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $asset_type = trim($_POST['asset_type'] ?? '');
    $asset_subtype = trim($_POST['asset_subtype'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $number_plate = trim($_POST['number_plate'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $asset_value = floatval($_POST['asset_value'] ?? 0);
    $net_value = floatval($_POST['net_value'] ?? 0);

    $date_purchased = $_POST['date_purchased'] ?? null;
    $status = trim($_POST['status'] ?? 'Operational');

    if(duplicateAssetExists($conn, 'serial_number', $serial_number, $id)){

        redirectToSamePage('Update blocked. Another active asset already uses this serial number.');
    }

    if(duplicateAssetExists($conn, 'number_plate', $number_plate, $id)){

        redirectToSamePage('Update blocked. Another active asset already uses this number plate.');
    }

    if(duplicateAssetProfileExists($conn, $asset_name, $model, $asset_type, $location, $id)){

        redirectToSamePage('Update blocked. Another active asset has the same name, model, type and location.');
    }

    if(duplicateNamedAssetExists($conn, $asset_name, $serial_number, $number_plate, $id)){

        redirectToSamePage('Update blocked. Another asset already has this name with the same serial number or number plate.');
    }

    $stmt = $conn->prepare("
        UPDATE assets
        SET
            asset_name = ?,
            model = ?,
            asset_type = ?,
            asset_subtype = ?,
            serial_number = ?,
            number_plate = ?,
            location = ?,
            asset_value = ?,
            net_value = ?,
            date_purchased = ?,
            status = ?
        WHERE id = ?
    ");

    if(!$stmt){

        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssddssi",
        $asset_name,
        $model,
        $asset_type,
        $asset_subtype,
        $serial_number,
        $number_plate,
        $location,
        $asset_value,
        $net_value,
        $date_purchased,
        $status,
        $id
    );

    if($stmt->execute()){

        redirectToSamePage('Asset updated successfully.');

    }else{

        die("Update failed: " . $stmt->error);
    }
}

/* =========================================================
   DEACTIVATE
========================================================= */

if(isset($_POST['request_deactivation_asset'])){

    $id = intval($_POST['asset_id'] ?? 0);
    $reason = trim($_POST['deactivation_reason'] ?? '');
    $notes = trim($_POST['deactivation_notes'] ?? '');

    if($reason === ''){

        redirectToSamePage('Please provide a reason for the deactivation request.');
    }

    $pending = $conn->prepare("
        SELECT id
        FROM deactivation_requests
        WHERE item_type = 'asset'
        AND item_id = ?
        AND final_status IN ('Pending Check','Pending MD Approval','Returned by Checker')
        LIMIT 1
    ");

    $pending->bind_param("i", $id);
    $pending->execute();
    $pending->store_result();

    if($pending->num_rows > 0){

        redirectToSamePage('A deactivation request for this asset is already in progress.');
    }

    $assetResult = $conn->query("SELECT asset_name, serial_number FROM assets WHERE id = $id LIMIT 1");
    $assetRow = $assetResult ? $assetResult->fetch_assoc() : [];
    $itemLabel = trim(($assetRow['asset_name'] ?? '') . ' - ' . ($assetRow['serial_number'] ?? ''));
    $attachmentPath = saveDeactivationAttachment('deactivation_attachment');
    $requesterId = (int)($_SESSION['user_id'] ?? 0);
    $requesterName = $_SESSION['name'] ?? 'System User';

    $stmt = $conn->prepare("
        INSERT INTO deactivation_requests
        (
            item_type,
            item_id,
            item_label,
            requested_by,
            requester_name,
            request_reason,
            request_notes,
            attachment_path,
            final_status
        )
        VALUES ('asset', ?, ?, ?, ?, ?, ?, ?, 'Pending Check')
    ");

    if(!$stmt){

        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "isissss",
        $id,
        $itemLabel,
        $requesterId,
        $requesterName,
        $reason,
        $notes,
        $attachmentPath
    );

    if($stmt->execute()){

        redirectToSamePage('Asset deactivation request submitted for checking.');

    }else{

        die("Deactivate failed: " . $stmt->error);
    }
}

/* =========================================================
   FETCH ASSETS
========================================================= */

$result = $conn->query("
    SELECT *
    FROM assets
    WHERE is_deactivated = 0
    ORDER BY id DESC
");

if(!$result){

    die("Assets query failed: " . $conn->error);
}

/* =========================================================
   FETCH SMART METERS
========================================================= */

$meters = [];

$meterQuery = $conn->query("
    SELECT *
    FROM meters
    ORDER BY serial_number ASC
");

if($meterQuery){

    while($meter = $meterQuery->fetch_assoc()){

        $meters[] = $meter;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Asset Management</title>

<link rel="stylesheet"
href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>

<style>
body{
    margin:0;
    background:#eef3f8;
    font-family:'Segoe UI',sans-serif;
}

.page-content{
    margin-left:250px;
    padding:90px 25px 120px;
}

.page-header{
    margin-bottom:24px;
}

.page-title{
    margin:0;
    font-size:32px;
    font-weight:800;
    color:#0f172a;
}

.page-subtitle{
    margin-top:8px;
    font-size:15px;
    color:#64748b;
}

.card{
    background:white;
    border-radius:24px;
    padding:24px;
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
    overflow:visible !important;
}

.top-action-bar{
    display:flex;
    justify-content:flex-end;
    margin-bottom:20px;
}

.register-btn{
    background:linear-gradient(135deg,#1e3a8a,#2563eb);
    color:white;
    border:none;
    padding:12px 18px;
    border-radius:12px;
    cursor:pointer;
}

.detail-main{
    font-size:14px;
    font-weight:600;
}

.detail-sub{
    font-size:12px;
    color:#64748b;
}

.status-badge{
    background:#dcfce7;
    color:#15803d;
    padding:8px 14px;
    border-radius:30px;
    font-size:12px;
    font-weight:600;
}

table.dataTable,
.dataTables_wrapper,
.card,
table.dataTable tbody td,
div.dt-container,
div.dt-layout-row,
div.dt-layout-table,
.dataTables_scroll,
.dataTables_scrollBody{
    overflow:visible !important;
}

td:last-child,
th:last-child{
    width:90px !important;
    min-width:90px !important;
    text-align:center !important;
    overflow:visible !important;
    position:relative !important;
}

.action-wrapper{
    position:relative !important;
    overflow:visible !important;
    display:flex;
    justify-content:center;
    align-items:center;
}

.action-btn{
    width:42px;
    height:42px;
    border:none;
    border-radius:12px;
    background:#ffffff;
    border:1px solid #dbe2ea;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    font-size:22px;
    color:#334155;
    transition:0.2s ease;
}

.action-btn:hover{
    background:#f8fafc;
    border-color:#cbd5e1;
}

.action-wrapper > button:not([onclick^="toggleMenu"]),
.action-wrapper > form{
    display:none !important;
}

.action-menu{
    display:none;
    position:absolute;
    right:0;
    top:50px;
    width:210px;
    background:white;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 20px 40px rgba(15,23,42,0.18);
    z-index:999999999 !important;
}

.action-menu button{
    width:100%;
    padding:14px 16px;
    background:white;
    border:none;
    text-align:left;
    cursor:pointer;
    font-size:13px;
    color:#334155;
    transition:0.2s ease;
}

.action-menu button:hover{
    background:#f8fafc;
}

.action-menu form{
    margin:0;
}

.action-menu form button{
    color:#7f1d1d;
    font-weight:700;
}

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.55);
    z-index:999999;
    justify-content:center;
    align-items:center;
    padding:20px;
    overflow-y:auto;
}

.modal-content{
    width:100%;
    max-width:650px;
    background:white;
    border-radius:24px;
    position:relative;
    max-height:90vh;
    overflow-y:auto;
    overflow-x:hidden;
    box-shadow:0 20px 50px rgba(15,23,42,0.18);
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:20px 24px;
    background:#f8fafc;
}

.close-modal{
    width:42px;
    height:42px;
    border:none;
    border-radius:12px;
    background:#ffffff;
    border:1px solid #dbe2ea;
    cursor:pointer;
    font-size:24px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:0.2s ease;
}

.close-modal:hover{
    background:#f8fafc;
}

.modal-body{
    padding:24px;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.form-group{
    display:flex;
    flex-direction:column;
    gap:7px;
}

.form-group.full{
    grid-column:1/-1;
}

.form-group input,
.form-group select{
    padding:12px;
    border:1px solid #dbe2ea;
    border-radius:12px;
}

.submit-btn{
    background:linear-gradient(135deg,#1e7d4f,#249c63);
    color:white;
    border:none;
    padding:14px;
    border-radius:14px;
    cursor:pointer;
}
</style>
</head>

<body>

<div class="page-content">

<div class="page-header">

<h1 class="page-title">
Asset Management
</h1>

<p class="page-subtitle">
Monitor, manage and track organizational assets, values, locations and operational status.
</p>

</div>

<div class="top-action-bar">

<button
class="register-btn"
onclick="openRegisterModal()">

+ Register Asset

</button>

</div>

<div class="card">

<table id="assetTable" class="display">

<thead>
<tr>
<th>Asset</th>
<th>Value</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $result->fetch_assoc()): ?>

<tr>

<td>
<div class="detail-main">
<?= htmlspecialchars($row['asset_name'] ?? ''); ?>
</div>

<div class="detail-sub">
Type: <?= htmlspecialchars($row['asset_type'] ?? ''); ?>
</div>

<div class="detail-sub">
Subtype: <?= htmlspecialchars($row['asset_subtype'] ?? ''); ?>
</div>

<div class="detail-sub">
Serial: <?= htmlspecialchars($row['serial_number'] ?? ''); ?>
</div>

<div class="detail-sub">
Location: <?= htmlspecialchars($row['location'] ?? ''); ?>
</div>

<?php if(!empty($row['number_plate'])): ?>
<div class="detail-sub">
Plate: <?= htmlspecialchars($row['number_plate']); ?>
</div>
<?php endif; ?>

</td>

<td>
<div class="detail-main">
KES <?= number_format((float)($row['asset_value'] ?? 0)); ?>
</div>

<div class="detail-sub">
Net: KES <?= number_format((float)($row['net_value'] ?? 0)); ?>
</div>
</td>

<td>
<span class="status-badge">
<?= htmlspecialchars($row['status'] ?? 'Operational'); ?>
</span>
</td>

<td>

<div class="action-wrapper">

<button
type="button"
class="action-btn"
onclick='openEditModal(
<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>
)'>
Edit
</button>

<button
type="button"
class="deactivate-btn"
onclick='openAssetDeactivationModal(
<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>
)'>
Request Deactivation
</button>

<button
type="button"
class="action-btn"
onclick="toggleMenu(this)">
<span style="font-size:22px;line-height:1;margin-top:-2px;display:block;">
⋮
</span>
</button>

<div class="action-menu">

<button
type="button"
onclick='openEditModal(
<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>
)'>
✏ Edit Asset
</button>

<button
type="button"
onclick='openAssetDeactivationModal(
<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>
)'>
Request Deactivation
</button>

</div>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

</div>

</div>

<!-- REGISTER MODAL -->

<div id="registerModal" class="modal">

<div class="modal-content">

<div class="modal-header">

<div>
<h3 style="margin:0;font-size:22px;color:#0f172a;">
Register Asset
</h3>

<p style="margin-top:6px;font-size:13px;color:#64748b;">
Fill in the asset details below
</p>
</div>

<button
type="button"
class="close-modal"
onclick="closeRegisterModal()">
×
</button>

</div>

<div class="modal-body">

<form method="POST">

<div class="form-grid">

<div class="form-group">
<label>Asset Name</label>
<input
type="text"
name="asset_name"
id="asset_name"
placeholder="e.g Smart Meter, HP Laptop, Toyota Hilux"
required>
</div>

<div class="form-group">
<label>Model</label>
<input
type="text"
name="model"
id="model">
</div>

<div class="form-group" id="serialInputGroup">
<label>Serial Number</label>
<input
type="text"
name="serial_number"
id="serial_number_input">
</div>

<div
class="form-group"
id="meterDropdownGroup"
style="display:none;">
<label>Select Smart Meter</label>

<select id="meter_dropdown">
<option value="">
-- Select Meter --
</option>

<?php foreach($meters as $meter): ?>

<option
value="<?= htmlspecialchars($meter['serial_number'] ?? ''); ?>"
data-model="<?= htmlspecialchars($meter['model'] ?? ($meter['meter_model'] ?? '')); ?>"
data-zone="<?= htmlspecialchars($meter['zone'] ?? ''); ?>"
data-date="<?= htmlspecialchars($meter['installation_date'] ?? ''); ?>"
data-status="<?= htmlspecialchars($meter['status'] ?? ($meter['meter_status'] ?? 'Operational')); ?>">
<?= htmlspecialchars($meter['serial_number'] ?? ''); ?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="form-group">
<label>Asset Type</label>
<select
name="asset_type"
id="asset_type"
required>
<option value="Office">Office</option>
<option value="Field">Field</option>
<option value="Digital">Digital</option>
</select>
</div>

<div class="form-group">
<label>Asset Subtype</label>
<select
name="asset_subtype"
id="asset_subtype"
required>
<option value="Fixed">Fixed</option>
<option value="Hardware">Hardware</option>
<option value="Software">Software</option>
</select>
</div>

<div
class="form-group"
id="numberPlateGroup"
style="display:none;">
<label>Number Plate</label>
<input
type="text"
name="number_plate"
id="number_plate">
</div>

<div class="form-group">
<label>Asset Location</label>
<input
type="text"
name="location"
id="location"
required>
</div>

<div class="form-group">
<label>Asset Value (KES)</label>
<input
type="number"
step="0.01"
name="asset_value"
id="asset_value"
required>
</div>

<div class="form-group">
<label>Net Value</label>
<input
type="number"
step="0.01"
name="net_value"
id="net_value"
readonly>
</div>

<div class="form-group">
<label>Date Purchased</label>
<input
type="date"
name="date_purchased"
id="date_purchased"
required>
</div>

<div class="form-group">
<label>Asset Status</label>
<select
name="status"
id="status">
<option value="Operational">Operational</option>
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
<option value="Under Maintenance">Under Maintenance</option>
<option value="Faulty">Faulty</option>
<option value="Retired">Retired</option>
</select>
</div>

<div class="form-group full">
<button
type="submit"
name="register_asset"
class="submit-btn">
Register Asset
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- EDIT MODAL -->

<div id="editModal" class="modal">

<div class="modal-content">

<div class="modal-header">

<div>
<h3 style="margin:0;font-size:22px;color:#0f172a;">
Edit Asset
</h3>

<p style="margin-top:6px;font-size:13px;color:#64748b;">
Update asset details below
</p>
</div>

<button
type="button"
class="close-modal"
onclick="closeModal()">
×
</button>

</div>

<div class="modal-body">

<form method="POST">

<input
type="hidden"
name="asset_id"
id="edit_asset_id">

<div class="form-grid">

<div class="form-group">
<label>Asset Name</label>
<input
type="text"
name="asset_name"
id="edit_asset_name"
required>
</div>

<div class="form-group">
<label>Model</label>
<input
type="text"
name="model"
id="edit_model">
</div>

<div class="form-group">
<label>Asset Type</label>
<select
name="asset_type"
id="edit_asset_type"
required>
<option value="Office">Office</option>
<option value="Field">Field</option>
<option value="Digital">Digital</option>
</select>
</div>

<div class="form-group">
<label>Asset Subtype</label>
<select
name="asset_subtype"
id="edit_asset_subtype"
required>
<option value="Fixed">Fixed</option>
<option value="Hardware">Hardware</option>
<option value="Software">Software</option>
</select>
</div>

<div class="form-group">
<label>Serial Number</label>
<input
type="text"
name="serial_number"
id="edit_serial_number">
</div>

<div class="form-group">
<label>Number Plate</label>
<input
type="text"
name="number_plate"
id="edit_number_plate">
</div>

<div class="form-group">
<label>Asset Location</label>
<input
type="text"
name="location"
id="edit_location"
required>
</div>

<div class="form-group">
<label>Asset Value (KES)</label>
<input
type="number"
step="0.01"
name="asset_value"
id="edit_asset_value"
required>
</div>

<div class="form-group">
<label>Net Value</label>
<input
type="number"
step="0.01"
name="net_value"
id="edit_net_value">
</div>

<div class="form-group">
<label>Date Purchased</label>
<input
type="date"
name="date_purchased"
id="edit_date_purchased">
</div>

<div class="form-group">
<label>Asset Status</label>
<select
name="status"
id="edit_status">
<option value="Operational">Operational</option>
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
<option value="Under Maintenance">Under Maintenance</option>
<option value="Faulty">Faulty</option>
<option value="Retired">Retired</option>
</select>
</div>

<div class="form-group full">
<button
type="submit"
name="update_asset"
class="submit-btn">
Update Asset
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- DEACTIVATION REQUEST MODAL -->

<div id="assetDeactivationModal" class="modal">

<div class="modal-content">

<div class="modal-header">

<div>
<h3 style="margin:0;font-size:22px;color:#0f172a;">
Request Asset Deactivation
</h3>

<p style="margin-top:6px;font-size:13px;color:#64748b;">
Submit details for checker verification and MD approval
</p>
</div>

<button
type="button"
class="close-modal"
onclick="closeAssetDeactivationModal()">
×
</button>

</div>

<div class="modal-body">

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="asset_id" id="deactivation_asset_id">

<div class="form-grid">

<div class="form-group full">
<label>Asset Details</label>
<input type="text" id="deactivation_asset_label" readonly>
</div>

<div class="form-group full">
<label>Reason for Deactivation</label>
<select name="deactivation_reason" required>
<option value="">-- Select Reason --</option>
<option value="Faulty asset">Faulty asset</option>
<option value="Asset replacement">Asset replacement</option>
<option value="Asset retired">Asset retired</option>
<option value="Lost or damaged asset">Lost or damaged asset</option>
<option value="Duplicate or wrong record">Duplicate or wrong record</option>
<option value="Other">Other</option>
</select>
</div>

<div class="form-group full">
<label>Verification Notes</label>
<input
type="text"
name="deactivation_notes"
placeholder="Enter supporting details for the checker and MD">
</div>

<div class="form-group full">
<label>Supporting Photo / Document</label>
<input
type="file"
name="deactivation_attachment"
accept=".jpg,.jpeg,.png,.pdf">
</div>

<div class="form-group full">
<button
type="submit"
name="request_deactivation_asset"
class="submit-btn">
Submit Deactivation Request
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<script>

$(document).ready(function(){

    $('#assetTable').DataTable();

});

/* =========================================================
   ACTION MENU
========================================================= */

function toggleMenu(button){

    const menu = button.nextElementSibling;

    document.querySelectorAll('.action-menu')
    .forEach(item => {

        if(item !== menu){

            item.style.display = 'none';
        }
    });

    menu.style.display =
    menu.style.display === 'block'
    ? 'none'
    : 'block';
}

/* =========================================================
   MODALS
========================================================= */

function openRegisterModal(){

    document.getElementById('registerModal').style.display = 'flex';
}

function closeRegisterModal(){

    document.getElementById('registerModal').style.display = 'none';
}

function closeModal(){

    document.getElementById('editModal').style.display = 'none';
}

function setSelectValue(selectId, value){

    const select = document.getElementById(selectId);

    if(!select){

        return;
    }

    const stringValue = value || '';

    let exists = false;

    Array.from(select.options).forEach(option => {

        if(option.value === stringValue){

            exists = true;
        }
    });

    if(!exists && stringValue !== ''){

        const option = document.createElement('option');

        option.value = stringValue;
        option.textContent = stringValue;

        select.appendChild(option);
    }

    select.value = stringValue;
}

function openEditModal(data){

    document.getElementById('editModal').style.display = 'flex';

    document.getElementById('edit_asset_id').value = data.id || '';
    document.getElementById('edit_asset_name').value = data.asset_name || '';
    document.getElementById('edit_model').value = data.model || '';

    setSelectValue('edit_asset_type', data.asset_type || '');
    setSelectValue('edit_asset_subtype', data.asset_subtype || '');

    document.getElementById('edit_serial_number').value = data.serial_number || '';
    document.getElementById('edit_number_plate').value = data.number_plate || '';
    document.getElementById('edit_location').value = data.location || '';
    document.getElementById('edit_asset_value').value = data.asset_value || '';
    document.getElementById('edit_net_value').value = data.net_value || '';
    document.getElementById('edit_date_purchased').value = data.date_purchased || '';

    setSelectValue('edit_status', data.status || 'Operational');
}

function openAssetDeactivationModal(data){

    document.getElementById('deactivation_asset_id').value = data.id || '';
    document.getElementById('deactivation_asset_label').value =
        (data.asset_name || '') + ' - ' + (data.serial_number || 'N/A');

    document.getElementById('assetDeactivationModal').style.display = 'flex';
}

function closeAssetDeactivationModal(){

    document.getElementById('assetDeactivationModal').style.display = 'none';
}

/* =========================================================
   SMART LOGIC
========================================================= */

document.addEventListener('DOMContentLoaded', function(){

    const assetName = document.getElementById('asset_name');
    const serialInputGroup = document.getElementById('serialInputGroup');
    const meterDropdownGroup = document.getElementById('meterDropdownGroup');
    const meterDropdown = document.getElementById('meter_dropdown');
    const numberPlateGroup = document.getElementById('numberPlateGroup');
    const serialInput = document.getElementById('serial_number_input');
    const modelInput = document.getElementById('model');
    const locationInput = document.getElementById('location');
    const datePurchasedInput = document.getElementById('date_purchased');

    function handleAssetNameLogic(){

        const value = assetName.value.toLowerCase();

        if(value.includes('smart meter')){

            serialInputGroup.style.display = 'none';
            meterDropdownGroup.style.display = 'flex';

        }else{

            serialInputGroup.style.display = 'flex';
            meterDropdownGroup.style.display = 'none';
            meterDropdown.value = '';
        }

        if(
            value.includes('vehicle') ||
            value.includes('motor vehicle') ||
            value.includes('car') ||
            value.includes('truck') ||
            value.includes('hilux') ||
            value.includes('prado') ||
            value.includes('pickup') ||
            value.includes('motorbike') ||
            value.includes('motorcycle') ||
            value.includes('van') ||
            value.includes('lorry')
        ){

            numberPlateGroup.style.display = 'flex';

        }else{

            numberPlateGroup.style.display = 'none';
            document.getElementById('number_plate').value = '';
        }
    }

    assetName.addEventListener('keyup', handleAssetNameLogic);
    assetName.addEventListener('change', handleAssetNameLogic);

    meterDropdown.addEventListener('change', function(){

        const selected = this.options[this.selectedIndex];

        serialInput.value = selected.value || '';
        modelInput.value = selected.dataset.model || '';
        locationInput.value = selected.dataset.zone || '';
        datePurchasedInput.value = selected.dataset.date || '';

        setSelectValue('status', selected.dataset.status || 'Operational');

        calculateDepreciation();
    });

    function calculateDepreciation(){

        const assetValue = parseFloat(document.getElementById('asset_value').value) || 0;

        const assetNameValue = assetName.value.toLowerCase();

        let usefulLife = 5;
        let residualRate = 10;

        if(assetNameValue.includes('computer')){

            usefulLife = 3;
            residualRate = 10;
        }

        else if(assetNameValue.includes('software')){

            usefulLife = 3;
            residualRate = 0;
        }

        else if(
            assetNameValue.includes('vehicle') ||
            assetNameValue.includes('car') ||
            assetNameValue.includes('truck') ||
            assetNameValue.includes('hilux') ||
            assetNameValue.includes('prado') ||
            assetNameValue.includes('pickup')
        ){

            usefulLife = 8;
            residualRate = 20;
        }

        else if(assetNameValue.includes('smart meter')){

            usefulLife = 10;
            residualRate = 5;
        }

        const residualValue = assetValue * (residualRate / 100);

        const annualDepreciation = (assetValue - residualValue) / usefulLife;

        const currentYear = new Date().getFullYear();

        const purchaseDate = document.getElementById('date_purchased').value;

        let yearsUsed = 0;

        if(purchaseDate){

            yearsUsed = currentYear - new Date(purchaseDate).getFullYear();
        }

        const netValue = assetValue - (annualDepreciation * yearsUsed);

        document.getElementById('net_value').value =
        Math.max(netValue, residualValue).toFixed(2);
    }

    document.getElementById('asset_value')
    .addEventListener('input', calculateDepreciation);

    document.getElementById('date_purchased')
    .addEventListener('change', calculateDepreciation);

    assetName.addEventListener('keyup', calculateDepreciation);
    assetName.addEventListener('change', calculateDepreciation);

});

/* =========================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================================= */

window.onclick = function(e){

    if(e.target.classList.contains('modal')){

        e.target.style.display = 'none';
    }
}

</script>

</body>
</html>
