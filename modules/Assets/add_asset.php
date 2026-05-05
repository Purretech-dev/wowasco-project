<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= SAVE (MUST BE FIRST - FIX HEADER ERROR) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $asset_type = $_POST['asset_type'] ?? '';

    $serial_number = '';

    if ($asset_type === 'Smart Meter') {
        $serial_number = $_POST['meter_serial'] ?? '';
    } else {
        $serial_number = $_POST['manual_serial'] ?? '';
    }

    if (!$serial_number) {
        die("Serial number is required.");
    }

    $asset_name = $_POST['asset_name'] ?? '';
    $subtype = $_POST['subtype'] ?? '';
    $location = $_POST['location'] ?? '';
    $status = $_POST['status'] ?? '';

    $asset_value = (float) ($_POST['asset_value'] ?? 0);
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    $date_added = date('Y-m-d');

    /* ================= DEPRECIATION ================= */
    $today = new DateTime();
    $purchase = new DateTime($purchase_date);
    $years_used = $purchase->diff($today)->y;

    $useful_life = 5;
    $annual_depreciation = $asset_value / $useful_life;

    $accumulated_depreciation = min(
        $annual_depreciation * $years_used,
        $asset_value
    );

    $current_value = $asset_value - $accumulated_depreciation;

    /* ================= UPDATE ================= */
    if (!empty($_POST['edit_id'])) {

        $stmt = $conn->prepare("
            UPDATE assets SET
            asset_name=?, asset_type=?, subtype=?, serial_number=?,
            location=?, purchase_date=?, status=?,
            asset_value=?, depreciated_value=?, net_value=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "sssssssdddi",
            $asset_name,
            $asset_type,
            $subtype,
            $serial_number,
            $location,
            $purchase_date,
            $status,
            $asset_value,
            $accumulated_depreciation,
            $current_value,
            $_POST['edit_id']
        );

        $stmt->execute();

    } else {

        /* ================= INSERT ================= */
        $stmt = $conn->prepare("
            INSERT INTO assets
            (asset_name, asset_type, subtype, serial_number, location,
             purchase_date, date_added, status,
             asset_value, depreciated_value, net_value)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "ssssssssddd",
            $asset_name,
            $asset_type,
            $subtype,
            $serial_number,
            $location,
            $purchase_date,
            $date_added,
            $status,
            $asset_value,
            $accumulated_depreciation,
            $current_value
        );

        $stmt->execute();
    }

    /* ================= SAFE REDIRECT ================= */
    header("Location: /wowasco-system/modules/Assets/add_asset.php");
    exit;
}

/* ================= LOAD EDIT DATA ================= */
$editData = null;

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $res = $conn->query("SELECT * FROM assets WHERE id=$id");
    $editData = $res->fetch_assoc();
}

/* ================= LOAD METERS ================= */
$meters = $conn->query("SELECT serial_number, zone, status FROM meters");
$meterData = [];

while ($m = $meters->fetch_assoc()) {
    $meterData[] = $m;
}

/* ================= INCLUDE UI (AFTER PROCESSING) ================= */
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>WOWASCO Asset Register</title>

