<?php
// ============================================================
// reset_database_kosong.php — SEKALI JALAN, kosongin SEMUA tabel di
// database (bukan cuma users). Dipakai kalau database ke-copy penuh dari
// deployment lain (mis. Payakumbuh -> Kupang) dan perlu mulai dari nol.
//
// DESTRUKTIF — hapus SEMUA data (users, mapping_nama, sls_import_data,
// catatan, dst). Butuh konfirmasi eksplisit, gak akan jalan tanpa itu.
// HAPUS FILE INI DARI SERVER setelah dipakai.
// ============================================================

require_once __DIR__ . '/config.php';

$db = getDB();

$tabel = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

$sudahDireset = false;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['konfirmasi'] ?? '') === 'KOSONGKAN') {
    try {
        $db->exec('PRAGMA foreign_keys = OFF');
        foreach ($tabel as $t) {
            $db->exec("DROP TABLE IF EXISTS \"$t\"");
        }
        $sudahDireset = true;
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Database — SELARAS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#F5F6FA;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;padding:32px;width:100%;max-width:460px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
h2{font-size:19px;font-weight:800;color:#1A2744;margin-bottom:6px}
.sub{font-size:13px;color:#6B7280;margin-bottom:18px}
.warn{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:14px;font-size:13px;margin-bottom:18px;line-height:1.6}
.ok{background:#DCFCE7;color:#16A34A;border:1px solid #BBF7D0;border-radius:8px;padding:14px;font-size:13px;margin-bottom:16px}
.tabel-list{background:#F5F6FA;border-radius:8px;padding:12px 14px;font-size:12px;color:#6B7280;margin-bottom:18px;max-height:140px;overflow-y:auto}
.tabel-list b{color:#1A2744}
label{display:block;font-size:12px;font-weight:700;color:#1A2744;margin-bottom:6px;margin-top:14px}
input[type=text]{width:100%;padding:10px 13px;border:1.5px solid #E4E4E4;border-radius:8px;font-size:14px;font-family:inherit;outline:none}
.btn{width:100%;padding:12px;background:#DC2626;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:18px}
.btn:disabled{opacity:.4;cursor:not-allowed}
a.next{display:block;text-align:center;margin-top:14px;color:#E8560A;font-weight:700;font-size:13px;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <h2>⚠️ Reset Database</h2>

  <?php if ($sudahDireset): ?>
    <div class="ok">✅ Semua tabel berhasil dihapus. Database sekarang kosong total.</div>
    <a href="<?= BASE_URL ?>/buat_admin_pertama.php" class="next">→ Lanjut bikin akun admin pertama</a>
  <?php else: ?>
    <p class="sub">Ini bakal hapus SEMUA data di database yang lagi kepakai sekarang (termasuk user, mapping, data import, catatan — semuanya).</p>

    <?php if ($err): ?><div class="warn">Gagal: <?= htmlspecialchars($err) ?></div><?php endif ?>

    <?php if (!$tabel): ?>
      <div class="ok">Database ini udah kosong (gak ada tabel sama sekali). Gak perlu direset lagi.</div>
      <a href="<?= BASE_URL ?>/buat_admin_pertama.php" class="next">→ Lanjut bikin akun admin pertama</a>
    <?php else: ?>
      <div class="tabel-list">
        <b>Tabel yang bakal dihapus (<?= count($tabel) ?>):</b><br>
        <?= implode(', ', array_map('htmlspecialchars', $tabel)) ?>
      </div>
      <div class="warn">Tindakan ini GAK BISA DIBATALKAN. Pastikan ini beneran database Kupang yang mau di-reset, bukan Payakumbuh.</div>
      <form method="POST" onsubmit="return document.getElementById('konf').value === 'KOSONGKAN'">
        <label>Ketik <code>KOSONGKAN</code> (huruf besar semua) buat konfirmasi:</label>
        <input type="text" id="konf" name="konfirmasi" autocomplete="off" placeholder="KOSONGKAN">
        <button type="submit" class="btn">Hapus Semua & Reset Database</button>
      </form>
    <?php endif ?>
  <?php endif ?>
</div>
</body>
</html>
