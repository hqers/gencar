<?php
// ============================================================
// reset_password_admin.php — Reset/set password buat akun 'admin' bawaan
// (yang otomatis dibikin initSchema() di config.php kamu tiap getDB()
// dipanggil). Bukan "buat user baru" — ini emang khusus buat akun admin
// bawaan yang udah pasti ada by design.
// HAPUS FILE INI DARI SERVER setelah dipakai.
// ============================================================

require_once __DIR__ . '/config.php';

$db = getDB(); // ini otomatis mastiin akun 'admin' bawaan ada (initSchema)

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';
    $nama     = trim($_POST['nama'] ?? '');

    if (!$username || !$password) {
        $err = 'Username dan password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $err = 'Password minimal 6 karakter.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $existing = $db->prepare("SELECT id FROM users WHERE username = ?");
        $existing->execute([$username]);

        if ($existing->fetch()) {
            // Update akun yang udah ada (mis. akun 'admin' bawaan)
            $sql = "UPDATE users SET password = ?" . ($nama ? ", nama = ?" : "") . " WHERE username = ?";
            $params = $nama ? [$hash, $nama, $username] : [$hash, $username];
            $db->prepare($sql)->execute($params);
            $msg = "✅ Password akun '$username' berhasil di-reset! Sekarang bisa login pakai username ini + password baru kamu.";
        } else {
            // Username belum ada -> bikin baru
            $db->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, 'admin')")
               ->execute([$username, $hash, $nama ?: $username, ]);
            $msg = "✅ Akun '$username' berhasil dibuat sbg admin! Sekarang bisa login.";
        }
    }
}

// Tampilin daftar user yang ada sekarang, biar keliatan jelas
$daftarUser = $db->query("SELECT username, nama, role, last_login FROM users")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password Admin — SELARAS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#F5F6FA;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;padding:32px;width:100%;max-width:420px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
h2{font-size:19px;font-weight:800;color:#1A2744;margin-bottom:6px}
.sub{font-size:13px;color:#6B7280;margin-bottom:18px}
.msg{border-radius:8px;padding:12px 14px;font-size:13px;margin-bottom:16px}
.msg-ok{background:#DCFCE7;color:#16A34A;border:1px solid #BBF7D0}
.msg-err{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA}
.userlist{background:#F5F6FA;border-radius:8px;padding:12px 14px;font-size:12px;color:#374151;margin-bottom:18px}
.userlist div{padding:3px 0}
.userlist b{color:#1A2744}
label{display:block;font-size:12px;font-weight:700;color:#1A2744;margin-bottom:5px;margin-top:14px}
label:first-of-type{margin-top:0}
input{width:100%;padding:10px 13px;border:1.5px solid #E4E4E4;border-radius:8px;font-size:14px;font-family:inherit;outline:none}
input:focus{border-color:#E8560A}
.hint{font-size:11px;color:#9CA3AF;margin-top:3px}
.btn{width:100%;padding:12px;background:linear-gradient(135deg,#E8560A,#F5A623);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:20px}
a.login-link{display:block;text-align:center;margin-top:14px;color:#E8560A;font-weight:700;font-size:13px;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <h2>🔑 Reset Password Admin</h2>
  <p class="sub">Sistem ini otomatis punya akun <b>admin</b> bawaan — reset passwordnya di sini, atau isi username lain buat bikin akun baru.</p>

  <?php if ($msg): ?><div class="msg msg-ok"><?= $msg ?></div><?php endif ?>
  <?php if ($err): ?><div class="msg msg-err"><?= htmlspecialchars($err) ?></div><?php endif ?>

  <div class="userlist">
    <b>Akun yang ada sekarang (<?= count($daftarUser) ?>):</b>
    <?php foreach ($daftarUser as $u): ?>
      <div>• <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)<?= $u['nama'] ? ' — ' . htmlspecialchars($u['nama']) : '' ?></div>
    <?php endforeach ?>
  </div>

  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" value="admin" required>
    <div class="hint">Biarkan "admin" buat reset akun bawaan, atau ganti buat bikin akun baru.</div>
    <label>Nama (opsional)</label>
    <input type="text" name="nama" placeholder="mis. Admin Kupang">
    <label>Password Baru</label>
    <input type="password" name="password" required minlength="6">
    <button type="submit" class="btn">Simpan Password →</button>
  </form>

  <?php if ($msg): ?><a href="<?= BASE_URL ?>/auth/login.php" class="login-link">→ Ke halaman login</a><?php endif ?>
</div>
</body>
</html>
