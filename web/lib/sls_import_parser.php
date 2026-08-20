<?php
// ============================================================
// lib/sls_import_parser.php — Parser export "Progres Pendataan Sub-SLS"
// Sumber: FASIH Pendataan SE 2026 (8 sheet, struktur kode wilayah berjenjang)
// ============================================================
require_once __DIR__ . '/XlsxReader.php';

// Definisi tiap sheet: key internal, nama sheet asli di excel, dan daftar
// field (posisi kolom setelah Kode & Nama, dimulai dari kolom index 2 / C).
// Pakai posisi kolom (bukan cocokkan teks header) karena header di file
// sumber pakai baris gabungan (merged cell) + ada karakter zero-width space.
const SLS_SHEET_DEFS = [
    'progres_pendataan' => [
        'sheet_name' => 'PROGRES PENDATAAN',
        'fields' => [
            2 => 'jumlah_prelist_usaha_keluarga',
        ],
    ],
    'skala_usaha' => [
        'sheet_name' => 'SKALA USAHA',
        'fields' => [
            2 => 'jumlah_prelist_ub',
            3 => 'jumlah_ub_didata',
            4 => 'persentase_ub_didata',
            5 => 'jumlah_prelist_umkm',
            6 => 'jumlah_umkm_didata',
            7 => 'persentase_umkm_didata',
            8 => 'total_usaha_didata',
        ],
    ],
    'usaha_perusahaan' => [
        'sheet_name' => 'USAHA PERUSAHAAN',
        'fields' => [
            2  => 'jumlah_prelist_usaha',
            3  => 'ditemukan',
            5  => 'tutup',
            7  => 'ganda',
            9  => 'tidak_ditemukan',
            11 => 'baru',
            13 => 'jumlah_usaha_bku',
        ],
    ],
    'usaha_keluarga' => [
        'sheet_name' => 'USAHA KELUARGA',
        'fields' => [
            2 => 'ditemukan',
            3 => 'tutup',
            4 => 'ganda',
            5 => 'tidak_ditemukan',
            6 => 'baru',
            7 => 'jumlah_usaha_dalam_keluarga',
        ],
    ],
    // CATATAN SKEMA (Agu 2026): BPS menambahkan kolom "Jumlah Prelist Usaha
    // Keluarga (ST2023+UMKM)" dan "Total Prelist Usaha dan Usaha Keluarga" di
    // antara "Jumlah Prelist Usaha" dan blok "USAHA BKU", menggeser semua
    // kolom setelahnya +2 posisi. Auto-detect, sama pola kayak proporsi_pertanian.
    'keseluruhan_usaha' => [
        'sheet_name' => 'KESELURUHAN USAHA',
        'fields' => null, // diisi runtime oleh slsDetectKeseluruhanUsahaFields()
    ],
    'proporsi_usaha' => [
        'sheet_name' => 'PROPORSI USAHA',
        'fields' => [
            2 => 'jumlah_usaha_berhasil_didata',
            3 => 'jumlah_usaha_didata',
            4 => 'persentase_usaha_didata',
            5 => 'jumlah_usaha_keluarga_didata',
            6 => 'persentase_usaha_keluarga_didata',
        ],
    ],
    'jaringan_usaha' => [
        'sheet_name' => 'JARINGAN USAHA',
        'fields' => [
            2 => 'tunggal',
            3 => 'kantor_pusat',
            4 => 'cabang',
            5 => 'perwakilan',
            6 => 'pabrik',
            7 => 'unit_pembantu',
        ],
    ],
    // CATATAN SKEMA (Juli 2026): BPS menambahkan kolom baru "Jumlah UTP
    // Subsektor Prelist" di antara "Jumlah UTP Subsektor Target" dan blok
    // "USAHA DITEMUKAN", menggeser semua kolom setelahnya +1 posisi.
    // Karena skema sheet ini sudah 2x berubah, mapping-nya di-auto-detect
    // saat parsing (lihat PROPORSI_PERTANIAN_FIELDS_V1/V2 & fungsi
    // slsDetectProporsiPertanianFields() di bawah), bukan hardcode statis.
    'proporsi_pertanian' => [
        'sheet_name' => 'PROPORSI PERTANIAN NON PERTANIA',
        'fields' => null, // diisi runtime oleh slsDetectProporsiPertanianFields()
    ],

    // Sheet dari file berbeda (Progres Pemutakhiran Keluarga), sheet_name 'KELUARGA'.
    // Bukan usaha — ini pendataan rumah tangga/keluarga.
    // CATATAN: per skema terbaru (Juli 2026), kolom "Keluarga Khusus" SUDAH PINDAH
    // ke sheet terpisah 'KELUARGA KHUSUS' (lihat sheet_key 'keluarga_khusus' di bawah).
    // Jangan tambah kembali field di kolom 14 di sini — itu sekarang kolom
    // "Total Hasil Pendataan" (= ditemukan+keluarga_baru, gak perlu disimpan
    // krn bisa dihitung ulang).
    'pemutakhiran_keluarga' => [
        'sheet_name' => 'KELUARGA',
        'fields' => [
            2  => 'prelist_awal',
            3  => 'ditemukan',
            5  => 'keluarga_baru',
            6  => 'meninggal',
            8  => 'tidak_eligible',
            10 => 'tidak_ditemukan',
            12 => 'nonrespon',
        ],
    ],

    // Sheet baru (pisah dari 'KELUARGA' sejak skema Juli 2026)
    'keluarga_khusus' => [
        'sheet_name' => 'KELUARGA KHUSUS',
        'fields' => [
            2 => 'khusus_hasil_pendataan_ppl', // ditemukan/dilaporkan PPL, belum tentu sudah didata lengkap
            3 => 'khusus_didata',               // sudah selesai didata
        ],
    ],

    // Sheet dari file berbeda (Export Monitoring SLS), status selesai/belum
    // per Sub-SLS (bukan realisasi Keluarga/Usaha) — sheet_name 'Monitoring SLS'.
    'monitoring_sls' => [
        'sheet_name' => 'Monitoring SLS',
        'fields' => [
            2 => 'target_sls',        // selalu 1 di level Sub-SLS
            3 => 'jumlah_sls_selesai', // 0 atau 1 di level Sub-SLS
        ],
    ],
];

