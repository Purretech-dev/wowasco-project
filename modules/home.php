<div class="overlay">

<?php
include $_SERVER['DOCUMENT_ROOT'].'/wowasco-system/api/db.php';

/* ================= FETCH METERS ================= */

$meters = [];

$res = $conn->query("SELECT * FROM meters");

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

/* ================= GROUPING ================= */

$activeByZone = groupBy($activeMeters, 'zone');
$inactiveByZone = groupBy($inactiveMeters, 'zone');

$activeByType = groupBy($activeMeters, 'customer_type');
$inactiveByType = groupBy($inactiveMeters, 'customer_type');

/* ================= KPI ================= */

$totalMeters = count($meters);
$activeCount = count($activeMeters);
$inactiveCount = count($inactiveMeters);

/* ================= REAL ZONE REVENUE ================= */

$zoneRevenue = [];

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
    round($row['revenue'], 2);
}

?>

<style>

/* ========================= UI ========================= */

.overlay {
    padding: 24px;
    font-family: 'Segoe UI', Arial, sans-serif;
    background:#f8fafc;
    margin-left: 270px;
    margin-top: 75px;
    margin-bottom: 60px;
    min-height: 100vh;
}

/* ========================= SECTIONS ========================= */

section {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    margin-bottom: 20px;
    border:1px solid #e5e7eb;
    box-shadow:0 2px 8px rgba(0,0,0,0.04);
}

/* ========================= HEADINGS ========================= */

h2 {
    color:#0f172a;
    font-size:18px;
    margin-bottom:18px;
    font-weight:600;
    border-left:3px solid #cbd5e1;
    padding-left:10px;
}

/* ========================= CARDS ========================= */

.cards-grid {
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.card {
    flex:1;
    min-width:220px;
    padding:20px;
    border-radius:16px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-left:3px solid #cbd5e1;
    transition:0.2s ease;
    cursor:pointer;
}

.card:hover{
    transform:translateY(-3px);
    box-shadow:0 4px 12px rgba(0,0,0,0.05);
}

.card h3{
    margin:0 0 10px;
    font-size:15px;
    color:#475569;
    font-weight:600;
}

.card p{
    font-size:26px;
    font-weight:700;
    color:#0f172a;
    margin:0;
}

/* ========================= CHARTS ========================= */

.charts-row{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
}

.chart-box{
    flex:1;
    min-width:320px;
    background:#fff;
    padding:14px;
    border-radius:14px;
    border:1px solid #e5e7eb;
    height:340px;
}

/* ========================= MODAL ========================= */

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.45);
    backdrop-filter:blur(4px);
    z-index:9999;
}

.modal-content{
    background:#fff;
    width:75%;
    margin:3% auto;
    border-radius:16px;
    padding:24px;
    max-height:88vh;
    overflow:auto;
    box-shadow:0 12px 30px rgba(0,0,0,0.15);
}

/* ========================= DRILLDOWN ========================= */

.drill-section{
    margin-top:18px;
}

.drill-title{
    font-size:15px;
    font-weight:600;
    margin-bottom:12px;
    color:#0f172a;
}

.drill-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:14px;
}

.drill-card{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:14px;
}

.drill-card h4{
    margin:0 0 10px;
    font-size:14px;
    color:#334155;
}

.drill-item{
    padding:8px 0;
    border-bottom:1px solid #e2e8f0;
    font-size:13px;
    color:#475569;
}

.drill-item:last-child{
    border-bottom:none;
}

/* ========================= TAGS ========================= */

.tag{
    display:inline-block;
    padding:4px 10px;
    border-radius:20px;
    background:#f1f5f9;
    border:1px solid #e2e8f0;
    font-size:12px;
    color:#475569;
    margin-top:8px;
}

/* ========================= CLOSE ========================= */

.close-btn{
    float:right;
    cursor:pointer;
    font-size:24px;
    color:#64748b;
}

/* ========================= RESPONSIVE ========================= */

@media(max-width:1000px){

    .overlay{
        margin-left:0;
    }

    .modal-content{
        width:95%;
    }

    .chart-box{
        min-width:100%;
    }
}

</style>

<!-- ===================== TODAY ===================== -->

<section>

<h2>Today's Analysis</h2>

