<style>

/* =========================
   WOWASCO PREMIUM NAVBAR
========================= */

.navbar{
    position:fixed;
    top:8px;
    left:260px;
    right:8px;

    height:72px;

    background:linear-gradient(135deg,#0b2239,#12385a);

    display:flex;
    align-items:center;
    justify-content:space-between;

    padding:0 28px;

    border-radius:20px;

    border:1px solid rgba(255,255,255,0.08);

    box-shadow:
        0 12px 30px rgba(15,23,42,0.18),
        0 2px 8px rgba(15,23,42,0.08);

    backdrop-filter:blur(12px);

    z-index:1000;
    color:white;

    transition:all 0.25s ease;
}

/* ================= TITLE ================= */

.nav-left{
    display:flex;
    align-items:center;
}

.nav-title{
    margin:0;
    font-family:"Inter","Segoe UI",sans-serif;
    font-size:24px;
    font-weight:800;
    letter-spacing:-0.6px;
    color:#ffffff;
    line-height:1.2;
}

/* ================= RIGHT SIDE ================= */

.nav-right{
    display:flex;
    align-items:center;
    gap:16px;
    font-family:"Inter","Segoe UI",sans-serif;
    font-size:14px;
    color:#cbd5e1;
}

/* ================= USER BADGE ================= */

.nav-right span{
    background:linear-gradient(135deg,#16a34a,#22c55e);
    padding:10px 16px;
    border-radius:999px;
    font-size:13px;
    color:white;
    font-weight:700;
    letter-spacing:0.2px;

    border:1px solid rgba(255,255,255,0.08);

    box-shadow:
        0 6px 18px rgba(34,197,94,0.25),
        inset 0 1px 0 rgba(255,255,255,0.12);

    transition:all 0.2s ease;
}

.nav-right span:hover{
    transform:translateY(-1px);
    box-shadow:
        0 10px 22px rgba(34,197,94,0.32),
        inset 0 1px 0 rgba(255,255,255,0.12);
}

/* ================= RESPONSIVE ================= */

@media(max-width:1024px){

    .navbar{
        left:8px;
        right:8px;
        padding:0 18px;
    }

    .nav-title{
        font-size:18px;
    }
}

@media(max-width:768px){

    .navbar{
        height:auto;
        min-height:72px;
        flex-direction:column;
        align-items:flex-start;
        justify-content:center;
        gap:10px;
        padding:14px 18px;
    }

    .nav-right{
        width:100%;
        justify-content:flex-start;
    }

    .nav-title{
        font-size:17px;
    }
}
</style>

<div class="navbar">

    <div class="nav-left">
        <h3 class="nav-title">
            💧 Wote Water & Sanitation Company (WOWASCO)
        </h3>
    </div>

    <div class="nav-right">
        <span>👤 Welcome, Admin</span>
    </div>

</div>