<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= FETCH ZONES ================= */
$zones = [];

$zoneResult = $conn->query("SELECT zone_name FROM zones");

while ($row = $zoneResult->fetch_assoc()) {
    $zones[] = $row['zone_name'];
}

/* ================= FETCH METERS ================= */
$meters = [];
$metersByZone = [];

$result = $conn->query("
    SELECT serial_number, zone, customer_name, customer_type, status
    FROM meters
");

while ($row = $result->fetch_assoc()) {
    $meters[] = $row;
}

/* ================= CLEAN FUNCTION ================= */
function clean($text)
{
    $text = strtolower(trim($text));
    return preg_replace('/[^a-z0-9]/', '', $text);
}

/* ================= GROUP METERS ================= */
$metersByZone = [];

foreach ($meters as $m) {
    $key = clean($m['zone']);
    $metersByZone[$key][] = $m;
}

/* ================= WOTE CENTER CLUSTERING ================= */
$baseLat = -1.7800;   // Wote town center
$baseLng = 37.6200;

$zoneMap = [];

$radiusStep = 0.018;  // keeps zones close but not overlapping
$angleStep = (2 * M_PI) / max(count($zones), 1);

$i = 0;

foreach ($zones as $z) {

    $key = clean($z);

    /* circular distribution around Wote (controlled cluster) */
    $angle = $i * $angleStep;
    $radius = 0.02 + (mt_rand(-500, 500) / 10000); // slight randomness

    $lat = $baseLat + ($radius * cos($angle));
    $lng = $baseLng + ($radius * sin($angle));

    $zoneMap[$key] = [
        "zone_name" => $z,
        "lat" => $lat,
        "lng" => $lng
    ];

    $i++;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>WOWASCO GIS System</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<style>

body{
    margin:0;
    font-family:Segoe UI;
    background:#eef2f7;
}

.overlay{
    margin-left:260px;
    margin-top:70px;
    padding:10px;
}

.title{
    font-size:20px;
    font-weight:700;
    color:#0b2d5c;
    margin-bottom:10px;
}

#map{
    height:78vh;
    width:100%;
    border-radius:12px;
}

.info-box{
    position:absolute;
    top:120px;
    left:290px;
    width:360px;
    background:white;
    padding:15px;
    border-radius:12px;
    box-shadow:0 6px 18px rgba(0,0,0,0.15);
    font-size:13px;
    display:none;
    z-index:999;
    line-height:1.5;
}

.zone-title{
    font-weight:700;
    color:#0b2d5c;
    margin-bottom:8px;
}

.badge{
    padding:3px 8px;
    border-radius:6px;
    font-size:11px;
    color:white;
}

.Active{ background:green; }
.Inactive{ background:orange; }
.UnderMaintenance{ background:red; }

.empty{
    color:#888;
    font-style:italic;
}

</style>
</head>

<body>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<div class="overlay">

<div class="title">🗺 WOWASCO GIS - Wote Cluster View</div>

<div id="map"></div>
<div class="info-box" id="infoBox"></div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>

/* ================= MAP ================= */
var map = L.map('map', {
    zoomControl: false
}).setView([-1.7800, 37.6200], 13);

/* zoom top-left */
L.control.zoom({ position: 'topleft' }).addTo(map);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'WOWASCO GIS'
}).addTo(map);

/* ================= DATA ================= */
var zones = <?php echo json_encode($zoneMap); ?>;
var metersByZone = <?php echo json_encode($metersByZone); ?>;

/* ================= DRAW ZONES (TRIANGLES) ================= */
Object.keys(zones).forEach(function(key){

    let z = zones[key];
    let meters = metersByZone[key] || [];

    /* TRIANGLE SHAPE */
    let polygon = L.polygon([
        [z.lat, z.lng],
        [z.lat + 0.012, z.lng - 0.010],
        [z.lat - 0.012, z.lng - 0.010]
    ], {
        color: "#0b2d5c",
        weight: 2,
        fillColor: meters.length ? "#cfe2ff" : "#f5f5f5",
        fillOpacity: 0.45
    }).addTo(map);

    polygon.on('mouseover', function () {

        let html = "<div class='zone-title'>" + z.zone_name + "</div>";
        html += "Meters: <b>" + meters.length + "</b><hr>";

        if (meters.length === 0) {
            html += "<div class='empty'>No meters installed</div>";
        } else {

            meters.forEach(m => {

                let cls = (m.status || "").replace(/\s+/g,'');

                html += "• <b>" + m.serial_number + "</b><br>";
                html += m.customer_name + " (" + m.customer_type + ") ";
                html += "<span class='badge " + cls + "'>" + m.status + "</span><br><br>";
            });
        }

        document.getElementById("infoBox").style.display = "block";
        document.getElementById("infoBox").innerHTML = html;
    });

    polygon.on('mouseout', function () {
        document.getElementById("infoBox").style.display = "none";
    });

    polygon.on('click', function () {
        map.setView([z.lat, z.lng], 14);
    });

});

</script>

</body>
</html>