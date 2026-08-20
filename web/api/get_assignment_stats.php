<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
$db = getDB();

// Total assignment
$total = $db->query("SELECT COUNT(*) FROM assignment")->fetchColumn();

// Distribusi data6
$d6 = $db->query("
    SELECT COALESCE(NULLIF(data6,''),'(kosong)') AS data6,
           COUNT(*) AS jumlah
    FROM assignment
    GROUP BY data6
    ORDER BY jumlah DESC
")->fetchAll();

// Distribusi sampleType
$st = $db->query("
    SELECT COALESCE(CAST(sample_type AS TEXT),'(null)') AS sample_type,
           COUNT(*) AS jumlah
    FROM assignment
    GROUP BY sample_type
    ORDER BY jumlah DESC
")->fetchAll();

// Distribusi status
$sv = $db->query("
    SELECT status, COUNT(*) AS jumlah
    FROM assignment
    GROUP BY status
    ORDER BY jumlah DESC
")->fetchAll();

// Per kecamatan
$kec = $db->query("
    SELECT kode_kec,
           COUNT(*) AS total,
           SUM(CASE WHEN data6='KELUARGA' THEN 1 ELSE 0 END) AS ruta,
           SUM(CASE WHEN data6!='' AND data6!='KELUARGA' THEN 1 ELSE 0 END) AS usaha,
           SUM(CASE WHEN data6='' OR data6 IS NULL THEN 1 ELSE 0 END) AS kosong,
           COUNT(DISTINCT kode_desa) AS jml_desa,
           COUNT(DISTINCT kode_sls)  AS jml_sls
    FROM assignment
    GROUP BY kode_kec
    ORDER BY kode_kec
")->fetchAll();

// Tanggal ambil
$tgl = $db->query("
    SELECT tanggal_ambil, COUNT(*) AS jumlah
    FROM assignment
    GROUP BY tanggal_ambil
    ORDER BY tanggal_ambil DESC
")->fetchAll();

jsonResponse([
    'total'       => (int)$total,
    'data6'       => $d6,
    'sampleType'  => $st,
    'status'      => $sv,
    'kecamatan'   => $kec,
    'tanggal_ambil' => $tgl,
]);