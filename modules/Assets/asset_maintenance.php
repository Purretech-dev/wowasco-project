<?php
$conn = new mysqli("localhost", "root", "", "wowasco");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$editData = null;

/* ================= SOFT DELETE ================= */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("UPDATE infrastructure SET deleted=1 WHERE id=$id");
    header("Location: infrastructure.php");
    exit();
}

/* ================= EDIT ================= */
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $res = $conn->query("SELECT * FROM infrastructure WHERE id=$id AND deleted=0");
    $editData = $res->fetch_assoc();
}

/* ================= IMAGE UPLOAD ================= */
function uploadImage($file){
    if (!isset($file) || $file['error'] != 0) return null;

    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($file["name"]);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return $targetFile;
    }
    return null;
}

/* ================= ADD / UPDATE ================= */
if (isset($_POST['add'])) {

    $name = $_POST['name'];
    $type = $_POST['type'];
    $location = $_POST['location'];
    $zone = $_POST['zone'];
    $status = $_POST['status'];
    $asset_category = $_POST['asset_category'];
    $activity = $_POST['activity'];

    if ($activity == "Maintenance") {
        $status = "Under Maintenance";
    }

    $photo = uploadImage($_FILES['photo']);

    if (!empty($_POST['edit_id'])) {

        $id = (int) $_POST['edit_id'];

        $stmt = $conn->prepare("
            UPDATE infrastructure SET
            name=?, type=?, location=?, zone=?, status=?,
            asset_category=?, activity=?, photo=COALESCE(?, photo)
            WHERE id=? AND deleted=0
        ");

        $stmt->bind_param(
            "ssssssssi",
            $name,$type,$location,$zone,$status,
            $asset_category,$activity,$photo,$id
        );

        $message = $stmt->execute() ? "Updated successfully." : "Error updating.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO infrastructure 
            (name, type, location, zone, status, asset_category, activity, photo, deleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");

        $stmt->bind_param(
            "ssssssss",
            $name,$type,$location,$zone,$status,
            $asset_category,$activity,$photo
        );

        $message = $stmt->execute() ? "Added successfully." : "Error adding.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Infrastructure Maintenance</title>

<style>
    /* ================= TYPOGRAPHY ENHANCEMENT ================= */

body{
    font-family:"Inter","Segoe UI",sans-serif;
    color:#1e293b;
    line-height:1.5;
    letter-spacing:0.2px;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
}

h3{
    font-size:18px;
    font-weight:700;
    color:#0f172a;
    letter-spacing:-0.3px;
}

label{
    font-size:13px;
    font-weight:700;
    color:#334155;
    letter-spacing:0.2px;
}

input,
select{
    font-size:14px;
    font-weight:500;
    color:#1e293b;
    letter-spacing:0.2px;
}

input::placeholder{
    color:#94a3b8;
}

button{
    font-size:14px;
    font-weight:700;
    letter-spacing:0.3px;
}

table{
    font-size:14px;
}

th{
    font-size:13px;
    font-weight:700;
    letter-spacing:0.5px;
    text-transform:uppercase;
}

td{
    font-size:14px;
    font-weight:500;
    color:#334155;
}

.badge{
    font-size:11px;
    font-weight:700;
    letter-spacing:0.3px;
}

/* ================= LAYOUT ================= */
.overlay {
    margin-left: 270px;
    margin-top: 80px;
    padding: 20px;
    background: #f4f6f9;
    min-height: 100vh;
}

/* ================= PAGE TITLE (NO ICON) ================= */
h2 {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 22px;
    letter-spacing: -0.7px;
    line-height: 1.2;
}

/* ================= FORM (REDUCED WIDTH) ================= */
.form-card {
    background: #fff;
    padding: 18px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    width: 420px;   /* 🔥 reduced size */
    max-width: 100%;
    margin-bottom: 20px;
}

/* ================= TABLE ================= */
.table-card {
    background: #fff;
    padding: 18px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

/* ================= LABELS ================= */
label {
    font-size: 13px;
    font-weight: 600;
    margin-top: 8px;
    display: block;
    color: #333;
}

/* ================= INPUTS ================= */
input, select {
    width: 100%;
    padding: 8px;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 13px;
}

/* ================= BUTTON ================= */
button {
    margin-top: 12px;
    padding: 9px;
    width: 100%;
    border: none;
    background: #0b2d5c;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

button:hover {
    background: #09407a;
}

/* ================= TABLE ================= */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

th {
    background: #0b2d5c;
    color: white;
}

/* ================= BADGES ================= */
.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    color: white;
}

.Active { background: green; }
.Inactive { background: gray; }
.UnderMaintenance { background: orange; }

/* ================= ACTIONS ================= */
.actions a {
    padding: 4px 6px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
}

.edit-btn { background: #ffc107; color: black; }
.delete-btn { background: #6c757d; color: white; }

</style>
</head>

<body>

<div class="overlay">

<h2>Infrastructure Maintenance Module</h2>

<?php if ($message): ?>
<p style="color:green"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- FORM -->
<div class="form-card">

<h3 style="font-size:14px; margin-bottom:10px;">
<?= $editData ? "Edit Record" : "Add Record" ?>
</h3>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="edit_id" value="<?= $editData['id'] ?? '' ?>">

<label>Name</label>
<input type="text" name="name" value="<?= $editData['name'] ?? '' ?>" required>

<label>Type</label>
<input type="text" name="type" value="<?= $editData['type'] ?? '' ?>" required>

<label>Zone</label>
<select name="zone" required>
<option value="">Select Zone</option>
<option>Kasarani & Town Zone</option>
<option>Westlands Zone</option>
<option>Muambani & Mwaani Zones</option>
<option>Shimo Zone</option>
<option>Kundakindu & Malawi Zones</option>
</select>

<label>Location</label>
<input type="text" name="location" value="<?= $editData['location'] ?? '' ?>">

<label>Asset Category</label>
<select name="asset_category" required>
<option>Fixed Asset</option>
<option>Digital Asset</option>
</select>

<label>Activity</label>
<select name="activity" required>
<option>Repair</option>
<option>Maintenance</option>
<option>Overhaul</option>
</select>

<label>Status</label>
<select name="status">
<option>Active</option>
<option>Inactive</option>
<option>Under Maintenance</option>
</select>

<label>Photo</label>
<input type="file" name="photo">

<button type="submit">
<?= $editData ? "Update Record" : "Save Record" ?>
</button>

</form>

</div>

<!-- TABLE -->
<div class="table-card">

<h3 style="font-size:14px;">Records</h3>

<table>
<tr>
<th>Name</th>
<th>Zone</th>
<th>Type</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php
$res = $conn->query("SELECT * FROM infrastructure WHERE deleted=0 ORDER BY id DESC");
while($row = $res->fetch_assoc()):
?>
<tr>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= htmlspecialchars($row['zone']) ?></td>
<td><?= htmlspecialchars($row['type']) ?></td>
<td><span class="badge <?= str_replace(' ','',$row['status']) ?>"><?= $row['status'] ?></span></td>
<td class="actions">
<a class="edit-btn" href="?edit=<?= $row['id'] ?>">Edit</a>
<a class="delete-btn" href="?delete=<?= $row['id'] ?>">Del</a>
</td>
</tr>
<?php endwhile; ?>

</table>

</div>

</div>

</body>
</html>