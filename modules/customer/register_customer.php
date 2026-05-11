<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= SAVE CUSTOMER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {

    $stmt = $conn->prepare("
        INSERT INTO customers 
        (name, phone, alt_phone, id_number, meter_type, customer_type, zone)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssssss",
        $_POST['name'],
        $_POST['phone'],
        $_POST['alt_phone'],
        $_POST['id_number'],
        $_POST['meter_type'],
        $_POST['customer_type'],
        $_POST['zone']
    );

    $stmt->execute();
}

/* ================= SEARCH ================= */
$search = $_GET['id_number'] ?? '';

$where = "WHERE 1";

if ($search != '') {
    $searchEsc = $conn->real_escape_string($search);
    $where .= " AND id_number LIKE '%$searchEsc%'";
}

/* ================= EXPORT (EXCEL FIX) ================= */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=customers_export.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $export = $conn->query("SELECT * FROM customers $where ORDER BY id DESC");

    echo "Name\tPhone\tID Number\tZone\tType\tMeter Type\n";

    while ($row = $export->fetch_assoc()) {
        echo $row['name'] . "\t" .
             $row['phone'] . "\t" .
             $row['id_number'] . "\t" .
             $row['zone'] . "\t" .
             $row['customer_type'] . "\t" .
             $row['meter_type'] . "\n";
    }

    exit();
}

/* ================= DATA ================= */
$customers = $conn->query("
    SELECT * FROM customers 
    $where 
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Customer Module V8</title>

<style>
body{
    margin:0;
    font-family:"Inter","Segoe UI",sans-serif;
    background:#f1f5f9;
    color:#1e293b;
    line-height:1.5;
    letter-spacing:0.2px;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
}
/* ================= TYPOGRAPHY ENHANCEMENT ================= */

h1,h2,h3,h4,h5,h6{
    margin-top:0;
    color:#0f172a;
    font-weight:800;
    letter-spacing:-0.5px;
}

h4{
    font-size:22px;
    margin-bottom:18px;
}

p{
    font-size:14px;
    color:#475569;
}

input,
select{
    font-size:14px;
    font-weight:500;
    color:#1e293b;
}

input::placeholder{
    color:#94a3b8;
}

button,
.actions a{
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

/* PAGE */
.page{
    margin-left:240px;
    margin-top:70px;
    padding:20px;
    padding-bottom:120px;
}

/* HEADER */
.header{
    background:linear-gradient(90deg,#1e7d4f,#2ca66f);
    color:white;
    padding:12px;
    border-radius:8px;
    margin-bottom:15px;
    font-weight:bold;
}

/* CARD */
.card{
    background:white;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
    margin-bottom:15px;
}

/* FORM */
.form-container{
    max-width:520px;
    margin:auto;
}

input, select{
    width:100%;
    padding:8px;
    margin-bottom:8px;
    border:1px solid #ddd;
    border-radius:6px;
    font-size:13px;
}

/* BUTTON */
button{
    background:#2ca66f;
    color:white;
    border:none;
    padding:7px 10px;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
}

button:hover{
    background:#24935f;
}

/* VIEW BUTTON */
.view-btn{
    background:#1e7d4f;
    margin-bottom:10px;
}

/* SEARCH */
.search-box{
    display:flex;
    gap:6px;
    margin-bottom:10px;
}

.search-box input{
    width:260px;
    padding:6px;
    font-size:12px;
}

/* ACTIONS */
.actions{
    margin-top:10px;
    display:flex;
    gap:8px;
}

.actions a{
    text-decoration:none;
    padding:6px 10px;
    border-radius:6px;
    font-size:12px;
    color:white;
}

.excel{ background:#1e7d4f; }
.pdf{ background:#e53935; }
.print{ background:#333; }

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th{
    background:#2ca66f;
    color:white;
    padding:8px;
    text-align:left;
}

td{
    padding:8px;
    border-bottom:1px solid #eee;
}

/* TOGGLE */
#tableSection{
    display:none;
}

/* ================= PRINT FIX (ONLY TABLE) ================= */
@media print {

    body * {
        visibility: hidden;
    }

    #tableSection, #tableSection * {
        visibility: visible;
    }

    #tableSection {
        position:absolute;
        left:0;
        top:0;
        width:100%;
    }

    .no-print {
        display:none !important;
    }
}
</style>

<script>
function toggleTable(){
    let section = document.getElementById("tableSection");

    if(section.style.display === "block"){
        section.style.display = "none";
    } else {
        section.style.display = "block";

        setTimeout(() => {
            section.scrollIntoView({behavior:"smooth", block:"start"});
        }, 50);
    }
}

function printPage(){
    window.print();
}
</script>

</head>

<body>

<?php
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page">

<div class="header">Customer Management Module V8</div>

<!-- FORM -->
<div class="card no-print">
<div class="form-container">

<h4>➕ Add Customer</h4>

<form method="POST">
<input name="name" placeholder="Name" required>
<input name="phone" placeholder="Phone" required>
<input name="alt_phone" placeholder="Alt Phone">
<input name="id_number" placeholder="ID Number" required>

<select name="meter_type">
<option>Meter Type</option>
<option>Smart Meter</option>
<option>Manual Meter</option>
</select>

<select name="customer_type">
<option>Customer Type</option>
<option>Domestic</option>
<option>Commercial</option>
</select>

<input name="zone" placeholder="Zone">

<button name="save_customer">Save Customer</button>
</form>

</div>
</div>

<!-- VIEW BUTTON -->
<button class="view-btn" onclick="toggleTable()">
👁 View Customer Records
</button>

<!-- TABLE -->
<div id="tableSection" class="card">

<!-- SEARCH -->
<form method="GET" class="search-box no-print">
<input type="text" name="id_number" placeholder="Search by ID Number..." value="<?= htmlspecialchars($search) ?>">
<button>Search</button>
</form>

<!-- ACTIONS -->
<div class="actions no-print">
<a class="excel" href="?id_number=<?= htmlspecialchars($search) ?>&export=excel">⬇ Excel</a>
<a class="pdf" href="#" onclick="alert('Add TCPDF for PDF export')">⬇ PDF</a>
<a class="print" href="#" onclick="printPage()">🖨 Print</a>
</div>

<!-- RESULTS -->
<table>
<tr>
<th>Name</th>
<th>Phone</th>
<th>ID</th>
<th>Zone</th>
<th>Type</th>
<th>Meter</th>
</tr>

<?php while($c = $customers->fetch_assoc()): ?>
<tr>
<td><?= $c['name'] ?></td>
<td><?= $c['phone'] ?></td>
<td><?= $c['id_number'] ?></td>
<td><?= $c['zone'] ?></td>
<td><?= $c['customer_type'] ?></td>
<td><?= $c['meter_type'] ?></td>
</tr>
<?php endwhile; ?>

</table>

</div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>