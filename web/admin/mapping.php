<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db  = getDB();
$msg = '';
$err = '';

// ── Handle POST actions ───────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $email  = strtolower(trim($_POST['email'] ?? ''));
    $nama   = trim($_POST['nama'] ?? '');
    $pml    = trim($_POST['pml'] ?? '');
    $tampil = isset($_POST['tampil']) ? 1 : 0;
    if (!$email || !$nama) {
        $err = 'Email dan nama wajib diisi.';
    } else {
        try {
            $db->prepare("INSERT INTO mapping_nama (email,nama,pml,tampil) VALUES (?,?,?,?)")
               ->execute([$email, $nama, $pml, $tampil]);
            $msg = "✅ Pencacah <strong>$nama</strong> berhasil ditambahkan.";
        } catch (Exception $e) {
            $err = 'Email sudah terdaftar.';
        }
    }
}

if ($action === 'edit') {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $pml    = trim($_POST['pml'] ?? '');
    $tampil = isset($_POST['tampil']) ? 1 : 0;
    $email  = strtolower(trim($_POST['email'] ?? ''));
    if (!$id || !$nama || !$email) {
        $err = 'Data tidak lengkap.';
    } else {
        $db->prepare("UPDATE mapping_nama SET email=?,nama=?,pml=?,tampil=? WHERE id=?")
           ->execute([$email, $nama, $pml, $tampil, $id]);
        $msg = "✅ Data <strong>$nama</strong> berhasil diperbarui.";
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $row = $db->prepare("SELECT nama FROM mapping_nama WHERE id=?")->execute([$id]);
        $row = $db->query("SELECT nama FROM mapping_nama WHERE id=$id")->fetch();
        $db->prepare("DELETE FROM mapping_nama WHERE id=?")->execute([$id]);
        $msg = "🗑️ Data <strong>" . htmlspecialchars($row['nama'] ?? '') . "</strong> dihapus.";
    }
}

