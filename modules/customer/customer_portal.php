<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function generateRef($prefix) {
    return $prefix . '-' . date('Ymd') . '-' . rand(1000, 9999);
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

function addColumnIfMissing($conn, $table, $column, $definition){
    if (tableExists($conn, $table) && !columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

function ensureCustomerCaseUpdatesTable($conn){
    $conn->query("
        CREATE TABLE IF NOT EXISTS customer_case_updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_type VARCHAR(80) NOT NULL,
            case_id INT NOT NULL,
            action_taken VARCHAR(150) NOT NULL,
            old_status VARCHAR(80) NULL,
            new_status VARCHAR(80) NULL,
            staff_name VARCHAR(150) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(case_type),
            INDEX(case_id),
            INDEX(created_at)
        )
    ");
}

addColumnIfMissing($conn, 'customer_complaints', 'evidence_file', "VARCHAR(255) NULL");
ensureCustomerCaseUpdatesTable($conn);

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
        $message = "<div class='alert-card red'><strong>Duplicate Application</strong><br>An application with this ID number already exists.</div>";
    } else {

        $uploadPath = "";

        if (!empty($_FILES['national_id_copy']['name'])) {

            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
            $fileName = $_FILES['national_id_copy']['name'];
            $fileTmp = $_FILES['national_id_copy']['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedTypes)) {
                $message = "<div class='alert-card red'><strong>Invalid Upload</strong><br>Only JPG, JPEG, PNG and PDF files are allowed.</div>";
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
                    <div class='alert-card green'>
                        <strong>Meter application submitted successfully.</strong><br>
                        Reference Number: <strong>$application_ref</strong>
                    </div>
                ";
            } else {
                $message = "<div class='alert-card red'><strong>Submission Failed</strong><br>Failed to submit meter application.</div>";
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
            <div class='alert-card green'>
                <strong>Enquiry submitted successfully.</strong><br>
                Reference Number: <strong>$enquiry_ref</strong>
            </div>
        ";
    } else {
        $message = "<div class='alert-card red'><strong>Submission Failed</strong><br>Failed to submit enquiry.</div>";
    }
}

/* ================= SAVE COMPLAINT ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {

    $complaint_ref = generateRef('CMP');
    $evidencePath = "";

    if (!empty($_FILES['complaint_evidence']['name'])) {

        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileName = $_FILES['complaint_evidence']['name'];
        $fileTmp = $_FILES['complaint_evidence']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedTypes)) {
            $message = "<div class='alert-card red'><strong>Invalid Upload</strong><br>Complaint evidence must be JPG, JPEG, PNG or PDF.</div>";
        } else {

            $uploadDir = __DIR__ . '/../../uploads/complaint_evidence/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = 'CMP_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $evidencePath = 'uploads/complaint_evidence/' . $newFileName;
            }
        }
    }

    if ($message !== "") {
        $activeTab = 'complaint';
    } else {

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
                description,
                evidence_file
            )
            VALUES (?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "sssssssss",
            $complaint_ref,
            $_POST['customer_name'],
            $_POST['contact'],
            $_POST['meter_serial'],
            $_POST['zone'],
            $_POST['complaint_type'],
            $_POST['priority'],
            $_POST['description'],
            $evidencePath
        );

        if ($stmt->execute()) {
            $message = "
                <div class='alert-card green'>
                    <strong>Complaint submitted successfully.</strong><br>
                    Reference Number: <strong>$complaint_ref</strong>
                </div>
            ";
        } else {
            $message = "<div class='alert-card red'><strong>Submission Failed</strong><br>Failed to submit complaint.</div>";
        }
    }
}

/* ================= TRACK REQUEST ================= */

$trackingResult = null;
$trackingType = "";
$trackingUpdates = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_request'])) {

    $reference = trim($_POST['reference_number']);
    $caseTypeForAudit = '';

    if (str_starts_with($reference, 'MTRAPP')) {
        $stmt = $conn->prepare("SELECT * FROM meter_applications WHERE application_ref=? LIMIT 1");
        $trackingType = "Meter Application";
        $caseTypeForAudit = "Meter Application";
    } elseif (str_starts_with($reference, 'ENQ')) {
        $stmt = $conn->prepare("SELECT * FROM customer_enquiries WHERE enquiry_ref=? LIMIT 1");
        $trackingType = "Enquiry";
        $caseTypeForAudit = "Enquiry";
    } else {
        $stmt = $conn->prepare("SELECT * FROM customer_complaints WHERE complaint_ref=? LIMIT 1");
        $trackingType = "Complaint";
        $caseTypeForAudit = "Complaint";
    }

    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $trackingResult = $stmt->get_result()->fetch_assoc();

    if ($trackingResult && tableExists($conn, 'customer_case_updates')) {
        $caseId = (int)$trackingResult['id'];
        $updatesStmt = $conn->prepare("
            SELECT *
            FROM customer_case_updates
            WHERE case_type = ?
            AND case_id = ?
            ORDER BY created_at ASC, id ASC
        ");

        if ($updatesStmt) {
            $updatesStmt->bind_param("si", $caseTypeForAudit, $caseId);
            $updatesStmt->execute();
            $updatesResult = $updatesStmt->get_result();

            while ($update = $updatesResult->fetch_assoc()) {
                $trackingUpdates[] = $update;
            }
        }
    }
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

    if (!empty($meterSerials) && tableExists($conn, 'bills')) {

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

$rationingRows = [];

if (
    tableExists($conn, 'zone_supply_schedule') &&
    tableExists($conn, 'zones') &&
    tableExists($conn, 'water_sources')
) {
    $scheduleResult = $conn->query("
        SELECT
            'Supply Schedule' AS notice_type,
            z.zone_name AS zone,
            zss.supply_day AS rationing_day,
            zss.start_time,
            zss.end_time,
            ws.source_name,
            COALESCE(zss.notes, '') AS notice,
            zss.status,
            NULL AS schedule_date
        FROM zone_supply_schedule zss
        LEFT JOIN zones z ON z.id = zss.zone_id
        LEFT JOIN water_sources ws ON ws.id = zss.source_id
        WHERE zss.status='Active'
        ORDER BY z.zone_name ASC, zss.supply_day ASC, zss.start_time ASC
    ");

    if ($scheduleResult) {
        while ($row = $scheduleResult->fetch_assoc()) {
            $rationingRows[] = $row;
        }
    }
}

if (tableExists($conn, 'zone_maintenance') && tableExists($conn, 'zones')) {
    $maintenanceResult = $conn->query("
        SELECT
            'Scheduled Maintenance' AS notice_type,
            z.zone_name AS zone,
            DAYNAME(zm.maintenance_date) AS rationing_day,
            '00:00:00' AS start_time,
            '23:59:00' AS end_time,
            '' AS source_name,
            TRIM(CONCAT(zm.issue_title, '. ', COALESCE(zm.issue_description, ''))) AS notice,
            zm.status,
            zm.maintenance_date AS schedule_date
        FROM zone_maintenance zm
        LEFT JOIN zones z ON z.id = zm.zone_id
        WHERE zm.status IN ('Open','In Progress')
        ORDER BY zm.maintenance_date ASC, z.zone_name ASC
    ");

    if ($maintenanceResult) {
        while ($row = $maintenanceResult->fetch_assoc()) {
            $rationingRows[] = $row;
        }
    }
}

if (empty($rationingRows) && tableExists($conn, 'water_rationing_schedule')) {
    $legacyRationing = $conn->query("
        SELECT
            'Published Notice' AS notice_type,
            zone,
            rationing_day,
            start_time,
            end_time,
            '' AS source_name,
            notice,
            status,
            NULL AS schedule_date
        FROM water_rationing_schedule
        WHERE status='Active'
        ORDER BY zone ASC, rationing_day ASC
    ");

    if ($legacyRationing) {
        while ($row = $legacyRationing->fetch_assoc()) {
            $rationingRows[] = $row;
        }
    }
}

/* ================= COUNTS ================= */

$totalApplications = tableExists($conn, 'meter_applications')
    ? (int)$conn->query("SELECT COUNT(*) AS c FROM meter_applications")->fetch_assoc()['c']
    : 0;

$totalEnquiries = tableExists($conn, 'customer_enquiries')
    ? (int)$conn->query("SELECT COUNT(*) AS c FROM customer_enquiries")->fetch_assoc()['c']
    : 0;

$totalComplaints = tableExists($conn, 'customer_complaints')
    ? (int)$conn->query("SELECT COUNT(*) AS c FROM customer_complaints")->fetch_assoc()['c']
    : 0;

$activeRationing = count($rationingRows);

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

<div class="container">

    <div class="page-header">
        <div>
            <h2>Customer Portal</h2>
            <p>Applications, enquiries, complaints, rationing updates, customer meter details, billing and request tracking.</p>
        </div>

        <div class="header-badge">
            Customer Service Center
        </div>
    </div>

    <?= $message ?>

    <div class="filters portal-tabs">

        <button type="button" class="tab-btn <?= $activeTab === 'meterApplication' ? 'active' : '' ?>" onclick="openPortalTab(event, 'meterApplication')">
            Meter Application
        </button>

        <button type="button" class="tab-btn <?= $activeTab === 'enquiry' ? 'active' : '' ?>" onclick="openPortalTab(event, 'enquiry')">
            Enquiry
        </button>

        <button type="button" class="tab-btn <?= $activeTab === 'complaint' ? 'active' : '' ?>" onclick="openPortalTab(event, 'complaint')">
            Complaint
        </button>

        <button type="button" class="tab-btn <?= $activeTab === 'rationing' ? 'active' : '' ?>" onclick="openPortalTab(event, 'rationing')">
            Water Rationing
        </button>

        <button type="button" class="tab-btn <?= $activeTab === 'meterBills' ? 'active' : '' ?>" onclick="openPortalTab(event, 'meterBills')">
            My Meter & Bills
        </button>

        <button type="button" class="tab-btn <?= $activeTab === 'tracking' ? 'active' : '' ?>" onclick="openPortalTab(event, 'tracking')">
            Track Request
        </button>

    </div>

    <div class="grid">

        <div class="panel">

            <div id="meterApplication" class="portal-section <?= $activeTab === 'meterApplication' ? 'active' : '' ?>">

                <h3 class="section-title">Meter Application</h3>

                <div class="insight-box">
                    Apply for a new water meter connection by filling in your details below.
                    After submission, a reference number will be generated for tracking.
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

                    <button type="submit" name="submit_meter_application" class="btn">
                        Submit Application
                    </button>

                </form>

            </div>

            <div id="enquiry" class="portal-section <?= $activeTab === 'enquiry' ? 'active' : '' ?>">

                <h3 class="section-title">Submit Enquiry</h3>

                <div class="insight-box">
                    Submit general enquiries about billing, water supply, meter application, sewerage or connection process.
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

                    <button type="submit" name="submit_enquiry" class="btn">
                        Submit Enquiry
                    </button>

                </form>

            </div>

            <div id="complaint" class="portal-section <?= $activeTab === 'complaint' ? 'active' : '' ?>">

                <h3 class="section-title">Submit Complaint</h3>

                <div class="insight-box">
                    Existing customers can report supply interruptions, billing issues, meter faults, wrong readings, leakages or sewerage issues.
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

                        <div class="form-group full-width">
                            <label>Upload Evidence Photo / Document</label>
                            <input type="file" name="complaint_evidence" accept=".jpg,.jpeg,.png,.pdf">
                            <small>Optional. Allowed formats: JPG, JPEG, PNG, PDF.</small>
                        </div>

                    </div>

                    <button type="submit" name="submit_complaint" class="btn">
                        Submit Complaint
                    </button>

                </form>

            </div>

            <div id="rationing" class="portal-section <?= $activeTab === 'rationing' ? 'active' : '' ?>">

                <h3 class="section-title">Water Rationing Schedule</h3>

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Zone</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Source</th>
                                <th>Notice</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($rationingRows) > 0): ?>
                                <?php foreach ($rationingRows as $r): ?>
                                    <tr>
                                        <td><?= clean($r['notice_type']) ?></td>
                                        <td><?= clean($r['zone']) ?></td>
                                        <td>
                                            <?= clean($r['rationing_day']) ?>
                                            <?php if (!empty($r['schedule_date'])): ?>
                                                <br><small><?= clean($r['schedule_date']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= clean($r['start_time']) ?> - <?= clean($r['end_time']) ?></td>
                                        <td><?= clean($r['source_name'] ?: 'N/A') ?></td>
                                        <td><?= clean($r['notice']) ?></td>
                                        <td><span class="badge good"><?= clean($r['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">No active water rationing schedule available from Zone Management.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div>

            </div>

            <div id="meterBills" class="portal-section <?= $activeTab === 'meterBills' ? 'active' : '' ?>">

                <h3 class="section-title">My Meter & Bills</h3>

                <div class="insight-box">
                    Search using your meter serial number or national ID number to view your registered meter and billing information.
                </div>

                <form method="POST" class="form-card">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Meter Serial Number / National ID Number</label>
                            <input type="text" name="lookup_value" required value="<?= clean($_POST['lookup_value'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" name="lookup_customer" class="btn">
                        Search My Information
                    </button>

                </form>

                <?php if (isset($_POST['lookup_customer'])): ?>

                    <?php if (count($customerMeters) > 0): ?>
                        <div class="lookup-results">
                            <?php foreach ($customerMeters as $m): ?>
                                <div class="lookup-card">
                                    <h4>Meter Details</h4>

                                    <div class="detail-grid">
                                        <div>
                                            <span>Serial Number</span>
                                            <strong><?= clean($m['serial_number'] ?? 'N/A') ?></strong>
                                        </div>

                                        <div>
                                            <span>Customer</span>
                                            <strong><?= clean($m['customer_name'] ?? 'N/A') ?></strong>
                                        </div>

                                        <div>
                                            <span>Zone</span>
                                            <strong><?= clean($m['zone'] ?? 'N/A') ?></strong>
                                        </div>

                                        <div>
                                            <span>Status</span>
                                            <strong><?= clean($m['status'] ?? 'N/A') ?></strong>
                                        </div>

                                        <div>
                                            <span>Model</span>
                                            <strong><?= clean($m['model'] ?? 'N/A') ?></strong>
                                        </div>

                                        <div>
                                            <span>Installation Date</span>
                                            <strong><?= clean($m['installation_date'] ?? 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="lookup-card">
                                <h4>Billing Details</h4>

                                <?php if (count($customerBills) > 0): ?>
                                    <div class="bill-list">
                                        <?php foreach ($customerBills as $b): ?>
                                            <div class="bill-row">
                                                <div>
                                                    <strong><?= clean($b['bill_month'] ?? 'Billing Record') ?></strong>
                                                    <span><?= clean($b['serial_number'] ?? '') ?></span>
                                                </div>

                                                <div>
                                                    <strong>KSh <?= number_format((float)($b['amount'] ?? 0), 2) ?></strong>
                                                    <span><?= clean($b['status'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-result">No billing information found for this meter.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-result">No meter or billing information found for the entered meter serial or national ID.</div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

            <div id="tracking" class="portal-section <?= $activeTab === 'tracking' ? 'active' : '' ?>">

                <h3 class="section-title">Track Request</h3>

                <div class="insight-box">
                    Use your reference number to track a meter application, enquiry or complaint.
                </div>

                <form method="POST" class="form-card">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Reference Number</label>
                            <input type="text" name="reference_number" placeholder="Example: MTRAPP-20260513-1234" required>
                        </div>
                    </div>

                    <button type="submit" name="track_request" class="btn">
                        Track Request
                    </button>

                </form>

                <?php if (isset($_POST['track_request'])): ?>

                    <div class="tracking-panel">

                        <?php if ($trackingResult): ?>

                            <div class="tracking-summary">
                                <div>
                                    <span>Request Type</span>
                                    <strong><?= clean($trackingType) ?></strong>
                                </div>

                                <div>
                                    <span>Current Status</span>
                                    <strong><span class="badge good"><?= clean($trackingResult['status'] ?? 'Submitted') ?></span></strong>
                                </div>

                                <div>
                                    <span>Date Submitted</span>
                                    <strong><?= clean($trackingResult['created_at'] ?? '') ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($trackingResult['response'])): ?>
                                <div class="tracking-note">
                                    <strong>Latest Response</strong>
                                    <p><?= clean($trackingResult['response']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($trackingResult['remarks'])): ?>
                                <div class="tracking-note">
                                    <strong>Remarks</strong>
                                    <p><?= clean($trackingResult['remarks']) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="tracking-timeline">
                                <h4>Request Movement</h4>

                                <div class="timeline-item">
                                    <strong>Submitted</strong>
                                    <span><?= clean($trackingResult['created_at'] ?? '') ?></span>
                                    <p>Your request was received by WOWASCO.</p>
                                </div>

                                <?php foreach ($trackingUpdates as $update): ?>
                                    <div class="timeline-item">
                                        <strong><?= clean($update['action_taken']) ?></strong>
                                        <span><?= clean($update['created_at']) ?></span>
                                        <p>
                                            Status: <?= clean($update['old_status'] ?: 'New') ?>
                                            &rarr;
                                            <?= clean($update['new_status']) ?>
                                        </p>

                                        <?php if (!empty($update['notes'])): ?>
                                            <p><?= clean($update['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($trackingUpdates)): ?>
                                    <div class="timeline-item">
                                        <strong>Awaiting Review</strong>
                                        <span>Current stage</span>
                                        <p>No staff action has been recorded yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>

                            <div class="empty-result">No request found with that reference number.</div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<style>
body{
    margin:0;
    background:#f8fafc;
    font-family:'Segoe UI',sans-serif;
    color:#1e293b;
}

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:22px;
}

.page-header h2{
    margin:0;
    font-size:26px;
    font-weight:800;
    color:#0f172a;
}

.page-header p{
    margin:6px 0 0;
    font-size:13px;
    color:#64748b;
}

.header-badge{
    background:#ecfdf3;
    color:#15803d;
    border:1px solid #bbf7d0;
    padding:10px 14px;
    border-radius:12px;
    font-size:13px;
    font-weight:700;
}

.section-title{
    margin:0 0 14px;
    font-size:16px;
    font-weight:800;
    color:#0f172a;
}

.filters{
    margin-top:24px;
    background:white;
    padding:18px;
    border-radius:16px;
    border:1px solid #e2e8f0;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}

.portal-tabs{
    margin-bottom:0;
}

.tab-btn{
    padding:11px 16px;
    border:none;
    border-radius:10px;
    background:#f8fafc;
    color:#334155;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;
    font-size:13px;
    display:inline-block;
    border:1px solid #dbe2ea;
}

.tab-btn.active{
    background:#1e7d4f;
    color:#ffffff;
    border-color:#1e7d4f;
}

.grid{
    display:grid;
    grid-template-columns:1fr;
    gap:20px;
    margin-top:24px;
}

.panel{
    background:white;
    border-radius:16px;
    border:1px solid #e2e8f0;
    padding:20px;
    box-shadow:0 4px 14px rgba(15,23,42,0.03);
}

.portal-section{
    display:none;
}

.portal-section.active{
    display:block;
}

.form-card{
    margin-top:14px;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:14px;
}

.form-group{
    display:flex;
    flex-direction:column;
}

.form-group label{
    margin-bottom:6px;
    color:#334155;
    font-size:13px;
    font-weight:700;
}

.form-group input,
.form-group select,
.form-group textarea{
    padding:11px 12px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
    background:white;
    color:#1e293b;
}

.form-group textarea{
    min-height:100px;
    resize:vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{
    outline:none;
    border-color:#1e7d4f;
}

.form-group small{
    margin-top:6px;
    color:#64748b;
    font-size:12px;
}

.full-width{
    grid-column:1/-1;
}

button,
.btn{
    padding:11px 16px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    cursor:pointer;
    font-weight:700;
    text-decoration:none;
    font-size:13px;
    display:inline-block;
    margin-top:14px;
}

.btn-blue{
    background:#1e3a8a;
}

.btn-light{
    background:#f8fafc;
    color:#334155;
    border:1px solid #dbe2ea;
}

.table-wrapper{
    overflow-x:auto;
    margin-top:14px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#f8fafc;
    color:#334155;
    padding:14px;
    text-align:left;
    font-size:13px;
    border-bottom:2px solid #e2e8f0;
    white-space:nowrap;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
    vertical-align:top;
}

tr:hover td{
    background:#fcfcfc;
}

.badge{
    padding:6px 11px;
    border-radius:20px;
    font-size:12px;
    font-weight:800;
    display:inline-block;
}

.critical{
    background:#fef2f2;
    color:#dc2626;
}

.warning{
    background:#fffbeb;
    color:#ca8a04;
}

.good{
    background:#ecfdf3;
    color:#15803d;
}

.lookup-results{
    display:grid;
    gap:14px;
    margin-top:18px;
}

.lookup-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:16px;
}

.lookup-card h4{
    margin:0 0 12px;
    color:#0f172a;
    font-size:15px;
}

.detail-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    gap:10px;
}

.detail-grid div{
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:10px;
}

.detail-grid span,
.bill-row span{
    display:block;
    color:#64748b;
    font-size:12px;
    margin-top:3px;
}

.detail-grid strong,
.bill-row strong{
    color:#1e293b;
    font-size:13px;
}

.bill-list{
    display:grid;
    gap:8px;
}

.bill-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:10px;
}

.empty-result{
    background:#f8fafc;
    border:1px dashed #cbd5e1;
    border-radius:12px;
    color:#64748b;
    font-size:13px;
    padding:14px;
    margin-top:14px;
}

.tracking-panel{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:16px;
    margin-top:14px;
}

.tracking-summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:10px;
}

.tracking-summary div{
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:10px;
}

.tracking-summary span{
    display:block;
    color:#64748b;
    font-size:12px;
    margin-bottom:4px;
}

.tracking-summary strong{
    color:#1e293b;
    font-size:13px;
}

.tracking-note{
    margin-top:12px;
    padding-top:12px;
    border-top:1px solid #eef2f7;
}

.tracking-note strong,
.tracking-timeline h4{
    color:#0f172a;
    font-size:14px;
}

.tracking-note p{
    margin:6px 0 0;
    color:#475569;
    font-size:13px;
    line-height:1.6;
}

.tracking-timeline{
    margin-top:16px;
}

.timeline-item{
    border-left:3px solid #1e7d4f;
    padding:0 0 14px 14px;
    margin-top:12px;
}

.timeline-item strong{
    display:block;
    color:#0f172a;
    font-size:13px;
}

.timeline-item span{
    display:block;
    color:#64748b;
    font-size:12px;
    margin-top:3px;
}

.timeline-item p{
    margin:6px 0 0;
    color:#475569;
    font-size:13px;
    line-height:1.5;
}

.alert-card{
    border-left:4px solid #facc15;
    padding:14px;
    border-radius:12px;
    margin-bottom:12px;
    font-size:13px;
    background:#fffbeb;
    color:#713f12;
}

.alert-card.red{
    background:#fef2f2;
    color:#991b1b;
    border-left-color:#dc2626;
}

.alert-card.green{
    background:#ecfdf3;
    color:#166534;
    border-left-color:#22c55e;
}

.alert-card.blue{
    background:#eff6ff;
    color:#1e3a8a;
    border-left-color:#1e3a8a;
}

.insight-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:14px;
    border-radius:12px;
    margin-top:14px;
    font-size:13px;
    color:#475569;
    line-height:1.7;
}

@media(max-width:1000px){

    .grid{
        grid-template-columns:1fr;
    }

    .container{
        margin-left:0;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .filters{
        flex-direction:column;
        align-items:stretch;
    }

    .tab-btn{
        width:100%;
    }

    .bill-row{
        align-items:flex-start;
        flex-direction:column;
    }
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
