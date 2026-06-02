<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

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
   CREATE DEACTIVATION COLUMN IF NOT EXISTS
========================================================= */

$checkColumn = $conn->query("
    SHOW COLUMNS FROM meters LIKE 'is_deactivated'
");

if($checkColumn->num_rows == 0){

    $conn->query("
        ALTER TABLE meters
        ADD is_deactivated TINYINT(1) DEFAULT 0
    ");
}

/* =========================================================
   DEACTIVATE METER
========================================================= */

if(isset($_POST['request_deactivation_meter'])){

    $id = (int)$_POST['meter_id'];
    $reason = trim($_POST['deactivation_reason'] ?? '');
    $notes = trim($_POST['deactivation_notes'] ?? '');

    if($reason === ''){

        echo "
        <script>
        alert('Please provide a reason for the deactivation request.');
        window.location.href = window.location.pathname + window.location.search;
        </script>
        ";
        exit;
    }

    $pending = $conn->prepare("
        SELECT id
        FROM deactivation_requests
        WHERE item_type = 'meter'
        AND item_id = ?
        AND final_status IN ('Pending Check','Pending MD Approval','Returned by Checker')
        LIMIT 1
    ");

    $pending->bind_param("i", $id);
    $pending->execute();
    $pending->store_result();

    if($pending->num_rows > 0){

        echo "
        <script>
        alert('A deactivation request for this meter is already in progress.');
        window.location.href = window.location.pathname + window.location.search;
        </script>
        ";
        exit;
    }

    $meterResult = $conn->query("SELECT serial_number, customer_name FROM meters WHERE id = $id LIMIT 1");
    $meterRow = $meterResult ? $meterResult->fetch_assoc() : [];
    $itemLabel = trim(($meterRow['serial_number'] ?? '') . ' - ' . ($meterRow['customer_name'] ?? ''));
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
        VALUES ('meter', ?, ?, ?, ?, ?, ?, ?, 'Pending Check')
    ");

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

    $stmt->execute();

    echo "
    <script>
    alert('Meter deactivation request submitted for checking.');
    window.location.href = window.location.pathname + window.location.search;
    </script>
    ";

exit;
}


/* =========================================================
   UPDATE METER
========================================================= */

if(isset($_POST['update_meter'])){

    $id = $_POST['meter_id'];

    $serial_number = $_POST['serial_number'];
    $model = $_POST['model'];
    $meter_type = $_POST['meter_type'];
    $installation_date = $_POST['installation_date'];

    $customer_name = $_POST['customer_name'];
    $customer_type = $_POST['customer_type'];
    $zone = $_POST['zone'];

    $stmt = $conn->prepare("
        UPDATE meters
        SET
        serial_number = ?,
        model = ?,
        meter_type = ?,
        installation_date = ?,
        customer_name = ?,
        customer_type = ?,
        zone = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssssssi",
        $serial_number,
        $model,
        $meter_type,
        $installation_date,
        $customer_name,
        $customer_type,
        $zone,
        $id
    );

   $stmt->execute();

echo "
<script>
window.location.href = window.location.pathname + window.location.search;
</script>
";

exit;
}
/* =========================================================
   REGISTER METER
========================================================= */

if(isset($_POST['register_meter'])){

    $serial_number = trim($_POST['serial_number'] ?? '');
    $model = $_POST['model'];
    $meter_type = $_POST['meter_type'];
    $installation_date = $_POST['installation_date'];

    $customer_name = $_POST['customer_name'];
    $customer_type = $_POST['customer_type'];
    $zone = $_POST['zone'];

    $national_id = $_POST['national_id'];
    $customer_phone = $_POST['customer_phone'];
    $alternative_phone = $_POST['alternative_phone'];

    /* BLOCK SERIALS THAT HAVE ALREADY BEEN REGISTERED */

    $check = $conn->prepare("
        SELECT id
        FROM meters
        WHERE LOWER(TRIM(serial_number)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    $check->bind_param("s", $serial_number);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){

        echo "
        <script>
        alert('This meter serial has already been registered and cannot be used again.');
        window.location.href = window.location.pathname + window.location.search;
        </script>
        ";

        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO meters
        (
            serial_number,
            model,
            meter_type,
            installation_date,
            customer_name,
            customer_type,
            zone,
            national_id,
            customer_phone,
            alternative_phone
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssssssss",
        $serial_number,
        $model,
        $meter_type,
        $installation_date,
        $customer_name,
        $customer_type,
        $zone,
        $national_id,
        $customer_phone,
        $alternative_phone
    );

    if($stmt->execute()){

        echo "
        <script>

        alert('Meter registered successfully.');

        window.location.href =
        window.location.pathname +
        window.location.search;

        </script>
        ";

        exit;
    }

    else {

        echo "
        <script>
        alert('Error registering meter.');
        </script>
        ";
    }
}

/* =========================================================
   FETCH METERS
========================================================= */

$result = $conn->query("
    SELECT *
    FROM meters
    WHERE is_deactivated = 0
    ORDER BY id DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Meter Management</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

body{
    margin:0;
    background:#eef3f8;
    font-family:'Segoe UI',sans-serif;
    color:#1e293b;
}

/* =========================================================
   PAGE
========================================================= */

.page-content{
    margin-left:250px;
    padding:90px 25px 120px;
    min-height:100vh;
}

.content-wrapper{
    max-width:1500px;
    margin:auto;
}

/* =========================================================
   HEADER
========================================================= */

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:25px;
}

.page-header h2{
    margin:0;
    font-size:30px;
    color:#0f172a;
}

.page-subtitle{
    color:#64748b;
    font-size:14px;
    margin-top:6px;
}
/* =========================================================
   TOP ACTION BAR
========================================================= */

.top-action-bar{

    display:flex;
    justify-content:flex-end;
    align-items:center;

    margin-bottom:18px;
}

.register-meter-btn{

    background:linear-gradient(135deg,#1e3a8a,#2563eb);

    color:white;

    border:none;

    padding:12px 18px;

    border-radius:12px;

    font-size:13px;
    font-weight:600;

    cursor:pointer;

    transition:0.2s ease;

    box-shadow:0 8px 18px rgba(37,99,235,0.15);
}

.register-meter-btn:hover{

    transform:translateY(-2px);
}

/* =========================================================
   CARD
========================================================= */

.card{
    background:white;
    border-radius:24px;
    padding:24px;
    border:1px solid #e2e8f0;
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
}

/* =========================================================
   DATATABLE
========================================================= */

table.dataTable{
    width:100% !important;
}

table.dataTable thead th{
    background:#f8fafc !important;
    color:#334155 !important;
    font-size:13px;
    border-bottom:none !important;
    padding:18px 14px !important;
}

table.dataTable tbody td{
    padding:18px 14px !important;
    border-bottom:1px solid #f1f5f9 !important;
    vertical-align:top;
}

table.dataTable tbody tr:hover{
    background:#f8fafc !important;
}

.dt-search input{
    border:1px solid #dbe2ea !important;
    border-radius:10px !important;
    padding:8px 12px !important;
}

.dt-length select{
    border:1px solid #dbe2ea !important;
    border-radius:10px !important;
    padding:7px 10px !important;
}

.dt-paging-button.current{
    background:#1e3a8a !important;
    color:white !important;
    border:none !important;
    border-radius:8px !important;
}
/* =========================================================
   DATATABLE PROFESSIONAL LAYOUT
========================================================= */

.top-controls{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:18px;
}

.bottom-controls{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-top:20px;
}

.dt-search{
    display:flex;
    align-items:center;
}

.dt-search input{
    width:260px !important;
    background:white;
    transition:0.2s ease;
}

.dt-search input:focus{
    border-color:#2563eb !important;
    box-shadow:0 0 0 4px rgba(37,99,235,0.08);
}

.dt-length{
    font-size:13px;
    color:#475569;
}

.dt-info{
    font-size:13px;
    color:#64748b;
}

.dt-paging{
    display:flex;
    align-items:center;
    gap:6px;
}

.dt-paging-button{
    min-width:38px !important;
    height:38px !important;
    border:none !important;
    border-radius:10px !important;
    background:#f8fafc !important;
    color:#334155 !important;
    font-size:13px !important;
    transition:0.2s ease;
}

.dt-paging-button:hover{
    background:#e2e8f0 !important;
    color:#0f172a !important;
}

.dt-paging-button.current{
    background:#1e3a8a !important;
    color:white !important;
    box-shadow:0 8px 18px rgba(30,58,138,0.18);
}

.dt-processing{
    border-radius:12px !important;
    padding:12px 18px !important;
    background:white !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 8px 24px rgba(15,23,42,0.08);
}
/* =========================================================
   DATATABLE CONTROLS CLEAN FIX
========================================================= */

.dt-layout-row:first-child{

    display:flex !important;
    justify-content:space-between !important;
    align-items:center !important;

    gap:30px !important;

    margin-bottom:20px !important;

    flex-wrap:nowrap !important;
}

/* =========================================================
   SHOW ENTRIES
========================================================= */

.dt-length{

    display:flex !important;
    align-items:center !important;

    white-space:nowrap !important;

    flex-shrink:0 !important;
}

/* =========================================================
   SEARCH
========================================================= */

.dt-search{

    display:flex !important;
    align-items:center !important;

    margin-left:auto !important;

    white-space:nowrap !important;

    flex-shrink:0 !important;
}

.dt-search input{

    width:260px !important;

    min-width:260px !important;

    margin-left:12px !important;
}

/* =========================================================
   MOBILE
========================================================= */

@media(max-width:768px){

    .dt-layout-row:first-child{

        flex-direction:column !important;
        align-items:flex-start !important;

        gap:15px !important;
    }

    .dt-search{

        width:100% !important;

        margin-left:0 !important;
    }

    .dt-search input{

        width:100% !important;
        min-width:100% !important;
    }
}
/* =========================================================
   MOBILE RESPONSIVE
========================================================= */

@media(max-width:768px){

    .dataTables_wrapper .top,
    .dataTables_wrapper .dt-layout-row:first-child{

        flex-direction:column !important;
        align-items:flex-start !important;
    }

    .dt-search,
    .dt-length{

        margin:0 !important;
        width:100% !important;
    }

    .dt-search input{

        width:100% !important;
        min-width:100% !important;
    }
}
/* =========================================================
   TABLE SPACING
========================================================= */

.dataTables_wrapper{
    width:100%;
}

.dataTables_scroll{
    border-radius:18px;
    overflow:hidden;
}

/* =========================================================
   DETAIL BLOCKS
========================================================= */

.detail-title{
    font-size:11px;
    text-transform:uppercase;
    color:#94a3b8;
    margin-bottom:5px;
    font-weight:700;
}

.detail-main{
    font-size:14px;
    font-weight:600;
    color:#0f172a;
    margin-bottom:4px;
}

.detail-sub{
    font-size:12px;
    color:#64748b;
    margin-bottom:3px;
}

/* =========================================================
   STATUS
========================================================= */

.status-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:8px 14px;
    border-radius:30px;
    font-size:12px;
    font-weight:600;
}

.status-active{
    background:#dcfce7;
    color:#15803d;
}

.status-inactive{
    background:#fee2e2;
    color:#b91c1c;
}

/* =========================================================
   ACTION MENU
========================================================= */

.action-wrapper{
    position:relative;
}

.action-btn{
    width:42px;
    height:42px;
    border:none;
    border-radius:12px;
    background:#ffffff;
    border:1px solid #dbe2ea;
    cursor:pointer;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:24px;
    font-weight:700;
    color:#334155;

    line-height:1;

    transition:0.2s ease;
}

.action-btn:hover{
    background:#f8fafc;
    border-color:#cbd5e1;
}


.action-menu{
    display:none;
    position:absolute;
    right:0;
    top:45px;
    width:200px;
    background:white;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 20px 40px rgba(15,23,42,0.12);
    z-index:999999;
}

.action-menu button{
    width:100%;
    padding:14px 16px;
    background:none;
    border:none;
    text-align:left;
    cursor:pointer;
    font-size:13px;
    color:#334155;
}

.action-menu button:hover{
    background:#f8fafc;
}

/* =========================================================
   MODAL
========================================================= */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.55);
    backdrop-filter:blur(4px);
    z-index:9999999;
    overflow:auto;
    padding:40px 20px;
    justify-content:center;
    align-items:flex-start;
}

.modal-content{
    width:100%;
    max-width:760px;
    background:white;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 24px 60px rgba(15,23,42,0.25);
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:20px 24px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}

.modal-header h3{
    margin:0;
    color:#0f172a;
}

.close-modal{
    background:none;
    border:none;
    font-size:30px;
    cursor:pointer;
    color:#64748b;
}

.modal-body{
    padding:24px;
}

/* =========================================================
   FORM
========================================================= */

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
    grid-column:1 / -1;
}

.form-group label{
    font-size:13px;
    font-weight:600;
    color:#334155;
}

.form-group input,
.form-group select{
    padding:12px 13px;
    border:1px solid #dbe2ea;
    border-radius:12px;
    font-size:13px;
}

.form-group input:focus,
.form-group select:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,0.1);
}