if ($action === 'toggle') {
    $id  = (int)($_POST['id'] ?? 0);
    $val = (int)($_POST['tampil'] ?? 0);
    if ($id) {
        $db->prepare("UPDATE mapping_nama SET tampil=? WHERE id=?")->execute([$val, $id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// ── Ambil data ────────────────────────────────────────────────────────────
$srch = trim($_GET['q'] ?? '');
$sql  = "SELECT * FROM mapping_nama";
if ($srch) $sql .= " WHERE nama LIKE ? OR email LIKE ? OR pml LIKE ?";
$sql .= " ORDER BY pml, nama";
$stmt = $db->prepare($sql);
if ($srch) $stmt->execute(["%$srch%", "%$srch%", "%$srch%"]);
else $stmt->execute();
$rows = $stmt->fetchAll();

// Daftar PML unik untuk dropdown
$pmls = $db->query("SELECT DISTINCT pml FROM mapping_nama WHERE pml != '' ORDER BY pml")
           ->fetchAll(PDO::FETCH_COLUMN);

$user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kelola Mapping — SELARAS SE2026</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{
  --o:#E8560A;--o-lt:#FDE8DD;--o-mid:#F5C98A;--o-dk:#C94508;
  --navy:#1A2744;--navy2:#243158;
  --ok:#16A34A;--ok-lt:#DCFCE7;
  --red:#DC2626;--red-lt:#FEE2E2;
  --amb:#D97706;--amb-lt:#FEF3C7;
  --text:#1A1A2E;--text2:#6B7280;--border:#E4E4E4;--bg:#F5F6FA;
  --grad:linear-gradient(135deg,#E8560A,#F5A623);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o)}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-right{display:flex;align-items:center;gap:8px}
.nav-user{color:rgba(255,255,255,.7);font-size:12px}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;cursor:pointer;font-family:inherit;text-decoration:none;transition:background .15s}
.nav-btn:hover{background:rgba(255,255,255,.18)}
.main{max-width:1100px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:24px}

/* ALERT */
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;font-weight:500}
.alert-ok{background:var(--ok-lt);color:var(--ok);border:1px solid #BBF7D0}
.alert-err{background:var(--red-lt);color:var(--red);border:1px solid #FECACA}

/* PANEL ADD */
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
.panel-title{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.panel-title i{color:var(--o);font-size:18px}
.form-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:10px;align-items:end}
.fg{display:flex;flex-direction:column;gap:4px}
label{font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px}
input[type=text],input[type=email],select{
  padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;
  font-size:13px;font-family:inherit;outline:none;color:var(--text);
  transition:border-color .15s;background:#fff;width:100%
}
input:focus,select:focus{border-color:var(--o)}
.btn{padding:9px 16px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;border:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;transition:opacity .15s}
.btn:hover{opacity:.88}
.btn-primary{background:var(--grad);color:#fff}
.btn-sm{padding:5px 10px;font-size:11.5px;border-radius:6px;font-weight:600}
.btn-edit{background:var(--amb-lt);color:var(--amb);border:1px solid #FDE68A}
.btn-del{background:var(--red-lt);color:var(--red);border:1px solid #FECACA}
.btn-ghost{background:var(--bg);color:var(--text2);border:1px solid var(--border)}
.toggle{width:36px;height:20px;border-radius:10px;background:var(--border);position:relative;cursor:pointer;transition:background .2s;border:none;flex-shrink:0}
.toggle.on{background:var(--ok)}
.toggle::after{content:'';position:absolute;width:14px;height:14px;border-radius:50%;background:#fff;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle.on::after{left:19px}

/* TOOLBAR */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.sbox{position:relative;flex:1;min-width:200px}
.sbox input{padding-left:34px}
.sbox i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text2);font-size:15px}
.cnt-badge{background:var(--o-lt);color:var(--o-dk);border:1px solid var(--o-mid);border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;white-space:nowrap}

/* TABLE */
.tw{background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead tr{background:var(--bg)}
th{padding:10px 12px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:9px 12px;font-size:13px;border-bottom:1px solid #F0F0F0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FDFAF6}
.td-email{font-size:11px;color:var(--text2)}
.pml-badge{display:inline-block;background:var(--navy);color:#fff;border-radius:4px;font-size:10px;font-weight:700;padding:2px 7px}
.no-data{text-align:center;padding:40px;color:var(--text2)}

/* MODAL */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;display:none;align-items:center;justify-content:center}
.overlay.show{display:flex}
.modal{background:#fff;border-radius:14px;padding:24px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-title{font-size:16px;font-weight:800;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.modal-grid .fg.full{grid-column:1/-1}
.modal-footer{display:flex;gap:8px;justify-content:flex-end}

@media(max-width:700px){
  .form-grid{grid-template-columns:1fr 1fr}
  .modal-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div>
      <span class="nav-title">SELARAS Admin</span>
      <span class="nav-sub">Kelola Mapping Pencacah</span>
    </div>
  </div>
  <div class="nav-right">
    <span class="nav-user"><i class="ti ti-user" style="font-size:13px"></i> <?= htmlspecialchars($user['nama'] ?: $user['username']) ?></span>
    <a href="../dashboard/" class="nav-btn"><i class="ti ti-layout-dashboard" style="font-size:13px"></i> Dashboard</a>
    <a href="../auth/logout.php" class="nav-btn">Keluar</a>
  </div>
</nav>

<div class="main">
  <div class="page-title">Kelola Mapping Pencacah</div>
  <div class="page-sub">Atur nama tampil, PML, dan status aktif tiap pencacah</div>

  <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif ?>
  <?php if ($err): ?><div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif ?>

  <!-- FORM TAMBAH -->
  <div class="panel">
    <div class="panel-title"><i class="ti ti-user-plus"></i> Tambah Pencacah Baru</div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" placeholder="pencacah@gmail.com" required>
        </div>
        <div class="fg">
          <label>Nama Tampil</label>
          <input type="text" name="nama" placeholder="Nama lengkap" required>
        </div>
        <div class="fg">
          <label>PML / Pengawas</label>
          <input type="text" name="pml" placeholder="Nama PML" list="pml-list">
          <datalist id="pml-list">
            <?php foreach ($pmls as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>">
            <?php endforeach ?>
          </datalist>
        </div>
        <div class="fg">
          <label>Tampilkan</label>
          <select name="tampil">
            <option value="1">Ya</option>
            <option value="0">Tidak</option>
          </select>
        </div>
        <div class="fg">
          <label>&nbsp;</label>
          <button type="submit" class="btn btn-primary"><i class="ti ti-plus"></i> Tambah</button>
        </div>
      </div>
    </form>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <form method="GET" style="flex:1;display:flex;gap:10px;align-items:center">
      <div class="sbox">
        <i class="ti ti-search"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($srch) ?>" placeholder="Cari nama, email, atau PML...">
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Cari</button>
      <?php if ($srch): ?><a href="?" class="btn btn-ghost btn-sm">Reset</a><?php endif ?>
    </form>
    <div class="cnt-badge"><?= count($rows) ?> pencacah</div>
  </div>

  <!-- TABEL -->
  <div class="tw">
    <?php if (!$rows): ?>
      <div class="no-data">Tidak ada data.</div>
    <?php else: ?>
    <table>
      <colgroup>
        <col style="width:5%"><col style="width:28%"><col style="width:24%">
        <col style="width:20%"><col style="width:11%"><col style="width:12%">
      </colgroup>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama & Email</th>
          <th>PML / Pengawas</th>
          <th>Email</th>
          <th>Tampil</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td style="color:var(--text2);font-size:12px"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:700;color:var(--navy)"><?= htmlspecialchars($r['nama']) ?></div>
            <div class="td-email"><?= htmlspecialchars($r['email']) ?></div>
          </td>
          <td><?php if ($r['pml']): ?><span class="pml-badge"><?= htmlspecialchars($r['pml']) ?></span><?php else: ?><span style="color:var(--text2);font-size:12px">—</span><?php endif ?></td>
          <td class="td-email"><?= htmlspecialchars($r['email']) ?></td>
          <td>
            <button class="toggle <?= $r['tampil'] ? 'on' : '' ?>"
              onclick="toggleTampil(<?= $r['id'] ?>, this)"
              title="<?= $r['tampil'] ? 'Tampil' : 'Disembunyikan' ?>">
            </button>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($r)) ?>)">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-del" onclick="confirmDelete(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nama'])) ?>')">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="overlay" id="editOverlay">
  <div class="modal">
    <div class="modal-title"><i class="ti ti-edit" style="color:var(--o)"></i> Edit Pencacah</div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="modal-grid">
        <div class="fg full">
          <label>Email</label>
          <input type="email" name="email" id="editEmail" required>
        </div>
        <div class="fg full">
          <label>Nama Tampil</label>
          <input type="text" name="nama" id="editNama" required>
        </div>
        <div class="fg full">
          <label>PML / Pengawas</label>
          <input type="text" name="pml" id="editPml" list="pml-list">
        </div>
        <div class="fg">
          <label>Tampilkan</label>
          <select name="tampil" id="editTampil">
            <option value="1">Ya</option>
            <option value="0">Tidak</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeEdit()">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- FORM DELETE (hidden) -->
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteId">
</form>

<script>
function openEdit(r) {
  document.getElementById('editId').value    = r.id;
  document.getElementById('editEmail').value = r.email;
  document.getElementById('editNama').value  = r.nama;
  document.getElementById('editPml').value   = r.pml || '';
  document.getElementById('editTampil').value = r.tampil ? '1' : '0';
  document.getElementById('editOverlay').classList.add('show');
}
function closeEdit() {
  document.getElementById('editOverlay').classList.remove('show');
}
document.getElementById('editOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

function confirmDelete(id, nama) {
  if (!confirm('Hapus pencacah "' + nama + '"?\n\nData progress-nya tidak akan terhapus.')) return;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteForm').submit();
}

function toggleTampil(id, btn) {
  var isOn = btn.classList.toggle('on');
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=toggle&id=' + id + '&tampil=' + (isOn ? 1 : 0)
  });
  btn.title = isOn ? 'Tampil' : 'Disembunyikan';
}
</script>
</body>
</html>