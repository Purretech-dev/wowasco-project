<style>
/* =========================
   WOWASCO SIDEBAR UI (COMPACT ENTERPRISE)
========================= */

.sidebar{
    position:fixed;
    width:235px;
    height:calc(100vh - 10px);
    background:#0a2a43;
    left:5px;
    top:5px;
    bottom:5px;
    overflow-y:auto;
    padding:12px 8px;
    box-shadow:2px 0 12px rgba(0,0,0,0.15);
    z-index:1000;
    border-radius:10px;
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

.submenu{
    margin-left:8px;
    padding-left:8px;
    border-left:2px solid #1f3b57;
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

.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#2563eb;
    border-radius:10px;
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
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isMetering ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('meteringMenu')">
       Metering Module ▾
    </a>

    <div id="meteringMenu" class="submenu" style="<?php echo $isMetering ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/metering/meter_register.php"
           class="<?php echo ($current_page == 'meter_register.php') ? 'active' : ''; ?>">
           Meter Register
        </a>

        <a href="dashboard.php?page=modules/metering/meter_status.php"
           class="<?php echo ($current_page == 'meter_status.php') ? 'active' : ''; ?>">
           Meter Status
        </a>

        <a href="dashboard.php?page=modules/metering/meter_dashboard.php"
           class="<?php echo ($current_page == 'meter_dashboard.php') ? 'active' : ''; ?>">
           Meter Dashboard
        </a>

        <a href="dashboard.php?page=modules/metering/meter_alerts.php"
           class="<?php echo ($current_page == 'meter_alerts.php') ? 'active' : ''; ?>">
           Meter Alerts
        </a>

    </div>

    <!-- PRODUCTION -->
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isProduction ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('productionMenu')">
       Production Module ▾
    </a>

    <div id="productionMenu" class="submenu" style="<?php echo $isProduction ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/production/pumped_volume.php"
           class="<?php echo ($current_page == 'pumped_volume.php') ? 'active' : ''; ?>">
           Pumped Volume
        </a>

        <a href="dashboard.php?page=modules/production/production_comparison.php"
           class="<?php echo ($current_page == 'production_comparison.php') ? 'active' : ''; ?>">
           Production Comparison
        </a>

    </div>


    <!-- ASSETS MODULE -->
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isAssets ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('assetsMenu')">
       Assets Module ▾
    </a>

    <div id="assetsMenu" class="submenu" style="<?php echo $isAssets ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/assets/add_asset.php"
           class="<?php echo ($current_page == 'add_asset.php') ? 'active' : ''; ?>">
           Add Asset
        </a>

        <a href="dashboard.php?page=modules/assets/view_asset.php"
           class="<?php echo ($current_page == 'view_asset.php') ? 'active' : ''; ?>">
           View Assets
        </a>

        <a href="dashboard.php?page=modules/assets/asset_maintenance.php"
           class="<?php echo ($current_page == 'asset_maintenance.php') ? 'active' : ''; ?>">
           Asset Maintenance
        </a>

    </div>

    <!-- CUSTOMER RELATIONS -->
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isCustomer ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('customerMenu')">
       Customer Relations ▾
    </a>

    <div id="customerMenu" class="submenu" style="<?php echo $isCustomer ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/customer/customer_relations.php"
           class="<?php echo ($current_page == 'customer_relations.php') ? 'active' : ''; ?>">
           Customer Relations
        </a>

        <a href="dashboard.php?page=modules/customer/register_customer.php"
           class="<?php echo ($current_page == 'register_customer.php') ? 'active' : ''; ?>">
           Register_customer
        </a>
         <a href="dashboard.php?page=modules/customer/customer_management.php"
           class="<?php echo ($current_page == 'customer_mangement.php') ? 'active' : ''; ?>">
           customer_management
        </a>

    </div>

    <!-- ZONING & GIS -->
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isZoning ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('zoningMenu')">
       Zoning & GIS ▾
    </a>

    <div id="zoningMenu" class="submenu" style="<?php echo $isZoning ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/zoning/zone_management.php"
           class="<?php echo ($current_page == 'zone_management.php') ? 'active' : ''; ?>">
            Zone Management
        </a>

        <a href="dashboard.php?page=modules/zoning/gis.php"
           class="<?php echo ($current_page == 'zoning/gis.php') ? 'active' : ''; ?>">
            GIS Module
        </a>

    </div>

    <!-- ADVANCED REPORTS -->
    <a href="javascript:void(0);"
       class="menu-toggle <?php echo $isReports ? 'active-parent' : ''; ?>"
       onclick="toggleAccordion('reportsMenu')">
       Advanced Reports ▾
    </a>

    <div id="reportsMenu" class="submenu" style="<?php echo $isReports ? 'display:block;' : 'display:none;'; ?>">

        <a href="dashboard.php?page=modules/reports/meter_reports.php"
           class="<?php echo ($current_page == 'meter_reports.php') ? 'active' : ''; ?>">
            Meter Reports
        </a>

        <a href="dashboard.php?page=modules/reports/production_reports.php"
           class="<?php echo ($current_page == 'production_reports.php') ? 'active' : ''; ?>">
            Production Reports
        </a>

        <a href="dashboard.php?page=modules/reports/custom_reports.php"
           class="<?php echo ($current_page == 'custom_reports.php') ? 'active' : ''; ?>">
            Custom Reports
        </a>

    </div>

</div>

<script>
function toggleAccordion(id) {
    const menus = document.querySelectorAll('.submenu');

    menus.forEach(menu => {
        if (menu.id !== id) menu.style.display = "none";
    });

    const target = document.getElementById(id);

    target.style.display = (target.style.display === "block") ? "none" : "block";
}
</script>