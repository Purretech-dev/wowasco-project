<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function addColumnIfMissing($conn, $table, $column, $definition) {
    if (tableExists($conn, $table) && !columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

function countRows($conn, $table, $condition = "1=1") {
    if (!tableExists($conn, $table)) return 0;
    $res = $conn->query("SELECT COUNT(*) AS c FROM `$table` WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

function logAction($conn, $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes) {
    if (!tableExists($conn, 'customer_case_updates')) return;

    $stmt = $conn->prepare("
        INSERT INTO customer_case_updates
        (case_type, case_id, action_taken, old_status, new_status, staff_name, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sisssss", $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes);
    $stmt->execute();
}

/* ================= ADD COLUMNS IF MISSING ================= */

addColumnIfMissing($conn, 'meter_applications', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'meter_applications', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'meter_applications', 'rejection_reason', "TEXT NULL");
addColumnIfMissing($conn, 'meter_applications', 'meter_serial', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'meter_applications', 'installation_date', "DATE NULL");
addColumnIfMissing($conn, 'meter_applications', 'reviewed_at', "DATETIME NULL");
addColumnIfMissing($conn, 'meter_applications', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

addColumnIfMissing($conn, 'customer_enquiries', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'customer_enquiries', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'customer_enquiries', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

addColumnIfMissing($conn, 'customer_complaints', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'customer_complaints', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'due_date', "DATE NULL");
addColumnIfMissing($conn, 'customer_complaints', 'resolution_notes', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'escalation_reason', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

/* ================= ACTION HANDLING ================= */

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crm_action'])) {

    $action = $_POST['crm_action'];
    $staff = trim($_POST['staff_name'] ?? 'Back Office');

    if ($action === 'update_application') {
        $id = (int) $_POST['application_id'];
        $status = trim($_POST['status']);
        $assigned = trim($_POST['assigned_staff']);
        $response = trim($_POST['response']);
        $meter_serial = trim($_POST['meter_serial']);
        $installation_date = trim($_POST['installation_date']);

        $old = $conn->query("SELECT status FROM meter_applications WHERE id=$id")->fetch_assoc();
        $oldStatus = $old['status'] ?? '';

        $stmt = $conn->prepare("
            UPDATE meter_applications
            SET status=?, assigned_staff=?, response=?, meter_serial=?, installation_date=?, reviewed_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("sssssi", $status, $assigned, $response, $meter_serial, $installation_date, $id);

        if ($stmt->execute()) {
            logAction($conn, 'Meter Application', $id, 'Application Updated', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Meter application updated successfully.</div>";
        }
    }

    if ($action === 'reject_application') {
        $id = (int) $_POST['application_id'];
        $reason = trim($_POST['rejection_reason']);

        $old = $conn->query("SELECT status FROM meter_applications WHERE id=$id")->fetch_assoc();
        $oldStatus = $old['status'] ?? '';

        $stmt = $conn->prepare("
            UPDATE meter_applications
            SET status='Rejected', rejection_reason=?, response=?, reviewed_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("ssi", $reason, $reason, $id);

        if ($stmt->execute()) {
            logAction($conn, 'Meter Application', $id, 'Application Rejected', $oldStatus, 'Rejected', $staff, $reason);
            $message = "<div class='alert red'><strong>Application Rejected.</strong><br>The application has been rejected successfully.</div>";
        }
    }

    if ($action === 'respond_enquiry') {
        $id = (int) $_POST['enquiry_id'];
        $status = trim($_POST['status']);
        $assigned = trim($_POST['assigned_staff']);
        $response = trim($_POST['response']);

        $old = $conn->query("SELECT status FROM customer_enquiries WHERE id=$id")->fetch_assoc();
        $oldStatus = $old['status'] ?? '';

        $stmt = $conn->prepare("
            UPDATE customer_enquiries
            SET status=?, assigned_staff=?, response=?
            WHERE id=?
        ");
        $stmt->bind_param("sssi", $status, $assigned, $response, $id);

        if ($stmt->execute()) {
            logAction($conn, 'Enquiry', $id, 'Enquiry Responded', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Enquiry updated successfully.</div>";
        }
    }

    if ($action === 'update_complaint') {
        $id = (int) $_POST['complaint_id'];
        $status = trim($_POST['status']);
        $assigned = trim($_POST['assigned_staff']);
        $priority = trim($_POST['priority']);
        $due_date = trim($_POST['due_date']);
        $response = trim($_POST['response']);
        $resolution_notes = trim($_POST['resolution_notes']);
        $escalation_reason = trim($_POST['escalation_reason']);

        $old = $conn->query("SELECT status FROM customer_complaints WHERE id=$id")->fetch_assoc();
        $oldStatus = $old['status'] ?? '';

        $stmt = $conn->prepare("
            UPDATE customer_complaints
            SET status=?, assigned_staff=?, priority=?, due_date=?, response=?, resolution_notes=?, escalation_reason=?
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi", $status, $assigned, $priority, $due_date, $response, $resolution_notes, $escalation_reason, $id);

        if ($stmt->execute()) {
            logAction($conn, 'Complaint', $id, 'Complaint Updated', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Complaint updated successfully.</div>";
        }
    }

    if ($action === 'save_rationing') {
        $zone = trim($_POST['zone']);
        $day = trim($_POST['rationing_day']);
        $start = trim($_POST['start_time']);
        $end = trim($_POST['end_time']);
        $notice = trim($_POST['notice']);
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("
            INSERT INTO water_rationing_schedule
            (zone, rationing_day, start_time, end_time, notice, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $zone, $day, $start, $end, $notice, $status);

        if ($stmt->execute()) {
            $message = "<div class='alert green'><strong>Published.</strong><br>Water rationing notice published successfully.</div>";
        }
    }

    if ($action === 'update_rationing_status') {
        $id = (int) $_POST['rationing_id'];
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("UPDATE water_rationing_schedule SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $message = "<div class='alert green'><strong>Updated.</strong><br>Water rationing status updated successfully.</div>";
        }
    }
}

/* ================= FILTERS ================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

function buildWhere($conn, $search, $statusFilter, $fields) {
    $where = " WHERE 1=1 ";

    if ($search !== '') {
        $safe = $conn->real_escape_string($search);
        $likes = [];
        foreach ($fields as $field) {
            $likes[] = "$field LIKE '%$safe%'";
        }
        $where .= " AND (" . implode(" OR ", $likes) . ")";
    }

    if ($statusFilter !== '') {
        $safeStatus = $conn->real_escape_string($statusFilter);
        $where .= " AND status='$safeStatus'";
    }

    return $where;
}

/* ================= COUNTS ================= */

$totalApps = countRows($conn, 'meter_applications');
$pendingApps = countRows($conn, 'meter_applications', "status IN ('Pending','Submitted','Under Review')");
$approvedApps = countRows($conn, 'meter_applications', "status='Approved'");
$rejectedApps = countRows($conn, 'meter_applications', "status='Rejected'");

$totalEnquiries = countRows($conn, 'customer_enquiries');
$openEnquiries = countRows($conn, 'customer_enquiries', "status IN ('Submitted','Open','In Progress')");
$closedEnquiries = countRows($conn, 'customer_enquiries', "status IN ('Closed','Resolved')");

$totalComplaints = countRows($conn, 'customer_complaints');
$openComplaints = countRows($conn, 'customer_complaints', "status IN ('Submitted','Assigned','In Progress','Escalated')");
$resolvedComplaints = countRows($conn, 'customer_complaints', "status IN ('Resolved','Closed')");
$escalatedComplaints = countRows($conn, 'customer_complaints', "status='Escalated'");

$activeRationing = countRows($conn, 'water_rationing_schedule', "status='Active'");

$totalCases = $totalApps + $totalEnquiries + $totalComplaints;
$openCases = $pendingApps + $openEnquiries + $openComplaints;
$resolvedCases = $approvedApps + $closedEnquiries + $resolvedComplaints;
$riskRate = $totalCases > 0 ? round((($openComplaints + $escalatedComplaints) / $totalCases) * 100) : 0;

/* ================= DATA ================= */

$appWhere = buildWhere($conn, $search, $statusFilter, [
    'application_ref', 'customer_name', 'contact', 'id_number', 'zone', 'meter_type', 'customer_type'
]);

$enquiryWhere = buildWhere($conn, $search, $statusFilter, [
    'enquiry_ref', 'customer_name', 'contact', 'email', 'enquiry_type', 'subject'
]);

$complaintWhere = buildWhere($conn, $search, $statusFilter, [
    'complaint_ref', 'customer_name', 'contact', 'meter_serial', 'zone', 'complaint_type', 'priority'
]);

$applications = tableExists($conn, 'meter_applications') ? $conn->query("SELECT * FROM meter_applications $appWhere ORDER BY id DESC") : false;
$enquiries = tableExists($conn, 'customer_enquiries') ? $conn->query("SELECT * FROM customer_enquiries $enquiryWhere ORDER BY id DESC") : false;
$complaints = tableExists($conn, 'customer_complaints') ? $conn->query("SELECT * FROM customer_complaints $complaintWhere ORDER BY id DESC") : false;
$rationing = tableExists($conn, 'water_rationing_schedule') ? $conn->query("SELECT * FROM water_rationing_schedule ORDER BY id DESC") : false;
$updates = tableExists($conn, 'customer_case_updates') ? $conn->query("SELECT * FROM customer_case_updates ORDER BY id DESC LIMIT 30") : false;
?>

<div class="container">

    <div class="page-header">
        <div>
            <h2>Customer Management Center</h2>
            <p>Back-office customer service, applications, enquiries, complaints, assignments, notices and audit intelligence.</p>
        </div>

        <div class="header-badge">
            Case Exposure: <?= $riskRate ?>%
        </div>
    </div>

    <?= $message ?>

    <div class="kpis">

        <div class="kpi blue">
            <h3><?= number_format($totalCases) ?></h3>
            <p>Total Customer Cases</p>
            <small>Applications, enquiries and complaints</small>
        </div>

        <div class="kpi yellow">
            <h3><?= number_format($openCases) ?></h3>
            <p>Open / Pending Cases</p>
            <small>Requires back-office follow-up</small>
        </div>

        <div class="kpi red">
            <h3><?= number_format($escalatedComplaints) ?></h3>
            <p>Escalated Complaints</p>
            <small>Immediate attention recommended</small>
        </div>

        <div class="kpi">
            <h3><?= number_format($resolvedCases) ?></h3>
            <p>Resolved / Approved</p>
            <small>Completed customer actions</small>
        </div>

    </div>

    <form class="filters" method="GET" action="dashboard.php">

        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'customer_relations/customer_management') ?>">

        <input type="text" name="search" placeholder="Search customer, reference, contact, ID or meter..." value="<?= clean($search) ?>">

        <select name="status">
            <option value="">All Statuses</option>
            <?php
            $statuses = ['Pending','Submitted','Under Review','More Information Required','Approved','Rejected','Assigned','In Progress','Escalated','Resolved','Closed','Completed'];
            foreach ($statuses as $s):
            ?>
                <option value="<?= clean($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                    <?= clean($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>

        <a href="dashboard.php?page=customer_relations/customer_management" class="btn btn-light">
            Reset
        </a>

    </form>

    <div class="filters crm-tabs">

        <button type="button" class="tab-btn active" onclick="openCrmTab(event,'applications')">
            Meter Applications
        </button>

        <button type="button" class="tab-btn" onclick="openCrmTab(event,'enquiries')">
            Enquiries
        </button>

        <button type="button" class="tab-btn" onclick="openCrmTab(event,'complaints')">
            Complaints
        </button>

        <button type="button" class="tab-btn" onclick="openCrmTab(event,'rationing')">
            Water Rationing
        </button>

        <button type="button" class="tab-btn" onclick="openCrmTab(event,'audit')">
            Audit Trail
        </button>

    </div>

    <div class="grid">

        <div class="panel">

            <!-- APPLICATIONS -->

            <div id="applications" class="tab-section active">

                <h3 class="section-title">Meter Application Approvals</h3>

                <div class="insight-box">
                    Review, approve, reject, assign officers, update meter serials and communicate application progress to customers.
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Customer</th>
                                <th>Meter</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th>ID Copy</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($applications && $applications->num_rows > 0): ?>
                            <?php while ($a = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= clean($a['application_ref']) ?></strong><br>
                                        <small><?= clean($a['created_at'] ?? '') ?></small>
                                    </td>

                                    <td>
                                        <?= clean($a['customer_name']) ?><br>
                                        <small>ID: <?= clean($a['id_number']) ?> | <?= clean($a['contact']) ?></small><br>
                                        <small>Zone: <?= clean($a['zone']) ?></small>
                                    </td>

                                    <td>
                                        <?= clean($a['meter_type']) ?><br>
                                        <small><?= clean($a['customer_type']) ?></small><br>
                                        <small>Serial: <?= clean($a['meter_serial'] ?? '') ?></small>
                                    </td>

                                    <td>
                                        <span class="badge warning"><?= clean($a['status'] ?? 'Pending') ?></span>
                                    </td>

                                    <td><?= clean($a['assigned_staff'] ?? 'Unassigned') ?></td>

                                    <td>
                                        <?php if (!empty($a['national_id_copy'])): ?>
                                            <a class="link-btn" href="<?= clean('../../' . $a['national_id_copy']) ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            <small>No file</small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <button class="expand-btn" onclick='openApplicationModal(<?= json_encode($a) ?>)'>Manage</button>
                                        <button class="expand-btn danger" onclick='openRejectModal(<?= (int)$a["id"] ?>)'>Reject</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No meter applications found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- ENQUIRIES -->

            <div id="enquiries" class="tab-section">

                <h3 class="section-title">Enquiry Desk</h3>

                <div class="insight-box">
                    Respond to customer enquiries, assign responsible officers and close completed enquiries.
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Enquiry</th>
                                <th>Customer</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($enquiries && $enquiries->num_rows > 0): ?>
                            <?php while ($e = $enquiries->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= clean($e['enquiry_ref']) ?></strong><br>
                                        <small><?= clean($e['created_at'] ?? '') ?></small>
                                    </td>

                                    <td>
                                        <?= clean($e['customer_name']) ?><br>
                                        <small><?= clean($e['contact']) ?> | <?= clean($e['email']) ?></small>
                                    </td>

                                    <td>
                                        <?= clean($e['subject']) ?><br>
                                        <small><?= clean($e['enquiry_type']) ?></small>
                                    </td>

                                    <td><span class="badge good"><?= clean($e['status'] ?? 'Submitted') ?></span></td>

                                    <td><?= clean($e['assigned_staff'] ?? 'Unassigned') ?></td>

                                    <td>
                                        <button class="expand-btn" onclick='openEnquiryModal(<?= json_encode($e) ?>)'>Respond</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No enquiries found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- COMPLAINTS -->

            <div id="complaints" class="tab-section">

                <h3 class="section-title">Complaint Desk</h3>

                <div class="insight-box">
                    Assign complaints, update progress, escalate urgent issues and record final resolution notes.
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>Customer</th>
                                <th>Issue</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($complaints && $complaints->num_rows > 0): ?>
                            <?php while ($c = $complaints->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= clean($c['complaint_ref']) ?></strong><br>
                                        <small><?= clean($c['created_at'] ?? '') ?></small>
                                    </td>

                                    <td>
                                        <?= clean($c['customer_name']) ?><br>
                                        <small><?= clean($c['contact']) ?></small><br>
                                        <small>Meter: <?= clean($c['meter_serial']) ?></small>
                                    </td>

                                    <td>
                                        <?= clean($c['complaint_type']) ?><br>
                                        <small><?= clean($c['zone']) ?></small>
                                    </td>

                                    <td><span class="badge warning"><?= clean($c['priority']) ?></span></td>

                                    <td><span class="badge critical"><?= clean($c['status'] ?? 'Submitted') ?></span></td>

                                    <td><?= clean($c['assigned_staff'] ?? 'Unassigned') ?></td>

                                    <td>
                                        <button class="expand-btn" onclick='openComplaintModal(<?= json_encode($c) ?>)'>Manage</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No complaints found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- RATIONING -->

            <div id="rationing" class="tab-section">

                <h3 class="section-title">Water Rationing Notices</h3>

                <div class="insight-box">
                    Create and manage water rationing notices visible to customers through the customer portal.
                </div>

                <form method="POST" class="form-card">
                    <input type="hidden" name="crm_action" value="save_rationing">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone</label>
                            <input type="text" name="zone" required>
                        </div>

                        <div class="form-group">
                            <label>Rationing Day</label>
                            <select name="rationing_day" required>
                                <option value="">Select Day</option>
                                <option>Monday</option>
                                <option>Tuesday</option>
                                <option>Wednesday</option>
                                <option>Thursday</option>
                                <option>Friday</option>
                                <option>Saturday</option>
                                <option>Sunday</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>

                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" required>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Notice</label>
                            <textarea name="notice" required></textarea>
                        </div>
                    </div>

                    <button class="btn">Publish Notice</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Zone</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Notice</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($rationing && $rationing->num_rows > 0): ?>
                                <?php while ($r = $rationing->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= clean($r['zone']) ?></td>
                                        <td><?= clean($r['rationing_day']) ?></td>
                                        <td><?= clean($r['start_time']) ?> - <?= clean($r['end_time']) ?></td>
                                        <td><?= clean($r['notice']) ?></td>
                                        <td><span class="badge good"><?= clean($r['status']) ?></span></td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="crm_action" value="update_rationing_status">
                                                <input type="hidden" name="rationing_id" value="<?= (int)$r['id'] ?>">
                                                <select name="status">
                                                    <option <?= $r['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                                    <option <?= $r['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                                <button class="expand-btn">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No rationing notices found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- AUDIT -->

            <div id="audit" class="tab-section">

                <h3 class="section-title">Audit Trail</h3>

                <div class="insight-box">
                    Recent back-office actions on applications, enquiries and complaints.
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Case Type</th>
                                <th>Action</th>
                                <th>Status Change</th>
                                <th>Staff</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($updates && $updates->num_rows > 0): ?>
                                <?php while ($u = $updates->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= clean($u['created_at']) ?></td>
                                        <td><?= clean($u['case_type']) ?></td>
                                        <td><?= clean($u['action_taken']) ?></td>
                                        <td><?= clean($u['old_status']) ?> → <?= clean($u['new_status']) ?></td>
                                        <td><?= clean($u['staff_name']) ?></td>
                                        <td><?= clean($u['notes']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No audit records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

        <!-- RIGHT ALERT PANEL -->

        <div class="panel">

            <h3 class="section-title">Customer Service Alerts</h3>

            <?php if($escalatedComplaints > 0): ?>
                <div class="alert red">
                    <strong><?= number_format($escalatedComplaints) ?> escalated complaint(s).</strong><br>
                    Immediate management attention is recommended.
                </div>
            <?php endif; ?>

            <?php if($openComplaints > 0): ?>
                <div class="alert">
                    <strong><?= number_format($openComplaints) ?> open complaint(s).</strong><br>
                    Assign officers and update resolution progress.
                </div>
            <?php endif; ?>

            <?php if($pendingApps > 0): ?>
                <div class="alert blue">
                    <strong><?= number_format($pendingApps) ?> pending meter application(s).</strong><br>
                    Review applications and update customer responses.
                </div>
            <?php endif; ?>

            <?php if($openEnquiries > 0): ?>
                <div class="alert">
                    <strong><?= number_format($openEnquiries) ?> open enquiry request(s).</strong><br>
                    Respond to customer enquiries within the service timeline.
                </div>
            <?php endif; ?>

            <?php if($activeRationing > 0): ?>
                <div class="alert blue">
                    <strong><?= number_format($activeRationing) ?> active rationing notice(s).</strong><br>
                    Keep affected customers updated through the portal.
                </div>
            <?php endif; ?>

            <?php if($openCases == 0 && $escalatedComplaints == 0): ?>
                <div class="alert green">
                    <strong>No major customer service alerts.</strong><br>
                    Current customer service workload is within acceptable limits.
                </div>
            <?php endif; ?>

            <div class="insight-box">
                <strong>Executive Insight</strong><br><br>
                The system currently has
                <strong><?= number_format($totalCases) ?></strong> customer service case(s),
                <strong><?= number_format($openCases) ?></strong> open or pending case(s), and
                <strong><?= number_format($resolvedCases) ?></strong> resolved or approved case(s).
            </div>

            <div class="insight-box">
                <strong>Recommended Operational Actions</strong><br><br>
                1. Prioritize escalated complaints.<br>
                2. Assign pending meter applications.<br>
                3. Respond to open enquiries.<br>
                4. Keep rationing notices updated.<br>
                5. Use the audit trail to track staff actions.
            </div>

        </div>

    </div>

</div>

<!-- MODALS -->

<div id="applicationModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('applicationModal')">×</button>
        <h3>Manage Meter Application</h3>

        <form method="POST">
            <input type="hidden" name="crm_action" value="update_application">
            <input type="hidden" name="application_id" id="app_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Staff Name</label>
                    <input type="text" name="staff_name" placeholder="Officer name">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="app_status">
                        <option>Pending</option>
                        <option>Under Review</option>
                        <option>More Information Required</option>
                        <option>Approved</option>
                        <option>Assigned</option>
                        <option>Meter Registered</option>
                        <option>Activated</option>
                        <option>Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned Staff</label>
                    <input type="text" name="assigned_staff" id="app_assigned">
                </div>

                <div class="form-group">
                    <label>Meter Serial</label>
                    <input type="text" name="meter_serial" id="app_meter_serial">
                </div>

                <div class="form-group">
                    <label>Installation Date</label>
                    <input type="date" name="installation_date" id="app_installation_date">
                </div>

                <div class="form-group full-width">
                    <label>Response / Notes visible to customer</label>
                    <textarea name="response" id="app_response"></textarea>
                </div>
            </div>

            <button class="btn">Save Application Update</button>
        </form>
    </div>
</div>

<div id="rejectModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('rejectModal')">×</button>
        <h3>Reject Application</h3>

        <form method="POST">
            <input type="hidden" name="crm_action" value="reject_application">
            <input type="hidden" name="application_id" id="reject_app_id">

            <div class="form-group">
                <label>Staff Name</label>
                <input type="text" name="staff_name" placeholder="Officer name">
            </div>

            <div class="form-group">
                <label>Rejection Reason</label>
                <textarea name="rejection_reason" required></textarea>
            </div>

            <button class="btn danger-btn">Reject Application</button>
        </form>
    </div>
</div>

<div id="enquiryModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('enquiryModal')">×</button>
        <h3>Respond to Enquiry</h3>

        <form method="POST">
            <input type="hidden" name="crm_action" value="respond_enquiry">
            <input type="hidden" name="enquiry_id" id="enquiry_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Staff Name</label>
                    <input type="text" name="staff_name" placeholder="Officer name">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="enquiry_status">
                        <option>Submitted</option>
                        <option>Open</option>
                        <option>In Progress</option>
                        <option>Resolved</option>
                        <option>Closed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned Staff</label>
                    <input type="text" name="assigned_staff" id="enquiry_assigned">
                </div>

                <div class="form-group full-width">
                    <label>Customer Message</label>
                    <textarea id="enquiry_message" readonly></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Response visible to customer</label>
                    <textarea name="response" id="enquiry_response" required></textarea>
                </div>
            </div>

            <button class="btn">Save Response</button>
        </form>
    </div>
</div>

<div id="complaintModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('complaintModal')">×</button>
        <h3>Manage Complaint</h3>

        <form method="POST">
            <input type="hidden" name="crm_action" value="update_complaint">
            <input type="hidden" name="complaint_id" id="complaint_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Staff Name</label>
                    <input type="text" name="staff_name" placeholder="Officer name">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="complaint_status">
                        <option>Submitted</option>
                        <option>Assigned</option>
                        <option>In Progress</option>
                        <option>Escalated</option>
                        <option>Resolved</option>
                        <option>Closed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned Staff</label>
                    <input type="text" name="assigned_staff" id="complaint_assigned">
                </div>

                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" id="complaint_priority">
                        <option>Low</option>
                        <option>Medium</option>
                        <option>High</option>
                        <option>Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" id="complaint_due_date">
                </div>

                <div class="form-group full-width">
                    <label>Complaint Description</label>
                    <textarea id="complaint_description" readonly></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Response visible to customer</label>
                    <textarea name="response" id="complaint_response"></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Resolution Notes</label>
                    <textarea name="resolution_notes" id="complaint_resolution"></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Escalation Reason</label>
                    <textarea name="escalation_reason" id="complaint_escalation"></textarea>
                </div>
            </div>

            <button class="btn">Save Complaint Update</button>
        </form>
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

.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
    gap:18px;
}

.kpi{
    background:white;
    border-radius:16px;
    padding:20px;
    border:1px solid #e2e8f0;
    border-left:5px solid #1e7d4f;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.kpi.blue{ border-left-color:#1e3a8a; }
.kpi.yellow{ border-left-color:#eab308; }
.kpi.red{ border-left-color:#dc2626; }

.kpi h3{
    margin:0;
    font-size:30px;
    color:#0f172a;
}

.kpi p{
    margin:8px 0 0;
    font-size:13px;
    color:#64748b;
    font-weight:700;
}

.kpi small{
    display:block;
    margin-top:8px;
    color:#94a3b8;
    font-size:12px;
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

.filters input,
.filters select{
    padding:11px 12px;
    border-radius:10px;
    border:1px solid #dbe2ea;
    font-size:13px;
    min-width:180px;
}

.filters input{
    min-width:300px;
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
}

.btn-light{
    background:#f8fafc;
    color:#334155;
    border:1px solid #dbe2ea;
}

.crm-tabs{
    margin-bottom:0;
}

.tab-btn{
    background:#f8fafc;
    color:#334155;
    border:1px solid #dbe2ea;
}

.tab-btn.active{
    background:#1e7d4f;
    color:white;
    border-color:#1e7d4f;
}

.grid{
    display:grid;
    grid-template-columns:1fr 360px;
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

.tab-section{
    display:none;
}

.tab-section.active{
    display:block;
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

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:7px 10px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:700;
    margin:2px;
}

.expand-btn.danger,
.danger-btn{
    background:#7f1d1d !important;
    color:white !important;
    border-color:#7f1d1d !important;
}

.alert{
    border-left:4px solid #facc15;
    padding:14px;
    border-radius:12px;
    margin-bottom:12px;
    font-size:13px;
    background:#fffbeb;
    color:#713f12;
}

.alert.red{
    background:#fef2f2;
    color:#991b1b;
    border-left-color:#dc2626;
}

.alert.green{
    background:#ecfdf3;
    color:#166534;
    border-left-color:#22c55e;
}

.alert.blue{
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

.form-card{
    margin-top:14px;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:14px;
}

.form-group{
    display:flex;
    flex-direction:column;
    margin-bottom:12px;
}

.form-group label{
    margin-bottom:6px;
    color:#334155;
    font-size:13px;
    font-weight:700;
}

.form-group input,
.form-group select,
.form-group textarea,
.inline-form select{
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

.full-width{
    grid-column:1/-1;
}

.inline-form{
    display:flex;
    gap:6px;
    align-items:center;
}

.link-btn{
    color:#1e3a8a;
    font-weight:800;
    text-decoration:none;
}

small{
    color:#64748b;
}

.modal{
    display:none;
    position:fixed;
    z-index:3000;
    left:0;
    top:0;
    width:100%;
    height:100%;
    background:rgba(15,23,42,0.45);
    padding:30px;
    box-sizing:border-box;
    overflow:auto;
}

.modal-content{
    background:#fff;
    max-width:900px;
    margin:40px auto;
    padding:22px;
    border-radius:16px;
    position:relative;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

.modal-content h3{
    margin-top:0;
    color:#0f172a;
}

.close-btn{
    position:absolute;
    right:14px;
    top:10px;
    border:none;
    background:transparent;
    font-size:25px;
    cursor:pointer;
    color:#334155;
}

@media(max-width:1000px){
    .grid{
        grid-template-columns:1fr;
    }

    .container{
        margin-left:0;
    }

    .filters{
        flex-direction:column;
        align-items:stretch;
    }

    .filters input,
    .filters select,
    .filters button,
    .filters .btn,
    .tab-btn{
        width:100%;
        box-sizing:border-box;
    }

    .form-grid{
        grid-template-columns:1fr;
    }
}
</style>

<script>
function openCrmTab(evt, tabName){
    let sections = document.getElementsByClassName("tab-section");
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

function closeModal(id){
    document.getElementById(id).style.display = "none";
}

function openApplicationModal(data){
    document.getElementById("app_id").value = data.id || "";
    document.getElementById("app_status").value = data.status || "Pending";
    document.getElementById("app_assigned").value = data.assigned_staff || "";
    document.getElementById("app_response").value = data.response || "";
    document.getElementById("app_meter_serial").value = data.meter_serial || "";
    document.getElementById("app_installation_date").value = data.installation_date || "";
    document.getElementById("applicationModal").style.display = "block";
}

function openRejectModal(id){
    document.getElementById("reject_app_id").value = id;
    document.getElementById("rejectModal").style.display = "block";
}

function openEnquiryModal(data){
    document.getElementById("enquiry_id").value = data.id || "";
    document.getElementById("enquiry_status").value = data.status || "Submitted";
    document.getElementById("enquiry_assigned").value = data.assigned_staff || "";
    document.getElementById("enquiry_message").value = data.message || "";
    document.getElementById("enquiry_response").value = data.response || "";
    document.getElementById("enquiryModal").style.display = "block";
}

function openComplaintModal(data){
    document.getElementById("complaint_id").value = data.id || "";
    document.getElementById("complaint_status").value = data.status || "Submitted";
    document.getElementById("complaint_assigned").value = data.assigned_staff || "";
    document.getElementById("complaint_priority").value = data.priority || "Medium";
    document.getElementById("complaint_due_date").value = data.due_date || "";
    document.getElementById("complaint_description").value = data.description || "";
    document.getElementById("complaint_response").value = data.response || "";
    document.getElementById("complaint_resolution").value = data.resolution_notes || "";
    document.getElementById("complaint_escalation").value = data.escalation_reason || "";
    document.getElementById("complaintModal").style.display = "block";
}

window.onclick = function(event){
    let modals = document.getElementsByClassName("modal");
    for(let i = 0; i < modals.length; i++){
        if(event.target === modals[i]){
            modals[i].style.display = "none";
        }
    }
}
</script>