<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/zone_config.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Zone Configuration Debug</title>

<style>

body{
    font-family:Arial, sans-serif;
    background:#f4f6fb;
    padding:20px;
}

h2{
    color:#0b2d5c;
}

.card{
    background:white;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    max-width:900px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th, td{
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:left;
    font-size:14px;
}

th{
    background:#0b2d5c;
    color:white;
}

.badge{
    padding:3px 8px;
    border-radius:6px;
    font-size:12px;
    color:white;
}

.good{ background:green; }

</style>

</head>
<body>

<h2>🧪 Zone Configuration Debug Tool</h2>

<div class="card">

<?php if (!isset($zones) || empty($zones)): ?>

    <p style="color:red;">⚠ No zones loaded. Check zone_config.php</p>

<?php else: ?>

<table>
<tr>
<th>GIS Key</th>
<th>Zone Name</th>
<th>Latitude</th>
<th>Longitude</th>
<th>Status</th>
</tr>

<?php foreach ($zones as $key => $z): ?>

<tr>
<td><b><?= htmlspecialchars($key) ?></b></td>
<td><?= htmlspecialchars($z['zone_name'] ?? 'N/A') ?></td>
<td><?= $z['lat'] ?? 'N/A' ?></td>
<td><?= $z['lng'] ?? 'N/A' ?></td>
<td><span class="badge good">ACTIVE</span></td>
</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>

</div>

</body>
</html>
