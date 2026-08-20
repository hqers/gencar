<?php
// ============================================================
// lib/sls_import_migrate.php — Bikin tabel kalau belum ada
// Panggil migrateSlsImportTables($db) sekali di awal request.
// ============================================================

function migrateSlsImportTables(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS sls_import_data (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal    TEXT NOT NULL,          -- tanggal snapshot (YYYY-MM-DD), dari 'Diperbarui' di file
            sheet_key  TEXT NOT NULL,          -- progres_pendataan | skala_usaha | ... (lihat SLS_SHEET_DEFS)
            kode       TEXT NOT NULL,          -- kode wilayah BPS (4/7/10/14/16 digit)
            level      TEXT NOT NULL,          -- kabkota | kecamatan | kelurahan | sls | subsls
            nama       TEXT,
            data_json  TEXT NOT NULL,          -- field metrik sheet tsb, dalam JSON
            updated_at TEXT NOT NULL,
            UNIQUE(tanggal, sheet_key, kode)
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sls_import_data_lookup ON sls_import_data(tanggal, sheet_key, kode)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sls_import_data_kode   ON sls_import_data(kode)");

    $db->exec("
        CREATE TABLE IF NOT EXISTS sls_import_log (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal        TEXT NOT NULL,
            filename       TEXT,
            kecamatan_kode TEXT,
            kecamatan_nama TEXT,
            sheets_found   TEXT,
            sheets_missing TEXT,
            row_count      INTEGER,
            uploaded_by    TEXT,
            created_at     TEXT NOT NULL
        )
    ");

    // Mapping Korwil (Koordinator Wilayah) -> PML. Ini struktur internal BPS
    // Kota Payakumbuh sendiri, gak ada sumbernya dari FASIH/SIHARAU, jadi
    // dikelola manual lewat mapping_korwil.php.
    $db->exec("
        CREATE TABLE IF NOT EXISTS mapping_korwil (
            pml_nama   TEXT PRIMARY KEY,
            korwil_nama TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    ");

    // Catatan/keterangan per PPL — diisi admin, muncul di ppl_dashboard.php
    // (Rekap PPL) DAN dashboard_wilayah.php (halaman Wilayah Kerja per PPL),
    // biar 1 catatan kelihatan konsisten di kedua tempat. Kunci pakai email
    // kalau ada (paling akurat), fallback nama kalau PPL gak punya email —
    // sama persis pola identitas yang dipakai di ppl_dashboard.php.
    $db->exec("
        CREATE TABLE IF NOT EXISTS catatan_ppl (
            identity_key TEXT PRIMARY KEY, -- email(lowercase) ATAU 'noemail:nama'
            nama_tampil  TEXT NOT NULL,    -- nama PPL, buat tampilan admin
            catatan      TEXT NOT NULL,
            updated_by   TEXT,
            updated_at   TEXT NOT NULL
        )
    ");

    // Catatan/keterangan per BARIS WILAYAH (kelurahan/SLS/Sub-SLS) di
    // dashboard_wilayah.php — beda dari catatan_ppl di atas (yg per PPL).
    // Kode bisa 10/14/16 digit tergantung level baris yg dikasih catatan.
    $db->exec("
        CREATE TABLE IF NOT EXISTS catatan_wilayah (
            kode         TEXT PRIMARY KEY,
            level        TEXT NOT NULL, -- kelurahan|sls|subsls
            nama_tampil  TEXT NOT NULL,
            catatan      TEXT NOT NULL,
            updated_at   TEXT NOT NULL
        )
    ");
}