/** "58,7" / "1.234,5" (format Indonesia) -> float. Angka biasa tetap angka. */
function slsNum($v) {
    if ($v === null || $v === '') return null;
    if (is_int($v) || is_float($v)) return $v;
    $s = str_replace(['.', ','], ['', '.'], trim((string)$v));
    return is_numeric($s) ? (float)$s + 0 : null;
}

// Versi LAMA (sebelum ada kolom "Jumlah UTP Subsektor Prelist")
const PROPORSI_PERTANIAN_FIELDS_V1 = [
    3  => 'jumlah_utp_subsektor_target_st2023',
    4  => 'usaha_ditemukan_pertanian',
    6  => 'usaha_ditemukan_non_pertanian',
    7  => 'usaha_baru_pertanian',
    9  => 'usaha_baru_non_pertanian',
    10 => 'usaha_keluarga_ditemukan_pertanian',
    12 => 'usaha_keluarga_ditemukan_non_pertanian',
    13 => 'usaha_keluarga_baru_pertanian',
    15 => 'usaha_keluarga_baru_non_pertanian',
];
// Versi BARU (Juli 2026 — ada kolom "Jumlah UTP Subsektor Prelist" di kolom E)
const PROPORSI_PERTANIAN_FIELDS_V2 = [
    3  => 'jumlah_utp_subsektor_target_st2023',
    4  => 'jumlah_utp_subsektor_prelist',
    5  => 'usaha_ditemukan_pertanian',
    7  => 'usaha_ditemukan_non_pertanian',
    8  => 'usaha_baru_pertanian',
    10 => 'usaha_baru_non_pertanian',
    11 => 'usaha_keluarga_ditemukan_pertanian',
    13 => 'usaha_keluarga_ditemukan_non_pertanian',
    14 => 'usaha_keluarga_baru_pertanian',
    16 => 'usaha_keluarga_baru_non_pertanian',
];
// Versi TERBARU (Agu 2026 — nama sheet ganti jadi "PROPORSI PERTANIAN" doang,
// dan kolom NON PERTANIAN dibuang total dari sheet ini, cuma tersisa Pertanian.
// Realisasi Non-Pertanian sekarang dihitung di dashboard_wilayah.php dengan
// cara: Total(sheet Keseluruhan Usaha) - Pertanian(sheet ini), bukan dibaca
// langsung dari sini lagi.
const PROPORSI_PERTANIAN_FIELDS_V3 = [
    3  => 'jumlah_utp_subsektor_target_st2023',
    4  => 'jumlah_utp_subsektor_prelist',
    5  => 'usaha_ditemukan_pertanian',
    7  => 'usaha_baru_pertanian',
    9  => 'usaha_keluarga_ditemukan_pertanian',
    11 => 'usaha_keluarga_baru_pertanian',
];

