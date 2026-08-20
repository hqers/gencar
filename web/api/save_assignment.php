<?php
// ============================================================
// api/save_assignment.php — Receiver data detail per usaha
// dari bookmarklet SIHARAU Detail
// ============================================================

require_once __DIR__ . '/../config.php';

set_time_limit(0);
setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || ($body['action'] ?? '') !== 'saveAssignment') {
    jsonResponse(['success' => false, 'message' => 'Invalid payload'], 400);
}

$items      = $body['items']      ?? [];
$kodeKec    = $body['kodeKec']    ?? '';
$tanggal    = $body['tanggal']    ?? date('Y-m-d');

if (empty($items)) {
    jsonResponse(['success' => false, 'message' => 'No items'], 400);
}

$db = getDB();

$stmt = $db->prepare("
    INSERT INTO assignment
        (id, region_code, kode_kec, kode_desa, kode_sls, status, email,
         sample_type, data6, latitude, longitude, tanggal_ambil, updated_at)
    VALUES
        (:id, :region_code, :kode_kec, :kode_desa, :kode_sls, :status, :email,
         :sample_type, :data6, :latitude, :longitude, :tanggal_ambil, datetime('now','localtime'))
    ON CONFLICT(id) DO UPDATE SET
        status        = excluded.status,
        email         = excluded.email,
        sample_type   = excluded.sample_type,
        data6         = excluded.data6,
        latitude      = excluded.latitude,
        longitude     = excluded.longitude,
        tanggal_ambil = excluded.tanggal_ambil,
        updated_at    = datetime('now','localtime')
");

$inserted = 0; $updated = 0; $errors = 0;

$db->beginTransaction();
try {
    foreach ($items as $item) {
        $rc    = $item['regionCode'] ?? '';
        $kDesa = strlen($rc) >= 10 ? substr($rc, 0, 10) : '';
        $kSls  = strlen($rc) >= 16 ? substr($rc, 0, 16) : $rc;

        try {
            $stmt->execute([
                ':id'           => $item['id'] ?? '',
                ':region_code'  => $rc,
                ':kode_kec'     => $kodeKec,
                ':kode_desa'    => $kDesa,
                ':kode_sls'     => $kSls,
                ':status'       => $item['status'] ?? '',
                ':email'        => strtolower($item['email'] ?? ''),
                ':sample_type'  => $item['sampleType'] ?? null,
                ':data6'        => $item['data6'] ?? '',
                ':latitude'     => $item['lat'] ?? null,
                ':longitude'    => $item['lng'] ?? null,
                ':tanggal_ambil'=> $tanggal,
            ]);
            $changes = $db->query('SELECT changes()')->fetchColumn();
            if ($changes == 1) $inserted++; else $updated++;
        } catch (Exception $e) {
            $errors++;
        }
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

// Hitung total per kecamatan di database
$total = $db->prepare("SELECT COUNT(*) FROM assignment WHERE kode_kec=?");
$total->execute([$kodeKec]);
$totalKec = $total->fetchColumn();

jsonResponse([
    'success'   => true,
    'inserted'  => $inserted,
    'updated'   => $updated,
    'errors'    => $errors,
    'total_kec' => $totalKec,
    'kode_kec'  => $kodeKec,
]);