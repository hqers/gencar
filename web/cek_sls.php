<?php
// ============================================================
// cek_sls.php — Cek/browse data hasil import Sub-SLS
// Sementara di root, sama seperti import_sls.php (belum masuk admin/ atau dashboard/)
// Data yang tersimpan di DB cuma level Sub-SLS (16 digit); level lain
// (kecamatan/kelurahan/SLS/kab-kota) dihitung di sini dengan agregasi.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_import_parser.php';  // SLS_SHEET_DEFS (label sheet)
require_once __DIR__ . '/lib/sls_aggregate.php';
require_once __DIR__ . '/lib/wilayah_lookup.php';       // $namaKec, $namaKel, $namaSls, $namaSubsls, $pplSls, $pplSubsls

requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Akses ditolak. Halaman ini khusus admin.');
}

$db = getDB();

// ── Filter ────────────────────────────────────────────────────────────────
$dates = $db->query("SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC")
            ->fetchAll(PDO::FETCH_COLUMN);

$tanggal = $_GET['tanggal'] ?? ($dates[0] ?? '');
$sheet   = $_GET['sheet']   ?? 'progres_pendataan';
$level   = $_GET['level']   ?? 'kecamatan';
$q       = trim($_GET['q']  ?? '');

$sheetLabels = [
    'progres_pendataan'  => 'Progres Pendataan',
    'skala_usaha'        => 'Skala Usaha',
    'usaha_perusahaan'   => 'Usaha/Perusahaan',
    'usaha_keluarga'     => 'Usaha Keluarga',
    'keseluruhan_usaha'  => 'Keseluruhan Usaha',
    'proporsi_usaha'     => 'Proporsi Usaha',
    'jaringan_usaha'     => 'Jaringan Usaha',
    'proporsi_pertanian' => 'Proporsi Pertanian/Non Pertanian',
    'pemutakhiran_keluarga' => 'Pemutakhiran Keluarga',
    'keluarga_khusus' => 'Keluarga Khusus',
];
$levelLabels = ['kabkota'=>'Kab/Kota','kecamatan'=>'Kecamatan','kelurahan'=>'Kelurahan','sls'=>'SLS','subsls'=>'Sub-SLS'];

$namaLookupByLevel = [
    'kecamatan' => $namaKec ?? [],
    'kelurahan' => $namaKel ?? [],
    'sls'       => $namaSls ?? [],
    'subsls'    => $namaSubsls ?? [],
    'kabkota'   => [],
];
$showPpl = in_array($level, ['sls', 'subsls'], true);

$rows = [];
$fieldKeys = [];
if ($tanggal) {
    $subslsRows = slsFetchSubslsRows($db, $tanggal, $sheet);
    $agg = slsAggregateToLevel($subslsRows, $level, $namaLookupByLevel[$level] ?? []);

    if ($showPpl) {
        $kodeInfo = slsBuildKodeSlsPplPmlMap($db, $pplSubsls, array_keys($subslsRows));
        $pplsBySls14 = []; // buat rollup ke level SLS (14 digit): kumpulan nama PPL anak2nya
        foreach ($kodeInfo as $kode16 => $info) {
            $pplsBySls14[substr($kode16, 0, 14)][$info['ppl']] = true;
        }
    }

    foreach ($agg as $kode => $r) {
        if ($q !== '' && stripos($kode, $q) === false && stripos($r['nama'], $q) === false) continue;
        if ($showPpl) {
            $r['ppl'] = $level === 'subsls'
                ? ($kodeInfo[$kode]['ppl'] ?? null)
                : implode(', ', array_keys($pplsBySls14[$kode] ?? []));
        }
        $rows[] = $r;
        if (!$fieldKeys && $r['data']) $fieldKeys = array_keys($r['data']);
    }
}

