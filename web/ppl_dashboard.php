<?php
// ============================================================
// ppl_dashboard.php — Rekap data usaha per PPL, dikelompokkan per PML.
// Mapping PPL/PML per kode Sub-SLS diambil dari tabel `assignment`
// (SIHARAU Detail, email per unit usaha) JOIN `mapping_nama` (email->nama+pml)
// — sumber otoritatif. Fallback ke wilayah_lookup.php (Master_Wilayah_kerja.xlsx)
// untuk kode yang belum pernah di-scan SIHARAU Detail.
// Sementara di root, sama seperti import_sls.php & cek_sls.php.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sls_aggregate.php';
require_once __DIR__ . '/lib/wilayah_lookup.php'; // $pplSubsls (fallback) + $namaSubsls, $namaKel

// Halaman publik — tidak perlu login, tapi tetap deteksi status login
// buat nampilin tombol Masuk/Keluar & menu admin (Import, Mapping) di navbar.
session_name(SESSION_NAME);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && (($_SESSION['role'] ?? '') === 'admin');
$namaUser   = $_SESSION['nama'] ?? $_SESSION['username'] ?? '';

const SLS_TARGET_PER_PPL = 716; // target tetap per PPL (sesuai arahan)

function n($v) { return $v ?? 0; }

/** Gradasi warna merah->hijau berdasar rasio $value/$cap (di-clamp 0..1). Return inline style, atau '' kalau cap gak valid. */
function totalGradientStyle($value, $cap) {
    if (!$cap || $cap <= 0) return '';
    $ratio = max(0, min(1, $value / $cap));
    $r = round(220 + (22  - 220) * $ratio);
    $g = round(38  + (163 - 38)  * $ratio);
    $b = round(38  + (74  - 38)  * $ratio);
    return "background-color:rgba($r,$g,$b,.14);color:rgb($r,$g,$b);font-weight:800;border-radius:6px";
}

/** Hitung 1 baris metrik dari data 1 PPL (atau hasil sum grup) */
function slsRowMetrics(array $sheets, int $jumlahPpl) {
    $up = $sheets['usaha_perusahaan']   ?? [];
    $uk = $sheets['usaha_keluarga']     ?? [];
    $pp = $sheets['proporsi_pertanian'] ?? [];
    $pk = $sheets['pemutakhiran_keluarga'] ?? [];
    $kk = $sheets['keluarga_khusus']    ?? [];
    $ms = $sheets['monitoring_sls']     ?? [];
    $du = $sheets['keseluruhan_usaha']  ?? [];

    // SLS Selesai — dari sheet Monitoring SLS terpisah (status selesai/belum
    // per Sub-SLS, bukan realisasi Keluarga/Usaha)
    $slsSelesai = n($ms['jumlah_sls_selesai'] ?? 0);

    // Usaha (BKU + Keluarga) dipecah Pertanian vs Non Pertanian.
    // CATATAN SKEMA (Agu 2026): sheet proporsi_pertanian udah gak punya lagi
    // kolom Non-Pertaniannya sendiri, jadi NONTANI dihitung dari selisih:
    // Total (sheet keseluruhan_usaha) dikurangi Pertanian (sheet proporsi_pertanian).
    $pertanianOk = n($pp['usaha_ditemukan_pertanian'] ?? 0) + n($pp['usaha_baru_pertanian'] ?? 0)
                 + n($pp['usaha_keluarga_ditemukan_pertanian'] ?? 0) + n($pp['usaha_keluarga_baru_pertanian'] ?? 0);
    $totalUsahaSemua = n($du['usaha_ditemukan'] ?? 0) + n($du['usaha_baru'] ?? 0)
                      + n($du['usaha_keluarga_ditemukan'] ?? 0) + n($du['usaha_keluarga_baru'] ?? 0);
    $nonPertanianOk = max(0, $totalUsahaSemua - $pertanianOk);

    // Bermasalah usaha (BKU+Keluarga) — dari sheet usaha_perusahaan/usaha_keluarga
    $bkuBrm = n($up['ganda'] ?? 0) + n($up['tutup'] ?? 0) + n($up['tidak_ditemukan'] ?? 0);
    $klgBrm = n($uk['ganda'] ?? 0) + n($uk['tutup'] ?? 0) + n($uk['tidak_ditemukan'] ?? 0);

    // Pendataan Keluarga (rumah tangga) — sheet & sumber data terpisah dari usaha
    $kelOk  = n($pk['ditemukan'] ?? 0) + n($pk['keluarga_baru'] ?? 0);
    $kelBrm = n($pk['meninggal'] ?? 0) + n($pk['tidak_eligible'] ?? 0) + n($pk['tidak_ditemukan'] ?? 0) + n($pk['nonrespon'] ?? 0);

    // Keluarga Khusus — sheet terpisah sejak skema Juli 2026
    $khususPpl    = n($kk['khusus_hasil_pendataan_ppl'] ?? 0); // ditemukan/dilaporkan PPL
    $khususDidata = n($kk['khusus_didata'] ?? 0);              // sudah selesai didata

    // TOTAL = Usaha Pertanian + Usaha Non Pertanian + Keluarga (rumah tangga)
    $totOk   = $pertanianOk + $nonPertanianOk + $kelOk;
    $totBrm  = $bkuBrm + $klgBrm + $kelBrm;

    $target  = SLS_TARGET_PER_PPL * $jumlahPpl;
    $pct     = $target > 0 ? round($totOk / $target * 100, 1) : 0;

    return [
        'target' => $target,
        'pertanianOk' => $pertanianOk, 'nonPertanianOk' => $nonPertanianOk,
        'kelOk' => $kelOk, 'totOk' => $totOk, 'totBrm' => $totBrm, 'pct' => $pct,
        'khususPpl' => $khususPpl, 'khususDidata' => $khususDidata,
        'slsSelesai' => $slsSelesai,
    ];
}

