<?php
require_once __DIR__ . '/../../api/db.php';

/* ================= MAINTENANCE ================= */
$maintenance_map = [
    "Tuesday" => [
        "Kasarani & Town Zone" => "Pipe burst near main distribution line"
    ]
];

/* ================= SCHEDULE ================= */
$schedule = [
    "Monday" => [
        ["source"=>"Kaiti Source","zone"=>"Kasarani & Town Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Westlands Zone","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ],
    "Tuesday" => [
        ["source"=>"Kaiti Source","zone"=>"Kasarani & Town Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Muambani & Mwaani Zones","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ],
    "Wednesday" => [
        ["source"=>"Kaiti Source","zone"=>"Westlands Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Return Zone","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ],
    "Thursday" => [
        ["source"=>"Kaiti Source","zone"=>"Westlands Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Shimo Zone","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ],
    "Friday" => [
        ["source"=>"Kaiti & Mwaani Sources","zone"=>"Kasarani & Town Zones","time"=>"6:00 AM – 7:00 PM","class"=>"mixed"]
    ],
    "Saturday" => [
        ["source"=>"Kaiti Source","zone"=>"Westlands Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Kundakindu & Malawi Zones","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ],
    "Sunday" => [
        ["source"=>"Kaiti Source","zone"=>"Kasarani Zone","time"=>"6:00 AM – 7:00 PM","class"=>"kaiti"],
        ["source"=>"Mwaani Source","zone"=>"Kundakindu & Malawi Zones","time"=>"6:00 AM – 7:00 PM","class"=>"mwaani"]
    ]
];
?>

<!DOCTYPE html>
<html>
<head>
<title>Zoning Calendar</title>

<style>

/* ================= BASE ================= */
body{
    margin:0;
    font-family: "Segoe UI", Arial, sans-serif;
    background:#eef2f7;
}

/* MAIN WRAPPER */
.overlay{
    margin-left:260px;
    margin-top:70px;
    padding:20px;
}

/* TITLE */
.page-title{
    font-size:20px;
    font-weight:700;
    color:#0b2d5c;
    margin-bottom:16px;
}

/* ================= CALENDAR GRID ================= */
.calendar{
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:12px;
}

/* ================= DAY PANEL (CLEAN + SMALL) ================= */
.day-col{
    background:#ffffff;
    border-radius:10px;
    padding:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
    border:1px solid #e6eaf0;
}

/* DAY TITLE */
.day-title{
    font-size:13px;
    font-weight:700;
    text-align:center;
    color:#0b2d5c;
    padding-bottom:8px;
    margin-bottom:10px;
    border-bottom:1px solid #eef1f5;
}

/* ================= ENTRY ================= */
.entry{
    padding:7px 8px;
    margin-bottom:6px;
    border-radius:8px;
    font-size:12px;
    cursor:pointer;
    background:#fff;
    border-left:3px solid transparent;
    transition:0.15s ease;
}

.entry:hover{
    background:#f8fafc;
}

/* ================= ACCENT COLORS (SUBTLE ONLY ON LEFT BORDER) ================= */
.kaiti{ border-left-color:#1e88e5; }
.mwaani{ border-left-color:#43a047; }
.mixed{ border-left-color:#f9a825; }

/* ================= ALERT STATE ================= */
.alert{
    border-left-color:#c62828 !important;
    background:#fff7f7;
}

/* ================= CONTENT STRUCTURE ================= */
.source{
    font-weight:700;
    font-size:12px;
    color:#1f2d3d;
}

.zone{
    font-size:12px;
    color:#455a64;
    margin-top:2px;
}

.time{
    font-size:11px;
    color:#78909c;
    margin-top:2px;
}

/* ================= MODAL ================= */
.modal{
    display:none;
    position:fixed;
    top:0;left:0;
    width:100%;height:100%;
    background:rgba(0,0,0,0.5);
}

.modal-content{
    background:#fff;
    width:380px;
    margin:100px auto;
    padding:18px;
    border-radius:10px;
}

/* INPUTS */
input,select{
    width:100%;
    padding:8px;
    margin-bottom:8px;
    border-radius:6px;
    border:1px solid #dcdfe6;
    font-size:12px;
}

/* BUTTON */
button{
    width:100%;
    padding:9px;
    border:none;
    background:#0b2d5c;
    color:#fff;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
}

/* NOTE */
.note{
    margin-top:15px;
    background:#fff;
    padding:12px;
    border-radius:8px;
    font-size:12px;
    border:1px solid #e6eaf0;
    color:#555;
}

</style>
</head>

<body>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>

<div class="overlay">

<div class="page-title">💧 Zoning Calendar</div>

<div class="calendar">

<?php foreach($schedule as $day=>$entries): ?>
<div class="day-col">

<div class="day-title"><?php echo $day; ?></div>

<?php foreach($entries as $e):

$isUnderMaintenance = isset($maintenance_map[$day][$e['zone']]);
?>

<div class="entry <?php echo $e['class']; ?> <?php echo $isUnderMaintenance ? 'alert' : ''; ?>"
onclick="editEntry('<?php echo $day; ?>','<?php echo $e['source']; ?>','<?php echo $e['zone']; ?>','<?php echo $e['time']; ?>')">

<div class="source"><?php echo $e['source']; ?></div>
<div class="zone"><?php echo $e['zone']; ?></div>
<div class="time"><?php echo $e['time']; ?></div>

<?php if($isUnderMaintenance): ?>
<div style="color:#c62828;font-size:11px;margin-top:3px;font-weight:600;">
⚠ Maintenance
</div>
<?php endif; ?>

</div>

<?php endforeach; ?>

</div>
<?php endforeach; ?>

</div>

<div class="note">
📢 Maintenance zones are automatically flagged and excluded from supply operations.
</div>

</div>

<!-- MODAL -->
<div class="modal" id="modal">
<div class="modal-content">

<h3>Edit / Reschedule</h3>

<label>Day</label>
<select id="day">
<option>Monday</option><option>Tuesday</option><option>Wednesday</option>
<option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
</select>

<label>Source</label>
<input type="text" id="source">

<label>Zone</label>
<input type="text" id="zone">

<label>Time</label>
<input type="text" id="time">

<button onclick="saveEdit()">Save</button>

</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function editEntry(day, source, zone, time){
    document.getElementById('modal').style.display='block';
    document.getElementById('day').value=day;
    document.getElementById('source').value=source;
    document.getElementById('zone').value=zone;
    document.getElementById('time').value=time;
}

function saveEdit(){
    alert("Saved (DB integration next step)");
    document.getElementById('modal').style.display='none';
}
</script>

</body>
</html>