<?php
// ============================================================
// api/get_progress.php — Ambil data progress untuk dashboard
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

// Ambil daftar tanggal yang tersedia
$dates = $db->query("
    SELECT DISTINCT tanggal
    FROM progress
    ORDER BY tanggal DESC
")->fetchAll(PDO::FETCH_COLUMN);

// Tanggal yang dipilih
$tgl = $_GET['tanggal'] ?? ($dates[0] ?? null);
$tglPrev = null;

if ($tgl && count($dates) > 1) {
    $idx = array_search($tgl, $dates);
    if ($idx !== false && isset($dates[$idx + 1])) {
        $tglPrev = $dates[$idx + 1];
    }
}

if (!$tgl) {
    jsonResponse(['success' => true, 'rows' => [], 'pmlRows' => [], 'dates' => [], 'lastLog' => null]);
}

// Ambil data tanggal dipilih + join mapping nama
$rows = $db->prepare("
    SELECT
        p.tanggal, p.email,
        COALESCE(m.nama, '') AS nama,
        COALESCE(m.pml, '')  AS pml,
        p.total, p.open_count AS open_count,
        p.draft, p.submitted, p.approved, p.rejected,
        p.selesai, p.progress_pct AS progress,
        p.catatan,
        COALESCE(m.tampil, 1) AS tampil
    FROM progress p
    LEFT JOIN mapping_nama m ON LOWER(p.email) = LOWER(m.email)
    WHERE p.tanggal = ?
      AND COALESCE(m.tampil, 1) = 1
    ORDER BY p.selesai DESC
");
$rows->execute([$tgl]);
$data = $rows->fetchAll();

// Ambil data tanggal sebelumnya untuk hitung selisih
$prevData = [];
if ($tglPrev) {
    $prevStmt = $db->prepare("
        SELECT email, selesai, submitted, approved,
               (submitted + approved + rejected) AS dikerjakan
        FROM progress WHERE tanggal = ?
    ");
    $prevStmt->execute([$tglPrev]);
    foreach ($prevStmt->fetchAll() as $r) {
        $e = strtolower($r['email']);
        $prevData[$e]        = (int)$r['selesai'];
        $prevData[$e.'_sub'] = (int)$r['submitted'];
        $prevData[$e.'_app'] = (int)$r['approved'];
        $prevData[$e.'_dik'] = (int)$r['dikerjakan'];
    }
}

// Tambah selisih ke tiap row
$rows = array_map(function($r) use ($prevData) {
    $email      = strtolower($r['email']);
    $selesai    = (int)$r['selesai'];
    $submitted  = (int)$r['submitted'];
    $approved   = (int)$r['approved'];
    $rejected   = (int)$r['rejected'];
    $dikerjakan = $submitted + $approved + $rejected; // sub+app+rej

    $r['selisih']           = isset($prevData[$email])
        ? $selesai - $prevData[$email] : null;
    $r['selisihDikerjakan'] = isset($prevData[$email.'_dik'])
        ? $dikerjakan - $prevData[$email.'_dik'] : null;
    $r['selisihApproved']   = isset($prevData[$email.'_app'])
        ? $approved - $prevData[$email.'_app'] : null;
    // selisihDiperiksa = delta (app+rej) — perlu prev rejected juga
    // prev_rej = prev_dik - prev_sub - prev_app
    if (isset($prevData[$email.'_dik'])) {
        $prevApp = $prevData[$email.'_app'] ?? 0;
        $prevRej = $prevData[$email.'_dik'] - ($prevData[$email.'_sub'] ?? 0) - $prevApp;
        $r['selisihDiperiksa'] = ($approved + $rejected) - ($prevApp + $prevRej);
    } else {
        $r['selisihDiperiksa'] = null;
    }

    $r['dikerjakan']  = $dikerjakan;
    $r['selesaiPML']  = $approved + $rejected; // diperiksa
    $r['total']       = (int)$r['total'];
    $r['open']        = (int)$r['open_count'];
    $r['draft']       = (int)$r['draft'];
    $r['submitted']   = $submitted;
    $r['approved']    = $approved;
    $r['rejected']    = $rejected;
    $r['selesai']     = $selesai;
    $r['progress']    = (float)$r['progress'];
    return $r;
}, $data);

// Agregat per PML
$pmlAgg = [];
foreach ($rows as $r) {
    if (!$r['pml']) continue;
    $key = $r['tanggal'] . '|' . $r['pml'];
    if (!isset($pmlAgg[$key])) {
        $pmlAgg[$key] = [
            'tanggal'      => $r['tanggal'],
            'pml'          => $r['pml'],
            'total'        => 0, 'selesai'   => 0,
            'submitted'    => 0, 'approved'  => 0,
            'rejected'     => 0, 'open'      => 0,
            'draft'        => 0, 'selisihTotal' => 0,
            'selisihNull'  => false, 'pencacah' => []
        ];
    }
    $agg = &$pmlAgg[$key];
    $agg['total']     += $r['total'];
    $agg['selesai']   += $r['approved'] + $r['rejected'];
    $agg['submitted'] += $r['submitted'];
    $agg['approved']  += $r['approved'];
    $agg['rejected']  += $r['rejected'];
    $agg['open']      += $r['open'];
    $agg['draft']     += $r['draft'];
    if ($r['selisih'] === null) $agg['selisihNull'] = true;
    else $agg['selisihTotal'] += $r['selisih'];
    $agg['pencacah'][] = [
        'nama'              => $r['nama'] ?: $r['email'],
        'email'             => $r['email'],
        'total'             => $r['total'],
        'dikerjakan'        => $r['dikerjakan'],
        'diperiksa'         => $r['selesaiPML'],  // app+rej
        'submitted'         => $r['submitted'],
        'approved'          => $r['approved'],
        'selisih'           => $r['selisih'],           // delta selesai PPL
        'selisihDikerjakan' => $r['selisihDikerjakan'],
        'selisihDiperiksa'  => $r['selisihDiperiksa'],  // delta (app+rej)
        'selisihApproved'   => $r['selisihApproved'],
    ];
    unset($agg);
}

$pmlRows = array_values(array_map(function($a) {
    $a['progress'] = $a['total'] > 0
        ? round($a['selesai'] / $a['total'] * 100, 1) : 0;
    $a['selisih']  = $a['selisihNull'] ? null : $a['selisihTotal'];
    return $a;
}, $pmlAgg));

// Last import log
$lastLog = $db->query("
    SELECT * FROM import_log ORDER BY id DESC LIMIT 1
")->fetch() ?: null;

if ($lastLog) {
    $lastLog['autoTimestamp'] = date('d/m/Y H:i:s', strtotime($lastLog['created_at']));
    $lastLog['reportDate']    = $lastLog['tanggal'];
    $lastLog['totalPages']    = '-';
    $lastLog['page']          = '-';
}

// Summary total
$summary = ['total' => 0, 'selesai' => 0, 'approved' => 0, 'open' => 0];
foreach ($rows as $r) {
    $summary['total']    += $r['total'];
    $summary['selesai']  += $r['selesai'];
    $summary['approved'] += $r['approved'];
    $summary['open']     += $r['open'];
}
$summary['progress_pct'] = $summary['total'] > 0
    ? round($summary['selesai'] / $summary['total'] * 100, 1) : 0;
$summary['approved_pct'] = $summary['total'] > 0
    ? round($summary['approved'] / $summary['total'] * 100, 1) : 0;

jsonResponse([
    'success' => true,
    'rows'    => $rows,
    'pmlRows' => $pmlRows,
    'dates'   => $dates,
    'lastLog' => $lastLog,
    'summary' => $summary,
    'target'  => 50729,
]);