<div class="cards-grid">

    <div class="card" onclick="showRevenue('today')">
        <h3>Revenue Today</h3>
        <p>KES 125,400</p>
    </div>

    <div class="card" onclick="showMeters('active')">
        <h3>Active Meters</h3>
        <p><?php echo $activeCount; ?></p>
    </div>

    <div class="card" onclick="showMeters('inactive')">
        <h3>Inactive Meters</h3>
        <p><?php echo $inactiveCount; ?></p>
    </div>

</div>

<div class="charts-row">

    <div class="chart-box">
        <canvas id="todayRevenueChart"></canvas>
    </div>

    <div class="chart-box">
        <canvas id="todayMetersChart"></canvas>
    </div>

</div>

</section>

<!-- ===================== LAST MONTH ===================== -->

<section>

<h2>Last Month's Analysis</h2>

<div class="cards-grid">

    <div class="card" onclick="showRevenue('last')">
        <h3>Revenue Last Month</h3>
        <p>KES 2,540,000</p>
    </div>

    <div class="card" onclick="showMeters('active')">
        <h3>Active Meters</h3>
        <p><?php echo $activeCount; ?></p>
    </div>

    <div class="card" onclick="showMeters('inactive')">
        <h3>Inactive Meters</h3>
        <p><?php echo $inactiveCount; ?></p>
    </div>

</div>

<div class="charts-row">

    <div class="chart-box">
        <canvas id="lastMonthRevenueChart"></canvas>
    </div>

    <div class="chart-box">
        <canvas id="lastMonthMetersChart"></canvas>
    </div>

</div>

</section>

<!-- ===================== TRENDS ===================== -->

<section>

<h2>Trend Graphs</h2>

<div class="charts-row">

    <div class="chart-box">
        <canvas id="revenueTrendChart"></canvas>
    </div>

    <div class="chart-box">
        <canvas id="metersTrendChart"></canvas>
    </div>

</div>

</section>

<!-- ===================== MODAL ===================== -->

<div id="modal" class="modal">

    <div class="modal-content">

        <span onclick="closeModal()" class="close-btn">×</span>

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

const activeByType =
<?php echo json_encode($activeByType); ?>;

const inactiveByType =
<?php echo json_encode($inactiveByType); ?>;

const zoneRevenue =
<?php echo json_encode($zoneRevenue); ?>;

/* ================= DRILLDOWN ================= */

