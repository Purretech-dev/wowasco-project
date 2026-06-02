<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= CREATE TABLE IF NOT EXISTS ================= */

$conn->query("
CREATE TABLE IF NOT EXISTS pumped_volume_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    pumped_date DATE NOT NULL,
    volume_m3 DECIMAL(12,2) NOT NULL DEFAULT 0,
    source_type VARCHAR(50) DEFAULT 'Manual Entry',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (meter_id),
    INDEX (pumped_date)
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    reading_date DATE NOT NULL,
    consumption DECIMAL(12,2) NOT NULL DEFAULT 0,
    reading_photo VARCHAR(255) NULL,
    source_type VARCHAR(80) DEFAULT 'Conventional Upload',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (meter_id),
    INDEX (reading_date)
)
");

/* ================= HELPERS ================= */

function safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function jsRedirect($url){
    echo "<script>window.location.href = '" . addslashes($url) . "';</script>";
    exit;
}

function redirectBack($savedKey = 'saved'){
    $query = $_GET;
    $query[$savedKey] = 1;
    $query['page'] = 'modules/production/pumped_volume.php';

    jsRedirect("dashboard.php?" . http_build_query($query));
}

addColumnIfMissing($conn, 'meter_readings', 'consumption', "DECIMAL(12,2) NOT NULL DEFAULT 0");
addColumnIfMissing($conn, 'meter_readings', 'reading_photo', "VARCHAR(255) NULL");
addColumnIfMissing($conn, 'meter_readings', 'source_type', "VARCHAR(80) DEFAULT 'Conventional Upload'");
addColumnIfMissing($conn, 'meter_readings', 'remarks', "TEXT NULL");

/* ================= SAVE PUMPED VOLUME ================= */

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_volume'])){

    $meter_id = (int)($_POST['meter_id'] ?? 0);
    $pumped_date = $_POST['pumped_date'] ?? date('Y-m-d');
    $volume_m3 = (float)($_POST['volume_m3'] ?? 0);
    $source_type = trim($_POST['source_type'] ?? 'Manual Entry');
    $remarks = trim($_POST['remarks'] ?? '');

    if($meter_id > 0 && $volume_m3 >= 0){

        $stmt = $conn->prepare("
            INSERT INTO pumped_volume_entries
            (meter_id, pumped_date, volume_m3, source_type, remarks)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isdss",
            $meter_id,
            $pumped_date,
            $volume_m3,
            $source_type,
            $remarks
        );

        $stmt->execute();
    }

    redirectBack('reading_saved');
}

