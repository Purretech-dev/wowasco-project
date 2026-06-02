<?php
require_once __DIR__ . '/../../api/db.php';

function clean($value){
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table){
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column){
    if(!tableExists($conn, $table)){
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    return $res && $res->num_rows > 0;
}

function addColumnIfMissing($conn, $table, $column, $definition){
    if(tableExists($conn, $table) && !columnExists($conn, $table, $column)){
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

function ensureDeactivationRequestsTable($conn){
    $conn->query("
        CREATE TABLE IF NOT EXISTS deactivation_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_type VARCHAR(30) NOT NULL,
            item_id INT NOT NULL,
            item_label VARCHAR(255) NULL,
            requested_by INT NULL,
            requester_name VARCHAR(150) NULL,
            request_reason TEXT NOT NULL,
            request_notes TEXT NULL,
            attachment_path VARCHAR(255) NULL,
            checker_id INT NULL,
            checker_name VARCHAR(150) NULL,
            checker_decision VARCHAR(50) NULL,
            checker_notes TEXT NULL,
            checked_at DATETIME NULL,
            approver_id INT NULL,
            approver_name VARCHAR(150) NULL,
            approver_decision VARCHAR(50) NULL,
            approver_notes TEXT NULL,
            approved_at DATETIME NULL,
            final_status VARCHAR(80) DEFAULT 'Pending Check',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(item_type),
            INDEX(item_id),
            INDEX(final_status)
        )
    ");
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

ensureDeactivationRequestsTable($conn);
ensureCustomerCaseUpdatesTable($conn);

addColumnIfMissing($conn, 'customer_enquiries', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'customer_enquiries', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'customer_enquiries', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

addColumnIfMissing($conn, 'customer_complaints', 'assigned_staff', "VARCHAR(100) NULL");
addColumnIfMissing($conn, 'customer_complaints', 'response', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'resolution_notes', "TEXT NULL");
addColumnIfMissing($conn, 'customer_complaints', 'evidence_file', "VARCHAR(255) NULL");
addColumnIfMissing($conn, 'customer_complaints', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

function logCustomerCaseAction($conn, $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes){
    if(!tableExists($conn, 'customer_case_updates')){
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO customer_case_updates
        (case_type, case_id, action_taken, old_status, new_status, staff_name, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if($stmt){
        $stmt->bind_param("sisssss", $caseType, $caseId, $action, $oldStatus, $newStatus, $staff, $notes);
        $stmt->execute();
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checker_action'])){
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = trim($_POST['checker_action']);
    $notes = trim($_POST['checker_notes'] ?? '');
    $checkerId = (int)($_SESSION['user_id'] ?? 0);
    $checkerName = $_SESSION['name'] ?? 'Checking Officer';

    $decision = 'Returned';
    $status = 'Returned by Checker';

    if($action === 'verify'){
        $decision = 'Verified';
        $status = 'Pending MD Approval';
    }

    if($action === 'reject'){
        $decision = 'Rejected';
        $status = 'Rejected by Checker';
    }

    $stmt = $conn->prepare("
        UPDATE deactivation_requests
        SET
            checker_id = ?,
            checker_name = ?,
            checker_decision = ?,
            checker_notes = ?,
            checked_at = NOW(),
            final_status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("issssi", $checkerId, $checkerName, $decision, $notes, $status, $requestId);
    $stmt->execute();

    echo "
    <script>
    alert('Deactivation request updated successfully.');
    window.location.href = window.location.pathname + window.location.search;
    </script>
    ";
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_case_action'])){
    $caseType = trim($_POST['case_type'] ?? '');
    $caseId = (int)($_POST['case_id'] ?? 0);
    $status = trim($_POST['case_status'] ?? 'In Progress');
    $assignedStaff = trim($_POST['assigned_staff'] ?? '');
    $response = trim($_POST['case_response'] ?? '');
    $checkerName = $_SESSION['name'] ?? 'Checking Officer';

    if($status === 'Escalated'){
        $status = 'Escalated to MD';
    }

    $actionLabel = $status === 'Escalated to MD'
        ? 'Checker Escalated Case to MD'
        : 'Checker Updated Case';

    if($assignedStaff === ''){
        $assignedStaff = $checkerName;
    }

    if($caseType === 'enquiry' && $caseId > 0 && tableExists($conn, 'customer_enquiries')){
        $oldStatus = '';
        $old = $conn->query("SELECT status FROM customer_enquiries WHERE id = $caseId");

        if($old && $old->num_rows > 0){
            $oldStatus = $old->fetch_assoc()['status'] ?? '';
        }

        $stmt = $conn->prepare("
            UPDATE customer_enquiries
            SET status = ?, assigned_staff = ?, response = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $status, $assignedStaff, $response, $caseId);
        $stmt->execute();

        logCustomerCaseAction($conn, 'Enquiry', $caseId, $actionLabel, $oldStatus, $status, $assignedStaff, $response);

        echo "
        <script>
        alert('Customer enquiry updated successfully.');
        window.location.href = window.location.pathname + window.location.search;
        </script>
        ";
        exit;
    }

    if($caseType === 'complaint' && $caseId > 0 && tableExists($conn, 'customer_complaints')){
        $oldStatus = '';
        $old = $conn->query("SELECT status FROM customer_complaints WHERE id = $caseId");

        if($old && $old->num_rows > 0){
            $oldStatus = $old->fetch_assoc()['status'] ?? '';
        }

        $stmt = $conn->prepare("
            UPDATE customer_complaints
            SET status = ?, assigned_staff = ?, response = ?, resolution_notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $status, $assignedStaff, $response, $response, $caseId);
        $stmt->execute();

        logCustomerCaseAction($conn, 'Complaint', $caseId, $actionLabel, $oldStatus, $status, $assignedStaff, $response);

        echo "
        <script>
        alert('Customer complaint updated successfully.');
        window.location.href = window.location.pathname + window.location.search;
        </script>
        ";
        exit;
    }
}

$requests = $conn->query("
    SELECT *
    FROM deactivation_requests
    WHERE final_status IN ('Pending Check','Returned by Checker')
    ORDER BY created_at DESC
");

$unresolvedEnquiries = tableExists($conn, 'customer_enquiries')
    ? $conn->query("
        SELECT
            id,
            enquiry_ref AS case_ref,
            customer_name,
            contact,
            email,
            enquiry_type AS case_category,
            subject,
            message,
            status,
            assigned_staff,
            response,
            created_at
        FROM customer_enquiries
        WHERE COALESCE(status, 'Submitted') NOT IN ('Resolved','Closed','Completed','Escalated to MD')
        AND (
            COALESCE(status, 'Submitted') IN ('Escalated','Returned to Checker')
            OR created_at <= DATE_SUB(NOW(), INTERVAL 12 HOUR)
        )
        ORDER BY created_at DESC, id DESC
    ")
    : false;

$unresolvedComplaints = tableExists($conn, 'customer_complaints')
    ? $conn->query("
        SELECT
            id,
            complaint_ref AS case_ref,
            customer_name,
            contact,
            meter_serial,
            zone,
            complaint_type AS case_category,
            priority,
            description AS message,
            status,
            assigned_staff,
            response,
            evidence_file,
            created_at
        FROM customer_complaints
        WHERE COALESCE(status, 'Submitted') NOT IN ('Resolved','Closed','Completed','Escalated to MD')
        AND (
            COALESCE(status, 'Submitted') IN ('Escalated','Returned to Checker')
            OR created_at <= DATE_SUB(NOW(), INTERVAL 12 HOUR)
        )
        ORDER BY created_at DESC, id DESC
    ")
    : false;
?>

<div class="page-content checker-page">
    <div class="page-header">
        <div>
            <h2>Checker Workbench</h2>
            <p>Verify deactivation requests and handle unresolved customer enquiries and complaints from one queue.</p>
        </div>
    </div>

    <div class="checker-tabs">
        <button type="button" class="checker-tab active" data-section="deactivationSection">
            Deactivation Requests
        </button>

        <button type="button" class="checker-tab" data-section="customerCasesSection">
            Forwarded Customer Cases
        </button>
    </div>

    <div id="deactivationSection" class="checker-section active">
        <div class="section-heading">
            <h3>Deactivation Requests</h3>
            <p>Verified requests move to MD approval. Returned or rejected requests stay out of the approval queue.</p>
        </div>

        <div class="table-panel">
            <table>
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Item</th>
                        <th>Requester</th>
                        <th>Reason</th>
                        <th>Evidence</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests && $requests->num_rows > 0): ?>
                        <?php while($row = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>#<?= (int)$row['id'] ?></strong><br>
                                    <small><?= clean($row['created_at']) ?></small><br>
                                    <span class="badge"><?= clean($row['final_status']) ?></span>
                                </td>
                                <td>
                                    <strong><?= clean(ucfirst($row['item_type'])) ?></strong><br>
                                    <?= clean($row['item_label']) ?><br>
                                    <small>ID: <?= (int)$row['item_id'] ?></small>
                                </td>
                                <td>
                                    <?= clean($row['requester_name']) ?><br>
                                    <small>User ID: <?= clean($row['requested_by']) ?></small>
                                </td>
                                <td>
                                    <strong><?= clean($row['request_reason']) ?></strong><br>
                                    <small><?= clean($row['request_notes']) ?></small>
                                </td>
                                <td>
                                    <?php if(!empty($row['attachment_path'])): ?>
                                        <a class="link-btn" href="<?= clean($row['attachment_path']) ?>" target="_blank">View File</a>
                                    <?php else: ?>
                                        <small>No attachment</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="decision-form">
                                        <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                        <textarea name="checker_notes" placeholder="Checker notes" required></textarea>
                                        <div class="decision-actions">
                                            <button type="submit" name="checker_action" value="verify">Verify</button>
                                            <button type="submit" name="checker_action" value="return" class="neutral-btn">Return</button>
                                            <button type="submit" name="checker_action" value="reject" class="danger-btn">Reject</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No deactivation requests are pending checker review.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="customerCasesSection" class="checker-section">
        <div class="section-heading customer-case-heading">
            <h3>Forwarded Customer Cases</h3>
            <p>Cases appear here after 12 unresolved hours, or immediately when escalated or returned. If the checker cannot resolve one, escalate it to MD.</p>
        </div>

        <div class="table-panel">
            <table>
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Customer</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Handle Case</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($unresolvedEnquiries && $unresolvedEnquiries->num_rows > 0): ?>
                        <?php while($case = $unresolvedEnquiries->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>Enquiry</strong><br>
                                    <?= clean($case['case_ref']) ?><br>
                                    <small><?= clean($case['created_at']) ?></small>
                                </td>
                                <td>
                                    <?= clean($case['customer_name']) ?><br>
                                    <small><?= clean($case['contact']) ?></small><br>
                                    <small><?= clean($case['email']) ?></small>
                                </td>
                                <td>
                                    <strong><?= clean($case['subject']) ?></strong><br>
                                    <small><?= clean($case['case_category']) ?></small><br>
                                    <?= clean($case['message']) ?>
                                </td>
                                <td>
                                    <span class="badge"><?= clean($case['status'] ?: 'Submitted') ?></span><br>
                                    <small>Assigned: <?= clean($case['assigned_staff'] ?: 'Unassigned') ?></small>
                                </td>
                                <td>
                                    <form method="POST" class="decision-form">
                                        <input type="hidden" name="case_type" value="enquiry">
                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">

                                        <select name="case_status" required>
                                        <?php foreach(['Returned to Checker','Assigned','In Progress','Escalated to MD','Resolved','Closed','Completed'] as $statusOption): ?>
                                                <option value="<?= clean($statusOption) ?>" <?= ($case['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                                                    <?= clean($statusOption) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <input type="text" name="assigned_staff" value="<?= clean($case['assigned_staff'] ?: ($_SESSION['name'] ?? 'Checking Officer')) ?>" placeholder="Assigned staff">
                                        <textarea name="case_response" placeholder="Response or action notes" required><?= clean($case['response']) ?></textarea>

                                        <div class="decision-actions">
                                            <button type="submit" name="customer_case_action" value="save">Save Case</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if($unresolvedComplaints && $unresolvedComplaints->num_rows > 0): ?>
                        <?php while($case = $unresolvedComplaints->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>Complaint</strong><br>
                                    <?= clean($case['case_ref']) ?><br>
                                    <small><?= clean($case['created_at']) ?></small>
                                </td>
                                <td>
                                    <?= clean($case['customer_name']) ?><br>
                                    <small><?= clean($case['contact']) ?></small><br>
                                    <small>Meter: <?= clean($case['meter_serial']) ?></small>
                                </td>
                                <td>
                                    <strong><?= clean($case['case_category']) ?></strong><br>
                                    <small>Zone: <?= clean($case['zone']) ?> | Priority: <?= clean($case['priority']) ?></small><br>
                                    <?= clean($case['message']) ?><br>

                                    <?php if(!empty($case['evidence_file'])): ?>
                                        <a class="link-btn" href="<?= clean('../../' . $case['evidence_file']) ?>" target="_blank">View Evidence</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge"><?= clean($case['status'] ?: 'Submitted') ?></span><br>
                                    <small>Assigned: <?= clean($case['assigned_staff'] ?: 'Unassigned') ?></small>
                                </td>
                                <td>
                                    <form method="POST" class="decision-form">
                                        <input type="hidden" name="case_type" value="complaint">
                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">

                                        <select name="case_status" required>
                                        <?php foreach(['Returned to Checker','Assigned','In Progress','Escalated to MD','Resolved','Closed','Completed'] as $statusOption): ?>
                                                <option value="<?= clean($statusOption) ?>" <?= ($case['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                                                    <?= clean($statusOption) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <input type="text" name="assigned_staff" value="<?= clean($case['assigned_staff'] ?: ($_SESSION['name'] ?? 'Checking Officer')) ?>" placeholder="Assigned staff">
                                        <textarea name="case_response" placeholder="Response, verification notes or resolution notes" required><?= clean($case['response']) ?></textarea>

                                        <div class="decision-actions">
                                            <button type="submit" name="customer_case_action" value="save">Save Case</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <?php if((!$unresolvedEnquiries || $unresolvedEnquiries->num_rows === 0) && (!$unresolvedComplaints || $unresolvedComplaints->num_rows === 0)): ?>
                        <tr>
                            <td colspan="5">No customer enquiries or complaints have crossed the 12-hour unresolved window, and no escalated cases are pending checker action.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.checker-page{margin-left:260px;margin-top:75px;padding:24px;background:#f4f7fb;min-height:calc(100vh - 135px);font-family:'Segoe UI',sans-serif;}
.page-header{margin-bottom:20px;}
.page-header h2{margin:0;color:#0a2a43;font-size:24px;}
.page-header p{margin:6px 0 0;color:#64748b;font-size:14px;}
.checker-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 18px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px;}
.checker-tab{border:1px solid #dbe2ea;background:#f8fafc;color:#334155;border-radius:9px;padding:10px 14px;cursor:pointer;font-weight:800;font-size:13px;}
.checker-tab.active{background:#0a2a43;color:#fff;border-color:#0a2a43;}
.checker-section{display:none;}
.checker-section.active{display:block;}
.section-heading{margin:18px 0 10px;}
.section-heading h3{margin:0;color:#0a2a43;font-size:17px;}
.section-heading p{margin:4px 0 0;color:#64748b;font-size:13px;}
.customer-case-heading{margin-top:28px;}
.table-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{background:#f8fafc;color:#334155;padding:13px;text-align:left;border-bottom:1px solid #e2e8f0;}
td{padding:13px;border-bottom:1px solid #eef2f7;vertical-align:top;color:#475569;}
.badge{display:inline-block;margin-top:6px;background:#eff6ff;color:#1e3a8a;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:700;}
.link-btn{color:#1e3a8a;font-weight:800;text-decoration:none;}
.decision-form select,
.decision-form input,
.decision-form textarea{width:100%;min-width:220px;border:1px solid #dbe2ea;border-radius:8px;padding:9px;box-sizing:border-box;margin-bottom:8px;background:#fff;color:#334155;}
.decision-form textarea{min-height:72px;}
.decision-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;}
.decision-actions button{border:none;background:#0a2a43;color:#fff;border-radius:8px;padding:8px 11px;cursor:pointer;font-weight:700;}
.decision-actions .neutral-btn{background:#64748b;}
.decision-actions .danger-btn{background:#7f1d1d;}
@media(max-width:900px){.checker-page{margin-left:0;}.checker-tab{width:100%;}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const buttons = document.querySelectorAll('.checker-tab');
    const sections = document.querySelectorAll('.checker-section');
    const savedSection = localStorage.getItem('checkerWorkbenchSection') || 'deactivationSection';

    function openCheckerSection(sectionId){
        sections.forEach(function(section){
            section.classList.toggle('active', section.id === sectionId);
        });

        buttons.forEach(function(button){
            button.classList.toggle('active', button.dataset.section === sectionId);
        });

        localStorage.setItem('checkerWorkbenchSection', sectionId);
    }

    buttons.forEach(function(button){
        button.addEventListener('click', function(){
            openCheckerSection(button.dataset.section);
        });
    });

    if(document.getElementById(savedSection)){
        openCheckerSection(savedSection);
    }
});
</script>
