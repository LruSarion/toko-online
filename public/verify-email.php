
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$pesan = '';
$berhasil = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $pesan = 'Tautan verifikasi tidak valid. Periksa kembali email kamu.';
} else {
    try {
        $hash = hash('sha256', $token);

        $st = $pdo->prepare(
            'SELECT id, email
             FROM pelanggan
             WHERE email_verification_token = ?
               AND email_verification_expires_at > NOW()
               AND email_verified_at IS NULL
             LIMIT 1'
        );
        $st->execute([$hash]);
        $pelanggan = $st->fetch(PDO::FETCH_ASSOC);

        if (!$pelanggan) {
            $pesan = 'Tautan verifikasi sudah digunakan, kedaluwarsa, atau tidak valid.';
        } else {
            $update = $pdo->prepare(
                'UPDATE pelanggan
                 SET email_verified_at = NOW(),
                     email_verification_token = NULL,
                     email_verification_expires_at = NULL
                 WHERE id = ?
                   AND email_verified_at IS NULL
                   AND email_verification_token = ?'
            );
            $update->execute([(int)$pelanggan['id'], $hash]);

            if ($update->rowCount() === 1) {
                $berhasil = true;
                $pesan = 'Email berhasil diverifikasi! Sekarang kamu bisa masuk ke akun Tokkoku Laku.';
            } else {
                $pesan = 'Verifikasi belum berhasil. Silakan coba lagi.';
            }
        }
    } catch (Throwable $e) {
        error_log('Verifikasi email gagal: ' . $e->getMessage());
        $pesan = 'Terjadi kendala saat memverifikasi email. Silakan coba lagi nanti.';
    }
}

$page = ['title' => 'Verifikasi email', 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>

<div class="auth">
  <div class="auth-main">
    <div class="auth-box">
      <h1><?= $berhasil ? 'Email Terverifikasi' : 'Verifikasi Email' ?></h1>
      <p><?= e($pesan) ?></p>

      <?php if ($berhasil): ?>
        <a class="btn btn-primary btn-block" href="login.php">Masuk ke akun</a>
      <?php else: ?>
        <a class="btn btn-primary btn-block" href="register.php">Kembali ke pendaftaran</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
