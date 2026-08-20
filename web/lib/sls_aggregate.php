<?php
// ============================================================
// lib/sls_aggregate.php — Agregasi data sls_import_data (level subsls saja
// yang tersimpan) ke level lebih tinggi, dan rekap per PPL.
// ============================================================

const SLS_LEVEL_LEN = ['kabkota' => 4, 'kecamatan' => 7, 'kelurahan' => 10, 'sls' => 14, 'subsls' => 16];

/**
 * Ambil semua baris subsls untuk 1 tanggal + 1 sheet, sudah di-decode JSON-nya.
 * Return: [ kode16 => ['nama'=>.., 'data'=>[field=>val,...]] ]
 */
function slsFetchSubslsRows(PDO $db, string $tanggal, string $sheetKey, ?string $prefix = null) {
    $sql = "SELECT kode, nama, data_json FROM sls_import_data WHERE tanggal = ? AND sheet_key = ? AND level = 'subsls'";
    $params = [$tanggal, $sheetKey];
    if ($prefix) { $sql .= " AND kode LIKE ?"; $params[] = $prefix . '%'; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['kode']] = ['nama' => $r['nama'], 'data' => json_decode($r['data_json'], true) ?: []];
    }
    return $out;
}

/**
 * Agregasi baris subsls ke level tertentu (kabkota|kecamatan|kelurahan|sls|subsls)
 * dengan cara memotong kode & menjumlahkan tiap field numerik.
 * $namaLookup: array [kode => nama] opsional untuk override nama hasil potongan
 * (misal dari $namaKec/$namaKel/$namaSls di wilayah_lookup.php).
 */
function slsAggregateToLevel(array $subslsRows, string $level, array $namaLookup = []) {
    if ($level === 'subsls') {
        $out = [];
        foreach ($subslsRows as $kode => $r) {
            $out[$kode] = ['kode' => $kode, 'nama' => $namaLookup[$kode] ?? $r['nama'], 'data' => $r['data']];
        }
        return $out;
    }

    $len = SLS_LEVEL_LEN[$level] ?? 16;
    $agg = [];
    foreach ($subslsRows as $kode16 => $r) {
        $key = substr($kode16, 0, $len);
        if (!isset($agg[$key])) $agg[$key] = ['kode' => $key, 'nama' => $namaLookup[$key] ?? $key, 'data' => []];
        foreach ($r['data'] as $field => $val) {
            if ($val === null) continue;
            $agg[$key]['data'][$field] = ($agg[$key]['data'][$field] ?? 0) + $val;
        }
    }
    ksort($agg);
    return $agg;
}

/**
 * Rekap per PPL: gabungan data dari beberapa sheet, dikelompokkan berdasarkan
 * nama PPL yang bertugas di tiap kode Sub-SLS.
 *
 * $sheetsData: [ sheetKey => [kode16 => ['nama'=>.., 'data'=>[field=>val]]] ]
 * $pplSubsls : array [kode16 => 'Nama PPL'] — kode->nama PPL sederhana
 *              (hasil ekstraksi dari slsBuildKodeSlsPplPmlMap, atau langsung
 *              dari wilayah_lookup.php kalau belum pakai mapping baru)
 *
 * Return: [ namaPPL => ['jumlah_sls'=>int, 'sheets'=>[sheetKey=>[field=>totalVal]]] ]
 */
function slsAggregateByPPL(array $sheetsData, array $pplSubsls) {
    $out = [];
    foreach ($sheetsData as $sheetKey => $rows) {
        foreach ($rows as $kode16 => $r) {
            $ppl = $pplSubsls[$kode16] ?? '(Belum ada PPL)';
            if (!isset($out[$ppl])) $out[$ppl] = ['jumlah_sls' => 0, 'sheets' => []];
            foreach ($r['data'] as $field => $val) {
                if ($val === null) continue;
                $out[$ppl]['sheets'][$sheetKey][$field] = ($out[$ppl]['sheets'][$sheetKey][$field] ?? 0) + $val;
            }
        }
    }
    // Hitung jumlah Sub-SLS yang jadi tanggung jawab tiap PPL (dari sheet pertama yang tersedia)
    $firstSheet = array_key_first($sheetsData);
    if ($firstSheet) {
        foreach ($sheetsData[$firstSheet] as $kode16 => $r) {
            $ppl = $pplSubsls[$kode16] ?? '(Belum ada PPL)';
            $out[$ppl]['jumlah_sls'] = ($out[$ppl]['jumlah_sls'] ?? 0) + 1;
        }
    }
    ksort($out);
    return $out;
}