/** Jumlahkan sheets dari beberapa PPL (buat subtotal PML / total kota) */
function slsSumSheets(array $group) {
    $out = [];
    foreach ($group as $d) {
        foreach ($d['sheets'] as $sk => $fields) {
            foreach ($fields as $f => $v) $out[$sk][$f] = n($out[$sk][$f] ?? 0) + n($v);
        }
    }
    return $out;
}

$db = getDB();

require_once __DIR__ . '/lib/sls_import_migrate.php';
migrateSlsImportTables($db); // pastiin tabel mapping_korwil ada
$korwilMap = slsFetchKorwilMap($db); // pml_nama => korwil_nama
$catatanMap = slsFetchCatatanPpl($db); // identity_key => ['catatan'=>,...]
$currentUrl = BASE_URL . '/ppl_dashboard.php?' . http_build_query($_GET); // buat "back" link dari halaman catatan

$assignStats = slsFetchAssignmentPrelistSelesai($db); // email => ['prelist'=>,'selesai'=>,'selesaiDelta'=>], sekali aja (snapshot 2 tanggal terbaru progress)

/** Jumlahkan Prelist/Selesai/DeltaSelesai dari assignment utk sekumpulan identityKey (subtotal PML/total kota) */
function sumAssignStats(array $ids, array $identityInfo, array $assignStats) {
    $prelist = 0; $selesai = 0; $delta = 0; $any = false; $anyDelta = false;
    foreach ($ids as $id) {
        $email = $identityInfo[$id]['email'] ?? null;
        if ($email && isset($assignStats[strtolower($email)])) {
            $prelist += $assignStats[strtolower($email)]['prelist'];
            $selesai += $assignStats[strtolower($email)]['selesai'];
            $any = true;
            if ($assignStats[strtolower($email)]['selesaiDelta'] !== null) {
                $delta += $assignStats[strtolower($email)]['selesaiDelta'];
                $anyDelta = true;
            }
        }
    }
    return $any ? ['prelist' => $prelist, 'selesai' => $selesai, 'selesaiDelta' => $anyDelta ? $delta : null] : null;
}

/** Render sepasang sel Prelist/Selesai/%Prelist */
function renderPrelistCells($prelist, $selesai) {
    if ($prelist === null) return '<td>—</td><td>—</td><td>—</td>';
    $pct = $prelist > 0 ? round($selesai / $prelist * 100, 1) : 0;
    $cls = $pct >= 80 ? 'pct-ok' : ($pct >= 50 ? 'pct-warn' : 'pct-bad');
    return '<td>' . number_format($prelist,0,',','.') . '</td>'
         . '<td>' . number_format($selesai,0,',','.') . '</td>'
         . '<td class="' . $cls . '">' . number_format($pct,1,',','.') . '%</td>';
}

/** Render sel Delta Selesai (warna hijau/merah/abu, sama gaya kayak fmtDelta) */
function renderSelesaiDeltaCell($delta) {
    if ($delta === null) return '<td><span style="color:var(--text2)">—</span></td>';
    if ($delta > 0) return '<td><span style="color:var(--ok);font-weight:700">+' . number_format($delta,0,',','.') . '</span></td>';
    if ($delta < 0) return '<td><span style="color:var(--red,#DC2626);font-weight:700">' . number_format($delta,0,',','.') . '</span></td>';
    return '<td><span style="color:var(--text2)">0</span></td>';
}

$dates = $db->query("SELECT DISTINCT tanggal FROM sls_import_data ORDER BY tanggal DESC")
            ->fetchAll(PDO::FETCH_COLUMN);
$tanggal = $_GET['tanggal'] ?? ($dates[0] ?? '');
$q       = trim($_GET['q'] ?? '');

$byPpl = [];
$byPml = [];
$tanpaPpl = []; // daftar [kode, nama] Sub-SLS yang belum ada PPL sama sekali (buat breakdown)
$byPplPrev = []; // rekap tanggal sebelumnya (identityKey => ['totOk'=>, 'kelOk'=>]), buat hitung penambahan harian
$tanggalPrev = null;

