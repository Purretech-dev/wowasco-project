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
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists($conn, $table, $column){
    if (!tableExists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function countRows($conn, $table, $condition = "1=1"){
    if (!tableExists($conn, $table)) return 0;
    $res = $conn->query("SELECT COUNT(*) AS total FROM `$table` WHERE $condition");
    return $res ? (int)$res->fetch_assoc()['total'] : 0;
}

function sumColumn($conn, $table, $column, $condition = "1=1"){
    if (!tableExists($conn, $table) || !columnExists($conn, $table, $column)) return 0;
    $res = $conn->query("SELECT SUM(`$column`) AS total FROM `$table` WHERE $condition");
    return $res ? (float)($res->fetch_assoc()['total'] ?? 0) : 0;
}

function money($amount){
    return 'KSh ' . number_format((float)$amount, 2);
}

/* ================= DATE FILTER ================= */

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

/* ================= METERS ================= */

$totalMeters = countRows($conn, 'meters');
$activeMeters = countRows($conn, 'meters', "status='Active'");
$inactiveMeters = countRows($conn, 'meters', "status='Inactive'");
$smartMeters = columnExists($conn, 'meters', 'meter_type') ? countRows($conn, 'meters', "meter_type LIKE '%Smart%'") : 0;
$conventionalMeters = columnExists($conn, 'meters', 'meter_type') ? countRows($conn, 'meters', "meter_type LIKE '%Conventional%'") : 0;

/* ================= CUSTOMERS ================= */

$totalCustomers = tableExists($conn, 'customers') ? countRows($conn, 'customers') : countRows($conn, 'customer');
$totalApplications = countRows($conn, 'meter_applications');
$pendingApplications = countRows($conn, 'meter_applications', "status IN ('Pending','Submitted','Under Review')");
$approvedApplications = countRows($conn, 'meter_applications', "status='Approved'");
$rejectedApplications = countRows($conn, 'meter_applications', "status='Rejected'");

/* ================= CUSTOMER RELATIONS ================= */

$totalComplaints = countRows($conn, 'customer_complaints');
$openComplaints = countRows($conn, 'customer_complaints', "status IN ('Submitted','Assigned','In Progress','Escalated')");
$resolvedComplaints = countRows($conn, 'customer_complaints', "status IN ('Resolved','Closed')");
$escalatedComplaints = countRows($conn, 'customer_complaints', "status='Escalated'");
$totalEnquiries = countRows($conn, 'customer_enquiries');
$openEnquiries = countRows($conn, 'customer_enquiries', "status IN ('Submitted','Open','In Progress')");

/* ================= ASSETS ================= */

$totalAssets = countRows($conn, 'assets', columnExists($conn, 'assets', 'is_deleted') ? "is_deleted=0" : "1=1");
$assetValue = sumColumn($conn, 'assets', 'asset_value', columnExists($conn, 'assets', 'is_deleted') ? "is_deleted=0" : "1=1");
$netAssetValue = sumColumn($conn, 'assets', 'net_value', columnExists($conn, 'assets', 'is_deleted') ? "is_deleted=0" : "1=1");
$inactiveAssets = countRows($conn, 'assets', "status='Inactive'");

/* ================= ZONES ================= */

$totalZones = countRows($conn, 'zones');
$activeZones = countRows($conn, 'zones', "status='Active'");
$zonesUnderMaintenance = countRows($conn, 'zones', "status='Under Maintenance'");
$activeZoneMaintenance = countRows($conn, 'zone_maintenance', "status IN ('Open','In Progress')");

/* ================= PUMPED VOLUMES ================= */

$pumpedTable = tableExists($conn, 'pumped_volumes') ? 'pumped_volumes' : (tableExists($conn, 'pumped_volume') ? 'pumped_volume' : '');

$totalPumped = 0;
$monthlyPumped = 0;

if ($pumpedTable) {
    $volumeCol = columnExists($conn, $pumpedTable, 'volume') ? 'volume' :
        (columnExists($conn, $pumpedTable, 'pumped_volume') ? 'pumped_volume' :
        (columnExists($conn, $pumpedTable, 'quantity') ? 'quantity' : ''));

    $dateCol = columnExists($conn, $pumpedTable, 'record_date') ? 'record_date' :
        (columnExists($conn, $pumpedTable, 'date_recorded') ? 'date_recorded' :
        (columnExists($conn, $pumpedTable, 'created_at') ? 'created_at' : ''));

    if ($volumeCol) {
        $totalPumped = sumColumn($conn, $pumpedTable, $volumeCol);
        if ($dateCol) {
            $monthlyPumped = sumColumn($conn, $pumpedTable, $volumeCol, "DATE(`$dateCol`) BETWEEN '$from' AND '$to'");
        }
    }
}

/* ================= BILLING / REVENUE ================= */

$totalBilled = sumColumn($conn, 'bills', 'amount');
$paidBills = columnExists($conn, 'bills', 'status') ? sumColumn($conn, 'bills', 'amount', "status='Paid'") : 0;
$unpaidBills = columnExists($conn, 'bills', 'status') ? sumColumn($conn, 'bills', 'amount', "status!='Paid'") : 0;

/* ================= RATIOS ================= */

$meterActiveRate = $totalMeters > 0 ? round(($activeMeters / $totalMeters) * 100, 1) : 0;
$complaintResolutionRate = $totalComplaints > 0 ? round(($resolvedComplaints / $totalComplaints) * 100, 1) : 0;
$applicationApprovalRate = $totalApplications > 0 ? round(($approvedApplications / $totalApplications) * 100, 1) : 0;
$revenueEfficiency = $totalPumped > 0 ? round($totalBilled / $totalPumped, 2) : 0;

/* ================= ZONE PERFORMANCE ================= */

$zoneMeterData = [];

if (tableExists($conn, 'meters') && columnExists($conn, 'meters', 'zone')) {
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

/* ================= COMPLAINTS BY ZONE ================= */

$complaintsByZone = [];

if (tableExists($conn, 'customer_complaints') && columnExists($conn, 'customer_complaints', 'zone')) {
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
    ? $conn->query("SELECT * FROM meter_applications ORDER BY id DESC LIMIT 6")
    : null;

$recentComplaints = tableExists($conn, 'customer_complaints')
    ? $conn->query("SELECT * FROM customer_complaints ORDER BY id DESC LIMIT 6")
    : null;

/* ================= RISK FLAGS ================= */

$riskFlags = [];

if ($inactiveMeters > 0) {
    $riskFlags[] = "$inactiveMeters meters are currently inactive.";
}

if ($openComplaints > 0) {
    $riskFlags[] = "$openComplaints customer complaints are still open.";
}

if ($pendingApplications > 0) {
    $riskFlags[] = "$pendingApplications meter applications are awaiting action.";
}

if ($activeZoneMaintenance > 0) {
    $riskFlags[] = "$activeZoneMaintenance zone maintenance cases are active.";
}

if ($unpaidBills > 0) {
    $riskFlags[] = "Outstanding unpaid bills total " . money($unpaidBills) . ".";
}

if ($totalPumped > 0 && $totalBilled <= 0) {
    $riskFlags[] = "Pumped volume exists but billing revenue is missing or not recorded.";
}

if (empty($riskFlags)) {
    $riskFlags[] = "No major operational risk detected from available data.";
}
?>

<div class="page-content">

    <div class="module-header">
        <div>
            <h2>Managing Director Dashboard</h2>
            <p>Executive reporting dashboard covering meters, customers, assets, pumped volumes, zones and customer relations.</p>
        </div>

        <button onclick="window.print()" class="print-btn">Print Dashboard</button>
    </div>

    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="<?= clean($_GET['page'] ?? 'advanced_reports/md_dashboard') ?>">

        <div>
            <label>From</label>
            <input type="date" name="from" value="<?= clean($from) ?>">
        </div>

        <div>
            <label>To</label>
            <input type="date" name="to" value="<?= clean($to) ?>">
        </div>

        <button type="submit">Apply Filter</button>
    </form>

    <div class="kpi-grid">

        <div class="kpi-card">
            <span>Total Meters</span>
            <strong><?= number_format($totalMeters) ?></strong>
            <small><?= $meterActiveRate ?>% active</small>
        </div>

        <div class="kpi-card">
            <span>Total Customers</span>
            <strong><?= number_format($totalCustomers) ?></strong>
            <small>Registered customer records</small>
        </div>

        <div class="kpi-card">
            <span>Total Pumped Volume</span>
            <strong><?= number_format($totalPumped, 2) ?></strong>
            <small>Monthly: <?= number_format($monthlyPumped, 2) ?></small>
        </div>

        <div class="kpi-card">
            <span>Total Billed</span>
            <strong><?= money($totalBilled) ?></strong>
            <small>Revenue efficiency: <?= $revenueEfficiency ?></small>
        </div>

        <div class="kpi-card">
            <span>Total Assets</span>
            <strong><?= number_format($totalAssets) ?></strong>
            <small>Value: <?= money($assetValue) ?></small>
        </div>

        <div class="kpi-card">
            <span>Net Asset Value</span>
            <strong><?= money($netAssetValue) ?></strong>
            <small><?= $inactiveAssets ?> inactive assets</small>
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
            <span>Zones</span>
            <strong><?= number_format($totalZones) ?></strong>
            <small><?= $activeZones ?> active, <?= $zonesUnderMaintenance ?> under maintenance</small>
        </div>

        <div class="kpi-card">
            <span>Open Enquiries</span>
            <strong><?= number_format($openEnquiries) ?></strong>
            <small><?= number_format($totalEnquiries) ?> total enquiries</small>
        </div>

    </div>

    <div class="report-grid two">

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

    <div class="report-grid two">

        <div class="report-card">
            <h3>Top Zones by Meter Count</h3>

            <?php if (!empty($zoneMeterData)): ?>
                <?php foreach ($zoneMeterData as $z): ?>
                    <div class="metric-row">
                        <span><?= clean($z['zone']) ?></span>
                        <strong><?= number_format($z['total_meters']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">No zone meter data available.</p>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <h3>Complaint Hotspots</h3>

            <?php if (!empty($complaintsByZone)): ?>
                <?php foreach ($complaintsByZone as $c): ?>
                    <div class="metric-row">
                        <span><?= clean($c['zone']) ?></span>
                        <strong><?= number_format($c['total_complaints']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">No complaint zone data available.</p>
            <?php endif; ?>
        </div>

    </div>

    <div class="report-card">
        <h3>Executive Risk Flags</h3>

        <div class="risk-list">
            <?php foreach ($riskFlags as $risk): ?>
                <div class="risk-item"><?= clean($risk) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="report-grid two">

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
                    <?php if ($recentApplications && $recentApplications->num_rows > 0): ?>
                        <?php while ($a = $recentApplications->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean($a['application_ref'] ?? '') ?></td>
                                <td><?= clean($a['customer_name'] ?? '') ?></td>
                                <td><?= clean($a['zone'] ?? '') ?></td>
                                <td><span class="status-badge"><?= clean($a['status'] ?? '') ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No recent applications found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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
                    <?php if ($recentComplaints && $recentComplaints->num_rows > 0): ?>
                        <?php while ($c = $recentComplaints->fetch_assoc()): ?>
                            <tr>
                                <td><?= clean($c['complaint_ref'] ?? '') ?></td>
                                <td><?= clean($c['customer_name'] ?? '') ?></td>
                                <td><?= clean($c['complaint_type'] ?? '') ?></td>
                                <td><span class="status-badge"><?= clean($c['status'] ?? '') ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No recent complaints found.</td></tr>
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
.filter-card,
.kpi-card,
.report-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.04);
}

.module-header{
    padding:18px 20px;
    margin-bottom:18px;
    border-left:4px solid #0a2a43;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
}

.module-header h2{
    margin:0;
    color:#0a2a43;
    font-size:20px;
}

.module-header p{
    margin:6px 0 0;
    color:#64748b;
    font-size:14px;
}

.print-btn,
.filter-card button{
    background:#0a2a43;
    color:#fff;
    border:none;
    padding:9px 14px;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
}

.filter-card{
    padding:14px;
    margin-bottom:18px;
    display:flex;
    align-items:end;
    gap:12px;
    flex-wrap:wrap;
}

.filter-card label{
    display:block;
    font-size:13px;
    font-weight:600;
    color:#334155;
    margin-bottom:5px;
}

.filter-card input{
    padding:9px;
    border:1px solid #d1d5db;
    border-radius:6px;
}

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
    gap:12px;
    margin-bottom:18px;
}

.kpi-card{
    padding:16px;
}

.kpi-card span{
    display:block;
    font-size:13px;
    color:#64748b;
    margin-bottom:8px;
}

.kpi-card strong{
    display:block;
    color:#0a2a43;
    font-size:22px;
    margin-bottom:5px;
}

.kpi-card small{
    color:#64748b;
}

.report-grid{
    display:grid;
    gap:18px;
    margin-bottom:18px;
}

.report-grid.two{
    grid-template-columns:repeat(auto-fit,minmax(330px,1fr));
}

.report-card{
    padding:18px;
    margin-bottom:18px;
    overflow-x:auto;
}

.report-card h3{
    margin:0 0 14px;
    color:#0a2a43;
    font-size:17px;
}

.metric-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid #e5e7eb;
    padding:10px 0;
    font-size:14px;
}

.metric-row span{
    color:#475569;
}

.metric-row strong{
    color:#0a2a43;
}

.risk-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:10px;
}

.risk-item{
    background:#f8fafc;
    border-left:4px solid #0a2a43;
    border-radius:8px;
    padding:12px;
    color:#334155;
    font-size:14px;
}

.empty{
    color:#64748b;
    font-size:14px;
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

@media print{
    .page-content{
        margin:0;
        padding:10px;
        background:white;
    }

    .print-btn,
    .filter-card{
        display:none;
    }

    .module-header,
    .kpi-card,
    .report-card{
        box-shadow:none;
    }
}
</style>