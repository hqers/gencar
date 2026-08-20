<?php
// ============================================================
// hapus_data.php — Hapus data sls_import_data per tanggal/sheet,
// buat re-import ulang kalau ada yang kebaca salah (misal skema
// excel berubah). Khusus admin, taruh di root spt import_sls.php.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_import_parser.php'; // SLS_SHEET_DEFS (label sheet)

requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Akses ditolak. Halaman ini khusus admin.');
}

$db = getDB();

$dates = $db->query("SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC")
            ->fetchAll(PDO::FETCH_COLUMN);

$sheetLabels = [
    ''                   => '— Semua sheet —',
    'progres_pendataan'  => 'Progres Pendataan',
    'skala_usaha'        => 'Skala Usaha',
    'usaha_perusahaan'   => 'Usaha/Perusahaan',
    'usaha_keluarga'     => 'Usaha Keluarga',
    'keseluruhan_usaha'  => 'Keseluruhan Usaha',
    'proporsi_usaha'     => 'Proporsi Usaha',
    'jaringan_usaha'     => 'Jaringan Usaha',
    'proporsi_pertanian' => 'Proporsi Pertanian/Non Pertanian',
    'pemutakhiran_keluarga' => 'Pemutakhiran Keluarga',
    'keluarga_khusus'    => 'Keluarga Khusus',
];

$msg = ''; $err = '';
$tanggal = $_POST['tanggal'] ?? $_GET['tanggal'] ?? '';
$sheet   = $_POST['sheet']   ?? $_GET['sheet']   ?? '';

// ── Hitung dulu berapa baris yang akan kena (preview) ──────────────────────
$previewCount = null;
if ($tanggal) {
    $sql = "SELECT COUNT(*) FROM sls_import_data WHERE tanggal = ?";
    $params = [$tanggal];
    if ($sheet !== '') { $sql .= " AND sheet_key = ?"; $params[] = $sheet; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $previewCount = (int)$stmt->fetchColumn();
}

// ── Eksekusi hapus (harus konfirmasi checkbox + tombol submit) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus' && !empty($_POST['konfirmasi'])) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $err = 'Tanggal wajib dipilih.';
    } else {
        $sql = "DELETE FROM sls_import_data WHERE tanggal = ?";
        $params = [$tanggal];
        if ($sheet !== '') { $sql .= " AND sheet_key = ?"; $params[] = $sheet; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $deleted = $stmt->rowCount();
        $msg = "✅ Berhasil menghapus $deleted baris untuk tanggal $tanggal" . ($sheet ? " (sheet: " . ($sheetLabels[$sheet] ?? $sheet) . ")" : " (semua sheet)") . ".";
        // refresh daftar tanggal & preview setelah hapus
        $dates = $db->query("SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_COLUMN);
        $previewCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hapus Data Import — SELARAS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--o-lt:#FDE8DD;--navy:#1A2744;--ok:#16A34A;--ok-lt:#DCFCE7;--red:#DC2626;--red-lt:#FEE2E2;
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
.alert-warn{background:#FEF3C7;color:#92400E;border:1px solid #FDE68A}
label{display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;margin-top:14px}
label:first-child{margin-top:0}
select{width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;outline:none}
.preview{margin-top:14px;padding:12px 14px;background:var(--bg);border-radius:8px;font-size:13px}
.preview b{color:var(--navy);font-size:16px}
.confirm-row{margin-top:16px;display:flex;align-items:start;gap:8px;font-size:12.5px;color:var(--text2)}
.confirm-row input{margin-top:2px}
.btn{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none;margin-top:14px}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:disabled{opacity:.4;cursor:not-allowed}
.no-data{text-align:center;padding:24px;color:var(--text2)}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div><span class="nav-title">SELARAS Admin</span><span class="nav-sub">Hapus Data Import</span></div>
  </div>
  <a href="<?= BASE_URL ?>/dashboard/" class="nav-btn">← Dashboard</a>
</nav>

<div class="main">
  <div class="page-title">Hapus Data Import</div>
  <div class="page-sub">Buat bersihin data yang kebaca salah (misal karena skema excel berubah), sebelum import ulang lewat <a href="<?= BASE_URL ?>/import_sls.php">import_sls.php</a>.</div>

  <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif ?>
  <?php if ($err): ?><div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif ?>

  <div class="panel">
    <?php if (!$dates): ?>
      <div class="no-data">Belum ada data tersimpan.</div>
    <?php else: ?>
    <form method="GET" id="previewForm">
      <label>Tanggal</label>
      <select name="tanggal" onchange="this.form.submit()">
        <option value="">— Pilih tanggal —</option>
        <?php foreach ($dates as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $d===$tanggal?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
        <?php endforeach ?>
      </select>

      <label>Sheet</label>
      <select name="sheet" onchange="this.form.submit()">
        <?php foreach ($sheetLabels as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $k===$sheet?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
        <?php endforeach ?>
      </select>
    </form>

    <?php if ($previewCount !== null): ?>
      <div class="preview">
        Akan menghapus <b><?= number_format($previewCount,0,',','.') ?></b> baris
        untuk tanggal <b><?= htmlspecialchars($tanggal) ?></b>
        (<?= $sheet ? htmlspecialchars($sheetLabels[$sheet] ?? $sheet) : 'semua sheet' ?>).
      </div>

      <?php if ($previewCount > 0): ?>
      <form method="POST">
        <input type="hidden" name="action" value="hapus">
        <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
        <input type="hidden" name="sheet" value="<?= htmlspecialchars($sheet) ?>">
        <div class="confirm-row">
          <input type="checkbox" id="konfirmasi" name="konfirmasi" value="1" onchange="document.getElementById('btnHapus').disabled=!this.checked">
          <label for="konfirmasi" style="text-transform:none;font-weight:400;margin:0">
            Saya yakin mau hapus data ini secara permanen (gak bisa dibatalkan — pastikan sudah siap import ulang filenya).
          </label>
        </div>
        <button type="submit" class="btn btn-danger" id="btnHapus" disabled>
          <i class="ti ti-trash"></i> Hapus <?= number_format($previewCount,0,',','.') ?> Baris
        </button>
      </form>
      <?php endif ?>
    <?php endif ?>
    <?php endif ?>
  </div>
</div>
</body>
</html>