if ($tanggal) {
    $sheetKeys = ['usaha_perusahaan', 'usaha_keluarga', 'proporsi_pertanian', 'pemutakhiran_keluarga', 'keluarga_khusus', 'monitoring_sls', 'keseluruhan_usaha'];
    $sheetsData = [];
    foreach ($sheetKeys as $sk) {
        $sheetsData[$sk] = slsFetchSubslsRows($db, $tanggal, $sk);
    }

    // Mapping otoritatif kode_sls -> ppl & pml (assignment 16digit > assignment 14digit > wilayah_lookup)
    $anySheet = $sheetsData['usaha_perusahaan'] ?: ($sheetsData['usaha_keluarga'] ?: $sheetsData['pemutakhiran_keluarga']);
    $kodeInfo = slsBuildKodeSlsPplPmlMap($db, $pplSubsls, array_keys($anySheet));

    // Susun kode->identitas PPL (BUKAN nama doang — 2 orang bisa punya nama sama
    // tapi email beda, jadi kunci utamanya email; nama cuma buat tampilan).
    $pplByKode  = [];   // kode16 => identityKey
    $identityInfo = []; // identityKey => ['nama'=>.., 'email'=>..|null]
    $pplToPml   = [];   // identityKey => nama PML
    foreach ($anySheet as $kode16 => $r) {
        if (isset($kodeInfo[$kode16])) {
            $info = $kodeInfo[$kode16];
            // Kalau ada email, itu identitas paling akurat. Kalau tidak (fallback
            // wilayah_lookup yang cuma punya nama), terpaksa pakai nama sbg identitas
            // — masih berisiko nabrak kalau ada 2 orang nama sama tanpa email diketahui.
            $identityKey = $info['email'] ?: ('noemail:' . strtolower(trim($info['ppl'])));
            $pplByKode[$kode16] = $identityKey;
            if (!isset($identityInfo[$identityKey])) $identityInfo[$identityKey] = ['nama' => $info['ppl'], 'email' => $info['email']];
            if (!isset($pplToPml[$identityKey])) $pplToPml[$identityKey] = $info['pml'];
        } else {
            $pplByKode[$kode16] = '(Belum ada PPL)';
            $identityInfo['(Belum ada PPL)'] = ['nama' => '(Belum ada PPL)', 'email' => null];
            $namaWil = $namaSubsls[$kode16] ?? $r['nama'] ?? $kode16;
            $tanpaPpl[] = ['kode' => $kode16, 'nama' => $namaWil];
        }
    }

    $byPpl = slsAggregateByPPL($sheetsData, $pplByKode); // key = identityKey, bukan nama

    if ($q !== '') {
        $byPpl = array_filter($byPpl, function($id) use ($q, $identityInfo) {
            $info = $identityInfo[$id] ?? ['nama' => $id, 'email' => ''];
            return stripos($info['nama'], $q) !== false || stripos((string)$info['email'], $q) !== false;
        }, ARRAY_FILTER_USE_KEY);
    }

    foreach ($byPpl as $id => $d) {
        $pml = $id === '(Belum ada PPL)' ? '(Belum ada PPL)' : ($pplToPml[$id] ?? '(PML tidak diketahui)');
        $byPml[$pml][$id] = $d;
    }
    ksort($byPml);
    foreach ($byPml as $pml => $ppls) {
        uksort($byPml[$pml], fn($a, $b) => strcasecmp($identityInfo[$a]['nama'] ?? $a, $identityInfo[$b]['nama'] ?? $b));
    }

    // "(Belum ada PPL)" ditaruh paling bawah (bukan ikut urutan abjad), dan
    // otomatis hilang dari tampilan kalau memang tidak ada isinya (0 Sub-SLS).
    if (isset($byPml['(Belum ada PPL)'])) {
        $belumAdaPplGroup = $byPml['(Belum ada PPL)'];
        unset($byPml['(Belum ada PPL)']);
        if (!empty($belumAdaPplGroup)) {
            $byPml['(Belum ada PPL)'] = $belumAdaPplGroup;
        }
    }

    // ── Penambahan harian: bandingkan dgn tanggal SEBELUMNYA yg tersedia ────
    // Wilayah kerja (siapa PPL di kode X) dianggap sama persis di kedua tanggal
    // (pakai $pplByKode yg sama) — yg beda cuma angka hasil pendataannya.
    $idx = array_search($tanggal, $dates);
    if ($idx !== false && isset($dates[$idx + 1])) {
        $tanggalPrev = $dates[$idx + 1];
        $sheetsDataPrev = [];
        foreach ($sheetKeys as $sk) {
            $sheetsDataPrev[$sk] = slsFetchSubslsRows($db, $tanggalPrev, $sk);
        }
        $byPplRaw = slsAggregateByPPL($sheetsDataPrev, $pplByKode);
        foreach ($byPplRaw as $id => $d) {
            $m = slsRowMetrics($d['sheets'], 1);
            $byPplPrev[$id] = ['totOk' => $m['totOk']];
        }
    }

    // ── Data ringkasan per PML/Korwil buat 4 barchart ────────────────────
    $pmlChartData = [];
    foreach ($byPml as $pmlName => $ppls) {
        if ($pmlName === '(Belum ada PPL)') continue; // gak relevan ditampilin di chart per PML
        $ids = array_keys($ppls);
        $m = slsRowMetrics(slsSumSheets($ppls), count($ppls));
        $as = sumAssignStats($ids, $identityInfo, $assignStats) ?? [];
        $prelist = $as['prelist'] ?? null;
        $selesai = $as['selesai'] ?? null;
        $pctPrelist = ($prelist !== null && $prelist > 0) ? round($selesai / $prelist * 100, 1) : null;

        $pmlChartData[] = [
            'label'        => slsKorwilPmlLabel($pmlName, $korwilMap),
            'pctTotal'     => $m['pct'],
            'pctPrelist'   => $pctPrelist,
            'deltaTotal'   => computeDeltaGroupRaw($m['totOk'], $ids, $byPplPrev, 'totOk'),
            'deltaPrelist' => $as['selesaiDelta'] ?? null,
        ];
    }
}

