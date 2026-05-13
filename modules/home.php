<div class="overlay">

<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

/* ================= FETCH METERS ================= */

$meters = [];

$res = $conn->query("
    SELECT * 
    FROM meters
    WHERE is_deactivated = 0
");

while($row = $res->fetch_assoc()){
    $meters[] = $row;
}

/* ================= HELPERS ================= */

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

$revenueQuery = $conn->query("

    SELECT 
        m.zone,
        SUM(r.consumption * 20) AS revenue

    FROM meter_readings r

    INNER JOIN meters m
        ON r.meter_id = m.id

    GROUP BY m.zone

");

while($row = $revenueQuery->fetch_assoc()){

    $zoneRevenue[$row['zone']] =
    round($row['revenue'],2);

    $totalRevenue += $row['revenue'];
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

.kpi-green{
    border-left-color:#16a34a;
}

.kpi-yellow{
    border-left-color:#eab308;
}

.kpi-blue{
    border-left-color:#1e3a8a;
}

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

.alert-critical{
    background:#fef2f2;
}

.alert-warning{
    background:#fefce8;
}

.alert-medium{
    background:#eff6ff;
}

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
    width:80%;
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

        <h1>
            Executive Dashboard
        </h1>

        <p>
            Utility Intelligence & Operational Control Center
        </p>

    </div>

    <div class="date-box">

        <span>Today's Date</span>

        <strong>
            <?= date('d M Y'); ?>
        </strong>

    </div>

</div>

<!-- ================= KPI ================= -->

<div class="kpi-grid">

    <div class="kpi-card kpi-green"
         onclick="showRevenue()">

        <div class="kpi-title">
            Revenue Collection
        </div>

        <div class="kpi-value">
            KES <?= number_format($totalRevenue); ?>
        </div>

        <div class="kpi-change">
            +8% This Month
        </div>

    </div>

    <div class="kpi-card kpi-blue"
         onclick="showMeters('active')">

        <div class="kpi-title">
            Active Smart Meters
        </div>

        <div class="kpi-value">
            <?= number_format($activeCount); ?>
        </div>

        <div class="kpi-change">
            Operational Coverage
        </div>

    </div>

    <div class="kpi-card kpi-yellow"
         onclick="showMeters('inactive')">

        <div class="kpi-title">
            Inactive Meters
        </div>

        <div class="kpi-value">
            <?= number_format($inactiveCount); ?>
        </div>

        <div class="kpi-change">
            Requires Attention
        </div>

    </div>

    <div class="kpi-card kpi-green">

        <div class="kpi-title">
            Collection Efficiency
        </div>

        <div class="kpi-value">
            <?= $collectionRate; ?>%
        </div>

        <div class="kpi-change">
            Billing Performance
        </div>

    </div>

</div>

<!-- ================= MAIN GRID ================= -->

<div class="main-grid">

    <!-- REVENUE -->

    <div class="card">

        <div class="card-header">

            <div>

                <div class="card-title">
                    Revenue Analytics
                </div>

                <div class="card-subtitle">
                    Monthly utility performance overview
                </div>

            </div>

            <button class="action-btn">
                Export Report
            </button>

        </div>

        <div class="chart-box">
            <canvas id="revenueChart"></canvas>
        </div>

    </div>

    <!-- ALERTS -->

    <div class="card">

        <div class="card-title">
            Critical Alerts
        </div>

        <div class="card-subtitle"
             style="margin-bottom:18px;">

            Operational risks requiring action

        </div>

        <div class="alert alert-critical">

            <strong>
                Leakage Detected
            </strong>

            <p>
                Kasarani Zone
            </p>

        </div>

        <div class="alert alert-warning">

            <strong>
                Low Reservoir Level
            </strong>

            <p>
                Kamunyolo Reservoir
            </p>

        </div>

        <div class="alert alert-medium">

            <strong>
                Offline Smart Meters
            </strong>

            <p>
                kundakindu Zone
            </p>
            <p>
                Return Zone
            </p>
            <p>
                Kilala Zone
            </p>

        </div>

    </div>

</div>
<!-- ================= ZONE INTELLIGENCE ================= -->

<<!-- ================= ZONE INTELLIGENCE SUMMARY ================= -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Zone Intelligence Center
            </div>

            <div class="card-subtitle">
                Strategic operational and revenue intelligence across all utility zones
            </div>

        </div>

        <button class="action-btn"
                onclick="showRevenue()">

            Open Intelligence Center

        </button>

    </div>

    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:18px;
        margin-top:10px;
    ">

        <!-- TOTAL ZONES -->

        <div style="
            background:#f8fafc;
            border-radius:16px;
            padding:20px;
            border:1px solid #e2e8f0;
        ">

            <div style="
                color:#64748b;
                font-size:13px;
                margin-bottom:10px;
            ">

                Total Operational Zones

            </div>

            <div style="
                font-size:32px;
                font-weight:700;
                color:#0f172a;
            ">

                <?= count($zoneRevenue); ?>

            </div>

        </div>

        <!-- HEALTHY -->

        <div style="
            background:#f0fdf4;
            border-radius:16px;
            padding:20px;
            border:1px solid #dcfce7;
        ">

            <div style="
                color:#15803d;
                font-size:13px;
                margin-bottom:10px;
            ">

                Healthy Zones

            </div>

            <div style="
                font-size:32px;
                font-weight:700;
                color:#166534;
            ">

                <?= rand(5,9); ?>

            </div>

        </div>

        <!-- RISK -->

        <div style="
            background:#fef2f2;
            border-radius:16px;
            padding:20px;
            border:1px solid #fee2e2;
        ">

            <div style="
                color:#b91c1c;
                font-size:13px;
                margin-bottom:10px;
            ">

                Critical Risk Zones

            </div>

            <div style="
                font-size:32px;
                font-weight:700;
                color:#dc2626;
            ">

                <?= rand(1,3); ?>

            </div>

        </div>

        <!-- TOP REVENUE -->

        <div style="
            background:#eff6ff;
            border-radius:16px;
            padding:20px;
            border:1px solid #dbeafe;
        ">

            <div style="
                color:#1d4ed8;
                font-size:13px;
                margin-bottom:10px;
            ">

                Highest Revenue Zone

            </div>

            <div style="
                font-size:22px;
                font-weight:700;
                color:#1e3a8a;
            ">

                <?= array_key_first($zoneRevenue); ?>

            </div>

        </div>

    </div>

    <!-- EXECUTIVE MESSAGE -->

    <div style="
        margin-top:20px;
        background:#f8fafc;
        border-left:4px solid #1e3a8a;
        padding:18px;
        border-radius:14px;
    ">

        <div style="
            font-size:14px;
            color:#334155;
            line-height:1.8;
        ">

            Executive intelligence indicates stable operational performance
            across most zones, although selected zones continue showing
            elevated inactive meter trends requiring targeted intervention.

        </div>

    </div>

</div>

<!-- ================= BOTTOM ================= -->

<div class="bottom-grid">

    <!-- CUSTOMER EXPERIENCE -->

    <div class="card">

        <div class="card-title">
            Customer Experience
        </div>

        <div class="card-subtitle">
            Service quality and complaint handling
        </div>

        <div class="progress-group">

            <div class="progress-row">

                <span>
                    Complaints Resolved
                </span>

                <strong>87%</strong>

            </div>

            <div class="progress-bar">

                <div class="progress-fill"
                     style="width:87%;">

                </div>

            </div>

        </div>

        <div class="progress-group">

            <div class="progress-row">

                <span>
                    Average Response Time
                </span>

                <strong>
                    2.4 hrs
                </strong>

            </div>

        </div>

        <div class="progress-group">

            <div class="progress-row">

                <span>
                    Customer Satisfaction
                </span>

                <strong>
                    91%
                </strong>

            </div>

        </div>

    </div>

    <!-- EXECUTIVE SUMMARY -->

    <div class="card summary-card">

        <div class="card-title"
             style="color:white;">

            Executive Summary

        </div>

        <p>

            • Revenue increased by 8%
            compared to last month.<br><br>

            • Non-Revenue Water reduced
            in Wote Zone.<br><br>

            • 3 critical leakages remain
            unresolved in Kasarani.<br><br>

            • 4 smart meters went offline
            in Town zone.<br><br>

            • Collection efficiency exceeded
            target by 6%.

        </p>

        <button class="summary-btn">

            View Full Intelligence Report

        </button>

    </div>

</div>

<!-- ================= MODAL ================= -->

<div id="modal"
     class="modal">

    <div class="modal-content">

        <span class="close-btn"
              onclick="closeModal()">

            ×

        </span>

        <div id="modalBody"></div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

/* ================= DATA ================= */

const activeByZone =
<?php echo json_encode($activeByZone); ?>;

const inactiveByZone =
<?php echo json_encode($inactiveByZone); ?>;

const zoneRevenue =
<?php echo json_encode($zoneRevenue); ?>;

/* ================= REVENUE DRILL ================= */

function showRevenue(){

    let html = `

    <h2>
        Revenue Intelligence
    </h2>

    <div class="drill-grid">

        ${
            Object.keys(zoneRevenue).map(zone => `

                <div class="drill-card">

                    <h4>${zone}</h4>

                    <div class="drill-item">

                        Revenue:<br>

                        <strong style="
                            color:#166534;
                            font-size:16px;
                        ">

                            KES ${
                                Number(
                                    zoneRevenue[zone]
                                ).toLocaleString()
                            }

                        </strong>

                    </div>

                    <div class="drill-item">

                        Active Meters:
                        ${
                            activeByZone[zone]
                            ? activeByZone[zone].length
                            : 0
                        }

                    </div>

                    <div class="drill-item">

                        Inactive Meters:
                        ${
                            inactiveByZone[zone]
                            ? inactiveByZone[zone].length
                            : 0
                        }

                    </div>

                </div>

            `).join('')
        }

    </div>

    `;

    document.getElementById("modalBody")
    .innerHTML = html;

    document.getElementById("modal")
    .style.display = "block";
}

/* ================= ZONE DETAILS ================= */

function showZoneDetails(zone){

    let active =
    activeByZone[zone]
    ? activeByZone[zone]
    : [];

    let inactive =
    inactiveByZone[zone]
    ? inactiveByZone[zone]
    : [];

    let html = `

    <h2>
        ${zone} Zone Intelligence
    </h2>

    <div class="drill-grid">

        <div class="drill-card">

            <h4>Active Meters</h4>

            ${
                active.map(m => `

                    <div class="drill-item">

                        ${m.serial_number}
                        — ${m.customer_name || 'N/A'}

                    </div>

                `).join('')
            }

        </div>

        <div class="drill-card">

            <h4>Inactive Meters</h4>

            ${
                inactive.map(m => `

                    <div class="drill-item">

                        ${m.serial_number}
                        — ${m.customer_name || 'N/A'}

                    </div>

                `).join('')
            }

        </div>

    </div>

    `;

    document.getElementById("modalBody")
    .innerHTML = html;

    document.getElementById("modal")
    .style.display = "block";
}

/* ================= METERS ================= */

function showMeters(type){

    let dataset =
    type === 'active'
    ? activeByZone
    : inactiveByZone;

    let html = `
        <h2>
            ${type.toUpperCase()} METERS
        </h2>

        <div class="drill-grid">
    `;

    for(let zone in dataset){

        html += `

            <div class="drill-card">

                <h4>${zone}</h4>

                ${
                    dataset[zone].map(m => `

                        <div class="drill-item">

                            ${m.serial_number}
                            — ${m.customer_name || 'N/A'}

                        </div>

                    `).join('')
                }

            </div>

        `;
    }

    html += `</div>`;

    document.getElementById("modalBody")
    .innerHTML = html;

    document.getElementById("modal")
    .style.display = "block";
}

/* ================= CLOSE ================= */

function closeModal(){

    document.getElementById("modal")
    .style.display = "none";
}

window.onclick = function(e){

    let modal =
    document.getElementById("modal");

    if(e.target == modal){

        closeModal();
    }
}

/* ================= CHARTS ================= */

new Chart(document.getElementById('revenueChart'), {

    type:'bar',

    data:{

        labels:[
            'Jan','Feb','Mar',
            'Apr','May','Jun'
        ],

        datasets:[{

            label:'Revenue',

            data:[
                1200000,
                1400000,
                1600000,
                1800000,
                2200000,
                2500000
            ],

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