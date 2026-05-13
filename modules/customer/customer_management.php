<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column) {
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

/* ================= ADD ENTERPRISE COLUMNS IF MISSING ================= */

addColumnIfMissing($conn, 'meter_applications', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'meter_applications', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'meter_applications', 'rejection_reason', "TEXT NULL");
addColumnIfMissing($conn, 'meter_applications', 'meter_serial', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'meter_applications', 'installation_date', "DATE NULL");
addColumnIfMissing($conn, 'meter_applications', 'reviewed_at', "DATETIME NULL");
addColumnIfMissing($conn, 'meter_applications', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

addColumnIfMissing($conn, 'customer_enquiries', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'customer_enquiries', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

addColumnIfMissing($conn, 'customer_complaints', 'due_date', "DATE NULL");
addColumnIfMissing($conn, 'customer_complaints', 'resolution_notes', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'escalation_reason', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

/* ================= LOG ACTION ================= */

function logAction($conn, $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes) {
    $stmt = $conn->prepare("
        INSERT INTO customer_case_updates
        (case_type, case_id, action_taken, old_status, new_status, staff_name, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sisssss", $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes);
    $stmt->execute();
}

/* ================= ACTION HANDLING ================= */

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crm_action'])) {

    $action = $_POST['crm_action'];
    $staff = trim($_POST['staff_name'] ?? 'Back Office');

    /* ===== METER APPLICATION ACTIONS ===== */

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
            $message = "<div class='alert success'>Meter application updated successfully.</div>";
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
            $message = "<div class='alert success'>Application rejected successfully.</div>";
        }
    }

    /* ===== ENQUIRY ACTIONS ===== */

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
            $message = "<div class='alert success'>Enquiry updated successfully.</div>";
        }
    }

    /* ===== COMPLAINT ACTIONS ===== */

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
            $message = "<div class='alert success'>Complaint updated successfully.</div>";
        }
    }

    /* ===== WATER RATIONING ACTIONS ===== */

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
            $message = "<div class='alert success'>Water rationing notice published successfully.</div>";
        }
    }

    if ($action === 'update_rationing_status') {
        $id = (int) $_POST['rationing_id'];
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("UPDATE water_rationing_schedule SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Water rationing status updated successfully.</div>";
        }
    }
}