/** Hitung raw delta level grup (subtotal PML / total kota): null kalau gak ada data pembanding sama sekali */
function computeDeltaGroupRaw($curTotal, array $ids, array $prevMap, string $field): ?float {
    $prevTotal = 0; $any = false;
    foreach ($ids as $id) {
        if (isset($prevMap[$id][$field])) { $prevTotal += $prevMap[$id][$field]; $any = true; }
    }
    return $any ? ($curTotal - $prevTotal) : null;
}

/** Format angka delta: +123 (hijau), -45 (merah), atau — kalau data tanggal sebelumnya gak ada */
function fmtDelta($cur, array $prevMap, string $id, string $field) {
    if (!isset($prevMap[$id][$field])) return '<span style="color:var(--text2)">—</span>';
    $d = $cur - $prevMap[$id][$field];
    if ($d > 0) return '<span style="color:var(--ok);font-weight:700">+' . number_format($d,0,',','.') . '</span>';
    if ($d < 0) return '<span style="color:var(--red,#DC2626);font-weight:700">' . number_format($d,0,',','.') . '</span>';
    return '<span style="color:var(--text2)">0</span>';
}

/** Delta level grup (subtotal PML / total kota) — versi HTML, pakai computeDeltaGroupRaw di dalamnya */
function fmtDeltaGroup($curTotal, array $ids, array $prevMap, string $field) {
    $d = computeDeltaGroupRaw($curTotal, $ids, $prevMap, $field);
    if ($d === null) return '<span style="color:var(--text2)">—</span>';
    if ($d > 0) return '<span style="color:var(--ok);font-weight:700">+' . number_format($d,0,',','.') . '</span>';
    if ($d < 0) return '<span style="color:var(--red,#DC2626);font-weight:700">' . number_format($d,0,',','.') . '</span>';
    return '<span style="color:var(--text2)">0</span>';
}
/**
 * Render 1 panel barchart horizontal dari $items (array of ['label'=>,'value'=>]).
 * $mode 'percent' = skala relatif ke nilai maksimum, warna gradasi oranye.
 * $mode 'delta'   = skala relatif ke |nilai| maksimum, ijo/merah sesuai tanda, null jadi "—".
 */
