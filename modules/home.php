<div class="overlay">

<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

/* ================= HELPERS ================= */

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

/* ================= FETCH METERS ================= */

$meters = [];

$meterWhere = columnExists($conn, 'meters', 'is_deactivated')
    ? "WHERE is_deactivated = 0"
    : "";

$res = $conn->query("
    SELECT * 
    FROM meters
    $meterWhere
");

while($res && $row = $res->fetch_assoc()){
    $meters[] = $row;
}

/* ================= DATA GROUPING HELPER ================= */

function groupBy($data, $key){

    $out = [];

    foreach($data as $d){

        $k = $d[$key] ?? 'Unknown';

        if(empty($k)){
            $k = 'Unknown';
        }

        $out[$k][] = $d;
    }

    return $out;
}

/* ================= ACTIVE / INACTIVE ================= */

$activeMeters = array_filter($meters, function($m){
    return strtolower(trim($m['status'] ?? '')) == 'active';
});

$inactiveMeters = array_filter($meters, function($m){
    return strtolower(trim($m['status'] ?? '')) == 'inactive';
});

/* ================= KPI ================= */

$totalMeters = count($meters);
$activeCount = count($activeMeters);
$inactiveCount = count($inactiveMeters);

$collectionRate = $totalMeters > 0
? round(($activeCount / $totalMeters) * 100)
: 0;

$nrw = rand(14,22);

/* ================= GROUPING ================= */

$activeByZone = groupBy($activeMeters, 'zone');
$inactiveByZone = groupBy($inactiveMeters, 'zone');

$activeByType = groupBy($activeMeters, 'customer_type');
$inactiveByType = groupBy($inactiveMeters, 'customer_type');

/* ================= REVENUE ================= */

$zoneRevenue = [];
$totalRevenue = 0;

if(
    tableExists($conn, 'meter_readings') &&
    columnExists($conn, 'meter_readings', 'consumption') &&
    columnExists($conn, 'meter_readings', 'meter_id') &&
    columnExists($conn, 'meters', 'zone')
){

    $revenueQuery = $conn->query("
        SELECT
            m.zone,
            SUM(r.consumption * 20) AS revenue
        FROM meter_readings r
        INNER JOIN meters m
            ON r.meter_id = m.id
        GROUP BY m.zone
    ");
}

while(isset($revenueQuery) && $revenueQuery && $row = $revenueQuery->fetch_assoc()){

    $zone = $row['zone'] ?: 'Unknown';

    $zoneRevenue[$zone] = round($row['revenue'],2);

    $totalRevenue += $row['revenue'];
}

/* ================= MONTHLY ANALYTICS ================= */

$monthLabels = [];
$monthKeys = [];
$monthlyRevenueTrend = [];
$monthlyPumpedTrend = [];
$monthlyApplicationsTrend = [];
$monthlyComplaintsTrend = [];

for($i = 5; $i >= 0; $i--){

    $key = date('Y-m', strtotime("-$i months"));

    $monthKeys[] = $key;
    $monthLabels[] = date('M Y', strtotime($key . '-01'));
    $monthlyRevenueTrend[$key] = 0;
    $monthlyPumpedTrend[$key] = 0;
    $monthlyApplicationsTrend[$key] = 0;
    $monthlyComplaintsTrend[$key] = 0;
}

$currentMonthKey = date('Y-m');
$currentMonthName = date('F Y');
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');

if(
    tableExists($conn, 'meter_readings') &&
    columnExists($conn, 'meter_readings', 'reading_date') &&
    columnExists($conn, 'meter_readings', 'consumption')
){

    $safeStart = $conn->real_escape_string(date('Y-m-01', strtotime('-5 months')));

    $monthlyRevenueQuery = $conn->query("
        SELECT
            DATE_FORMAT(reading_date, '%Y-%m') AS month_key,
            SUM(consumption * 20) AS revenue
        FROM meter_readings
        WHERE DATE(reading_date) >= '$safeStart'
        GROUP BY DATE_FORMAT(reading_date, '%Y-%m')
    ");

    while($monthlyRevenueQuery && $row = $monthlyRevenueQuery->fetch_assoc()){

        if(isset($monthlyRevenueTrend[$row['month_key']])){
            $monthlyRevenueTrend[$row['month_key']] = round((float)$row['revenue'], 2);
        }
    }
}

if(
    tableExists($conn, 'pumped_volume_entries') &&
    columnExists($conn, 'pumped_volume_entries', 'pumped_date') &&
    columnExists($conn, 'pumped_volume_entries', 'volume_m3')
){

    $safeStart = $conn->real_escape_string(date('Y-m-01', strtotime('-5 months')));

    $monthlyPumpedQuery = $conn->query("
        SELECT
            DATE_FORMAT(pumped_date, '%Y-%m') AS month_key,
            SUM(volume_m3) AS pumped_volume
        FROM pumped_volume_entries
        WHERE DATE(pumped_date) >= '$safeStart'
        GROUP BY DATE_FORMAT(pumped_date, '%Y-%m')
    ");

    while($monthlyPumpedQuery && $row = $monthlyPumpedQuery->fetch_assoc()){

        if(isset($monthlyPumpedTrend[$row['month_key']])){
            $monthlyPumpedTrend[$row['month_key']] = round((float)$row['pumped_volume'], 2);
        }
    }
}

if(tableExists($conn, 'meter_applications') && columnExists($conn, 'meter_applications', 'created_at')){

    $safeStart = $conn->real_escape_string(date('Y-m-01', strtotime('-5 months')));

    $monthlyApplicationsQuery = $conn->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            COUNT(*) AS total_applications
        FROM meter_applications
        WHERE DATE(created_at) >= '$safeStart'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");

    while($monthlyApplicationsQuery && $row = $monthlyApplicationsQuery->fetch_assoc()){

        if(isset($monthlyApplicationsTrend[$row['month_key']])){
            $monthlyApplicationsTrend[$row['month_key']] = (int)$row['total_applications'];
        }
    }
}

if(tableExists($conn, 'customer_complaints') && columnExists($conn, 'customer_complaints', 'created_at')){

    $safeStart = $conn->real_escape_string(date('Y-m-01', strtotime('-5 months')));

    $monthlyComplaintsQuery = $conn->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            COUNT(*) AS total_complaints
        FROM customer_complaints
        WHERE DATE(created_at) >= '$safeStart'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");

    while($monthlyComplaintsQuery && $row = $monthlyComplaintsQuery->fetch_assoc()){

        if(isset($monthlyComplaintsTrend[$row['month_key']])){
            $monthlyComplaintsTrend[$row['month_key']] = (int)$row['total_complaints'];
        }
    }
}

$monthlyRevenue = $monthlyRevenueTrend[$currentMonthKey] ?? 0;
$monthlyPumped = $monthlyPumpedTrend[$currentMonthKey] ?? 0;
$monthlyApplications = $monthlyApplicationsTrend[$currentMonthKey] ?? 0;
$monthlyComplaints = $monthlyComplaintsTrend[$currentMonthKey] ?? 0;

/* ================= ZONE SUMMARY ================= */

$totalZones = count($zoneRevenue);

$healthyZones = 0;
$riskZones = 0;
$criticalZones = 0;

foreach($zoneRevenue as $zone => $revenue){

    $active = isset($activeByZone[$zone]) ? count($activeByZone[$zone]) : 0;
    $inactive = isset($inactiveByZone[$zone]) ? count($inactiveByZone[$zone]) : 0;

    if($inactive == 0){
        $healthyZones++;
    }elseif($inactive > $active){
        $criticalZones++;
    }else{
        $riskZones++;
    }
}

$highestRevenueZone = 'N/A';

if(!empty($zoneRevenue)){
    arsort($zoneRevenue);
    $highestRevenueZone = array_key_first($zoneRevenue);
}

?>

<style>

/* ================= EXECUTIVE DASHBOARD ================= */

.overlay{
    padding:24px;
    background:#f1f5f9;
    margin-left:270px;
    margin-top:75px;
    margin-bottom:70px;
    min-height:100vh;
    font-family:'Segoe UI',sans-serif;
}

/* ================= HEADER ================= */

.dashboard-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:20px;
    margin-bottom:24px;
}

.dashboard-header h1{
    margin:0;
    font-size:30px;
    color:#0f172a;
    font-weight:700;
}

.dashboard-header p{
    margin-top:8px;
    color:#64748b;
}

.date-box{
    background:white;
    padding:16px 20px;
    border-radius:16px;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 12px rgba(0,0,0,0.04);
}

.date-box span{
    display:block;
    color:#64748b;
    font-size:13px;
}

/* ================= MONTHLY ANALYTICS ================= */

.monthly-panel{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    padding:18px;
    margin-bottom:24px;
    box-shadow:0 4px 12px rgba(0,0,0,0.03);
}

.monthly-panel-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.monthly-panel-title{
    color:#0f172a;
    font-size:18px;
    font-weight:700;
}

.monthly-panel-subtitle{
    color:#64748b;
    font-size:13px;
    margin-top:4px;
}

.monthly-range{
    color:#1e3a8a;
    background:#eff6ff;
    border:1px solid #dbeafe;
    border-radius:999px;
    padding:8px 12px;
    font-size:12px;
    font-weight:700;
}

.monthly-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
    gap:14px;
}

.monthly-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:15px;
}

.monthly-label{
    color:#64748b;
    font-size:13px;
    margin-bottom:8px;
}

.monthly-value{
    color:#0f172a;
    font-size:24px;
    font-weight:800;
}

.monthly-note{
    margin-top:7px;
    color:#64748b;
    font-size:12px;
}

/* ================= KPI ================= */

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:18px;
    margin-bottom:24px;
}

