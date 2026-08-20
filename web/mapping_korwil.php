<?php
// ============================================================
// mapping_korwil.php — Kelola mapping Korwil (Koordinator Wilayah) -> PML.
// Ini struktur internal BPS Kota Payakumbuh sendiri, gak ada sumbernya dari
// FASIH/SIHARAU, jadi diisi manual di sini. Khusus admin, taruh di root
// spt import_sls.php/cek_sls.php/hapus_data.php.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_import_migrate.php';
require_once __DIR__ . '/lib/sls_aggregate.php';

requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Akses ditolak. Halaman ini khusus admin.');
}

$db = getDB();
migrateSlsImportTables($db);

$msg = ''; $err = '';

// ── Simpan perubahan ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan') {
    $korwilList = $_POST['korwil'] ?? []; // [pml_nama => korwil_nama]
    $now = date('Y-m-d H:i:s');

    $upsert = $db->prepare("
        INSERT INTO mapping_korwil (pml_nama, korwil_nama, updated_at)
        VALUES (:pml, :korwil, :updated_at)
        ON CONFLICT(pml_nama) DO UPDATE SET korwil_nama = excluded.korwil_nama, updated_at = excluded.updated_at
    ");
    $hapus = $db->prepare("DELETE FROM mapping_korwil WHERE pml_nama = :pml");

    $n = 0;
    foreach ($korwilList as $pmlNama => $korwilNama) {
        $pmlNama = trim($pmlNama);
        $korwilNama = trim($korwilNama);
        if ($pmlNama === '') continue;
        if ($korwilNama === '') {
            $hapus->execute([':pml' => $pmlNama]); // kosongin = hapus mapping-nya
        } else {
            $upsert->execute([':pml' => $pmlNama, ':korwil' => $korwilNama, ':updated_at' => $now]);
            $n++;
        }
    }
    $msg = "✅ Mapping Korwil tersimpan ($n PML diisi Korwil-nya).";
}

// ── Daftar semua PML yang dikenal sistem (dari mapping_nama, sumber PPL) ───
$daftarPml = $db->query("
    SELECT DISTINCT pml FROM mapping_nama WHERE TRIM(pml) != '' ORDER BY pml
")->fetchAll(PDO::FETCH_COLUMN);

$korwilMap = slsFetchKorwilMap($db);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mapping Korwil — SELARAS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--navy:#1A2744;--ok:#16A34A;--ok-lt:#DCFCE7;--red:#DC2626;--red-lt:#FEE2E2;
--text:#1A1A2E;--text2:#6B7280;--border:#E4E4E4;--bg:#F5F6FA;--grad:linear-gradient(135deg,#E8560A,#F5A623)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o)}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;text-decoration:none}
.main{max-width:640px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:20px}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:18px}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;font-weight:500}
.alert-ok{background:var(--ok-lt);color:var(--ok);border:1px solid #BBF7D0}
.alert-err{background:var(--red-lt);color:var(--red);border:1px solid #FECACA}
.row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #F0F0F0}
.row:last-child{border-bottom:none}
.row-pml{flex:1;font-weight:600;color:var(--navy);font-size:13px}
.row-input{flex:1}
.row-input input{width:100%;padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.row-input input:focus{border-color:var(--o)}
.btn{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none;margin-top:16px;background:var(--grad);color:#fff}
.no-data{text-align:center;padding:24px;color:var(--text2)}
.hint{font-size:11.5px;color:var(--text2);margin-top:4px}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div><span class="nav-title">SELARAS Admin</span><span class="nav-sub">Mapping Korwil</span></div>
  </div>
  <a href="<?= BASE_URL ?>/ppl_dashboard.php" class="nav-btn">← Rekap PPL</a>
</nav>

<div class="main">
  <div class="page-title">Mapping Korwil → PML</div>
  <div class="page-sub">Tentukan Korwil (Koordinator Wilayah) buat tiap PML. Kosongkan isian buat hapus mapping-nya. PML yang gak diisi Korwil tetap tampil normal di dashboard, cuma labelnya gak digabung.</div>

  <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif ?>
  <?php if ($err): ?><div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif ?>

  <div class="panel">
    <?php if (!$daftarPml): ?>
      <div class="no-data">Belum ada data PML terdeteksi (cek tabel mapping_nama sudah ada isinya belum).</div>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="action" value="simpan">
      <?php foreach ($daftarPml as $pml): ?>
        <div class="row">
          <div class="row-pml"><?= htmlspecialchars($pml) ?></div>
          <div class="row-input">
            <input type="text" name="korwil[<?= htmlspecialchars($pml) ?>]"
                   value="<?= htmlspecialchars($korwilMap[$pml] ?? '') ?>"
                   placeholder="Nama Korwil...">
          </div>
        </div>
      <?php endforeach ?>
      <button type="submit" class="btn"><i class="ti ti-device-floppy"></i> Simpan Semua</button>
      <div class="hint">Total <?= count($daftarPml) ?> PML terdaftar.</div>
    </form>
    <?php endif ?>
  </div>
</div>
</body>
</html>
