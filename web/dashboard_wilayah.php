<?php
// ============================================================
// dashboard_wilayah.php — Target vs Realisasi per kelurahan, 1 tabel lebar
// (Keluarga | Usaha | Usaha Pertanian bersebelahan), dikelompokkan per
// kecamatan — urutan kecamatan sama kayak tab Wilayah di dashboard/index.php.
//   - Keluarga    : target dari DTSEN (hardcode), realisasi dari pemutakhiran_keluarga
//   - Usaha       : target 716×jumlah PPL, realisasi dari proporsi_pertanian (semua kategori)
//   - Usaha Pertanian : target dari sheet proporsi_pertanian (UTP Subsektor Target),
//                       realisasi dari proporsi_pertanian (kategori pertanian aja)
// Semua realisasi & target Usaha/Pertanian sudah ada dari hasil import Sub-SLS
// biasa (import_sls.php) — gak ada sumber data baru selain DTSEN yang di-hardcode.
// Halaman publik, sama seperti ppl_dashboard.php.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_aggregate.php';
require_once __DIR__ . '/lib/wilayah_lookup.php'; // $namaKec, $namaKel, $pplSubsls
require_once __DIR__ . '/lib/dtsen_target.php';   // TARGET_KELUARGA_KELURAHAN (hardcode)

session_name(SESSION_NAME);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && (($_SESSION['role'] ?? '') === 'admin');
$namaUser   = $_SESSION['nama'] ?? $_SESSION['username'] ?? '';

// Cegah WebView app nyimpen halaman ini lama-lama — tanpa ini, klik reload
// di app kadang masih nampilin versi lama (app gak baca update sampai cache expired)
header('Cache-Control: no-cache, must-revalidate');

function n($v) { return $v ?? 0; }
function pct(?float $target, float $realisasi): ?float {
    return ($target !== null && $target > 0) ? round($realisasi / $target * 100, 1) : null;
}
function barColor(?float $p): string {
    if ($p === null) return 'var(--text2)';
    return $p >= 80 ? 'var(--ok)' : ($p >= 50 ? '#D97706' : 'var(--o)');
}
function selPct(?float $p, string $target, string $realisasi): string {
    if ($p === null) return '<td>' . ($target === '—' ? '—' : $target) . '</td><td>' . $realisasi . '</td><td>—</td>';
    $c = barColor($p);
    return '<td>' . $target . '</td><td>' . $realisasi . '</td>'
         . '<td><div class="prog-wrap"><div class="prog-bg"><div class="prog-fill" style="width:' . min(100,$p) . '%;background:' . $c . '"></div></div>'
         . '<span style="font-weight:700;color:' . $c . ';min-width:42px;display:inline-block">' . number_format($p,1,',','.') . '%</span></div></td>';
}

/** Render sel kolom "Catatan" — link ke catatan_wilayah.php, isi teks kalau ada, "+ Catatan" kalau belum */
function renderCatatanCell(string $kode, string $level, string $nama, array $catatanMap, string $backUrl): string {
    $url = BASE_URL . '/catatan_wilayah.php?kode=' . urlencode($kode) . '&level=' . urlencode($level)
         . '&nama=' . urlencode($nama) . '&back=' . urlencode($backUrl);
    $ada = $catatanMap[$kode]['catatan'] ?? null;
    if ($ada) {
        $ringkas = strlen($ada) > 28 ? substr($ada, 0, 28) . '…' : $ada;
        return '<td style="text-align:left;max-width:140px"><a href="' . htmlspecialchars($url) . '" title="' . htmlspecialchars($ada) . '" style="color:#B45309;background:#FEF3C7;border-radius:5px;padding:2px 7px;font-size:11px;text-decoration:none;display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><i class="ti ti-note" style="font-size:11px"></i> ' . htmlspecialchars($ringkas) . '</a></td>';
    }
    return '<td style="text-align:left"><a href="' . htmlspecialchars($url) . '" style="color:var(--text2);font-size:11px;text-decoration:none;border-bottom:1px dotted var(--text2)">+ Catatan</a></td>';
}

/**
 * Realisasi Pertanian & Non-Pertanian, dari 2 sheet berbeda: proporsi_pertanian
 * (khusus pertanian) dan keseluruhan_usaha (total gabungan). Non-Pertanian
 * dihitung dari selisih (Total - Pertanian), karena sejak Agustus 2026 sheet
 * proporsi_pertanian gak punya lagi kolom Non-Pertaniannya sendiri.
 * Return: ['pertanian'=>int, 'nonPertanian'=>int]
 */
function hitungRealisasiUsaha(array $dp, array $du): array {
    $pertanian = n($dp['usaha_ditemukan_pertanian'] ?? 0) + n($dp['usaha_baru_pertanian'] ?? 0)
               + n($dp['usaha_keluarga_ditemukan_pertanian'] ?? 0) + n($dp['usaha_keluarga_baru_pertanian'] ?? 0);
    $total = n($du['usaha_ditemukan'] ?? 0) + n($du['usaha_baru'] ?? 0)
           + n($du['usaha_keluarga_ditemukan'] ?? 0) + n($du['usaha_keluarga_baru'] ?? 0);
    // max(0, ...) jaga2 kalau 2 sheet ini gak sinkron sempurna (mis. salah satu
    // sheet gak diimport lengkap di tanggal itu) biar gak muncul angka minus.
    $nonPertanian = max(0, $total - $pertanian);
    return ['pertanian' => $pertanian, 'nonPertanian' => $nonPertanian];
}

