<?php
// ============================================================
// fix_tabel_catatan.php — SEKALI JALAN, bikin tabel catatan_wilayah &
// catatan_ppl langsung (buat jaga-jaga kalau opcache bikin
// lib/sls_import_migrate.php yang baru belum kepakai). Hapus file ini
// dari server setelah dipakai sekali.
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS catatan_wilayah (
            kode         TEXT PRIMARY KEY,
            level        TEXT NOT NULL,
            nama_tampil  TEXT NOT NULL,
            catatan      TEXT NOT NULL,
            updated_at   TEXT NOT NULL
        )
    ");
    echo "✅ Tabel catatan_wilayah: OK (udah ada / berhasil dibuat)\n";
} catch (Exception $e) {
    echo "❌ Gagal bikin catatan_wilayah: " . $e->getMessage() . "\n";
}

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS catatan_ppl (
            identity_key TEXT PRIMARY KEY,
            nama_tampil  TEXT NOT NULL,
            catatan      TEXT NOT NULL,
            updated_by   TEXT,
            updated_at   TEXT NOT NULL
        )
    ");
    echo "✅ Tabel catatan_ppl: OK (udah ada / berhasil dibuat)\n";
} catch (Exception $e) {
    echo "❌ Gagal bikin catatan_ppl: " . $e->getMessage() . "\n";
}

// Verifikasi via query langsung ke sqlite_master
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('catatan_wilayah','catatan_ppl')")->fetchAll(PDO::FETCH_COLUMN);
echo "\nTabel yang beneran terdeteksi di database sekarang: " . implode(', ', $tables) . "\n";
