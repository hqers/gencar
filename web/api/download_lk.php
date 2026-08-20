<?php
// ============================================================
// api/download_lk.php — Generate LK Beban Kerja dari data SELARAS
// Kolom yang bisa diisi: PML, PCL, Kecamatan, Desa, Target (total assignment)
// Kolom Ruta & Usaha tidak ada di data SELARAS
// ============================================================

require_once __DIR__ . '/../config.php';

$tgl = $_GET['tanggal'] ?? null;
$db  = getDB();

// Ambil tanggal terbaru kalau kosong
if (!$tgl) {
    $tgl = $db->query("SELECT MAX(tanggal) FROM progress")->fetchColumn();
}

// Ambil data progress + mapping nama
$stmt = $db->prepare("
    SELECT
        p.email,
        COALESCE(m.nama, p.email) AS nama,
        COALESCE(m.pml, '')       AS pml,
        p.total, p.submitted, p.approved, p.rejected,
        (p.submitted + p.approved + p.rejected) AS dikerjakan,
        (p.approved + p.rejected)               AS diperiksa
    FROM progress p
    LEFT JOIN mapping_nama m ON LOWER(p.email) = LOWER(m.email)
    WHERE p.tanggal = ?
      AND COALESCE(m.tampil, 1) = 1
    ORDER BY m.pml, m.nama
");
$stmt->execute([$tgl]);
$rows = $stmt->fetchAll();

if (!$rows) {
    die('Tidak ada data untuk tanggal ' . $tgl);
}

// Ambil info wilayah per pencacah dari progress_wilayah
$wilayah = [];
$wStmt = $db->prepare("
    SELECT email,
           SUBSTR(region_code,1,7)  AS kode_kec,
           SUBSTR(region_code,1,10) AS kode_desa,
           SUM(total) AS total
    FROM progress_wilayah
    WHERE tanggal = ?
    GROUP BY email, SUBSTR(region_code,1,7), SUBSTR(region_code,1,10)
    ORDER BY email, kode_kec, kode_desa
");
$wStmt->execute([$tgl]);
foreach ($wStmt->fetchAll() as $w) {
    $wilayah[strtolower($w['email'])][] = $w;
}

$namaKec = [
    '5371010' => 'ALAK',
    '5371020' => 'MAULAFA',
    '5371030' => 'OEBOBO',
    '5371031' => 'KOTA RAJA',
    '5371040' => 'KELAPA LIMA',
    '5371041' => 'KOTA LAMA',
];

// Generate CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="LK_Beban_Kerja_SE2026_' . $tgl . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8

fputcsv($out, ['LEMBAR KERJA BEBAN KERJA PETUGAS SE2026']);
fputcsv($out, ['Kabupaten/Kota: Kota Payakumbuh', 'Provinsi: Sumatera Barat']);
fputcsv($out, ['Tanggal: ' . $tgl]);
fputcsv($out, []);

fputcsv($out, [
    'No', 'Nama PML', 'Nama PCL', 'Email PCL',
    'Kecamatan', 'Kode Kecamatan', 'Kode Desa',
    'Target (Total Assignment)',
    'Dikerjakan (Sub+App+Rej)', '% Dikerjakan',
    'Diperiksa (App+Rej)', '% Diperiksa',
    'Approved', '% Approved',
    'Catatan Ruta', 'Catatan Usaha'
]);

$no = 1;
foreach ($rows as $r) {
    $email    = strtolower($r['email']);
    $wils     = $wilayah[$email] ?? [];
    $total    = (int)$r['total'];
    $dik      = (int)$r['dikerjakan'];
    $per      = (int)$r['diperiksa'];
    $app      = (int)$r['approved'];
    $dikPct   = $total > 0 ? round($dik/$total*100,1) : 0;
    $perPct   = $total > 0 ? round($per/$total*100,1) : 0;
    $appPct   = $total > 0 ? round($app/$total*100,1) : 0;

    if ($wils) {
        // Per desa
        foreach ($wils as $w) {
            fputcsv($out, [
                $no++,
                $r['pml'],
                $r['nama'],
                $r['email'],
                $namaKec[$w['kode_kec']] ?? $w['kode_kec'],
                $w['kode_kec'],
                $w['kode_desa'],
                $w['total'],
                $dik, $dikPct.'%',
                $per, $perPct.'%',
                $app, $appPct.'%',
                '', '' // Ruta & Usaha kosong
            ]);
        }
    } else {
        // Tidak ada data wilayah — tulis satu baris
        fputcsv($out, [
            $no++,
            $r['pml'],
            $r['nama'],
            $r['email'],
            '', '', '',
            $total,
            $dik, $dikPct.'%',
            $per, $perPct.'%',
            $app, $appPct.'%',
            '', ''
        ]);
    }
}

fclose($out);
exit;