.field-hint{
    color:#64748b;
    font-size:12px;
    line-height:1.4;
}

.submit-btn{
    background:linear-gradient(135deg,#1e7d4f,#249c63);
    color:white;
    border:none;
    padding:14px;
    border-radius:14px;
    font-weight:600;
    cursor:pointer;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .page-content{
        margin-left:0;
    }

    .form-grid{
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

<div class="page-content">

<div class="content-wrapper">

<!-- =========================================================
   HEADER
========================================================= -->

<div class="page-header">

    <div>

        <h2>
            Meter Management
        </h2>

        <div class="page-subtitle">
            Smart and conventional meter monitoring, management and control
        </div>

    </div>

</div>

<!-- =========================================================
   TOP ACTION BAR
========================================================= -->

<div class="top-action-bar">

    <button
        class="register-meter-btn"
        onclick="openRegisterModal()">

        + Register Meter

    </button>

</div>

<!-- =========================================================
   TABLE CARD
========================================================= -->

<div class="card">

<table id="meterTable"
       class="display"
       style="width:100%">

<thead>

<tr>

<th>Meter Details</th>
<th>Customer Details</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row = $result->fetch_assoc()): ?>

<?php

$status = $row['status'] ?? 'Inactive';

$statusClass =
$status == 'Active'
? 'status-active'
: 'status-inactive';

?>

<tr>

<!-- =========================================================
   METER DETAILS
========================================================= -->

<td>

    <div class="detail-title">
        Serial Number
    </div>

    <div class="detail-main">
        <?= htmlspecialchars($row['serial_number']); ?>
    </div>

    <div class="detail-sub">
        Model:
        <?= htmlspecialchars($row['model']); ?>
    </div>

    <div class="detail-sub">
        Type:
        <?= htmlspecialchars($row['meter_type']); ?>
    </div>

    <div class="detail-sub">
        Installed:
        <?= htmlspecialchars($row['installation_date']); ?>
    </div>

</td>

<!-- =========================================================
   CUSTOMER DETAILS
========================================================= -->

<td>

    <div class="detail-title">
        Customer
    </div>

    <div class="detail-main">
        <?= htmlspecialchars($row['customer_name']); ?>
    </div>

    <div class="detail-sub">
        Type:
        <?= htmlspecialchars($row['customer_type']); ?>
    </div>

    <div class="detail-sub">
        Zone:
        <?= htmlspecialchars($row['zone']); ?>
    </div>

</td>

<!-- =========================================================
   STATUS
========================================================= -->

<td>

    <span class="status-badge <?= $statusClass; ?>">

        ● <?= htmlspecialchars($status); ?>

    </span>

</td>

<!-- =========================================================
   ACTION
========================================================= -->

<td>

<div class="action-wrapper">

<button
class="action-btn"
onclick="toggleMenu(this)">

&#8942;

</button>

<div class="action-menu">

<button
onclick='openEditModal(
<?= json_encode($row); ?>
)'>

✏ Edit Meter

</button>

<button
type="button"
onclick='openMeterDeactivationModal(<?= json_encode($row); ?>)'>

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

</div>

<!-- =========================================================
   EDIT MODAL
========================================================= -->

<div id="editModal"
     class="modal">

<div class="modal-content">

<div class="modal-header">

<h3>
Edit Meter
</h3>

<button
class="close-modal"
onclick="closeModal()">

×

</button>

</div>

<div class="modal-body">

<form method="POST">

<input
type="hidden"
name="meter_id"
id="edit_meter_id">

<div class="form-grid">

<div class="form-group">

<label>Serial Number</label>

<input
type="text"
name="serial_number"
id="edit_serial"
required>

</div>

<div class="form-group">

<label>Model</label>

<input
type="text"
name="model"
id="edit_model"
required>

</div>

<div class="form-group">

<label>Meter Type</label>

<select
name="meter_type"
id="edit_meter_type"
required>

<option value="Smart Meter">
Smart Meter
</option>

<option value="Conventional Meter">
Conventional Meter
</option>

</select>

</div>

<div class="form-group">

<label>Installation Date</label>

<input
type="date"
name="installation_date"
id="edit_installation_date"
required>

</div>

<div class="form-group">

<label>Customer Name</label>

<input
type="text"
name="customer_name"
id="edit_customer_name"
required>

</div>

<div class="form-group">

<label>Customer Type</label>

<select
name="customer_type"
id="edit_customer_type"
required>

<option value="Residential">
Residential
</option>

<option value="Commercial">
Commercial
</option>

<option value="Government Entities">
Government Entities
</option>

<option value="Domestic">
Domestic
</option>

</select>

</div>

<div class="form-group">

<label>Zone</label>

<select
name="zone"
id="edit_zone"
required>

<option>Westlands</option>
<option>Shimo</option>
<option>Kasarani</option>
<option>Kundakindu</option>
<option>Town</option>
<option>Unoa</option>
<option>Kitikyumu</option>
<option>Mukuyuni</option>
<option>Muambani</option>
<option>Mwaani</option>
<option>Kaiti</option>
<option>Kilala</option>

</select>

</div>

<div class="form-group full">

<button
type="submit"
name="update_meter"
class="submit-btn">

Update Meter

</button>

</div>

</div>

</form>

</div>

</div>

</div>

<!-- =========================================================
   REGISTER MODAL
========================================================= -->

<div id="registerModal"
     class="modal">

<div class="modal-content">

<div class="modal-header">

<h3>
Register Meter
</h3>

<button
class="close-modal"
onclick="closeRegisterModal()">

×

</button>

</div>

<div class="modal-body">

<form method="POST">

<div class="form-grid">

<div class="form-group">

<label>Meter Serial</label>

<input
type="text"
name="serial_number"
id="register_serial_number"
placeholder="Enter meter serial number"
required>

<small class="field-hint">
Enter the meter serial manually. Duplicate serials are blocked automatically.
</small>

</div>

<div class="form-group">

<label>Model</label>

<input
type="text"
name="model"
required>

</div>

<div class="form-group">

<label>Meter Type</label>

<select name="meter_type" id="register_meter_type" required>

<option value="">
-- Select Meter Type --
</option>

<option value="Smart Meter">
Smart Meter
</option>

<option value="Conventional Meter">
Conventional Meter
</option>

</select>

</div>

<div class="form-group">

<label>Customer Name</label>

<input
type="text"
name="customer_name"
id="register_customer_name"
required>

</div>

<div class="form-group">

<label>National ID</label>

<input
type="text"
name="national_id"
id="register_national_id"
required>

</div>

<div class="form-group">

<label>Phone</label>

<input
type="text"
name="customer_phone"
id="register_customer_phone"
required>

</div>

<div class="form-group">

<label>Alt Phone</label>

<input
type="text"
name="alternative_phone">

</div>

<div class="form-group">

<label>Customer Type</label>

<select name="customer_type" id="register_customer_type" required>

<option value="">
-- Select --
</option>

<option>
Government Entities
</option>

<option>
Residential
</option>

<option>
Commercial
</option>

<option>
Domestic
</option>

</select>

</div>

<div class="form-group">

<label>Date</label>

<input
type="date"
name="installation_date"
id="register_installation_date"
max="<?= date('Y-m-d'); ?>"
required>

</div>

<div class="form-group">

<label>Zone</label>

<select name="zone" id="register_zone" required>

<option value="">
-- Select Zone --
</option>

<option>Westlands</option>
<option>Shimo</option>
<option>Kasarani</option>
<option>Kundakindu</option>
<option>Town</option>
<option>Unoa</option>
<option>Kitikyumu</option>
<option>Mukuyuni</option>
<option>Muambani</option>
<option>Mwaani</option>
<option>Kaiti</option>
<option>Kilala</option>

</select>

</div>

<div class="form-group full">

<button
type="submit"
name="register_meter"
class="submit-btn">

Register Meter

</button>

</div>

</div>

</form>

</div>

</div>

</div>

<!-- =========================================================
   DEACTIVATION REQUEST MODAL
========================================================= -->

<div id="meterDeactivationModal"
     class="modal">

<div class="modal-content">

<div class="modal-header">

<h3>
Request Meter Deactivation
</h3>

<button
class="close-modal"
onclick="closeMeterDeactivationModal()">

×

</button>

</div>

<div class="modal-body">

<form method="POST" enctype="multipart/form-data">

<input
type="hidden"
name="meter_id"
id="deactivation_meter_id">

<div class="form-grid">

<div class="form-group full">
<label>Meter Details</label>
<input
type="text"
id="deactivation_meter_label"
readonly>
</div>

<div class="form-group full">
<label>Reason for Deactivation</label>
<select name="deactivation_reason" required>
<option value="">-- Select Reason --</option>
<option value="Faulty meter">Faulty meter</option>
<option value="Meter replacement">Meter replacement</option>
<option value="Customer disconnected">Customer disconnected</option>
<option value="Duplicate or wrong record">Duplicate or wrong record</option>
<option value="Meter retired">Meter retired</option>
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
<small class="field-hint">Accepted: JPG, PNG or PDF.</small>
</div>

<div class="form-group full">
<button
type="submit"
name="request_deactivation_meter"
class="submit-btn">

Submit Deactivation Request

</button>
</div>

</div>

</form>

</div>

</div>

</div>
<!-- =========================================================
   SCRIPTS
========================================================= -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>

<script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js"></script>

<script>

/* =========================================================
   DATATABLE
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    if ($.fn.DataTable.isDataTable('#meterTable')) {

        $('#meterTable').DataTable().destroy();
    }

    $('#meterTable').DataTable({

        paging: true,

        searching: true,

        ordering: true,

        info: true,

        autoWidth: false,

        pageLength: 10,

        lengthMenu: [
            [5, 10, 25, 50, 100],
            [5, 10, 25, 50, 100]
        ],

        pagingType: "full_numbers",

        order: [[0, "desc"]],

        columnDefs: [
            {
                orderable: false,
                targets: [3]
            }
        ],

        language: {

            search: "",

            searchPlaceholder: "Search meters...",

            lengthMenu: "Show _MENU_ meters",

            info: "Showing _START_ to _END_ of _TOTAL_ meters",

            paginate: {
                first: "«",
                last: "»",
                previous: "‹",
                next: "›"
            }
        }

    });

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

    if(menu.style.display === 'block'){

        menu.style.display = 'none';

    }else{

        menu.style.display = 'block';
    }
}

/* =========================================================
   CLOSE MENUS
========================================================= */

document.addEventListener('click', function(e){

    if(!e.target.closest('.action-wrapper')){

        document.querySelectorAll('.action-menu')
        .forEach(menu => {

            menu.style.display = 'none';
        });
    }
});

/* =========================================================
   EDIT MODAL
========================================================= */

function openEditModal(data){

    document.getElementById('editModal')
    .style.display = 'flex';

    document.getElementById('edit_meter_id').value =
    data.id;

    document.getElementById('edit_serial').value =
    data.serial_number;

    document.getElementById('edit_model').value =
    data.model;

    document.getElementById('edit_meter_type').value =
    data.meter_type;

    document.getElementById('edit_installation_date').value =
    data.installation_date;

    document.getElementById('edit_customer_name').value =
    data.customer_name;

    document.getElementById('edit_customer_type').value =
    data.customer_type;

    document.getElementById('edit_zone').value =
    data.zone;
}

function closeModal(){

    document.getElementById('editModal')
    .style.display = 'none';
}

/* =========================================================
   CLOSE MODAL OUTSIDE
========================================================= */

window.addEventListener('click', function(e){

    const modal =
    document.getElementById('editModal');

    if(e.target === modal){

        closeModal();
    }
});
/* =========================================================
   REGISTER MODAL
========================================================= */

function openRegisterModal(){

    document.getElementById('registerModal')
    .style.display = 'flex';
}

function closeRegisterModal(){

    document.getElementById('registerModal')
    .style.display = 'none';
}

function openMeterDeactivationModal(data){

    document.getElementById('deactivation_meter_id').value = data.id || '';
    document.getElementById('deactivation_meter_label').value =
        (data.serial_number || '') + ' - ' + (data.customer_name || 'N/A');

    document.getElementById('meterDeactivationModal')
    .style.display = 'flex';
}

function closeMeterDeactivationModal(){

    document.getElementById('meterDeactivationModal')
    .style.display = 'none';
}

/* =========================================================
   CLOSE REGISTER MODAL OUTSIDE
========================================================= */

window.addEventListener('click', function(e){

    const registerModal =
    document.getElementById('registerModal');

    if(e.target === registerModal){

        closeRegisterModal();
    }

    const meterDeactivationModal =
    document.getElementById('meterDeactivationModal');

    if(e.target === meterDeactivationModal){

        closeMeterDeactivationModal();
    }
});

</script>

</body>
</html>
