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

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approver_action'])){
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = trim($_POST['approver_action']);
    $notes = trim($_POST['approver_notes'] ?? '');
    $approverId = (int)($_SESSION['user_id'] ?? 0);
    $approverName = $_SESSION['name'] ?? 'Managing Director';

    $requestResult = $conn->query("
        SELECT *
        FROM deactivation_requests
        WHERE id = $requestId
        AND final_status = 'Pending MD Approval'
        LIMIT 1
    ");

    $request = $requestResult ? $requestResult->fetch_assoc() : null;

    if(!$request){
        echo "<script>alert('Request was not found or is not pending MD approval.');window.location.href=window.location.pathname+window.location.search;</script>";
        exit;
    }

    $decision = 'Rejected';
    $status = 'Rejected by MD';

    if($action === 'approve'){
        $decision = 'Approved';
        $status = 'Approved';

        $itemId = (int)$request['item_id'];

        if($request['item_type'] === 'meter'){
            $stmtItem = $conn->prepare("UPDATE meters SET is_deactivated = 1, status = 'Deactivated' WHERE id = ?");
            $stmtItem->bind_param("i", $itemId);
            $stmtItem->execute();
        }

        if($request['item_type'] === 'asset'){
            $stmtItem = $conn->prepare("UPDATE assets SET is_deactivated = 1, status = 'Deactivated' WHERE id = ?");
            $stmtItem->bind_param("i", $itemId);
            $stmtItem->execute();
        }
    }

    $stmt = $conn->prepare("
        UPDATE deactivation_requests
        SET
            approver_id = ?,
            approver_name = ?,
            approver_decision = ?,
            approver_notes = ?,
            approved_at = NOW(),
            final_status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("issssi", $approverId, $approverName, $decision, $notes, $status, $requestId);
    $stmt->execute();

    echo "
    <script>
    alert('MD decision saved successfully.');
    window.location.href = window.location.pathname + window.location.search;
    </script>
    ";
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['md_customer_case_action'])){
    $caseType = trim($_POST['case_type'] ?? '');
    $caseId = (int)($_POST['case_id'] ?? 0);
    $status = trim($_POST['case_status'] ?? 'Resolved');
    $notes = trim($_POST['md_case_notes'] ?? '');
    $mdName = $_SESSION['name'] ?? 'Managing Director';

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
        $stmt->bind_param("sssi", $status, $mdName, $notes, $caseId);
        $stmt->execute();

        logCustomerCaseAction($conn, 'Enquiry', $caseId, 'MD Reviewed Enquiry', $oldStatus, $status, $mdName, $notes);

        echo "<script>alert('MD enquiry review saved successfully.');window.location.href=window.location.pathname+window.location.search;</script>";
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
        $stmt->bind_param("ssssi", $status, $mdName, $notes, $notes, $caseId);
        $stmt->execute();

        logCustomerCaseAction($conn, 'Complaint', $caseId, 'MD Reviewed Complaint', $oldStatus, $status, $mdName, $notes);

        echo "<script>alert('MD complaint review saved successfully.');window.location.href=window.location.pathname+window.location.search;</script>";
        exit;
    }
}