/**
 * Deteksi versi skema sheet PROPORSI PERTANIAN dengan mencari teks header
 * (bukan hardcode posisi kolom), soalnya skema sheet ini sudah beberapa
 * kali berubah dari BPS — termasuk V3 yang buang kolom Non Pertanian total.
 */
function slsDetectProporsiPertanianFields(XlsxReader $reader, $sheetName) {
    $grid = $reader->readSheetAsGrid($sheetName, 20);
    $adaSubsektorPrelist = false;
    $adaNonPertanian = false;
    for ($r = 1; $r <= min(8, count($grid) ?: 8); $r++) {
        foreach (($grid[$r] ?? []) as $val) {
            if ($val === null) continue;
            // buang zero-width space & spasi ekstra sebelum dicocokkan
            $clean = preg_replace('/[\x{200B}\x{200C}\x{FEFF}]/u', '', (string)$val);
            if (stripos($clean, 'Subsektor Prelist') !== false) $adaSubsektorPrelist = true;
            if (stripos($clean, 'Non Pertanian') !== false) $adaNonPertanian = true;
        }
    }
    if ($adaSubsektorPrelist && !$adaNonPertanian) return PROPORSI_PERTANIAN_FIELDS_V3;
    if ($adaSubsektorPrelist) return PROPORSI_PERTANIAN_FIELDS_V2;
    return PROPORSI_PERTANIAN_FIELDS_V1;
}

// Versi LAMA sheet KESELURUHAN USAHA (1 kolom prelist gabungan usaha+keluarga)
const KESELURUHAN_USAHA_FIELDS_V1 = [
    2 => 'jumlah_prelist_usaha',
    3 => 'usaha_ditemukan',
    4 => 'usaha_baru',
    5 => 'usaha_subtotal',
    6 => 'usaha_keluarga_ditemukan',
    7 => 'usaha_keluarga_baru',
    8 => 'usaha_keluarga_subtotal',
    9 => 'total_usaha',
];
// Versi BARU (Agu 2026 — prelist usaha & prelist usaha-keluarga dipisah,
// plus ada kolom "Total Prelist Usaha dan Usaha Keluarga" tersendiri)
const KESELURUHAN_USAHA_FIELDS_V2 = [
    2  => 'jumlah_prelist_usaha',
    3  => 'jumlah_prelist_usaha_keluarga',
    4  => 'total_prelist_usaha_keluarga',
    5  => 'usaha_ditemukan',
    7  => 'usaha_baru',
    9  => 'usaha_subtotal',
    11 => 'usaha_keluarga_ditemukan',
    13 => 'usaha_keluarga_baru',
    15 => 'usaha_keluarga_subtotal',
];

/** Deteksi versi skema sheet KESELURUHAN USAHA, cari teks header "Prelist Usaha Keluarga" */
function slsDetectKeseluruhanUsahaFields(XlsxReader $reader, $sheetName) {
    $grid = $reader->readSheetAsGrid($sheetName, 20);
    for ($r = 1; $r <= min(8, count($grid) ?: 8); $r++) {
        foreach (($grid[$r] ?? []) as $val) {
            if ($val === null) continue;
            $clean = preg_replace('/[\x{200B}\x{200C}\x{FEFF}]/u', '', (string)$val);
            if (stripos($clean, 'Prelist Usaha Keluarga') !== false) {
                return KESELURUHAN_USAHA_FIELDS_V2;
            }
        }
    }
    return KESELURUHAN_USAHA_FIELDS_V1;
}

