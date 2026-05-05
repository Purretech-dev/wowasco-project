<style>
/* =========================
   WOWASCO SIDEBAR UI (FIXED SCROLL + STABLE TOGGLE)
========================= */

.sidebar{
    position:fixed;
    width:235px;
    top:5px;
    bottom:5px;
    background:#0a2a43;
    left:5px;
    overflow-y:auto;
    overflow-x:hidden;
    padding:12px 8px;
    padding-bottom:60px;
    box-shadow:2px 0 12px rgba(0,0,0,0.15);
    z-index:1000;
    border-radius:10px;
    scroll-behavior:smooth;
}

.sidebar * {
    box-sizing:border-box;
}

.brand{
    text-align:center;
    margin-bottom:18px;
}

.logo-img{
    width:75px;
    margin:8px auto;
    display:block;
}

.divider{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    margin:8px 0;
}

.divider .line{
    width:18px;
    height:2px;
    background:#2563eb;
}

.d1,.d2,.d3{
    width:7px;
    height:7px;
    border-radius:50%;
}

.d1{background:#2563eb;}
.d2{background:#16a34a;}
.d3{background:#f59e0b;}

.sidebar a{
    display:block;
    padding:10px 12px;
    margin:3px 0;
    color:#cbd5e1;
    text-decoration:none;
    font-size:13px;
    border-radius:6px;
    transition:0.25s;
}

.sidebar a:hover{
    background:#123a5a;
    color:#fff;
    transform:translateX(2px);
}

.sidebar a.active{
    background:#2563eb;
    color:#fff;
    font-weight:600;
}

.active-parent{
    background:#0f3d2e;
    color:#16a34a !important;
    font-weight:600;
    border-left:4px solid #16a34a;
}

.menu-toggle{
    cursor:pointer;
    font-weight:500;
}

/* ✅ CONTROLLED TOGGLE SYSTEM */
.submenu{
    margin-left:8px;
    padding-left:8px;
    border-left:2px solid #1f3b57;
    display:none;
}

.submenu.open{
    display:block;
}

.submenu a{
    font-size:12.5px;
    padding:9px 10px;
}

.submenu a.active{
    background:#16a34a;
    color:white;
}

.submenu a:hover{
    background:#f59e0b;
    color:#111;
}

/* SCROLLBAR */
.sidebar::-webkit-scrollbar{
    width:6px;
}
.sidebar::-webkit-scrollbar-thumb{
    background:#2563eb;
    border-radius:10px;
}
.sidebar::-webkit-scrollbar-track{
    background:transparent;
}
</style>

<div class="sidebar">

    <div class="brand">
        <img src="assets/images/logo1.png" class="logo-img">

        <div class="divider">
            <span class="d1"></span>
            <span class="line"></span>
            <span class="d2"></span>
            <span class="line"></span>
            <span class="d3"></span>
        </div>

        <img src="assets/images/logo2.png" class="logo-img">
    </div>

    <a href="dashboard.php?page=modules/home.php"
       class="<?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
       Dashboard
    </a>

    <?php
        $isMetering = strpos($page, 'metering') !== false;
        $isProduction = strpos($page, 'production') !== false;
        $isAssets = strpos($page, 'assets') !== false;
        $isCustomer = strpos($page, 'customer') !== false;
        $isZoning = strpos($page, 'zoning') !== false;
        $isReports = strpos($page, 'reports') !== false;
    ?>

    <!-- METERING -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isMetering ? 'active-parent' : ''; ?>" data-target="meteringMenu">
       Metering Module ▾
    </a>

    <div id="meteringMenu" class="submenu <?php echo $isMetering ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/metering/meter_register.php" class="<?php echo ($current_page == 'meter_register.php') ? 'active' : ''; ?>">Meter Register</a>
        <a href="dashboard.php?page=modules/metering/meter_status.php" class="<?php echo ($current_page == 'meter_status.php') ? 'active' : ''; ?>">Meter Status</a>
        <a href="dashboard.php?page=modules/metering/meter_dashboard.php" class="<?php echo ($current_page == 'meter_dashboard.php') ? 'active' : ''; ?>">Meter Dashboard</a>
        <a href="dashboard.php?page=modules/metering/meter_alerts.php" class="<?php echo ($current_page == 'meter_alerts.php') ? 'active' : ''; ?>">Meter Alerts</a>
    </div>

    <!-- PRODUCTION -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isProduction ? 'active-parent' : ''; ?>" data-target="productionMenu">
       Production Module ▾
    </a>

    <div id="productionMenu" class="submenu <?php echo $isProduction ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/production/pumped_volume.php">Pumped Volume</a>
        <a href="dashboard.php?page=modules/production/production_comparison.php">Production Comparison</a>
    </div>

    <!-- ASSETS -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isAssets ? 'active-parent' : ''; ?>" data-target="assetsMenu">
       Assets Module ▾
    </a>

    <div id="assetsMenu" class="submenu <?php echo $isAssets ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/assets/add_asset.php">Add Asset</a>
        <a href="dashboard.php?page=modules/assets/view_asset.php">View Assets</a>
        <a href="dashboard.php?page=modules/assets/asset_maintenance.php">Asset Maintenance</a>
    </div>

    <!-- CUSTOMER -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isCustomer ? 'active-parent' : ''; ?>" data-target="customerMenu">
       Customer Relations ▾
    </a>

    <div id="customerMenu" class="submenu <?php echo $isCustomer ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/customer/customer_relations.php">Customer Relations</a>
        <a href="dashboard.php?page=modules/customer/register_customer.php">Register Customer</a>
        <a href="dashboard.php?page=modules/customer/customer_management.php">Customer Management</a>
    </div>

    <!-- ZONING -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isZoning ? 'active-parent' : ''; ?>" data-target="zoningMenu">
       Zoning & GIS ▾
    </a>

    <div id="zoningMenu" class="submenu <?php echo $isZoning ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/zoning/zone_management.php">Zone Management</a>
        <a href="dashboard.php?page=modules/zoning/gis.php">GIS Module</a>
    </div>

    <!-- REPORTS -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isReports ? 'active-parent' : ''; ?>" data-target="reportsMenu">
       Advanced Reports ▾
    </a>

    <div id="reportsMenu" class="submenu <?php echo $isReports ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/reports/meter_reports.php">Meter Reports</a>
        <a href="dashboard.php?page=modules/reports/production_reports.php">Production Reports</a>
        <a href="dashboard.php?page=modules/reports/custom_reports.php">Custom Reports</a>
    </div>

</div>

<script>
function initSidebar() {

    const toggles = document.querySelectorAll(".menu-toggle");

    toggles.forEach(toggle => {

        // Remove old listeners (prevents duplication bugs)
        toggle.replaceWith(toggle.cloneNode(true));
    });

    // Re-select after cloning
    const freshToggles = document.querySelectorAll(".menu-toggle");

    freshToggles.forEach(toggle => {

        toggle.addEventListener("click", function () {

            const targetId = this.getAttribute("data-target");
            const targetMenu = document.getElementById(targetId);

            document.querySelectorAll(".submenu").forEach(menu => {
                if(menu !== targetMenu){
                    menu.classList.remove("open");
                }
            });

            targetMenu.classList.toggle("open");

        });

    });
}

/* RUN ON LOAD */
document.addEventListener("DOMContentLoaded", initSidebar);

/* 🔥 CRITICAL: RUN AFTER ANY CLICK (fixes your issue) */
document.addEventListener("click", function () {
    setTimeout(initSidebar, 50);
});
</script>