/**
 * Prelist & Selesai per email, dari tabel `progress` (bookmarklet SIHARAU utama,
 * sama persis sumber yang dipakai dashboard/index.php) — snapshot per tanggal,
 * dipakai tanggal TERBARU yang ada di tabel progress (independen dari tanggal
 * snapshot excel yg dipilih di ppl_dashboard.php, karena ini 2 sumber data beda).
 * "Prelist" = kolom total (total sample assignment dari FASIH utk petugas itu).
 * "Selesai" = kolom selesai (submitted+approved+rejected, sudah dihitung save.php).
 * "selesaiDelta" = Selesai tanggal terbaru dikurangi Selesai tanggal sebelumnya
 * (null kalau gak ada data tanggal sebelumnya utk email itu).
 * Return: [ email(lowercase) => ['prelist'=>int, 'selesai'=>int, 'selesaiDelta'=>int|null] ]
 */
function slsFetchAssignmentPrelistSelesai(PDO $db) {
    $out = [];
    try {
        $dates  = $db->query("SELECT DISTINCT tanggal FROM progress ORDER BY tanggal DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        $latest = $dates[0] ?? null;
        $prev   = $dates[1] ?? null;
        if (!$latest) return $out;

        $stmt = $db->prepare("SELECT LOWER(TRIM(email)) AS email, total, selesai FROM progress WHERE tanggal = ?");
        $stmt->execute([$latest]);
        foreach ($stmt->fetchAll() as $r) {
            $out[$r['email']] = ['prelist' => (int)$r['total'], 'selesai' => (int)$r['selesai'], 'selesaiDelta' => null];
        }

        if ($prev) {
            $stmtPrev = $db->prepare("SELECT LOWER(TRIM(email)) AS email, selesai FROM progress WHERE tanggal = ?");
            $stmtPrev->execute([$prev]);
            $prevMap = [];
            foreach ($stmtPrev->fetchAll() as $r) $prevMap[$r['email']] = (int)$r['selesai'];
            foreach ($out as $email => &$d) {
                if (isset($prevMap[$email])) $d['selesaiDelta'] = $d['selesai'] - $prevMap[$email];
            }
            unset($d);
        }
    } catch (Exception $e) {
        // Tabel progress belum ada / kosong
    }
    return $out;
}

/**
 * Sumber otoritatif mapping kode Sub-SLS -> PPL & PML.
 *
 * Urutan prioritas per kode Sub-SLS (16 digit):
 *  1. Match persis 16 digit di `progress_wilayah` (bookmarklet SIHARAU utama —
 *     paling lengkap cakupannya, tiap petugas selalu lapor region_code + email)
 *  2. Match 14 digit (SLS) di `progress_wilayah` — jaga2 kalau region_code lama
 *     cuma sampai level SLS, bukan Sub-SLS penuh
 *  3. Match persis 16 digit di `assignment` (SIHARAU Detail — pelengkap kalau
 *     ada kode yang somehow tidak muncul di progress_wilayah)
 *  4. Match 14 digit di `assignment`
 *  5. Fallback ke $pplSubslsFallback (wilayah_lookup.php / Master_Wilayah_kerja.xlsx)
 *  6. Kalau semua di atas gagal -> tidak masuk hasil (berarti benar2 belum ada
 *     petugas yang pernah lapor progress di wilayah itu)
 *
 * Tiap kode diambil dari email yang PALING BANYAK laporannya (progress_wilayah)
 * / item-nya (assignment) di kode itu, dan hanya dari TANGGAL TERBARU yang
 * tersedia (progress_wilayah), supaya kalau wilayah pernah pindah tangan PPL,
 * yang dipakai adalah assignment terbaru.
 *
 * PML TIDAK PERNAH langsung "memiliki" wilayah — PML selalu berupa agregat dari
 * PPL di bawahnya (dari kolom `pml` di `mapping_nama`, dicari lewat email PPL
 * yang ketemu di atas). Kalau email pencacah kebetulan terdaftar sebagai PML di
 * `mapping_nama.pml` (dia turun sendiri mendata), tetap diperlakukan sebagai PPL
 * biasa dan dikelompokkan di bawah nama dia sendiri sebagai PML.
 *
 * $targetKodes16: daftar semua kode Sub-SLS (16 digit) yang perlu diresolve
 *                 (biasanya array_keys dari data sls_import_data)
 *
 * Return: [ kode16 => ['ppl'=>nama, 'pml'=>namaPml, 'email'=>email|null, 'sumber'=>string] ]
 *         (kode yang gagal diresolve TIDAK muncul di array hasil)
 */
function slsBuildKodeSlsPplPmlMap(PDO $db, array $pplSubslsFallback, array $targetKodes16) {
    // 1) progress_wilayah: ambil email per region_code dari TANGGAL TERBARU yang
    //    laporannya paling besar (`total`) di tanggal itu, kalau ada >1 email
    //    lapor region_code yang sama di tanggal yang sama.
    $pwBy16 = [];
    $pwBy14 = [];
    try {
        $rows = $db->query("
            SELECT tanggal, region_code, email, total
            FROM progress_wilayah
            WHERE region_code != '' AND email != ''
            ORDER BY tanggal DESC, total DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            $rc = $r['region_code'];
            $entry = ['email' => strtolower(trim($r['email']))];
            if (strlen($rc) >= 16) {
                if (!isset($pwBy16[$rc])) $pwBy16[$rc] = $entry; // baris pertama = tanggal terbaru+total terbesar
            } else {
                $prefix14 = substr($rc, 0, 14);
                if (!isset($pwBy14[$prefix14])) $pwBy14[$prefix14] = $entry;
            }
        }
    } catch (Exception $e) {
        // Tabel progress_wilayah belum ada / kosong — lanjut pakai sumber lain
    }

    // 2) assignment: email dominan (paling banyak item) per kode_sls — pelengkap
    $asBy16 = [];
    $asBy14 = [];
    try {
        $rows = $db->query("
            SELECT kode_sls, email, COUNT(*) AS cnt
            FROM assignment
            WHERE kode_sls != '' AND email != ''
            GROUP BY kode_sls, email
        ")->fetchAll();
        foreach ($rows as $r) {
            $k = $r['kode_sls'];
            $entry = ['email' => strtolower(trim($r['email'])), 'cnt' => $r['cnt']];
            if (strlen($k) >= 16) {
                if (!isset($asBy16[$k]) || $r['cnt'] > $asBy16[$k]['cnt']) $asBy16[$k] = $entry;
            } else {
                $prefix14 = substr($k, 0, 14);
                if (!isset($asBy14[$prefix14]) || $r['cnt'] > $asBy14[$prefix14]['cnt']) $asBy14[$prefix14] = $entry;
            }
        }
    } catch (Exception $e) {
        // Tabel assignment belum ada / kosong — lanjut pakai fallback saja
    }

    // 2) Mapping email -> nama, pml (dari mapping_nama, sudah ada di sistem)
    $emailMap = [];
    $namaToPmlLegacy = []; // buat fallback berbasis nama (kode lama)
    $pmlNamesLegacy  = [];
    foreach ($db->query("SELECT email, nama, pml FROM mapping_nama") as $m) {
        $email = strtolower(trim($m['email']));
        $nama  = trim($m['nama']);
        $pml   = trim($m['pml']);
        if ($email !== '') $emailMap[$email] = ['nama' => $nama, 'pml' => $pml ?: '(Tanpa PML)'];
        if ($nama  !== '') $namaToPmlLegacy[strtolower($nama)] = $pml ?: '(Tanpa PML)';
        if ($pml   !== '') $pmlNamesLegacy[strtolower($pml)] = $pml;
    }

    // 3) Resolve tiap kode target dengan urutan prioritas:
    //    progress_wilayah(16) -> progress_wilayah(14) -> assignment(16) -> assignment(14) -> wilayah_lookup
    $out = [];
    $tiers = [
        ['map' => $pwBy16, 'key' => fn($k) => $k,              'sumber' => 'progress_wilayah'],
        ['map' => $pwBy14, 'key' => fn($k) => substr($k, 0, 14), 'sumber' => 'progress_wilayah_sls14'],
        ['map' => $asBy16, 'key' => fn($k) => $k,              'sumber' => 'assignment'],
        ['map' => $asBy14, 'key' => fn($k) => substr($k, 0, 14), 'sumber' => 'assignment_sls14'],
    ];

    foreach ($targetKodes16 as $kode) {
        $resolved = false;
        foreach ($tiers as $t) {
            $lookupKey = ($t['key'])($kode);
            if (!isset($t['map'][$lookupKey])) continue;
            $email = $t['map'][$lookupKey]['email'];
            $info  = $emailMap[$email] ?? null;
            if ($info && $info['nama'] !== '') {
                $out[$kode] = ['ppl' => $info['nama'], 'pml' => $info['pml'], 'email' => $email, 'sumber' => $t['sumber']];
                $resolved = true;
                break;
            }
        }
        if ($resolved) continue;

        if (isset($pplSubslsFallback[$kode])) {
            $nama = $pplSubslsFallback[$kode];
            $key  = strtolower(trim($nama));
            if (isset($namaToPmlLegacy[$key])) {
                $pml = $namaToPmlLegacy[$key];
            } elseif (isset($pmlNamesLegacy[$key])) {
                $pml = $pmlNamesLegacy[$key]; // PML turun sendiri jadi PPL
            } else {
                $pml = '(PML tidak diketahui)';
            }
            $out[$kode] = ['ppl' => $nama, 'pml' => $pml, 'email' => null, 'sumber' => 'wilayah_lookup'];
            continue;
        }
        // Gagal diresolve dari ketiganya -> tidak dimasukkan (caller anggap "Belum ada PPL")
    }

    return $out;
}

/**
 * Ambil mapping PML -> Korwil (Koordinator Wilayah), dari tabel mapping_korwil.
 * Return: [ pml_nama => korwil_nama ]
 */
function slsFetchKorwilMap(PDO $db) {
    $out = [];
    try {
        $rows = $db->query("SELECT pml_nama, korwil_nama FROM mapping_korwil")->fetchAll();
        foreach ($rows as $r) {
            $out[$r['pml_nama']] = $r['korwil_nama'];
        }
    } catch (Exception $e) {
        // Tabel belum ada (migrasi belum jalan) — anggap belum ada mapping
    }
    return $out;
}

/** Label gabungan buat ditampilkan: "Korwil / PML" kalau ada korwilnya, kalau enggak ya PML aja */
function slsKorwilPmlLabel(string $pmlNama, array $korwilMap): string {
    if ($pmlNama === '(Belum ada PPL)' || $pmlNama === '(PML tidak diketahui)' || $pmlNama === '(Tanpa PML)') {
        return $pmlNama;
    }
    $korwil = $korwilMap[$pmlNama] ?? null;
    return $korwil ? "$korwil / $pmlNama" : $pmlNama;
}

/**
 * Ambil semua catatan PPL sekaligus. Return: [ identity_key => ['catatan'=>, 'nama_tampil'=>, 'updated_at'=>] ]
 */
function slsFetchCatatanPpl(PDO $db): array {
    $out = [];
    try {
        foreach ($db->query("SELECT identity_key, nama_tampil, catatan, updated_at FROM catatan_ppl") as $r) {
            $out[$r['identity_key']] = $r;
        }
    } catch (Exception $e) {
        // tabel belum ada (migrasi belum jalan)
    }
    return $out;
}

/** Bikin identity_key konsisten (email lowercase, atau fallback 'noemail:nama') — sama pola dgn ppl_dashboard.php */
function slsIdentityKey(?string $email, string $nama): string {
    $email = trim((string)$email);
    return $email !== '' ? strtolower($email) : 'noemail:' . strtolower(trim($nama));
}

/**
 * Ambil semua catatan wilayah sekaligus. Return: [ kode => ['catatan'=>, 'nama_tampil'=>, 'level'=>, 'updated_at'=>] ]
 */
function slsFetchCatatanWilayah(PDO $db): array {
    $out = [];
    try {
        foreach ($db->query("SELECT kode, level, nama_tampil, catatan, updated_at FROM catatan_wilayah") as $r) {
            $out[$r['kode']] = $r;
        }
    } catch (Exception $e) {
        // tabel belum ada (migrasi belum jalan)
    }
    return $out;
}
