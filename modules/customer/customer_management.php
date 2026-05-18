<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function nullableDate($value) {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

function publicFileUrl($path) {
    $path = trim((string)($path ?? ''));

    if ($path === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    return $path;
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

addColumnIfMissing($conn, 'water_rationing_schedule', 'source', "VARCHAR(150) NULL");
addColumnIfMissing($conn, 'water_rationing_schedule', 'notice_type', "VARCHAR(100) NULL DEFAULT 'Rationing'");
addColumnIfMissing($conn, 'water_rationing_schedule', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

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
        $installation_date = nullableDate($_POST['installation_date']);

        $old = $conn->query("SELECT status FROM meter_applications WHERE id=$id")->fetch_assoc();
        $oldStatus = $old['status'] ?? '';

        $stmt = $conn->prepare("
            UPDATE meter_applications
            SET status=?, assigned_staff=?, response=?, meter_serial=?, installation_date=?, reviewed_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("sssssi", $status, $assigned, $response, $meter_serial, $installation_date, $id);

        if ($stmt && $stmt->execute()) {
            logAction($conn, 'Meter Application', $id, 'Application Updated', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Meter application updated successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Update failed.</strong><br>Meter application could not be updated.</div>";
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

        if ($stmt && $stmt->execute()) {
            logAction($conn, 'Meter Application', $id, 'Application Rejected', $oldStatus, 'Rejected', $staff, $reason);
            $message = "<div class='alert red'><strong>Application Rejected.</strong><br>The application has been rejected successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Rejection failed.</strong><br>The application could not be rejected.</div>";
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

        if ($stmt && $stmt->execute()) {
            logAction($conn, 'Enquiry', $id, 'Enquiry Responded', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Enquiry updated successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Update failed.</strong><br>Enquiry could not be updated.</div>";
        }
    }

    if ($action === 'update_complaint') {
        $id = (int) $_POST['complaint_id'];
        $status = trim($_POST['status']);
        $assigned = trim($_POST['assigned_staff']);
        $priority = trim($_POST['priority']);
        $due_date = nullableDate($_POST['due_date']);
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

        if ($stmt && $stmt->execute()) {
            logAction($conn, 'Complaint', $id, 'Complaint Updated', $oldStatus, $status, $staff, $response);
            $message = "<div class='alert green'><strong>Success.</strong><br>Complaint updated successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Update failed.</strong><br>Complaint could not be updated.</div>";
        }
    }

    if ($action === 'save_rationing') {
        $zone = trim($_POST['zone']);
        $day = trim($_POST['rationing_day']);
        $start = trim($_POST['start_time']);
        $end = trim($_POST['end_time']);
        $source = trim($_POST['source']);
        $noticeType = trim($_POST['notice_type']);
        $notice = trim($_POST['notice']);
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("
            INSERT INTO water_rationing_schedule
            (zone, rationing_day, start_time, end_time, source, notice_type, notice, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $zone, $day, $start, $end, $source, $noticeType, $notice, $status);

        if ($stmt && $stmt->execute()) {
            $message = "<div class='alert green'><strong>Published.</strong><br>Water rationing notice published successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Publish failed.</strong><br>Water rationing notice could not be published.</div>";
        }
    }

    if ($action === 'update_rationing_details') {
        $id = (int) $_POST['rationing_id'];
        $zone = trim($_POST['zone']);
        $day = trim($_POST['rationing_day']);
        $start = trim($_POST['start_time']);
        $end = trim($_POST['end_time']);
        $source = trim($_POST['source']);
        $noticeType = trim($_POST['notice_type']);
        $notice = trim($_POST['notice']);
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("
            UPDATE water_rationing_schedule
            SET zone=?, rationing_day=?, start_time=?, end_time=?, source=?, notice_type=?, notice=?, status=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssssi", $zone, $day, $start, $end, $source, $noticeType, $notice, $status, $id);

        if ($stmt && $stmt->execute()) {
            $message = "<div class='alert green'><strong>Updated.</strong><br>Water rationing notice updated successfully.</div>";
        } else {
            $message = "<div class='alert red'><strong>Update failed.</strong><br>Water rationing notice could not be updated.</div>";
        }
    }
}

/* ================= FILTERS ================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$currentPage = trim($_GET['page'] ?? 'modules/customer/customer_management.php');

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

function whereToCondition($where) {
    return trim(preg_replace('/^\s*WHERE\s+/i', '', $where));
}

/* ================= DATA CONDITIONS ================= */

$appWhere = buildWhere($conn, $search, $statusFilter, [
    'application_ref', 'customer_name', 'contact', 'id_number', 'zone', 'meter_type', 'customer_type'
]);

$enquiryWhere = buildWhere($conn, $search, $statusFilter, [
    'enquiry_ref', 'customer_name', 'contact', 'email', 'enquiry_type', 'subject'
]);

$complaintWhere = buildWhere($conn, $search, $statusFilter, [
    'complaint_ref', 'customer_name', 'contact', 'meter_serial', 'zone', 'complaint_type', 'priority'
]);

$appCondition = whereToCondition($appWhere);
$enquiryCondition = whereToCondition($enquiryWhere);
$complaintCondition = whereToCondition($complaintWhere);

/* ================= COUNTS ================= */

$totalApps = countRows($conn, 'meter_applications', $appCondition);
$pendingApps = countRows($conn, 'meter_applications', "($appCondition) AND status IN ('Pending','Submitted','Under Review')");
$approvedApps = countRows($conn, 'meter_applications', "($appCondition) AND status='Approved'");
$rejectedApps = countRows($conn, 'meter_applications', "($appCondition) AND status='Rejected'");

$totalEnquiries = countRows($conn, 'customer_enquiries', $enquiryCondition);
$openEnquiries = countRows($conn, 'customer_enquiries', "($enquiryCondition) AND status IN ('Submitted','Open','In Progress')");
$closedEnquiries = countRows($conn, 'customer_enquiries', "($enquiryCondition) AND status IN ('Closed','Resolved')");

$totalComplaints = countRows($conn, 'customer_complaints', $complaintCondition);
$openComplaints = countRows($conn, 'customer_complaints', "($complaintCondition) AND status IN ('Submitted','Assigned','In Progress','Escalated')");
$resolvedComplaints = countRows($conn, 'customer_complaints', "($complaintCondition) AND status IN ('Resolved','Closed')");
$escalatedComplaints = countRows($conn, 'customer_complaints', "($complaintCondition) AND status='Escalated'");

$activeRationing = countRows($conn, 'water_rationing_schedule', "status='Active'");

$totalCases = $totalApps + $totalEnquiries + $totalComplaints;
$openCases = $pendingApps + $openEnquiries + $openComplaints;
$resolvedCases = $approvedApps + $closedEnquiries + $resolvedComplaints;
$riskRate = $totalCases > 0 ? round((($openComplaints + $escalatedComplaints) / $totalCases) * 100) : 0;

/* ================= DATA ================= */

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

    <div id="toastHost" class="toast-host" aria-live="polite" aria-atomic="true"></div>

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

        <input type="hidden" name="page" value="<?= clean($currentPage) ?>">

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

        <a href="dashboard.php?page=<?= clean($currentPage) ?>" class="btn btn-light">
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

                <div class="case-list">
                    <?php if ($applications && $applications->num_rows > 0): ?>
                        <?php while ($a = $applications->fetch_assoc()): ?>
                            <div class="case-card">
                                <div class="case-card-head">
                                    <div>
                                        <h4><?= clean($a['application_ref']) ?></h4>
                                        <p><?= clean($a['created_at'] ?? '') ?></p>
                                    </div>

                                    <span class="badge warning"><?= clean($a['status'] ?? 'Pending') ?></span>
                                </div>

                                <div class="case-summary">
                                    <div>
                                        <span>Customer</span>
                                        <strong><?= clean($a['customer_name']) ?></strong>
                                        <small>ID: <?= clean($a['id_number']) ?> | <?= clean($a['contact']) ?></small>
                                    </div>

                                    <div>
                                        <span>Meter</span>
                                        <strong><?= clean($a['meter_type']) ?></strong>
                                        <small><?= clean($a['customer_type']) ?> | Serial: <?= clean($a['meter_serial'] ?? 'N/A') ?></small>
                                    </div>

                                    <div>
                                        <span>Zone</span>
                                        <strong><?= clean($a['zone']) ?></strong>
                                        <small>Staff: <?= clean($a['assigned_staff'] ?? 'Unassigned') ?></small>
                                    </div>
                                </div>

                                <div class="case-actions">
                                    <?php if (!empty($a['national_id_copy'])): ?>
                                        <a class="link-btn" href="<?= clean(publicFileUrl($a['national_id_copy'])) ?>" target="_blank" rel="noopener" onclick="notify('ID copy', 'Opening uploaded ID document.', 'blue')">View ID Copy</a>
                                    <?php else: ?>
                                        <span class="muted-note">No ID copy</span>
                                    <?php endif; ?>

                                    <button type="button" class="expand-btn" onclick='openApplicationModal(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>Manage</button>
                                    <button type="button" class="expand-btn danger" onclick='openRejectModal(<?= (int)$a["id"] ?>)'>Reject</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No meter applications found.</div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ENQUIRIES -->

            <div id="enquiries" class="tab-section">

                <h3 class="section-title">Enquiry Desk</h3>

                <div class="insight-box">
                    Respond to customer enquiries, assign responsible officers and close completed enquiries.
                </div>

                <div class="case-list">
                    <?php if ($enquiries && $enquiries->num_rows > 0): ?>
                        <?php while ($e = $enquiries->fetch_assoc()): ?>
                            <div class="case-card">
                                <div class="case-card-head">
                                    <div>
                                        <h4><?= clean($e['enquiry_ref']) ?></h4>
                                        <p><?= clean($e['created_at'] ?? '') ?></p>
                                    </div>

                                    <span class="badge good"><?= clean($e['status'] ?? 'Submitted') ?></span>
                                </div>

                                <div class="case-summary">
                                    <div>
                                        <span>Customer</span>
                                        <strong><?= clean($e['customer_name']) ?></strong>
                                        <small><?= clean($e['contact']) ?> | <?= clean($e['email']) ?></small>
                                    </div>

                                    <div>
                                        <span>Subject</span>
                                        <strong><?= clean($e['subject']) ?></strong>
                                        <small><?= clean($e['enquiry_type']) ?></small>
                                    </div>

                                    <div>
                                        <span>Assigned Staff</span>
                                        <strong><?= clean($e['assigned_staff'] ?? 'Unassigned') ?></strong>
                                    </div>
                                </div>

                                <div class="case-actions">
                                    <button type="button" class="expand-btn" onclick='openEnquiryModal(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>Respond</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No enquiries found.</div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- COMPLAINTS -->

            <div id="complaints" class="tab-section">

                <h3 class="section-title">Complaint Desk</h3>

                <div class="insight-box">
                    Assign complaints, update progress, escalate urgent issues and record final resolution notes.
                </div>

                <div class="case-list">
                    <?php if ($complaints && $complaints->num_rows > 0): ?>
                        <?php while ($c = $complaints->fetch_assoc()): ?>
                            <div class="case-card">
                                <div class="case-card-head">
                                    <div>
                                        <h4><?= clean($c['complaint_ref']) ?></h4>
                                        <p><?= clean($c['created_at'] ?? '') ?></p>
                                    </div>

                                    <div class="badge-pair">
                                        <span class="badge warning"><?= clean($c['priority']) ?></span>
                                        <span class="badge critical"><?= clean($c['status'] ?? 'Submitted') ?></span>
                                    </div>
                                </div>

                                <div class="case-summary">
                                    <div>
                                        <span>Customer</span>
                                        <strong><?= clean($c['customer_name']) ?></strong>
                                        <small><?= clean($c['contact']) ?> | Meter: <?= clean($c['meter_serial']) ?></small>
                                    </div>

                                    <div>
                                        <span>Issue</span>
                                        <strong><?= clean($c['complaint_type']) ?></strong>
                                        <small><?= clean($c['zone']) ?></small>
                                    </div>

                                    <div>
                                        <span>Assigned Staff</span>
                                        <strong><?= clean($c['assigned_staff'] ?? 'Unassigned') ?></strong>
                                    </div>
                                </div>

                                <div class="case-actions">
                                    <button type="button" class="expand-btn" onclick='openComplaintModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>Manage</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No complaints found.</div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RATIONING -->

            <div id="rationing" class="tab-section">

                <h3 class="section-title">Water Rationing Notices</h3>

                <div class="insight-box">
                    Create and manage water rationing notices visible to customers through the customer portal.
                </div>

                <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>" class="form-card">
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
                            <label>Source</label>
                            <input type="text" name="source" placeholder="Water source, line, plant or area">
                        </div>

                        <div class="form-group">
                            <label>Notice Type</label>
                            <select name="notice_type">
                                <option>Rationing</option>
                                <option>Scheduled Maintenance</option>
                                <option>Hours Change</option>
                                <option>Source Change</option>
                                <option>Emergency Interruption</option>
                            </select>
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

                    <button type="submit" class="btn">Publish Notice</button>
                </form>

                <div class="rationing-list">
                    <?php if ($rationing && $rationing->num_rows > 0): ?>
                        <?php while ($r = $rationing->fetch_assoc()): ?>
                            <div class="rationing-card">
                                <div class="rationing-card-head">
                                    <div>
                                        <h4><?= clean($r['zone']) ?></h4>
                                        <p><?= clean($r['notice_type'] ?? 'Rationing') ?></p>
                                    </div>

                                    <span class="badge <?= ($r['status'] ?? '') === 'Active' ? 'good' : 'warning' ?>">
                                        <?= clean($r['status']) ?>
                                    </span>
                                </div>

                                <div class="rationing-details">
                                    <div>
                                        <span>Day</span>
                                        <strong><?= clean($r['rationing_day']) ?></strong>
                                    </div>

                                    <div>
                                        <span>Time</span>
                                        <strong><?= clean($r['start_time']) ?> - <?= clean($r['end_time']) ?></strong>
                                    </div>

                                    <div>
                                        <span>Source</span>
                                        <strong><?= clean($r['source'] ?? 'N/A') ?></strong>
                                    </div>
                                </div>

                                <div class="rationing-notice">
                                    <?= clean($r['notice']) ?>
                                </div>

                                <div class="rationing-actions">
                                    <button
                                        type="button"
                                        class="expand-btn"
                                        onclick='openRationingModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>
                                        Edit Details
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No rationing notices found.</div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- AUDIT -->

            <div id="audit" class="tab-section">

                <h3 class="section-title">Audit Trail</h3>

                <div class="insight-box">
                    Recent back-office actions on applications, enquiries and complaints.
                </div>

                <div class="audit-list">
                    <?php if ($updates && $updates->num_rows > 0): ?>
                        <?php while ($u = $updates->fetch_assoc()): ?>
                            <div class="audit-card">
                                <div class="audit-topline">
                                    <strong><?= clean($u['case_type']) ?></strong>
                                    <span><?= clean($u['created_at']) ?></span>
                                </div>

                                <div class="audit-action"><?= clean($u['action_taken']) ?></div>

                                <div class="audit-meta">
                                    <span><?= clean($u['old_status']) ?> &rarr; <?= clean($u['new_status']) ?></span>
                                    <span><?= clean($u['staff_name']) ?></span>
                                </div>

                                <p><?= clean($u['notes']) ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No audit records found.</div>
                    <?php endif; ?>
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

        <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>">
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

            <button type="submit" class="btn">Save Application Update</button>
        </form>
    </div>
</div>

<div id="rejectModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('rejectModal')">×</button>
        <h3>Reject Application</h3>

        <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>">
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

            <button type="submit" class="btn danger-btn">Reject Application</button>
        </form>
    </div>
</div>

<div id="enquiryModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('enquiryModal')">×</button>
        <h3>Respond to Enquiry</h3>

        <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>">
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

            <button type="submit" class="btn">Save Response</button>
        </form>
    </div>
</div>

<div id="complaintModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('complaintModal')">×</button>
        <h3>Manage Complaint</h3>

        <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>">
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

            <button type="submit" class="btn">Save Complaint Update</button>
        </form>
    </div>
</div>

<div id="rationingModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('rationingModal')">×</button>
        <h3>Edit Rationing Notice</h3>

        <form method="POST" action="dashboard.php?page=<?= clean($currentPage) ?>">
            <input type="hidden" name="crm_action" value="update_rationing_details">
            <input type="hidden" name="rationing_id" id="rationing_id">

            <div class="form-grid">
                <div class="form-group">
                    <label>Zone</label>
                    <input type="text" name="zone" id="rationing_zone" required>
                </div>

                <div class="form-group">
                    <label>Rationing Day</label>
                    <select name="rationing_day" id="rationing_day" required>
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
                    <input type="time" name="start_time" id="rationing_start" required>
                </div>

                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" id="rationing_end" required>
                </div>

                <div class="form-group">
                    <label>Source</label>
                    <input type="text" name="source" id="rationing_source" placeholder="Water source, line, plant or area">
                </div>

                <div class="form-group">
                    <label>Notice Type</label>
                    <select name="notice_type" id="rationing_notice_type">
                        <option>Rationing</option>
                        <option>Scheduled Maintenance</option>
                        <option>Hours Change</option>
                        <option>Source Change</option>
                        <option>Emergency Interruption</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="rationing_status">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Notice</label>
                    <textarea name="notice" id="rationing_notice" required></textarea>
                </div>
            </div>

            <button type="submit" class="btn">Save Changes</button>
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
    grid-template-columns:minmax(0,1fr) 340px;
    gap:20px;
    margin-top:24px;
}

.panel{
    min-width:0;
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
    width:100%;
    overflow-x:auto;
    margin-top:14px;
}

.case-list,
.audit-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:14px;
    margin-top:16px;
}

.case-card,
.audit-card{
    border:1px solid #e2e8f0;
    border-radius:14px;
    background:#fff;
    padding:16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
    min-width:0;
}

.case-card-head,
.audit-topline{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding-bottom:12px;
    border-bottom:1px solid #edf2f7;
}

.case-card-head h4,
.audit-topline strong{
    margin:0;
    color:#0f172a;
    font-size:16px;
    line-height:1.25;
    overflow-wrap:anywhere;
}

.case-card-head p,
.audit-topline span{
    margin:4px 0 0;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}

.case-summary{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin-top:12px;
}

.case-summary div{
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:10px;
    min-width:0;
}

.case-summary span{
    display:block;
    color:#64748b;
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    margin-bottom:4px;
}

.case-summary strong{
    display:block;
    color:#1e293b;
    font-size:13px;
    overflow-wrap:anywhere;
}

.case-summary small{
    display:block;
    margin-top:5px;
    overflow-wrap:anywhere;
}

.case-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:8px;
    margin-top:12px;
}

.badge-pair{
    display:flex;
    align-items:flex-start;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:6px;
}

.muted-note{
    color:#64748b;
    font-size:12px;
    font-weight:700;
}

.audit-action{
    margin-top:12px;
    font-size:14px;
    font-weight:800;
    color:#1e293b;
}

.audit-meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:10px;
}

.audit-meta span{
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:999px;
    padding:6px 10px;
    color:#475569;
    font-size:12px;
    font-weight:700;
}

.audit-card p{
    margin:12px 0 0;
    color:#475569;
    font-size:13px;
    line-height:1.6;
    overflow-wrap:anywhere;
}

.rationing-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:14px;
    margin-top:16px;
}

.rationing-card{
    border:1px solid #e2e8f0;
    border-radius:14px;
    background:#fff;
    padding:16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.rationing-card-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding-bottom:12px;
    border-bottom:1px solid #edf2f7;
}

.rationing-card-head h4{
    margin:0;
    color:#0f172a;
    font-size:16px;
    line-height:1.25;
}

.rationing-card-head p{
    margin:4px 0 0;
    color:#64748b;
    font-size:12px;
    font-weight:700;
}

.rationing-details{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin-top:12px;
}

.rationing-details div{
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:10px;
    min-width:0;
}

.rationing-details span{
    display:block;
    color:#64748b;
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    margin-bottom:4px;
}

.rationing-details strong{
    display:block;
    color:#1e293b;
    font-size:13px;
    overflow-wrap:anywhere;
}

.rationing-notice{
    margin-top:12px;
    color:#475569;
    font-size:13px;
    line-height:1.6;
    background:#fbfdff;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:12px;
}

.rationing-actions{
    display:flex;
    justify-content:flex-end;
    margin-top:12px;
}

.empty-state{
    border:1px dashed #cbd5e1;
    border-radius:12px;
    padding:16px;
    color:#64748b;
    background:#f8fafc;
    font-size:13px;
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

.toast-host{
    position:fixed;
    top:92px;
    right:22px;
    z-index:5000;
    display:grid;
    gap:10px;
    width:min(360px, calc(100vw - 28px));
    pointer-events:none;
}

.toast{
    background:#fff;
    border:1px solid #e2e8f0;
    border-left:4px solid #1e7d4f;
    border-radius:12px;
    box-shadow:0 14px 32px rgba(15,23,42,0.16);
    padding:13px 14px;
    color:#1e293b;
    transform:translateX(0);
    opacity:1;
    transition:opacity .22s ease, transform .22s ease;
    pointer-events:auto;
}

.toast strong{
    display:block;
    margin-bottom:4px;
    font-size:13px;
    color:#0f172a;
}

.toast span{
    display:block;
    font-size:12px;
    color:#475569;
    line-height:1.45;
}

.toast.green{
    border-left-color:#22c55e;
}

.toast.red{
    border-left-color:#dc2626;
}

.toast.blue{
    border-left-color:#1e3a8a;
}

.toast.leaving{
    opacity:0;
    transform:translateX(18px);
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

@media(max-width:1200px){
    .grid{
        grid-template-columns:1fr;
    }
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

    .case-list,
    .audit-list,
    .rationing-list{
        grid-template-columns:1fr;
    }

    .case-card-head,
    .audit-topline{
        flex-direction:column;
    }

    .case-summary{
        grid-template-columns:1fr;
    }

    .case-actions{
        justify-content:stretch;
    }

    .case-actions .expand-btn,
    .case-actions .link-btn,
    .case-actions .muted-note{
        width:100%;
        text-align:center;
        box-sizing:border-box;
    }

    .rationing-details{
        grid-template-columns:1fr;
    }

    .rationing-card-head,
    .rationing-actions{
        align-items:stretch;
    }

    .rationing-actions .expand-btn{
        width:100%;
    }

    .toast-host{
        top:80px;
        right:14px;
        left:14px;
        width:auto;
    }
}
</style>

<script>
function escapeText(value){
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function notify(title, message, type = 'green'){
    const host = document.getElementById('toastHost');

    if(!host){
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<strong>' + escapeText(title) + '</strong><span>' + escapeText(message) + '</span>';

    host.appendChild(toast);

    setTimeout(function(){
        toast.classList.add('leaving');

        setTimeout(function(){
            toast.remove();
        }, 260);
    }, 4600);
}

document.addEventListener('DOMContentLoaded', function(){
    const pageAlert = document.querySelector('.container > .alert');

    if(pageAlert){
        const isError = pageAlert.classList.contains('red');
        const isSuccess = pageAlert.classList.contains('green');
        const type = isError ? 'red' : (isSuccess ? 'green' : 'blue');
        const title = isError ? 'Action failed' : 'Action completed';

        notify(title, pageAlert.innerText.trim(), type);
    }

    document.querySelectorAll('form[method="POST"]').forEach(function(form){
        form.addEventListener('submit', function(){
            const actionInput = form.querySelector('input[name="crm_action"]');
            const action = actionInput ? actionInput.value : '';
            const labels = {
                update_application: 'Saving meter application changes...',
                reject_application: 'Submitting application rejection...',
                respond_enquiry: 'Saving enquiry response...',
                update_complaint: 'Saving complaint update...',
                save_rationing: 'Publishing water rationing notice...',
                update_rationing_details: 'Saving water rationing changes...'
            };

            notify('Please wait', labels[action] || 'Saving changes...', 'blue');
        });
    });
});

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
    notify('Meter application', 'Application details opened for editing.', 'blue');
}

function openRejectModal(id){
    document.getElementById("reject_app_id").value = id;
    document.getElementById("rejectModal").style.display = "block";
    notify('Reject application', 'Add the rejection reason, then submit.', 'red');
}

function openEnquiryModal(data){
    document.getElementById("enquiry_id").value = data.id || "";
    document.getElementById("enquiry_status").value = data.status || "Submitted";
    document.getElementById("enquiry_assigned").value = data.assigned_staff || "";
    document.getElementById("enquiry_message").value = data.message || data.description || data.enquiry_message || data.subject || "";
    document.getElementById("enquiry_response").value = data.response || "";
    document.getElementById("enquiryModal").style.display = "block";
    notify('Customer enquiry', 'Enquiry loaded for response.', 'blue');
}

function openComplaintModal(data){
    document.getElementById("complaint_id").value = data.id || "";
    document.getElementById("complaint_status").value = data.status || "Submitted";
    document.getElementById("complaint_assigned").value = data.assigned_staff || "";
    document.getElementById("complaint_priority").value = data.priority || "Medium";
    document.getElementById("complaint_due_date").value = data.due_date || "";
    document.getElementById("complaint_description").value = data.description || data.complaint_description || data.message || data.complaint_type || "";
    document.getElementById("complaint_response").value = data.response || "";
    document.getElementById("complaint_resolution").value = data.resolution_notes || "";
    document.getElementById("complaint_escalation").value = data.escalation_reason || "";
    document.getElementById("complaintModal").style.display = "block";
    notify('Customer complaint', 'Complaint loaded for update.', 'blue');
}

function openRationingModal(data){
    document.getElementById("rationing_id").value = data.id || "";
    document.getElementById("rationing_zone").value = data.zone || "";
    document.getElementById("rationing_day").value = data.rationing_day || "Monday";
    document.getElementById("rationing_start").value = data.start_time || "";
    document.getElementById("rationing_end").value = data.end_time || "";
    document.getElementById("rationing_source").value = data.source || "";
    document.getElementById("rationing_notice_type").value = data.notice_type || "Rationing";
    document.getElementById("rationing_notice").value = data.notice || "";
    document.getElementById("rationing_status").value = data.status || "Active";
    document.getElementById("rationingModal").style.display = "block";
    notify('Water rationing', 'Schedule details opened for editing.', 'blue');
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
