<?php
require_once __DIR__ . '/../../api/db.php';
?>

<style>
.page-content{
    margin-left:260px;
    margin-top:75px;
    margin-bottom:60px;
    padding:24px;
    min-height:calc(100vh - 135px);
}

.module-header{
    background:#fff;
    border:1px solid #e2e8f0;
    border-left:5px solid #0a2a43;
    border-radius:14px;
    padding:22px 24px;
    margin-bottom:20px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.module-header h2{
    margin:0;
    color:#0a2a43;
    font-size:24px;
}

.module-header p{
    margin:7px 0 0;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

.coming-soon-panel{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:26px;
    box-shadow:0 4px 14px rgba(15,23,42,0.04);
}

.status-pill{
    display:inline-flex;
    align-items:center;
    background:#eff6ff;
    color:#1e3a8a;
    border:1px solid #bfdbfe;
    border-radius:999px;
    padding:7px 12px;
    font-size:12px;
    font-weight:800;
    margin-bottom:14px;
}

.coming-soon-panel h3{
    margin:0;
    color:#0f172a;
    font-size:22px;
}

.coming-soon-panel > p{
    max-width:850px;
    margin:10px 0 22px;
    color:#475569;
    font-size:14px;
    line-height:1.7;
}

.feature-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:14px;
}

.feature-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:16px;
}

.feature-card h4{
    margin:0 0 8px;
    color:#0a2a43;
    font-size:15px;
}

.feature-card p{
    margin:0;
    color:#64748b;
    font-size:13px;
    line-height:1.6;
}

.note-panel{
    margin-top:18px;
    background:#ecfdf3;
    color:#166534;
    border:1px solid #bbf7d0;
    border-left:4px solid #22c55e;
    border-radius:12px;
    padding:14px 16px;
    font-size:13px;
    line-height:1.6;
}

@media(max-width:1000px){
    .page-content{
        margin-left:0;
        padding:15px;
    }
}
</style>

<div class="page-content">

    <div class="module-header">
        <h2>GIS Mapping</h2>
        <p>Interactive zone mapping and field visibility for WOWASCO operations.</p>
    </div>

    <div class="coming-soon-panel">
        <div class="status-pill">Coming Soon</div>

        <h3>GIS Mapping Module Is Being Prepared</h3>

        <p>
            This module will give users a map-based view of WOWASCO zones, meters,
            water sources, supply schedules and field activity. The page is currently
            reserved while the GIS tools are being finalized.
        </p>

        <div class="feature-grid">

            <div class="feature-card">
                <h4>Zone Map View</h4>
                <p>Users will view operational zones on a map and inspect zone boundaries, status and assigned supply areas.</p>
            </div>

            <div class="feature-card">
                <h4>Meter Locations</h4>
                <p>Users will see mapped customer meters, meter status and basic customer allocation by zone.</p>
            </div>

            <div class="feature-card">
                <h4>Water Sources</h4>
                <p>Users will identify boreholes, tanks, treatment points and other water sources linked to service zones.</p>
            </div>

            <div class="feature-card">
                <h4>Supply Schedules</h4>
                <p>Users will view zone supply days, hours, affected areas and active rationing information.</p>
            </div>

            <div class="feature-card">
                <h4>Maintenance Visibility</h4>
                <p>Users will see scheduled maintenance, interruptions and affected zones directly from the map.</p>
            </div>

            <div class="feature-card">
                <h4>Field Operations</h4>
                <p>Users will support field planning by locating meters, assets, zones and reported service issues.</p>
            </div>

        </div>

        <div class="note-panel">
            The GIS module is coming soon. For now, use Zone Management to manage zones,
            water sources, maintenance notices and supply schedules.
        </div>
    </div>

</div>
