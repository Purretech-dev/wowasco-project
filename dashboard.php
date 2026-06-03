<?php
require_once __DIR__ . '/includes/auth_guard.php';
include __DIR__ . '/includes/config.php';

$userRole = $_SESSION['role'] ?? 'customer';
$userRole = $userRole === 'admin' ? 'super_admin' : $userRole;
$_SESSION['role'] = $userRole;

$isCustomerUser = ($userRole === 'customer');

/* =========================
   DEFAULT PAGE
========================= */
$page = $_GET['page'] ?? ($isCustomerUser ? 'modules/customer/customer_portal.php' : 'modules/home.php');

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
    'modules/admin/user_management.php',

    // METERING MODULE
    'modules/metering/meter_dashboard.php',
    'modules/metering/meter_management.php',
    'modules/metering/meter_alerts.php',

    // PRODUCTION MODULE (FIXED STRUCTURE)
    'modules/production/pumped_volume.php',
    'modules/production/production_comparison.php',

    // ASSETS MODULE (future-safe)
    'modules/Assets/asset_management.php',
    'modules/Assets/asset_maintenance.php',

    // APPROVAL WORKFLOW
    'modules/approvals/deactivation_checker.php',
    'modules/approvals/deactivation_approver.php',

    // CUSTOMER RELATIONS
    'modules/customer/customer_portal.php',
    'modules/customer/customer_management.php',

    // ZONING & GIS
    'modules/zoning/zone_management.php',
    'modules/zoning/gis.php',

    // REPORTS
    'modules/reports/md_dashboard.php',
    'modules/reports/metering_reports.php',
    'modules/reports/production_reports.php',
    'modules/reports/asset_reports.php',
    'modules/reports/customer_reports.php',
    'modules/reports/zoning_reports.php',
];

if ($isCustomerUser) {
    $allowed_pages = [
        'modules/customer/customer_portal.php'
    ];

    $page = 'modules/customer/customer_portal.php';
} elseif ($userRole !== 'super_admin') {
    $assignedPages = $_SESSION['allowed_pages'] ?? [];
    $assignedPages = is_array($assignedPages) ? $assignedPages : [];
    $assignedPages = array_values(array_intersect($assignedPages, $allowed_pages));

    $allowed_pages = array_values(array_unique(array_merge([
        'modules/home.php'
    ], $assignedPages)));
}

/* =========================
   SECURITY CHECK
========================= */
if (!in_array($page, $allowed_pages)) {
    $page = $allowed_pages[0] ?? 'modules/home.php';
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
    <link rel="stylesheet" href="assets/css/responsive.css">

    <!-- CHARTS (GLOBAL ONLY ONCE) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<?php if ($isCustomerUser): ?>

<style>
body.customer-shell{
    background:#f8fafc;
}

.customer-topbar{
    margin:12px;
    min-height:72px;
    background:linear-gradient(135deg,#0b2239,#12385a);
    border-radius:18px;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:14px 22px;
    box-shadow:0 12px 30px rgba(15,23,42,0.16);
}

.customer-brand{
    display:flex;
    align-items:center;
    gap:12px;
}

.customer-brand img{
    width:48px;
    height:48px;
    object-fit:contain;
    background:#fff;
    border-radius:10px;
    padding:5px;
}

.customer-brand strong{
    display:block;
    font-size:16px;
}

.customer-brand span{
    display:block;
    color:#cbd5e1;
    font-size:12px;
    margin-top:2px;
}

.customer-actions{
    display:flex;
    align-items:center;
    gap:10px;
    color:#dbeafe;
    font-size:13px;
    font-weight:700;
}

.customer-actions a{
    color:#0a2a43;
    background:#f4c542;
    text-decoration:none;
    padding:9px 12px;
    border-radius:9px;
    font-weight:800;
}

body.customer-shell .main-content{
    margin:0;
}

body.customer-shell .container,
body.customer-shell .page-content{
    margin-left:0 !important;
    margin-top:20px !important;
}

@media(max-width:720px){
    .customer-topbar{
        align-items:flex-start;
        flex-direction:column;
    }
}
</style>

<script>
document.body.classList.add('customer-shell');
</script>

<div class="customer-topbar">
    <div class="customer-brand">
        <img src="assets/images/logo1.png" alt="WOWASCO logo">
        <img src="assets/images/logo2.png" alt="WOWASCO logo">
        <div>
            <strong>WOWASCO Customer Portal</strong>
            <span>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Customer') ?></span>
        </div>
    </div>

    <div class="customer-actions">
        <span>Customer Account</span>
        <a href="auth/logout.php">Logout</a>
    </div>
</div>

<div class="main-content">
    <?php include $full_path; ?>
</div>

<?php else: ?>

<button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="mobile-sidebar-backdrop" id="mobileSidebarBackdrop"></div>

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

<script>
document.addEventListener('DOMContentLoaded', function(){
    const menuButton = document.getElementById('mobileMenuBtn');
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    const sidebar = document.querySelector('.sidebar');

    function closeMobileMenu(){
        document.body.classList.remove('sidebar-open');
    }

    if(menuButton && sidebar){
        menuButton.addEventListener('click', function(){
            document.body.classList.toggle('sidebar-open');
        });
    }

    if(backdrop){
        backdrop.addEventListener('click', closeMobileMenu);
    }

    document.querySelectorAll('.sidebar a[href]').forEach(function(link){
        link.addEventListener('click', function(){
            if(window.innerWidth <= 1024 && link.getAttribute('href') !== 'javascript:void(0);'){
                closeMobileMenu();
            }
        });
    });
});
</script>

<?php endif; ?>

</body>
</html>
