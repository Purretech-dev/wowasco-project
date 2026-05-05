<?php
include __DIR__ . '/includes/config.php';

/* =========================
   DEFAULT PAGE
========================= */
$page = $_GET['page'] ?? 'modules/home.php';

/* =========================
   CLEAN NORMALIZATION
   (prevents path tricks + duplicate slashes)
========================= */
$page = str_replace(['..', '\\'], '', $page);
$page = ltrim($page, '/');

/* =========================
   ALLOWED PAGES (FULL CONTROL)
========================= */
$allowed_pages = [

    // DASHBOARD
    'modules/home.php',

    // METERING MODULE
    'modules/metering/meter_dashboard.php',
    'modules/metering/meter_register.php',
    'modules/metering/meter_status.php',
    'modules/metering/meter_alerts.php',

    // PRODUCTION MODULE (FIXED STRUCTURE)
    'modules/production/pumped_volume.php',
    'modules/production/production_comparison.php',

    // ASSETS MODULE (future-safe)
    'modules/assets/add_asset.php',
    'modules/assets/view_asset.php',
    'modules/assets/asset_maintenance.php',

    // CUSTOMER RELATIONS
    'modules/customer/customer_relations.php',
    'modules/customer/register_customer.php',
    'modules/customer/customer_management.php',

    // ZONING & GIS
    'modules/zoning/zone_management.php',
    'modules/zoning/gis.php',

    // REPORTS
    'modules/reports/advanced_reports.php',
    'modules/reports/custom_reports.php',
];

/* =========================
   SECURITY CHECK
========================= */
if (!in_array($page, $allowed_pages)) {
    $page = 'modules/home.php';
}

/* =========================
   SAFE FILE RESOLVE
========================= */
$full_path = __DIR__ . '/' . $page;

if (!file_exists($full_path)) {
    $full_path = __DIR__ . '/modules/home.php';
}

/* =========================
   CURRENT PAGE (MENU ACTIVE STATE)
========================= */
$current_page = basename($page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WOWASCO System</title>

    <!-- GLOBAL CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- CHARTS (GLOBAL ONLY ONCE) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<!-- =========================
   SIDEBAR (ALWAYS FIXED)
========================= -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- =========================
   NAVBAR (ALWAYS FIXED)
========================= -->
<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- =========================
   MAIN CONTENT WRAPPER
   (THIS IS CRITICAL FIX)
========================= -->
<div class="main-content">

    <?php include $full_path; ?>

</div>

<!-- =========================
   FOOTER (ALWAYS FIXED)
========================= -->
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>