/* ================= FILTERS ================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

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

function countRows($conn, $table, $condition = "1=1") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM $table WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

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

$applications = $conn->query("SELECT * FROM meter_applications $appWhere ORDER BY id DESC");
$enquiries = $conn->query("SELECT * FROM customer_enquiries $enquiryWhere ORDER BY id DESC");
$complaints = $conn->query("SELECT * FROM customer_complaints $complaintWhere ORDER BY id DESC");
$rationing = $conn->query("SELECT * FROM water_rationing_schedule ORDER BY id DESC");
$updates = $conn->query("SELECT * FROM customer_case_updates ORDER BY id DESC LIMIT 30");
?>

<div class="page-content">

    <div class="module-header">
        <h2>Customer Management</h2>
        <p>Back office management for meter applications, enquiries, complaints, assignments, responses and customer notices.</p>
    </div>

    <?= $message ?>

    <div class="summary-grid">
        <div class="summary-card">
            <span>Total Applications</span>
            <strong><?= $totalApps ?></strong>
        </div>
        <div class="summary-card">
            <span>Pending Applications</span>
            <strong><?= $pendingApps ?></strong>
        </div>
        <div class="summary-card">
            <span>Approved Applications</span>
            <strong><?= $approvedApps ?></strong>
        </div>
        <div class="summary-card">
            <span>Rejected Applications</span>
            <strong><?= $rejectedApps ?></strong>
        </div>
        <div class="summary-card">
            <span>Total Enquiries</span>
            <strong><?= $totalEnquiries ?></strong>
        </div>
        <div class="summary-card">
            <span>Open Enquiries</span>
            <strong><?= $openEnquiries ?></strong>
        </div>
        <div class="summary-card">
            <span>Total Complaints</span>
            <strong><?= $totalComplaints ?></strong>
        </div>
        <div class="summary-card">
            <span>Escalated Complaints</span>
            <strong><?= $escalatedComplaints ?></strong>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET">
            <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'customer_relations/customer_management') ?>">

            <input type="text" name="search" placeholder="Search customer, ref, contact, ID, meter..." value="<?= clean($search) ?>">

            <select name="status">
                <option value="">All Statuses</option>
                <?php
                $statuses = ['Pending','Submitted','Under Review','More Information Required','Approved','Rejected','Assigned','In Progress','Escalated','Resolved','Closed','Completed'];
                foreach ($statuses as $s):
                ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filter</button>
            <a href="dashboard.php?page=customer_relations/customer_management" class="clear-btn">Reset</a>
        </form>
    </div>

    <div class="crm-tabs">
        <button class="tab-btn active" onclick="openCrmTab(event,'applications')">Meter Applications</button>
        <button class="tab-btn" onclick="openCrmTab(event,'enquiries')">Enquiries</button>
        <button class="tab-btn" onclick="openCrmTab(event,'complaints')">Complaints</button>
        <button class="tab-btn" onclick="openCrmTab(event,'rationing')">Water Rationing</button>
        <button class="tab-btn" onclick="openCrmTab(event,'audit')">Audit Trail</button>
    </div>

    <!-- ================= METER APPLICATIONS ================= -->

    <div id="applications" class="tab-section active">
        <div class="section-header">
            <h3>Meter Application Approvals</h3>
            <p>Review, approve, reject, assign staff and update application status.</p>
        </div>

        <div class="table-card">
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
                                <small><?= clean($a['created_at']) ?></small>
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
                            <td><span class="status-badge"><?= clean($a['status']) ?></span></td>
                            <td><?= clean($a['assigned_staff'] ?? 'Unassigned') ?></td>
                            <td>
                                <?php if (!empty($a['national_id_copy'])): ?>
                                    <a class="link-btn" href="<?= clean('../../' . $a['national_id_copy']) ?>" target="_blank">View</a>
                                <?php else: ?>
                                    <small>No file</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="small-btn" onclick='openApplicationModal(<?= json_encode($a) ?>)'>Manage</button>
                                <button class="small-btn danger" onclick='openRejectModal(<?= (int)$a["id"] ?>)'>Reject</button>
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

    <!-- ================= ENQUIRIES ================= -->

    <div id="enquiries" class="tab-section">
        <div class="section-header">
            <h3>Enquiry Desk</h3>
            <p>Respond to customer enquiries and close completed enquiries.</p>
        </div>

        <div class="table-card">
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
                                <small><?= clean($e['created_at']) ?></small>
                            </td>
                            <td>
                                <?= clean($e['customer_name']) ?><br>
                                <small><?= clean($e['contact']) ?> | <?= clean($e['email']) ?></small>
                            </td>
                            <td>
                                <?= clean($e['subject']) ?><br>
                                <small><?= clean($e['enquiry_type']) ?></small>
                            </td>
                            <td><span class="status-badge"><?= clean($e['status']) ?></span></td>
                            <td><?= clean($e['assigned_staff'] ?? 'Unassigned') ?></td>
                            <td>
                                <button class="small-btn" onclick='openEnquiryModal(<?= json_encode($e) ?>)'>Respond</button>
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

    <!-- ================= COMPLAINTS ================= -->

    <div id="complaints" class="tab-section">
        <div class="section-header">
            <h3>Complaint Desk</h3>
            <p>Assign complaints, update progress, escalate critical claims and resolve cases.</p>
        </div>

        <div class="table-card">
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
                                <small><?= clean($c['created_at']) ?></small>
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
                            <td><span class="status-badge"><?= clean($c['priority']) ?></span></td>
                            <td><span class="status-badge"><?= clean($c['status']) ?></span></td>
                            <td><?= clean($c['assigned_staff'] ?? 'Unassigned') ?></td>
                            <td>
                                <button class="small-btn" onclick='openComplaintModal(<?= json_encode($c) ?>)'>Manage</button>
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

    <!-- ================= WATER RATIONING ================= -->

    <div id="rationing" class="tab-section">
        <div class="section-header">
            <h3>Water Rationing Notices</h3>
            <p>Create and manage notices visible on the customer portal.</p>
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

            <button class="submit-btn">Publish Notice</button>
        </form>

        <div class="table-card">
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
                                <td><span class="status-badge"><?= clean($r['status']) ?></span></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="crm_action" value="update_rationing_status">
                                        <input type="hidden" name="rationing_id" value="<?= (int)$r['id'] ?>">
                                        <select name="status">
                                            <option <?= $r['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option <?= $r['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                        <button class="small-btn">Save</button>
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

    <!-- ================= AUDIT ================= -->

    <div id="audit" class="tab-section">
        <div class="section-header">
            <h3>Audit Trail</h3>
            <p>Recent back-office actions on applications, enquiries and complaints.</p>
        </div>

        <div class="table-card">
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

<!-- ================= APPLICATION MODAL ================= -->

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

            <button class="submit-btn">Save Application Update</button>
        </form>
    </div>
</div>

<!-- ================= REJECT MODAL ================= -->

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

            <button class="submit-btn danger-btn">Reject Application</button>
        </form>
    </div>
</div>

<!-- ================= ENQUIRY MODAL ================= -->

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

            <button class="submit-btn">Save Response</button>
        </form>
    </div>
</div>

<!-- ================= COMPLAINT MODAL ================= -->

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

            <button class="submit-btn">Save Complaint Update</button>
        </form>
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

.module-header,
.section-header,
.form-card,
.table-card,
.filter-card,
.summary-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
}

.module-header{
    padding:18px 20px;
    margin-bottom:18px;
    border-left:4px solid #0a2a43;
}

.module-header h2{
    margin:0;
    color:#0a2a43;
    font-size:20px;
}

.module-header p,
.section-header p{
    margin:6px 0 0;
    color:#64748b;
    font-size:14px;
}

.summary-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:12px;
    margin-bottom:18px;
}

.summary-card{
    padding:15px;
}

.summary-card span{
    display:block;
    color:#64748b;
    font-size:13px;
    margin-bottom:8px;
}

.summary-card strong{
    color:#0a2a43;
    font-size:22px;
}

.filter-card{
    padding:14px;
    margin-bottom:18px;
}

.filter-card form{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.filter-card input,
.filter-card select,
.filter-card button,
.clear-btn{
    padding:9px 11px;
    border-radius:6px;
    border:1px solid #d1d5db;
    font-size:13px;
}

.filter-card input{
    min-width:260px;
}

.filter-card button,
.clear-btn{
    background:#0a2a43;
    color:#fff;
    text-decoration:none;
    cursor:pointer;
    border:none;
}

.clear-btn{
    background:#64748b;
}

.crm-tabs{
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

.tab-section{
    display:none;
}

.tab-section.active{
    display:block;
}

.section-header{
    padding:16px 18px;
    margin-bottom:14px;
}

.section-header h3{
    margin:0;
    color:#0a2a43;
    font-size:18px;
}

.form-card,
.table-card{
    padding:18px;
    margin-bottom:18px;
    overflow-x:auto;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:15px;
}

.form-group{
    display:flex;
    flex-direction:column;
    margin-bottom:12px;
}

.form-group label{
    font-size:13px;
    color:#334155;
    font-weight:600;
    margin-bottom:6px;
}

.form-group input,
.form-group select,
.form-group textarea,
.inline-form select{
    padding:9px;
    border:1px solid #d1d5db;
    border-radius:6px;
    font-size:14px;
}

.form-group textarea{
    min-height:90px;
    resize:vertical;
}

.full-width{
    grid-column:1/-1;
}

.submit-btn,
.small-btn{
    background:#0a2a43;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.submit-btn{
    padding:9px 15px;
    font-size:14px;
    margin-top:10px;
}

.small-btn{
    padding:6px 10px;
    font-size:12px;
    margin:2px;
}

.danger,
.danger-btn{
    background:#7f1d1d !important;
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
    vertical-align:top;
}

small{
    color:#64748b;
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

.link-btn{
    color:#0a2a43;
    font-weight:600;
    text-decoration:none;
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

.inline-form{
    display:flex;
    gap:6px;
    align-items:center;
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
    border-radius:10px;
    position:relative;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

.modal-content h3{
    margin-top:0;
    color:#0a2a43;
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