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

/* ================= MESSAGE ================= */

$message = "";

/* ================= LOG ================= */

function logZoneAction($conn, $action, $description, $staff = 'Zone Manager') {
    $stmt = $conn->prepare("
        INSERT INTO zone_activity_log (action_type, description, staff_name)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("sss", $action, $description, $staff);
    $stmt->execute();
}

/* ================= ACTIONS ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_action'])) {

    $action = $_POST['zone_action'];
    $staff = trim($_POST['staff_name'] ?? 'Zone Manager');

    if ($action === 'save_zone') {
        $zone_name = trim($_POST['zone_name']);
        $zone_code = trim($_POST['zone_code']);
        $source_name = trim($_POST['source_name']);
        $officer = trim($_POST['officer_in_charge']);
        $status = trim($_POST['status']);
        $notes = trim($_POST['notes']);

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
            $message = "<div class='alert success'>Zone saved successfully.</div>";
        }
    }

    if ($action === 'save_source') {
        $source_name = trim($_POST['source_name']);
        $source_type = trim($_POST['source_type']);
        $location = trim($_POST['location']);
        $status = trim($_POST['status']);
        $notes = trim($_POST['notes']);

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
            $message = "<div class='alert success'>Water source saved successfully.</div>";
        }
    }

    if ($action === 'save_schedule') {
        $zone_id = (int) $_POST['zone_id'];
        $source_id = (int) $_POST['source_id'];
        $day = trim($_POST['supply_day']);
        $start = trim($_POST['start_time']);
        $end = trim($_POST['end_time']);
        $status = trim($_POST['status']);
        $notes = trim($_POST['notes']);

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
            $message = "<div class='alert success'>Supply schedule saved and published to customer portal.</div>";
        }
    }

    if ($action === 'save_maintenance') {
        $zone_id = (int) $_POST['zone_id'];
        $issue_title = trim($_POST['issue_title']);
        $issue_description = trim($_POST['issue_description']);
        $maintenance_date = trim($_POST['maintenance_date']);
        $status = trim($_POST['status']);
        $assigned_team = trim($_POST['assigned_team']);

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
            $message = "<div class='alert success'>Maintenance notice saved and published to customer portal.</div>";
        }
    }

    if ($action === 'update_zone_status') {
        $zone_id = (int) $_POST['zone_id'];
        $status = trim($_POST['status']);

        $stmt = $conn->prepare("UPDATE zones SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $zone_id);

        if ($stmt->execute()) {
            logZoneAction($conn, "Zone Status Updated", "Zone ID $zone_id updated to $status", $staff);
            $message = "<div class='alert success'>Zone status updated successfully.</div>";
        }
    }

    if ($action === 'update_maintenance') {
        $maintenance_id = (int) $_POST['maintenance_id'];
        $status = trim($_POST['status']);
        $resolution_notes = trim($_POST['resolution_notes']);

        $stmt = $conn->prepare("
            UPDATE zone_maintenance
            SET status=?, resolution_notes=?
            WHERE id=?
        ");
        $stmt->bind_param("ssi", $status, $resolution_notes, $maintenance_id);

        if ($stmt->execute()) {
            logZoneAction($conn, "Maintenance Updated", "Maintenance ID $maintenance_id updated to $status", $staff);
            $message = "<div class='alert success'>Maintenance status updated successfully.</div>";
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

function countData($conn, $table, $condition = "1=1") {
    $res = $conn->query("SELECT COUNT(*) AS c FROM $table WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

$totalZones = countData($conn, 'zones');
$activeZones = countData($conn, 'zones', "status='Active'");
$totalSources = countData($conn, 'water_sources');
$activeMaintenance = countData($conn, 'zone_maintenance', "status IN ('Open','In Progress')");
$totalSchedules = countData($conn, 'zone_supply_schedule');

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

<div class="page-content">

    <div class="module-header">
        <h2>Zone Management</h2>
        <p>Manage water zones, sources, supply schedules, maintenance interruptions and customer-facing rationing information.</p>
    </div>

    <?= $message ?>

    <div class="summary-grid">
        <div class="summary-card">
            <span>Total Zones</span>
            <strong><?= $totalZones ?></strong>
        </div>

        <div class="summary-card">
            <span>Active Zones</span>
            <strong><?= $activeZones ?></strong>
        </div>

        <div class="summary-card">
            <span>Water Sources</span>
            <strong><?= $totalSources ?></strong>
        </div>

        <div class="summary-card">
            <span>Supply Schedules</span>
            <strong><?= $totalSchedules ?></strong>
        </div>

        <div class="summary-card">
            <span>Active Maintenance</span>
            <strong><?= $activeMaintenance ?></strong>
        </div>
    </div>

    <div class="zone-tabs">
        <button class="tab-btn active" onclick="openZoneTab(event,'zonesTab')">Zones</button>
        <button class="tab-btn" onclick="openZoneTab(event,'sourcesTab')">Water Sources</button>
        <button class="tab-btn" onclick="openZoneTab(event,'scheduleTab')">Supply Schedule</button>
        <button class="tab-btn" onclick="openZoneTab(event,'maintenanceTab')">Maintenance</button>
        <button class="tab-btn" onclick="openZoneTab(event,'calendarTab')">Weekly Calendar</button>
        <button class="tab-btn" onclick="openZoneTab(event,'auditTab')">Audit Trail</button>
    </div>

    <!-- ================= ZONES ================= -->

    <div id="zonesTab" class="tab-section active">

        <div class="section-header">
            <h3>Zone Register</h3>
            <p>Create and manage service zones, officers in charge and operational status.</p>
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

            <button class="submit-btn">Save Zone</button>
        </form>

        <div class="table-card">
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
                                <td><?= clean($meterTotal) ?></td>
                                <td><span class="status-badge"><?= clean($z['status']) ?></span></td>
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

                                        <button class="small-btn">Save</button>
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

    <!-- ================= SOURCES ================= -->

    <div id="sourcesTab" class="tab-section">

        <div class="section-header">
            <h3>Water Sources</h3>
            <p>Manage water sources supplying different zones.</p>
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

            <button class="submit-btn">Save Source</button>
        </form>

        <div class="table-card">
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
                                <td><span class="status-badge"><?= clean($s['status']) ?></span></td>
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

    <!-- ================= SUPPLY SCHEDULE ================= -->

    <div id="scheduleTab" class="tab-section">

        <div class="section-header">
            <h3>Supply Schedule</h3>
            <p>Create rationing or supply schedules and publish them to the customer portal.</p>
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

            <button class="submit-btn">Save Schedule</button>
        </form>

        <div class="table-card">
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
                                <td><span class="status-badge"><?= clean($sc['status']) ?></span></td>
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

    <!-- ================= MAINTENANCE ================= -->

    <div id="maintenanceTab" class="tab-section">

        <div class="section-header">
            <h3>Zone Maintenance</h3>
            <p>Record zone interruptions, pipe bursts, maintenance notices and operational disruptions.</p>
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

            <button class="submit-btn">Save Maintenance Notice</button>
        </form>

        <div class="table-card">
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
                                <td><span class="status-badge"><?= clean($m['status']) ?></span></td>
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

                                        <button class="small-btn">Update</button>
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

    <!-- ================= WEEKLY CALENDAR ================= -->

    <div id="calendarTab" class="tab-section">

        <div class="section-header">
            <h3>Weekly Zone Calendar</h3>
            <p>Operational calendar showing active supply schedules by day.</p>
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
                                $zoneName = $conn->real_escape_string($ds['zone_name']);
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

    <!-- ================= AUDIT ================= -->

    <div id="auditTab" class="tab-section">

        <div class="section-header">
            <h3>Zone Activity Log</h3>
            <p>Recent zone management actions.</p>
        </div>

        <div class="table-card">
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
.summary-card,
.zone-tabs{
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

.zone-tabs{
    padding:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:18px;
}

.tab-btn{
    border:1px solid #d1d5db;
    background:#fff;
    color:#334155;
    padding:8px 12px;
    border-radius:6px;
    font-size:13px;
    cursor:pointer;
}

.tab-btn.active{
    background:#0a2a43;
    color:#fff;
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
    margin-top:14px;
}

.small-btn{
    padding:6px 10px;
    font-size:12px;
    margin-top:6px;
}

.inline-form{
    display:flex;
    gap:6px;
    align-items:center;
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

.calendar-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
}

.calendar-day{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:12px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
}

.calendar-day h4{
    margin:0 0 10px;
    color:#0a2a43;
    font-size:15px;
    border-bottom:1px solid #e5e7eb;
    padding-bottom:8px;
}

.calendar-entry{
    border-left:3px solid #0a2a43;
    background:#f8fafc;
    padding:8px;
    border-radius:6px;
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
    border-left-color:#7f1d1d;
    background:#fef2f2;
}

.empty-day{
    color:#94a3b8;
    font-size:13px;
    padding:8px;
    background:#f8fafc;
    border-radius:6px;
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