<style>
body{margin:0;font-family:Segoe UI;background:#f4f7fb;}
.page{margin-left:240px;margin-top:70px;padding:20px;}

.header{
    background:#0b2d5c;color:white;padding:18px;
    border-radius:8px;display:flex;justify-content:space-between;
}

.add-btn{
    background:#0f6b5f;padding:10px 15px;
    border:none;color:white;border-radius:8px;cursor:pointer;
}

.modal{
    display:<?= $editData ? 'flex' : 'none' ?>;
    position:fixed;top:0;left:0;width:100%;height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;align-items:center;
}

.modal-content{
    background:white;padding:25px;border-radius:12px;width:520px;
}

.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.full{grid-column:1/-1;}
input,select{width:100%;padding:7px;}

.btn{
    width:100%;margin-top:15px;padding:10px;
    background:#0f6b5f;color:white;border:none;
}

.hidden{display:none;}
</style>
</head>

<body>

<div class="page">

<div class="header">
WOWASCO Asset Register
<button class="add-btn" onclick="document.getElementById('assetModal').style.display='flex'">
+ Add Asset
</button>
</div>

<div class="modal" id="assetModal">
<div class="modal-content">

<form method="POST">

<input type="hidden" name="edit_id" value="<?= $editData['id'] ?? '' ?>">

<div class="grid">

<div class="full">
<label>Asset Name</label>
<input name="asset_name" required value="<?= $editData['asset_name'] ?? '' ?>">
</div>

<div>
<label>Asset Type</label>
<select name="asset_type" id="asset_type" onchange="toggleSmartMeter()" required>
<option value="">Select</option>
<option value="Smart Meter" <?= ($editData['asset_type'] ?? '')=='Smart Meter'?'selected':'' ?>>Smart Meter</option>
<option value="Field Asset" <?= ($editData['asset_type'] ?? '')=='Field Asset'?'selected':'' ?>>Field Asset</option>
<option value="Office Asset" <?= ($editData['asset_type'] ?? '')=='Office Asset'?'selected':'' ?>>Office Asset</option>
</select>
</div>

<div>
<label>Subtype</label>
<select name="subtype">
<option <?= ($editData['subtype'] ?? '')=='Fixed Asset'?'selected':'' ?>>Fixed Asset</option>
<option <?= ($editData['subtype'] ?? '')=='Digital Asset'?'selected':'' ?>>Digital Asset</option>
</select>
</div>

<div class="full <?= ($editData['asset_type'] ?? '')=='Smart Meter' ? '' : 'hidden' ?>" id="smart_meter_box">
<label>Meter Serial</label>
<select name="meter_serial" id="meter_serial" onchange="fillMeterDetails()">
<option value="">Select Meter</option>
<?php foreach($meterData as $m): ?>
<option value="<?= $m['serial_number'] ?>"
<?= ($editData['serial_number'] ?? '')==$m['serial_number']?'selected':'' ?>>
<?= $m['serial_number'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="full <?= ($editData['asset_type'] ?? '')=='Smart Meter' ? 'hidden' : '' ?>" id="manual_box">
<label>Serial Number</label>
<input name="manual_serial" value="<?= $editData['serial_number'] ?? '' ?>">
</div>

<div class="full">
<label>Location</label>
<input name="location" id="location" value="<?= $editData['location'] ?? '' ?>">
</div>

<div>
<label>Asset Value</label>
<input name="asset_value" value="<?= $editData['asset_value'] ?? '' ?>">
</div>

<div>
<label>Purchase Date</label>
<input type="date" name="purchase_date" value="<?= $editData['purchase_date'] ?? '' ?>">
</div>

<div class="full">
<label>Status</label>
<select name="status" id="status">
<option <?= ($editData['status'] ?? '')=='Active'?'selected':'' ?>>Active</option>
<option <?= ($editData['status'] ?? '')=='Inactive'?'selected':'' ?>>Inactive</option>
<option <?= ($editData['status'] ?? '')=='Faulty'?'selected':'' ?>>Faulty</option>
</select>
</div>

</div>

<button class="btn">
<?= $editData ? 'Update Asset' : 'Add Asset' ?>
</button>

</form>

</div>
</div>

</div>

<script>
let meterData = <?= json_encode($meterData) ?>;

function toggleSmartMeter(){
    let type = document.getElementById("asset_type").value;

    document.getElementById("smart_meter_box").style.display =
        (type === "Smart Meter") ? "block" : "none";

    document.getElementById("manual_box").style.display =
        (type === "Smart Meter") ? "none" : "block";
}

function fillMeterDetails(){

    let serial = document.getElementById("meter_serial").value;
    let meter = meterData.find(m => m.serial_number === serial);

    if(!meter) return;

    document.getElementById("location").value = meter.zone || '';

    let statusSelect = document.getElementById("status");

    for(let i=0;i<statusSelect.options.length;i++){
        if(statusSelect.options[i].value === meter.status){
            statusSelect.selectedIndex = i;
            break;
        }
    }
}
</script>

</body>
</html>