/** Level wilayah berdasarkan panjang kode BPS */
function slsLevelFromKode($kode) {
    switch (strlen($kode)) {
        case 4:  return 'kabkota';
        case 7:  return 'kecamatan';
        case 10: return 'kelurahan';
        case 14: return 'sls';
        case 16: return 'subsls';
        default: return 'unknown';
    }
}

/**
 * Parse satu file XLSX export Sub-SLS.
 * Return: [
 *   'sheets' => [ sheet_key => [ kode => ['nama'=>..,'level'=>..,'data'=>[field=>val]] ] ],
 *   'sheets_found' => [sheet_key,...],
 *   'sheets_missing' => [sheet_key,...],
 *   'row_count' => int,
 *   'kecamatan' => ['kode'=>'1376010','nama'=>'PAYAKUMBUH BARAT'] atau null,
 * ]
 */
function parseSlsImportFile($filePath) {
    $reader = new XlsxReader($filePath);
    $available = $reader->sheetNames();

    $result = [
        'sheets'         => [],
        'sheets_found'   => [],
        'sheets_missing' => [],
        'row_count'      => 0,
        'kecamatan'      => null,
    ];

    foreach (SLS_SHEET_DEFS as $sheetKey => $def) {
        $sheetName = $def['sheet_name'];

        // CATATAN SKEMA (Agu 2026): BPS ganti nama sheet dari "PROPORSI
        // PERTANIAN NON PERTANIA" jadi cuma "PROPORSI PERTANIAN" (kolom
        // Non Pertanian-nya juga dibuang dari sheet ini, lihat V3 di bawah).
        if ($sheetKey === 'proporsi_pertanian' && !in_array($sheetName, $available, true) && in_array('PROPORSI PERTANIAN', $available, true)) {
            $sheetName = 'PROPORSI PERTANIAN';
        }

        if (!in_array($sheetName, $available, true)) {
            $result['sheets_missing'][] = $sheetKey;
            continue;
        }
        $result['sheets_found'][] = $sheetKey;

        // Sheet dengan skema yang bisa berubah versi (fields = null di SLS_SHEET_DEFS)
        // diresolve dulu di sini berdasarkan deteksi header, bukan hardcode statis.
        if ($def['fields'] === null && $sheetKey === 'proporsi_pertanian') {
            $def['fields'] = slsDetectProporsiPertanianFields($reader, $sheetName);
        }
        if ($def['fields'] === null && $sheetKey === 'keseluruhan_usaha') {
            $def['fields'] = slsDetectKeseluruhanUsahaFields($reader, $sheetName);
        }

        $maxCol = max(array_keys($def['fields'])) + 1;
        $grid   = $reader->readSheetAsGrid($sheetName, $maxCol);

        $rows = [];
        foreach ($grid as $rowNum => $cols) {
            $kode = trim((string)($cols[0] ?? ''));
            $nama = trim((string)($cols[1] ?? ''));
            // Baris data valid: kode harus semua digit (buang judul/header/baris kosong)
            if ($kode === '' || !ctype_digit($kode)) continue;

            // Hanya simpan level Sub-SLS (16 digit) — level Kab/Kota, Kecamatan,
            // Kelurahan, dan SLS (14 digit) di file ini cuma subtotal hasil
            // penjumlahan baris Sub-SLS, jadi tidak perlu disimpan dobel.
            // Level-level itu dihitung ulang dengan agregasi SUBSTR(kode,...)
            // saat data ditampilkan (lihat lib/sls_aggregate.php).
            if (strlen($kode) !== 16) {
                // Tetap deteksi kecamatan dari baris 7 digit untuk log import
                if ($result['kecamatan'] === null && strlen($kode) === 7) {
                    $result['kecamatan'] = ['kode' => $kode, 'nama' => $nama];
                }
                continue;
            }

            $data = [];
            foreach ($def['fields'] as $colIdx => $fieldName) {
                $data[$fieldName] = slsNum($cols[$colIdx] ?? null);
            }

            $rows[$kode] = [
                'nama'  => $nama,
                'level' => 'subsls',
                'data'  => $data,
            ];
        }

        $result['sheets'][$sheetKey] = $rows;
        $result['row_count'] = max($result['row_count'], count($rows));
    }

    return $result;
}