function renderBarChart(string $title, array $items, string $mode): string {
    if (!$items) return '<div class="chart-box"><div class="chart-box-title">' . htmlspecialchars($title) . '</div><div class="no-data" style="padding:16px">Belum ada data.</div></div>';

    // Urutkan dari nilai tertinggi ke terendah. Buat mode 'delta', diurutkan
    // berdasar nilai asli (bukan |nilai|) — jadi kenaikan besar di atas,
    // penurunan besar di bawah. Yang null (gak ada data pembanding) ditaruh paling akhir.
    usort($items, function($a, $b) {
        if ($a['value'] === null && $b['value'] === null) return 0;
        if ($a['value'] === null) return 1;
        if ($b['value'] === null) return -1;
        return $b['value'] <=> $a['value'];
    });

    if ($mode === 'delta') {
        $maxAbs = max(array_map(fn($it) => $it['value'] !== null ? abs($it['value']) : 0, $items)) ?: 1;
    } else {
        $maxAbs = max(array_map(fn($it) => $it['value'] ?? 0, $items)) ?: 1;
    }

    $html = '<div class="chart-box"><div class="chart-box-title">' . htmlspecialchars($title) . '</div>';
    foreach ($items as $it) {
        $val = $it['value'];
        $html .= '<div class="chart-row"><div class="chart-label" title="' . htmlspecialchars($it['label']) . '">' . htmlspecialchars($it['label']) . '</div>';
        if ($val === null) {
            $html .= '<div class="chart-track"></div><div class="chart-val" style="color:var(--text2)">—</div>';
        } elseif ($mode === 'delta') {
            $pct = min(100, abs($val) / $maxAbs * 100);
            $color = $val > 0 ? 'var(--ok)' : ($val < 0 ? '#DC2626' : 'var(--border)');
            $label = ($val > 0 ? '+' : '') . number_format($val, 0, ',', '.');
            $html .= '<div class="chart-track"><div class="chart-fill" style="width:' . $pct . '%;background:' . $color . '"></div></div>';
            $html .= '<div class="chart-val" style="color:' . $color . '">' . $label . '</div>';
        } else {
            $pct = min(100, $val / $maxAbs * 100);
            $html .= '<div class="chart-track"><div class="chart-fill" style="width:' . $pct . '%;background:var(--grad)"></div></div>';
            $html .= '<div class="chart-val">' . number_format($val, 1, ',', '.') . '%</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rekap Usaha per PPL — SELARAS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<style>
:root{--o:#E8560A;--o-lt:#FDE8DD;--navy:#1A2744;--ok:#16A34A;--ok-lt:#DCFCE7;
--text:#1A1A2E;--text2:#6B7280;--border:#E4E4E4;--bg:#F5F6FA;--grad:linear-gradient(135deg,#E8560A,#F5A623)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
nav{background:var(--navy);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-right{display:flex;align-items:center;gap:10px}
.nav-user{color:rgba(255,255,255,.7);font-size:12px}
.nav-dot{width:10px;height:10px;border-radius:50%;background:var(--o)}
.nav-title{color:#fff;font-size:15px;font-weight:700}
.nav-sub{color:rgba(255,255,255,.45);font-size:11px;margin-left:4px}
.nav-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:11.5px;padding:5px 12px;border-radius:6px;text-decoration:none}
.main{max-width:1300px;margin:0 auto;padding:24px 16px}
.page-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px}
.page-sub{font-size:13px;color:var(--text2);margin-bottom:20px}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px}
.frow{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.fg{display:flex;flex-direction:column;gap:4px}
label{font-size:10.5px;font-weight:700;color:var(--text2);text-transform:uppercase}
select,input[type=text]{padding:8px 11px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit}
.btn{padding:9px 16px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:var(--grad);color:#fff}
.section-title{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:10px;display:flex;align-items:center;gap:8px;cursor:default}
.section-title i{color:var(--o)}
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:800px){.chart-grid{grid-template-columns:1fr}}
.chart-box{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px}
.chart-box-title{font-size:13px;font-weight:700;color:var(--navy);margin-bottom:12px}
.chart-row{display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:11.5px}
.chart-label{width:130px;flex-shrink:0;text-align:right;color:var(--navy);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chart-track{flex:1;background:var(--bg);border-radius:4px;height:16px;position:relative;overflow:hidden}
.chart-fill{height:16px;border-radius:4px;display:flex;align-items:center}
.chart-val{width:56px;flex-shrink:0;font-weight:700;color:var(--navy);font-size:11px}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{padding:8px 10px;text-align:right;font-size:10.5px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap;background:#fff}
th.lbl{text-align:left}
td{padding:7px 10px;border-bottom:1px solid #F0F0F0;white-space:nowrap;text-align:right}
td.lbl{text-align:left;font-weight:600;color:var(--navy)}
.td-email{font-size:11px;color:var(--text2);font-weight:400}
tr:hover td{background:#FDFAF6}
.no-data{text-align:center;padding:40px;color:var(--text2)}
.tw{overflow-x:auto}
.cnt{background:var(--o-lt);color:#C94508;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700}
.cnt-warn{background:#FEF3C7;color:#92400E}
.pml-row td{background:var(--navy);color:#fff;font-weight:700;font-size:12px;padding:8px 10px;text-align:left}
.legend{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:11.5px;color:var(--text2)}
.legend-top{margin-bottom:12px;padding-bottom:12px;border-bottom:1px dashed var(--border)}
.legend b{color:var(--navy)}
.pml-cnt{font-weight:400;color:rgba(255,255,255,.6);font-size:11px}
tr.subtotal-row td{background:#EEF1F7;color:var(--navy);font-weight:700}
tr.total-row td{background:var(--o-lt);color:#C94508;font-weight:800;border-top:2px solid var(--o)}
.pct-ok{color:var(--ok);font-weight:800}
.pct-warn{color:#D97706;font-weight:800}
.pct-bad{color:#DC2626;font-weight:800}
details summary{cursor:pointer;list-style:none}
details summary::-webkit-details-marker{display:none}
</style>
</head>
<body>

<nav>
  <div class="nav-left">
    <div class="nav-dot"></div>
    <div><span class="nav-title">SELARAS</span><span class="nav-sub">Rekap Usaha per PPL</span></div>
  </div>
  <div class="nav-right">
    <a href="<?= BASE_URL ?>/dashboard/" class="nav-btn">← Dashboard</a>
    <a href="<?= BASE_URL ?>/dashboard_wilayah.php" class="nav-btn"><i class="ti ti-map-2"></i> Wilayah</a>
    <?php if ($isLoggedIn): ?>
      <span class="nav-user"><?= htmlspecialchars($namaUser) ?></span>
      <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>/import_sls.php" class="nav-btn"><i class="ti ti-database-import"></i> Import</a>
        <a href="<?= BASE_URL ?>/admin/mapping.php" class="nav-btn"><i class="ti ti-users-group"></i> Mapping</a>
        <a href="<?= BASE_URL ?>/mapping_korwil.php" class="nav-btn"><i class="ti ti-sitemap"></i> Korwil</a>
      <?php endif ?>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-btn">Keluar</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/login.php" class="nav-btn"><i class="ti ti-login-2"></i> Masuk</a>
    <?php endif ?>
  </div>
</nav>

<div class="main">
  <div class="page-title">Rekap Data Usaha & Keluarga per PPL</div>
  <div class="page-sub">PPL/PML per Sub-SLS diambil dari <code>progress_wilayah</code>/<code>assignment</code>, fallback ke Master Wilayah Kerja. Target tetap <?= SLS_TARGET_PER_PPL ?> per PPL. Kolom Δ menunjukkan penambahan dibanding tanggal snapshot sebelumnya.</div>

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
          <label>Cari nama PPL</label>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="mis. Diana...">
        </div>
        <button type="submit" class="btn">Tampilkan</button>
      </div>
    </form>
  </div>

  <div class="panel">
    <div class="section-title"><i class="ti ti-chart-bar"></i> Ringkasan per Korwil/PML</div>
    <div class="chart-grid">
      <?= renderBarChart('% Total', array_map(fn($d) => ['label' => $d['label'], 'value' => $d['pctTotal']], $pmlChartData), 'percent') ?>
      <?= renderBarChart('% Prelist', array_map(fn($d) => ['label' => $d['label'], 'value' => $d['pctPrelist']], $pmlChartData), 'percent') ?>
      <?= renderBarChart('Δ Total', array_map(fn($d) => ['label' => $d['label'], 'value' => $d['deltaTotal']], $pmlChartData), 'delta') ?>
      <?= renderBarChart('Δ Prelist', array_map(fn($d) => ['label' => $d['label'], 'value' => $d['deltaPrelist']], $pmlChartData), 'delta') ?>
    </div>
  </div>

  <?php if ($tanpaPpl): ?>
  <div class="panel">
    <details>
      <summary class="section-title"><i class="ti ti-alert-triangle"></i> Sub-SLS Belum Ada PPL
        <span class="cnt cnt-warn" style="margin-left:auto"><?= count($tanpaPpl) ?> Sub-SLS</span>
      </summary>
      <div style="margin-top:12px" class="tw">
        <table>
          <thead><tr><th class="lbl">Kode Sub-SLS</th><th class="lbl">Nama Wilayah</th></tr></thead>
          <tbody>
            <?php foreach ($tanpaPpl as $t): ?>
            <tr><td class="lbl" style="font-family:monospace;font-size:11px"><?= htmlspecialchars($t['kode']) ?></td><td class="lbl"><?= htmlspecialchars($t['nama']) ?></td></tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
  <?php endif ?>

  <div class="panel">
    <div class="section-title"><i class="ti ti-building-store"></i> Rekap Usaha per PPL
      <span class="cnt" style="margin-left:auto"><?= count($byPpl) ?> PPL</span>
    </div>
    <?php if (!$byPpl): ?>
      <div class="no-data">Belum ada data untuk tanggal ini.</div>
    <?php else: ?>
    <div class="legend legend-top">
      <span><b>Target</b> = 716 per PPL</span>
      <span><b>TANI</b> = Usaha (BKU+Keluarga) Pertanian, Ditemukan+Baru</span>
      <span><b>NONTANI</b> = Usaha (BKU+Keluarga) Non Pertanian, Ditemukan+Baru</span>
      <span><b>KEL</b> = Keluarga (rumah tangga) Ditemukan+Baru</span>
      <span><b>Total</b> = TANI + NONTANI + KEL (diwarnai gradasi merah→hijau, capnya 716)</span>
      <span><b>%</b> = Total ÷ Target</span>
      <span><b>Δ TOTAL</b> = penambahan Total sejak <?= $tanggalPrev ? htmlspecialchars($tanggalPrev) : 'tanggal sebelumnya (belum ada data pembanding)' ?></span>
      <span><b>Prelist/Selesai/%Prelist</b> = dari tabel <code>progress</code> (sama seperti dashboard utama) — Prelist=total sample assignment, Selesai=submitted+approved+rejected, tanggal terbaru yang ada</span>
      <span><b>Δ Selesai</b> = penambahan Selesai dibanding tanggal sebelumnya di tabel <code>progress</code></span>
      <span><b>SLS Selesai</b> = jumlah Sub-SLS berstatus selesai / total Sub-SLS yang dipegang (dari sheet Monitoring SLS)</span>
      <span><b>MUTASI</b> = Usaha (BKU+Keluarga) Ganda+Tutup+Tdk Ditemukan + Keluarga Meninggal+Tdk Eligible+Tdk Ditemukan+Tdk Ditemui</span>
      <span><b>KEL-KHUSUS</b> = Keluarga Khusus: dilaporkan PPL / sudah didata</span>
    </div>
    <div class="tw">
    <table>
      <thead><tr>
        <th class="lbl">PPL</th>
        <th>Target</th>
        <th>Total</th>
        <th>%</th>
        <th>Δ TOTAL</th>
        <th>Prelist</th>
        <th>Selesai</th>
        <th>%Prelist</th>
        <th>Δ Selesai</th>
        <th>SLS Selesai</th>
        <th>TANI</th>
        <th>NONTANI</th>
        <th>KEL</th>
        <th>MUTASI</th>
        <th>KEL-KHUSUS</th>
      </tr></thead>
      <tbody>
        <?php foreach ($byPml as $pml => $ppls): ?>
        <tr class="pml-row"><td colspan="15"><?= htmlspecialchars($pml) ?> <span class="pml-cnt">(<?= count($ppls) ?> PPL)</span></td></tr>
        <?php foreach ($ppls as $id => $d):
          $m = slsRowMetrics($d['sheets'], 1);
          $isUnassigned = ($id === '(Belum ada PPL)');
          $pctClass = $m['pct'] >= 80 ? 'pct-ok' : ($m['pct'] >= 50 ? 'pct-warn' : 'pct-bad');
          $info = $identityInfo[$id] ?? ['nama' => $id, 'email' => null];
        ?>
        <tr>
          <td class="lbl" style="padding-left:22px">
            <?php
              $catatanKey = slsIdentityKey($info['email'] ?? null, $info['nama']);
              $catatanPpl = $catatanMap[$catatanKey]['catatan'] ?? null;
            ?>
            <?php if ($isUnassigned): ?>
              <?= htmlspecialchars($info['nama']) ?> <span style="color:var(--text2);font-weight:400;font-size:11px">(<?= $d['jumlah_sls'] ?> Sub-SLS)</span>
            <?php else:
              $linkParam = $info['email'] ? 'ppl_email=' . urlencode($info['email']) : 'ppl_nama=' . urlencode($info['nama']);
            ?>
              <a href="<?= BASE_URL ?>/dashboard_wilayah.php?tanggal=<?= urlencode($tanggal) ?>&<?= $linkParam ?>" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--o)"><?= htmlspecialchars($info['nama']) ?></a> <span style="color:var(--text2);font-weight:400;font-size:11px">(<?= $d['jumlah_sls'] ?> Sub-SLS)</span>
            <?php endif ?>
            <?php if (!$isUnassigned): ?>
              <?php
                $catatanUrl = BASE_URL . '/catatan_ppl.php?email=' . urlencode($info['email'] ?? '') . '&nama=' . urlencode($info['nama']) . '&back=' . urlencode($currentUrl);
              ?>
              <?php if ($catatanPpl): ?>
                <a href="<?= htmlspecialchars($catatanUrl) ?>" title="Klik buat edit catatan" style="text-decoration:none"><i class="ti ti-note" style="color:var(--amber,#F5A623);font-size:13px;margin-left:3px" title="<?= htmlspecialchars($catatanPpl) ?>"></i></a>
              <?php else: ?>
                <a href="<?= htmlspecialchars($catatanUrl) ?>" style="color:var(--text2);font-size:10px;text-decoration:none;border-bottom:1px dotted var(--text2);margin-left:5px">+ catatan</a>
              <?php endif ?>
            <?php endif ?>
            <?php if ($info['email']): ?><div class="td-email"><?= htmlspecialchars($info['email']) ?></div><?php endif ?>
            <?php if ($catatanPpl): ?><a href="<?= htmlspecialchars($catatanUrl ?? '#') ?>" style="text-decoration:none"><div style="font-size:10.5px;color:#B45309;background:#FEF3C7;border-radius:5px;padding:2px 7px;margin-top:3px;display:inline-block"><?= htmlspecialchars($catatanPpl) ?></div></a><?php endif ?>
          </td>
          <td><?= $isUnassigned ? '—' : number_format($m['target'],0,',','.') ?></td>
          <td><?= $isUnassigned ? number_format($m['totOk'],0,',','.') : '<span style="'.totalGradientStyle($m['totOk'], $m['target']).';padding:2px 8px;display:inline-block">'.number_format($m['totOk'],0,',','.').'</span>' ?></td>
          <td class="<?= $isUnassigned ? '' : $pctClass ?>"><?= $isUnassigned ? '—' : number_format($m['pct'],1,',','.') . '%' ?></td>
          <td><?= fmtDelta($m['totOk'], $byPplPrev, $id, 'totOk') ?></td>
          <?php
            $email = $info['email'] ?? null;
            $as = ($email && isset($assignStats[strtolower($email)])) ? $assignStats[strtolower($email)] : null;
            echo renderPrelistCells($as['prelist'] ?? null, $as['selesai'] ?? null);
            echo renderSelesaiDeltaCell($as['selesaiDelta'] ?? null);
          ?>
          <td><?= $isUnassigned ? '—' : number_format($m['slsSelesai'],0,',','.') . ' / ' . number_format($d['jumlah_sls'],0,',','.') ?></td>
          <td><?= number_format($m['pertanianOk'],0,',','.') ?></td>
          <td><?= number_format($m['nonPertanianOk'],0,',','.') ?></td>
          <td><?= number_format($m['kelOk'],0,',','.') ?></td>
          <td><?= number_format($m['totBrm'],0,',','.') ?></td>
          <td><?= number_format($m['khususPpl'],0,',','.') ?> / <?= number_format($m['khususDidata'],0,',','.') ?></td>
        </tr>
        <?php endforeach ?>
        <?php
          $mPml = slsRowMetrics(slsSumSheets($ppls), count($ppls));
          $pmlUnassigned = ($pml === '(Belum ada PPL)');
          $pctClass = $mPml['pct'] >= 80 ? 'pct-ok' : ($mPml['pct'] >= 50 ? 'pct-warn' : 'pct-bad');
          $idsInGroup = array_keys($ppls);
        ?>
        <tr class="subtotal-row">
          <td class="lbl">Subtotal <?= htmlspecialchars($pml) ?></td>
          <td><?= $pmlUnassigned ? '—' : number_format($mPml['target'],0,',','.') ?></td>
          <td><?= $pmlUnassigned ? number_format($mPml['totOk'],0,',','.') : '<span style="'.totalGradientStyle($mPml['totOk'], $mPml['target']).';padding:2px 8px;display:inline-block">'.number_format($mPml['totOk'],0,',','.').'</span>' ?></td>
          <td class="<?= $pmlUnassigned ? '' : $pctClass ?>"><?= $pmlUnassigned ? '—' : number_format($mPml['pct'],1,',','.') . '%' ?></td>
          <td><?= fmtDeltaGroup($mPml['totOk'], $idsInGroup, $byPplPrev, 'totOk') ?></td>
          <?php
            $asGroup = sumAssignStats($idsInGroup, $identityInfo, $assignStats) ?? [];
            echo renderPrelistCells($asGroup['prelist'] ?? null, $asGroup['selesai'] ?? null);
            echo renderSelesaiDeltaCell($asGroup['selesaiDelta'] ?? null);
            $jumlahSlsGroup = array_sum(array_column($ppls, 'jumlah_sls'));
          ?>
          <td><?= number_format($mPml['slsSelesai'],0,',','.') ?> / <?= number_format($jumlahSlsGroup,0,',','.') ?></td>
          <td><?= number_format($mPml['pertanianOk'],0,',','.') ?></td>
          <td><?= number_format($mPml['nonPertanianOk'],0,',','.') ?></td>
          <td><?= number_format($mPml['kelOk'],0,',','.') ?></td>
          <td><?= number_format($mPml['totBrm'],0,',','.') ?></td>
          <td><?= number_format($mPml['khususPpl'],0,',','.') ?> / <?= number_format($mPml['khususDidata'],0,',','.') ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php
          // Target kota cuma dihitung dari PPL yg beneran (bukan bucket "Belum ada PPL"),
          // tapi angka hasil pendataannya (totOk dkk) tetap termasuk semuanya.
          $realPplCount = count($byPpl) - (isset($byPpl['(Belum ada PPL)']) ? 1 : 0);
          $mTotal = slsRowMetrics(slsSumSheets($byPpl), max(1, $realPplCount));
          $allIds = array_keys($byPpl);
        ?>
        <tr class="total-row">
          <td class="lbl">TOTAL SE KOTA PAYAKUMBUH</td>
          <td><?= number_format($mTotal['target'],0,',','.') ?></td>
          <td><?= number_format($mTotal['totOk'],0,',','.') ?></td>
          <td><?= number_format($mTotal['pct'],1,',','.') ?>%</td>
          <td><?= fmtDeltaGroup($mTotal['totOk'], $allIds, $byPplPrev, 'totOk') ?></td>
          <?php
            $asTotal = sumAssignStats($allIds, $identityInfo, $assignStats) ?? [];
            echo renderPrelistCells($asTotal['prelist'] ?? null, $asTotal['selesai'] ?? null);
            echo renderSelesaiDeltaCell($asTotal['selesaiDelta'] ?? null);
            $jumlahSlsTotal = array_sum(array_column($byPpl, 'jumlah_sls'));
          ?>
          <td><?= number_format($mTotal['slsSelesai'],0,',','.') ?> / <?= number_format($jumlahSlsTotal,0,',','.') ?></td>
          <td><?= number_format($mTotal['pertanianOk'],0,',','.') ?></td>
          <td><?= number_format($mTotal['nonPertanianOk'],0,',','.') ?></td>
          <td><?= number_format($mTotal['kelOk'],0,',','.') ?></td>
          <td><?= number_format($mTotal['totBrm'],0,',','.') ?></td>
          <td><?= number_format($mTotal['khususPpl'],0,',','.') ?> / <?= number_format($mTotal['khususDidata'],0,',','.') ?></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php endif ?>
</div>
</body>
</html>
