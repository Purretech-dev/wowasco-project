<style>
/* =========================
   WOWASCO HEADER BAR (ENHANCED)
========================= */

.navbar{
    position:fixed;
    top:5px;
    left:260px; /* aligns with sidebar */
    right:5px;

    height:60px;

    /* 🔥 smoother modern UI */
    background:linear-gradient(90deg,#0a2a43,#123a5a);

    display:flex;
    align-items:center;
    justify-content:space-between;

    padding:0 22px;

    /* smooth ends like sidebar */
    border-radius:10px;

    /* 🔥 BLUE BOUNDARY TO MATCH SIDEBAR */
    border-left:4px solid #2563eb;
    border-bottom:2px solid #2563eb;

    box-shadow:0 3px 12px rgba(0,0,0,0.15);

    z-index:1000;
    color:white;
}

/* TITLE */
.nav-title{
    font-size:22px;
    font-weight:600;
    letter-spacing:0.5px;
    color:#ffffff;
}

/* RIGHT SIDE */
.nav-right{
    display:flex;
    align-items:center;
    gap:15px;
    font-size:14px;
    color:#cbd5e1;
}

/* USER BADGE */
.nav-right span{
    background:#16a34a;
    padding:6px 12px;
    border-radius:20px;
    font-size:13px;
    color:white;
    font-weight:500;
    box-shadow:0 2px 6px rgba(0,0,0,0.2);
}

/* RESPONSIVE */
@media(max-width:1024px){
    .navbar{
        left:5px;
    }
}
</style>

<div class="navbar">
    <div class="nav-left">
        <h3 class="nav-title">Wote Water & Sanitation Company (WOWASCO)</h3>
    </div>

    <div class="nav-right">
        <span>Welcome, Admin</span>
    </div>
</div>