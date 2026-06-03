<?php
require_once __DIR__ . '/auth_guard.php';

$page = $_GET['page'] ?? 'modules/home.php';

if (!function_exists('isActivePage')) {
    function isActivePage($target){
        global $page;
        return $page === $target ? 'active' : '';
    }
}

if (!function_exists('isActiveModule')) {
    function isActiveModule($keyword){
        global $page;
        return stripos($page, $keyword) !== false;
    }
}

if (!function_exists('canAccessPage')) {
    function canAccessPage($target){
        $role = $_SESSION['role'] ?? 'customer';
        $role = $role === 'admin' ? 'super_admin' : $role;

        if ($role === 'super_admin') {
            return true;
        }

        if ($role === 'customer') {
            return $target === 'modules/customer/customer_portal.php';
        }

        $allowedPages = $_SESSION['allowed_pages'] ?? [];
        $allowedPages = is_array($allowedPages) ? $allowedPages : [];

        return $target === 'modules/home.php' || in_array($target, $allowedPages, true);
    }
}

if (!function_exists('canAccessAnyPage')) {
    function canAccessAnyPage($targets){
        foreach ($targets as $target) {
            if (canAccessPage($target)) {
                return true;
            }
        }

        return false;
    }
}

$menus = [
    'meteringMenu' => [
        'title' => 'Metering Module',
        'active' => isActiveModule('modules/metering'),
        'pages' => [
            'modules/metering/meter_management.php' => 'Meter Management',
            'modules/metering/meter_dashboard.php' => 'Meter Dashboard',
            'modules/metering/meter_alerts.php' => 'Meter Alerts'
        ]
    ],
    'productionMenu' => [
        'title' => 'Production Module',
        'active' => isActiveModule('modules/production'),
        'pages' => [
            'modules/production/pumped_volume.php' => 'Pumped Volume',
            'modules/production/production_comparison.php' => 'Production Comparison'
        ]
    ],
    'assetsMenu' => [
        'title' => 'Assets Module',
        'active' => isActiveModule('modules/Assets'),
        'pages' => [
            'modules/Assets/asset_management.php' => 'Asset Management',
            'modules/Assets/asset_maintenance.php' => 'Asset Maintenance'
        ]
    ],
    'customerMenu' => [
        'title' => 'Customer Relations',
        'active' => isActiveModule('modules/customer'),
        'pages' => [
            'modules/customer/customer_portal.php' => 'Customer Portal',
            'modules/customer/customer_management.php' => 'Customer Management'
        ]
    ],
    'zoningMenu' => [
        'title' => 'Zoning & GIS',
        'active' => isActiveModule('modules/zoning'),
        'pages' => [
            'modules/zoning/zone_management.php' => 'Zone Management',
            'modules/zoning/gis.php' => 'GIS Module'
        ]
    ],
    'approvalsMenu' => [
        'title' => 'Approval Workflow',
        'active' => isActiveModule('modules/approvals'),
        'pages' => [
            'modules/approvals/deactivation_checker.php' => 'Checker Workbench',
            'modules/approvals/deactivation_approver.php' => 'MD Approval'
        ]
    ],
    'reportsMenu' => [
        'title' => 'Advanced Reports',
        'active' => isActiveModule('modules/reports'),
        'pages' => [
            'modules/reports/md_dashboard.php' => 'Report Dashboard',
            'modules/reports/metering_reports.php' => 'Meter Reports',
            'modules/reports/production_reports.php' => 'Production Reports',
            'modules/reports/asset_reports.php' => 'Asset Reports',
            'modules/reports/customer_reports.php' => 'Customer Reports',
            'modules/reports/zoning_reports.php' => 'Zoning Reports'
        ]
    ]
];
?>