// ── Ringkasan per tanggal: jumlah baris Sub-SLS per sheet ──────────────────
$summary = [];
if ($tanggal) {
    $stmt = $db->prepare("
        SELECT sheet_key, COUNT(*) AS jumlah
        FROM sls_import_data WHERE tanggal = ? AND level = 'subsls'
        GROUP BY sheet_key
    ");
    $stmt->execute([$tanggal]);
    foreach ($stmt->fetchAll() as $s) {
        $summary[$s['sheet_key']] = (int)$s['jumlah'];
    }
}

function fmtVal($v) {
    if ($v === null) return '—';
    if (is_float($v)) return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    return $v;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cek Data Sub-SLS — SELARAS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--o-lt:#FDE8DD;--o-mid:#F5C98A;--navy:#1A2744;--ok:#16A34A;--ok-lt:#DCFCE7;
--text:#1A1A2E;--text2:#6B7280;--border:#E4E4E4;--bg:#F5F6FA;--grad:linear-gradient(135deg,#E8560A,#F5A623)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o)}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;text-decoration:none}
.main{max-width:1100px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:20px}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px}
.frow{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:6px}
.fg{display:flex;flex-direction:column;gap:4px}
label{font-size:10.5px;font-weight:700;color:var(--text2);text-transform:uppercase}
select,input[type=text]{padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit}
.btn{padding:9px 16px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:var(--grad);color:#fff}
.sumgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:4px}
.sumcard{background:var(--bg);border-radius:8px;padding:10px 12px}
.sumcard .k{font-size:10.5px;color:var(--text2);text-transform:uppercase;font-weight:700}
.sumcard .v{font-size:18px;font-weight:800;color:var(--navy)}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{padding:8px 10px;text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap;background:#fff}
td{padding:7px 10px;border-bottom:1px solid #F0F0F0;white-space:nowrap}
tr:hover td{background:#FDFAF6}
.kode{font-family:monospace;font-size:11px;color:var(--text2)}
.lvl{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:700;background:var(--o-lt);color:var(--o)}
.no-data{text-align:center;padding:40px;color:var(--text2)}
.tw{overflow-x:auto}
.cnt{background:var(--o-lt);color:#C94508;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div><span class="nav-title">SELARAS Admin</span><span class="nav-sub">Cek Data Sub-SLS</span></div>
  </div>
  <a href="<?= BASE_URL ?>/dashboard/" class="nav-btn">← Dashboard</a>
</nav>

<div class="main">
  <div class="page-title">Cek Data Import Sub-SLS</div>
  <div class="page-sub">Lihat data yang sudah tersimpan dari hasil upload excel FASIH.</div>

  <?php if (!$dates): ?>
    <div class="panel"><div class="no-data">Belum ada data yang diimport. Upload dulu lewat <a href="<?= BASE_URL ?>/import_sls.php">import_sls.php</a>.</div></div>
  <?php else: ?>

  <div class="panel">
    <form method="GET">
      <div class="frow">
        <div class="fg">
          <label>Tanggal snapshot</label>
          <select name="tanggal" onchange="this.form.submit()">
            <?php foreach ($dates as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>" <?= $d===$tanggal?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="fg">
          <label>Sheet</label>
          <select name="sheet" onchange="this.form.submit()">
            <?php foreach ($sheetLabels as $k=>$lbl): ?>
              <option value="<?= $k ?>" <?= $k===$sheet?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="fg">
          <label>Level wilayah</label>
          <select name="level" onchange="this.form.submit()">
            <?php foreach ($levelLabels as $k=>$lbl): ?>
              <option value="<?= $k ?>" <?= $k===$level?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="fg">
          <label>Cari kode / nama</label>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="mis. Payakumbuh, 1376010...">
        </div>
        <button type="submit" class="btn">Tampilkan</button>
      </div>
    </form>

    <?php if ($summary): ?>
      <div style="margin-top:14px;font-size:11.5px;color:var(--text2);font-weight:700;text-transform:uppercase">Ringkasan tanggal <?= htmlspecialchars($tanggal) ?></div>
      <div class="sumgrid">
        <?php foreach ($sheetLabels as $sk=>$lbl):
          $tot = $summary[$sk] ?? 0;
        ?>
          <div class="sumcard">
            <div class="k"><?= htmlspecialchars($lbl) ?></div>
            <div class="v"><?= $tot ?: '—' ?></div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div style="font-weight:700;color:var(--navy)"><?= htmlspecialchars($sheetLabels[$sheet] ?? $sheet) ?> — <?= htmlspecialchars($levelLabels[$level] ?? $level) ?></div>
      <span class="cnt"><?= count($rows) ?> baris</span>
    </div>
    <?php if (!$rows): ?>
      <div class="no-data">Tidak ada data untuk kombinasi filter ini.</div>
    <?php else: ?>
      <div class="tw">
      <table>
        <thead><tr>
          <th>Kode</th><th>Nama</th>
          <?php foreach ($fieldKeys as $fk): ?><th><?= htmlspecialchars(str_replace('_',' ',$fk)) ?></th><?php endforeach ?>
          <?php if ($showPpl): ?><th>PPL</th><?php endif ?>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="kode"><?= htmlspecialchars($r['kode']) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <?php foreach ($fieldKeys as $fk): ?>
              <td><?= htmlspecialchars(fmtVal($r['data'][$fk] ?? null)) ?></td>
            <?php endforeach ?>
            <?php if ($showPpl): ?><td style="font-size:11.5px"><?= htmlspecialchars($r['ppl'] ?: '—') ?></td><?php endif ?>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      </div>
    <?php endif ?>
  </div>

  <?php endif ?>
</div>
</body>
</html>