function showRevenue(period){

    let html = `

    <h2>
        Revenue Intelligence (${period.toUpperCase()})
    </h2>

    <div class="drill-section">

        <div class="drill-title">
            Revenue Breakdown
        </div>

        <div class="drill-grid">

            <div class="drill-card">

                <h4>Customer Types</h4>

                <div class="drill-item">
                    Domestic — KES 600,000
                </div>

                <div class="drill-item">
                    Commercial — KES 400,000
                </div>

                <div class="drill-item">
                    Government Entities — KES 250,400
                </div>

                <span class="tag">
                    Revenue Streams
                </span>

            </div>

            <!-- ================= REAL ZONE ANALYSIS ================= -->

            <div class="drill-card">

                <h4>Zone Revenue Analysis</h4>

                ${
                    Object.keys(zoneRevenue).map(zone => `

                        <div class="drill-item">

                            <strong>${zone}</strong><br>

                            Estimated Revenue:<br>

                            <span style="
                                font-size:15px;
                                font-weight:600;
                                color:#166534;
                            ">

                                KES ${
                                    Number(
                                        zoneRevenue[zone]
                                    ).toLocaleString()
                                }

                            </span><br><br>

                            Active Meters:
                            ${
                                activeByZone[zone]
                                ? activeByZone[zone].length
                                : 0
                            }<br>

                            Inactive Meters:
                            ${
                                inactiveByZone[zone]
                                ? inactiveByZone[zone].length
                                : 0
                            }

                        </div>

                    `).join('')
                }

                <span class="tag">
                    Live Revenue Intelligence
                </span>

            </div>

            <div class="drill-card">

                <h4>Operational Insight</h4>

                <div class="drill-item">
                    Revenue Growth Trend Positive
                </div>

                <div class="drill-item">
                    Commercial usage increasing
                </div>

                <div class="drill-item">
                    Low inactive meter losses
                </div>

                <span class="tag">
                    System Insights
                </span>

            </div>

        </div>

    </div>

    `;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= METERS DRILLDOWN ================= */

function showMeters(type){

    let zoneData = type === 'active'
        ? activeByZone
        : inactiveByZone;

    let typeData = type === 'active'
        ? activeByType
        : inactiveByType;

    let html = `
    <h2>
        ${type.toUpperCase()} METERS ANALYTICS
    </h2>
    `;

    html += `
    <div class="drill-section">

    <div class="drill-title">
        Zone Breakdown
    </div>

    <div class="drill-grid">
    `;

    for(let zone in zoneData){

        html += `
        <div class="drill-card">

            <h4>${zone}</h4>

            <div class="drill-item">
                Total Meters:
                ${zoneData[zone].length}
            </div>
        `;

        zoneData[zone].forEach(m=>{

            html += `
            <div class="drill-item">
                ${m.serial_number}
                — ${m.customer_name || 'N/A'}
            </div>
            `;
        });

        html += `
            <span class="tag">
                Zone Analytics
            </span>

        </div>
        `;
    }

    html += `</div></div>`;

    html += `
    <div class="drill-section">

    <div class="drill-title">
        Customer Type Breakdown
    </div>

    <div class="drill-grid">
    `;

    for(let typeName in typeData){

        html += `
        <div class="drill-card">

            <h4>${typeName}</h4>

            <div class="drill-item">
                Total Meters:
                ${typeData[typeName].length}
            </div>
        `;

        typeData[typeName].forEach(m=>{

            html += `
            <div class="drill-item">
                ${m.serial_number}
                — ${m.customer_name || 'N/A'}
            </div>
            `;
        });

        html += `
            <span class="tag">
                Customer Intelligence
            </span>

        </div>
        `;
    }

    html += `</div></div>`;

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

/* ================= CLOSE ================= */

function closeModal(){

    let modal =
    document.getElementById("modal");

    modal.style.display = "none";
}

/* ================= CLOSE OUTSIDE ================= */

window.onclick = function(event){

    let modal =
    document.getElementById("modal");

    if(event.target == modal){

        closeModal();
    }
}

/* ================= CHARTS ================= */

/* TODAY REVENUE */

new Chart(document.getElementById('todayRevenueChart'), {

    type:'bar',

    data:{
        labels:['Mon','Tue','Wed','Thu','Fri'],
        datasets:[{
            data:[120,190,300,250,400],
            backgroundColor:'#334155'
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

/* TODAY METERS */

new Chart(document.getElementById('todayMetersChart'), {

    type:'doughnut',

    data:{
        labels:['Active','Inactive'],
        datasets:[{
            data:[
                <?php echo $activeCount; ?>,
                <?php echo $inactiveCount; ?>
            ],
            backgroundColor:[
                '#027b26',
                '#dcc811'
            ],
            borderColor:[
                '#ffffff',
                '#ffffff'
            ],
            borderWidth:4,
            hoverOffset:6
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,
        cutout:'68%',

        plugins:{
            legend:{
                position:'bottom',
                labels:{
                    padding:18,
                    boxWidth:14,
                    color:'#475569',
                    font:{
                        size:12
                    }
                }
            }
        }
    }
});

/* LAST MONTH REVENUE */

new Chart(document.getElementById('lastMonthRevenueChart'), {

    type:'line',

    data:{
        labels:['W1','W2','W3','W4'],
        datasets:[{
            data:[500,800,700,900],
            borderColor:'#334155',
            tension:0.4
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

/* LAST MONTH METERS */

new Chart(document.getElementById('lastMonthMetersChart'), {

    type:'doughnut',

    data:{
        labels:['Active','Inactive'],
        datasets:[{
            data:[
                <?php echo $activeCount; ?>,
                <?php echo $inactiveCount; ?>
            ],
            backgroundColor:[
                '#027b26',
                '#dcc811'
            ],
            borderColor:[
                '#ffffff',
                '#ffffff'
            ],
            borderWidth:4,
            hoverOffset:6
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,
        cutout:'68%',

        plugins:{
            legend:{
                position:'bottom',
                labels:{
                    padding:18,
                    boxWidth:14,
                    color:'#475569',
                    font:{
                        size:12
                    }
                }
            }
        }
    }
});

/* TRENDS */

new Chart(document.getElementById('revenueTrendChart'), {

    type:'line',

    data:{
        labels:['Jan','Feb','Mar','Apr','May'],
        datasets:[{
            data:[100,200,300,400,500],
            borderColor:'#334155',
            tension:0.4
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

new Chart(document.getElementById('metersTrendChart'), {

    type:'line',

    data:{
        labels:['Jan','Feb','Mar','Apr','May'],
        datasets:[{
            data:[2000,2500,3000,3500,4000],
            borderColor:'#64748b',
            tension:0.4
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});

</script>

</div>