$db = getDB();

require_once __DIR__ . '/lib/sls_import_migrate.php';
migrateSlsImportTables($db); // pastiin tabel catatan_wilayah ada
$catatanWilayahMap = slsFetchCatatanWilayah($db);
$currentUrl = BASE_URL . '/dashboard_wilayah.php?' . http_build_query($_GET); // buat "back" link dari halaman catatan

$dates = $db->query("SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC")
            ->fetchAll(PDO::FETCH_COLUMN);
$tanggal = $_GET['tanggal'] ?? ($dates[0] ?? '');
$kelurahanDetail = $_GET['kelurahan'] ?? null; // drill-down tingkat 1: kelurahan -> daftar SLS
$slsDetail       = $_GET['sls'] ?? null;       // drill-down tingkat 2: SLS -> daftar Sub-SLS
$pplEmail        = $_GET['ppl_email'] ?? null; // mode dari ppl_dashboard.php: filter wilayah kerja 1 PPL
$pplNama         = $_GET['ppl_nama'] ?? null;  // fallback identitas kalau PPL gak punya email

$byKec = []; // kecKode => ['nama'=>, 'rows'=>[kelKode=>row,...]]
$totAll = ['tk'=>0,'rk'=>0,'tu'=>0,'ru'=>0,'tp'=>0,'rp'=>0];
$detailRows = []; // dipakai kalau $kelurahanDetail keisi (level SLS)
$detailTotal = ['tk'=>null,'rk'=>0,'tu'=>null,'ru'=>0,'tp'=>null,'rp'=>0];
$namaKelurahanDetail = null;
$subslsRows = []; // dipakai kalau $slsDetail ATAU $pplEmail/$pplNama keisi (level Sub-SLS)
$namaSlsDetail = null;
$namaPplDetail = null;

