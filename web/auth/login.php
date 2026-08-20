<?php
require_once __DIR__ . '/../config.php';

session_name(SESSION_NAME);
session_start();

// Sudah login? redirect ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? BASE_URL . '/dashboard/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['nama']      = $user['nama'];
            $_SESSION['role']      = $user['role'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = datetime('now','localtime') WHERE id = ?")
               ->execute([$user['id']]);

            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    } else {
        $error = 'Isi username dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — SELARAS SE2026</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#1A2744 0%,#243158 60%,#2e1a00 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;padding:36px;width:100%;max-width:380px;box-shadow:0 24px 60px rgba(0,0,0,0.3)}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:28px;justify-content:center}
.logo-badge{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#E8560A,#F5A623);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff}
.logo-text strong{display:block;font-size:16px;font-weight:800;color:#1A2744}
.logo-text span{font-size:11px;color:#6B7280}
h2{font-size:20px;font-weight:800;color:#1A2744;margin-bottom:6px;text-align:center}
.sub{font-size:13px;color:#6B7280;text-align:center;margin-bottom:24px}
label{display:block;font-size:12px;font-weight:700;color:#1A2744;margin-bottom:5px}
input{width:100%;padding:10px 13px;border:1.5px solid #E4E4E4;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s;margin-bottom:14px}
input:focus{border-color:#E8560A}
.btn{width:100%;padding:12px;background:linear-gradient(135deg,#E8560A,#F5A623);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s}
.btn:hover{opacity:.92}
.error{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:10px 13px;font-size:13px;margin-bottom:16px}
.back{text-align:center;margin-top:16px;font-size:12.5px;color:#6B7280}
.back a{color:#E8560A;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-badge">SE</div>
    <div class="logo-text">
      <strong>SELARAS</strong>
      <span>Dashboard SE2026 Kupang</span>
    </div>
  </div>
  <h2>Masuk ke Dashboard</h2>
  <p class="sub">Khusus petugas BPS Kota Kupang</p>

  <?php if ($error): ?>
    <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
    <label>Username</label>
    <input type="text" name="username" autocomplete="username" autofocus
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password">
    <button type="submit" class="btn">Masuk →</button>
  </form>

  <div class="back"><a href="<?= BASE_URL ?>">← Kembali ke halaman utama</a></div>
</div>
</body>
</html>