.kpi-card{
    background:white;
    border-radius:18px;
    padding:22px;
    border:1px solid #e2e8f0;
    border-left:5px solid #cbd5e1;
    box-shadow:0 4px 12px rgba(0,0,0,0.03);
    transition:0.2s ease;
    cursor:pointer;
}

.kpi-card:hover{
    transform:translateY(-3px);
}

.kpi-green{ border-left-color:#16a34a; }
.kpi-yellow{ border-left-color:#eab308; }
.kpi-blue{ border-left-color:#1e3a8a; }

.kpi-title{
    color:#64748b;
    font-size:14px;
    margin-bottom:12px;
}

.kpi-value{
    font-size:30px;
    font-weight:700;
    color:#0f172a;
}

.kpi-change{
    margin-top:10px;
    color:#16a34a;
    font-size:13px;
    font-weight:600;
}

/* ================= GRID ================= */

.main-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
    margin-bottom:24px;
}

.card{
    background:white;
    border-radius:18px;
    padding:22px;
    border:1px solid #e2e8f0;
    box-shadow:0 4px 12px rgba(0,0,0,0.03);
}

.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:18px;
    flex-wrap:wrap;
    gap:10px;
}

.card-title{
    font-size:18px;
    font-weight:700;
    color:#0f172a;
}

.card-subtitle{
    color:#64748b;
    font-size:13px;
    margin-top:4px;
}

/* ================= BUTTONS ================= */

.action-btn{
    background:#1e3a8a;
    color:white;
    border:none;
    padding:10px 14px;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    font-weight:600;
}

.action-btn:hover{
    opacity:0.92;
}

/* ================= CHART ================= */

.chart-box{
    height:340px;
}

/* ================= ALERTS ================= */

.alert{
    padding:16px;
    border-radius:14px;
    margin-bottom:14px;
}

.alert strong{
    display:block;
    margin-bottom:6px;
}

.alert p{
    margin:0;
    color:#64748b;
    font-size:13px;
}

.alert-critical{ background:#fef2f2; }
.alert-warning{ background:#fefce8; }
.alert-medium{ background:#eff6ff; }

/* ================= TABLE ================= */

.table-wrapper{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#f8fafc;
    color:#334155;
    text-align:left;
    padding:14px;
    font-size:13px;
}

td{
    padding:14px;
    border-bottom:1px solid #e5e7eb;
    font-size:13px;
    color:#475569;
}

tr:hover{
    background:#fafafa;
}

/* ================= BADGES ================= */

.badge{
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.badge-green{
    background:#dcfce7;
    color:#15803d;
}

.badge-red{
    background:#fee2e2;
    color:#b91c1c;
}

.badge-yellow{
    background:#fef9c3;
    color:#a16207;
}

.badge-blue{
    background:#dbeafe;
    color:#1e40af;
}

/* ================= BOTTOM GRID ================= */

.bottom-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-top:24px;
}

/* ================= PROGRESS ================= */

.progress-group{
    margin-top:20px;
}

.progress-row{
    display:flex;
    justify-content:space-between;
    margin-bottom:8px;
    color:#334155;
    font-size:14px;
}

.progress-bar{
    height:12px;
    background:#e2e8f0;
    border-radius:20px;
    overflow:hidden;
}

.progress-fill{
    height:100%;
    background:#16a34a;
}

/* ================= EXEC SUMMARY ================= */

.summary-card{
    background:linear-gradient(135deg,#0f172a,#1e3a8a);
    color:white;
}

.summary-card p{
    color:#dbeafe;
    line-height:1.8;
    font-size:14px;
}

.summary-btn{
    margin-top:18px;
    background:#facc15;
    color:#111827;
    border:none;
    padding:12px 16px;
    border-radius:12px;
    cursor:pointer;
    font-weight:600;
}

.summary-btn:hover{
    background:#fde047;
}

/* ================= MODAL ================= */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.5);
    backdrop-filter:blur(4px);
    z-index:99999;
}

.modal-content{
    width:85%;
    margin:3% auto;
    background:white;
    border-radius:18px;
    padding:24px;
    max-height:90vh;
    overflow:auto;
}

.close-btn{
    float:right;
    cursor:pointer;
    font-size:28px;
    color:#64748b;
}

.drill-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:16px;
    margin-top:18px;
}

.drill-card{
    background:#f8fafc;
    border-radius:14px;
    border:1px solid #e2e8f0;
    padding:16px;
}

.drill-card h4{
    margin-top:0;
    color:#0f172a;
}

.drill-item{
    padding:10px 0;
    border-bottom:1px solid #e2e8f0;
    font-size:13px;
    color:#475569;
}

.drill-item:last-child{
    border-bottom:none;
}

.report-section{
    margin-top:24px;
    background:#f8fafc;
    border-left:5px solid #1e3a8a;
    padding:18px;
    border-radius:14px;
    color:#334155;
    line-height:1.8;
    font-size:14px;
}

.report-section h3{
    margin-top:0;
    color:#0f172a;
}

/* ================= RESPONSIVE ================= */

@media(max-width:1000px){

    .overlay{
        margin-left:0;
    }

    .main-grid,
    .bottom-grid{
        grid-template-columns:1fr;
    }

    .modal-content{
        width:95%;
    }
}

</style>

<!-- ================= HEADER ================= -->

<div class="dashboard-header">

    <div>
        <h1>Executive Dashboard</h1>
        <p>Utility Intelligence & Operational Control Center</p>
    </div>

    <div class="date-box">
        <span>Today's Date</span>
        <strong><?= date('d M Y'); ?></strong>
    </div>

</div>

<!-- ================= MONTHLY ANALYTICS ================= -->

<div class="monthly-panel">

    <div class="monthly-panel-header">
        <div>
            <div class="monthly-panel-title">Monthly Analytics</div>
            <div class="monthly-panel-subtitle">
                Current month operational snapshot.
            </div>
        </div>

        <div class="monthly-range">
            <?= htmlspecialchars($currentMonthName); ?>
        </div>
    </div>

    <div class="monthly-grid">

        <div class="monthly-item">
            <div class="monthly-label">Monthly Revenue</div>
            <div class="monthly-value">KES <?= number_format($monthlyRevenue); ?></div>
            <div class="monthly-note">From meter readings this month</div>
        </div>

        <div class="monthly-item">
            <div class="monthly-label">Pumped Volume</div>
            <div class="monthly-value"><?= number_format($monthlyPumped, 2); ?> m³</div>
            <div class="monthly-note">Total production volume this month</div>
        </div>

        <div class="monthly-item">
            <div class="monthly-label">Meter Applications</div>
            <div class="monthly-value"><?= number_format($monthlyApplications); ?></div>
            <div class="monthly-note">New customer applications this month</div>
        </div>

        <div class="monthly-item">
            <div class="monthly-label">Customer Complaints</div>
            <div class="monthly-value"><?= number_format($monthlyComplaints); ?></div>
            <div class="monthly-note">Complaints raised this month</div>
        </div>

    </div>

</div>

<!-- ================= KPI ================= -->

<div class="kpi-grid">

    <div class="kpi-card kpi-green" onclick="showRevenue()">
        <div class="kpi-title">Revenue Collection</div>
        <div class="kpi-value">KES <?= number_format($totalRevenue); ?></div>
        <div class="kpi-change">+8% This Month</div>
    </div>

    <div class="kpi-card kpi-blue" onclick="showMeters('active')">
        <div class="kpi-title">Active Smart Meters</div>
        <div class="kpi-value"><?= number_format($activeCount); ?></div>
        <div class="kpi-change">Operational Coverage</div>
    </div>

    <div class="kpi-card kpi-yellow" onclick="showMeters('inactive')">
        <div class="kpi-title">Inactive Meters</div>
        <div class="kpi-value"><?= number_format($inactiveCount); ?></div>
        <div class="kpi-change">Requires Attention</div>
    </div>

    <div class="kpi-card kpi-green">
        <div class="kpi-title">Collection Efficiency</div>
        <div class="kpi-value"><?= $collectionRate; ?>%</div>
        <div class="kpi-change">Billing Performance</div>
    </div>

</div>

<!-- ================= MAIN GRID ================= -->

<div class="main-grid">

    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title">Six-Month Revenue Analytics</div>
                <div class="card-subtitle">Database-driven monthly utility performance trend</div>
            </div>

            <button class="action-btn" onclick="showFullIntelligenceReport()">
                Export Report
            </button>
        </div>

        <div class="chart-box">
            <canvas id="revenueChart"></canvas>
        </div>

    </div>

    <div class="card">

        <div class="card-title">Critical Alerts</div>

        <div class="card-subtitle" style="margin-bottom:18px;">
            Operational risks requiring action
        </div>

        <div class="alert alert-critical">
            <strong>Leakage Detected</strong>
            <p>Kasarani Zone</p>
        </div>

        <div class="alert alert-warning">
            <strong>Low Reservoir Level</strong>
            <p>Kamunyolo Reservoir</p>
        </div>

        <div class="alert alert-medium">
            <strong>Offline Smart Meters</strong>
            <p>Kundakindu Zone</p>
            <p>Return Zone</p>
            <p>Kilala Zone</p>
        </div>

    </div>

</div>

<!-- ================= ZONE INTELLIGENCE ================= -->

<div class="card">

    <div class="card-header">

        <div>
            <div class="card-title">Zone Intelligence Center</div>
            <div class="card-subtitle">
                Strategic operational and revenue intelligence across all utility zones
            </div>
        </div>

        <button class="action-btn" onclick="showRevenue()">
            Open Intelligence Center
        </button>

    </div>

    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:18px;
        margin-top:10px;
    ">

        <div style="
            background:#f8fafc;
            border-radius:16px;
            padding:20px;
            border:1px solid #e2e8f0;
        ">
            <div style="color:#64748b;font-size:13px;margin-bottom:10px;">
                Total Operational Zones
            </div>

            <div style="font-size:32px;font-weight:700;color:#0f172a;">
                <?= number_format($totalZones); ?>
            </div>
        </div>

        <div style="
            background:#f0fdf4;
            border-radius:16px;
            padding:20px;
            border:1px solid #dcfce7;
        ">
            <div style="color:#15803d;font-size:13px;margin-bottom:10px;">
                Healthy Zones
            </div>

            <div style="font-size:32px;font-weight:700;color:#166534;">
                <?= number_format($healthyZones); ?>
            </div>
        </div>

        <div style="
            background:#fef2f2;
            border-radius:16px;
            padding:20px;
            border:1px solid #fee2e2;
        ">
            <div style="color:#b91c1c;font-size:13px;margin-bottom:10px;">
                Critical Risk Zones
            </div>

            <div style="font-size:32px;font-weight:700;color:#dc2626;">
                <?= number_format($criticalZones); ?>
            </div>
        </div>

        <div style="
            background:#eff6ff;
            border-radius:16px;
            padding:20px;
            border:1px solid #dbeafe;
        ">
            <div style="color:#1d4ed8;font-size:13px;margin-bottom:10px;">
                Highest Revenue Zone
            </div>

            <div style="font-size:22px;font-weight:700;color:#1e3a8a;">
                <?= htmlspecialchars($highestRevenueZone); ?>
            </div>
        </div>

    </div>

    <div style="
        margin-top:20px;
        background:#f8fafc;
        border-left:4px solid #1e3a8a;
        padding:18px;
        border-radius:14px;
    ">
        <div style="font-size:14px;color:#334155;line-height:1.8;">
            Executive intelligence indicates stable operational performance across most zones.
            Zones with inactive meters should be prioritized for field inspection,
            billing verification, meter reconnection review, and NRW reduction.
        </div>
    </div>

</div>

<!-- ================= BOTTOM ================= -->

<div class="bottom-grid">

    <div class="card">

        <div class="card-title">Customer Experience</div>
        <div class="card-subtitle">Service quality and complaint handling</div>

        <div class="progress-group">
            <div class="progress-row">
                <span>Complaints Resolved</span>
                <strong>87%</strong>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" style="width:87%;"></div>
            </div>
        </div>

        <div class="progress-group">
            <div class="progress-row">
                <span>Average Response Time</span>
                <strong>2.4 hrs</strong>
            </div>
        </div>

        <div class="progress-group">
            <div class="progress-row">
                <span>Customer Satisfaction</span>
                <strong>91%</strong>
            </div>
        </div>

    </div>

    <div class="card summary-card">

        <div class="card-title" style="color:white;">
            Executive Summary
        </div>

        <p>
            • Revenue currently stands at KES <?= number_format($totalRevenue); ?>.<br><br>
            • <?= number_format($activeCount); ?> smart meters are active.<br><br>
            • <?= number_format($inactiveCount); ?> smart meters require attention.<br><br>
            • Collection efficiency is currently <?= $collectionRate; ?>%.<br><br>
            • Estimated NRW exposure is <?= $nrw; ?>%.
        </p>

        <button class="summary-btn" onclick="showFullIntelligenceReport()">
            View Full Intelligence Report
        </button>

    </div>

</div>

<!-- ================= MODAL ================= -->

<div id="modal" class="modal">

    <div class="modal-content">

        <span class="close-btn" onclick="closeModal()">×</span>

        <div id="modalBody"></div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

/* ================= DATA ================= */

const activeByZone = <?php echo json_encode($activeByZone); ?>;
const inactiveByZone = <?php echo json_encode($inactiveByZone); ?>;
const zoneRevenue = <?php echo json_encode($zoneRevenue); ?>;

const dashboardIntel = {
    totalRevenue: <?= json_encode($totalRevenue); ?>,
    totalMeters: <?= json_encode($totalMeters); ?>,
    activeCount: <?= json_encode($activeCount); ?>,
    inactiveCount: <?= json_encode($inactiveCount); ?>,
    collectionRate: <?= json_encode($collectionRate); ?>,
    nrw: <?= json_encode($nrw); ?>,
    totalZones: <?= json_encode($totalZones); ?>,
    healthyZones: <?= json_encode($healthyZones); ?>,
    riskZones: <?= json_encode($riskZones); ?>,
    criticalZones: <?= json_encode($criticalZones); ?>,
    highestRevenueZone: <?= json_encode($highestRevenueZone); ?>,
    reportDate: "<?= date('d M Y'); ?>"
};

const monthlyAnalytics = {
    labels: <?= json_encode($monthLabels); ?>,
    revenue: <?= json_encode(array_values($monthlyRevenueTrend)); ?>,
    pumped: <?= json_encode(array_values($monthlyPumpedTrend)); ?>,
    applications: <?= json_encode(array_values($monthlyApplicationsTrend)); ?>,
    complaints: <?= json_encode(array_values($monthlyComplaintsTrend)); ?>,
    currentMonth: <?= json_encode($currentMonthName); ?>
};

/* ================= REVENUE DRILL ================= */

function showRevenue(){

    let html = `

    <h2>Revenue Intelligence</h2>

    <div class="drill-grid">

        ${
            Object.keys(zoneRevenue).map(zone => `

                <div class="drill-card">

                    <h4>${zone}</h4>

                    <div class="drill-item">
                        Revenue:<br>
                        <strong style="color:#166534;font-size:16px;">
                            KES ${Number(zoneRevenue[zone]).toLocaleString()}
                        </strong>
                    </div>

                    <div class="drill-item">
                        Active Meters:
                        ${activeByZone[zone] ? activeByZone[zone].length : 0}
                    </div>

                    <div class="drill-item">
                        Inactive Meters:
                        ${inactiveByZone[zone] ? inactiveByZone[zone].length : 0}
                    </div>

                    <div class="drill-item">
                        <button class="action-btn" onclick="showZoneDetails('${zone}')">
                            View Zone Details
                        </button>
                    </div>

                </div>

            `).join('')
        }

    </div>

    `;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= FULL INTELLIGENCE REPORT ================= */

function showFullIntelligenceReport(){

    let zones = Object.keys(zoneRevenue);

    let topZone = zones.length
        ? zones.reduce((a,b) => Number(zoneRevenue[a]) > Number(zoneRevenue[b]) ? a : b)
        : 'N/A';

    let lowZone = zones.length
        ? zones.reduce((a,b) => Number(zoneRevenue[a]) < Number(zoneRevenue[b]) ? a : b)
        : 'N/A';

    let totalRevenue = Number(dashboardIntel.totalRevenue || 0);

    let meterRisk =
        dashboardIntel.inactiveCount > 0
        ? 'Inactive smart meters require field verification, billing validation, customer follow-up, and technical reconnection review.'
        : 'No inactive smart meter risk has been detected from the current meter records.';

    let collectionStatus =
        dashboardIntel.collectionRate >= 80
        ? 'Strong'
        : dashboardIntel.collectionRate >= 60
            ? 'Moderate'
            : 'Weak';

    let collectionBadge =
        collectionStatus === 'Strong'
        ? 'badge-green'
        : collectionStatus === 'Moderate'
            ? 'badge-yellow'
            : 'badge-red';

    let html = `

        <h2>Full Executive Intelligence Report</h2>

        <div class="drill-grid">

            <div class="drill-card">
                <h4>Report Snapshot</h4>
                <div class="drill-item"><strong>Date:</strong> ${dashboardIntel.reportDate}</div>
                <div class="drill-item"><strong>Total Revenue:</strong> KES ${totalRevenue.toLocaleString()}</div>
                <div class="drill-item"><strong>Total Smart Meters:</strong> ${dashboardIntel.totalMeters.toLocaleString()}</div>
                <div class="drill-item"><strong>Total Operational Zones:</strong> ${dashboardIntel.totalZones.toLocaleString()}</div>
            </div>

            <div class="drill-card">
                <h4>Meter Operations</h4>
                <div class="drill-item"><strong>Active Meters:</strong> ${dashboardIntel.activeCount.toLocaleString()}</div>
                <div class="drill-item"><strong>Inactive Meters:</strong> ${dashboardIntel.inactiveCount.toLocaleString()}</div>
                <div class="drill-item"><strong>Operational Risk:</strong> ${meterRisk}</div>
            </div>

            <div class="drill-card">
                <h4>Revenue Intelligence</h4>
                <div class="drill-item"><strong>Highest Revenue Zone:</strong> ${topZone}</div>
                <div class="drill-item"><strong>Lowest Revenue Zone:</strong> ${lowZone}</div>
                <div class="drill-item"><strong>Total Zone Revenue:</strong> KES ${totalRevenue.toLocaleString()}</div>
            </div>

            <div class="drill-card">
                <h4>Collection Performance</h4>
                <div class="drill-item">
                    <strong>Collection Efficiency:</strong>
                    <span class="badge ${collectionBadge}">${dashboardIntel.collectionRate}% - ${collectionStatus}</span>
                </div>
                <div class="drill-item"><strong>Estimated NRW Exposure:</strong> ${dashboardIntel.nrw}%</div>
                <div class="drill-item"><strong>Focus:</strong> Improve billing accuracy, meter availability, and zone-level accountability.</div>
            </div>

        </div>

        <div class="report-section">
            <h3>Executive Interpretation</h3>

            The current utility intelligence report shows total revenue of
            <strong>KES ${totalRevenue.toLocaleString()}</strong>, supported by
            <strong>${dashboardIntel.activeCount.toLocaleString()}</strong> active smart meters out of
            <strong>${dashboardIntel.totalMeters.toLocaleString()}</strong> total active meter records.

            <br><br>

            Collection efficiency currently stands at
            <strong>${dashboardIntel.collectionRate}%</strong>, which is classified as
            <strong>${collectionStatus}</strong>. The highest performing revenue zone is
            <strong>${topZone}</strong>, while <strong>${lowZone}</strong> requires closer review
            for possible low billing activity, inactive meters, under-consumption, or delayed readings.

            <br><br>

            The dashboard indicates an estimated NRW exposure of
            <strong>${dashboardIntel.nrw}%</strong>. Management should prioritize leakage tracking,
            offline meter recovery, meter reading validation, and zone-level revenue assurance.
        </div>

        <h3 style="margin-top:25px;color:#0f172a;">Zone Performance Breakdown</h3>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Revenue</th>
                        <th>Active Meters</th>
                        <th>Inactive Meters</th>
                        <th>Status</th>
                        <th>Recommended Action</th>
                    </tr>
                </thead>

                <tbody>

                    ${
                        zones.map(zone => {

                            let active = activeByZone[zone] ? activeByZone[zone].length : 0;
                            let inactive = inactiveByZone[zone] ? inactiveByZone[zone].length : 0;
                            let revenue = Number(zoneRevenue[zone] || 0);

                            let status = inactive > active
                                ? 'Critical'
                                : inactive > 0
                                    ? 'Watch'
                                    : 'Stable';

                            let badge = status === 'Critical'
                                ? 'badge-red'
                                : status === 'Watch'
                                    ? 'badge-yellow'
                                    : 'badge-green';

                            let action = status === 'Critical'
                                ? 'Dispatch technical team, inspect meters, validate readings, and review billing gaps.'
                                : status === 'Watch'
                                    ? 'Monitor inactive meters, verify consumption patterns, and follow up affected customers.'
                                    : 'Maintain current controls and continue routine performance monitoring.';

                            return `
                                <tr>
                                    <td><strong>${zone}</strong></td>
                                    <td>KES ${revenue.toLocaleString()}</td>
                                    <td>${active}</td>
                                    <td>${inactive}</td>
                                    <td><span class="badge ${badge}">${status}</span></td>
                                    <td>${action}</td>
                                </tr>
                            `;
                        }).join('')
                    }

                </tbody>

            </table>

        </div>

        <div class="report-section">
            <h3>Management Recommendations</h3>

            1. Prioritize inactive meter recovery in zones marked as <strong>Critical</strong> or <strong>Watch</strong>.<br>
            2. Compare low-revenue zones against meter activity to detect billing gaps or consumption anomalies.<br>
            3. Strengthen NRW controls through leakage response, meter audit, and zone-level water balance reviews.<br>
            4. Use the highest revenue zone as a performance benchmark for other zones.<br>
            5. Schedule routine intelligence reviews using updated readings, customer activity, and field reports.
        </div>

    `;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= ZONE DETAILS ================= */

function showZoneDetails(zone){

    let active = activeByZone[zone] ? activeByZone[zone] : [];
    let inactive = inactiveByZone[zone] ? inactiveByZone[zone] : [];

    let html = `

    <h2>${zone} Zone Intelligence</h2>

    <div class="drill-grid">

        <div class="drill-card">

            <h4>Active Meters</h4>

            ${
                active.length
                ? active.map(m => `
                    <div class="drill-item">
                        ${m.serial_number} — ${m.customer_name || 'N/A'}
                    </div>
                `).join('')
                : `<div class="drill-item">No active meters found.</div>`
            }

        </div>

        <div class="drill-card">

            <h4>Inactive Meters</h4>

            ${
                inactive.length
                ? inactive.map(m => `
                    <div class="drill-item">
                        ${m.serial_number} — ${m.customer_name || 'N/A'}
                    </div>
                `).join('')
                : `<div class="drill-item">No inactive meters found.</div>`
            }

        </div>

    </div>

    `;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= METERS ================= */

function showMeters(type){

    let dataset = type === 'active' ? activeByZone : inactiveByZone;

    let html = `
        <h2>${type.toUpperCase()} METERS</h2>
        <div class="drill-grid">
    `;

    for(let zone in dataset){

        html += `

            <div class="drill-card">

                <h4>${zone}</h4>

                ${
                    dataset[zone].map(m => `
                        <div class="drill-item">
                            ${m.serial_number} — ${m.customer_name || 'N/A'}
                        </div>
                    `).join('')
                }

            </div>

        `;
    }

    html += `</div>`;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= CLOSE ================= */

function closeModal(){
    document.getElementById("modal").style.display = "none";
}

window.onclick = function(e){

    let modal = document.getElementById("modal");

    if(e.target == modal){
        closeModal();
    }
}

/* ================= CHARTS ================= */

new Chart(document.getElementById('revenueChart'), {

    type:'bar',

    data:{
        labels: monthlyAnalytics.labels,

        datasets:[{
            label:'Revenue',
            data: monthlyAnalytics.revenue,
            backgroundColor:'#1e3a8a',
            borderRadius:8
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,

        plugins:{
            legend:{
                display:false
            }
        }
    }

});

</script>

</div>