if ($tanggal && ($pplEmail || $pplNama)) {
    // ── Mode dari ppl_dashboard.php: cuma tampilin Sub-SLS yg dikerjakan 1 PPL ─
    $subKeluarga  = slsFetchSubslsRows($db, $tanggal, 'pemutakhiran_keluarga');
    $subPertanian = slsFetchSubslsRows($db, $tanggal, 'proporsi_pertanian');
    $subUsaha     = slsFetchSubslsRows($db, $tanggal, 'keseluruhan_usaha');
    $subMonSls    = slsFetchSubslsRows($db, $tanggal, 'monitoring_sls');

    $anySheet = $subPertanian ?: $subKeluarga;
    $kodeInfo = slsBuildKodeSlsPplPmlMap($db, $pplSubsls, array_keys($anySheet));

    // Saring kode yg identitas PPL-nya cocok (email diprioritaskan, krn paling akurat)
    $kodeMilikPpl = [];
    foreach ($kodeInfo as $kode16 => $info) {
        $cocok = $pplEmail
            ? (strtolower(trim($info['email'] ?? '')) === strtolower(trim($pplEmail)))
            : (strtolower(trim($info['ppl'])) === strtolower(trim($pplNama)));
        if ($cocok) { $kodeMilikPpl[] = $kode16; $namaPplDetail = $info['ppl']; }
    }

    sort($kodeMilikPpl);
    foreach ($kodeMilikPpl as $kodeSub) {
        $dk = $subKeluarga[$kodeSub]['data'] ?? [];
        $dp = $subPertanian[$kodeSub]['data'] ?? [];
        $du = $subUsaha[$kodeSub]['data'] ?? [];
        $rk = n($dk['ditemukan'] ?? 0) + n($dk['keluarga_baru'] ?? 0);
        $ru2 = hitungRealisasiUsaha($dp, $du);
        $ru = $ru2['nonPertanian'];
        $rp = $ru2['pertanian'];
        $slsSelesai = n($subMonSls[$kodeSub]['data']['jumlah_sls_selesai'] ?? 0);
        $subslsRows[] = [
            'kode' => $kodeSub,
            'nama' => $subKeluarga[$kodeSub]['nama'] ?? $subPertanian[$kodeSub]['nama'] ?? $namaSubsls[$kodeSub] ?? $kodeSub,
            'targetKeluarga' => TARGET_KELUARGA_SUBSLS[$kodeSub] ?? null, 'realisasiKeluarga' => $rk,
            'targetUsaha' => TARGET_USAHA_SUBSLS[$kodeSub] ?? null, 'realisasiUsaha' => $ru,
            'targetPertanian' => TARGET_PERTANIAN_SUBSLS[$kodeSub] ?? null, 'realisasiPertanian' => $rp,
            'slsSelesai' => $slsSelesai,
            'ppl' => $kodeInfo[$kodeSub]['ppl'] ?? '—',
        ];
    }
    if ($namaPplDetail === null) $namaPplDetail = $pplNama ?: $pplEmail; // fallback kalau gak ketemu kode-nya sama sekali

    // Ambil catatan PPL ini kalau ada (sama sumbernya kayak di ppl_dashboard.php)
    $catatanKeyDetail = slsIdentityKey($pplEmail, $namaPplDetail ?? $pplNama ?? '');
    $catatanPplDetail = slsFetchCatatanPpl($db)[$catatanKeyDetail]['catatan'] ?? null;
} elseif ($tanggal && $kelurahanDetail && $slsDetail) {
    // ── Mode drill-down tingkat 2: detail per Sub-SLS di 1 SLS ───────────
    $namaSlsDetail = $namaSls[$slsDetail] ?? $slsDetail;

    $subKeluarga  = slsFetchSubslsRows($db, $tanggal, 'pemutakhiran_keluarga', $slsDetail);
    $subPertanian = slsFetchSubslsRows($db, $tanggal, 'proporsi_pertanian', $slsDetail);
    $subUsaha     = slsFetchSubslsRows($db, $tanggal, 'keseluruhan_usaha', $slsDetail);

    $anySheet = $subPertanian ?: $subKeluarga;
    $kodeInfo = slsBuildKodeSlsPplPmlMap($db, $pplSubsls, array_keys($anySheet));

    $semuaKodeSub = array_unique(array_merge(array_keys($subKeluarga), array_keys($subPertanian)));
    sort($semuaKodeSub);
    foreach ($semuaKodeSub as $kodeSub) {
        $dk = $subKeluarga[$kodeSub]['data'] ?? [];
        $dp = $subPertanian[$kodeSub]['data'] ?? [];
        $du = $subUsaha[$kodeSub]['data'] ?? [];
        $rk = n($dk['ditemukan'] ?? 0) + n($dk['keluarga_baru'] ?? 0);
        $ru2 = hitungRealisasiUsaha($dp, $du);
        $ru = $ru2['nonPertanian'];
        $rp = $ru2['pertanian'];
        // Target sekarang BENERAN ADA per Sub-SLS (hardcode paling detail)
        $tk = TARGET_KELUARGA_SUBSLS[$kodeSub] ?? null;
        $tu = TARGET_USAHA_SUBSLS[$kodeSub] ?? null;
        $tp = TARGET_PERTANIAN_SUBSLS[$kodeSub] ?? null;
        $subslsRows[] = [
            'kode' => $kodeSub,
            'nama' => $subKeluarga[$kodeSub]['nama'] ?? $subPertanian[$kodeSub]['nama'] ?? $namaSubsls[$kodeSub] ?? $kodeSub,
            'targetKeluarga' => $tk, 'realisasiKeluarga' => $rk,
            'targetUsaha' => $tu, 'realisasiUsaha' => $ru,
            'targetPertanian' => $tp, 'realisasiPertanian' => $rp,
            'ppl' => $kodeInfo[$kodeSub]['ppl'] ?? '—',
        ];
    }
} elseif ($tanggal && $kelurahanDetail) {
    // ── Mode drill-down: detail per SLS di 1 kelurahan ───────────────────
    $namaKelurahanDetail = $namaKel[$kelurahanDetail] ?? $kelurahanDetail;

    // Target tetap dari hardcode per kelurahan (sama kayak di tabel utama) —
    // dipakai buat baris TOTAL di bawah, bukan per-baris SLS (gak ada datanya).
    $detailTotal['tk'] = TARGET_KELUARGA_KELURAHAN[$kelurahanDetail] ?? null;
    $detailTotal['tu'] = TARGET_USAHA_KELURAHAN[$kelurahanDetail] ?? null;
    $detailTotal['tp'] = TARGET_PERTANIAN_KELURAHAN[$kelurahanDetail] ?? null;

    $subslsKeluarga  = slsFetchSubslsRows($db, $tanggal, 'pemutakhiran_keluarga', $kelurahanDetail);
    $slsKeluarga     = slsAggregateToLevel($subslsKeluarga, 'sls', $namaSls);

    $subslsPertanian = slsFetchSubslsRows($db, $tanggal, 'proporsi_pertanian', $kelurahanDetail);
    $slsPertanian    = slsAggregateToLevel($subslsPertanian, 'sls', $namaSls);

    $subslsUsaha     = slsFetchSubslsRows($db, $tanggal, 'keseluruhan_usaha', $kelurahanDetail);
    $slsUsaha        = slsAggregateToLevel($subslsUsaha, 'sls', $namaSls);

    // PPL per Sub-SLS -> digabung jadi per-SLS (bisa lebih dari 1 nama kalau
    // SLS itu punya beberapa Sub-SLS dgn PPL beda-beda)
    $anySheet = $subslsPertanian ?: $subslsKeluarga;
    $kodeInfo = slsBuildKodeSlsPplPmlMap($db, $pplSubsls, array_keys($anySheet));
    $pplBySls = [];
    foreach ($kodeInfo as $kode16 => $info) {
        $pplBySls[substr($kode16, 0, 14)][$info['ppl']] = true;
    }

    // Target per SLS = jumlah dari Target semua Sub-SLS di bawahnya (bukan
    // kosong lagi) — hardcode-nya emang di level Sub-SLS, di sini di-rollup.
    $targetKeluargaBySls = [];
    $targetUsahaBySls = [];
    $targetPertanianBySls = [];
    foreach (TARGET_KELUARGA_SUBSLS as $kodeSub => $val) {
        $targetKeluargaBySls[substr($kodeSub, 0, 14)] = ($targetKeluargaBySls[substr($kodeSub, 0, 14)] ?? 0) + $val;
    }
    foreach (TARGET_USAHA_SUBSLS as $kodeSub => $val) {
        $targetUsahaBySls[substr($kodeSub, 0, 14)] = ($targetUsahaBySls[substr($kodeSub, 0, 14)] ?? 0) + $val;
    }
    foreach (TARGET_PERTANIAN_SUBSLS as $kodeSub => $val) {
        $targetPertanianBySls[substr($kodeSub, 0, 14)] = ($targetPertanianBySls[substr($kodeSub, 0, 14)] ?? 0) + $val;
    }

    $semuaKodeSls = array_unique(array_merge(array_keys($slsKeluarga), array_keys($slsPertanian)));
    sort($semuaKodeSls);
    foreach ($semuaKodeSls as $kodeSls) {
        $dk = $slsKeluarga[$kodeSls]['data'] ?? [];
        $dp = $slsPertanian[$kodeSls]['data'] ?? [];
        $du = $slsUsaha[$kodeSls]['data'] ?? [];
        $rk = n($dk['ditemukan'] ?? 0) + n($dk['keluarga_baru'] ?? 0);
        $ru2 = hitungRealisasiUsaha($dp, $du);
        $ru = $ru2['nonPertanian'];
        $rp = $ru2['pertanian'];
        $tk = $targetKeluargaBySls[$kodeSls] ?? null;
        $tu = $targetUsahaBySls[$kodeSls] ?? null;
        $tp = $targetPertanianBySls[$kodeSls] ?? null;
        $detailRows[] = [
            'kode' => $kodeSls,
            'nama' => $slsKeluarga[$kodeSls]['nama'] ?? $slsPertanian[$kodeSls]['nama'] ?? $namaSls[$kodeSls] ?? $kodeSls,
            'targetKeluarga' => $tk, 'realisasiKeluarga' => $rk,
            'targetUsaha' => $tu, 'realisasiUsaha' => $ru,
            'targetPertanian' => $tp, 'realisasiPertanian' => $rp,
            'ppl' => implode(', ', array_keys($pplBySls[$kodeSls] ?? [])) ?: '—',
        ];
        $detailTotal['rk'] += $rk; $detailTotal['ru'] += $ru; $detailTotal['rp'] += $rp;
    }
}

