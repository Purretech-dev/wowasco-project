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

function countData($conn, $table, $condition = "1=1") {
    if (!tableExists($conn, $table)) return 0;
    $res = $conn->query("SELECT COUNT(*) AS c FROM `$table` WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

/* ================= REQUIRED TABLES ================= */

$conn->query("
CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(150) NOT NULL UNIQUE,
    zone_code VARCHAR(100) NULL,
    source_name VARCHAR(150) NULL,
    officer_in_charge VARCHAR(150) NULL,
    status VARCHAR(50) DEFAULT 'Active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS water_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(150) NOT NULL UNIQUE,
    source_type VARCHAR(100) NULL,
    location VARCHAR(150) NULL,
    status VARCHAR(50) DEFAULT 'Active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS zone_supply_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    source_id INT NOT NULL,
    supply_day VARCHAR(30) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS zone_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    issue_title VARCHAR(200) NOT NULL,
    issue_description TEXT NULL,
    maintenance_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Open',
    assigned_team VARCHAR(150) NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS zone_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(150) NOT NULL,
    description TEXT NULL,
    staff_name VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS water_rationing_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone VARCHAR(150) NOT NULL,
    rationing_day VARCHAR(30) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    notice TEXT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/* ================= MESSAGE ================= */

$message = "";

/* ================= LOG ================= */

function logZoneAction($conn, $action, $description, $staff = 'Zone Manager') {
    if (!tableExists($conn, 'zone_activity_log')) return;

    $stmt = $conn->prepare("
        INSERT INTO zone_activity_log (action_type, description, staff_name)
        VALUES (?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("sss", $action, $description, $staff);
        $stmt->execute();
    }
}

/* ================= ACTIONS ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_action'])) {

    $action = $_POST['zone_action'];
    $staff = trim($_POST['staff_name'] ?? 'Zone Manager');

    if ($action === 'save_zone') {
        $zone_name = trim($_POST['zone_name'] ?? '');
        $zone_code = trim($_POST['zone_code'] ?? '');
        $source_name = trim($_POST['source_name'] ?? '');
        $officer = trim($_POST['officer_in_charge'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO zones (zone_name, zone_code, source_name, officer_in_charge, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                zone_code=VALUES(zone_code),
                source_name=VALUES(source_name),
                officer_in_charge=VALUES(officer_in_charge),
                status=VALUES(status),
                notes=VALUES(notes)
        ");

        $stmt->bind_param("ssssss", $zone_name, $zone_code, $source_name, $officer, $status, $notes);

        if ($stmt->execute()) {
            logZoneAction($conn, "Zone Saved", "Zone saved or updated: $zone_name", $staff);
            $message = "<div class='alert green'><strong>Success.</strong><br>Zone saved successfully.</div>";
        }
    }

    if ($action === 'save_source') {
        $source_name = trim($_POST['source_name'] ?? '');
        $source_type = trim($_POST['source_type'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO water_sources (source_name, source_type, location, status, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                source_type=VALUES(source_type),
                location=VALUES(location),
                status=VALUES(status),
                notes=VALUES(notes)
        ");

        $stmt->bind_param("sssss", $source_name, $source_type, $location, $status, $notes);

        if ($stmt->execute()) {
            logZoneAction($conn, "Source Saved", "Water source saved or updated: $source_name", $staff);
            $message = "<div class='alert green'><strong>Success.</strong><br>Water source saved successfully.</div>";
        }
    }

    if ($action === 'save_schedule') {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $source_id = (int)($_POST['source_id'] ?? 0);
        $day = trim($_POST['supply_day'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO zone_supply_schedule
            (zone_id, source_id, supply_day, start_time, end_time, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("iisssss", $zone_id, $source_id, $day, $start, $end, $status, $notes);

        if ($stmt->execute()) {
            $z = $conn->query("SELECT zone_name FROM zones WHERE id=$zone_id")->fetch_assoc();
            $s = $conn->query("SELECT source_name FROM water_sources WHERE id=$source_id")->fetch_assoc();

            $zoneName = $z['zone_name'] ?? '';
            $sourceName = $s['source_name'] ?? '';

            $notice = "Water supply scheduled from $sourceName.";

            $portal = $conn->prepare("
                INSERT INTO water_rationing_schedule
                (zone, rationing_day, start_time, end_time, notice, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $portal->bind_param("ssssss", $zoneName, $day, $start, $end, $notice, $status);
            $portal->execute();

            logZoneAction($conn, "Schedule Created", "Supply schedule created for $zoneName on $day", $staff);
            $message = "<div class='alert green'><strong>Published.</strong><br>Supply schedule saved and published to customer portal.</div>";
        }
    }

    if ($action === 'save_maintenance') {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $issue_title = trim($_POST['issue_title'] ?? '');
        $issue_description = trim($_POST['issue_description'] ?? '');
        $maintenance_date = trim($_POST['maintenance_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Open');
        $assigned_team = trim($_POST['assigned_team'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO zone_maintenance
            (zone_id, issue_title, issue_description, maintenance_date, status, assigned_team)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("isssss", $zone_id, $issue_title, $issue_description, $maintenance_date, $status, $assigned_team);

        if ($stmt->execute()) {
            $z = $conn->query("SELECT zone_name FROM zones WHERE id=$zone_id")->fetch_assoc();
            $zoneName = $z['zone_name'] ?? '';

            $notice = "Maintenance notice: $issue_title. $issue_description";

            $portal = $conn->prepare("
                INSERT INTO water_rationing_schedule
                (zone, rationing_day, start_time, end_time, notice, status)
                VALUES (?, DAYNAME(?), '00:00:00', '23:59:00', ?, 'Active')
            ");

            $portal->bind_param("sss", $zoneName, $maintenance_date, $notice);
            $portal->execute();

            logZoneAction($conn, "Maintenance Created", "Maintenance created for $zoneName: $issue_title", $staff);
            $message = "<div class='alert green'><strong>Published.</strong><br>Maintenance notice saved and published to customer portal.</div>";
        }
    }

    if ($action === 'update_zone_status') {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Active');

        $stmt = $conn->prepare("UPDATE zones SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $zone_id);

        if ($stmt->execute()) {
            logZoneAction($conn, "Zone Status Updated", "Zone ID $zone_id updated to $status", $staff);
            $message = "<div class='alert green'><strong>Updated.</strong><br>Zone status updated successfully.</div>";
        }
    }

    if ($action === 'update_maintenance') {
        $maintenance_id = (int)($_POST['maintenance_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Open');
        $resolution_notes = trim($_POST['resolution_notes'] ?? '');

        $stmt = $conn->prepare("
            UPDATE zone_maintenance
            SET status=?, resolution_notes=?
            WHERE id=?
        ");

        $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);

        if ($stmt->execute()) {
            logZoneAction($conn, "Maintenance Updated", "Maintenance ID $maintenance_id updated to $status", $staff);
            $message = "<div class='alert green'><strong>Updated.</strong><br>Maintenance status updated successfully.</div>";
        }
    }
}

/* ================= DATA ================= */

$zones = $conn->query("SELECT * FROM zones ORDER BY zone_name ASC");
$sources = $conn->query("SELECT * FROM water_sources ORDER BY source_name ASC");

$zonesForForms = $conn->query("SELECT * FROM zones ORDER BY zone_name ASC");
$sourcesForForms = $conn->query("SELECT * FROM water_sources ORDER BY source_name ASC");

$schedules = $conn->query("
    SELECT zss.*, z.zone_name, ws.source_name
    FROM zone_supply_schedule zss
    LEFT JOIN zones z ON z.id = zss.zone_id
    LEFT JOIN water_sources ws ON ws.id = zss.source_id
    ORDER BY FIELD(zss.supply_day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), z.zone_name ASC
");

$maintenance = $conn->query("
    SELECT zm.*, z.zone_name
    FROM zone_maintenance zm
    LEFT JOIN zones z ON z.id = zm.zone_id
    ORDER BY zm.id DESC
");

$logs = $conn->query("SELECT * FROM zone_activity_log ORDER BY id DESC LIMIT 30");

/* ================= COUNTS ================= */

$totalZones = countData($conn, 'zones');
$activeZones = countData($conn, 'zones', "status='Active'");
$totalSources = countData($conn, 'water_sources');
$activeSources = countData($conn, 'water_sources', "status='Active'");
$activeMaintenance = countData($conn, 'zone_maintenance', "status IN ('Open','In Progress')");
$totalSchedules = countData($conn, 'zone_supply_schedule');
$activeSchedules = countData($conn, 'zone_supply_schedule', "status='Active'");

$zoneRiskRate = $totalZones > 0 ? round(($activeMaintenance / $totalZones) * 100) : 0;

/* ================= METER COUNTS BY ZONE ================= */

$meterCounts = [];

if (tableExists($conn, 'meters')) {
    $meterRes = $conn->query("
        SELECT zone, COUNT(*) AS total_meters
        FROM meters
        GROUP BY zone
    ");

    if ($meterRes) {
        while ($m = $meterRes->fetch_assoc()) {
            $meterCounts[strtolower(trim($m['zone']))] = $m['total_meters'];
        }
    }
}
?>

<div class="container">

    <div class="page-header">
        <div>
            <h2>Zone Management Center</h2>
            <p>Manage water zones, sources, supply schedules, maintenance interruptions and customer-facing rationing information.</p>
        </div>

        <div class="header-badge">
            Zone Risk: <?= number_format($zoneRiskRate) ?>%
        </div>
    </div>

    <?= $message ?>

    <div class="kpis">

        <div class="kpi blue">
            <h3><?= number_format($totalZones) ?></h3>
            <p>Total Zones</p>
            <small>All service zones registered</small>
        </div>

        <div class="kpi">
            <h3><?= number_format($activeZones) ?></h3>
            <p>Active Zones</p>
            <small>Currently operational service zones</small>
        </div>

        <div class="kpi yellow">
            <h3><?= number_format($totalSources) ?></h3>
            <p>Water Sources</p>
            <small>Boreholes, intakes and supply points</small>
        </div>

        <div class="kpi red">
            <h3><?= number_format($activeMaintenance) ?></h3>
            <p>Active Maintenance</p>
            <small>Open or ongoing interruptions</small>
        </div>

    </div>

    <div class="filters zone-tabs">
        <button type="button" class="tab-btn active" onclick="openZoneTab(event,'zonesTab')">Zones</button>
        <button type="button" class="tab-btn" onclick="openZoneTab(event,'sourcesTab')">Water Sources</button>
        <button type="button" class="tab-btn" onclick="openZoneTab(event,'scheduleTab')">Supply Schedule</button>
        <button type="button" class="tab-btn" onclick="openZoneTab(event,'maintenanceTab')">Maintenance</button>
        <button type="button" class="tab-btn" onclick="openZoneTab(event,'calendarTab')">Weekly Calendar</button>
        <button type="button" class="tab-btn" onclick="openZoneTab(event,'auditTab')">Audit Trail</button>
    </div>

    <div class="grid">

        <div class="panel">

            <div id="zonesTab" class="tab-section active">

                <h3 class="section-title">Zone Register</h3>

                <div class="insight-box">
                    Create and manage service zones, assigned officers, default water sources and operational status.
                </div>

                <form method="POST" class="form-card">
                    <input type="hidden" name="zone_action" value="save_zone">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone Name</label>
                            <input type="text" name="zone_name" required>
                        </div>

                        <div class="form-group">
                            <label>Zone Code</label>
                            <input type="text" name="zone_code">
                        </div>

                        <div class="form-group">
                            <label>Default Source</label>
                            <input type="text" name="source_name" placeholder="Example: Kaiti Source">
                        </div>

                        <div class="form-group">
                            <label>Officer In Charge</label>
                            <input type="text" name="officer_in_charge">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option>Active</option>
                                <option>Inactive</option>
                                <option>Under Maintenance</option>
                                <option>Suspended</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Staff Name</label>
                            <input type="text" name="staff_name" placeholder="Optional">
                        </div>

                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>

                    <button class="btn">Save Zone</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Zone</th>
                                <th>Source</th>
                                <th>Officer</th>
                                <th>Meters</th>
                                <th>Status</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($zones && $zones->num_rows > 0): ?>
                                <?php while ($z = $zones->fetch_assoc()): ?>
                                    <?php
                                        $key = strtolower(trim($z['zone_name']));
                                        $meterTotal = $meterCounts[$key] ?? 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= clean($z['zone_name']) ?></strong><br>
                                            <small><?= clean($z['zone_code']) ?></small>
                                        </td>
                                        <td><?= clean($z['source_name']) ?></td>
                                        <td><?= clean($z['officer_in_charge']) ?></td>
                                        <td><strong><?= clean($meterTotal) ?></strong></td>
                                        <td><span class="badge good"><?= clean($z['status']) ?></span></td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="zone_action" value="update_zone_status">
                                                <input type="hidden" name="zone_id" value="<?= (int)$z['id'] ?>">

                                                <select name="status">
                                                    <option <?= $z['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                                    <option <?= $z['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    <option <?= $z['status'] === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                                    <option <?= $z['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                                </select>

                                                <button class="expand-btn">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No zones found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div id="sourcesTab" class="tab-section">

                <h3 class="section-title">Water Sources</h3>

                <div class="insight-box">
                    Register and manage water sources supplying different zones.
                </div>

                <form method="POST" class="form-card">
                    <input type="hidden" name="zone_action" value="save_source">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Source Name</label>
                            <input type="text" name="source_name" required>
                        </div>

                        <div class="form-group">
                            <label>Source Type</label>
                            <select name="source_type">
                                <option>Borehole</option>
                                <option>River Intake</option>
                                <option>Treatment Plant</option>
                                <option>Storage Tank</option>
                                <option>Mixed Source</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option>Active</option>
                                <option>Inactive</option>
                                <option>Under Maintenance</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Staff Name</label>
                            <input type="text" name="staff_name">
                        </div>

                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>

                    <button class="btn">Save Source</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($sources && $sources->num_rows > 0): ?>
                                <?php while ($s = $sources->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= clean($s['source_name']) ?></strong></td>
                                        <td><?= clean($s['source_type']) ?></td>
                                        <td><?= clean($s['location']) ?></td>
                                        <td><span class="badge good"><?= clean($s['status']) ?></span></td>
                                        <td><?= clean($s['created_at']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No water sources found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div id="scheduleTab" class="tab-section">

                <h3 class="section-title">Supply Schedule</h3>

                <div class="insight-box">
                    Create rationing or supply schedules and publish them automatically to the customer portal.
                </div>

                <form method="POST" class="form-card">
                    <input type="hidden" name="zone_action" value="save_schedule">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone</label>
                            <select name="zone_id" required>
                                <option value="">Select Zone</option>
                                <?php if ($zonesForForms): ?>
                                    <?php while ($zf = $zonesForForms->fetch_assoc()): ?>
                                        <option value="<?= (int)$zf['id'] ?>"><?= clean($zf['zone_name']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Water Source</label>
                            <select name="source_id" required>
                                <option value="">Select Source</option>
                                <?php if ($sourcesForForms): ?>
                                    <?php while ($sf = $sourcesForForms->fetch_assoc()): ?>
                                        <option value="<?= (int)$sf['id'] ?>"><?= clean($sf['source_name']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Supply Day</label>
                            <select name="supply_day" required>
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

                        <div class="form-group">
                            <label>Staff Name</label>
                            <input type="text" name="staff_name">
                        </div>

                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes"></textarea>
                        </div>
                    </div>

                    <button class="btn">Save Schedule</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Zone</th>
                                <th>Source</th>
                                <th>Supply Time</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($schedules && $schedules->num_rows > 0): ?>
                                <?php while ($sc = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= clean($sc['supply_day']) ?></td>
                                        <td><?= clean($sc['zone_name']) ?></td>
                                        <td><?= clean($sc['source_name']) ?></td>
                                        <td><?= clean($sc['start_time']) ?> - <?= clean($sc['end_time']) ?></td>
                                        <td><span class="badge good"><?= clean($sc['status']) ?></span></td>
                                        <td><?= clean($sc['notes']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No schedules found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div id="maintenanceTab" class="tab-section">

                <h3 class="section-title">Zone Maintenance</h3>

                <div class="insight-box">
                    Record interruptions, pipe bursts, maintenance notices and operational disruptions.
                </div>

                <form method="POST" class="form-card">
                    <input type="hidden" name="zone_action" value="save_maintenance">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone</label>
                            <select name="zone_id" required>
                                <option value="">Select Zone</option>
                                <?php
                                $zonesForMaintenance = $conn->query("SELECT * FROM zones ORDER BY zone_name ASC");
                                while ($zmf = $zonesForMaintenance->fetch_assoc()):
                                ?>
                                    <option value="<?= (int)$zmf['id'] ?>"><?= clean($zmf['zone_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Issue Title</label>
                            <input type="text" name="issue_title" required>
                        </div>

                        <div class="form-group">
                            <label>Maintenance Date</label>
                            <input type="date" name="maintenance_date" required>
                        </div>

                        <div class="form-group">
                            <label>Assigned Team</label>
                            <input type="text" name="assigned_team">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option>Open</option>
                                <option>In Progress</option>
                                <option>Resolved</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Staff Name</label>
                            <input type="text" name="staff_name">
                        </div>

                        <div class="form-group full-width">
                            <label>Issue Description</label>
                            <textarea name="issue_description" required></textarea>
                        </div>
                    </div>

                    <button class="btn">Save Maintenance Notice</button>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Zone</th>
                                <th>Issue</th>
                                <th>Date</th>
                                <th>Team</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($maintenance && $maintenance->num_rows > 0): ?>
                                <?php while ($m = $maintenance->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= clean($m['zone_name']) ?></td>
                                        <td>
                                            <strong><?= clean($m['issue_title']) ?></strong><br>
                                            <small><?= clean($m['issue_description']) ?></small>
                                        </td>
                                        <td><?= clean($m['maintenance_date']) ?></td>
                                        <td><?= clean($m['assigned_team']) ?></td>
                                        <td><span class="badge warning"><?= clean($m['status']) ?></span></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="zone_action" value="update_maintenance">
                                                <input type="hidden" name="maintenance_id" value="<?= (int)$m['id'] ?>">

                                                <select name="status">
                                                    <option <?= $m['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                                    <option <?= $m['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option <?= $m['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                                </select>

                                                <textarea name="resolution_notes" placeholder="Resolution notes"><?= clean($m['resolution_notes']) ?></textarea>

                                                <button class="expand-btn">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No maintenance records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div id="calendarTab" class="tab-section">

                <h3 class="section-title">Weekly Zone Calendar</h3>

                <div class="insight-box">
                    Operational calendar showing active supply schedules by day.
                </div>

                <div class="calendar-grid">
                    <?php
                    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

                    foreach ($days as $day):
                        $daySafe = $conn->real_escape_string($day);
                        $daySchedules = $conn->query("
                            SELECT zss.*, z.zone_name, ws.source_name
                            FROM zone_supply_schedule zss
                            LEFT JOIN zones z ON z.id = zss.zone_id
                            LEFT JOIN water_sources ws ON ws.id = zss.source_id
                            WHERE zss.supply_day='$daySafe'
                            ORDER BY z.zone_name ASC
                        ");
                    ?>

                        <div class="calendar-day">
                            <h4><?= clean($day) ?></h4>

                            <?php if ($daySchedules && $daySchedules->num_rows > 0): ?>
                                <?php while ($ds = $daySchedules->fetch_assoc()): ?>
                                    <?php
                                        $maint = $conn->query("
                                            SELECT id FROM zone_maintenance 
                                            WHERE status IN ('Open','In Progress') 
                                            AND zone_id=" . (int)$ds['zone_id'] . "
                                            LIMIT 1
                                        ");
                                        $underMaintenance = $maint && $maint->num_rows > 0;
                                    ?>

                                    <div class="calendar-entry <?= $underMaintenance ? 'maintenance-entry' : '' ?>">
                                        <strong><?= clean($ds['source_name']) ?></strong>
                                        <span><?= clean($ds['zone_name']) ?></span>
                                        <small><?= clean($ds['start_time']) ?> - <?= clean($ds['end_time']) ?></small>

                                        <?php if ($underMaintenance): ?>
                                            <em>Maintenance</em>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-day">No schedule</div>
                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>
                </div>

            </div>

            <div id="auditTab" class="tab-section">

                <h3 class="section-title">Zone Activity Log</h3>

                <div class="insight-box">
                    Recent zone management actions and staff updates.
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Staff</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($logs && $logs->num_rows > 0): ?>
                                <?php while ($l = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= clean($l['created_at']) ?></td>
                                        <td><?= clean($l['action_type']) ?></td>
                                        <td><?= clean($l['description']) ?></td>
                                        <td><?= clean($l['staff_name']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No activity logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

        <div class="panel">

            <h3 class="section-title">Zone Operations Alerts</h3>

            <?php if($activeMaintenance > 0): ?>
                <div class="alert red">
                    <strong><?= number_format($activeMaintenance) ?> active maintenance case(s).</strong><br>
                    Review affected zones and update customer-facing notices.
                </div>
            <?php endif; ?>

            <?php if($activeSchedules > 0): ?>
                <div class="alert blue">
                    <strong><?= number_format($activeSchedules) ?> active supply schedule(s).</strong><br>
                    Ensure schedules are accurate and visible to customers.
                </div>
            <?php endif; ?>

            <?php if($activeSources > 0): ?>
                <div class="alert">
                    <strong><?= number_format($activeSources) ?> active water source(s).</strong><br>
                    Monitor source status and linked zone supply reliability.
                </div>
            <?php endif; ?>

            <?php if($activeZones == 0): ?>
                <div class="alert red">
                    <strong>No active zones detected.</strong><br>
                    Activate operational zones to improve system reporting.
                </div>
            <?php endif; ?>

            <?php if($activeMaintenance == 0 && $activeZones > 0): ?>
                <div class="alert green">
                    <strong>No major zone interruptions detected.</strong><br>
                    Current zone operations appear stable.
                </div>
            <?php endif; ?>

            <div class="insight-box">
                <strong>Executive Insight</strong><br><br>
                The system currently has
                <strong><?= number_format($totalZones) ?></strong> zone(s),
                <strong><?= number_format($activeZones) ?></strong> active zone(s),
                <strong><?= number_format($totalSources) ?></strong> water source(s), and
                <strong><?= number_format($totalSchedules) ?></strong> supply schedule(s).
            </div>

            <div class="insight-box">
                <strong>Recommended Operational Actions</strong><br><br>
                1. Keep all active zones mapped to water sources.<br>
                2. Update maintenance notices immediately.<br>
                3. Publish accurate supply schedules to customers.<br>
                4. Review inactive or suspended zones.<br>
                5. Use the audit trail to monitor staff changes.
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

.tab-btn{
    padding:11px 16px;
    border-radius:10px;
    background:#f8fafc;
    color:#334155;
    cursor:pointer;
    font-weight:700;
    font-size:13px;
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

.section-title{
    margin:0 0 14px;
    font-size:16px;
    font-weight:800;
    color:#0f172a;
}

.tab-section{
    display:none;
}

.tab-section.active{
    display:block;
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
    min-height:95px;
    resize:vertical;
}

.full-width{
    grid-column:1/-1;
}

button,
.btn,
.expand-btn{
    padding:10px 14px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    cursor:pointer;
    font-weight:700;
    font-size:13px;
    margin-top:10px;
}

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
}

.table-wrapper{
    overflow-x:auto;
    margin-top:16px;
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

.good{
    background:#ecfdf3;
    color:#15803d;
}

.warning{
    background:#fffbeb;
    color:#ca8a04;
}

.critical{
    background:#fef2f2;
    color:#dc2626;
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

.inline-form{
    display:flex;
    gap:6px;
    align-items:center;
    flex-wrap:wrap;
}

.calendar-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
    margin-top:16px;
}

.calendar-day{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:12px;
}

.calendar-day h4{
    margin:0 0 10px;
    color:#0f172a;
}

.calendar-entry{
    border-left:4px solid #1e7d4f;
    background:#f8fafc;
    padding:10px;
    border-radius:10px;
    margin-bottom:8px;
}

.calendar-entry strong,
.calendar-entry span,
.calendar-entry small,
.calendar-entry em{
    display:block;
}

.calendar-entry strong{
    color:#334155;
    font-size:13px;
}

.calendar-entry span{
    color:#475569;
    font-size:13px;
}

.calendar-entry small{
    color:#64748b;
    margin-top:3px;
}

.calendar-entry em{
    color:#7f1d1d;
    font-size:12px;
    font-style:normal;
    margin-top:4px;
}

.maintenance-entry{
    border-left-color:#dc2626;
    background:#fef2f2;
}

.empty-day{
    color:#94a3b8;
    font-size:13px;
    padding:8px;
    background:#f8fafc;
    border-radius:8px;
}

small{
    color:#64748b;
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

    .tab-btn,
    .filters button{
        width:100%;
    }

    .form-grid{
        grid-template-columns:1fr;
    }
}
</style>

<script>
function openZoneTab(evt, tabName){
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
</script>