/* ================= SAVE CONVENTIONAL METER READING PHOTO ================= */

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_conventional_reading'])){

    $meter_id = (int)($_POST['conventional_meter_id'] ?? 0);
    $reading_date = $_POST['reading_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['reading_remarks'] ?? '');
    $photoPath = '';

    if($meter_id > 0 && !empty($_FILES['reading_photo']['name'])){

        $allowedTypes = ['jpg', 'jpeg', 'png'];
        $fileName = $_FILES['reading_photo']['name'];
        $fileTmp = $_FILES['reading_photo']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if(in_array($fileExt, $allowedTypes)){
            $uploadDir = __DIR__ . '/../../uploads/meter_readings/';

            if(!is_dir($uploadDir)){
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = 'CONV_READING_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if(move_uploaded_file($fileTmp, $targetPath)){
                $photoPath = 'uploads/meter_readings/' . $newFileName;
            }
        }
    }

    if($meter_id > 0 && $photoPath !== ''){
        $sourceType = 'Conventional Upload';
        $consumption = 0;

        $stmt = $conn->prepare("
            INSERT INTO meter_readings
            (meter_id, reading_date, consumption, reading_photo, source_type, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isdsss",
            $meter_id,
            $reading_date,
            $consumption,
            $photoPath,
            $sourceType,
            $remarks
        );

        $stmt->execute();
    }

    redirectBack();
}

/* ================= DELETE RECORD ================= */

if(isset($_GET['delete_entry'])){

    $delete_id = (int)$_GET['delete_entry'];

    $stmt = $conn->prepare("
        DELETE FROM pumped_volume_entries
        WHERE id = ?
    ");

    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    $query = $_GET;
    unset($query['delete_entry']);
    $query['page'] = 'modules/production/pumped_volume.php';

    jsRedirect("dashboard.php?" . http_build_query($query));
}

/* ================= FILTERS ================= */

$search = trim($_GET['search'] ?? '');
$zone_filter = trim($_GET['zone'] ?? '');
$customer_type_filter = trim($_GET['customer_type'] ?? '');
$start = trim($_GET['start_date'] ?? '');
$end = trim($_GET['end_date'] ?? '');

$filterApplied =
    $search !== '' ||
    $zone_filter !== '' ||
    $customer_type_filter !== '' ||
    ($start !== '' && $end !== '');

$scopeLabel = $filterApplied ? 'Filtered Dataset' : 'All Meters Dataset';

/* ================= DYNAMIC WHERE ================= */

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if($search !== ''){
    $where .= " AND m.serial_number = ? ";
    $params[] = $search;
    $types .= "s";
}

if($zone_filter !== ''){
    $where .= " AND m.zone = ? ";
    $params[] = $zone_filter;
    $types .= "s";
}

if($customer_type_filter !== ''){
    $where .= " AND m.customer_type = ? ";
    $params[] = $customer_type_filter;
    $types .= "s";
}

if($start !== '' && $end !== ''){
    $where .= " AND p.pumped_date BETWEEN ? AND ? ";
    $params[] = $start;
    $params[] = $end;
    $types .= "ss";
}

/* ================= MAIN ANALYTICS QUERY ================= */

$baseSql = "
    SELECT
        m.id AS meter_id,
        m.serial_number,
        m.customer_name,
        m.zone,
        m.customer_type,
        m.status,
        COUNT(p.id) AS entry_count,
        COALESCE(SUM(p.volume_m3),0) AS pumped_volume,
        MAX(p.pumped_date) AS last_pumped_date
    FROM meters m
    LEFT JOIN pumped_volume_entries p
        ON p.meter_id = m.id
    $where
    GROUP BY
        m.id,
        m.serial_number,
        m.customer_name,
        m.zone,
        m.customer_type,
        m.status
    ORDER BY pumped_volume DESC
";

$stmt = $conn->prepare($baseSql);

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$allResult = $stmt->get_result();

$allRows = [];

while($row = $allResult->fetch_assoc()){
    $allRows[] = $row;
}

/* ================= ANALYTICS DATA BASED ON CURRENT SCOPE ================= */

$total_records = count($allRows);
$total_volume_all = 0;
$activeMeters = 0;
$zoneMap = [];
$typeMap = [];

foreach($allRows as $row){

    $volume = (float)$row['pumped_volume'];
    $total_volume_all += $volume;

    if(strtolower(trim($row['status'] ?? '')) === 'active'){
        $activeMeters++;
    }

    $zoneName = $row['zone'] ?: 'Unassigned';
    $typeName = $row['customer_type'] ?: 'Unknown';

    if(!isset($zoneMap[$zoneName])){
        $zoneMap[$zoneName] = [
            'zone_name' => $zoneName,
            'total_volume' => 0,
            'meters_count' => 0
        ];
    }

    $zoneMap[$zoneName]['total_volume'] += $volume;
    $zoneMap[$zoneName]['meters_count']++;

    if(!isset($typeMap[$typeName])){
        $typeMap[$typeName] = [
            'customer_type_name' => $typeName,
            'total_volume' => 0,
            'meters_count' => 0
        ];
    }

    $typeMap[$typeName]['total_volume'] += $volume;
    $typeMap[$typeName]['meters_count']++;
}

$zone_data = array_values($zoneMap);
$type_data = array_values($typeMap);

usort($zone_data, function($a, $b){
    return $b['total_volume'] <=> $a['total_volume'];
});

usort($type_data, function($a, $b){
    return $b['total_volume'] <=> $a['total_volume'];
});

$highestZone = !empty($zone_data) ? $zone_data[0]['zone_name'] : 'N/A';
$highestType = !empty($type_data) ? $type_data[0]['customer_type_name'] : 'N/A';

$avgVolume = $total_records > 0 ? round($total_volume_all / $total_records, 2) : 0;

/* ================= PAGINATION ================= */

$limit = 10;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;

if($page < 1){
    $page = 1;
}

$total_pages = max(1, ceil($total_records / $limit));

if($page > $total_pages){
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;
$pagedRows = array_slice($allRows, $offset, $limit);

/* ================= FILTERED ENTRY LOG ================= */

$entryLogSql = "
    SELECT
        p.*,
        m.serial_number,
        m.customer_name,
        m.zone,
        m.customer_type
    FROM pumped_volume_entries p
    INNER JOIN meters m
        ON p.meter_id = m.id
    $where
    ORDER BY p.pumped_date DESC, p.id DESC
    LIMIT 8
";

$entryStmt = $conn->prepare($entryLogSql);

if(!empty($params)){
    $entryStmt->bind_param($types, ...$params);
}

$entryStmt->execute();
$entryLog = $entryStmt->get_result();

/* ================= DROPDOWNS ================= */

$metersList = $conn->query("
    SELECT id, serial_number, customer_name, zone, customer_type
    FROM meters
    ORDER BY serial_number ASC
");

$conventionalFilters = [];

if(columnExists($conn, 'meters', 'meter_type')){
    $conventionalFilters[] = "LOWER(meter_type) LIKE '%conventional%'";
}

if(columnExists($conn, 'meters', 'type')){
    $conventionalFilters[] = "LOWER(`type`) LIKE '%conventional%'";
}

$conventionalWhere = !empty($conventionalFilters)
    ? "WHERE (" . implode(" OR ", $conventionalFilters) . ")"
    : "";

$conventionalMetersList = $conn->query("
    SELECT id, serial_number, customer_name, zone, customer_type
    FROM meters
    $conventionalWhere
    ORDER BY serial_number ASC
");

$serials = $conn->query("
    SELECT DISTINCT serial_number
    FROM meters
    WHERE serial_number IS NOT NULL AND serial_number != ''
    ORDER BY serial_number ASC
");

$zonesFilter = $conn->query("
    SELECT DISTINCT zone
    FROM meters
    WHERE zone IS NOT NULL AND zone != ''
    ORDER BY zone ASC
");

$typesFilter = $conn->query("
    SELECT DISTINCT customer_type
    FROM meters
    WHERE customer_type IS NOT NULL AND customer_type != ''
    ORDER BY customer_type ASC
");

?>

<style>
body{
    font-family:'Segoe UI',system-ui,sans-serif;
    background:#f8fafc;
    margin:0;
    color:#1e293b;
}

.container{
    margin-left:240px;
    margin-top:80px;
    padding:24px;
    padding-bottom:120px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:16px;
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
    color:#64748b;
    font-size:13px;
}

.header-badge{
    background:#ecfdf3;
    color:#15803d;
    border:1px solid #bbf7d0;
    padding:10px 14px;
    border-radius:12px;
    font-size:13px;
    font-weight:800;
}

.scope-note{
    display:inline-block;
    margin-top:10px;
    padding:7px 11px;
    border-radius:20px;
    background:#eff6ff;
    color:#1e40af;
    font-size:12px;
    font-weight:800;
}

.entry-tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:16px;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:10px;
}

.entry-tab{
    border:1px solid #dbe2ea;
    background:#f8fafc;
    color:#334155;
    border-radius:10px;
    padding:11px 14px;
    cursor:pointer;
    font-weight:800;
    font-size:13px;
}

.entry-tab.active{
    background:#0a2a43;
    border-color:#0a2a43;
    color:#fff;
}

.entry-section{
    display:none;
}

.entry-section.active{
    display:block;
}

.card{
    background:white;
    border-radius:16px;
    border:1px solid #e2e8f0;
    padding:22px;
    margin-bottom:22px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.card-title{
    font-size:17px;
    font-weight:800;
    margin-bottom:16px;
    color:#0f172a;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
    gap:12px;
}

input,select,textarea{
    padding:11px 12px;
    border:1px solid #dbe2ea;
    border-radius:10px;
    font-size:13px;
    background:white;
    color:#1e293b;
    width:100%;
    box-sizing:border-box;
}

textarea{
    min-height:42px;
    resize:vertical;
}

button,.btn{
    padding:11px 16px;
    border:none;
    border-radius:10px;
    background:#1e7d4f;
    color:white;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
}

.btn-blue{
    background:#1e3a8a;
}

.btn-light{
    background:#f8fafc;
    color:#334155;
    border:1px solid #dbe2ea;
}

.filter-form{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
}

.filter-form select,
.filter-form input{
    width:auto;
    min-width:180px;
}

.toggle-btn{
    background:white;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    margin-right:10px;
    margin-bottom:16px;
    font-weight:700;
}

.active-toggle{
    background:#eff6ff !important;
    border-color:#2563eb !important;
    color:#2563eb !important;
}

.table-wrapper{
    overflow-x:auto;
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
    font-weight:800;
    border-bottom:2px solid #e2e8f0;
}

td{
    padding:14px;
    border-bottom:1px solid #f1f5f9;
    font-size:13px;
    color:#1e293b;
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

.badge-green{
    background:#dcfce7;
    color:#15803d;
}

.badge-yellow{
    background:#fef9c3;
    color:#a16207;
}

.badge-blue{
    background:#dbeafe;
    color:#1e40af;
}

.hidden{
    display:none;
}

.expand-btn{
    background:#f8fafc;
    border:1px solid #dbe2ea;
    color:#334155;
    padding:7px 12px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:700;
}

.drilldown{
    display:none;
    background:#f8fafc;
}

.drill-card{
    padding:16px;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
}

.drill-item{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:12px;
}

.drill-item strong{
    display:block;
    font-size:12px;
    color:#64748b;
    margin-bottom:5px;
}

.pagination{
    margin-top:22px;
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
}

.pagination a{
    padding:8px 12px;
    border-radius:8px;
    border:1px solid #dbe2ea;
    background:white;
    color:#334155;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
}

.pagination a.active{
    background:#eff6ff;
    border-color:#2563eb;
    color:#2563eb;
}

.alert-success{
    background:#ecfdf3;
    color:#166534;
    border:1px solid #bbf7d0;
    padding:14px;
    border-radius:12px;
    margin-bottom:18px;
    font-size:13px;
    font-weight:700;
}

.insight{
    background:#f8fafc;
    border-left:5px solid #1e3a8a;
    padding:16px;
    border-radius:12px;
    font-size:14px;
    line-height:1.7;
    color:#334155;
}

.back-btn{
    display:inline-block;
    margin-top:10px;
    padding:12px 18px;
    background:white;
    border:1px solid #dbe2ea;
    color:#334155;
    text-decoration:none;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
}

.print-report{
    display:none;
}

@media(max-width:1000px){
    .container{
        margin-left:0;
    }

    .filter-form{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-form input,
    .filter-form select,
    .filter-form button,
    .filter-form .btn{
        width:100%;
    }

    .drill-card{
        grid-template-columns:1fr;
    }

    .entry-tab{
        width:100%;
    }
}

/* ================= PRINT FILTERED DATA ONLY ================= */

@media print{

    body{
        background:white;
        color:#000;
    }

    body *{
        visibility:hidden !important;
    }

    .print-report,
    .print-report *{
        visibility:visible !important;
    }

    .print-report{
        display:block !important;
        position:absolute;
        left:0;
        top:0;
        width:100%;
        padding:18px;
        font-family:Arial, sans-serif;
    }

    .print-report h2{
        margin:0 0 6px;
        font-size:22px;
        color:#000;
    }

    .print-report p{
        margin:0 0 14px;
        font-size:12px;
        color:#333;
    }

    .print-report table{
        width:100%;
        border-collapse:collapse;
        font-size:11px;
    }

    .print-report th,
    .print-report td{
        border:1px solid #999;
        padding:7px;
        text-align:left;
        color:#000;
    }

    .print-report th{
        background:#eee !important;
        font-weight:bold;
    }

    @page{
        size:A4 landscape;
        margin:12mm;
    }
}
</style>

<!-- ================= PRINT ONLY FILTERED REPORT ================= -->

<div class="print-report">

    <h2>WOWASCO Pumped Volume Report</h2>

    <p>
        Scope: <?= safe($scopeLabel) ?> |
        Generated: <?= date('d M Y H:i') ?> |
        Records: <?= number_format($total_records) ?> |
        Total Volume: <?= number_format($total_volume_all,2) ?> m³ |
        Active Meters: <?= number_format($activeMeters) ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Meter Serial</th>
                <th>Customer Name</th>
                <th>Zone</th>
                <th>Customer Type</th>
                <th>Status</th>
                <th>Records</th>
                <th>Total Volume</th>
                <th>Last Entry</th>
            </tr>
        </thead>

        <tbody>
            <?php if(empty($allRows)): ?>
                <tr>
                    <td colspan="9">No pumped volume records found.</td>
                </tr>
            <?php else: ?>
                <?php $printNo = 1; foreach($allRows as $printRow): ?>
                    <tr>
                        <td><?= $printNo++ ?></td>
                        <td><?= safe($printRow['serial_number']) ?></td>
                        <td><?= safe($printRow['customer_name'] ?: 'N/A') ?></td>
                        <td><?= safe($printRow['zone'] ?: 'Unassigned') ?></td>
                        <td><?= safe($printRow['customer_type'] ?: 'Unknown') ?></td>
                        <td><?= safe($printRow['status'] ?: 'N/A') ?></td>
                        <td><?= number_format($printRow['entry_count']) ?></td>
                        <td><?= number_format($printRow['pumped_volume'],2) ?> m³</td>
                        <td><?= safe($printRow['last_pumped_date'] ?: 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<div class="container">

<div class="page-header">
    <div>
        <h2>📊 Pumped Volume Operations Center</h2>
        <p>Enterprise-level pumped volume capture, zone analysis, customer type analytics, and meter-based reporting.</p>
        <span class="scope-note">
            <?= safe($scopeLabel) ?>
        </span>
    </div>

    <div class="header-badge">
        Total Volume: <?= number_format($total_volume_all,2) ?> m³
    </div>
</div>

<?php if(isset($_GET['saved'])): ?>
    <div class="alert-success">
        Pumped volume record saved successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['reading_saved'])): ?>
    <div class="alert-success">
        Conventional meter reading photo uploaded successfully.
    </div>
<?php endif; ?>

<div class="entry-tabs">
    <button type="button" class="entry-tab active" data-entry-section="pumpedVolumeEntry">
        Add Pumped Volume Record
    </button>

    <button type="button" class="entry-tab" data-entry-section="conventionalReadingEntry">
        Upload Meter Readings for Conventional Meters
    </button>
</div>

<div id="pumpedVolumeEntry" class="entry-section active">

<div class="card">

    <div class="card-title">Add Pumped Volume Record</div>

    <form method="POST" class="form-grid">

        <select name="meter_id" required>
            <option value="">Select Meter / Customer</option>

            <?php while($m = $metersList->fetch_assoc()): ?>
                <option value="<?= (int)$m['id'] ?>">
                    <?= safe($m['serial_number']) ?> —
                    <?= safe($m['customer_name'] ?: 'N/A') ?> —
                    <?= safe($m['zone'] ?: 'Unassigned') ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="date" name="pumped_date" value="<?= date('Y-m-d') ?>" required>

        <input
            type="number"
            step="0.01"
            min="0"
            name="volume_m3"
            placeholder="Volume Pumped (m³)"
            required>

        <select name="source_type">
            <option value="Manual Entry">Manual Entry</option>
            <option value="Meter Reading">Meter Reading</option>
            <option value="Production Estimate">Production Estimate</option>
            <option value="Field Report">Field Report</option>
        </select>

        <textarea name="remarks" placeholder="Remarks / operational notes"></textarea>

        <button type="submit" name="save_volume">
            Save Pumped Volume
        </button>

    </form>

</div>

</div>

<div id="conventionalReadingEntry" class="entry-section">

<div class="card">

    <div class="card-title">Upload Meter Readings for Conventional Meters</div>

    <form method="POST" enctype="multipart/form-data" class="form-grid">

        <select name="conventional_meter_id" required>
            <option value="">Select Conventional Meter</option>

            <?php if($conventionalMetersList && $conventionalMetersList->num_rows > 0): ?>
                <?php while($cm = $conventionalMetersList->fetch_assoc()): ?>
                    <option value="<?= (int)$cm['id'] ?>">
                        <?= safe($cm['serial_number']) ?> -
                        <?= safe($cm['customer_name'] ?: 'N/A') ?> -
                        <?= safe($cm['zone'] ?: 'Unassigned') ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <input type="date" name="reading_date" value="<?= date('Y-m-d') ?>" required>

        <input type="file" name="reading_photo" accept=".jpg,.jpeg,.png" required>

        <textarea name="reading_remarks" placeholder="Remarks / reading notes"></textarea>

        <button type="submit" name="save_conventional_reading">
            Save Conventional Reading
        </button>

    </form>

</div>

</div>

<div class="card">

    <div class="card-title">Filter Pumped Volume Analytics</div>

    <form method="GET" action="dashboard.php" class="filter-form">

        <input type="hidden" name="page" value="modules/production/pumped_volume.php">

        <select name="search">
            <option value="">All Meter Serials</option>

            <?php while($s = $serials->fetch_assoc()): ?>
                <option value="<?= safe($s['serial_number']) ?>" <?= ($search == $s['serial_number']) ? 'selected' : '' ?>>
                    <?= safe($s['serial_number']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="zone">
            <option value="">All Zones</option>

            <?php while($z = $zonesFilter->fetch_assoc()): ?>
                <option value="<?= safe($z['zone']) ?>" <?= ($zone_filter == $z['zone']) ? 'selected' : '' ?>>
                    <?= safe($z['zone']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="customer_type">
            <option value="">All Customer Types</option>

            <?php while($t = $typesFilter->fetch_assoc()): ?>
                <option value="<?= safe($t['customer_type']) ?>" <?= ($customer_type_filter == $t['customer_type']) ? 'selected' : '' ?>>
                    <?= safe($t['customer_type']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="date" name="start_date" value="<?= safe($start) ?>">
        <input type="date" name="end_date" value="<?= safe($end) ?>">

        <button type="submit">Filter Analytics</button>

        <a class="btn btn-light" href="dashboard.php?page=modules/production/pumped_volume.php">
            Reset
        </a>

        <button type="button" class="btn-blue" onclick="window.print()">
            Print Filtered Report
        </button>

    </form>

</div>

<div class="card">

    <button class="toggle-btn active-toggle" onclick="toggleTable('customerTable', this)">
        Per Meter / Customer
    </button>

    <button class="toggle-btn" onclick="toggleTable('zoneTable', this)">
        Per Zone
    </button>

    <button class="toggle-btn" onclick="toggleTable('typeTable', this)">
        Per Customer Type
    </button>

    <button class="toggle-btn" onclick="toggleTable('entryLogTable', this)">
        Recent Entries
    </button>

    <div id="customerTable">

        <div class="table-wrapper">

            <table>

                <tr>
                    <th>Meter Serial</th>
                    <th>Customer Name</th>
                    <th>Zone</th>
                    <th>Customer Type</th>
                    <th>Records</th>
                    <th>Total Volume</th>
                    <th>Last Entry</th>
                    <th>Action</th>
                </tr>

                <?php if(empty($pagedRows)): ?>

                    <tr>
                        <td colspan="8">No pumped volume records found.</td>
                    </tr>

                <?php endif; ?>

                <?php foreach($pagedRows as $row): ?>

                    <?php
                        $volume = (float)$row['pumped_volume'];

                        if($volume >= 5000){
                            $insight = "High consumption / high production demand";
                            $badge = "badge-green";
                        }
                        elseif($volume >= 2000){
                            $insight = "Moderate consumption";
                            $badge = "badge-blue";
                        }
                        else{
                            $insight = "Low consumption";
                            $badge = "badge-yellow";
                        }
                    ?>

                    <tr>
                        <td><strong><?= safe($row['serial_number']) ?></strong></td>
                        <td><?= safe($row['customer_name'] ?: 'N/A') ?></td>
                        <td><?= safe($row['zone'] ?: 'Unassigned') ?></td>
                        <td><?= safe($row['customer_type'] ?: 'Unknown') ?></td>
                        <td><?= number_format($row['entry_count']) ?></td>
                        <td><strong><?= number_format($volume,2) ?> m³</strong></td>
                        <td><?= safe($row['last_pumped_date'] ?: 'N/A') ?></td>
                        <td>
                            <button type="button" class="expand-btn" onclick="toggleDrill(<?= (int)$row['meter_id'] ?>)">
                                Details
                            </button>
                        </td>
                    </tr>

                    <tr id="drill-<?= (int)$row['meter_id'] ?>" class="drilldown">
                        <td colspan="8">

                            <div class="drill-card">

                                <div class="drill-item">
                                    <strong>Meter Serial</strong>
                                    <?= safe($row['serial_number']) ?>
                                </div>

                                <div class="drill-item">
                                    <strong>Customer</strong>
                                    <?= safe($row['customer_name'] ?: 'N/A') ?>
                                </div>

                                <div class="drill-item">
                                    <strong>Zone</strong>
                                    <?= safe($row['zone'] ?: 'Unassigned') ?>
                                </div>

                                <div class="drill-item">
                                    <strong>Customer Type</strong>
                                    <?= safe($row['customer_type'] ?: 'Unknown') ?>
                                </div>

                                <div class="drill-item">
                                    <strong>Total Pumped Volume</strong>
                                    <?= number_format($volume,2) ?> m³
                                </div>

                                <div class="drill-item">
                                    <strong>Consumption Class</strong>
                                    <span class="badge <?= $badge ?>"><?= safe($insight) ?></span>
                                </div>

                            </div>

                        </td>
                    </tr>

                <?php endforeach; ?>

            </table>

        </div>

        <div class="pagination">

            <?php if($page > 1): ?>
                <a href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $page-1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>">
                    ← Prev
                </a>
            <?php endif; ?>

            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a
                    href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $i ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>"
                    class="<?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="dashboard.php?page=modules/production/pumped_volume.php&page_num=<?= $page+1 ?>&search=<?= urlencode($search) ?>&zone=<?= urlencode($zone_filter) ?>&customer_type=<?= urlencode($customer_type_filter) ?>&start_date=<?= urlencode($start) ?>&end_date=<?= urlencode($end) ?>">
                    Next →
                </a>
            <?php endif; ?>

        </div>

    </div>

    <div id="zoneTable" class="hidden">

        <div class="table-wrapper">

            <table>
                <tr>
                    <th>Zone</th>
                    <th>Meters</th>
                    <th>Total Volume</th>
                    <th>Percentage Contribution</th>
                </tr>

                <?php foreach($zone_data as $zone): ?>

                    <?php
                        $percentage = $total_volume_all > 0
                        ? round(($zone['total_volume'] / $total_volume_all) * 100, 1)
                        : 0;
                    ?>

                    <tr>
                        <td><strong><?= safe($zone['zone_name']) ?></strong></td>
                        <td><?= number_format($zone['meters_count']) ?></td>
                        <td><?= number_format($zone['total_volume'],2) ?> m³</td>
                        <td><?= $percentage ?>%</td>
                    </tr>

                <?php endforeach; ?>

            </table>

        </div>

    </div>

    <div id="typeTable" class="hidden">

        <div class="table-wrapper">

            <table>
                <tr>
                    <th>Customer Type</th>
                    <th>Meters</th>
                    <th>Total Volume</th>
                    <th>Percentage Contribution</th>
                </tr>

                <?php foreach($type_data as $type): ?>

                    <?php
                        $percentage = $total_volume_all > 0
                        ? round(($type['total_volume'] / $total_volume_all) * 100, 1)
                        : 0;
                    ?>

                    <tr>
                        <td><strong><?= safe($type['customer_type_name']) ?></strong></td>
                        <td><?= number_format($type['meters_count']) ?></td>
                        <td><?= number_format($type['total_volume'],2) ?> m³</td>
                        <td><?= $percentage ?>%</td>
                    </tr>

                <?php endforeach; ?>

            </table>

        </div>

    </div>

    <div id="entryLogTable" class="hidden">

        <div class="table-wrapper">

            <table>
                <tr>
                    <th>Date</th>
                    <th>Meter</th>
                    <th>Customer</th>
                    <th>Zone</th>
                    <th>Customer Type</th>
                    <th>Volume</th>
                    <th>Source</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>

                <?php if($entryLog->num_rows == 0): ?>
                    <tr>
                        <td colspan="9">No recent entries found for this scope.</td>
                    </tr>
                <?php endif; ?>

                <?php while($e = $entryLog->fetch_assoc()): ?>
                    <tr>
                        <td><?= safe($e['pumped_date']) ?></td>
                        <td><?= safe($e['serial_number']) ?></td>
                        <td><?= safe($e['customer_name'] ?: 'N/A') ?></td>
                        <td><?= safe($e['zone'] ?: 'Unassigned') ?></td>
                        <td><?= safe($e['customer_type'] ?: 'Unknown') ?></td>
                        <td><strong><?= number_format($e['volume_m3'],2) ?> m³</strong></td>
                        <td><?= safe($e['source_type']) ?></td>
                        <td><?= safe($e['remarks'] ?: '-') ?></td>
                        <td>
                            <a
                                class="btn btn-light"
                                onclick="return confirm('Delete this pumped volume entry?')"
                                href="dashboard.php?page=modules/production/pumped_volume.php&delete_entry=<?= (int)$e['id'] ?>">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>

            </table>

        </div>

    </div>

</div>

<div class="card">

    <div class="card-title">Executive Pumped Volume Intelligence</div>

    <div class="insight">
        Current scope:
        <strong><?= safe($scopeLabel) ?></strong>.
        The analysis shows
        <strong><?= number_format($total_records) ?></strong> meter(s) and a total pumped volume of
        <strong><?= number_format($total_volume_all,2) ?> m³</strong>.

        <br><br>

        The highest contributing zone is
        <strong><?= safe($highestZone) ?></strong>, while the highest consuming customer category is
        <strong><?= safe($highestType) ?></strong>.

        <br><br>

        When no filters are applied, the tables and analysis summarize all meters. When filters are applied,
        tables, print output, zone analysis, and customer type analysis automatically reflect the filtered dataset only.
    </div>

</div>

<a href="/wowasco-system/dashboard.php" class="back-btn">
    ← Back
</a>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const buttons = document.querySelectorAll('.entry-tab');
    const sections = document.querySelectorAll('.entry-section');
    const savedSection = localStorage.getItem('pumpedVolumeEntrySection') || 'pumpedVolumeEntry';

    function openEntrySection(sectionId){
        sections.forEach(function(section){
            section.classList.toggle('active', section.id === sectionId);
        });

        buttons.forEach(function(button){
            button.classList.toggle('active', button.dataset.entrySection === sectionId);
        });

        localStorage.setItem('pumpedVolumeEntrySection', sectionId);
    }

    buttons.forEach(function(button){
        button.addEventListener('click', function(){
            openEntrySection(button.dataset.entrySection);
        });
    });

    if(document.getElementById(savedSection)){
        openEntrySection(savedSection);
    }
});

function toggleTable(id, btn){

    let tables = [
        'customerTable',
        'zoneTable',
        'typeTable',
        'entryLogTable'
    ];

    tables.forEach(tableId => {
        let table = document.getElementById(tableId);

        if(table){
            table.style.display = 'none';
        }
    });

    let selected = document.getElementById(id);

    if(selected){
        selected.style.display = 'block';
    }

    let buttons = document.querySelectorAll('.toggle-btn');

    buttons.forEach(button => {
        button.classList.remove('active-toggle');
    });

    btn.classList.add('active-toggle');
}

function toggleDrill(id){

    let row = document.getElementById('drill-' + id);

    if(!row){
        return;
    }

    row.style.display =
    row.style.display === 'table-row'
    ? 'none'
    : 'table-row';
}
</script>
