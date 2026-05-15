```php
<?php
require_once __DIR__ . '/../../api/db.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection error.");
}

/* ================= HELPERS ================= */

function clean($value){
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table){

    $table = $conn->real_escape_string($table);

    $res = $conn->query("
        SHOW TABLES LIKE '$table'
    ");

    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column){

    if (!tableExists($conn, $table)) {
        return false;
    }

    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("
        SHOW COLUMNS FROM `$table`
        LIKE '$column'
    ");

    return $res && $res->num_rows > 0;
}

function countRows($conn, $table, $condition = "1=1"){

    if (!tableExists($conn, $table)) {
        return 0;
    }

    $res = $conn->query("
        SELECT COUNT(*) AS total
        FROM `$table`
        WHERE $condition
    ");

    return $res
        ? (int)$res->fetch_assoc()['total']
        : 0;
}

function sumColumn($conn, $table, $column, $condition = "1=1"){

    if (
        !tableExists($conn, $table) ||
        !columnExists($conn, $table, $column)
    ) {
        return 0;
    }

    $res = $conn->query("
        SELECT SUM(`$column`) AS total
        FROM `$table`
        WHERE $condition
    ");

    return $res
        ? (float)($res->fetch_assoc()['total'] ?? 0)
        : 0;
}

function money($amount){
    return 'KSh ' . number_format((float)$amount, 2);
}

/* ================= DATE FILTER ================= */

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/* ================= METERS ================= */

$totalMeters    = countRows($conn, 'meters');
$activeMeters   = countRows($conn, 'meters', "status='Active'");
$inactiveMeters = countRows($conn, 'meters', "status='Inactive'");

$smartMeters = columnExists($conn, 'meters', 'meter_type')
    ? countRows($conn, 'meters', "meter_type LIKE '%Smart%'")
    : 0;

$conventionalMeters = columnExists($conn, 'meters', 'meter_type')
    ? countRows($conn, 'meters', "meter_type LIKE '%Conventional%'")
    : 0;

/* ================= CUSTOMERS ================= */

$totalCustomers = tableExists($conn, 'customers')
    ? countRows($conn, 'customers')
    : countRows($conn, 'customer');

$totalApplications = countRows($conn, 'meter_applications');

$pendingApplications = countRows(
    $conn,
    'meter_applications',
    "status IN ('Pending','Submitted','Under Review')"
);

$approvedApplications = countRows(
    $conn,
    'meter_applications',
    "status='Approved'"
);

$rejectedApplications = countRows(
    $conn,
    'meter_applications',
    "status='Rejected'"
);

/* ================= CUSTOMER RELATIONS ================= */

$totalComplaints = countRows($conn, 'customer_complaints');

$openComplaints = countRows(
    $conn,
    'customer_complaints',
    "status IN ('Submitted','Assigned','In Progress','Escalated')"
);

$resolvedComplaints = countRows(
    $conn,
    'customer_complaints',
    "status IN ('Resolved','Closed')"
);

$escalatedComplaints = countRows(
    $conn,
    'customer_complaints',
    "status='Escalated'"
);

$totalEnquiries = countRows($conn, 'customer_enquiries');

$openEnquiries = countRows(
    $conn,
    'customer_enquiries',
    "status IN ('Submitted','Open','In Progress')"
);

/* ================= ASSETS ================= */

$totalAssets = countRows(
    $conn,
    'assets',
    columnExists($conn, 'assets', 'is_deleted')
        ? "is_deleted=0"
        : "1=1"
);

$assetValue = sumColumn(
    $conn,
    'assets',
    'asset_value',
    columnExists($conn, 'assets', 'is_deleted')
        ? "is_deleted=0"
        : "1=1"
);

$netAssetValue = sumColumn(
    $conn,
    'assets',
    'net_value',
    columnExists($conn, 'assets', 'is_deleted')
        ? "is_deleted=0"
        : "1=1"
);

$inactiveAssets = countRows(
    $conn,
    'assets',
    "status='Inactive'"
);

/* ================= ZONES ================= */

$totalZones = countRows($conn, 'zones');

$activeZones = countRows(
    $conn,
    'zones',
    "status='Active'"
);

$zonesUnderMaintenance = countRows(
    $conn,
    'zones',
    "status='Under Maintenance'"
);

$activeZoneMaintenance = countRows(
    $conn,
    'zone_maintenance',
    "status IN ('Open','In Progress')"
);

/* ================= PUMPED VOLUMES ================= */

$pumpedTable = tableExists($conn, 'pumped_volumes')
    ? 'pumped_volumes'
    : (
        tableExists($conn, 'pumped_volume')
            ? 'pumped_volume'
            : ''
    );

$totalPumped   = 0;
$monthlyPumped = 0;

if ($pumpedTable) {

    $volumeCol = columnExists($conn, $pumpedTable, 'volume')
        ? 'volume'
        : (
            columnExists($conn, $pumpedTable, 'pumped_volume')
                ? 'pumped_volume'
                : (
                    columnExists($conn, $pumpedTable, 'quantity')
                        ? 'quantity'
                        : ''
                )
        );

    $dateCol = columnExists($conn, $pumpedTable, 'record_date')
        ? 'record_date'
        : (
            columnExists($conn, $pumpedTable, 'date_recorded')
                ? 'date_recorded'
                : (
                    columnExists($conn, $pumpedTable, 'created_at')
                        ? 'created_at'
                        : ''
                )
        );

    if ($volumeCol) {

        $totalPumped = sumColumn(
            $conn,
            $pumpedTable,
            $volumeCol
        );

        if ($dateCol) {

            $monthlyPumped = sumColumn(
                $conn,
                $pumpedTable,
                $volumeCol,
                "DATE(`$dateCol`) BETWEEN '$from' AND '$to'"
            );
        }
    }
}

/* ================= BILLING ================= */

$totalBilled = sumColumn(
    $conn,
    'bills',
    'amount'
);

$paidBills = columnExists($conn, 'bills', 'status')
    ? sumColumn($conn, 'bills', 'amount', "status='Paid'")
    : 0;

$unpaidBills = columnExists($conn, 'bills', 'status')
    ? sumColumn($conn, 'bills', 'amount', "status!='Paid'")
    : 0;

/* ================= RATIOS ================= */

$meterActiveRate = $totalMeters > 0
    ? round(($activeMeters / $totalMeters) * 100, 1)
    : 0;

$complaintResolutionRate = $totalComplaints > 0
    ? round(($resolvedComplaints / $totalComplaints) * 100, 1)
    : 0;

$applicationApprovalRate = $totalApplications > 0
    ? round(($approvedApplications / $totalApplications) * 100, 1)
    : 0;

$revenueEfficiency = $totalPumped > 0
    ? round($totalBilled / $totalPumped, 2)
    : 0;

/* ================= ZONE PERFORMANCE ================= */

$zoneMeterData = [];

if (
    tableExists($conn, 'meters') &&
    columnExists($conn, 'meters', 'zone')
) {

    $res = $conn->query("
        SELECT zone, COUNT(*) AS total_meters
        FROM meters
        GROUP BY zone
        ORDER BY total_meters DESC
        LIMIT 8
    ");

    if ($res) {

        while ($row = $res->fetch_assoc()) {
            $zoneMeterData[] = $row;
        }
    }
}

/* ================= COMPLAINT HOTSPOTS ================= */

$complaintsByZone = [];

if (
    tableExists($conn, 'customer_complaints') &&
    columnExists($conn, 'customer_complaints', 'zone')
) {

    $res = $conn->query("
        SELECT zone, COUNT(*) AS total_complaints
        FROM customer_complaints
        GROUP BY zone
        ORDER BY total_complaints DESC
        LIMIT 8
    ");

    if ($res) {

        while ($row = $res->fetch_assoc()) {
            $complaintsByZone[] = $row;
        }
    }
}

/* ================= RECENT ITEMS ================= */

$recentApplications = tableExists($conn, 'meter_applications')
    ? $conn->query("
        SELECT *
        FROM meter_applications
        ORDER BY id DESC
        LIMIT 6
    ")
    : null;

$recentComplaints = tableExists($conn, 'customer_complaints')
    ? $conn->query("
        SELECT *
        FROM customer_complaints
        ORDER BY id DESC
        LIMIT 6
    ")
    : null;

/* ================= RISK FLAGS ================= */

$riskFlags = [];

if ($inactiveMeters > 0) {
    $riskFlags[] = "$inactiveMeters inactive meters detected.";
}

if ($openComplaints > 0) {
    $riskFlags[] = "$openComplaints complaints remain unresolved.";
}

if ($pendingApplications > 0) {
    $riskFlags[] = "$pendingApplications applications pending approval.";
}

if ($activeZoneMaintenance > 0) {
    $riskFlags[] = "$activeZoneMaintenance maintenance operations ongoing.";
}

if ($unpaidBills > 0) {
    $riskFlags[] = "Outstanding unpaid bills total " . money($unpaidBills);
}

if (empty($riskFlags)) {
    $riskFlags[] = "No major operational risks detected.";
}
?>

<div class="page-content">

    <!-- ================= HEADER ================= -->

    <div class="module-header">

        <div>

            <h2>Managing Director Dashboard</h2>

            <p>
                Executive reporting dashboard covering
                metering, production, customer relations,
                zoning, assets and operational performance.
            </p>

        </div>

    </div>

    <!-- ================= FILTER ================= -->

    <form method="GET" class="filter-card">

        <input
            type="hidden"
            name="page"
            value="<?= clean($_GET['page'] ?? 'advanced_reports/md_dashboard') ?>"
        >

        <div>

            <label>From</label>

            <input
                type="date"
                name="from"
                value="<?= clean($from) ?>"
            >

        </div>

        <div>

            <label>To</label>

            <input
                type="date"
                name="to"
                value="<?= clean($to) ?>"
            >

        </div>

        <button type="submit">
            Apply Filter
        </button>

    </form>

    <!-- ================= KPI GRID ================= -->

    <div class="kpi-grid">

        <div class="kpi-card">
            <span>Total Meters</span>
            <strong><?= number_format($totalMeters) ?></strong>
            <small><?= $meterActiveRate ?>% Active</small>
        </div>

        <div class="kpi-card">
            <span>Total Customers</span>
            <strong><?= number_format($totalCustomers) ?></strong>
            <small>Registered customer accounts</small>
        </div>

        <div class="kpi-card">
            <span>Total Pumped Volume</span>
            <strong><?= number_format($totalPumped, 2) ?></strong>
            <small>Monthly: <?= number_format($monthlyPumped, 2) ?></small>
        </div>

        <div class="kpi-card">
            <span>Total Revenue</span>
            <strong><?= money($totalBilled) ?></strong>
            <small>Efficiency: <?= $revenueEfficiency ?></small>
        </div>

        <div class="kpi-card">
            <span>Total Assets</span>
            <strong><?= number_format($totalAssets) ?></strong>
            <small>Gross Value: <?= money($assetValue) ?></small>
        </div>

        <div class="kpi-card">
            <span>Net Asset Value</span>
            <strong><?= money($netAssetValue) ?></strong>
            <small><?= number_format($inactiveAssets) ?> inactive assets</small>
        </div>

        <div class="kpi-card">
            <span>Customer Complaints</span>
            <strong><?= number_format($totalComplaints) ?></strong>
            <small><?= $complaintResolutionRate ?>% resolved</small>
        </div>

        <div class="kpi-card">
            <span>Meter Applications</span>
            <strong><?= number_format($totalApplications) ?></strong>
            <small><?= $applicationApprovalRate ?>% approved</small>
        </div>

        <div class="kpi-card">
            <span>Total Zones</span>
            <strong><?= number_format($totalZones) ?></strong>
            <small><?= $activeZones ?> active zones</small>
        </div>

        <div class="kpi-card">
            <span>Open Enquiries</span>
            <strong><?= number_format($openEnquiries) ?></strong>
            <small><?= number_format($totalEnquiries) ?> total enquiries</small>
        </div>

    </div>

    <!-- ================= PERFORMANCE TABS ================= -->

    <div class="performance-tabs">

        <button class="tab-btn active"
            onclick="openPerformanceTab(event, 'operationalTab')">

            Operational Performance

        </button>

        <button class="tab-btn"
            onclick="openPerformanceTab(event, 'customerServiceTab')">

            Customer Service Performance

        </button>

        <button class="tab-btn"
            onclick="openPerformanceTab(event, 'zonePerformanceTab')">

            Top Zones by Meter Count

        </button>

        <button class="tab-btn"
            onclick="openPerformanceTab(event, 'complaintHotspotTab')">

            Complaint Hotspots

        </button>

    </div>

    <!-- ================= OPERATIONAL PERFORMANCE ================= -->

    <div id="operationalTab"
        class="performance-content active-tab">

        <div class="report-card">

            <h3>Operational Performance</h3>

            <div class="metric-row">
                <span>Active Meters</span>
                <strong><?= number_format($activeMeters) ?></strong>
            </div>

            <div class="metric-row">
                <span>Inactive Meters</span>
                <strong><?= number_format($inactiveMeters) ?></strong>
            </div>

            <div class="metric-row">
                <span>Smart Meters</span>
                <strong><?= number_format($smartMeters) ?></strong>
            </div>

            <div class="metric-row">
                <span>Conventional Meters</span>
                <strong><?= number_format($conventionalMeters) ?></strong>
            </div>

            <div class="metric-row">
                <span>Active Zone Maintenance</span>
                <strong><?= number_format($activeZoneMaintenance) ?></strong>
            </div>

        </div>

    </div>

    <!-- ================= CUSTOMER SERVICE ================= -->

    <div id="customerServiceTab"
        class="performance-content">

        <div class="report-card">

            <h3>Customer Service Performance</h3>

            <div class="metric-row">
                <span>Open Complaints</span>
                <strong><?= number_format($openComplaints) ?></strong>
            </div>

            <div class="metric-row">
                <span>Resolved Complaints</span>
                <strong><?= number_format($resolvedComplaints) ?></strong>
            </div>

            <div class="metric-row">
                <span>Escalated Complaints</span>
                <strong><?= number_format($escalatedComplaints) ?></strong>
            </div>

            <div class="metric-row">
                <span>Pending Applications</span>
                <strong><?= number_format($pendingApplications) ?></strong>
            </div>

            <div class="metric-row">
                <span>Rejected Applications</span>
                <strong><?= number_format($rejectedApplications) ?></strong>
            </div>

        </div>

    </div>

    <!-- ================= ZONE PERFORMANCE ================= -->

    <div id="zonePerformanceTab"
        class="performance-content">

        <div class="report-card">

            <h3>Top Zones by Meter Count</h3>

            <?php if (!empty($zoneMeterData)): ?>

                <?php foreach ($zoneMeterData as $z): ?>

                    <div class="metric-row">

                        <span><?= clean($z['zone']) ?></span>

                        <strong>
                            <?= number_format($z['total_meters']) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty">
                    No zone performance data available.
                </p>

            <?php endif; ?>

        </div>

    </div>

    <!-- ================= COMPLAINT HOTSPOTS ================= -->

    <div id="complaintHotspotTab"
        class="performance-content">

        <div class="report-card">

            <h3>Complaint Hotspots</h3>

            <?php if (!empty($complaintsByZone)): ?>

                <?php foreach ($complaintsByZone as $c): ?>

                    <div class="metric-row">

                        <span><?= clean($c['zone']) ?></span>

                        <strong>
                            <?= number_format($c['total_complaints']) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="empty">
                    No complaint hotspot data available.
                </p>

            <?php endif; ?>

        </div>

    </div>

    <!-- ================= RISK FLAGS ================= -->

    <div class="report-card">

        <h3>Executive Risk Flags</h3>

        <div class="risk-list">

            <?php foreach ($riskFlags as $risk): ?>

                <div class="risk-item">

                    <?= clean($risk) ?>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <!-- ================= TABLES ================= -->

    <div class="report-grid two">

        <!-- APPLICATIONS -->

        <div class="report-card">

            <h3>Recent Meter Applications</h3>

            <table>

                <thead>

                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Zone</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    $recentApplications &&
                    $recentApplications->num_rows > 0
                ): ?>

                    <?php while ($a = $recentApplications->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= clean($a['application_ref'] ?? '') ?>
                            </td>

                            <td>
                                <?= clean($a['customer_name'] ?? '') ?>
                            </td>

                            <td>
                                <?= clean($a['zone'] ?? '') ?>
                            </td>

                            <td>

                                <span class="status-badge">

                                    <?= clean($a['status'] ?? '') ?>

                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="4">
                            No recent applications found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- COMPLAINTS -->

        <div class="report-card">

            <h3>Recent Complaints</h3>

            <table>

                <thead>

                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Issue</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    $recentComplaints &&
                    $recentComplaints->num_rows > 0
                ): ?>

                    <?php while ($c = $recentComplaints->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= clean($c['complaint_ref'] ?? '') ?>
                            </td>

                            <td>
                                <?= clean($c['customer_name'] ?? '') ?>
                            </td>

                            <td>
                                <?= clean($c['complaint_type'] ?? '') ?>
                            </td>

                            <td>

                                <span class="status-badge">

                                    <?= clean($c['status'] ?? '') ?>

                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="4">
                            No recent complaints found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- ================= SCRIPT ================= -->

<script>

function openPerformanceTab(evt, tabId){

    let i;

    let tabcontent =
        document.getElementsByClassName("performance-content");

    for(i = 0; i < tabcontent.length; i++){

        tabcontent[i].classList.remove("active-tab");
    }

    let tabbuttons =
        document.getElementsByClassName("tab-btn");

    for(i = 0; i < tabbuttons.length; i++){

        tabbuttons[i].classList.remove("active");
    }

    document
        .getElementById(tabId)
        .classList.add("active-tab");

    evt.currentTarget.classList.add("active");
}

</script>

<style>

/* =========================================
   THEME
========================================= */

:root{
    --primary:#0a2a43;
    --primary-soft:#eef4f8;
    --primary-light:#f4f7fb;
    --border:#dbe3ee;
    --text:#334155;
}

/* =========================================
   PAGE
========================================= */

.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:22px;
    background:#f4f7fb;
    min-height:calc(100vh - 135px);
    font-family:'Segoe UI',Tahoma,sans-serif;
}

/* =========================================
   HEADER
========================================= */

.module-header{
    background:#ffffff;
    border-radius:18px;
    padding:26px;
    margin-bottom:22px;

    /* LEFT OVERLAY */
    border-left:6px solid var(--primary);

    /* LIGHT BORDER */
    border-top:1px solid var(--border);
    border-right:1px solid var(--border);
    border-bottom:1px solid var(--border);

    box-shadow:0 8px 20px rgba(0,0,0,0.05);
}

.module-header h2{
    margin:0;
    font-size:26px;
    font-weight:700;
    color:var(--primary);
}

.module-header p{
    margin:8px 0 0;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

/* =========================================
   FILTER
========================================= */

.filter-card{
    background:#fff;
    border-radius:16px;
    padding:18px 20px;
    margin-bottom:22px;
    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:16px;
    border:1px solid var(--border);
    box-shadow:0 4px 16px rgba(15,23,42,0.05);
}

.filter-card label{
    display:block;
    margin-bottom:6px;
    font-size:13px;
    font-weight:600;
    color:#475569;
}

.filter-card input{
    min-width:180px;
    padding:11px 12px;
    border-radius:10px;
    border:1px solid var(--border);
    background:#f8fafc;
}

.filter-card input:focus{
    outline:none;
    border-color:var(--primary);
    background:#fff;
}

.filter-card button{
    border:none;
    background:var(--primary);
    color:#fff;
    padding:11px 18px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
}

/* =========================================
   KPI GRID
========================================= */

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:18px;
    margin-bottom:24px;
}

.kpi-card{
    position:relative;
    overflow:hidden;
    background:#fff;
    border-radius:18px;
    padding:22px;
    border:1px solid var(--border);
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
}

.kpi-card::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:5px;
    background:var(--primary);
}

.kpi-card span{
    display:block;
    color:#64748b;
    font-size:13px;
    font-weight:600;
    margin-bottom:10px;
    text-transform:uppercase;
}

.kpi-card strong{
    display:block;
    font-size:28px;
    color:var(--primary);
    margin-bottom:8px;
    font-weight:700;
}

.kpi-card small{
    color:#475569;
    font-size:13px;
}

/* =========================================
   REPORT CARD
========================================= */

.report-card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    border:1px solid var(--border);
    box-shadow:0 6px 18px rgba(15,23,42,0.05);
    overflow-x:auto;
    margin-bottom:22px;
}

.report-card h3{
    margin:0 0 18px;
    font-size:18px;
    color:var(--primary);
    font-weight:700;
}

/* =========================================
   PERFORMANCE TABS
========================================= */

.performance-tabs{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-bottom:20px;
}

.tab-btn{
    border:none;
    background:#ffffff;
    color:var(--primary);
    padding:12px 18px;
    border-radius:12px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    border:1px solid var(--border);
    transition:0.25s ease;
}

.tab-btn:hover{
    background:var(--primary-soft);
}

.tab-btn.active{
    background:var(--primary);
    color:#ffffff;
    border-color:var(--primary);
}

/* =========================================
   TAB CONTENT
========================================= */

.performance-content{
    display:none;
    animation:fadeEffect 0.3s ease;
}

.performance-content.active-tab{
    display:block;
}

/* =========================================
   METRIC ROWS
========================================= */

.metric-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 0;
    border-bottom:1px solid #edf2f7;
}

.metric-row:last-child{
    border-bottom:none;
}

.metric-row span{
    color:#475569;
    font-size:14px;
}

.metric-row strong{
    color:var(--primary);
    font-size:15px;
    font-weight:700;
}

/* =========================================
   RISK FLAGS
========================================= */

.risk-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:14px;
}

.risk-item{
    background:#fff;
    border-left:5px solid var(--primary);
    padding:16px;
    border-radius:12px;
    font-size:14px;
    color:#475569;
    box-shadow:0 4px 10px rgba(0,0,0,0.04);
}

/* =========================================
   TABLES
========================================= */

.report-grid.two{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(360px,1fr));
    gap:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

thead{
    background:#f8fafc;
}

th{
    padding:13px 12px;
    text-align:left;
    color:#334155;
    font-size:13px;
    font-weight:700;
    border-bottom:1px solid var(--border);
}

td{
    padding:13px 12px;
    border-bottom:1px solid #edf2f7;
    color:#475569;
}

tbody tr:hover{
    background:#f8fafc;
}

/* =========================================
   STATUS BADGES
========================================= */

.status-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:6px 12px;
    border-radius:30px;
    font-size:12px;
    font-weight:700;
    background:var(--primary-soft);
    color:var(--primary);
}

/* =========================================
   EMPTY
========================================= */

.empty{
    color:#94a3b8;
    font-size:14px;
    padding:14px 0;
}

/* =========================================
   ANIMATION
========================================= */

@keyframes fadeEffect{

    from{
        opacity:0;
        transform:translateY(6px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:992px){

    .page-content{
        margin-left:0;
        padding:15px;
    }

    .filter-card{
        flex-direction:column;
        align-items:stretch;
    }

    .filter-card input{
        width:100%;
    }

    .kpi-grid{
        grid-template-columns:repeat(auto-fit,minmax(100%,1fr));
    }

    .performance-tabs{
        flex-direction:column;
    }
}

</style>
```
