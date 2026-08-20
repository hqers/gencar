<?php
// ============================================================
// api/save.php — Receiver data dari SIHARAU Bookmarklet
// POST endpoint, menggantikan Google Apps Script doPost
// ============================================================

require_once __DIR__ . '/../config.php';

set_time_limit(0); // tidak ada timeout
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Parse body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || ($body['action'] ?? '') !== 'batchSave') {
    jsonResponse(['success' => false, 'message' => 'Invalid payload'], 400);
}

$rows        = $body['rows']       ?? [];
$reportDate  = $body['reportDate'] ?? '';
$catatan     = $body['catatan']    ?? '';
$totalEl     = $body['totalElements'] ?? 0;

if (empty($rows) || empty($reportDate)) {
    jsonResponse(['success' => false, 'message' => 'Missing rows or reportDate'], 400);
}

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
    jsonResponse(['success' => false, 'message' => 'Invalid date format'], 400);
}

$db = getDB();
$inserted = 0;
$upserted = 0;
$errors   = 0;

// Ambil mapping nama sekali
$namaMap = [];
$pmlMap  = [];
$stmt = $db->query('SELECT email, nama, pml FROM mapping_nama');
foreach ($stmt->fetchAll() as $row) {
    $namaMap[strtolower($row['email'])] = $row['nama'];
    $pmlMap[strtolower($row['email'])]  = $row['pml'];
}

$db->beginTransaction();
try {
    $upsertSQL = "
        INSERT INTO progress
            (tanggal, email, total, open_count, draft, submitted, approved, rejected, selesai, progress_pct, catatan, updated_at)
        VALUES
            (:tanggal, :email, :total, :open_count, :draft, :submitted, :approved, :rejected, :selesai, :progress_pct, :catatan, datetime('now','localtime'))
        ON CONFLICT(tanggal, email) DO UPDATE SET
            total        = excluded.total,
            open_count   = excluded.open_count,
            draft        = excluded.draft,
            submitted    = excluded.submitted,
            approved     = excluded.approved,
            rejected     = excluded.rejected,
            selesai      = excluded.selesai,
            progress_pct = excluded.progress_pct,
            catatan      = excluded.catatan,
            updated_at   = datetime('now','localtime')
    ";
    $stmt = $db->prepare($upsertSQL);

    // Prepared statement untuk progress_wilayah
    $stmtWilayah = $db->prepare("
        INSERT INTO progress_wilayah
            (tanggal, email, region_code, total, open_count, draft, submitted, approved, rejected, selesai, catatan, updated_at)
        VALUES
            (:tanggal, :email, :region_code, :total, :open_count, :draft, :submitted, :approved, :rejected, :selesai, :catatan, datetime('now','localtime'))
        ON CONFLICT(tanggal, email, region_code) DO UPDATE SET
            total      = excluded.total,
            open_count = excluded.open_count,
            draft      = excluded.draft,
            submitted  = excluded.submitted,
            approved   = excluded.approved,
            rejected   = excluded.rejected,
            selesai    = excluded.selesai,
            catatan    = excluded.catatan,
            updated_at = datetime('now','localtime')
    ");

    foreach ($rows as $petugas) {
        $email = strtolower(trim($petugas['email'] ?? ''));
        if (!$email) { $errors++; continue; }

        $submitted = 0; $approved = 0; $open = 0; $draft = 0; $rejected = 0;

        foreach ($petugas['regionSummary'] ?? [] as $region) {
            $rCode = $region['regionCode'] ?? '';
            $rTotal = (int)($region['total'] ?? 0);
            $rSub = 0; $rApp = 0; $rOpen = 0; $rDraft = 0; $rRej = 0;

            foreach ($region['statusBreakdown'] ?? [] as $s) {
                $st = strtoupper($s['status'] ?? '');
                $c  = (int)($s['count'] ?? 0);
                if     (strpos($st, 'SUBMITTED') !== false) { $submitted += $c; $rSub  += $c; }
                elseif (strpos($st, 'APPROVED')  !== false) { $approved  += $c; $rApp  += $c; }
                elseif (strpos($st, 'OPEN')      !== false) { $open      += $c; $rOpen += $c; }
                elseif (strpos($st, 'DRAFT')     !== false) { $draft     += $c; $rDraft+= $c; }
                elseif (strpos($st, 'REJECTED')  !== false) { $rejected  += $c; $rRej  += $c; }
                // EDITED BY Admin Kabupaten → dianggap selesai (masuk approved)
                elseif (strpos($st, 'EDITED')    !== false) { $approved  += $c; $rApp  += $c; }
                // REVOKED BY Pengawas → dikembalikan, dianggap submitted ulang
                elseif (strpos($st, 'REVOKED')   !== false) { $submitted += $c; $rSub  += $c; }
                // COMPLETE BY Admin → selesai (masuk approved)
                elseif (strpos($st, 'COMPLETE')  !== false) { $approved  += $c; $rApp  += $c; }
            }

            // Simpan per wilayah kalau ada regionCode
            if ($rCode) {
                $rSelesai = $rSub + $rApp + $rRej;
                $stmtWilayah->execute([
                    ':tanggal'     => $reportDate,
                    ':email'       => $email,
                    ':region_code' => $rCode,
                    ':total'       => $rTotal,
                    ':open_count'  => $rOpen,
                    ':draft'       => $rDraft,
                    ':submitted'   => $rSub,
                    ':approved'    => $rApp,
                    ':rejected'    => $rRej,
                    ':selesai'     => $rSelesai,
                    ':catatan'     => $catatan,
                ]);
            }
        }

        $total    = (int)($petugas['total'] ?? 0);
        $selesai  = $submitted + $approved + $rejected;
        $progress = $total > 0 ? round($selesai / $total * 100, 1) : 0;

        // Upsert mapping nama kalau belum ada
        $namaKey = $namaMap[$email] ?? null;
        if (!$namaKey) {
            $fullname = trim($petugas['username'] ?? $petugas['email'] ?? '');
            $db->prepare("INSERT OR IGNORE INTO mapping_nama (email, nama) VALUES (?, ?)")
               ->execute([$email, $fullname]);
        }

        $stmt->execute([
            ':tanggal'      => $reportDate,
            ':email'        => $email,
            ':total'        => $total,
            ':open_count'   => $open,
            ':draft'        => $draft,
            ':submitted'    => $submitted,
            ':approved'     => $approved,
            ':rejected'     => $rejected,
            ':selesai'      => $selesai,
            ':progress_pct' => $progress,
            ':catatan'      => $catatan,
        ]);

        // SQLite: changes() = 1 insert, 2 = update
        $changes = $db->query('SELECT changes()')->fetchColumn();
        if ($db->lastInsertId() && $changes == 1) $inserted++;
        else $upserted++;
    }

    // Log import
    $db->prepare("
        INSERT INTO import_log (tanggal, catatan, total_petugas, inserted, upserted, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $reportDate, $catatan, count($rows),
        $inserted, $upserted,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $db->commit();

    jsonResponse([
        'success'  => true,
        'inserted' => $inserted,
        'upserted' => $upserted,
        'errors'   => $errors,
        'total'    => count($rows),
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
