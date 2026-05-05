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
/* =========================
   GLOBAL LAYOUT FIX (PROPER CENTERING)
========================= */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#eef3f8;
    margin:0;
    
}

/* CONTENT AREA (ALREADY OFFSET BY SIDEBAR + NAVBAR) */
.page-wrapper{
    margin-left: 240px;
    padding-top: 80px;
    padding-bottom: 60px;

    min-height: 100vh;
    box-sizing: border-box;

    display: flex;
    justify-content: center;
}

/* THIS FIXES RIGHT SHIFT ISSUE */
.center-wrapper{
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* FORM CONTAINER */
.container {
    width: 100%;
    max-width: 650px;
    background:#fff;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    margin: 40px auto;
    margin-left: calc(260px + 40px);
     margin-left: 260px; /* accounts for sidebar */
    padding: 0 20px;
    justify-content: center;
}

h2 {
    text-align:center;
    color:#003366;
    margin-bottom:25px;
}

.message {
    padding:12px;
    margin-bottom:20px;
    border-radius:6px;
    text-align:center;
    font-weight:bold;
}
.success {background:#d4edda;color:#155724;}
.error {background:#f8d7da;color:#721c24;}

form {
    display:flex;
    flex-direction:column;
    gap:14px;
}

.form-group {
    display:flex;
    flex-direction:column;
    gap:6px;
}

label {
    font-weight:600;
    color:#333;
}

input, select {
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #ccc;
    font-size:14px;
}

input:focus, select:focus {
    border-color:#003366;
    box-shadow:0 0 5px rgba(0,51,102,0.2);
    outline:none;
}

button {
    width:100%;
    padding:12px;
    background:#003366;
    color:#fff;
    border:none;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
}

button:hover {
    background:#00509e;
}

.back-btn {
    display:block;
    margin-top:10px;
    padding:10px;
    background:#6c757d;
    color:#fff;
    text-decoration:none;
    border-radius:6px;
    text-align:center;
}
</style>
</head>

<body>

<div class="page-wrapper">

    <div class="center-wrapper">

        <div class="container">

        <h2>Register New Meter</h2>

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
        <label>Customer Phone Number</label>
        <input type="text" name="customer_phone" required>
        </div>

        <div class="form-group">
        <label>Alternative Phone Number</label>
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
        <label>Installation Date</label>
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

        <button type="submit">Register Meter</button>

        <a href="/wowasco-system/dashboard.php?page=modules/home.php" class="back-btn">← Back to Dashboard</a>

        </form>

        </div>

    </div>

</div>

</body>
</html>