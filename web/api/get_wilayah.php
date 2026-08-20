<?php
// ============================================================
// api/get_wilayah.php — Rekap progress per wilayah
// Mendukung level: kecamatan (7 digit), desa (10 digit), sls (16 digit)
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$level  = $_GET['level']   ?? 'kecamatan'; // kecamatan | desa | sls
$tgl    = $_GET['tanggal'] ?? null;
$parent = $_GET['parent']  ?? null; // filter by kode induk

// Panjang kode per level
$lenMap = ['kecamatan' => 7, 'desa' => 10, 'sls' => 14, 'subsls' => 16];
$len    = $lenMap[$level] ?? 7;

// Ambil tanggal terbaru kalau tidak diisi
if (!$tgl) {
    $tgl = $db->query("SELECT MAX(tanggal) FROM progress_wilayah")->fetchColumn();
}
if (!$tgl) {
    jsonResponse(['success' => true, 'rows' => [], 'tanggal' => null, 'level' => $level]);
}

// Ambil daftar tanggal tersedia di progress_wilayah
$dates = $db->query("SELECT DISTINCT tanggal FROM progress_wilayah ORDER BY tanggal DESC")
            ->fetchAll(PDO::FETCH_COLUMN);

// Kalau tanggal yang diminta tidak ada di progress_wilayah, pakai yang terbaru
if ($tgl && !in_array($tgl, $dates)) {
    $tglAsli = $tgl;
    $tgl = $dates[0] ?? null; // fallback ke terbaru
    if (!$tgl) {
        jsonResponse([
            'success' => true, 'rows' => [], 'tanggal' => null,
            'level' => $level, 'dates' => [],
            'warning' => 'Belum ada data wilayah. Jalankan bookmarklet SIHARAU terlebih dahulu.'
        ]);
    }
}

// Query: grup per prefix regionCode sesuai level
// SQLite: SUBSTR(region_code, 1, N)
$sql = "
    SELECT
        SUBSTR(region_code, 1, $len)    AS kode,
        SUM(total)                       AS total,
        SUM(open_count)                  AS open_count,
        SUM(draft)                       AS draft,
        SUM(submitted)                   AS submitted,
        SUM(approved)                    AS approved,
        SUM(rejected)                    AS rejected,
        SUM(selesai)                     AS selesai,
        COUNT(DISTINCT email)            AS jml_pencacah
    FROM progress_wilayah
    WHERE tanggal = ?
";

$params = [$tgl];

// Filter by parent (misal tampilkan desa dalam kecamatan tertentu)
if ($parent) {
    $sql .= " AND region_code LIKE ?";
    $params[] = $parent . '%';
}

$sql .= " GROUP BY SUBSTR(region_code, 1, $len) ORDER BY kode";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Tambah progress_pct dan nama kecamatan
require_once __DIR__ . '/../lib/wilayah_lookup.php';

$rows = array_map(function($r) use ($len, $namaKec, $namaKel, $namaSls, $namaSubsls, $pplSubsls, $pplSls, $pplDesa, $pplKec) {
    $kode    = $r['kode'];
    $total   = (int)$r['total'];
    $selesai = (int)$r['selesai'];
    $approved= (int)$r['approved'];

    $r['total']        = $total;
    $r['open']         = (int)$r['open_count'];
    $r['submitted']    = (int)$r['submitted'];
    $r['approved']     = $approved;
    $r['rejected']     = (int)$r['rejected'];
    $r['selesai']      = $selesai;
    $r['jml_pencacah'] = (int)$r['jml_pencacah'];
    $r['progress_pct'] = $total > 0 ? round($selesai / $total * 100, 1) : 0;
    $r['approved_pct'] = $total > 0 ? round($approved / $total * 100, 1) : 0;

    $kec7  = substr($kode, 0, 7);
    $des10 = substr($kode, 0, 10);

    if ($len === 7) {
        $r['nama'] = $namaKec[$kode] ?? $kode;
        $r['ppl']  = [];
    } elseif ($len === 10) {
        $namaKelurahan = $namaKel[$kode] ?? null;
        $namaKecamatan = $namaKec[$kec7] ?? $kec7;
        $r['nama'] = $namaKelurahan
            ? $namaKelurahan . ' (' . $namaKecamatan . ')'
            : $kode . ' (' . $namaKecamatan . ')';
        $r['ppl']  = [];
    } elseif ($len === 14) {
        $namaSLS       = $namaSls[$kode] ?? null;
        $namaKelurahan = $namaKel[$des10] ?? $des10;
        $r['nama'] = $namaSLS
            ? $namaSLS . ' — ' . $namaKelurahan
            : $kode;
        $r['ppl']  = $pplSls[$kode] ?? [];
    } else {
        $namaSSLS      = $namaSubsls[$kode] ?? null;
        $namaKelurahan = $namaKel[$des10] ?? $des10;
        $r['nama'] = $namaSSLS
            ? $namaSSLS . ' — ' . $namaKelurahan
            : $kode;
        $r['ppl']  = isset($pplSubsls[$kode]) ? [$pplSubsls[$kode]] : [];
    }

    unset($r['open_count']);
    return $r;
}, $rows);

// Summary total
$summary = array_reduce($rows, function($carry, $r) {
    $carry['total']    += $r['total'];
    $carry['selesai']  += $r['selesai'];
    $carry['approved'] += $r['approved'];
    $carry['open']     += $r['open'];
    return $carry;
}, ['total' => 0, 'selesai' => 0, 'approved' => 0, 'open' => 0]);

$summary['progress_pct'] = $summary['total'] > 0
    ? round($summary['selesai'] / $summary['total'] * 100, 1) : 0;
$summary['approved_pct'] = $summary['total'] > 0
    ? round($summary['approved'] / $summary['total'] * 100, 1) : 0;

jsonResponse([
    'success'  => true,
    'tanggal'  => $tgl,
    'level'    => $level,
    'dates'    => $dates,
    'summary'  => $summary,
    'rows'     => $rows,
]);