<style>
.sidebar{
    position:fixed;
    width:240px;
    top:5px;
    bottom:5px;
    left:5px;
    background:linear-gradient(180deg,#082235 0%, #0a2a43 100%);
    overflow-y:auto;
    overflow-x:hidden;
    padding:14px 10px 70px;
    box-shadow:2px 0 18px rgba(0,0,0,0.18);
    z-index:1000;
    border-radius:12px;
    scroll-behavior:smooth;
    border:1px solid rgba(37,99,235,0.25);
}

.sidebar *{
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

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
    font-weight:500;
    letter-spacing:0.3px;
    line-height:1.4;
}

.sidebar a:hover,
.submenu a:hover{
    background:rgba(245, 206, 11, 0.98);
    color:#ffffff;
    transform:translateX(2px);
    box-shadow:0 4px 12px rgba(245,158,11,0.22);
}

.sidebar a.active,
.submenu a.active{
    background:#16a34a;
    color:#ffffff !important;
    font-weight:700;
    border-left:3px solid #bbf7d0;
    box-shadow:0 4px 12px rgba(22,163,74,0.28);
}

.active-parent{
    background:rgba(22,163,74,0.15) !important;
    color:#4ade80 !important;
    font-weight:700 !important;
    border-left:4px solid #16a34a;
    box-shadow:inset 0 0 0 1px rgba(22,163,74,0.15);
}

.brand{
    margin-bottom:20px;
    padding-bottom:12px;
}

.logo-wrapper{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    margin-bottom:8px;
}

.logo-column{
    display:flex;
    align-items:center;
    justify-content:center;
}

.vertical-divider{
    height:72px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:space-between;
    padding:2px 0;
}

.vertical-divider .line{
    width:2px;
    flex:1;
    background:#ffffff;
    border-radius:10px;
    min-height:18px;
}

.d1,.d2,.d3{
    width:8px;
    height:8px;
    border-radius:50%;
    box-shadow:0 0 8px rgba(255,255,255,0.25);
    flex-shrink:0;
}

.d1{background:#2563eb;}
.d2{background:#16a34a;}
.d3{background:#f59e0b;}

.logo-img{
    width:72px;
    height:72px;
    object-fit:contain;
    padding:6px;
    border-radius:14px;
    background:rgba(37,99,235,0.12);
    transition:0.3s ease;
    border:1px solid rgba(245,158,11,0.22);
}

.logo-img:hover{
    transform:scale(1.03);
    background:rgba(245,158,11,0.18);
}

.system-title{
    text-align:center;
    color:#e2e8f0;
    font-size:10.5px;
    font-weight:600;
    letter-spacing:1.2px;
    text-transform:uppercase;
    line-height:1.5;
    margin-top:10px;
}

.menu-toggle{
    cursor:pointer;
    user-select:none;
}

.menu-toggle span:first-child{
    font-size:13.2px;
    font-weight:600;
    letter-spacing:0.4px;
}

.submenu{
    margin-left:10px;
    padding-left:10px;
    border-left:2px solid rgba(37,99,235,0.35);
    display:none;
    animation:fadeIn 0.25s ease;
}

.submenu.open{
    display:block;
}

.submenu a{
    font-size:12.6px;
    font-weight:500;
    letter-spacing:0.2px;
    padding:9px 10px;
    margin:3px 0;
    border-radius:7px;
}

@keyframes fadeIn{
    from{opacity:0; transform:translateY(-3px);}
    to{opacity:1; transform:translateY(0);}
}

.sidebar::-webkit-scrollbar{
    width:6px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#16a34a;
    border-radius:10px;
}

.sidebar::-webkit-scrollbar-track{
    background:#0a2a43;
}
</style>

<div class="sidebar">

    <div class="brand">
        <div class="logo-wrapper">
            <div class="logo-column">
                <img src="assets/images/logo1.png" class="logo-img" alt="WOWASCO logo">
            </div>

            <div class="vertical-divider">
                <span class="d1"></span>
                <span class="line"></span>
                <span class="d2"></span>
                <span class="line"></span>
                <span class="d3"></span>
            </div>

            <div class="logo-column">
                <img src="assets/images/logo2.png" class="logo-img" alt="WOWASCO logo">
            </div>
        </div>

        <div class="system-title">
            WOWASCO SMART MANAGEMENT SYSTEM
        </div>
    </div>

    <?php if (canAccessPage('modules/home.php')): ?>
        <a href="dashboard.php?page=modules/home.php" class="<?= $page === 'modules/home.php' ? 'active' : '' ?>">
            <span>Dashboard</span>
        </a>
    <?php endif; ?>

    <?php foreach ($menus as $menuId => $menu): ?>
        <?php if (!canAccessAnyPage(array_keys($menu['pages']))) continue; ?>

        <a href="javascript:void(0);" class="menu-toggle <?= $menu['active'] ? 'active-parent' : '' ?>" data-target="<?= htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8') ?>">
            <span><?= htmlspecialchars($menu['title'], ENT_QUOTES, 'UTF-8') ?></span><span>v</span>
        </a>

        <div id="<?= htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8') ?>" class="submenu <?= $menu['active'] ? 'open' : '' ?>">
            <?php foreach ($menu['pages'] as $target => $label): ?>
                <?php if (!canAccessPage($target)) continue; ?>
                <a href="dashboard.php?page=<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>" class="<?= isActivePage($target) ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <a href="dashboard.php?page=modules/admin/user_management.php" class="<?= isActivePage('modules/admin/user_management.php') ?>">
            <span>User Management</span>
        </a>
    <?php endif; ?>

</div>

<script>
function initSidebar(){
    document.querySelectorAll(".menu-toggle").forEach(toggle => {
        toggle.onclick = function(){
            const targetMenu = document.getElementById(this.getAttribute("data-target"));

            document.querySelectorAll(".submenu").forEach(menu => {
                if(menu !== targetMenu){
                    menu.classList.remove("open");
                }
            });

            if(targetMenu){
                targetMenu.classList.toggle("open");
            }
        };
    });
}

document.addEventListener("DOMContentLoaded", initSidebar);
</script>
