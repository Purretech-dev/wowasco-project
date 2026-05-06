<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

$message = '';
$success = false;

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $national_id = $_POST['national_id'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    $alternative_phone = $_POST['alternative_phone'] ?? '';

    $serial_number = $_POST['serial_number'] ?? '';
    $model = $_POST['model'] ?? '';
    $customer_type = $_POST['customer_type'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $meter_type = $_POST['meter_type'] ?? '';
    $installation_date = $_POST['installation_date'] ?? '';
    $zone = $_POST['zone'] ?? '';

    if(
        empty($national_id) ||
        empty($customer_phone) ||
        empty($serial_number) ||
        empty($model) ||
        empty($customer_type) ||
        empty($customer_name) ||
        empty($meter_type) ||
        empty($installation_date) ||
        empty($zone)
    ){
        $message = "Error: Please fill in all required fields.";
        $success = false;
    }

    elseif(strtotime($installation_date) > strtotime(date('Y-m-d'))){
        $message = "Error: Installation date cannot be in the future.";
        $success = false;
    }

    else {

        $check = $conn->prepare("SELECT id FROM meters WHERE serial_number = ?");
        $check->bind_param("s", $serial_number);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $message = "Error: Meter serial already exists.";
            $success = false;
        }
        else {

            $stmt = $conn->prepare("
                INSERT INTO meters 
                (national_id, customer_phone, alternative_phone, serial_number, model, customer_type, customer_name, meter_type, installation_date, zone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssssss",
                $national_id,
                $customer_phone,
                $alternative_phone,
                $serial_number,
                $model,
                $customer_type,
                $customer_name,
                $meter_type,
                $installation_date,
                $zone
            );

            if($stmt->execute()){
                $message = "Meter registered successfully!";
                $success = true;
            } else {
                $message = "Error: " . $stmt->error;
                $success = false;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Meter - WOWASCO</title>

<style>

body{
    font-family:Segoe UI, sans-serif;
    background:#eef3f8;
    margin:0;
}

/* PAGE LAYOUT - SHIFTED LEFT */
.page-wrapper{
    margin-left:240px;
    padding-top:80px;

    display:flex;
    justify-content:flex-start; /* 🔥 move left */
    padding-left:30px;
}

/* FORM CARD */
.container{
    width:100%;
    max-width:380px; /* slightly smaller */
    background:#fff;
    border-radius:12px;
    border:1px solid #e6e6e6;
    box-shadow:0 4px 12px rgba(0,0,0,0.06);
    padding:16px;

    /* 🔥 VERTICAL SCROLL ENABLED */
    max-height:75vh;
    overflow-y:auto;
    scrollbar-width:thin;
}

/* TITLE */
h2{
    text-align:center;
    font-size:16px;
    color:#2c3e50;
    margin-bottom:12px;
}

/* MESSAGE */
.message{
    padding:7px;
    margin-bottom:10px;
    border-radius:6px;
    text-align:center;
    font-size:12px;
}

.success{background:#e9f7ef;color:#1e7d4f;}
.error{background:#fdecea;color:#c0392b;}

/* FORM */
form{
    display:flex;
    flex-direction:column;
    gap:8px;
}

/* FIELDS */
.form-group{
    display:flex;
    flex-direction:column;
    gap:3px;
}

label{
    font-size:11px;
    font-weight:600;
    color:#444;
}

/* INPUTS */
input, select{
    padding:7px;
    border:1px solid #ddd;
    border-radius:6px;
    font-size:12px;
}

/* FOCUS (SUBTLE YELLOW) */
input:focus, select:focus{
    border-color:#f1c40f;
    box-shadow:0 0 0 2px rgba(241,196,15,0.12);
    outline:none;
}

/* BUTTON */
button{
    padding:10px;
    border:none;
    border-radius:7px;
    background:#2c3e50;
    color:white;
    font-size:13px;
    cursor:pointer;

    /* sticky bottom feel */
    position:sticky;
    bottom:0;
}

button:hover{
    background:#34495e;
}

/* BACK BUTTON */
.back-btn{
    display:block;
    text-align:center;
    padding:7px;
    margin-top:6px;
    border-radius:7px;
    text-decoration:none;
    background:#f4f6f7;
    color:#2c3e50;
    border:1px solid #e0e0e0;
    font-size:11px;
}

.back-btn:hover{
    background:#eef2f3;
}

</style>
</head>

<body>

<div class="page-wrapper">

<div class="container">

<h2>Register Meter</h2>

<?php if($message != ''): ?>
<div class="message <?php echo ($success) ? 'success' : 'error'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form method="POST">

<div class="form-group">
<label>Meter Serial</label>
<input type="text" name="serial_number" required>
</div>

<div class="form-group">
<label>Model</label>
<input type="text" name="model" required>
</div>

<div class="form-group">
<label>Meter Type</label>
<select name="meter_type" required>
<option value="">-- Select Meter Type --</option>
<option value="Smart Meter">Smart Meter</option>
<option value="Conventional Meter">Conventional Meter</option>
</select>
</div>

<div class="form-group">
<label>Customer Name</label>
<input type="text" name="customer_name" required>
</div>

<div class="form-group">
<label>National ID</label>
<input type="text" name="national_id" required>
</div>

<div class="form-group">
<label>Phone</label>
<input type="text" name="customer_phone" required>
</div>

<div class="form-group">
<label>Alt Phone</label>
<input type="text" name="alternative_phone">
</div>

<div class="form-group">
<label>Customer Type</label>
<select name="customer_type" required>
<option value="">-- Select --</option>
<option>Government Entities</option>
<option>Residential</option>
<option>Commercial</option>
<option>Domestic</option>
</select>
</div>

<div class="form-group">
<label>Date</label>
<input type="date" name="installation_date" max="<?php echo date('Y-m-d'); ?>" required>
</div>

<div class="form-group">
<label>Zone</label>
<select name="zone" required>
<option value="">-- Select Zone --</option>
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

<button type="submit">Register</button>

<a href="/wowasco-system/dashboard.php?page=modules/home.php" class="back-btn">← Back</a>

</form>

</div>

</div>

</body>
</html>