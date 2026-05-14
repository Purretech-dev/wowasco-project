<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function generateRef($prefix) {
    return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
}

$message = "";

/* ================= SAVE METER APPLICATION ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_meter_application'])) {

    $application_ref = generateRef('MTRAPP');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $zone = trim($_POST['zone'] ?? '');
    $meter_type = trim($_POST['meter_type'] ?? '');
    $customer_type = trim($_POST['customer_type'] ?? '');

    $check = $conn->prepare("SELECT id FROM meter_applications WHERE id_number=? LIMIT 1");
    $check->bind_param("s", $id_number);
    $check->execute();
    $exists = $check->get_result();

    if ($exists->num_rows > 0) {
        $message = "<div class='alert error'>An application with this ID number already exists.</div>";
    } else {

        $uploadPath = "";

        if (!empty($_FILES['national_id_copy']['name'])) {

            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
            $fileName = $_FILES['national_id_copy']['name'];
            $fileTmp = $_FILES['national_id_copy']['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedTypes)) {
                $message = "<div class='alert error'>Only JPG, JPEG, PNG and PDF files are allowed.</div>";
            } else {

                $uploadDir = __DIR__ . '/../../uploads/customer_ids/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newFileName = 'ID_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $uploadPath = 'uploads/customer_ids/' . $newFileName;
                }
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("
                INSERT INTO meter_applications
                (
                    application_ref,
                    customer_name,
                    contact,
                    id_number,
                    zone,
                    meter_type,
                    customer_type,
                    national_id_copy
                )
                VALUES (?,?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "ssssssss",
                $application_ref,
                $customer_name,
                $contact,
                $id_number,
                $zone,
                $meter_type,
                $customer_type,
                $uploadPath
            );

            if ($stmt->execute()) {
                $message = "
                    <div class='alert success'>
                        Meter application submitted successfully. 
                        Reference Number: <strong>$application_ref</strong>
                    </div>
                ";
            } else {
                $message = "<div class='alert error'>Failed to submit meter application.</div>";
            }
        }
    }
}

/* ================= SAVE ENQUIRY ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_enquiry'])) {

    $enquiry_ref = generateRef('ENQ');

    $stmt = $conn->prepare("
        INSERT INTO customer_enquiries
        (
            enquiry_ref,
            customer_name,
            contact,
            email,
            enquiry_type,
            subject,
            message
        )
        VALUES (?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "sssssss",
        $enquiry_ref,
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['email'],
        $_POST['enquiry_type'],
        $_POST['subject'],
        $_POST['message']
    );

    if ($stmt->execute()) {
        $message = "
            <div class='alert success'>
                Enquiry submitted successfully. 
                Reference Number: <strong>$enquiry_ref</strong>
            </div>
        ";
    } else {
        $message = "<div class='alert error'>Failed to submit enquiry.</div>";
    }
}

/* ================= SAVE COMPLAINT ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {

    $complaint_ref = generateRef('CMP');

    $stmt = $conn->prepare("
        INSERT INTO customer_complaints
        (
            complaint_ref,
            customer_name,
            contact,
            meter_serial,
            zone,
            complaint_type,
            priority,
            description
        )
        VALUES (?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssssssss",
        $complaint_ref,
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['meter_serial'],
        $_POST['zone'],
        $_POST['complaint_type'],
        $_POST['priority'],
        $_POST['description']
    );

    if ($stmt->execute()) {
        $message = "
            <div class='alert success'>
                Complaint submitted successfully. 
                Reference Number: <strong>$complaint_ref</strong>
            </div>
        ";
    } else {
        $message = "<div class='alert error'>Failed to submit complaint.</div>";
    }
}

/* ================= TRACK REQUEST ================= */

