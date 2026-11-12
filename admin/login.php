<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
if (admin_id()) { redirect('index.php'); }
$error = ''; $username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $username = post('username');
    $password = (string)($_POST['password'] ?? '');
    $kunci = throttle_key('admin', $username);
    if ($username === '' || $password === '') { $error = 'Username dan password wajib diisi.'; }
    elseif (throttle_blocked($pdo, $kunci)) { $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'; }
    else {
        $st = $pdo->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $a = $st->fetch();
        if ($a && admin_cek_password($pdo, $a, $password)) {
            throttle_clear($pdo, $kunci);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_username'] = $a['username'];
            redirect('index.php');
        }
        throttle_fail($pdo, $kunci);
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Login admin — <?= e($APP['nama']) ?></title>
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600&display=swap">
<link rel="stylesheet" href="../assets/css/store.css">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>Panel admin</h1>
    <p class="muted" style="margin-bottom:22px"><?= e($APP['nama']) ?> — masuk untuk mengelola toko.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" data-once>
      <?= csrf_field() ?>
      <div class="field"><label for="u">Username</label><input class="input" id="u" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></div>
      <div class="field"><label for="p">Password</label><input class="input" id="p" type="password" name="password" autocomplete="current-password" required></div>
      <button class="btn btn-primary btn-block btn-lg">Masuk</button>
    </form>
    <p class="center" style="margin:18px 0 0"><a href="../">&larr; Kembali ke toko</a></p>
  </div>
</div>
<script src="../assets/js/store.js"></script>
</body>
</html>
