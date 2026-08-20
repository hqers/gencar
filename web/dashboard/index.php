<?php
require_once __DIR__ . '/../config.php';
// Dashboard publik — tidak perlu login, tapi tetap deteksi status login
// untuk menampilkan tombol Masuk/Keluar & Mapping di navbar.
session_name(SESSION_NAME);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
getDB(); // inisialisasi DB

$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && (($_SESSION['role'] ?? '') === 'admin');
$namaUser   = $_SESSION['nama'] ?? $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SELARAS — Dashboard SE2026</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="../favicon.ico">
<meta name="theme-color" content="#1A2744">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{
  --o:#E8560A;--o-h:#C94508;--o-lt:#FDE8DD;--o-mid:#F5C98A;
  --amber:#F5A623;--amber-lt:#FEF3DC;
  --navy:#1A2744;--navy2:#243158;
  --ok:#16A34A;--ok-lt:#DCFCE7;
  --red:#DC2626;--red-lt:#FEE2E2;
  --amb:#D97706;--amb-lt:#FEF3C7;
  --slate:#6B7280;--sl-lt:#F5F6FA;
  --text:#1A1A2E;--text2:#6B7280;--border:rgba(0,0,0,0.08);--bg:#F5F6FA;
  --grad:linear-gradient(135deg,#E8560A,#F5A623);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}

/* NAV */
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o);flex-shrink:0}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-right{display:flex;align-items:center;gap:10px}
.nav-user{color:rgba(255,255,255,.7);font-size:12px}
.nav-ts{color:rgba(255,255,255,.6);font-size:11px;text-align:right;line-height:1.5}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;cursor:pointer;font-family:inherit;transition:background .15s}
.nav-btn:hover{background:rgba(255,255,255,.18)}