$trackingResult = null;
$trackingType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_request'])) {

    $reference = trim($_POST['reference_number']);

    if (str_starts_with($reference, 'MTRAPP')) {
        $stmt = $conn->prepare("SELECT * FROM meter_applications WHERE application_ref=? LIMIT 1");
        $trackingType = "Meter Application";
    } elseif (str_starts_with($reference, 'ENQ')) {
        $stmt = $conn->prepare("SELECT * FROM customer_enquiries WHERE enquiry_ref=? LIMIT 1");
        $trackingType = "Enquiry";
    } else {
        $stmt = $conn->prepare("SELECT * FROM customer_complaints WHERE complaint_ref=? LIMIT 1");
        $trackingType = "Complaint";
    }

    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $trackingResult = $stmt->get_result()->fetch_assoc();
}

/* ================= CUSTOMER LOOKUP ================= */

$customerMeters = [];
$customerBills = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_customer'])) {

    $lookup = trim($_POST['lookup_value'] ?? '');

    $meterQuery = $conn->prepare("
        SELECT *
        FROM meters
        WHERE serial_number = ?
        OR national_id = ?
        ORDER BY id DESC
    ");

    $meterQuery->bind_param("ss", $lookup, $lookup);
    $meterQuery->execute();
    $meterResult = $meterQuery->get_result();

    $meterSerials = [];

    if ($meterResult) {
        while ($row = $meterResult->fetch_assoc()) {
            $customerMeters[] = $row;

            if (!empty($row['serial_number'])) {
                $meterSerials[] = $row['serial_number'];
            }
        }
    }

    if (!empty($meterSerials)) {

        $placeholders = implode(',', array_fill(0, count($meterSerials), '?'));
        $types = str_repeat('s', count($meterSerials));

        $billQuery = $conn->prepare("
            SELECT *
            FROM bills
            WHERE serial_number IN ($placeholders)
            ORDER BY bill_month DESC, id DESC
        ");

        $billQuery->bind_param($types, ...$meterSerials);
        $billQuery->execute();
        $billResult = $billQuery->get_result();

        if ($billResult) {
            while ($row = $billResult->fetch_assoc()) {
                $customerBills[] = $row;
            }
        }
    }
}

/* ================= WATER RATIONING ================= */

$rationing = $conn->query("
    SELECT *
    FROM water_rationing_schedule
    WHERE status='Active'
    ORDER BY zone ASC, rationing_day ASC
");

/* ================= ACTIVE TAB ================= */

$activeTab = 'meterApplication';

if (isset($_POST['submit_enquiry'])) {
    $activeTab = 'enquiry';
} elseif (isset($_POST['submit_complaint'])) {
    $activeTab = 'complaint';
} elseif (isset($_POST['lookup_customer'])) {
    $activeTab = 'meterBills';
} elseif (isset($_POST['track_request'])) {
    $activeTab = 'tracking';
}
?>

<div class="page-content">

    <div class="module-header">
        <h2>Customer Portal</h2>
        <p>Meter applications, enquiries, complaints, water rationing, meter details and billing information.</p>
    </div>

    <?= $message ?>

    <div class="portal-tabs">
        <button class="tab-btn <?= $activeTab === 'meterApplication' ? 'active' : '' ?>" onclick="openPortalTab(event, 'meterApplication')">Meter Application</button>
        <button class="tab-btn <?= $activeTab === 'enquiry' ? 'active' : '' ?>" onclick="openPortalTab(event, 'enquiry')">Enquiry</button>
        <button class="tab-btn <?= $activeTab === 'complaint' ? 'active' : '' ?>" onclick="openPortalTab(event, 'complaint')">Complaint</button>
        <button class="tab-btn <?= $activeTab === 'rationing' ? 'active' : '' ?>" onclick="openPortalTab(event, 'rationing')">Water Rationing</button>
        <button class="tab-btn <?= $activeTab === 'meterBills' ? 'active' : '' ?>" onclick="openPortalTab(event, 'meterBills')">My Meter & Bills</button>
        <button class="tab-btn <?= $activeTab === 'tracking' ? 'active' : '' ?>" onclick="openPortalTab(event, 'tracking')">Track Request</button>
    </div>

    <div id="meterApplication" class="portal-section <?= $activeTab === 'meterApplication' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>Meter Application</h3>
            <p>Fill in the form below to apply for a water meter connection.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="form-card">

            <div class="form-grid">

                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" required>
                </div>

                <div class="form-group">
                    <label>National ID Number</label>
                    <input type="text" name="id_number" required>
                </div>

                <div class="form-group">
                    <label>Zone</label>
                    <input type="text" name="zone" required>
                </div>

                <div class="form-group">
                    <label>Meter Type</label>
                    <select name="meter_type" required>
                        <option value="">Select Meter Type</option>
                        <option value="Smart Meter">Smart Meter</option>
                        <option value="Conventional">Conventional</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Customer Type</label>
                    <select name="customer_type" required>
                        <option value="">Select Customer Type</option>
                        <option value="Domestic">Domestic</option>
                        <option value="Government Entities">Government Entities</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Residential">Residential</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Upload ID Copy</label>
                    <input type="file" name="national_id_copy" accept=".jpg,.jpeg,.png,.pdf" required>
                    <small>Allowed formats: JPG, JPEG, PNG, PDF.</small>
                </div>

            </div>

            <button type="submit" name="submit_meter_application" class="submit-btn">
                Submit Application
            </button>

        </form>

    </div>

    <div id="enquiry" class="portal-section <?= $activeTab === 'enquiry' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>Submit Enquiry</h3>
            <p>First-time and existing customers can submit enquiries here.</p>
        </div>

        <form method="POST" class="form-card">

            <div class="form-grid">

                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>

                <div class="form-group">
                    <label>Enquiry Type</label>
                    <select name="enquiry_type" required>
                        <option value="">Select Enquiry Type</option>
                        <option>General Enquiry</option>
                        <option>Meter Application</option>
                        <option>Billing</option>
                        <option>Water Supply</option>
                        <option>Sewerage</option>
                        <option>Connection Process</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Subject</label>
                    <input type="text" name="subject" required>
                </div>

                <div class="form-group full-width">
                    <label>Message</label>
                    <textarea name="message" required></textarea>
                </div>

            </div>

            <button type="submit" name="submit_enquiry" class="submit-btn">
                Submit Enquiry
            </button>

        </form>

    </div>

    <div id="complaint" class="portal-section <?= $activeTab === 'complaint' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>Submit Complaint</h3>
            <p>Existing customers can submit service-related complaints.</p>
        </div>

        <form method="POST" class="form-card">

            <div class="form-grid">

                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact" required>
                </div>

                <div class="form-group">
                    <label>Meter Serial Number</label>
                    <input type="text" name="meter_serial">
                </div>

                <div class="form-group">
                    <label>Zone</label>
                    <input type="text" name="zone">
                </div>

                <div class="form-group">
                    <label>Complaint Type</label>
                    <select name="complaint_type" required>
                        <option value="">Select Complaint Type</option>
                        <option>Water Supply Interruption</option>
                        <option>Billing Issue</option>
                        <option>Meter Fault</option>
                        <option>Wrong Meter Reading</option>
                        <option>Leakage</option>
                        <option>Sewerage Issue</option>
                        <option>Staff Conduct</option>
                        <option>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" required>
                        <option>Low</option>
                        <option selected>Medium</option>
                        <option>High</option>
                        <option>Critical</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" required></textarea>
                </div>

            </div>

            <button type="submit" name="submit_complaint" class="submit-btn">
                Submit Complaint
            </button>

        </form>

    </div>

    <div id="rationing" class="portal-section <?= $activeTab === 'rationing' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>Water Rationing Schedule</h3>
            <p>View active water rationing notices by zone.</p>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Notice</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rationing && $rationing->num_rows > 0): ?>
                        <?php while ($r = $rationing->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean($r['zone']) ?></td>
                                <td><?= clean($r['rationing_day']) ?></td>
                                <td><?= clean($r['start_time']) ?></td>
                                <td><?= clean($r['end_time']) ?></td>
                                <td><?= clean($r['notice']) ?></td>
                                <td><span class="status-badge"><?= clean($r['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No active water rationing schedule available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div id="meterBills" class="portal-section <?= $activeTab === 'meterBills' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>My Meter & Bills</h3>
            <p>Enter either your meter serial number or national ID number to view your meter and billing details.</p>
        </div>

        <form method="POST" class="form-card">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Meter Serial Number / National ID Number</label>
                    <input type="text" name="lookup_value" required value="<?= clean($_POST['lookup_value'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" name="lookup_customer" class="submit-btn">
                Search My Information
            </button>

        </form>

        <?php if (isset($_POST['lookup_customer'])): ?>

            <div class="table-card">
                <h4>Meter Information</h4>

                <table>
                    <thead>
                        <tr>
                            <th>Serial Number</th>
                            <th>Customer</th>
                            <th>National ID</th>
                            <th>Phone</th>
                            <th>Alternative Phone</th>
                            <th>Zone</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Installation Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customerMeters) > 0): ?>
                            <?php foreach ($customerMeters as $m): ?>
                                <tr>
                                    <td><?= clean($m['serial_number'] ?? '') ?></td>
                                    <td><?= clean($m['customer_name'] ?? '') ?></td>
                                    <td><?= clean($m['national_id'] ?? '') ?></td>
                                    <td><?= clean($m['customer_phone'] ?? '') ?></td>
                                    <td><?= clean($m['alternative_phone'] ?? '') ?></td>
                                    <td><?= clean($m['zone'] ?? '') ?></td>
                                    <td><?= clean($m['model'] ?? '') ?></td>
                                    <td><span class="status-badge"><?= clean($m['status'] ?? '') ?></span></td>
                                    <td><?= clean($m['installation_date'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No meter information found for the entered meter serial or national ID.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h4>Billing Information</h4>

                <table>
                    <thead>
                        <tr>
                            <th>Bill No</th>
                            <th>Meter Serial</th>
                            <th>Bill Month</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customerBills) > 0): ?>
                            <?php foreach ($customerBills as $b): ?>
                                <tr>
                                    <td><?= clean($b['id'] ?? '') ?></td>
                                    <td><?= clean($b['serial_number'] ?? '') ?></td>
                                    <td><?= clean($b['bill_month'] ?? '') ?></td>
                                    <td>KSh <?= number_format((float)($b['amount'] ?? 0), 2) ?></td>
                                    <td><span class="status-badge"><?= clean($b['status'] ?? '') ?></span></td>
                                    <td><?= clean($b['created_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No billing information found for the entered meter serial or national ID.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>

    <div id="tracking" class="portal-section <?= $activeTab === 'tracking' ? 'active' : '' ?>">

        <div class="section-header">
            <h3>Track Request</h3>
            <p>Track your meter application, enquiry, or complaint using your reference number.</p>
        </div>

        <form method="POST" class="form-card">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" placeholder="Example: MTRAPP-20260513-1234" required>
                </div>
            </div>

            <button type="submit" name="track_request" class="submit-btn">
                Track Request
            </button>

        </form>

        <?php if (isset($_POST['track_request'])): ?>

            <div class="tracking-card">

                <?php if ($trackingResult): ?>

                    <h4><?= clean($trackingType) ?> Status</h4>

                    <p>
                        <strong>Status:</strong>
                        <span class="status-badge"><?= clean($trackingResult['status'] ?? '') ?></span>
                    </p>

                    <?php if (!empty($trackingResult['response'])): ?>
                        <p><strong>Response:</strong> <?= clean($trackingResult['response']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($trackingResult['remarks'])): ?>
                        <p><strong>Remarks:</strong> <?= clean($trackingResult['remarks']) ?></p>
                    <?php endif; ?>

                    <p><strong>Date Submitted:</strong> <?= clean($trackingResult['created_at'] ?? '') ?></p>

                <?php else: ?>

                    <p>No request found with that reference number.</p>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<style>
.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:20px;
    background:#f4f7fb;
    min-height:calc(100vh - 135px);
    font-family:Arial, sans-serif;
}

.module-header{
    background:#ffffff;
    padding:18px 20px;
    border-radius:10px;
    margin-bottom:18px;
    border-left:4px solid #0a2a43;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
}

.module-header h2{
    margin:0;
    color:#0a2a43;
    font-size:20px;
}

.module-header p{
    margin:6px 0 0;
    color:#64748b;
    font-size:14px;
}

.portal-tabs{
    background:#ffffff;
    padding:12px;
    border-radius:10px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:18px;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
}

.tab-btn{
    border:1px solid #d1d5db;
    background:#ffffff;
    color:#334155;
    padding:8px 12px;
    border-radius:6px;
    font-size:13px;
    cursor:pointer;
}

.tab-btn.active{
    background:#0a2a43;
    color:#ffffff;
    border-color:#0a2a43;
}

.portal-section{
    display:none;
}

.portal-section.active{
    display:block;
}

.section-header{
    background:#ffffff;
    padding:16px 18px;
    border-radius:10px;
    margin-bottom:14px;
    border:1px solid #e5e7eb;
}

.section-header h3{
    margin:0;
    color:#0a2a43;
    font-size:18px;
}

.section-header p{
    margin:5px 0 0;
    color:#64748b;
    font-size:14px;
}

.form-card,
.table-card,
.tracking-card{
    background:#ffffff;
    padding:18px;
    border-radius:10px;
    border:1px solid #e5e7eb;
    margin-bottom:18px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:15px;
}

.form-group{
    display:flex;
    flex-direction:column;
}

.form-group label{
    margin-bottom:6px;
    color:#334155;
    font-size:13px;
    font-weight:600;
}

.form-group input,
.form-group select,
.form-group textarea{
    padding:10px;
    border:1px solid #d1d5db;
    border-radius:6px;
    font-size:14px;
    background:#ffffff;
}

.form-group textarea{
    min-height:100px;
    resize:vertical;
}

.form-group small{
    color:#64748b;
    font-size:12px;
    margin-top:5px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
    outline:none;
    border-color:#0a2a43;
}

.full-width{
    grid-column:1/-1;
}

.submit-btn{
    margin-top:18px;
    background:#0a2a43;
    color:white;
    border:none;
    padding:9px 15px;
    border-radius:6px;
    font-size:14px;
    cursor:pointer;
}

.submit-btn:hover{
    background:#123a5a;
}

.alert{
    padding:12px 15px;
    border-radius:8px;
    margin-bottom:18px;
    font-size:14px;
}

.alert.success{
    background:#f0fdf4;
    color:#166534;
    border-left:4px solid #16a34a;
}

.alert.error{
    background:#fef2f2;
    color:#991b1b;
    border-left:4px solid #dc2626;
}

.table-card{
    overflow-x:auto;
}

.table-card h4,
.tracking-card h4{
    margin-top:0;
    color:#0a2a43;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

th{
    background:#f8fafc;
    color:#334155;
    text-align:left;
    padding:10px;
    border-bottom:1px solid #e5e7eb;
}

td{
    padding:10px;
    border-bottom:1px solid #e5e7eb;
    color:#334155;
}

.status-badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:20px;
    font-size:12px;
    background:#f1f5f9;
    color:#334155;
    border:1px solid #cbd5e1;
}
</style>

<script>
function openPortalTab(evt, tabName){
    let sections = document.getElementsByClassName("portal-section");
    let buttons = document.getElementsByClassName("tab-btn");

    for(let i = 0; i < sections.length; i++){
        sections[i].classList.remove("active");
    }

    for(let i = 0; i < buttons.length; i++){
        buttons[i].classList.remove("active");
    }

    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>