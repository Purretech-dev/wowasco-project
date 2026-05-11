<style>
/* =========================
   WOWASCO SIDEBAR UI ENHANCED
========================= */

.sidebar{
    position:fixed;
    width:240px;
    top:5px;
    bottom:5px;
    left:5px;
    background:linear-gradient(180deg,#082235 0%, #0a2a43 100%);
    overflow-y:auto;
    overflow-x:hidden;
    padding:14px 10px;
    padding-bottom:70px;
    box-shadow:2px 0 18px rgba(0,0,0,0.18);
    z-index:1000;
    border-radius:12px;
    scroll-behavior:smooth;
    border:1px solid rgba(255,255,255,0.04);
}

.sidebar *{
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}
/* =========================
   TYPOGRAPHY ENHANCEMENT
========================= */

.sidebar a{
    font-weight:500;
    letter-spacing:0.3px;
    line-height:1.4;
}

.menu-toggle span:first-child{
    font-size:13.2px;
    font-weight:600;
    letter-spacing:0.4px;
}

.submenu a{
    font-size:12.6px;
    font-weight:500;
    letter-spacing:0.2px;
}

.system-title{
    font-size:10.5px;
    font-weight:600;
    letter-spacing:1.2px;
    text-transform:uppercase;
    line-height:1.5;
}
/* =========================
   BRAND SECTION
========================= */

.brand{
    margin-bottom:20px;
    padding-bottom:12px;
}

/* NEW LOGO LAYOUT */
.logo-wrapper{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    margin-bottom:8px;
}

.logo-column{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:14px;
}


/* VERTICAL DIVIDER */
.vertical-divider{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:5px;
}

.vertical-divider .line{
    width:2px;
    height:20px;
    background:#ffffff;
    border-radius:10px;
}

.d1,.d2,.d3{
    width:7px;
    height:7px;
    border-radius:50%;
    box-shadow:0 0 8px rgba(255,255,255,0.25);
}

.d1{background:#2563eb;}
.d2{background:#16a34a;}
.d3{background:#f59e0b;}

.logo-img{
    width:72px;
    padding:6px;
    border-radius:14px;
    background:rgba(255,255,255,0.04);
    transition:0.3s ease;
    border:1px solid rgba(255,255,255,0.06);
}

.logo-img:hover{
    transform:scale(1.03);
    background:rgba(255,255,255,0.08);
}

.system-title{
    text-align:center;
    color:#e2e8f0;
    font-size:11px;
    letter-spacing:0.5px;
    margin-top:10px;
    opacity:0.85;
}

/* =========================
   LINKS
========================= */

.sidebar a{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:11px 13px;
    margin:4px 0;
    color:#cbd5e1;
    text-decoration:none;
    font-size:13px;
    border-radius:8px;
    transition:0.25s ease;
    position:relative;
}

.sidebar a:hover{
    background:rgba(255,255,255,0.06);
    color:#fff;
    transform:translateX(2px);
}

.sidebar a.active{
    background:linear-gradient(90deg,#2563eb,#1d4ed8);
    color:#fff;
    font-weight:600;
    box-shadow:0 4px 10px rgba(37,99,235,0.25);
}

.active-parent{
    background:rgba(22,163,74,0.12);
    color:#4ade80 !important;
    font-weight:600;
    border-left:4px solid #16a34a;
}

/* =========================
   MENU TOGGLES
========================= */

.menu-toggle{
    cursor:pointer;
    user-select:none;
    transition:0.25s;
}

.menu-toggle:hover{
    background:rgba(255,255,255,0.05);
}

/* =========================
   SUBMENUS
========================= */

.submenu{
    margin-left:10px;
    padding-left:10px;
    border-left:2px solid rgba(255,255,255,0.08);
    display:none;
    animation:fadeIn 0.25s ease;
}

.submenu.open{
    display:block;
}

.submenu a{
    font-size:12.5px;
    padding:9px 10px;
    margin:3px 0;
    border-radius:7px;
}

.submenu a.active{
    background:#16a34a;
    color:white;
    font-weight:600;
}

.submenu a:hover{
    background:#f59e0b;
    color:#111827;
}

/* =========================
   ANIMATION
========================= */

@keyframes fadeIn{
    from{
        opacity:0;
        transform:translateY(-3px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* =========================
   SCROLLBAR
========================= */

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

    <!-- BRAND -->
    <div class="brand">

        <div class="logo-wrapper">

            <!-- LEFT LOGO -->
            <div class="logo-column">
                <img src="assets/images/logo1.png" class="logo-img">
            </div>

            <!-- CENTER VERTICAL DIVIDER -->
            <div class="vertical-divider">
                <span class="d1"></span>
                <span class="line"></span>
                <span class="d2"></span>
                <span class="line"></span>
                <span class="d3"></span>
            </div>

            <!-- RIGHT LOGO -->
            <div class="logo-column">
                <img src="assets/images/logo2.png" class="logo-img">
            </div>

        </div>

        <div class="system-title">
            WOWASCO SMART MANAGEMENT SYSTEM
        </div>

    </div>

    <!-- DASHBOARD -->
    <a href="dashboard.php?page=modules/home.php"
       class="<?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
       <span>Dashboard</span>
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
       <span>Metering Module</span>
       <span>▾</span>
    </a>

    <div id="meteringMenu" class="submenu <?php echo $isMetering ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/metering/meter_register.php" class="<?php echo ($current_page == 'meter_register.php') ? 'active' : ''; ?>">Meter Register</a>
        <a href="dashboard.php?page=modules/metering/meter_status.php" class="<?php echo ($current_page == 'meter_status.php') ? 'active' : ''; ?>">Meter Status</a>
        <a href="dashboard.php?page=modules/metering/meter_dashboard.php" class="<?php echo ($current_page == 'meter_dashboard.php') ? 'active' : ''; ?>">Meter Dashboard</a>
        <a href="dashboard.php?page=modules/metering/meter_alerts.php" class="<?php echo ($current_page == 'meter_alerts.php') ? 'active' : ''; ?>">Meter Alerts</a>
    </div>

    <!-- PRODUCTION -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isProduction ? 'active-parent' : ''; ?>" data-target="productionMenu">
       <span>Production Module</span>
       <span>▾</span>
    </a>

    <div id="productionMenu" class="submenu <?php echo $isProduction ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/production/pumped_volume.php">Pumped Volume</a>
        <a href="dashboard.php?page=modules/production/production_comparison.php">Production Comparison</a>
    </div>

    <!-- ASSETS -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isAssets ? 'active-parent' : ''; ?>" data-target="assetsMenu">
       <span>Assets Module</span>
       <span>▾</span>
    </a>

    <div id="assetsMenu" class="submenu <?php echo $isAssets ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/assets/add_asset.php">Add Asset</a>
        <a href="dashboard.php?page=modules/assets/view_asset.php">View Assets</a>
        <a href="dashboard.php?page=modules/assets/asset_maintenance.php">Asset Maintenance</a>
    </div>

    <!-- CUSTOMER -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isCustomer ? 'active-parent' : ''; ?>" data-target="customerMenu">
       <span>Customer Relations</span>
       <span>▾</span>
    </a>

    <div id="customerMenu" class="submenu <?php echo $isCustomer ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/customer/customer_relations.php">Customer Relations</a>
        <a href="dashboard.php?page=modules/customer/register_customer.php">Register Customer</a>
        <a href="dashboard.php?page=modules/customer/customer_management.php">Customer Management</a>
    </div>

    <!-- ZONING -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isZoning ? 'active-parent' : ''; ?>" data-target="zoningMenu">
       <span>Zoning & GIS</span>
       <span>▾</span>
    </a>

    <div id="zoningMenu" class="submenu <?php echo $isZoning ? 'open' : ''; ?>">
        <a href="dashboard.php?page=modules/zoning/zone_management.php">Zone Management</a>
        <a href="dashboard.php?page=modules/zoning/gis.php">GIS Module</a>
    </div>

    <!-- REPORTS -->
    <a href="javascript:void(0);" class="menu-toggle <?php echo $isReports ? 'active-parent' : ''; ?>" data-target="reportsMenu">
       <span>Advanced Reports</span>
       <span>▾</span>
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
        toggle.replaceWith(toggle.cloneNode(true));
    });

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

/* INITIALIZE */
document.addEventListener("DOMContentLoaded", initSidebar);

/* RE-INITIALIZE AFTER DYNAMIC CLICKS */
document.addEventListener("click", function () {
    setTimeout(initSidebar, 50);
});
</script>