/* TABNAV */
.tabnav{background:#fff;border-bottom:2px solid var(--border);padding:0 24px;display:flex}
.tablink{padding:12px 20px;font-size:13px;font-weight:600;color:var(--text2);border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;transition:color .15s,border-color .15s;display:flex;align-items:center;gap:6px}
.tablink:hover{color:var(--o)}
.tablink.active{color:var(--o);border-bottom-color:var(--o)}

/* IBAR */
.ibar{background:#fff;border-bottom:3px solid var(--o);padding:9px 24px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:12px;color:var(--text2)}
.iitem{display:flex;align-items:center;gap:5px}
.iitem i{color:var(--o);font-size:14px}
.iitem strong{color:var(--text)}
.bnote{background:var(--amb-lt);color:var(--amb);border:1px solid var(--o-mid);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600}
.dsep{width:4px;height:4px;border-radius:50%;background:var(--border)}

.main{max-width:1200px;margin:0 auto;padding:20px 16px}

/* TAB PANELS */
.tab-panel{display:none}
.tab-panel.active{display:block}

/* FILTER */
.frow{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:18px}
select{padding:7px 28px 7px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 8px center;appearance:none;color:var(--text);outline:none;cursor:pointer}
select:focus{border-color:var(--o)}
.sbox{flex:1;min-width:200px;position:relative}
.sbox input{width:100%;padding:7px 10px 7px 34px;border:1px solid var(--border);border-radius:6px;font-size:13px;outline:none;background:#fff;color:var(--text)}
.sbox input:focus{border-color:var(--o)}
.sbox i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text2);font-size:15px}
.pcnt{background:var(--o-lt);color:var(--o-h);border:1px solid var(--o-mid);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600}

/* CARDS */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 16px;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.ct::before{background:var(--navy)}.cs::before{background:var(--amb)}.ca::before{background:var(--ok)}
.cr::before{background:var(--red)}.co::before{background:var(--slate)}.cp::before{background:var(--o)}
.cpa::before{background:#1A7A5E}.ctg::before{background:#6C3483}
.clbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--text2);margin-bottom:6px}
.cval{font-size:24px;font-weight:800;line-height:1}
.ct .cval{color:var(--navy)}.cs .cval{color:var(--amb)}.ca .cval{color:var(--ok)}
.cr .cval{color:var(--red)}.co .cval{color:var(--slate)}.cp .cval{color:var(--o-h)}
.cpa .cval{color:#1A7A5E}.ctg .cval{color:#6C3483}
.cnote{font-size:11px;color:var(--text2);margin-top:4px}
.target-bar-wrap{margin-top:8px}
.target-bar-bg{background:var(--border);border-radius:4px;height:6px;overflow:hidden}
.target-bar-fill{height:6px;border-radius:4px;background:var(--o);transition:width .6s}

/* TOP/BOT */
.tb-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.tb-card{background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden}
.tb-head{padding:12px 16px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border)}
.tb-head.top{background:#DCFCE7}.tb-head.bot{background:#FEE2E2}
.tb-head-title{font-size:13px;font-weight:700}
.tb-head.top .tb-head-title{color:var(--ok)}.tb-head.bot .tb-head-title{color:var(--red)}
.tb-row{display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid #F5F5F5}
.tb-row:last-child{border-bottom:none}
.tb-rank{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.top .tb-rank{background:var(--ok-lt);color:var(--ok)}.bot .tb-rank{background:var(--red-lt);color:var(--red)}
.tb-info{flex:1;min-width:0}
.tb-name{font-size:12px;font-weight:600;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tb-email{font-size:10px;color:var(--text2)}
.tb-bar-bg{height:4px;background:var(--border);border-radius:2px;margin-top:3px;overflow:hidden}
.tb-bar-fill{height:4px;border-radius:2px}
.top .tb-bar-fill{background:var(--ok)}.bot .tb-bar-fill{background:var(--red)}
.tb-val{font-size:14px;font-weight:800;flex-shrink:0}
.top .tb-val{color:var(--ok)}.bot .tb-val{color:var(--red)}

/* TABLE */
.tw{background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden}
.th2{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:var(--sl-lt)}
.thl{font-size:14px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:8px}
.thl i{color:var(--o);font-size:18px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead tr{background:var(--sl-lt)}
th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);white-space:nowrap;cursor:pointer;user-select:none;border-bottom:1px solid var(--border)}
th:hover{background:#E4E8F0}
th.asc::after{content:" ▲"}th.desc::after{content:" ▼"}
td{padding:10px 12px;font-size:13px;border-bottom:1px solid #F0F0F0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FDFAF6}
.nm{font-weight:600;font-size:13px;color:var(--navy)}
.em{font-size:11px;color:var(--text2);margin-top:2px}
.chip{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:700}
.csub{background:var(--amb-lt);color:var(--amb)}.capp{background:var(--ok-lt);color:var(--ok)}
.crej{background:var(--red-lt);color:var(--red)}.copn{background:var(--sl-lt);color:var(--slate)}
.empty{text-align:center;padding:48px;color:var(--text2)}
.spin{width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--o);border-radius:50%;animation:sp .8s linear infinite;margin:0 auto 12px}
@keyframes sp{to{transform:rotate(360deg)}}

/* PML GRID */
.pml-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:20px}
.pml-card{background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden}
.pml-card-head{padding:12px 16px;background:var(--navy);display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none}
.pml-card-name{color:#fff;font-size:14px;font-weight:700}
.pml-card-count{color:rgba(255,255,255,.55);font-size:11px;display:flex;align-items:center;gap:8px}
.pml-toggle-btn{background:rgba(255,255,255,.15);border:none;color:rgba(255,255,255,.8);font-size:11px;padding:3px 8px;border-radius:4px;cursor:pointer;font-family:inherit;white-space:nowrap}
.pml-toggle-btn:hover{background:rgba(255,255,255,.25)}
.pml-card-body{padding:12px 16px}
.pml-stats{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.pml-stat{flex:1;min-width:55px;text-align:center;background:var(--sl-lt);border-radius:6px;padding:6px 4px}
.pml-stat-val{font-size:15px;font-weight:800}
.pml-stat-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-top:2px}
.s-sel .pml-stat-val{color:var(--navy)}.s-sub .pml-stat-val{color:var(--amb)}
.s-app .pml-stat-val{color:var(--ok)}.s-rej .pml-stat-val{color:var(--red)}
.s-dlt .pml-stat-val{color:var(--o-h)}
.pml-prog-wrap{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.pml-prog-bg{flex:1;background:var(--border);border-radius:4px;height:8px;overflow:hidden}
.pml-prog-fill{height:8px;border-radius:4px;transition:width .5s}
.pml-prog-pct{font-size:12px;font-weight:700;min-width:40px;text-align:right}
.pml-pencacah-title{display:flex;justify-content:space-between;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:6px}
.pml-pc-row{display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #F5F5F5;font-size:11px;gap:2px}
.pml-pc-row:last-child{border-bottom:none}
.pml-pc-name{color:var(--navy);font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-right:6px;font-size:12px}
.pml-pc-val{font-weight:700;color:var(--navy);min-width:28px;text-align:right}
.pml-pc-num{font-weight:700;color:var(--navy);min-width:26px;text-align:right}
.pml-pc-pct{font-weight:700;min-width:38px;text-align:right}
.delta-pos{color:var(--ok);font-weight:700;font-size:11px;min-width:38px;text-align:right}
.delta-neg{color:var(--red);font-weight:700;font-size:11px;min-width:38px;text-align:right}
.delta-zero{color:var(--text2);font-size:11px;min-width:38px;text-align:right}
.delta-null{color:var(--text2);font-weight:400;font-size:11px;min-width:38px;text-align:right}

/* CHART */
.chart-section{background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px}
.chart-title{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.chart-row{display:flex;align-items:center;gap:10px;margin-bottom:7px}
.chart-label{font-size:11px;font-weight:600;color:var(--navy);width:120px;flex-shrink:0;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chart-bar-wrap{flex:1;background:var(--border);border-radius:4px;height:20px;overflow:hidden;position:relative}
.chart-bar-fill{height:20px;border-radius:4px;transition:width .5s;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;min-width:2px}
.chart-bar-val{font-size:11px;font-weight:800;color:#fff;white-space:nowrap}
.chart-bar-pct{font-size:11px;font-weight:700;color:var(--ok);min-width:48px;text-align:right}

/* TARGET CARD */
.hari-card{border-top:3px solid var(--ok);background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 16px;position:relative;overflow:hidden}
.hari-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}

/* WILAYAH */
.wil-row{cursor:pointer}
.wil-row:hover td{background:#FDF5EE!important}
.wil-kode{font-size:11px;color:var(--text2);font-family:monospace}
.wil-nama{font-weight:700;color:var(--navy)}
.wil-link{color:var(--o);font-size:11px;font-weight:600}
.prog-bar-wrap{display:flex;align-items:center;gap:8px}
.prog-bar-bg{flex:1;background:var(--border);border-radius:3px;height:6px;overflow:hidden;min-width:60px}
.prog-bar-fill{height:6px;border-radius:3px;background:var(--grad)}
.prog-bar-app{height:6px;border-radius:3px;background:var(--ok)}

@media(max-width:900px){
  .pml-grid{grid-template-columns:1fr}
}
@media(max-width:700px){
  .tb-grid{grid-template-columns:1fr}.cards{grid-template-columns:repeat(2,1fr)}
  .pml-grid{grid-template-columns:1fr}.nav-sub{display:none}
  td,th{padding:7px 8px;font-size:12px}
}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div>
      <span class="nav-title">SELARAS</span>
      <span class="nav-sub">SE2026 · BPS Kota Kupang</span>
    </div>
  </div>
  <div class="nav-right">
    <div class="nav-ts" id="navTs">Memuat...</div>
    <a href="<?= BASE_URL ?>/ppl_dashboard.php" class="nav-btn"><i class="ti ti-report-analytics"></i> Rekap PPL</a>
    <a href="<?= BASE_URL ?>/dashboard_wilayah.php" class="nav-btn"><i class="ti ti-map-2"></i> Wilayah</a>
    <?php if ($isLoggedIn): ?>
      <span class="nav-user"><?= htmlspecialchars($namaUser) ?></span>
      <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>/admin/mapping.php" class="nav-btn"><i class="ti ti-users-group"></i> Mapping</a>
      <?php endif ?>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-btn">Keluar</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/login.php" class="nav-btn"><i class="ti ti-login-2"></i> Masuk</a>
    <?php endif ?>
  </div>
</nav>

<div class="tabnav">
  <div class="tablink active" onclick="switchTab('pencacah',this)"><i class="ti ti-users"></i> Pencacah</div>
  <div class="tablink" onclick="switchTab('pml',this)"><i class="ti ti-user-check"></i> PML</div>
  <div class="tablink" onclick="switchTab('wilayah',this)"><i class="ti ti-map-pin"></i> Wilayah</div>
</div>

<div class="ibar" id="ibar">
  <div class="iitem"><span>Memuat data...</span></div>
</div>

<div class="main">

  <!-- FILTER -->
  <div class="frow">
    <select id="selDate" onchange="loadData()"><option value="">Semua Tanggal</option></select>
    <div class="sbox">
      <i class="ti ti-search"></i>
      <input type="text" id="srch" placeholder="Cari nama atau email petugas..." oninput="fil()">
    </div>
    <span class="pcnt" id="pcnt">— petugas</span>
  </div>

  <!-- CARDS -->
  <div class="cards" id="cards"></div>

  <!-- TAB: PENCACAH -->
  <div id="tab-pencacah" class="tab-panel active">
    <div class="tb-grid" id="tbGrid" style="display:none">
      <div class="tb-card">
        <div class="tb-head top">
          <i class="ti ti-trophy" style="color:var(--ok);font-size:16px"></i>
          <span class="tb-head-title">Top 10 Petugas</span>
        </div>
        <div id="top10"></div>
      </div>
      <div class="tb-card">
        <div class="tb-head bot">
          <i class="ti ti-alert-triangle" style="color:var(--red);font-size:16px"></i>
          <span class="tb-head-title">Bottom 10 Petugas</span>
        </div>
        <div id="bot10"></div>
      </div>
    </div>
    <div class="tw">
      <div class="th2">
        <div class="thl"><i class="ti ti-table"></i> Detail per Petugas</div>
      </div>
      <div id="tbody"><div class="empty"><div class="spin"></div>Memuat data...</div></div>
    </div>
  </div>

  <!-- TAB: PML -->
  <div id="tab-pml" class="tab-panel">
    <div class="chart-section" id="pmlChart" style="display:none">
      <div class="chart-title" style="display:flex;justify-content:space-between;align-items:center">
        <span><i class="ti ti-chart-bar" style="color:var(--o);font-size:18px"></i> Approved per PML</span>
        <button onclick="downloadPML()" class="nav-btn" style="font-size:12px;padding:5px 12px;background:var(--ok-lt);color:var(--ok);border:1px solid #BBF7D0">
          <i class="ti ti-download"></i> Unduh Excel
        </button>
      </div>
      <div id="pmlChartBars"></div>
    </div>
    <div class="pml-grid" id="pmlGrid">
      <div class="empty" style="grid-column:1/-1"><div class="spin"></div>Memuat data...</div>
    </div>
  </div>

  <!-- TAB: WILAYAH -->
  <div id="tab-wilayah" class="tab-panel">
    <div class="tw">
      <div class="th2" style="flex-wrap:wrap;gap:8px">
        <div class="thl" style="flex-wrap:wrap;gap:4px;align-items:center">
          <i class="ti ti-map-pin"></i>
          <span id="wil-breadcrumb">Rekap per Kecamatan</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center;margin-left:auto;flex-wrap:wrap">
          <select id="wilDate" onchange="loadWilayah()" style="font-size:12px;padding:5px 10px">
            <option value="">Tanggal terbaru</option>
          </select>
          <button id="wil-back" onclick="wilBack()" class="nav-btn" style="display:none;font-size:12px;padding:5px 12px;background:var(--o-lt);color:var(--o);border:1px solid var(--o-mid)">
            ← Kembali
          </button>
          <button id="wil-home" onclick="wilHome()" class="nav-btn" style="display:none;font-size:12px;padding:5px 12px;background:var(--navy);color:#fff;border-color:var(--navy2)">
            ⌂ Kecamatan
          </button>
        </div>
      </div>
      <div id="wilTable"><div class="empty"><div class="spin"></div>Memuat data wilayah...</div></div>
    </div>
  </div>

</div><!-- /main -->

<script>
var ALL=[], ALL_PML=[], DATES=[], TARGET=50729;
var SC='selisih', SD=-1;
var NUMERIC=['total','open','draft','submitted','approved','rejected','selesai','progress','selisih'];
var currentTab = 'pencacah';

// State wilayah
var WIL_LEVEL = 'kecamatan';
var WIL_PARENT = null;
var WIL_STACK = []; // [{level, parent, label}]

var NAMA_KEC = {
  '5371010':'Alak','5371020':'Maulafa',
  '5371030':'Oebobo','5371031':'Kota Raja',
  '5371040':'Kelapa Lima','5371041':'Kota Lama'
};

// Fetch data dari PHP API
function loadData(isInitial) {
  var tgl = document.getElementById('selDate').value;
  var url = '../api/get_progress.php' + (tgl ? '?tanggal=' + tgl : '');
  fetch(url, { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(res) {
      if (!res.success) {
        document.getElementById('tbody').innerHTML = '<div class="empty">Error: ' + res.message + '</div>';
        return;
      }
      ALL     = res.rows || [];
      ALL_PML = res.pmlRows || [];
      TARGET  = res.target || 50729;
      // Build dropdown tanggal hanya saat pertama kali load
      if (isInitial) {
        DATES = res.dates || [];
        buildDateFilter();
      }
      renderInfobar(res.lastLog);
      fil();
    })
    .catch(function(e) {
      document.getElementById('tbody').innerHTML = '<div class="empty">Gagal memuat: ' + e.message + '</div>';
    });
}

function buildDateFilter() {
  var sel = document.getElementById('selDate');
  var cur = sel.value; // simpan pilihan sekarang
  sel.innerHTML = '<option value="">Semua Tanggal</option>';
  DATES.forEach(function(d) {
    var o = document.createElement('option');
    o.value = d; o.textContent = fmtTgl(d); sel.appendChild(o);
  });
  // Restore pilihan sebelumnya kalau masih ada, kalau tidak pakai terbaru
  if (cur && DATES.indexOf(cur) >= 0) {
    sel.value = cur;
  } else if (DATES.length > 0) {
    sel.value = DATES[0];
  }
}

function fmtTgl(s) {
  if (!s) return '-';
  var p = s.split('-');
  if (p.length !== 3) return s;
  var b = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  return parseInt(p[2]) + ' ' + b[parseInt(p[1])] + ' ' + p[0];
}

function renderInfobar(log) {
  var bar = document.getElementById('ibar');
  if (!log) { bar.innerHTML = '<span>Belum ada data.</span>'; return; }
  var h = '<div class="iitem"><i class="ti ti-clock"></i><span>Import terakhir: <strong>' + log.autoTimestamp + '</strong></span></div>';
  h += '<div class="dsep"></div>';
  h += '<div class="iitem"><i class="ti ti-calendar"></i><span>Laporan: <strong>' + fmtTgl(log.reportDate) + '</strong></span></div>';
  if (log.catatan) h += '<span class="bnote">' + log.catatan + '</span>';
  bar.innerHTML = h;
  document.getElementById('navTs').innerHTML = 'Diperbarui<br><strong>' + log.autoTimestamp + '</strong>';
}

function fil() {
  var dv = document.getElementById('selDate').value;
  var sw = document.getElementById('srch').value.toLowerCase();

  var f = ALL.filter(function(r) {
    if (dv && r.tanggal !== dv) return false;
    if (sw) { var h = (r.email + ' ' + r.nama).toLowerCase(); if (h.indexOf(sw) < 0) return false; }
    return true;
  });

  f.sort(function(a, b) {
    var va = a[SC], vb = b[SC];
    if (NUMERIC.indexOf(SC) >= 0) {
      va = (va === null || va === undefined) ? -Infinity : parseFloat(va);
      vb = (vb === null || vb === undefined) ? -Infinity : parseFloat(vb);
      return SD * (va - vb);
    }
    return SD * String(va).localeCompare(String(vb));
  });

  var fPML = ALL_PML.filter(function(r) {
    if (dv && r.tanggal !== dv) return false;
    if (sw && r.pml.toLowerCase().indexOf(sw) < 0) return false;
    return true;
  });
  fPML.sort(function(a,b){ return b.selesai - a.selesai; });

  document.getElementById('pcnt').textContent = f.length + ' petugas';
  renderCards(f);
  renderTopBot(f);
  renderTable(f);
  renderPMLChart(fPML);
  renderPMLGrid(fPML);
}

function hitungHariBerjalan(tglStr) {
  if (!tglStr) return null;
  // Parse manual agar tidak ada masalah timezone
  var awal = new Date(2026, 5, 15); // bulan 0-indexed, jadi 5 = Juni
  var p    = tglStr.split('-');
  var tgl  = new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2]));
  var diff = Math.round((tgl - awal) / (1000*60*60*24)) + 1;
  return diff > 0 ? diff : null;
}

function renderCards(rows) {
  var tot=0, sub=0, app=0, rej=0, open=0, sel=0;
  rows.forEach(function(r){ tot+=r.total; sub+=r.submitted; app+=r.approved; rej+=r.rejected; open+=r.open; sel+=r.selesai; });
  var pct    = tot > 0 ? (sel/tot*100).toFixed(1) : '0.0';
  var appPct = tot > 0 ? (app/tot*100).toFixed(1) : '0.0';
  var tgtPct = TARGET > 0 ? Math.min(sel/TARGET*100, 100).toFixed(1) : 0;
  var tgtColor = sel >= TARGET ? '#16A34A' : sel/TARGET >= 0.7 ? '#D97706' : '#E8560A';

  var dv   = document.getElementById('selDate').value;
  var hari = hitungHariBerjalan(dv);
  var tgtH = null;
  if (hari) {
    if (hari <= 45) {
      tgtH = (hari / 45 * 75).toFixed(1);
    } else {
      tgtH = (75 + (hari - 45) / 30 * 25).toFixed(1);
    }
  }
  var diff = tgtH ? (parseFloat(pct) - parseFloat(tgtH)).toFixed(1) : null;
  var sc   = diff === null ? '#9E9E9E' : parseFloat(diff) >= 0 ? '#16A34A' : '#DC2626';
  var sl   = diff === null ? '—' : parseFloat(diff) >= 0 ? '▲ di atas target' : '▼ di bawah target';

  var cardHarian = '<div class="card" style="border-top:3px solid '+sc+'">' +
    '<div class="clbl"><i class="ti ti-clock-check" style="font-size:13px;margin-right:4px"></i>Target Hari Ini</div>' +
    '<div class="cval" style="color:'+sc+'">'+(tgtH ? tgtH+'%' : '—')+'</div>' +
    '<div class="cnote">Hari ke-'+(hari||'?')+' — '+(hari<=45?'fase I (target 75%)':'fase II (target 100%)')+'</div>' +
    (diff !== null ? '<div style="margin-top:5px;font-size:11px;font-weight:700;color:'+sc+'">'+(parseFloat(diff)>=0?'+':'')+diff+'% '+sl+'</div>' : '') +
  '</div>';

  document.getElementById('cards').innerHTML =
    mkCard('ct','ti-briefcase','Total Usaha',tot,'Assignment') +
    mkCard('cs','ti-upload','Submitted',sub,'oleh Pencacah') +
    mkCard('ca','ti-circle-check','Approved',app,'oleh Pengawas') +
    mkCard('cr','ti-circle-x','Rejected',rej,'oleh Pengawas') +
    mkCard('co','ti-lock-open','Belum Diisi',open,'Status OPEN') +
    mkCard('cp','ti-chart-bar','Progress',pct+'%','dari total usaha') +
    mkCard('cpa','ti-rosette','Prog. Approved',appPct+'%','dari total usaha') +
    cardHarian +
    '<div class="card ctg">' +
      '<div class="clbl"><i class="ti ti-target" style="font-size:13px;margin-right:4px"></i>Target Selesai</div>' +
      '<div class="cval">'+sel+' <span style="font-size:13px;font-weight:400;color:#9E9E9E">/ '+tot+'</span></div>' +
      '<div class="target-bar-wrap"><div class="target-bar-bg"><div class="target-bar-fill" style="width:'+tgtPct+'%;background:'+tgtColor+'"></div></div></div>' +
    '</div>';
}

function mkCard(cls,ic,lbl,val,note){
  return '<div class="card '+cls+'"><div class="clbl"><i class="ti '+ic+'" style="font-size:13px;margin-right:4px"></i>'+lbl+'</div><div class="cval">'+val+'</div><div class="cnote">'+note+'</div></div>';
}

function renderTopBot(rows) {
  var isMobile = window.innerWidth < 640;
  if (!rows.length || isMobile) { document.getElementById('tbGrid').style.display='none'; return; }
  document.getElementById('tbGrid').style.display = 'grid';
  var sorted = [].concat(rows).sort(function(a,b){ return b.selesai-a.selesai; });
  var maxVal = sorted[0] ? sorted[0].selesai : 1;
  var top = sorted.slice(0,10);
  var bot = [].concat(rows).sort(function(a,b){ return a.selesai-b.selesai; }).slice(0,10);
  document.getElementById('top10').innerHTML = top.map(function(r,i){
    var p = maxVal>0?Math.round(r.selesai/maxVal*100):0;
    return '<div class="tb-row top"><div class="tb-rank">'+(i+1)+'</div><div class="tb-info"><div class="tb-name">'+(r.nama||r.email)+'</div>'+(r.nama?'<div class="tb-email">'+r.email+'</div>':'')+'<div class="tb-bar-bg"><div class="tb-bar-fill top" style="width:'+p+'%"></div></div></div><div class="tb-val">'+r.selesai+'</div></div>';
  }).join('');
  document.getElementById('bot10').innerHTML = bot.map(function(r,i){
    var mx = bot[bot.length-1] ? bot[bot.length-1].selesai : 1;
    var p  = mx>0?Math.round(r.selesai/mx*100):0;
    return '<div class="tb-row bot"><div class="tb-rank">'+(i+1)+'</div><div class="tb-info"><div class="tb-name">'+(r.nama||r.email)+'</div>'+(r.nama?'<div class="tb-email">'+r.email+'</div>':'')+'<div class="tb-bar-bg"><div class="tb-bar-fill bot" style="width:'+p+'%"></div></div></div><div class="tb-val">'+r.selesai+'</div></div>';
  }).join('');
}

function renderTable(rows) {
  if (!rows.length) { document.getElementById('tbody').innerHTML='<div class="empty">Tidak ada data.</div>'; return; }

  var isMobile = window.innerWidth < 640;

  if (isMobile) {
    // ── Mobile: card per petugas ─────────────────────────────────────
    var html = rows.map(function(r) {
      var delta = r.selisih;
      var dc, dv;
      if (delta===null)  { dc='#9E9E9E'; dv='—'; }
      else if (delta>0)  { dc='var(--ok)'; dv='+'+delta; }
      else if (delta===0){ dc='#9E9E9E'; dv='0'; }
      else               { dc='var(--red)'; dv=String(delta); }

      var prog = r.total>0?(r.selesai/r.total*100).toFixed(1):0;
      var progC = prog>=50?'var(--ok)':prog>=25?'var(--amb)':'var(--o)';

      return '<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin:6px 0">'+
        // Nama + delta
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">'+
          '<div>'+
            '<div style="font-weight:700;font-size:13px;color:var(--navy)">'+(r.nama||r.email)+'</div>'+
            (r.nama?'<div style="font-size:10.5px;color:var(--text2);margin-top:1px">'+r.email+'</div>':'')+
            (r.catatan?'<div style="font-size:10px;color:var(--amb);font-weight:600;margin-top:2px">'+r.catatan+'</div>':'')+
          '</div>'+
          '<div style="text-align:right">'+
            '<div style="font-size:20px;font-weight:800;color:'+dc+'">'+dv+'</div>'+
            '<div style="font-size:9px;color:var(--text2)">hari ini</div>'+
          '</div>'+
        '</div>'+
        // Progress bar
        '<div style="margin-bottom:10px">'+
          '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px">'+
            '<span style="color:var(--text2)">Selesai</span>'+
            '<span style="font-weight:700;color:'+progC+'">'+r.selesai+' / '+r.total+' ('+prog+'%)</span>'+
          '</div>'+
          '<div style="background:var(--border);border-radius:3px;height:6px;overflow:hidden">'+
            '<div style="height:6px;border-radius:3px;background:'+progC+';width:'+prog+'%"></div>'+
          '</div>'+
        '</div>'+
        // Chip stats
        '<div style="display:flex;gap:6px;flex-wrap:wrap">'+
          '<div style="background:var(--amb-lt);color:var(--amb);border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700">Sub '+r.submitted+'</div>'+
          '<div style="background:var(--ok-lt);color:var(--ok);border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700">App '+r.approved+'</div>'+
          '<div style="background:var(--red-lt);color:var(--red);border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700">Rej '+r.rejected+'</div>'+
          '<div style="background:var(--sl-lt);color:var(--slate);border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700">Open '+r.open+'</div>'+
        '</div>'+
      '</div>';
    }).join('');

    document.getElementById('tbody').innerHTML = '<div style="padding:8px 0">'+html+'</div>';

  } else {
    // ── Desktop: tabel ────────────────────────────────────────────────
    var cols=[
      {k:'nama',l:'Nama / Email',w:'22%'},{k:'total',l:'Total',w:'7%'},
      {k:'submitted',l:'Submitted',w:'9%'},{k:'approved',l:'Approved',w:'9%'},
      {k:'rejected',l:'Rejected',w:'8%'},{k:'open',l:'Open',w:'7%'},
      {k:'selesai',l:'Selesai',w:'9%'},{k:'selisih',l:'Progres Hari Ini',w:'14%'}
    ];
    var cg=cols.map(function(c){return '<col style="width:'+c.w+'">';}).join('');
    var ths=cols.map(function(c){
      var sc=SC===c.k?(SD===1?'asc':'desc'):'';
      return '<th class="'+sc+'" onclick="srt(\''+c.k+'\')">'+c.l+'</th>';
    }).join('');
    var trs=rows.map(function(r){
      var nc=r.nama?'<div class="nm">'+r.nama+'</div><div class="em">'+r.email+'</div>':'<div class="em">'+r.email+'</div>';
      var delta=r.selisih, tc;
      if(delta===null) tc='<span style="color:#9E9E9E;font-size:12px">—</span>';
      else if(delta>0)  tc='<span style="font-size:15px;font-weight:800;color:var(--ok)">'+delta+'</span>';
      else if(delta===0)tc='<span style="font-size:13px;font-weight:700;color:#9E9E9E">0</span>';
      else              tc='<span style="font-size:15px;font-weight:800;color:var(--red)">'+delta+'</span>';
      var cc=r.catatan?'<br><span style="font-size:10px;color:#D97706;font-weight:600">'+r.catatan+'</span>':'';
      return '<tr><td>'+nc+'</td><td style="font-weight:700;color:var(--navy)">'+r.total+'</td><td><span class="chip csub">'+r.submitted+'</span></td><td><span class="chip capp">'+r.approved+'</span></td><td><span class="chip crej">'+r.rejected+'</span></td><td><span class="chip copn">'+r.open+'</span></td><td style="font-weight:700;font-size:15px;color:var(--navy)">'+r.selesai+'</td><td>'+tc+cc+'</td></tr>';
    }).join('');
    document.getElementById('tbody').innerHTML='<table><colgroup>'+cg+'</colgroup><thead><tr>'+ths+'</tr></thead><tbody>'+trs+'</tbody></table>';
  }
}

function renderPMLChart(pmls) {
  var el=document.getElementById('pmlChart'), bars=document.getElementById('pmlChartBars');
  if(!pmls.length){el.style.display='none';return;}
  el.style.display='block';
  var sorted=pmls.slice().sort(function(a,b){return b.approved-a.approved;}).slice(0,16);
  var mx=sorted[0]?sorted[0].approved:1;
  bars.innerHTML=sorted.map(function(p){
    var pct=mx>0?Math.round(p.approved/mx*100):0;
    var ap=p.total>0?(p.approved/p.total*100).toFixed(1):'0.0';
    var bc=pct>=80?'#16A34A':pct>=40?'#1A7A5E':'#E8560A';
    return '<div class="chart-row"><div class="chart-label" title="'+p.pml+'">'+p.pml+'</div><div class="chart-bar-wrap"><div class="chart-bar-fill" style="width:'+pct+'%;background:'+bc+'">'+(pct>15?'<span class="chart-bar-val">'+p.approved+'</span>':'')+'</div>'+(pct<=15?'<span style="position:absolute;left:'+(pct+1)+'%;top:50%;transform:translateY(-50%);font-size:11px;font-weight:800;color:var(--navy)">'+p.approved+'</span>':'')+'</div><div class="chart-bar-pct">'+ap+'%</div></div>';
  }).join('');
}

function renderPMLGrid(pmls) {
  var el=document.getElementById('pmlGrid');
  if(!pmls.length){el.innerHTML='<div class="empty" style="grid-column:1/-1">Tidak ada data PML.</div>';return;}
  el.innerHTML=pmls.map(function(p){
    var pct=p.progress,bc=pct>=80?'#16A34A':pct>=40?'#D97706':pct>=15?'#E8560A':'#6B7280';
    var dh=p.selisih===null?'<span class="delta-null">—</span>':p.selisih>0?'<span class="delta-pos">'+p.selisih+'</span>':p.selisih===0?'<span class="delta-zero">0</span>':'<span class="delta-neg">'+p.selisih+'</span>';
    var cardId = 'pml-'+Math.random().toString(36).substr(2,6);
    var isMobilePML = window.innerWidth < 640;
    var pcRows=(p.pencacah||[]).slice().sort(function(a,b){return b.dikerjakan-a.dikerjakan;}).map(function(pc){
      var tot  = pc.total||0;
      var dik  = pc.dikerjakan||0;
      var dikPct = tot>0?(dik/tot*100).toFixed(1):'0.0';
      var dikC = parseFloat(dikPct)>=50?'var(--o-h)':parseFloat(dikPct)>=20?'var(--o)':'var(--slate)';
      var sdik = pc.selisihDikerjakan;
      var sdikH = sdik===null?'<span class="delta-null">—</span>':sdik>0?'<span class="delta-pos">'+sdik+'</span>':sdik===0?'<span class="delta-zero">0</span>':'<span class="delta-neg">'+sdik+'</span>';
      var per  = pc.diperiksa||0;
      var perPct = tot>0?(per/tot*100).toFixed(1):'0.0';
      var perC = parseFloat(perPct)>=50?'var(--ok)':parseFloat(perPct)>=20?'var(--amb)':'var(--slate)';
      var sper = pc.selisihDiperiksa;
      var sperH = sper===null?'<span class="delta-null">—</span>':sper>0?'<span class="delta-pos">'+sper+'</span>':sper===0?'<span class="delta-zero">0</span>':'<span class="delta-neg">'+sper+'</span>';
      var app  = pc.approved||0;
      var appPct = tot>0?(app/tot*100).toFixed(1):'0.0';
      var appC = parseFloat(appPct)>=50?'var(--ok)':parseFloat(appPct)>=20?'var(--amb)':'var(--slate)';
      var sapp = pc.selisihApproved;
      var sappH = sapp===null?'<span class="delta-null">—</span>':sapp>0?'<span class="delta-pos">'+sapp+'</span>':sapp===0?'<span class="delta-zero">0</span>':'<span class="delta-neg">'+sapp+'</span>';

      if (isMobilePML) {
        // Mobile: nama di atas, stats ringkas di bawah
        return '<div class="pml-pc-row" style="flex-direction:column;align-items:flex-start;gap:6px;padding:8px 0">'+
          '<div style="display:flex;justify-content:space-between;width:100%;align-items:center">'+
            '<div class="pml-pc-name" style="font-size:12px;font-weight:700">'+pc.nama+'</div>'+
            '<div style="display:flex;gap:6px;align-items:center">'+
              sdikH+
            '</div>'+
          '</div>'+
          '<div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px">'+
            '<span style="color:'+dikC+';font-weight:700">Dik '+dik+' ('+dikPct+'%)</span>'+
            '<span style="color:'+perC+';font-weight:700">Per '+per+' ('+perPct+'%)</span>'+
            '<span style="color:'+appC+';font-weight:700">App '+app+' ('+appPct+'%)</span>'+
          '</div>'+
        '</div>';
      }

      return '<div class="pml-pc-row">'+
        '<div class="pml-pc-name" title="'+pc.nama+'">'+pc.nama+'</div>'+
        '<span class="pc-detail" style="display:contents">'+
          '<div class="pml-pc-num">'+dik+'</div>'+
          '<div class="pml-pc-pct" style="color:'+dikC+'">'+dikPct+'%</div>'+
          sdikH+
          '<div class="pml-pc-num">'+per+'</div>'+
          '<div class="pml-pc-pct" style="color:'+perC+'">'+perPct+'%</div>'+
          sperH+
          '<div class="pml-pc-num">'+app+'</div>'+
          '<div class="pml-pc-pct" style="color:'+appC+'">'+appPct+'%</div>'+
          sappH+
        '</span>'+
      '</div>';
    }).join('');

    return '<div class="pml-card" id="'+cardId+'">'+
      '<div class="pml-card-head">'+
        '<div class="pml-card-name"><i class="ti ti-user-check" style="margin-right:6px;font-size:14px"></i>'+p.pml+'</div>'+
        '<div class="pml-card-count">'+((p.pencacah||[]).length)+' pencacah</div>'+
      '</div>'+
      '<div class="pml-card-body">'+
        '<div class="pml-stats">'+
          '<div class="pml-stat s-sel">'+
            '<div class="pml-stat-val">'+p.selesai+'</div>'+
            '<div style="font-size:9px;color:var(--text2);margin-top:1px">/ '+p.total+'</div>'+
            '<div class="pml-stat-lbl">Diperiksa</div>'+
          '</div>'+
          '<div class="pml-stat s-sub">'+
            '<div class="pml-stat-val">'+p.submitted+'</div>'+
            '<div class="pml-stat-lbl">Blm Diperiksa</div>'+
          '</div>'+
          '<div class="pml-stat s-app"><div class="pml-stat-val">'+p.approved+'</div><div class="pml-stat-lbl">Approved</div></div>'+
          '<div class="pml-stat s-rej"><div class="pml-stat-val">'+p.rejected+'</div><div class="pml-stat-lbl">Rejected</div></div>'+
          '<div class="pml-stat s-dlt"><div class="pml-stat-val">'+dh+'</div><div class="pml-stat-lbl">+Hari ini</div></div>'+
        '</div>'+
        '<div class="pml-prog-wrap">'+
          '<div class="pml-prog-bg"><div class="pml-prog-fill" style="width:'+Math.min(pct,100)+'%;background:'+bc+'"></div></div>'+
          '<div class="pml-prog-pct" style="color:'+bc+'">'+pct+'%</div>'+
        '</div>'+
        '<div class="pml-pencacah">'+
          '<div class="pml-pencacah-title">'+
            '<span>Detail Pencacah</span>'+
            (!isMobilePML ?
            '<span style="display:flex;align-items:center;gap:8px">'+
              '<span class="pc-detail-hdr" style="display:flex;gap:3px;font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:var(--text2)">'+
                '<span style="min-width:24px;text-align:right" title="Dikerjakan PPL">Dik</span>'+
                '<span style="min-width:36px;text-align:right" title="% Dikerjakan">Dik%</span>'+
                '<span style="min-width:28px;text-align:right" title="+Dikerjakan">+Dik</span>'+
                '<span style="min-width:24px;text-align:right" title="Diperiksa PML">Per</span>'+
                '<span style="min-width:36px;text-align:right" title="% Diperiksa">Per%</span>'+
                '<span style="min-width:28px;text-align:right" title="+Diperiksa">+Per</span>'+
                '<span style="min-width:24px;text-align:right" title="Approved">App</span>'+
                '<span style="min-width:36px;text-align:right" title="% Approved">App%</span>'+
                '<span style="min-width:28px;text-align:right" title="+Approved">+App</span>'+
              '</span>'+
              '<button class="pml-toggle-btn" onclick="togglePcDetail(\''+cardId+'\',this)" title="Sembunyikan/tampilkan kolom detail">⊟</button>'+
            '</span>' : '')+
          '</div>'+
          pcRows+
        '</div>'+
      '</div>'+
    '</div>';
  }).join('');
}

function togglePcDetail(cardId, btn) {
  var card = document.getElementById(cardId);
  if (!card) return;
  // Toggle semua elemen .pc-detail dan .pc-detail-hdr dalam card ini
  var details = card.querySelectorAll('.pc-detail');
  var hdr     = card.querySelector('.pc-detail-hdr');
  var hidden  = btn.dataset.hidden === '1';
  details.forEach(function(el) {
    // pc-detail pakai display:contents — hide dengan class
    el.style.display = hidden ? 'contents' : 'none';
  });
  if (hdr) hdr.style.display = hidden ? 'flex' : 'none';
  btn.dataset.hidden = hidden ? '0' : '1';
  btn.textContent    = hidden ? '⊟' : '⊞';
  btn.title          = hidden ? 'Sembunyikan kolom detail' : 'Tampilkan kolom detail';
}

function srt(col){ if(SC===col) SD*=-1; else{SC=col;SD=-1;} fil(); }

// ── DOWNLOAD PML ──────────────────────────────────────────────────────────
function downloadPML() {
  var dv = document.getElementById('selDate').value;
  window.location.href = '../api/download_xlsx.php?type=pml' + (dv ? '&tanggal=' + dv : '');
}

// ── WILAYAH ──────────────────────────────────────────────────────────────
var WIL_LEVEL = 'kecamatan';
var WIL_PARENT = null;
var WIL_STACK = [];
var NAMA_KEC = {
  '5371010':'Alak','5371020':'Maulafa',
  '5371030':'Oebobo','5371031':'Kota Raja',
  '5371040':'Kelapa Lima','5371041':'Kota Lama'
};

function loadWilayah(level, parent) {
  if (level !== undefined) WIL_LEVEL = level;
  if (parent !== undefined) WIL_PARENT = parent;
  var tgl = document.getElementById('wilDate').value;
  var url = '../api/get_wilayah.php?level=' + WIL_LEVEL;
  if (tgl)        url += '&tanggal=' + tgl;
  if (WIL_PARENT) url += '&parent=' + WIL_PARENT;
  document.getElementById('wilTable').innerHTML =
    '<div class="empty"><div class="spin"></div>Memuat...</div>';
  var crumb = WIL_LEVEL === 'kecamatan' ? 'Rekap per Kecamatan'
            : WIL_LEVEL === 'desa'      ? 'Desa di '+(NAMA_KEC[WIL_PARENT]||WIL_PARENT)
            : WIL_LEVEL === 'sls'       ? 'SLS di '+WIL_PARENT
            : 'Sub-SLS di '+WIL_PARENT;
  document.getElementById('wil-breadcrumb').textContent = crumb;
  document.getElementById('wil-back').style.display = WIL_STACK.length > 0 ? '' : 'none';
  document.getElementById('wil-home').style.display = WIL_LEVEL !== 'kecamatan' ? '' : 'none';
  fetch(url)
    .then(function(r){ return r.json(); })
    .then(function(res) {
      if (!res.success) {
        document.getElementById('wilTable').innerHTML =
          '<div class="empty">Error: '+(res.message||'Gagal')+'</div>'; return;
      }
      // Isi dropdown tanggal wilayah (independen dari selDate)
      if (res.dates && res.dates.length) {
        var sel = document.getElementById('wilDate');
        var cur = res.tanggal || res.dates[0] || '';
        sel.innerHTML = '<option value="">Tanggal terbaru</option>';
        res.dates.forEach(function(d){
          var o = document.createElement('option');
          o.value = d; o.textContent = fmtTgl(d);
          if (d === cur) o.selected = true;
          sel.appendChild(o);
        });
      }
      // Tampilkan warning kalau ada
      if (res.warning) {
        document.getElementById('wilTable').innerHTML =
          '<div class="empty" style="color:var(--amb)">⚠️ '+res.warning+'</div>';
        return;
      }
      renderWilayahTable(res.rows, res.summary, res.tanggal);
    })
    .catch(function(e){
      document.getElementById('wilTable').innerHTML =
        '<div class="empty">Gagal: '+e.message+'</div>';
    });
}

function drillDown(kode) {
  var next = WIL_LEVEL==='kecamatan'?'desa'
           : WIL_LEVEL==='desa'     ?'sls'
           : WIL_LEVEL==='sls'      ?'subsls'
           : null;
  if (!next) return;
  WIL_STACK.push({level:WIL_LEVEL, parent:WIL_PARENT});
  loadWilayah(next, kode);
}

function wilBack() {
  if (!WIL_STACK.length) return;
  var prev = WIL_STACK.pop();
  loadWilayah(prev.level, prev.parent);
}

function wilHome() {
  WIL_STACK = [];
  loadWilayah('kecamatan', null);
}

function renderWilayahTable(rows, summary, tanggal) {
  if (!rows || !rows.length) {
    document.getElementById('wilTable').innerHTML =
      '<div class="empty">Belum ada data wilayah.<br><small>Jalankan bookmarklet SIHARAU terlebih dahulu.</small></div>';
    return;
  }
  var canDrill = WIL_LEVEL !== 'subsls';
  var lblLevel = WIL_LEVEL==='kecamatan'?'Kecamatan'
               : WIL_LEVEL==='desa'    ?'Desa / Kelurahan'
               : WIL_LEVEL==='sls'     ?'SLS'
               : 'Sub-SLS';
  var isMobile = window.innerWidth < 640;

  var sumProg = summary.total>0?(summary.selesai/summary.total*100).toFixed(1):'0.0';
  var sumApp  = summary.total>0?(summary.approved/summary.total*100).toFixed(1):'0.0';

  var infoBar = '<div style="padding:8px 16px;font-size:11px;color:var(--text2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">'+
    '<span>Data per '+fmtTgl(tanggal)+'</span>'+
    '<span style="font-weight:700;color:var(--navy)">'+rows.length+' '+lblLevel+' | Progress: <span style="color:var(--o)">'+sumProg+'%</span> | Approved: <span style="color:var(--ok)">'+sumApp+'%</span></span>'+
  '</div>';

  if (isMobile) {
    // ── Mobile: card view ─────────────────────────────────────────────
    var cards = rows.map(function(r,i){
      var progC = r.progress_pct>=50?'var(--ok)':r.progress_pct>=25?'var(--amb)':'var(--o)';
      var appC  = r.approved_pct>=50?'var(--ok)':r.approved_pct>=25?'var(--amb)':'var(--slate)';
      var drillBtn = canDrill
        ? '<button onclick="drillDown(\''+r.kode+'\')" style="width:100%;margin-top:10px;padding:8px;background:var(--o-lt);color:var(--o);border:1px solid var(--o-mid);border-radius:6px;font-size:12px;font-weight:700;cursor:pointer">Detail '+lblLevel+' →</button>'
        : '';
      return '<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin:8px 12px;box-shadow:0 1px 4px rgba(0,0,0,0.06)"'+(canDrill?' onclick="drillDown(\''+r.kode+'\')" style="cursor:pointer"':'')+'>'+
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">'+
          '<div>'+
            '<div style="font-weight:700;font-size:13px;color:var(--navy)">'+r.nama+'</div>'+
            '<div style="font-size:10px;color:var(--text2);font-family:monospace;margin-top:2px">'+r.kode+'</div>'+
          '</div>'+
          '<div style="font-size:11px;color:var(--text2)">'+r.jml_pencacah+' PPL'+
            ((WIL_LEVEL==='sls'||WIL_LEVEL==='subsls')&&r.ppl&&r.ppl.length?' · '+r.ppl.join(', '):'')+
          '</div>'+
        '</div>'+
        // Progress bar
        '<div style="margin-bottom:8px">'+
          '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px">'+
            '<span style="color:var(--text2)">Progress</span>'+
            '<span style="font-weight:700;color:'+progC+'">'+r.selesai.toLocaleString('id')+' / '+r.total.toLocaleString('id')+' ('+r.progress_pct+'%)</span>'+
          '</div>'+
          '<div style="background:var(--border);border-radius:4px;height:8px;overflow:hidden">'+
            '<div style="height:8px;border-radius:4px;background:'+progC+';width:'+r.progress_pct+'%"></div>'+
          '</div>'+
        '</div>'+
        // Approved bar
        '<div>'+
          '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px">'+
            '<span style="color:var(--text2)">Approved</span>'+
            '<span style="font-weight:700;color:'+appC+'">'+r.approved.toLocaleString('id')+' ('+r.approved_pct+'%)</span>'+
          '</div>'+
          '<div style="background:var(--border);border-radius:4px;height:6px;overflow:hidden">'+
            '<div style="height:6px;border-radius:4px;background:'+appC+';width:'+r.approved_pct+'%"></div>'+
          '</div>'+
        '</div>'+
        drillBtn+
      '</div>';
    }).join('');

    document.getElementById('wilTable').innerHTML = infoBar + '<div style="padding-bottom:8px">'+cards+'</div>';

  } else {
    // ── Desktop: tabel ────────────────────────────────────────────────
    // Di level SLS/SubSLS: teks saja (tidak ada bar), kolom lebih ringkas
    var isSlsLevel = WIL_LEVEL==='sls'||WIL_LEVEL==='subsls';

    var thead = '<tr>'+
      '<th style="width:4%">#</th>'+
      '<th style="width:8%">Kode</th>'+
      '<th>'+lblLevel+'</th>'+
      '<th style="width:7%">Total</th>'+
      '<th style="width:7%">Selesai</th>'+
      '<th style="width:'+(isSlsLevel?'8%':'18%')+'">Progress</th>'+
      '<th style="width:7%">Approved</th>'+
      '<th style="width:'+(isSlsLevel?'8%':'18%')+'">App%</th>'+
      '<th style="width:5%">PPL</th>'+
      (isSlsLevel?'<th style="width:16%">Nama PPL</th>':'')+
      (canDrill?'<th style="width:7%"></th>':'')+
    '</tr>';

    var tbody = rows.map(function(r,i){
      var progC = r.progress_pct>=50?'var(--ok)':r.progress_pct>=25?'var(--amb)':'var(--o)';
      var appC  = r.approved_pct>=50?'var(--ok)':r.approved_pct>=25?'var(--amb)':'var(--slate)';
      var drill = canDrill
        ? '<td><button class="nav-btn" style="font-size:11px;padding:3px 8px;background:var(--o-lt);color:var(--o);border:1px solid var(--o-mid)" '+
            'onclick="event.stopPropagation();drillDown(\''+r.kode+'\')">Detail →</button></td>'
        : '';

      // Kolom progress — bar di kec/desa, teks saja di SLS/SubSLS
      var progCell = isSlsLevel
        ? '<td style="font-weight:700;color:'+progC+';text-align:right">'+r.progress_pct+'%</td>'
        : '<td><div class="prog-bar-wrap">'+
            '<div class="prog-bar-bg"><div class="prog-bar-fill" style="width:'+r.progress_pct+'%;background:'+progC+'"></div></div>'+
            '<span style="font-size:11px;font-weight:700;color:'+progC+';min-width:38px;text-align:right">'+r.progress_pct+'%</span>'+
          '</div></td>';
      var appCell = isSlsLevel
        ? '<td style="font-weight:700;color:'+appC+';text-align:right">'+r.approved_pct+'%</td>'
        : '<td><div class="prog-bar-wrap">'+
            '<div class="prog-bar-bg"><div class="prog-bar-fill" style="width:'+r.approved_pct+'%;background:'+appC+'"></div></div>'+
            '<span style="font-size:11px;font-weight:700;color:'+appC+';min-width:38px;text-align:right">'+r.approved_pct+'%</span>'+
          '</div></td>';

      return '<tr class="wil-row" onclick="'+(canDrill?'drillDown(\''+r.kode+'\')':'')+'" style="'+(canDrill?'cursor:pointer':'')+'">'+
        '<td style="color:var(--text2);font-size:12px">'+(i+1)+'</td>'+
        '<td class="wil-kode" style="font-size:10px">'+r.kode+'</td>'+
        '<td><div class="wil-nama">'+r.nama+'</div></td>'+
        '<td style="font-weight:700">'+r.total.toLocaleString('id')+'</td>'+
        '<td style="font-weight:700;color:var(--navy)">'+r.selesai.toLocaleString('id')+'</td>'+
        progCell+
        '<td style="font-weight:700;color:var(--ok)">'+r.approved.toLocaleString('id')+'</td>'+
        appCell+
        '<td style="font-size:12px;color:var(--text2)">'+r.jml_pencacah+'</td>'+
        (WIL_LEVEL==='sls'||WIL_LEVEL==='subsls'?'<td style="font-size:11px;color:var(--navy);max-width:120px">'+(r.ppl&&r.ppl.length?r.ppl.join(', '):'—')+'</td>':'')+
        drill+
      '</tr>';
    }).join('');

    var tfoot = '<tr style="background:var(--sl-lt);font-weight:700">'+
      '<td colspan="3" style="font-size:12px;color:var(--navy)">TOTAL ('+rows.length+' wilayah)</td>'+
      '<td>'+summary.total.toLocaleString('id')+'</td>'+
      '<td>'+summary.selesai.toLocaleString('id')+'</td>'+
      '<td><span style="font-weight:800;color:var(--o)">'+sumProg+'%</span></td>'+
      '<td>'+summary.approved.toLocaleString('id')+'</td>'+
      '<td><span style="font-weight:800;color:var(--ok)">'+sumApp+'%</span></td>'+
      '<td colspan="'+(canDrill?(WIL_LEVEL==='sls'||WIL_LEVEL==='subsls'?3:2):(WIL_LEVEL==='sls'||WIL_LEVEL==='subsls'?2:1))+'"></td>'+
    '</tr>';

    document.getElementById('wilTable').innerHTML = infoBar+
      '<div style="overflow-x:auto"><table>'+
      '<thead>'+thead+'</thead>'+
      '<tbody>'+tbody+'</tbody>'+
      '<tfoot>'+tfoot+'</tfoot>'+
      '</table></div>';
  }
}

function switchTab(tab, el) {
  currentTab = tab;
  document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.tablink').forEach(function(t){ t.classList.remove('active'); });
  document.getElementById('tab-'+tab).classList.add('active');
  el.classList.add('active');
  // Load wilayah saat pertama kali tab dibuka
  if (tab === 'wilayah' && document.getElementById('wilTable').querySelector('.spin')) {
    WIL_LEVEL = 'kecamatan'; WIL_PARENT = null; WIL_STACK = [];
    loadWilayah();
  }
}

function fil() {
  var dv = document.getElementById('selDate').value;
  var sw = document.getElementById('srch').value.toLowerCase();

  var f = ALL.filter(function(r) {
    if (dv && r.tanggal !== dv) return false;
    if (sw) { var h=(r.email+' '+r.nama).toLowerCase(); if(h.indexOf(sw)<0) return false; }
    return true;
  });
  f.sort(function(a,b){
    var va=a[SC],vb=b[SC];
    if(NUMERIC.indexOf(SC)>=0){
      va=(va===null||va===undefined)?-Infinity:parseFloat(va);
      vb=(vb===null||vb===undefined)?-Infinity:parseFloat(vb);
      return SD*(va-vb);
    }
    return SD*String(va).localeCompare(String(vb));
  });

  var fPML = ALL_PML.filter(function(r) {
    if (dv && r.tanggal !== dv) return false;
    if (sw && r.pml.toLowerCase().indexOf(sw)<0) return false;
    return true;
  });
  fPML.sort(function(a,b){ return b.selesai-a.selesai; });

  document.getElementById('pcnt').textContent = f.length+' petugas';
  renderCards(f);
  renderTopBot(f);
  renderTable(f);
  renderPMLChart(fPML);
  renderPMLGrid(fPML);
}

// Saat ganti tanggal, reload data tanpa rebuild dropdown
document.getElementById('selDate').addEventListener('change', function(){
  loadData(false);
});

// Initial load — rebuild dropdown
loadData(true);
</script>
</body>
</html>