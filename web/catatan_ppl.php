<?php
// ============================================================
// catatan_ppl.php — Edit catatan 1 PPL saja (kotak teks simpel). Diakses
// dari klik "+ Catatan"/badge catatan di baris PPL itu, ppl_dashboard.php.
// PUBLIK — siapapun bisa isi tanpa perlu login.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_import_migrate.php';
require_once __DIR__ . '/lib/sls_aggregate.php';

$db = getDB();
migrateSlsImportTables($db);

$email = $_GET['email'] ?? $_POST['email'] ?? '';
$nama  = $_GET['nama']  ?? $_POST['nama']  ?? '';
$back  = $_GET['back']  ?? $_POST['back']  ?? BASE_URL . '/ppl_dashboard.php';

if ($nama === '') {
    die('Parameter gak lengkap.');
}

$key = slsIdentityKey($email ?: null, $nama);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan') {
    $teks = trim($_POST['catatan'] ?? '');
    $now = date('Y-m-d H:i:s');
    if ($teks === '') {
        $db->prepare("DELETE FROM catatan_ppl WHERE identity_key = ?")->execute([$key]);
    } else {
        $db->prepare("
            INSERT INTO catatan_ppl (identity_key, nama_tampil, catatan, updated_by, updated_at)
            VALUES (?, ?, ?, 'publik', ?)
            ON CONFLICT(identity_key) DO UPDATE SET catatan = excluded.catatan, updated_by = excluded.updated_by, updated_at = excluded.updated_at
        ")->execute([$key, $nama, $teks, $now]);
    }
    header('Location: ' . $back);
    exit;
}

$existing = $db->prepare("SELECT catatan, updated_at FROM catatan_ppl WHERE identity_key = ?");
$existing->execute([$key]);
$row = $existing->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Catatan PPL — SELARAS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--navy:#1A2744;--border:#E4E4E4;--bg:#F5F6FA;--grad:linear-gradient(135deg,#E8560A,#F5A623);--text2:#6B7280}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:#1A1A2E;font-size:14px;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:14px;padding:28px;max-width:460px;width:92%;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.lvl{display:inline-block;background:#FDE8DD;color:#C94508;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:10px}
h1{font-size:17px;color:var(--navy);margin-bottom:4px}
.sub{font-size:11px;color:var(--text2);margin-bottom:18px}
textarea{width:100%;padding:11px;border:1.5px solid var(--border);border-radius:9px;font-size:13.5px;font-family:inherit;min-height:100px;resize:vertical;outline:none}
textarea:focus{border-color:var(--o)}
.updated{font-size:11px;color:var(--text2);margin-top:6px}
.actions{display:flex;gap:8px;margin-top:16px}
.btn{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none}
.btn-save{background:var(--grad);color:#fff;flex:1}
.btn-cancel{background:var(--bg);color:var(--navy);text-decoration:none;display:flex;align-items:center;justify-content:center;padding:10px 16px}
</style>
</head>
<body>
<div class="card">
  <span class="lvl">PPL</span>
  <h1><?= htmlspecialchars($nama) ?></h1>
  <?php if ($email): ?><div class="sub"><?= htmlspecialchars($email) ?></div><?php endif ?>
  <form method="POST">
    <input type="hidden" name="action" value="simpan">
    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
    <input type="hidden" name="nama" value="<?= htmlspecialchars($nama) ?>">
    <input type="hidden" name="back" value="<?= htmlspecialchars($back) ?>">
    <textarea name="catatan" placeholder="Tulis catatan buat PPL ini... (kosongkan buat hapus)" autofocus><?= htmlspecialchars($row['catatan'] ?? '') ?></textarea>
    <?php if ($row): ?><div class="updated">Diubah <?= htmlspecialchars($row['updated_at']) ?></div><?php endif ?>
    <div class="actions">
      <button type="submit" class="btn btn-save"><i class="ti ti-device-floppy"></i> Simpan</button>
      <a href="<?= htmlspecialchars($back) ?>" class="btn btn-cancel">Batal</a>
    </div>
  </form>
</div>
</body>
</html>
