<?php
// ============================================================
// api/import_sls.php — Import data "Progres Pendataan Sub-SLS" (FASIH excel)
// Khusus ADMIN. Upload 1-5 file excel (satu file per kecamatan), sekali proses.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_import_parser.php';
require_once __DIR__ . '/lib/sls_import_migrate.php';

requireLogin();

// ── Akses khusus admin ──────────────────────────────────────────────────
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Akses ditolak. Halaman ini khusus admin.');
}

$db = getDB();
migrateSlsImportTables($db);

$msg      = '';
$err      = '';
$results  = []; // ringkasan per file yang berhasil diproses

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $tanggal = trim($_POST['tanggal'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $err = 'Tanggal snapshot wajib diisi dengan format yang benar.';
    } elseif (empty($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        $err = 'Pilih minimal satu file excel.';
    } else {
        $files = $_FILES['files'];
        $count = count($files['name']);
        $now   = date('Y-m-d H:i:s');

        // Statement upsert dipakai berulang untuk semua sheet & file
        $upsert = $db->prepare("
            INSERT INTO sls_import_data (tanggal, sheet_key, kode, level, nama, data_json, updated_at)
            VALUES (:tanggal, :sheet_key, :kode, :level, :nama, :data_json, :updated_at)
            ON CONFLICT(tanggal, sheet_key, kode) DO UPDATE SET
                level      = excluded.level,
                nama       = excluded.nama,
                data_json  = excluded.data_json,
                updated_at = excluded.updated_at
        ");
        $logStmt = $db->prepare("
            INSERT INTO sls_import_log
                (tanggal, filename, kecamatan_kode, kecamatan_nama, sheets_found, sheets_missing, row_count, uploaded_by, created_at)
            VALUES (:tanggal, :filename, :kkode, :knama, :sfound, :smissing, :rowcount, :uploader, :created_at)
        ");

        for ($i = 0; $i < $count; $i++) {
            $tmpPath  = $files['tmp_name'][$i];
            $origName = $files['name'][$i];
            $errCode  = $files['error'][$i];

            if ($errCode !== UPLOAD_ERR_OK || !$tmpPath) {
                $results[] = ['file' => $origName, 'success' => false, 'message' => 'Upload gagal (kode error ' . $errCode . ').'];
                continue;
            }
            if (!preg_match('/\.xlsx$/i', $origName)) {
                $results[] = ['file' => $origName, 'success' => false, 'message' => 'Bukan file .xlsx.'];
                continue;
            }

            try {
                $parsed = parseSlsImportFile($tmpPath);

                if (empty($parsed['sheets_found'])) {
                    $results[] = ['file' => $origName, 'success' => false, 'message' => 'Tidak ada sheet yang dikenali di file ini.'];
                    continue;
                }

                $db->beginTransaction();
                $savedRows = 0;
                foreach ($parsed['sheets'] as $sheetKey => $rows) {
                    foreach ($rows as $kode => $row) {
                        $upsert->execute([
                            ':tanggal'    => $tanggal,
                            ':sheet_key'  => $sheetKey,
                            ':kode'       => $kode,
                            ':level'      => $row['level'],
                            ':nama'       => $row['nama'],
                            ':data_json'  => json_encode($row['data'], JSON_UNESCAPED_UNICODE),
                            ':updated_at' => $now,
                        ]);
                        $savedRows++;
                    }
                }

                $logStmt->execute([
                    ':tanggal'    => $tanggal,
                    ':filename'   => $origName,
                    ':kkode'      => $parsed['kecamatan']['kode'] ?? null,
                    ':knama'      => $parsed['kecamatan']['nama'] ?? null,
                    ':sfound'     => implode(',', $parsed['sheets_found']),
                    ':smissing'   => implode(',', $parsed['sheets_missing']),
                    ':rowcount'   => $parsed['row_count'],
                    ':uploader'   => $_SESSION['username'] ?? '',
                    ':created_at' => $now,
                ]);
                $db->commit();

                $results[] = [
                    'file'          => $origName,
                    'success'       => true,
                    'kecamatan'     => $parsed['kecamatan']['nama'] ?? '(tidak terdeteksi)',
                    'sheets_found'  => count($parsed['sheets_found']),
                    'sheets_missing'=> $parsed['sheets_missing'],
                    'row_count'     => $parsed['row_count'],
                    'saved_rows'    => $savedRows,
                ];
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $results[] = ['file' => $origName, 'success' => false, 'message' => $e->getMessage()];
            }
        }

        $okCount = count(array_filter($results, fn($r) => $r['success']));
        if ($okCount > 0) {
            $msg = "✅ Berhasil memproses $okCount dari $count file untuk tanggal " . htmlspecialchars($tanggal) . ".";
        } else {
            $err = 'Tidak ada file yang berhasil diproses. Lihat detail di bawah.';
        }
    }
}

// ── Riwayat import terakhir ──────────────────────────────────────────────
$history = $db->query("
    SELECT * FROM sls_import_log ORDER BY id DESC LIMIT 20
")->fetchAll();

$dates = $db->query("
    SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Import Data Sub-SLS — SELARAS Admin</title>
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
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;cursor:pointer;font-family:inherit;text-decoration:none}
.nav-btn:hover{background:rgba(255,255,255,.18)}
.main{max-width:900px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:24px}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;font-weight:500}
.alert-ok{background:var(--ok-lt);color:var(--ok);border:1px solid #BBF7D0}
.alert-err{background:var(--red-lt);color:var(--red);border:1px solid #FECACA}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
.panel-title{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.panel-title i{color:var(--o);font-size:18px}
label{display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
input[type=date]{padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;outline:none;width:220px}
.filebox{border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;margin:14px 0;cursor:pointer;transition:border-color .15s}
.filebox:hover{border-color:var(--o)}
.filebox i{font-size:28px;color:var(--o);margin-bottom:6px;display:block}
.filebox-hint{font-size:12px;color:var(--text2);margin-top:4px}
#fileList{margin-top:10px;font-size:12.5px;color:var(--text)}
#fileList div{padding:4px 0}
.btn{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;border:none;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--grad);color:#fff}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}
.result-item{border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:13px}
.result-ok{border-color:#BBF7D0;background:#F7FEF9}
.result-fail{border-color:#FECACA;background:#FFF7F7}
.result-file{font-weight:700;color:var(--navy)}
.result-detail{color:var(--text2);font-size:12px;margin-top:2px}
table{width:100%;border-collapse:collapse}
th{padding:9px 12px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border)}
td{padding:8px 12px;font-size:12.5px;border-bottom:1px solid #F0F0F0}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700}
.badge-ok{background:var(--ok-lt);color:var(--ok)}
.no-data{text-align:center;padding:32px;color:var(--text2);font-size:13px}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div>
      <span class="nav-title">SELARAS Admin</span>
      <span class="nav-sub">Import Data Sub-SLS</span>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/dashboard/" class="nav-btn">← Dashboard</a>
</nav>

<div class="main">
  <div class="page-title">Import Data Progres Pendataan Sub-SLS</div>
  <div class="page-sub">Upload file export FASIH per kecamatan (bisa beberapa file sekaligus, satu file = satu kecamatan). Seluruh 8 sheet di dalam tiap file akan disimpan otomatis.</div>

  <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif ?>
  <?php if ($err): ?><div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif ?>

  <div class="panel">
    <div class="panel-title"><i class="ti ti-upload"></i> Upload File Excel</div>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="action" value="upload">

      <label>Tanggal Snapshot (sesuai "Diperbarui" di file excel)</label>
      <input type="date" name="tanggal" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>

      <div class="filebox" onclick="document.getElementById('fileInput').click()">
        <i class="ti ti-file-spreadsheet"></i>
        <div>Klik untuk pilih file excel (.xlsx)</div>
        <div class="filebox-hint">Bisa pilih lebih dari satu file sekaligus (maks. 5 kecamatan)</div>
      </div>
      <input type="file" id="fileInput" name="files[]" accept=".xlsx" multiple style="display:none" onchange="showFiles(this)">
      <div id="fileList"></div>

      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
          <i class="ti ti-database-import"></i> Proses & Simpan ke Database
        </button>
      </div>
    </form>
  </div>

  <?php if ($results): ?>
  <div class="panel">
    <div class="panel-title"><i class="ti ti-list-check"></i> Hasil Proses</div>
    <?php foreach ($results as $r): ?>
      <?php if ($r['success']): ?>
        <div class="result-item result-ok">
          <div class="result-file">✅ <?= htmlspecialchars($r['file']) ?> — <?= htmlspecialchars($r['kecamatan']) ?></div>
          <div class="result-detail">
            <?= $r['sheets_found'] ?>/8 sheet terbaca, <?= $r['saved_rows'] ?> baris disimpan.
            <?php if ($r['sheets_missing']): ?>
              Sheet tidak ditemukan: <?= htmlspecialchars(implode(', ', $r['sheets_missing'])) ?>.
            <?php endif ?>
          </div>
        </div>
      <?php else: ?>
        <div class="result-item result-fail">
          <div class="result-file">❌ <?= htmlspecialchars($r['file']) ?></div>
          <div class="result-detail"><?= htmlspecialchars($r['message']) ?></div>
        </div>
      <?php endif ?>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <div class="panel">
    <div class="panel-title"><i class="ti ti-history"></i> Riwayat Import</div>
    <?php if (!$history): ?>
      <div class="no-data">Belum ada riwayat import.</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>Tanggal</th><th>File</th><th>Kecamatan</th><th>Baris</th><th>Diupload oleh</th><th>Waktu</th>
      </tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><?= htmlspecialchars($h['tanggal']) ?></td>
          <td><?= htmlspecialchars($h['filename']) ?></td>
          <td><?= htmlspecialchars($h['kecamatan_nama'] ?: '—') ?></td>
          <td><span class="badge badge-ok"><?= (int)$h['row_count'] ?></span></td>
          <td><?= htmlspecialchars($h['uploaded_by']) ?></td>
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['created_at']))) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>
</div>

<script>
function showFiles(input) {
  var list = document.getElementById('fileList');
  var btn  = document.getElementById('submitBtn');
  if (!input.files.length) { list.innerHTML = ''; btn.disabled = true; return; }
  var html = '';
  for (var i = 0; i < input.files.length; i++) {
    html += '<div>📄 ' + input.files[i].name + '</div>';
  }
  list.innerHTML = html;
  btn.disabled = false;
}
</script>
</body>
</html>
