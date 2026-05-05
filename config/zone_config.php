<?php
require_once __DIR__ . '/../api/db.php';

/* ================= LOAD ZONES FROM DATABASE ================= */
$zones = [];

$result = $conn->query("
    SELECT zone_name
    FROM zones
    ORDER BY zone_name ASC
");

/* ================= BASE COORDINATE (WOTE CENTER) ================= */
$baseLat = -1.7800;
$baseLng = 37.6200;

/* spacing control to avoid overlap */
$index = 0;
$total = $result->num_rows;

/* ================= BUILD ZONE CONFIG ================= */
while ($row = $result->fetch_assoc()) {

    $zoneName = $row['zone_name'];

    /* ================= CREATE SAFE KEY ================= */
    $key = strtolower($zoneName);
    $key = preg_replace('/[^a-z0-9]/', '_', $key);
    $key = preg_replace('/_+/', '_', $key);

    /* ================= DUMMY GIS POSITIONING ================= */
    $angle = $total > 0 ? ($index * (360 / $total)) : 0;
    $radius = 0.03;

    $lat = $baseLat + ($radius * cos(deg2rad($angle)));
    $lng = $baseLng + ($radius * sin(deg2rad($angle)));

    /* ================= FINAL STRUCTURE ================= */
    $zones[$key] = [
        "zone_name" => $zoneName,
        "label"     => $zoneName,
        "lat"       => $lat,
        "lng"       => $lng
    ];

    $index++;
}

/* ================= OPTIONAL DEBUG MODE ================= */
$ZONE_DEBUG = false;

if ($ZONE_DEBUG) {
    echo "<pre>";
    print_r($zones);
    echo "</pre>";
}
?>