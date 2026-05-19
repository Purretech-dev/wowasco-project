<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

/* =========================================================
   SAME PAGE REDIRECT
========================================================= */

function redirectSamePage($message = ''){

    $currentPage = $_SERVER['REQUEST_URI'];

    echo "
    <script>
        " . (!empty($message) ? "alert(" . json_encode($message) . ");" : "") . "
        window.location.href = " . json_encode($currentPage) . ";
    </script>
    ";

    exit;
}

/* =========================================================
   CREATE TABLES
========================================================= */

$conn->query("
CREATE TABLE IF NOT EXISTS asset_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NULL,
    asset_name VARCHAR(255),
    serial_number VARCHAR(100),
    model VARCHAR(255),
    asset_type VARCHAR(100),
    location VARCHAR(255),
    maintenance_type VARCHAR(100),
    issue_description TEXT,
    priority VARCHAR(50),
    reported_by VARCHAR(255),
    assigned_to VARCHAR(255),
    vendor_name VARCHAR(255),
    date_reported DATE,
    expected_completion_date DATE,
    actual_completion_date DATE NULL,
    estimated_cost DECIMAL(12,2) DEFAULT 0,
    parts_cost DECIMAL(12,2) DEFAULT 0,
    labour_cost DECIMAL(12,2) DEFAULT 0,
    vendor_cost DECIMAL(12,2) DEFAULT 0,
    actual_cost DECIMAL(12,2) DEFAULT 0,
    downtime_hours DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(100) DEFAULT 'Pending',
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS asset_maintenance_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT,
    part_name VARCHAR(255),
    quantity INT DEFAULT 1,
    unit_cost DECIMAL(12,2) DEFAULT 0,
    total_cost DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS asset_maintenance_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT,
    asset_name VARCHAR(255),
    serial_number VARCHAR(100),
    frequency VARCHAR(50),
    last_service_date DATE,
    next_service_date DATE,
    assigned_to VARCHAR(255),
    notes TEXT,
    status VARCHAR(100) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/* =========================================================
   SAFE COLUMN UPGRADES
========================================================= */

function ensureColumn($conn, $table, $column, $definition){

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && $check->num_rows == 0){

        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

ensureColumn($conn, 'assets', 'is_deactivated', 'TINYINT(1) DEFAULT 0');
ensureColumn($conn, 'assets', 'asset_name', 'VARCHAR(255) NULL');
ensureColumn($conn, 'assets', 'serial_number', 'VARCHAR(100) NULL');
ensureColumn($conn, 'assets', 'model', 'VARCHAR(255) NULL');
ensureColumn($conn, 'assets', 'asset_type', 'VARCHAR(100) NULL');
ensureColumn($conn, 'assets', 'location', 'VARCHAR(255) NULL');
ensureColumn($conn, 'assets', 'status', 'VARCHAR(100) NULL');

ensureColumn($conn, 'asset_maintenance', 'source_schedule_id', 'INT NULL');
ensureColumn($conn, 'asset_maintenance', 'work_order_ref', 'VARCHAR(80) NULL');

ensureColumn($conn, 'asset_maintenance_schedule', 'maintenance_type', "VARCHAR(100) DEFAULT 'Preventive'");
ensureColumn($conn, 'asset_maintenance_schedule', 'priority', "VARCHAR(50) DEFAULT 'Medium'");
ensureColumn($conn, 'asset_maintenance_schedule', 'estimated_cost', 'DECIMAL(12,2) DEFAULT 0');
ensureColumn($conn, 'asset_maintenance_schedule', 'work_scope', 'TEXT NULL');
ensureColumn($conn, 'asset_maintenance_schedule', 'last_work_order_id', 'INT NULL');
ensureColumn($conn, 'asset_maintenance_schedule', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

function assetMaintenanceRef(){

    return 'WO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/* =========================================================
   FETCH ASSET DETAILS HELPER
========================================================= */

function getAsset($conn, $asset_id){

    $stmt = $conn->prepare("
        SELECT *
        FROM assets
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $asset_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/* =========================================================
   CREATE WORK ORDER
========================================================= */

if(isset($_POST['create_work_order'])){

    $asset_id = intval($_POST['asset_id'] ?? 0);
    $asset = getAsset($conn, $asset_id);
    $source_schedule_id = intval($_POST['source_schedule_id'] ?? 0);
    $work_order_ref = assetMaintenanceRef();

    $asset_name = $asset['asset_name'] ?? '';
    $serial_number = $asset['serial_number'] ?? '';
    $model = $asset['model'] ?? '';
    $asset_type = $asset['asset_type'] ?? '';
    $location = $asset['location'] ?? '';

    $maintenance_type = $_POST['maintenance_type'] ?? '';
    $issue_description = $_POST['issue_description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $reported_by = $_POST['reported_by'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $vendor_name = $_POST['vendor_name'] ?? '';
    $date_reported = $_POST['date_reported'] ?? date('Y-m-d');
    $expected_completion_date = $_POST['expected_completion_date'] ?? null;
    $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    $stmt = $conn->prepare("
        INSERT INTO asset_maintenance
        (
            asset_id,
            asset_name,
            serial_number,
            model,
            asset_type,
            location,
            maintenance_type,
            issue_description,
            priority,
            reported_by,
            assigned_to,
            vendor_name,
            date_reported,
            expected_completion_date,
            source_schedule_id,
            work_order_ref,
            estimated_cost,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssssssssssisds",
        $asset_id,
        $asset_name,
        $serial_number,
        $model,
        $asset_type,
        $location,
        $maintenance_type,
        $issue_description,
        $priority,
        $reported_by,
        $assigned_to,
        $vendor_name,
        $date_reported,
        $expected_completion_date,
        $source_schedule_id,
        $work_order_ref,
        $estimated_cost,
        $status
    );

    if($stmt->execute()){

        $newMaintenanceId = $stmt->insert_id;

        $conn->query("
            UPDATE assets
            SET status = 'Under Maintenance'
            WHERE id = $asset_id
        ");

        if($source_schedule_id > 0){

            $conn->query("
                UPDATE asset_maintenance_schedule
                SET last_work_order_id = $newMaintenanceId
                WHERE id = $source_schedule_id
            ");
        }

        redirectSamePage("Maintenance work order created successfully.");
    }

    die($stmt->error);
}

/* =========================================================
   UPDATE WORK ORDER
========================================================= */

if(isset($_POST['update_work_order'])){

    $id = intval($_POST['maintenance_id']);

    $maintenance_type = $_POST['maintenance_type'] ?? '';
    $issue_description = $_POST['issue_description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $vendor_name = $_POST['vendor_name'] ?? '';
    $expected_completion_date = $_POST['expected_completion_date'] ?? null;
    $actual_completion_date = $_POST['actual_completion_date'] ?? null;

    $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
    $parts_cost = floatval($_POST['parts_cost'] ?? 0);
    $labour_cost = floatval($_POST['labour_cost'] ?? 0);
    $vendor_cost = floatval($_POST['vendor_cost'] ?? 0);
    $actual_cost = $parts_cost + $labour_cost + $vendor_cost;

    $downtime_hours = floatval($_POST['downtime_hours'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $resolution_notes = $_POST['resolution_notes'] ?? '';

    $stmt = $conn->prepare("
        UPDATE asset_maintenance
        SET
            maintenance_type = ?,
            issue_description = ?,
            priority = ?,
            assigned_to = ?,
            vendor_name = ?,
            expected_completion_date = ?,
            actual_completion_date = ?,
            estimated_cost = ?,
            parts_cost = ?,
            labour_cost = ?,
            vendor_cost = ?,
            actual_cost = ?,
            downtime_hours = ?,
            status = ?,
            resolution_notes = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssssssddddddssi",
        $maintenance_type,
        $issue_description,
        $priority,
        $assigned_to,
        $vendor_name,
        $expected_completion_date,
        $actual_completion_date,
        $estimated_cost,
        $parts_cost,
        $labour_cost,
        $vendor_cost,
        $actual_cost,
        $downtime_hours,
        $status,
        $resolution_notes,
        $id
    );

    if($stmt->execute()){

        $assetRes = $conn->query("
            SELECT asset_id, source_schedule_id
            FROM asset_maintenance
            WHERE id = $id
        ");

        $assetRow = $assetRes->fetch_assoc();
        $asset_id = intval($assetRow['asset_id'] ?? 0);
        $source_schedule_id = intval($assetRow['source_schedule_id'] ?? 0);

        if($asset_id > 0){

            if($status === 'Completed'){

                $conn->query("
                    UPDATE assets
                    SET status = 'Operational'
                    WHERE id = $asset_id
                ");

                if($source_schedule_id > 0){

                    $safeCompletionDate = $conn->real_escape_string($actual_completion_date ?: date('Y-m-d'));

                    $conn->query("
                        UPDATE asset_maintenance_schedule
                        SET last_service_date = '$safeCompletionDate'
                        WHERE id = $source_schedule_id
                    ");
                }

            }else if($status === 'In Progress'){

                $conn->query("
                    UPDATE assets
                    SET status = 'Under Maintenance'
                    WHERE id = $asset_id
                ");
            }
        }

        redirectSamePage("Work order updated successfully.");
    }

    die($stmt->error);
}

/* =========================================================
   ADD PART
========================================================= */

if(isset($_POST['add_part'])){

    $maintenance_id = intval($_POST['maintenance_id']);
    $part_name = $_POST['part_name'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $total_cost = $quantity * $unit_cost;

    $stmt = $conn->prepare("
        INSERT INTO asset_maintenance_parts
        (
            maintenance_id,
            part_name,
            quantity,
            unit_cost,
            total_cost
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isidd",
        $maintenance_id,
        $part_name,
        $quantity,
        $unit_cost,
        $total_cost
    );

    if($stmt->execute()){

        $conn->query("
            UPDATE asset_maintenance
            SET
                parts_cost = (
                    SELECT IFNULL(SUM(total_cost),0)
                    FROM asset_maintenance_parts
                    WHERE maintenance_id = $maintenance_id
                ),
                actual_cost = parts_cost + labour_cost + vendor_cost
            WHERE id = $maintenance_id
        ");

        redirectSamePage("Part added successfully.");
    }

    die($stmt->error);
}

/* =========================================================
   CREATE PREVENTIVE SCHEDULE
========================================================= */

if(isset($_POST['create_schedule'])){

    $asset_id = intval($_POST['schedule_asset_id']);
    $asset = getAsset($conn, $asset_id);

    $asset_name = $asset['asset_name'] ?? '';
    $serial_number = $asset['serial_number'] ?? '';

    $maintenance_type = $_POST['schedule_maintenance_type'] ?? 'Preventive';
    $priority = $_POST['schedule_priority'] ?? 'Medium';
    $frequency = $_POST['frequency'] ?? '';
    $last_service_date = $_POST['last_service_date'] ?? null;
    $next_service_date = $_POST['next_service_date'] ?? null;
    $assigned_to = $_POST['schedule_assigned_to'] ?? '';
    $estimated_cost = floatval($_POST['schedule_estimated_cost'] ?? 0);
    $work_scope = $_POST['schedule_work_scope'] ?? '';
    $notes = $_POST['schedule_notes'] ?? '';
    $status = $_POST['schedule_status'] ?? 'Active';

    $stmt = $conn->prepare("
        INSERT INTO asset_maintenance_schedule
        (
            asset_id,
            asset_name,
            serial_number,
            maintenance_type,
            priority,
            frequency,
            last_service_date,
            next_service_date,
            assigned_to,
            estimated_cost,
            work_scope,
            notes,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssssssdsss",
        $asset_id,
        $asset_name,
        $serial_number,
        $maintenance_type,
        $priority,
        $frequency,
        $last_service_date,
        $next_service_date,
        $assigned_to,
        $estimated_cost,
        $work_scope,
        $notes,
        $status
    );

    if($stmt->execute()){

        redirectSamePage("Preventive maintenance schedule created successfully.");
    }

    die($stmt->error);
}

/* =========================================================
   UPDATE PREVENTIVE SCHEDULE
========================================================= */

if(isset($_POST['update_schedule'])){

    $id = intval($_POST['schedule_id'] ?? 0);
    $maintenance_type = $_POST['schedule_maintenance_type'] ?? 'Preventive';
    $priority = $_POST['schedule_priority'] ?? 'Medium';
    $frequency = $_POST['frequency'] ?? '';
    $last_service_date = $_POST['last_service_date'] ?? null;
    $next_service_date = $_POST['next_service_date'] ?? null;
    $assigned_to = $_POST['schedule_assigned_to'] ?? '';
    $estimated_cost = floatval($_POST['schedule_estimated_cost'] ?? 0);
    $work_scope = $_POST['schedule_work_scope'] ?? '';
    $notes = $_POST['schedule_notes'] ?? '';
    $status = $_POST['schedule_status'] ?? 'Active';

    $stmt = $conn->prepare("
        UPDATE asset_maintenance_schedule
        SET
            maintenance_type = ?,
            priority = ?,
            frequency = ?,
            last_service_date = ?,
            next_service_date = ?,
            assigned_to = ?,
            estimated_cost = ?,
            work_scope = ?,
            notes = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssssdsssi",
        $maintenance_type,
        $priority,
        $frequency,
        $last_service_date,
        $next_service_date,
        $assigned_to,
        $estimated_cost,
        $work_scope,
        $notes,
        $status,
        $id
    );

    if($stmt->execute()){

        redirectSamePage("Preventive maintenance schedule updated successfully.");
    }

    die($stmt->error);
}

/* =========================================================
   GENERATE WORK ORDER FROM SCHEDULE
========================================================= */

if(isset($_POST['generate_work_order'])){

    $schedule_id = intval($_POST['schedule_id'] ?? 0);

    $scheduleResult = $conn->query("
        SELECT *
        FROM asset_maintenance_schedule
        WHERE id = $schedule_id
        LIMIT 1
    ");

    $schedule = $scheduleResult ? $scheduleResult->fetch_assoc() : null;

    if(!$schedule){

        redirectSamePage("Schedule was not found.");
    }

    $asset = getAsset($conn, intval($schedule['asset_id']));
    $asset_id = intval($schedule['asset_id']);
    $asset_name = $asset['asset_name'] ?? ($schedule['asset_name'] ?? '');
    $serial_number = $asset['serial_number'] ?? ($schedule['serial_number'] ?? '');
    $model = $asset['model'] ?? '';
    $asset_type = $asset['asset_type'] ?? '';
    $location = $asset['location'] ?? '';
    $work_order_ref = assetMaintenanceRef();
    $maintenance_type = $schedule['maintenance_type'] ?? 'Preventive';
    $issue_description = $schedule['work_scope'] ?: ($schedule['notes'] ?? 'Preventive maintenance generated from schedule.');
    $priority = $schedule['priority'] ?? 'Medium';
    $reported_by = 'Preventive Schedule';
    $assigned_to = $schedule['assigned_to'] ?? '';
    $vendor_name = '';
    $date_reported = date('Y-m-d');
    $expected_completion_date = $schedule['next_service_date'] ?? date('Y-m-d');
    $estimated_cost = floatval($schedule['estimated_cost'] ?? 0);
    $status = 'Pending';

    $stmt = $conn->prepare("
        INSERT INTO asset_maintenance
        (
            asset_id,
            asset_name,
            serial_number,
            model,
            asset_type,
            location,
            maintenance_type,
            issue_description,
            priority,
            reported_by,
            assigned_to,
            vendor_name,
            date_reported,
            expected_completion_date,
            source_schedule_id,
            work_order_ref,
            estimated_cost,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssssssssssisds",
        $asset_id,
        $asset_name,
        $serial_number,
        $model,
        $asset_type,
        $location,
        $maintenance_type,
        $issue_description,
        $priority,
        $reported_by,
        $assigned_to,
        $vendor_name,
        $date_reported,
        $expected_completion_date,
        $schedule_id,
        $work_order_ref,
        $estimated_cost,
        $status
    );

    if($stmt->execute()){

        $newMaintenanceId = $stmt->insert_id;

        $conn->query("
            UPDATE asset_maintenance_schedule
            SET
                last_work_order_id = $newMaintenanceId,
                last_service_date = IFNULL(last_service_date, CURDATE())
            WHERE id = $schedule_id
        ");

        $conn->query("
            UPDATE assets
            SET status = 'Under Maintenance'
            WHERE id = $asset_id
        ");

        redirectSamePage("Work order generated from preventive schedule.");
    }

    die($stmt->error);
}

/* =========================================================
   FETCH DATA
========================================================= */

$assets = [];

$assetResult = $conn->query("
    SELECT *
    FROM assets
    WHERE IFNULL(is_deactivated,0) = 0
    ORDER BY asset_name ASC
");

while($assetResult && $row = $assetResult->fetch_assoc()){

    $assets[] = $row;
}

$workOrders = $conn->query("
    SELECT *
    FROM asset_maintenance
    ORDER BY id DESC
");

$schedules = $conn->query("
    SELECT *
    FROM asset_maintenance_schedule
    ORDER BY next_service_date ASC
");

$parts = [];

$partsResult = $conn->query("
    SELECT *
    FROM asset_maintenance_parts
    ORDER BY id DESC
");

while($partsResult && $part = $partsResult->fetch_assoc()){

    $parts[$part['maintenance_id']][] = $part;
}

/* =========================================================
   DASHBOARD COUNTS
========================================================= */

function countValue($conn, $sql){

    $res = $conn->query($sql);

    if($res){

        $row = $res->fetch_assoc();

        return $row['c'] ?? 0;
    }

    return 0;
}

$totalOrders = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance");
$pendingOrders = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance WHERE status='Pending'");
$inProgressOrders = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance WHERE status='In Progress'");
$completedOrders = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance WHERE status='Completed'");
$overdueOrders = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance WHERE expected_completion_date < CURDATE() AND status NOT IN ('Completed','Cancelled')");
$upcomingSchedules = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance_schedule WHERE next_service_date >= CURDATE() AND status='Active'");
$overdueSchedules = countValue($conn, "SELECT COUNT(*) c FROM asset_maintenance_schedule WHERE next_service_date < CURDATE() AND status='Active'");

$costResult = $conn->query("
    SELECT IFNULL(SUM(actual_cost),0) total_cost
    FROM asset_maintenance
");

$costRow = $costResult->fetch_assoc();
$totalCost = $costRow['total_cost'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>

<title>Assets Maintenance</title>

<link rel="stylesheet"
href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>

<style>

body{
    margin:0;
    background:#eef3f8;
    font-family:'Segoe UI',sans-serif;
}

.page-content{
    margin-left:250px;
    padding:90px 25px 120px;
}

.page-header{
    margin-bottom:24px;
}

.page-title{
    margin:0;
    font-size:32px;
    font-weight:800;
    color:#0f172a;
}

.page-subtitle{
    margin-top:8px;
    font-size:15px;
    color:#64748b;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:22px;
}

.summary-card{
    background:white;
    border-radius:20px;
    padding:18px;
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
    border-left:5px solid #1e7d4f;
}

.summary-card.blue{
    border-left-color:#1e3a8a;
}

.summary-card.yellow{
    border-left-color:#f4c430;
}

.summary-title{
    font-size:12px;
    color:#64748b;
    text-transform:uppercase;
    font-weight:700;
}

.summary-value{
    font-size:25px;
    font-weight:800;
    margin-top:7px;
    color:#0f172a;
}

.card{
    background:white;
    border-radius:24px;
    padding:24px;
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
    margin-bottom:22px;
    overflow:visible !important;
}

.top-action-bar{
    display:flex;
    gap:10px;
    justify-content:flex-end;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.primary-btn{
    background:linear-gradient(135deg,#1e3a8a,#2563eb);
    color:white;
    border:none;
    padding:12px 18px;
    border-radius:12px;
    cursor:pointer;
}

.green-btn{
    background:linear-gradient(135deg,#1e7d4f,#249c63);
    color:white;
    border:none;
    padding:12px 18px;
    border-radius:12px;
    cursor:pointer;
}

.tab-bar{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.tab-btn{
    border:none;
    background:white;
    padding:11px 16px;
    border-radius:12px;
    cursor:pointer;
    color:#334155;
    font-weight:600;
    box-shadow:0 3px 10px rgba(15,23,42,0.05);
}

.tab-btn.active{
    background:#0f172a;
    color:white;
}

.tab-section{
    display:none;
}

.tab-section.active{
    display:block;
}

.status-badge{
    background:#dcfce7;
    color:#15803d;
    padding:7px 13px;
    border-radius:30px;
    font-size:12px;
    font-weight:700;
}

.status-pending{
    background:#fef3c7;
    color:#92400e;
}

.status-progress{
    background:#dbeafe;
    color:#1d4ed8;
}

.status-completed{
    background:#dcfce7;
    color:#15803d;
}

.status-critical{
    background:#fee2e2;
    color:#b91c1c;
}

.detail-main{
    font-size:14px;
    font-weight:700;
    color:#0f172a;
}

.detail-sub{
    font-size:12px;
    color:#64748b;
    margin-top:3px;
}

/* =========================================================
   ACTION MENU FIX
========================================================= */

.action-wrapper{
    position:relative;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:visible !important;
    z-index:9999;
}

.action-btn{
    width:42px;
    height:42px;
    border:none;
    border-radius:12px;
    background:#ffffff;
    border:1px solid #dbe2ea;
    cursor:pointer;
    font-size:22px;
    font-weight:700;
    color:#0f172a;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:all .2s ease;
}

.action-btn:hover{
    background:#f8fafc;
    border-color:#cbd5e1;
}

.action-menu{
    display:none;
    position:absolute;
    right:0;
    top:52px;
    min-width:240px;
    background:#ffffff;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    box-shadow:0 20px 40px rgba(15,23,42,0.18);
    z-index:999999999 !important;
    animation:fadeMenu .18s ease;
}

@keyframes fadeMenu{
    from{
        opacity:0;
        transform:translateY(8px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

.action-menu button{
    width:100%;
    padding:14px 18px;
    background:white;
    border:none;
    text-align:left;
    cursor:pointer;
    font-size:13px;
    color:#334155;
    transition:background .2s ease;
}

.action-menu button:hover{
    background:#f8fafc;
}

/* =========================================================
   DATATABLE OVERFLOW FIX
========================================================= */

.dataTables_wrapper{
    overflow:visible !important;
    position:relative !important;
    z-index:1;
}

.dataTables_scroll,
.dataTables_scrollBody,
.dataTables_scrollHead,
.dataTables_scrollFoot{
    overflow:visible !important;
}

table.dataTable{
    overflow:visible !important;
    position:relative;
}

table.dataTable tbody{
    overflow:visible !important;
}

table.dataTable tbody tr{
    overflow:visible !important;
    position:relative;
}

table.dataTable tbody td{
    overflow:visible !important;
    position:relative;
}

.card{
    overflow:visible !important;
    position:relative;
}

/* LAST COLUMN */

td:last-child,
th:last-child{
    overflow:visible !important;
    width:90px !important;
    text-align:center !important;
    position:relative;
    z-index:999;
}

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.55);
    z-index:999999;
    justify-content:center;
    align-items:center;
    padding:20px;
    overflow-y:auto;
}

.modal-content{
    width:100%;
    max-width:760px;
    background:white;
    border-radius:24px;
    max-height:90vh;
    overflow-y:auto;
    box-shadow:0 20px 50px rgba(15,23,42,0.18);
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:20px 24px;
    background:#f8fafc;
}

.modal-body{
    padding:24px;
}

.close-modal{
    width:42px;
    height:42px;
    border:none;
    border-radius:12px;
    background:white;
    border:1px solid #dbe2ea;
    cursor:pointer;
    font-size:24px;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.form-group{
    display:flex;
    flex-direction:column;
    gap:7px;
}

.form-group.full{
    grid-column:1/-1;
}

.form-group input,
.form-group select,
.form-group textarea{
    padding:12px;
    border:1px solid #dbe2ea;
    border-radius:12px;
    font-family:'Segoe UI',sans-serif;
}

.form-group textarea{
    min-height:90px;
    resize:vertical;
}

.submit-btn{
    background:linear-gradient(135deg,#1e7d4f,#249c63);
    color:white;
    border:none;
    padding:14px;
    border-radius:14px;
    cursor:pointer;
}

.parts-box{
    background:#f8fafc;
    padding:10px;
    border-radius:12px;
    margin-top:8px;
}

.part-item{
    font-size:12px;
    color:#475569;
    border-bottom:1px solid #e2e8f0;
    padding:6px 0;
}

.report-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.report-box{
    background:#f8fafc;
    border-radius:16px;
    padding:18px;
}

@media(max-width:1000px){
    .cards{
        grid-template-columns:repeat(2,1fr);
    }

    .form-grid,
    .report-grid{
        grid-template-columns:1fr;
    }
}

</style>
</head>

<body>

<div class="page-content">

<div class="page-header">

<h1 class="page-title">
Assets Maintenance
</h1>

<p class="page-subtitle">
Manage asset repairs, preventive maintenance, work orders, costs, downtime, parts, schedules and history.
</p>

</div>

<div class="cards">

<div class="summary-card blue">
<div class="summary-title">Total Work Orders</div>
<div class="summary-value"><?= $totalOrders; ?></div>
</div>

<div class="summary-card yellow">
<div class="summary-title">Pending</div>
<div class="summary-value"><?= $pendingOrders; ?></div>
</div>

<div class="summary-card blue">
<div class="summary-title">In Progress</div>
<div class="summary-value"><?= $inProgressOrders; ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Completed</div>
<div class="summary-value"><?= $completedOrders; ?></div>
</div>

<div class="summary-card yellow">
<div class="summary-title">Overdue Jobs</div>
<div class="summary-value"><?= $overdueOrders; ?></div>
</div>

<div class="summary-card blue">
<div class="summary-title">Upcoming PM</div>
<div class="summary-value"><?= $upcomingSchedules; ?></div>
</div>

<div class="summary-card yellow">
<div class="summary-title">Overdue PM</div>
<div class="summary-value"><?= $overdueSchedules; ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Total Cost</div>
<div class="summary-value">KES <?= number_format($totalCost); ?></div>
</div>

</div>

<div class="top-action-bar">

<button class="green-btn" onclick="openModal('scheduleModal')">
+ Preventive Schedule
</button>

<button class="primary-btn" onclick="openModal('workOrderModal')">
+ New Work Order
</button>

</div>

<div class="tab-bar">

<button class="tab-btn active" onclick="openTab(event,'scheduleTab')">
Preventive Schedule
</button>

<button class="tab-btn" onclick="openTab(event,'workOrdersTab')">
Work Orders
</button>

<button class="tab-btn" onclick="openTab(event,'historyTab')">
Maintenance History
</button>

<button class="tab-btn" onclick="openTab(event,'reportsTab')">
Reports
</button>

</div>

<!-- WORK ORDERS -->

<div id="workOrdersTab" class="tab-section">

<div class="card">

<table id="workOrdersTable" class="display">

<thead>
<tr>
<th>Asset</th>
<th>Maintenance</th>
<th>Cost</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($wo = $workOrders->fetch_assoc()): ?>

<tr>

<td>
<div class="detail-main"><?= htmlspecialchars($wo['asset_name']); ?></div>
<div class="detail-sub">Serial: <?= htmlspecialchars($wo['serial_number']); ?></div>
<div class="detail-sub">Type: <?= htmlspecialchars($wo['asset_type']); ?></div>
<div class="detail-sub">Location: <?= htmlspecialchars($wo['location']); ?></div>
</td>

<td>
<div class="detail-main"><?= htmlspecialchars($wo['maintenance_type']); ?></div>
<div class="detail-sub">Priority: <?= htmlspecialchars($wo['priority']); ?></div>
<div class="detail-sub">Assigned: <?= htmlspecialchars($wo['assigned_to']); ?></div>
<div class="detail-sub">Due: <?= htmlspecialchars($wo['expected_completion_date']); ?></div>
</td>

<td>
<div class="detail-main">KES <?= number_format($wo['actual_cost']); ?></div>
<div class="detail-sub">Est: KES <?= number_format($wo['estimated_cost']); ?></div>
<div class="detail-sub">Downtime: <?= htmlspecialchars($wo['downtime_hours']); ?> hrs</div>
</td>

<td>

<?php
$statusClass = 'status-badge';

if($wo['status'] === 'Pending') $statusClass .= ' status-pending';
if($wo['status'] === 'In Progress') $statusClass .= ' status-progress';
if($wo['status'] === 'Completed') $statusClass .= ' status-completed';
if($wo['priority'] === 'Critical') $statusClass .= ' status-critical';
?>

<span class="<?= $statusClass; ?>">
<?= htmlspecialchars($wo['status']); ?>
</span>

</td>

<td>

<div class="action-wrapper">

<button class="action-btn" onclick="toggleMenu(this)">⋮</button>

<div class="action-menu">

<button type="button" onclick='openEditWorkOrder(<?= json_encode($wo); ?>)'>
✏ Update Work Order
</button>

<button type="button" onclick="openAddPartModal(<?= $wo['id']; ?>)">
➕ Add Part
</button>

</div>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- SCHEDULE -->

<div id="scheduleTab" class="tab-section active">

<div class="card">

<table id="scheduleTable" class="display">

<thead>
<tr>
<th>Asset</th>
<th>Maintenance Plan</th>
<th>Frequency</th>
<th>Service Dates</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($sc = $schedules->fetch_assoc()): ?>

<tr>

<td>
<div class="detail-main"><?= htmlspecialchars($sc['asset_name']); ?></div>
<div class="detail-sub">Serial: <?= htmlspecialchars($sc['serial_number']); ?></div>
<div class="detail-sub">Assigned: <?= htmlspecialchars($sc['assigned_to']); ?></div>
</td>

<td>
<div class="detail-main"><?= htmlspecialchars($sc['maintenance_type'] ?? 'Preventive'); ?></div>
<div class="detail-sub">Priority: <?= htmlspecialchars($sc['priority'] ?? 'Medium'); ?></div>
<div class="detail-sub">Est: KES <?= number_format((float)($sc['estimated_cost'] ?? 0)); ?></div>
</td>

<td>
<?= htmlspecialchars($sc['frequency']); ?>
</td>

<td>
<div class="detail-sub">Last: <?= htmlspecialchars($sc['last_service_date']); ?></div>
<div class="detail-sub">Next: <?= htmlspecialchars($sc['next_service_date']); ?></div>
</td>

<td>
<span class="status-badge">
<?= htmlspecialchars($sc['status']); ?>
</span>
</td>

<td>

<div class="action-wrapper">

<button class="action-btn" onclick="toggleMenu(this)">...</button>

<div class="action-menu">

<button type="button" onclick='openEditSchedule(<?= htmlspecialchars(json_encode($sc), ENT_QUOTES, "UTF-8"); ?>)'>
Edit Schedule
</button>

<form method="POST" onsubmit="return confirm('Create a work order from this preventive schedule?');">
<input type="hidden" name="schedule_id" value="<?= (int)$sc['id']; ?>">
<button type="submit" name="generate_work_order">
Create Work Order
</button>
</form>

</div>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- HISTORY -->

<div id="historyTab" class="tab-section">

<div class="card">

<table id="historyTable" class="display">

<thead>
<tr>
<th>Asset</th>
<th>History</th>
<th>Resolution</th>
<th>Cost</th>
</tr>
</thead>

<tbody>

<?php
$history = $conn->query("
    SELECT *
    FROM asset_maintenance
    WHERE status IN ('Completed','Cancelled')
    ORDER BY asset_name ASC, date_reported DESC
");
?>

<?php while($h = $history->fetch_assoc()): ?>

<tr>

<td>
<div class="detail-main"><?= htmlspecialchars($h['asset_name']); ?></div>
<div class="detail-sub"><?= htmlspecialchars($h['serial_number']); ?></div>
</td>

<td>
<div class="detail-main"><?= htmlspecialchars($h['maintenance_type']); ?></div>
<div class="detail-sub"><?= htmlspecialchars($h['date_reported']); ?> → <?= htmlspecialchars($h['actual_completion_date']); ?></div>
<div class="detail-sub">Status: <?= htmlspecialchars($h['status']); ?></div>
</td>

<td>
<?= htmlspecialchars($h['resolution_notes']); ?>
</td>

<td>
KES <?= number_format($h['actual_cost']); ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- REPORTS -->

<div id="reportsTab" class="tab-section">

<div class="card">

<div class="report-grid">

<div class="report-box">
<h3>Maintenance Cost Summary</h3>
<p>Total Actual Cost: <strong>KES <?= number_format($totalCost); ?></strong></p>
<p>Completed Jobs: <strong><?= $completedOrders; ?></strong></p>
<p>Pending Jobs: <strong><?= $pendingOrders; ?></strong></p>
</div>

<div class="report-box">
<h3>Operational Risk</h3>
<p>Overdue Work Orders: <strong><?= $overdueOrders; ?></strong></p>
<p>Overdue Preventive Maintenance: <strong><?= $overdueSchedules; ?></strong></p>
<p>Assets Under Maintenance: <strong><?= $inProgressOrders; ?></strong></p>
</div>

</div>

<br>

<table id="costReportTable" class="display">

<thead>
<tr>
<th>Asset</th>
<th>Total Maintenance Cost</th>
<th>Total Jobs</th>
<th>Total Downtime</th>
</tr>
</thead>

<tbody>

<?php
$costReport = $conn->query("
    SELECT
        asset_name,
        SUM(actual_cost) total_cost,
        COUNT(*) total_jobs,
        SUM(downtime_hours) downtime
    FROM asset_maintenance
    GROUP BY asset_name
    ORDER BY total_cost DESC
");
?>

<?php while($cr = $costReport->fetch_assoc()): ?>

<tr>
<td><?= htmlspecialchars($cr['asset_name']); ?></td>
<td>KES <?= number_format($cr['total_cost']); ?></td>
<td><?= $cr['total_jobs']; ?></td>
<td><?= $cr['downtime']; ?> hrs</td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<!-- NEW WORK ORDER MODAL -->

<div id="workOrderModal" class="modal">

<div class="modal-content">

<div class="modal-header">
<h3>New Maintenance Work Order</h3>
<button class="close-modal" onclick="closeModal('workOrderModal')">×</button>
</div>

<div class="modal-body">

<form method="POST">

<input type="hidden" name="source_schedule_id" id="source_schedule_id" value="0">

<div class="form-grid">

<div class="form-group">
<label>Select Asset</label>
<select name="asset_id" id="asset_id" onchange="autofillAsset()" required>
<option value="">-- Select Asset --</option>

<?php foreach($assets as $asset): ?>

<option
value="<?= $asset['id']; ?>"
data-name="<?= htmlspecialchars($asset['asset_name'] ?? ''); ?>"
data-serial="<?= htmlspecialchars($asset['serial_number'] ?? ''); ?>"
data-model="<?= htmlspecialchars($asset['model'] ?? ''); ?>"
data-type="<?= htmlspecialchars($asset['asset_type'] ?? ''); ?>"
data-location="<?= htmlspecialchars($asset['location'] ?? ''); ?>">
<?= htmlspecialchars($asset['asset_name']); ?> - <?= htmlspecialchars($asset['serial_number']); ?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="form-group">
<label>Asset Details</label>
<input type="text" id="asset_preview" readonly>
</div>

<div class="form-group">
<label>Maintenance Type</label>
<select name="maintenance_type" required>
<option value="Preventive">Preventive</option>
<option value="Corrective">Corrective</option>
<option value="Emergency">Emergency</option>
<option value="Inspection">Inspection</option>
</select>
</div>

<div class="form-group">
<label>Priority</label>
<select name="priority" required>
<option value="Low">Low</option>
<option value="Medium">Medium</option>
<option value="High">High</option>
<option value="Critical">Critical</option>
</select>
</div>

<div class="form-group">
<label>Reported By</label>
<input type="text" name="reported_by">
</div>

<div class="form-group">
<label>Assigned To</label>
<input type="text" name="assigned_to">
</div>

<div class="form-group">
<label>Vendor Name</label>
<input type="text" name="vendor_name">
</div>

<div class="form-group">
<label>Date Reported</label>
<input type="date" name="date_reported" value="<?= date('Y-m-d'); ?>">
</div>

<div class="form-group">
<label>Expected Completion Date</label>
<input type="date" name="expected_completion_date">
</div>

<div class="form-group">
<label>Estimated Cost</label>
<input type="number" step="0.01" name="estimated_cost">
</div>

<div class="form-group">
<label>Status</label>
<select name="status">
<option value="Pending">Pending</option>
<option value="Approved">Approved</option>
<option value="In Progress">In Progress</option>
</select>
</div>

<div class="form-group full">
<label>Issue Description</label>
<textarea name="issue_description" required></textarea>
</div>

<div class="form-group full">
<button type="submit" name="create_work_order" class="submit-btn">
Create Work Order
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- EDIT WORK ORDER MODAL -->

<div id="editWorkOrderModal" class="modal">

<div class="modal-content">

<div class="modal-header">
<h3>Update Work Order</h3>
<button class="close-modal" onclick="closeModal('editWorkOrderModal')">×</button>
</div>

<div class="modal-body">

<form method="POST">

<input type="hidden" name="maintenance_id" id="edit_maintenance_id">

<div class="form-grid">

<div class="form-group">
<label>Maintenance Type</label>
<select name="maintenance_type" id="edit_maintenance_type">
<option value="Preventive">Preventive</option>
<option value="Corrective">Corrective</option>
<option value="Emergency">Emergency</option>
<option value="Inspection">Inspection</option>
</select>
</div>

<div class="form-group">
<label>Priority</label>
<select name="priority" id="edit_priority">
<option value="Low">Low</option>
<option value="Medium">Medium</option>
<option value="High">High</option>
<option value="Critical">Critical</option>
</select>
</div>

<div class="form-group">
<label>Assigned To</label>
<input type="text" name="assigned_to" id="edit_assigned_to">
</div>

<div class="form-group">
<label>Vendor Name</label>
<input type="text" name="vendor_name" id="edit_vendor_name">
</div>

<div class="form-group">
<label>Expected Completion</label>
<input type="date" name="expected_completion_date" id="edit_expected_completion_date">
</div>

<div class="form-group">
<label>Actual Completion</label>
<input type="date" name="actual_completion_date" id="edit_actual_completion_date">
</div>

<div class="form-group">
<label>Estimated Cost</label>
<input type="number" step="0.01" name="estimated_cost" id="edit_estimated_cost">
</div>

<div class="form-group">
<label>Parts Cost</label>
<input type="number" step="0.01" name="parts_cost" id="edit_parts_cost">
</div>

<div class="form-group">
<label>Labour Cost</label>
<input type="number" step="0.01" name="labour_cost" id="edit_labour_cost">
</div>

<div class="form-group">
<label>Vendor Cost</label>
<input type="number" step="0.01" name="vendor_cost" id="edit_vendor_cost">
</div>

<div class="form-group">
<label>Downtime Hours</label>
<input type="number" step="0.01" name="downtime_hours" id="edit_downtime_hours">
</div>

<div class="form-group">
<label>Status</label>
<select name="status" id="edit_status">
<option value="Pending">Pending</option>
<option value="Approved">Approved</option>
<option value="In Progress">In Progress</option>
<option value="Completed">Completed</option>
<option value="Cancelled">Cancelled</option>
</select>
</div>

<div class="form-group full">
<label>Issue Description</label>
<textarea name="issue_description" id="edit_issue_description"></textarea>
</div>

<div class="form-group full">
<label>Resolution Notes</label>
<textarea name="resolution_notes" id="edit_resolution_notes"></textarea>
</div>

<div class="form-group full">
<button type="submit" name="update_work_order" class="submit-btn">
Update Work Order
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- ADD PART MODAL -->

<div id="partModal" class="modal">

<div class="modal-content">

<div class="modal-header">
<h3>Add Spare Part</h3>
<button class="close-modal" onclick="closeModal('partModal')">×</button>
</div>

<div class="modal-body">

<form method="POST">

<input type="hidden" name="maintenance_id" id="part_maintenance_id">

<div class="form-grid">

<div class="form-group">
<label>Part Name</label>
<input type="text" name="part_name" required>
</div>

<div class="form-group">
<label>Quantity</label>
<input type="number" name="quantity" value="1" required>
</div>

<div class="form-group">
<label>Unit Cost</label>
<input type="number" step="0.01" name="unit_cost" required>
</div>

<div class="form-group full">
<button type="submit" name="add_part" class="submit-btn">
Add Part
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- SCHEDULE MODAL -->

<div id="scheduleModal" class="modal">

<div class="modal-content">

<div class="modal-header">
<h3>Create Preventive Maintenance Schedule</h3>
<button class="close-modal" onclick="closeModal('scheduleModal')">×</button>
</div>

<div class="modal-body">

<form method="POST">

<div class="form-grid">

<div class="form-group">
<label>Select Asset</label>
<select name="schedule_asset_id" required>
<option value="">-- Select Asset --</option>

<?php foreach($assets as $asset): ?>

<option value="<?= $asset['id']; ?>">
<?= htmlspecialchars($asset['asset_name']); ?> - <?= htmlspecialchars($asset['serial_number']); ?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="form-group">
<label>Maintenance Type</label>
<select name="schedule_maintenance_type" required>
<option value="Preventive">Preventive</option>
<option value="Inspection">Inspection</option>
<option value="Calibration">Calibration</option>
<option value="Servicing">Servicing</option>
</select>
</div>

<div class="form-group">
<label>Priority</label>
<select name="schedule_priority" required>
<option value="Low">Low</option>
<option value="Medium">Medium</option>
<option value="High">High</option>
<option value="Critical">Critical</option>
</select>
</div>

<div class="form-group">
<label>Frequency</label>
<select name="frequency" required>
<option value="Weekly">Weekly</option>
<option value="Monthly">Monthly</option>
<option value="Quarterly">Quarterly</option>
<option value="Bi-Annually">Bi-Annually</option>
<option value="Yearly">Yearly</option>
</select>
</div>

<div class="form-group">
<label>Last Service Date</label>
<input type="date" name="last_service_date">
</div>

<div class="form-group">
<label>Next Service Date</label>
<input type="date" name="next_service_date" required>
</div>

<div class="form-group">
<label>Assigned To</label>
<input type="text" name="schedule_assigned_to">
</div>

<div class="form-group">
<label>Estimated Cost</label>
<input type="number" step="0.01" name="schedule_estimated_cost">
</div>

<div class="form-group">
<label>Status</label>
<select name="schedule_status">
<option value="Active">Active</option>
<option value="Paused">Paused</option>
<option value="Closed">Closed</option>
</select>
</div>

<div class="form-group full">
<label>Work Scope</label>
<textarea name="schedule_work_scope" placeholder="Describe the preventive work, inspection checks, source changes, calibration or service activity."></textarea>
</div>

<div class="form-group full">
<label>Notes</label>
<textarea name="schedule_notes"></textarea>
</div>

<div class="form-group full">
<button type="submit" name="create_schedule" class="submit-btn">
Create Schedule
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<!-- EDIT SCHEDULE MODAL -->

<div id="editScheduleModal" class="modal">

<div class="modal-content">

<div class="modal-header">
<h3>Edit Preventive Maintenance Schedule</h3>
<button class="close-modal" onclick="closeModal('editScheduleModal')">×</button>
</div>

<div class="modal-body">

<form method="POST">

<input type="hidden" name="schedule_id" id="edit_schedule_id">

<div class="form-grid">

<div class="form-group">
<label>Maintenance Type</label>
<select name="schedule_maintenance_type" id="edit_schedule_maintenance_type">
<option value="Preventive">Preventive</option>
<option value="Inspection">Inspection</option>
<option value="Calibration">Calibration</option>
<option value="Servicing">Servicing</option>
</select>
</div>

<div class="form-group">
<label>Priority</label>
<select name="schedule_priority" id="edit_schedule_priority">
<option value="Low">Low</option>
<option value="Medium">Medium</option>
<option value="High">High</option>
<option value="Critical">Critical</option>
</select>
</div>

<div class="form-group">
<label>Frequency</label>
<select name="frequency" id="edit_schedule_frequency">
<option value="Weekly">Weekly</option>
<option value="Monthly">Monthly</option>
<option value="Quarterly">Quarterly</option>
<option value="Bi-Annually">Bi-Annually</option>
<option value="Yearly">Yearly</option>
</select>
</div>

<div class="form-group">
<label>Last Service Date</label>
<input type="date" name="last_service_date" id="edit_schedule_last_service_date">
</div>

<div class="form-group">
<label>Next Service Date</label>
<input type="date" name="next_service_date" id="edit_schedule_next_service_date" required>
</div>

<div class="form-group">
<label>Assigned To</label>
<input type="text" name="schedule_assigned_to" id="edit_schedule_assigned_to">
</div>

<div class="form-group">
<label>Estimated Cost</label>
<input type="number" step="0.01" name="schedule_estimated_cost" id="edit_schedule_estimated_cost">
</div>

<div class="form-group">
<label>Status</label>
<select name="schedule_status" id="edit_schedule_status">
<option value="Active">Active</option>
<option value="Paused">Paused</option>
<option value="Closed">Closed</option>
</select>
</div>

<div class="form-group full">
<label>Work Scope</label>
<textarea name="schedule_work_scope" id="edit_schedule_work_scope"></textarea>
</div>

<div class="form-group full">
<label>Notes</label>
<textarea name="schedule_notes" id="edit_schedule_notes"></textarea>
</div>

<div class="form-group full">
<button type="submit" name="update_schedule" class="submit-btn">
Save Schedule Changes
</button>
</div>

</div>

</form>

</div>

</div>

</div>

<script>

$(document).ready(function(){

    $('#workOrdersTable').DataTable();
    $('#scheduleTable').DataTable();
    $('#historyTable').DataTable();
    $('#costReportTable').DataTable();

});

function openTab(evt, tabId){

    document.querySelectorAll('.tab-section').forEach(section => {
        section.classList.remove('active');
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    document.getElementById(tabId).classList.add('active');

    evt.currentTarget.classList.add('active');
}

function openModal(id){

    document.getElementById(id).style.display = 'flex';
}

function closeModal(id){

    document.getElementById(id).style.display = 'none';
}

function toggleMenu(button){

    const menu = button.nextElementSibling;

    document.querySelectorAll('.action-menu').forEach(item => {

        if(item !== menu){

            item.style.display = 'none';
        }
    });

    menu.style.display =
    menu.style.display === 'block'
    ? 'none'
    : 'block';
}

function autofillAsset(){

    const select = document.getElementById('asset_id');
    const selected = select.options[select.selectedIndex];

    document.getElementById('asset_preview').value =
    (selected.dataset.name || '') +
    ' | ' +
    (selected.dataset.serial || '') +
    ' | ' +
    (selected.dataset.location || '');
}

function openAddPartModal(id){

    document.getElementById('part_maintenance_id').value = id;

    openModal('partModal');
}

function setSelectValue(id, value){

    const select = document.getElementById(id);

    if(!select){

        return;
    }

    const stringValue = value || '';

    let exists = false;

    Array.from(select.options).forEach(option => {

        if(option.value === stringValue){

            exists = true;
        }
    });

    if(!exists && stringValue !== ''){

        const option = document.createElement('option');

        option.value = stringValue;
        option.textContent = stringValue;

        select.appendChild(option);
    }

    select.value = stringValue;
}

function openEditWorkOrder(data){

    document.getElementById('edit_maintenance_id').value = data.id || '';

    setSelectValue('edit_maintenance_type', data.maintenance_type || '');
    setSelectValue('edit_priority', data.priority || '');
    setSelectValue('edit_status', data.status || '');

    document.getElementById('edit_assigned_to').value = data.assigned_to || '';
    document.getElementById('edit_vendor_name').value = data.vendor_name || '';
    document.getElementById('edit_expected_completion_date').value = data.expected_completion_date || '';
    document.getElementById('edit_actual_completion_date').value = data.actual_completion_date || '';
    document.getElementById('edit_estimated_cost').value = data.estimated_cost || 0;
    document.getElementById('edit_parts_cost').value = data.parts_cost || 0;
    document.getElementById('edit_labour_cost').value = data.labour_cost || 0;
    document.getElementById('edit_vendor_cost').value = data.vendor_cost || 0;
    document.getElementById('edit_downtime_hours').value = data.downtime_hours || 0;
    document.getElementById('edit_issue_description').value = data.issue_description || '';
    document.getElementById('edit_resolution_notes').value = data.resolution_notes || '';

    openModal('editWorkOrderModal');
}

function openEditSchedule(data){

    document.getElementById('edit_schedule_id').value = data.id || '';

    setSelectValue('edit_schedule_maintenance_type', data.maintenance_type || 'Preventive');
    setSelectValue('edit_schedule_priority', data.priority || 'Medium');
    setSelectValue('edit_schedule_frequency', data.frequency || '');
    setSelectValue('edit_schedule_status', data.status || 'Active');

    document.getElementById('edit_schedule_last_service_date').value = data.last_service_date || '';
    document.getElementById('edit_schedule_next_service_date').value = data.next_service_date || '';
    document.getElementById('edit_schedule_assigned_to').value = data.assigned_to || '';
    document.getElementById('edit_schedule_estimated_cost').value = data.estimated_cost || 0;
    document.getElementById('edit_schedule_work_scope').value = data.work_scope || '';
    document.getElementById('edit_schedule_notes').value = data.notes || '';

    openModal('editScheduleModal');
}

window.onclick = function(e){

    if(e.target.classList.contains('modal')){

        e.target.style.display = 'none';
    }
}

</script>

</body>
</html>
