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
        $out[$k][] = $d;
    }
    return $out;
}

/* ACTIVE / INACTIVE */
$activeMeters = array_filter($meters, fn($m)=>($m['status'] ?? '')=='Active');
$inactiveMeters = array_filter($meters, fn($m)=>($m['status'] ?? '')=='Inactive');

/* GROUPING */
$activeByZone = groupBy($activeMeters, 'zone');
$inactiveByZone = groupBy($inactiveMeters, 'zone');
$activeByType = groupBy($activeMeters, 'customer_type');
$inactiveByType = groupBy($inactiveMeters, 'customer_type');

?>

<style>

/* ========================= UI (kept + enhanced) ========================= */
.overlay {
    padding: 24px;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #f4f7fb 0%, #eef3f8 100%);
    margin-left: 270px;
    margin-top: 75px;
    margin-bottom: 60px;
    min-height: 100vh;
}

/* sections */
section {
    background: #fff;
    padding: 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

/* headings */
h2 {
    color: #0a2a43;
    font-size: 18px;
    margin-bottom: 14px;
    border-left: 4px solid #f1c40f;
    padding-left: 10px;
}

/* ========================= CARDS ========================= */
.cards-grid {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.card {
    flex: 1;
    min-width: 200px;
    padding: 18px;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    transition: all 0.25s ease;
}

.card:hover {
    transform: translateY(-5px);
}

.card.green {
    border-left: 5px solid #2e7d32;
    background: linear-gradient(135deg, #eafaf0, #ffffff);
}

.card.blue {
    border-left: 5px solid #0a2a43;
    background: linear-gradient(135deg, #eaf2ff, #ffffff);
}

.card.yellow {
    border-left: 5px solid #f1c40f;
    background: linear-gradient(135deg, #fff9e6, #ffffff);
}

.card.clickable {
    cursor: pointer;
}

.card p {
    font-size: 22px;
    font-weight: bold;
}

/* ========================= MODAL ========================= */
.modal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(10,20,40,0.6);
    backdrop-filter: blur(6px);
    z-index:9999;
}

.modal-content {
    background:#fff;
    width:70%;
    margin:4% auto;
    padding:22px;
    border-radius:16px;
    max-height:80vh;
    overflow:auto;
}

/* ========================= CHARTS ========================= */
.charts-row {
    display:flex;
    gap:15px;
    flex-wrap:wrap;
}

.chart-box {
    flex:1;
    min-width:300px;
    background:#fff;
    padding:10px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.06);
}

.chart-box.graph {
    height:240px;
}

.chart-box.doughnut {
    height:240px;
}

</style>

<!-- ===================== TODAY ===================== -->
<section>
    <h2>Today's Analysis</h2>

    <div class="cards-grid">

        <div class="card green clickable" onclick="showRevenue('today')">
            <h3>Revenue Today</h3>
            <p>KES 125,400</p>
        </div>

        <div class="card yellow clickable" onclick="showMeters('active')">
            <h3>Active Meters</h3>
            <p><?php echo count($activeMeters); ?></p>
        </div>

        <div class="card blue clickable" onclick="showMeters('inactive')">
            <h3>Inactive Meters</h3>
            <p><?php echo count($inactiveMeters); ?></p>
        </div>

    </div>

    <!-- GRAPHS RESTORED -->
    <div class="charts-row">

        <div class="chart-box graph">
            <canvas id="todayRevenueChart"></canvas>
        </div>

        <div class="chart-box doughnut">
            <canvas id="todayMetersChart"></canvas>
        </div>

    </div>
</section>

<!-- ===================== LAST MONTH ===================== -->
<section>
    <h2>Last Month's Analysis</h2>

    <div class="cards-grid">

        <div class="card blue clickable" onclick="showRevenue('last')">
            <h3>Revenue Last Month</h3>
            <p>KES 2,540,000</p>
        </div>

        <div class="card green clickable" onclick="showMeters('active')">
            <h3>Active Meters</h3>
            <p><?php echo count($activeMeters); ?></p>
        </div>

        <div class="card yellow clickable" onclick="showMeters('inactive')">
            <h3>Inactive Meters</h3>
            <p><?php echo count($inactiveMeters); ?></p>
        </div>

    </div>

    <!-- GRAPHS RESTORED -->
    <div class="charts-row">

        <div class="chart-box graph">
            <canvas id="lastMonthRevenueChart"></canvas>
        </div>

        <div class="chart-box doughnut">
            <canvas id="lastMonthMetersChart"></canvas>
        </div>

    </div>
</section>

<!-- ===================== TRENDS ===================== -->
<section>
    <h2>Trend Graphs</h2>

    <div class="charts-row">

        <div class="chart-box graph">
            <canvas id="revenueTrendChart"></canvas>
        </div>

        <div class="chart-box graph">
            <canvas id="metersTrendChart"></canvas>
        </div>

    </div>
</section>

<!-- ===================== MODAL ===================== -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span onclick="closeModal()" style="float:right;cursor:pointer;font-size:22px;">×</span>
        <div id="modalBody"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

/* ================= DRILLDOWN ================= */
function showRevenue(period){
    let html = "<h3>Revenue ("+period+") Breakdown</h3>";
    html += "<h4>By Customer Type</h4>";
    html += "<ul><li>Residential: 60,000</li><li>Commercial: 40,000</li><li>Gov: 25,400</li></ul>";

    html += "<h4>By Zone</h4>";
    html += "<ul><li>Town</li><li>Westlands</li><li>Kasarani</li></ul>";

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

function showMeters(type){

    let data = type === 'active'
        ? <?php echo json_encode($activeByZone); ?>
        : <?php echo json_encode($inactiveByZone); ?>;

    let html = "<h3>"+type.toUpperCase()+" METERS</h3>";

    if(Object.keys(data).length === 0){
        html += "<p>No meters found.</p>";
    } else {
        for(let z in data){
            html += "<b>"+z+"</b><ul>";
            data[z].forEach(m=>{
                html += "<li>"+m.serial_number+" - "+m.customer_name+"</li>";
            });
            html += "</ul>";
        }
    }

    document.getElementById("modalBody").innerHTML = html;
    document.getElementById("modal").style.display = "block";
}

function closeModal(){
    document.getElementById("modal").style.display = "none";
}

/* ================= CHARTS ================= */

/* TODAY REVENUE */
new Chart(document.getElementById('todayRevenueChart'), {
    type:'bar',
    data:{
        labels:['Mon','Tue','Wed','Thu','Fri'],
        datasets:[{data:[120,190,300,250,400],backgroundColor:'#0a2a43'}]
    }
});

/* TODAY METERS */
new Chart(document.getElementById('todayMetersChart'), {
    type:'doughnut',
    data:{
        labels:['Active','Inactive'],
        datasets:[{data:[4320,380],backgroundColor:['#2e7d32','#f1c40f']}]
    }
});

/* LAST MONTH REVENUE */
new Chart(document.getElementById('lastMonthRevenueChart'), {
    type:'line',
    data:{
        labels:['W1','W2','W3','W4'],
        datasets:[{data:[500,800,700,900],borderColor:'#0a2a43'}]
    }
});

/* LAST MONTH METERS */
new Chart(document.getElementById('lastMonthMetersChart'), {
    type:'doughnut',
    data:{
        labels:['Active','Inactive'],
        datasets:[{data:[4100,400],backgroundColor:['#2e7d32','#f1c40f']}]
    }
});

/* TRENDS */
new Chart(document.getElementById('revenueTrendChart'), {
    type:'line',
    data:{
        labels:['Jan','Feb','Mar','Apr','May'],
        datasets:[{data:[100,200,300,400,500],borderColor:'#0a2a43'}]
    }
});

new Chart(document.getElementById('metersTrendChart'), {
    type:'line',
    data:{
        labels:['Jan','Feb','Mar','Apr','May'],
        datasets:[{data:[2000,2500,3000,3500,4000],borderColor:'#2e7d32'}]
    }
});

</script>