$requests = $conn->query("
    SELECT *
    FROM deactivation_requests
    WHERE final_status = 'Pending MD Approval'
    ORDER BY checked_at DESC, created_at DESC
");

$mdEnquiries = tableExists($conn, 'customer_enquiries')
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
        WHERE status = 'Escalated to MD'
        ORDER BY updated_at DESC, created_at DESC
    ")
    : false;

$mdComplaints = tableExists($conn, 'customer_complaints')
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
        WHERE status = 'Escalated to MD'
        ORDER BY updated_at DESC, created_at DESC
    ")
    : false;

$auditTrail = tableExists($conn, 'customer_case_updates')
    ? $conn->query("
        SELECT
            ccu.*,
            CASE
                WHEN ccu.case_type = 'Enquiry' THEN (SELECT enquiry_ref FROM customer_enquiries WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Complaint' THEN (SELECT complaint_ref FROM customer_complaints WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Meter Application' THEN (SELECT application_ref FROM meter_applications WHERE id = ccu.case_id LIMIT 1)
                ELSE ''
            END AS case_reference,
            CASE
                WHEN ccu.case_type = 'Enquiry' THEN (SELECT customer_name FROM customer_enquiries WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Complaint' THEN (SELECT customer_name FROM customer_complaints WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Meter Application' THEN (SELECT customer_name FROM meter_applications WHERE id = ccu.case_id LIMIT 1)
                ELSE ''
            END AS current_customer,
            CASE
                WHEN ccu.case_type = 'Enquiry' THEN (SELECT assigned_staff FROM customer_enquiries WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Complaint' THEN (SELECT assigned_staff FROM customer_complaints WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Meter Application' THEN (SELECT assigned_staff FROM meter_applications WHERE id = ccu.case_id LIMIT 1)
                ELSE ''
            END AS current_assigned_staff,
            CASE
                WHEN ccu.case_type = 'Enquiry' THEN (SELECT status FROM customer_enquiries WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Complaint' THEN (SELECT status FROM customer_complaints WHERE id = ccu.case_id LIMIT 1)
                WHEN ccu.case_type = 'Meter Application' THEN (SELECT status FROM meter_applications WHERE id = ccu.case_id LIMIT 1)
                ELSE ''
            END AS current_status
        FROM customer_case_updates ccu
        ORDER BY ccu.created_at DESC, ccu.id DESC
        LIMIT 80
    ")
    : false;
?>

<div class="page-content approver-page">
    <div class="page-header">
        <div>
            <h2>MD Approval Desk</h2>
            <p>Approve deactivation requests and review escalated customer service cases.</p>
        </div>
    </div>

    <div class="approver-tabs">
        <button type="button" class="approver-tab active" data-section="deactivationRequestsSection">
            Deactivation Requests
        </button>

        <button type="button" class="approver-tab" data-section="escalatedCasesSection">
            Escalated Customer Cases
        </button>

        <button type="button" class="approver-tab" data-section="auditTrailSection">
            Customer Management Audit Trails
        </button>
    </div>

    <div id="deactivationRequestsSection" class="approver-section active">
        <div class="section-heading">
            <h3>Deactivation Requests</h3>
            <p>Requests shown here have already been checked and verified.</p>
        </div>

        <div class="table-panel">
            <table>
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Item</th>
                        <th>Requester</th>
                        <th>Checker Review</th>
                        <th>Evidence</th>
                        <th>MD Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($requests && $requests->num_rows > 0): ?>
                        <?php while($row = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>#<?= (int)$row['id'] ?></strong><br>
                                    <small>Requested: <?= clean($row['created_at']) ?></small><br>
                                    <small>Checked: <?= clean($row['checked_at']) ?></small>
                                </td>
                                <td>
                                    <strong><?= clean(ucfirst($row['item_type'])) ?></strong><br>
                                    <?= clean($row['item_label']) ?><br>
                                    <small>ID: <?= (int)$row['item_id'] ?></small>
                                </td>
                                <td>
                                    <?= clean($row['requester_name']) ?><br>
                                    <strong><?= clean($row['request_reason']) ?></strong><br>
                                    <small><?= clean($row['request_notes']) ?></small>
                                </td>
                                <td>
                                    <span class="badge"><?= clean($row['checker_decision']) ?></span><br>
                                    <strong><?= clean($row['checker_name']) ?></strong><br>
                                    <small><?= clean($row['checker_notes']) ?></small>
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
                                        <textarea name="approver_notes" placeholder="MD approval/rejection notes" required></textarea>
                                        <div class="decision-actions">
                                            <button type="submit" name="approver_action" value="approve">Approve Deactivation</button>
                                            <button type="submit" name="approver_action" value="reject" class="danger-btn">Reject</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No deactivation requests are pending MD approval.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="escalatedCasesSection" class="approver-section">
        <div class="section-heading">
            <h3>Escalated Customer Cases</h3>
            <p>Cases forwarded by the checker for MD review and final direction.</p>
        </div>

        <div class="table-panel">
            <table>
            <thead>
                <tr>
                    <th>Case</th>
                    <th>Customer</th>
                    <th>Details</th>
                    <th>Checker Status</th>
                    <th>MD Review</th>
                </tr>
            </thead>
            <tbody>
                <?php if($mdEnquiries && $mdEnquiries->num_rows > 0): ?>
                    <?php while($case = $mdEnquiries->fetch_assoc()): ?>
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
                                <?= clean($case['message']) ?><br>
                                <small>Checker notes: <?= clean($case['response']) ?></small>
                            </td>
                            <td><span class="badge"><?= clean($case['status']) ?></span></td>
                            <td>
                                <form method="POST" class="decision-form">
                                    <input type="hidden" name="case_type" value="enquiry">
                                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                    <select name="case_status" required>
                                        <?php foreach(['Resolved','Closed','Completed','Returned to Checker'] as $statusOption): ?>
                                            <option value="<?= clean($statusOption) ?>"><?= clean($statusOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="md_case_notes" placeholder="MD review notes" required></textarea>
                                    <div class="decision-actions">
                                        <button type="submit" name="md_customer_case_action" value="save">Save Review</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>

                <?php if($mdComplaints && $mdComplaints->num_rows > 0): ?>
                    <?php while($case = $mdComplaints->fetch_assoc()): ?>
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
                                <small>Checker notes: <?= clean($case['response']) ?></small><br>
                                <?php if(!empty($case['evidence_file'])): ?>
                                    <a class="link-btn" href="<?= clean('../../' . $case['evidence_file']) ?>" target="_blank">View Evidence</a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge"><?= clean($case['status']) ?></span></td>
                            <td>
                                <form method="POST" class="decision-form">
                                    <input type="hidden" name="case_type" value="complaint">
                                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                    <select name="case_status" required>
                                        <?php foreach(['Resolved','Closed','Completed','Returned to Checker'] as $statusOption): ?>
                                            <option value="<?= clean($statusOption) ?>"><?= clean($statusOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="md_case_notes" placeholder="MD review notes" required></textarea>
                                    <div class="decision-actions">
                                        <button type="submit" name="md_customer_case_action" value="save">Save Review</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>

                <?php if((!$mdEnquiries || $mdEnquiries->num_rows === 0) && (!$mdComplaints || $mdComplaints->num_rows === 0)): ?>
                    <tr>
                        <td colspan="5">No customer cases are escalated to MD review.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>

    <div id="auditTrailSection" class="approver-section">
        <div class="section-heading">
            <h3>Customer Management Audit Trails</h3>
            <p>Recent customer case actions for MD review.</p>
        </div>

        <div class="audit-list">
            <?php if($auditTrail && $auditTrail->num_rows > 0): ?>
                <?php while($audit = $auditTrail->fetch_assoc()): ?>
                    <div class="audit-row">
                        <div>
                            <strong><?= clean($audit['staff_name'] ?: 'System User') ?></strong>
                            <span>At <?= clean($audit['created_at']) ?></span>
                        </div>
                        <div>
                            <strong><?= clean($audit['action_taken']) ?></strong>
                            <span><?= clean($audit['case_type']) ?> <?= clean($audit['case_reference'] ?: '#' . $audit['case_id']) ?></span>
                        </div>
                        <div>
                            <strong><?= clean($audit['old_status'] ?: 'New') ?> &rarr; <?= clean($audit['new_status'] ?: 'Updated') ?></strong>
                            <span>Current status: <?= clean($audit['current_status'] ?: $audit['new_status'] ?: 'N/A') ?></span>
                        </div>
                        <div>
                            <strong>Assigned to <?= clean($audit['current_assigned_staff'] ?: 'Unassigned') ?></strong>
                            <span>Customer: <?= clean($audit['current_customer'] ?: 'N/A') ?></span>
                        </div>
                        <p>
                            <?= clean($audit['staff_name'] ?: 'System User') ?>
                            handled this case
                            <?php if(!empty($audit['current_assigned_staff'])): ?>
                                and it is currently assigned to <?= clean($audit['current_assigned_staff']) ?>
                            <?php else: ?>
                                and it is currently unassigned
                            <?php endif; ?>.
                            <?php if(!empty($audit['notes'])): ?>
                                Notes: <?= clean($audit['notes']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="audit-row">No customer management audit trail records found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.approver-page{margin-left:260px;margin-top:75px;padding:24px;background:#f4f7fb;min-height:calc(100vh - 135px);font-family:'Segoe UI',sans-serif;}
.page-header{margin-bottom:20px;}
.page-header h2{margin:0;color:#0a2a43;font-size:24px;}
.page-header p{margin:6px 0 0;color:#64748b;font-size:14px;}
.approver-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 18px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px;}
.approver-tab{border:1px solid #dbe2ea;background:#f8fafc;color:#334155;border-radius:9px;padding:10px 14px;cursor:pointer;font-weight:800;font-size:13px;}
.approver-tab.active{background:#0a2a43;color:#fff;border-color:#0a2a43;}
.approver-section{display:none;}
.approver-section.active{display:block;}
.section-heading{margin:22px 0 10px;}
.section-heading h3{margin:0;color:#0a2a43;font-size:17px;}
.section-heading p{margin:4px 0 0;color:#64748b;font-size:13px;}
.table-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{background:#f8fafc;color:#334155;padding:13px;text-align:left;border-bottom:1px solid #e2e8f0;}
td{padding:13px;border-bottom:1px solid #eef2f7;vertical-align:top;color:#475569;}
.badge{display:inline-block;margin-bottom:6px;background:#eff6ff;color:#1e3a8a;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:700;}
.link-btn{color:#1e3a8a;font-weight:800;text-decoration:none;}
.decision-form select,
.decision-form textarea{width:100%;min-width:220px;min-height:42px;border:1px solid #dbe2ea;border-radius:8px;padding:9px;box-sizing:border-box;margin-bottom:8px;background:#fff;color:#334155;}
.decision-form textarea{min-height:72px;}
.decision-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;}
.decision-actions button{border:none;background:#0a2a43;color:#fff;border-radius:8px;padding:8px 11px;cursor:pointer;font-weight:700;}
.decision-actions .danger-btn{background:#7f1d1d;}
.audit-list{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;}
.audit-row{display:grid;grid-template-columns:1.1fr 1.2fr 1fr .9fr;gap:12px;padding:13px;border-bottom:1px solid #eef2f7;color:#475569;font-size:13px;}
.audit-row:last-child{border-bottom:none;}
.audit-row strong{display:block;color:#0a2a43;}
.audit-row span{display:block;color:#64748b;font-size:12px;margin-top:4px;}
.audit-row p{grid-column:1/-1;margin:0;color:#475569;line-height:1.5;}
@media(max-width:900px){.approver-page{margin-left:0;}}
@media(max-width:900px){.audit-row{grid-template-columns:1fr;}.approver-tab{width:100%;}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const buttons = document.querySelectorAll('.approver-tab');
    const sections = document.querySelectorAll('.approver-section');
    const savedSection = localStorage.getItem('mdApprovalDeskSection') || 'deactivationRequestsSection';

    function openApproverSection(sectionId){
        sections.forEach(function(section){
            section.classList.toggle('active', section.id === sectionId);
        });

        buttons.forEach(function(button){
            button.classList.toggle('active', button.dataset.section === sectionId);
        });

        localStorage.setItem('mdApprovalDeskSection', sectionId);
    }

    buttons.forEach(function(button){
        button.addEventListener('click', function(){
            openApproverSection(button.dataset.section);
        });
    });

    if(document.getElementById(savedSection)){
        openApproverSection(savedSection);
    }
});
</script>