if ($tanggal && !$kelurahanDetail) {
    $subslsKeluarga  = slsFetchSubslsRows($db, $tanggal, 'pemutakhiran_keluarga');
    $rollupKeluarga  = slsAggregateToLevel($subslsKeluarga, 'kelurahan', $namaKel);

    $subslsPertanian = slsFetchSubslsRows($db, $tanggal, 'proporsi_pertanian');
    $rollupPertanian = slsAggregateToLevel($subslsPertanian, 'kelurahan', $namaKel);

    $subslsUsaha     = slsFetchSubslsRows($db, $tanggal, 'keseluruhan_usaha');
    $rollupUsaha     = slsAggregateToLevel($subslsUsaha, 'kelurahan', $namaKel);

    $semuaKode = array_unique(array_merge(
        array_keys($rollupKeluarga), array_keys($rollupPertanian),
        array_keys(TARGET_KELUARGA_KELURAHAN), array_keys(TARGET_PERTANIAN_KELURAHAN)
    ));

    foreach ($semuaKode as $kode) {
        $kecKode = substr($kode, 0, 7);
        $namaWil = $rollupKeluarga[$kode]['nama'] ?? $rollupPertanian[$kode]['nama'] ?? $namaKel[$kode] ?? $kode;
        $namaKecamatan = $namaKec[$kecKode] ?? $kecKode;

        $dk = $rollupKeluarga[$kode]['data'] ?? [];
        $realisasiKeluarga = n($dk['ditemukan'] ?? 0) + n($dk['keluarga_baru'] ?? 0);
        $targetKeluarga = TARGET_KELUARGA_KELURAHAN[$kode] ?? null;

        // Target Pertanian & Target Usaha: HARDCODE per kelurahan (lihat
        // lib/dtsen_target.php) — bukan dihitung dari data import harian,
        // karena sheet yang dibutuhkan gak selalu ada di tiap file yg diupload.
        $targetPertanian = TARGET_PERTANIAN_KELURAHAN[$kode] ?? null;
        $targetUsaha = TARGET_USAHA_KELURAHAN[$kode] ?? null;

        // Realisasi tetap dinamis (emang ini yg mestinya update tiap hari).
        // Non-Pertanian = Total(sheet Keseluruhan Usaha) - Pertanian(sheet
        // Proporsi Pertanian), krn sheet Proporsi Pertanian sekarang gak
        // punya lagi kolom Non-Pertaniannya sendiri (skema Agu 2026).
        $dp = $rollupPertanian[$kode]['data'] ?? [];
        $du = $rollupUsaha[$kode]['data'] ?? [];
        $ru = hitungRealisasiUsaha($dp, $du);
        $realisasiUsaha = $ru['nonPertanian'];
        $realisasiPertanian = $ru['pertanian'];

        $namaDikenal = isset($namaKel[$kode]); // false kalau kode gak kekenali sbg kelurahan valid

        $kecDikenal = isset($namaKec[$kecKode]); // false kalau kode kecamatan gak dikenal (mis. "1376000" TIDAK DIKETAHUI)
        if (!isset($byKec[$kecKode])) $byKec[$kecKode] = ['nama' => $namaKecamatan, 'kecDikenal' => $kecDikenal, 'rows' => []];
        $byKec[$kecKode]['rows'][$kode] = [
            'nama' => $namaWil, 'namaDikenal' => $namaDikenal,
            'targetKeluarga' => $targetKeluarga, 'realisasiKeluarga' => $realisasiKeluarga,
            'targetUsaha' => $targetUsaha, 'realisasiUsaha' => $realisasiUsaha,
            'targetPertanian' => $targetPertanian, 'realisasiPertanian' => $realisasiPertanian,
        ];

        $totAll['tk'] += n($targetKeluarga); $totAll['rk'] += $realisasiKeluarga;
        $totAll['tu'] += n($targetUsaha); $totAll['ru'] += $realisasiUsaha;
        $totAll['tp'] += n($targetPertanian); $totAll['rp'] += $realisasiPertanian;
    }

    // Urutan kecamatan: sama kayak tab Wilayah di dashboard/index.php (ascending
    // kode kecamatan: Barat, Selatan, Timur, Utara, Lamposi Tigo Nagori) —
    // kecamatan yang gak dikenal (mis. "1376000" TIDAK DIKETAHUI, kode bucket
    // BPS buat data yg gak ke-geotag) ditaruh paling akhir, BUKAN ikut urutan
    // angka biasa (soalnya "1376000" < "1376010" jadi kalau ascending biasa
    // dia malah nongol di paling atas).
    uksort($byKec, function($a, $b) use ($byKec) {
        $ka = $byKec[$a]['kecDikenal']; $kb = $byKec[$b]['kecDikenal'];
        if ($ka !== $kb) return $ka ? -1 : 1;
        return strcmp($a, $b);
    });
    foreach ($byKec as $kecKode => &$kec) {
        uksort($kec['rows'], function($a, $b) use ($kec) {
            $ra = $kec['rows'][$a]; $rb = $kec['rows'][$b];
            if ($ra['namaDikenal'] !== $rb['namaDikenal']) return $ra['namaDikenal'] ? -1 : 1;
            return strcmp($a, $b); // sama2 dikenal/gak dikenal -> urut kode ascending
        });
    }
    unset($kec);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Wilayah — SELARAS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--o-lt:#FDE8DD;--navy:#1A2744;--ok:#16A34A;--ok-lt:#DCFCE7;--red:#DC2626;
--text:#1A1A2E;--text2:#6B7280;--border:#E4E4E4;--bg:#F5F6FA;--grad:linear-gradient(135deg,#E8560A,#F5A623)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-right{display:flex;align-items:center;gap:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o)}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-user{color:rgba(255,255,255,.7);font-size:12px}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;text-decoration:none}
.main{max-width:1200px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:20px}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:18px}
.frow{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
label{font-size:10.5px;font-weight:700;color:var(--text2);text-transform:uppercase;display:block;margin-bottom:5px}
select{padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit}
.btn{padding:9px 16px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:var(--grad);color:#fff}
.legend{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:11.5px;color:var(--text2);margin-bottom:12px;padding-bottom:12px;border-bottom:1px dashed var(--border)}
.legend b{color:var(--navy)}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{padding:8px 10px;text-align:right;font-size:10.5px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap}
th.lbl{text-align:left}
th.grp{text-align:center;background:var(--o-lt);color:#C94508;border-left:2px solid #fff}
td{padding:7px 10px;border-bottom:1px solid #F0F0F0;text-align:right}
td.lbl{text-align:left;font-weight:600;color:var(--navy)}
tr:hover td{background:#FDFAF6}
.kec-row td{background:var(--navy);color:#fff;font-weight:700;font-size:12px;padding:8px 10px;text-align:left}
.kec-cnt{font-weight:400;color:rgba(255,255,255,.6);font-size:11px}
tr.subtotal-row td{background:#EEF1F7;color:var(--navy);font-weight:700}
tr.total-row td{background:var(--o-lt);color:#C94508;font-weight:800;border-top:2px solid var(--o)}
.prog-wrap{display:flex;align-items:center;gap:6px;justify-content:flex-end}
.prog-bg{width:50px;background:var(--bg);border-radius:4px;height:8px;overflow:hidden}
.prog-fill{height:8px;border-radius:4px}
.no-data{text-align:center;padding:40px;color:var(--text2)}
.tw{overflow-x:auto}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div><span class="nav-title">SELARAS</span><span class="nav-sub">Dashboard Wilayah</span></div>
  </div>
  <div class="nav-right">
    <a href="<?= BASE_URL ?>/dashboard/" class="nav-btn">← Dashboard</a>
    <a href="<?= BASE_URL ?>/ppl_dashboard.php" class="nav-btn">Rekap PPL</a>
    <?php if ($isLoggedIn): ?>
      <span class="nav-user"><?= htmlspecialchars($namaUser) ?></span>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-btn">Keluar</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/login.php" class="nav-btn"><i class="ti ti-login-2"></i> Masuk</a>
    <?php endif ?>
  </div>
</nav>

<div class="main">
  <div class="page-title">Dashboard Wilayah</div>
  <div class="page-sub">Target vs Realisasi per kelurahan, dikelompokkan per kecamatan.</div>

  <?php if (!$dates): ?>
    <div class="panel"><div class="no-data">Belum ada data yang diimport. Upload dulu lewat <a href="<?= BASE_URL ?>/import_sls.php">import_sls.php</a>.</div></div>
  <?php else: ?>

  <div class="panel">
    <form method="GET">
      <div class="frow">
        <div>
          <label>Tanggal snapshot</label>
          <select name="tanggal" onchange="this.form.submit()">
            <?php foreach ($dates as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>" <?= $d===$tanggal?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <button type="submit" class="btn">Tampilkan</button>
      </div>
    </form>
  </div>

  <?php if ($pplEmail || $pplNama): ?>

  <div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/ppl_dashboard.php" class="nav-btn" style="background:var(--bg);color:var(--navy);border-color:var(--border)">← Kembali ke Rekap PPL</a>
    </div>
    <div class="section-title" style="margin-top:12px"><i class="ti ti-user"></i> Wilayah Kerja — <?= htmlspecialchars($namaPplDetail) ?></div>
    <?php if ($catatanPplDetail): ?>
      <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:10px 14px;margin:8px 0;font-size:12.5px;color:#92400E;display:flex;gap:8px;align-items:start">
        <i class="ti ti-note" style="flex-shrink:0;margin-top:1px"></i>
        <span><b>Catatan:</b> <?= htmlspecialchars($catatanPplDetail) ?></span>
      </div>
    <?php endif ?>
    <div class="section-note">Semua Sub-SLS yang dikerjakan PPL ini, dengan Target & Realisasi Keluarga/Usaha/Usaha Pertanian.</div>
    <?php if (!$subslsRows): ?>
      <div class="no-data">Belum ada Sub-SLS yang ke-mapping ke PPL ini utk tanggal yang dipilih.</div>
    <?php else: ?>
    <div class="tw">
    <table>
      <thead>
        <tr>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Sub-SLS</th>
          <th class="grp" colspan="3">Keluarga</th>
          <th class="grp" colspan="3">Usaha</th>
          <th class="grp" colspan="3" style="background:#DCFCE7;color:#16A34A">Usaha Pertanian</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">SLS Selesai</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Catatan</th>
        </tr>
        <tr>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $ppt = ['tk'=>0,'rk'=>0,'tu'=>0,'ru'=>0,'tp'=>0,'rp'=>0,'sls'=>0];
          foreach ($subslsRows as $sr):
            $ppk = pct($sr['targetKeluarga'], $sr['realisasiKeluarga']);
            $ppu = pct($sr['targetUsaha'], $sr['realisasiUsaha']);
            $ppp = pct($sr['targetPertanian'], $sr['realisasiPertanian']);
            $ppt['tk'] += n($sr['targetKeluarga']); $ppt['rk'] += $sr['realisasiKeluarga'];
            $ppt['tu'] += n($sr['targetUsaha']); $ppt['ru'] += $sr['realisasiUsaha'];
            $ppt['tp'] += n($sr['targetPertanian']); $ppt['rp'] += $sr['realisasiPertanian'];
            $ppt['sls'] += $sr['slsSelesai'];
        ?>
        <tr>
          <td class="lbl"><?= htmlspecialchars($sr['nama']) ?> <span style="color:var(--text2);font-family:monospace;font-size:10px">(<?= htmlspecialchars($sr['kode']) ?>)</span></td>
          <?= selPct($ppk, $sr['targetKeluarga']!==null ? number_format($sr['targetKeluarga'],0,',','.') : '—', number_format($sr['realisasiKeluarga'],0,',','.')) ?>
          <?= selPct($ppu, $sr['targetUsaha']!==null ? number_format($sr['targetUsaha'],0,',','.') : '—', number_format($sr['realisasiUsaha'],0,',','.')) ?>
          <?= selPct($ppp, $sr['targetPertanian']!==null ? number_format($sr['targetPertanian'],0,',','.') : '—', number_format($sr['realisasiPertanian'],0,',','.')) ?>
          <td><?= $sr['slsSelesai'] ? '<span style="color:var(--ok);font-weight:700">✓ Selesai</span>' : '<span style="color:var(--text2)">Belum</span>' ?></td>
          <?= renderCatatanCell($sr['kode'], 'subsls', $sr['nama'], $catatanWilayahMap, $currentUrl) ?>
        </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php $ptk = pct($ppt['tk'], $ppt['rk']); $ptu = pct($ppt['tu'], $ppt['ru']); $ptp = pct($ppt['tp'], $ppt['rp']); ?>
        <tr class="total-row">
          <td class="lbl">Total <?= htmlspecialchars($namaPplDetail) ?></td>
          <?= selPct($ptk, number_format($ppt['tk'],0,',','.'), number_format($ppt['rk'],0,',','.')) ?>
          <?= selPct($ptu, number_format($ppt['tu'],0,',','.'), number_format($ppt['ru'],0,',','.')) ?>
          <?= selPct($ptp, number_format($ppt['tp'],0,',','.'), number_format($ppt['rp'],0,',','.')) ?>
          <td><?= $ppt['sls'] ?> / <?= count($subslsRows) ?></td>
          <td>—</td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php elseif ($kelurahanDetail && $slsDetail): ?>

  <div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
      <a href="?tanggal=<?= urlencode($tanggal) ?>&kelurahan=<?= urlencode($kelurahanDetail) ?>" class="nav-btn" style="background:var(--bg);color:var(--navy);border-color:var(--border)">← Kembali ke daftar SLS</a>
    </div>
    <div class="section-title" style="margin-top:12px"><i class="ti ti-map-pin"></i> Detail Sub-SLS — <?= htmlspecialchars($namaSlsDetail) ?></div>
    <div class="section-note">Target Keluarga, Usaha, dan Usaha Pertanian sekarang semuanya ada per Sub-SLS (level paling detail).</div>
    <?php if (!$subslsRows): ?>
      <div class="no-data">Belum ada data Sub-SLS di SLS ini utk tanggal yang dipilih.</div>
    <?php else: ?>
    <div class="tw">
    <table>
      <thead>
        <tr>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Sub-SLS</th>
          <th class="grp" colspan="3">Keluarga</th>
          <th class="grp" colspan="3">Usaha</th>
          <th class="grp" colspan="3" style="background:#DCFCE7;color:#16A34A">Usaha Pertanian</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">PPL</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Catatan</th>
        </tr>
        <tr>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $sst = ['tk'=>0,'rk'=>0,'tu'=>0,'ru'=>0,'tp'=>0,'rp'=>0];
          foreach ($subslsRows as $sr):
            $psk = pct($sr['targetKeluarga'], $sr['realisasiKeluarga']);
            $psu = pct($sr['targetUsaha'], $sr['realisasiUsaha']);
            $psp = pct($sr['targetPertanian'], $sr['realisasiPertanian']);
            $sst['tk'] += n($sr['targetKeluarga']); $sst['rk'] += $sr['realisasiKeluarga'];
            $sst['tu'] += n($sr['targetUsaha']); $sst['ru'] += $sr['realisasiUsaha'];
            $sst['tp'] += n($sr['targetPertanian']); $sst['rp'] += $sr['realisasiPertanian'];
        ?>
        <tr>
          <td class="lbl"><?= htmlspecialchars($sr['nama']) ?> <span style="color:var(--text2);font-family:monospace;font-size:10px">(<?= htmlspecialchars($sr['kode']) ?>)</span></td>
          <?= selPct($psk, $sr['targetKeluarga']!==null ? number_format($sr['targetKeluarga'],0,',','.') : '—', number_format($sr['realisasiKeluarga'],0,',','.')) ?>
          <?= selPct($psu, $sr['targetUsaha']!==null ? number_format($sr['targetUsaha'],0,',','.') : '—', number_format($sr['realisasiUsaha'],0,',','.')) ?>
          <?= selPct($psp, $sr['targetPertanian']!==null ? number_format($sr['targetPertanian'],0,',','.') : '—', number_format($sr['realisasiPertanian'],0,',','.')) ?>
          <td class="lbl"><?= htmlspecialchars($sr['ppl']) ?></td>
          <?= renderCatatanCell($sr['kode'], 'subsls', $sr['nama'], $catatanWilayahMap, $currentUrl) ?>
        </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php $ssk = pct($sst['tk'], $sst['rk']); $ssu = pct($sst['tu'], $sst['ru']); $ssp = pct($sst['tp'], $sst['rp']); ?>
        <tr class="total-row">
          <td class="lbl">Total <?= htmlspecialchars($namaSlsDetail) ?></td>
          <?= selPct($ssk, number_format($sst['tk'],0,',','.'), number_format($sst['rk'],0,',','.')) ?>
          <?= selPct($ssu, number_format($sst['tu'],0,',','.'), number_format($sst['ru'],0,',','.')) ?>
          <?= selPct($ssp, number_format($sst['tp'],0,',','.'), number_format($sst['rp'],0,',','.')) ?>
          <td>—</td>
          <td>—</td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php elseif ($kelurahanDetail): ?>

  <div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
      <a href="?tanggal=<?= urlencode($tanggal) ?>" class="nav-btn" style="background:var(--bg);color:var(--navy);border-color:var(--border)">← Kembali ke daftar kelurahan</a>
    </div>
    <div class="section-title" style="margin-top:12px"><i class="ti ti-map-pin"></i> Detail SLS — <?= htmlspecialchars($namaKelurahanDetail) ?></div>
    <div class="section-note">Target Keluarga, Usaha, dan Usaha Pertanian = jumlah dari semua Sub-SLS di bawahnya. Klik nama SLS buat lihat detail sampai Sub-SLS.</div>
    <?php if (!$detailRows): ?>
      <div class="no-data">Belum ada data SLS di kelurahan ini utk tanggal yang dipilih.</div>
    <?php else: ?>
    <div class="tw">
    <table>
      <thead>
        <tr>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">SLS</th>
          <th class="grp" colspan="3">Keluarga</th>
          <th class="grp" colspan="3">Usaha</th>
          <th class="grp" colspan="3" style="background:#DCFCE7;color:#16A34A">Usaha Pertanian</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">PPL</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Catatan</th>
        </tr>
        <tr>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detailRows as $dr):
          $pdk = pct($dr['targetKeluarga'], $dr['realisasiKeluarga']);
          $pdu = pct($dr['targetUsaha'], $dr['realisasiUsaha']);
          $pdp = pct($dr['targetPertanian'], $dr['realisasiPertanian']);
        ?>
        <tr>
          <td class="lbl"><a href="?tanggal=<?= urlencode($tanggal) ?>&kelurahan=<?= urlencode($kelurahanDetail) ?>&sls=<?= urlencode($dr['kode']) ?>" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--o)"><?= htmlspecialchars($dr['nama']) ?></a> <span style="color:var(--text2);font-family:monospace;font-size:10px">(<?= htmlspecialchars($dr['kode']) ?>)</span></td>
          <?= selPct($pdk, $dr['targetKeluarga']!==null ? number_format($dr['targetKeluarga'],0,',','.') : '—', number_format($dr['realisasiKeluarga'],0,',','.')) ?>
          <?= selPct($pdu, $dr['targetUsaha']!==null ? number_format($dr['targetUsaha'],0,',','.') : '—', number_format($dr['realisasiUsaha'],0,',','.')) ?>
          <?= selPct($pdp, $dr['targetPertanian']!==null ? number_format($dr['targetPertanian'],0,',','.') : '—', number_format($dr['realisasiPertanian'],0,',','.')) ?>
          <td class="lbl"><?= htmlspecialchars($dr['ppl']) ?></td>
          <?= renderCatatanCell($dr['kode'], 'sls', $dr['nama'], $catatanWilayahMap, $currentUrl) ?>
        </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php
          $dtpk = pct($detailTotal['tk'], $detailTotal['rk']);
          $dtpu = pct($detailTotal['tu'], $detailTotal['ru']);
          $dtpp = pct($detailTotal['tp'], $detailTotal['rp']);
        ?>
        <tr class="total-row">
          <td class="lbl">Total <?= htmlspecialchars($namaKelurahanDetail) ?></td>
          <?= selPct($dtpk, $detailTotal['tk']!==null ? number_format($detailTotal['tk'],0,',','.') : '—', number_format($detailTotal['rk'],0,',','.')) ?>
          <?= selPct($dtpu, $detailTotal['tu']!==null ? number_format($detailTotal['tu'],0,',','.') : '—', number_format($detailTotal['ru'],0,',','.')) ?>
          <?= selPct($dtpp, $detailTotal['tp']!==null ? number_format($detailTotal['tp'],0,',','.') : '—', number_format($detailTotal['rp'],0,',','.')) ?>
          <td>—</td>
          <td>—</td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php else: ?>

  <div class="panel">
    <div class="legend">
      <span><b>Keluarga</b>: Target dari data DTSEN (tetap), Realisasi = ditemukan+baru</span>
      <span><b>Usaha</b>: Target = 716 × jumlah PPL di kelurahan itu, Realisasi = seluruh usaha ditemukan+baru</span>
      <span><b>Usaha Pertanian</b>: Target = UTP Subsektor Target (ST2023), Realisasi = usaha pertanian ditemukan+baru</span>
    </div>
    <?php if (!$byKec): ?>
      <div class="no-data">Belum ada data.</div>
    <?php else: ?>
    <div class="tw">
    <table>
      <thead>
        <tr>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Kelurahan</th>
          <th class="grp" colspan="3">Keluarga</th>
          <th class="grp" colspan="3">Usaha</th>
          <th class="grp" colspan="3" style="background:#DCFCE7;color:#16A34A">Usaha Pertanian</th>
          <th class="lbl" rowspan="2" style="vertical-align:bottom">Catatan</th>
        </tr>
        <tr>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
          <th>Target</th><th>Realisasi</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $tpk = pct($totAll['tk'], $totAll['rk']); $tpu = pct($totAll['tu'], $totAll['ru']); $tpp = pct($totAll['tp'], $totAll['rp']);
        ?>
        <tr class="total-row">
          <td class="lbl">TOTAL SE KOTA PAYAKUMBUH</td>
          <?= selPct($tpk, number_format($totAll['tk'],0,',','.'), number_format($totAll['rk'],0,',','.')) ?>
          <?= selPct($tpu, number_format($totAll['tu'],0,',','.'), number_format($totAll['ru'],0,',','.')) ?>
          <?= selPct($tpp, number_format($totAll['tp'],0,',','.'), number_format($totAll['rp'],0,',','.')) ?>
          <td>—</td>
        </tr>
        <?php foreach ($byKec as $kecKode => $kec):
          $st = ['tk'=>0,'rk'=>0,'tu'=>0,'ru'=>0,'tp'=>0,'rp'=>0];
        ?>
        <tr class="kec-row"><td colspan="11"><?= htmlspecialchars($kec['nama']) ?> <span class="kec-cnt">(<?= count($kec['rows']) ?> kelurahan)</span></td></tr>
        <?php foreach ($kec['rows'] as $kode => $r):
          $pk = pct($r['targetKeluarga'], $r['realisasiKeluarga']);
          $pu = pct($r['targetUsaha'], $r['realisasiUsaha']);
          $pp = pct($r['targetPertanian'], $r['realisasiPertanian']);
          $st['tk'] += n($r['targetKeluarga']); $st['rk'] += $r['realisasiKeluarga'];
          $st['tu'] += n($r['targetUsaha']); $st['ru'] += $r['realisasiUsaha'];
          $st['tp'] += n($r['targetPertanian']); $st['rp'] += $r['realisasiPertanian'];
        ?>
        <tr>
          <td class="lbl" style="padding-left:22px"><a href="?tanggal=<?= urlencode($tanggal) ?>&kelurahan=<?= urlencode($kode) ?>" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--o)"><?= htmlspecialchars($r['nama']) ?></a></td>
          <?= selPct($pk, $r['targetKeluarga']!==null ? number_format($r['targetKeluarga'],0,',','.') : '—', number_format($r['realisasiKeluarga'],0,',','.')) ?>
          <?= selPct($pu, $r['targetUsaha']!==null ? number_format($r['targetUsaha'],0,',','.') : '—', number_format($r['realisasiUsaha'],0,',','.')) ?>
          <?= selPct($pp, $r['targetPertanian']!==null ? number_format($r['targetPertanian'],0,',','.') : '—', number_format($r['realisasiPertanian'],0,',','.')) ?>
          <?= renderCatatanCell($kode, 'kelurahan', $r['nama'], $catatanWilayahMap, $currentUrl) ?>
        </tr>
        <?php endforeach ?>
        <?php
          $spk = pct($st['tk'], $st['rk']); $spu = pct($st['tu'], $st['ru']); $spp = pct($st['tp'], $st['rp']);
        ?>
        <tr class="subtotal-row">
          <td class="lbl">Subtotal <?= htmlspecialchars($kec['nama']) ?></td>
          <?= selPct($spk, number_format($st['tk'],0,',','.'), number_format($st['rk'],0,',','.')) ?>
          <?= selPct($spu, number_format($st['tu'],0,',','.'), number_format($st['ru'],0,',','.')) ?>
          <?= selPct($spp, number_format($st['tp'],0,',','.'), number_format($st['rp'],0,',','.')) ?>
          <td>—</td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php endif ?>

  <?php endif ?>
</div>
</body>
</html>
