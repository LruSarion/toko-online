
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$ROOT = '../';
$next = safe_next(qs('next', post('next')), 'index.php');

if (pelanggan_id()) {
    redirect($next);
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $email = strtolower(trim(post('email')));
    $password = (string)($_POST['password'] ?? '');
    $kunci = throttle_key('pelanggan', $email);

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } elseif (throttle_blocked($pdo, $kunci)) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.';
    } else {
        $st = $pdo->prepare(
            'SELECT * FROM pelanggan WHERE email = ? LIMIT 1'
        );
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if ($u && password_verify($password, $u['password'])) {
            /*
             * Akun lama: dibuat sebelum fitur verifikasi diterapkan.
             * Akun baru: wajib memiliki email_verified_at.
             *
             * Sesuaikan tanggal batas ini dengan waktu saat fitur
             * verifikasi mulai diterapkan di website.
             */
            $batasAkunLama = '2026-10-19 00:00:00';

            $akunLama = isset($u['created_at'])
                && $u['created_at'] < $batasAkunLama;

            $sudahVerifikasi = !empty($u['email_verified_at']);

            if (!$akunLama && !$sudahVerifikasi) {
                throttle_clear($pdo, $kunci);
                $error = 'Email kamu belum diverifikasi. Silakan buka tautan verifikasi yang dikirim ke email saat mendaftar.';
            } else {
                throttle_clear($pdo, $kunci);

                session_regenerate_id(true);

                $_SESSION['pelanggan_id'] = (int)$u['id'];
                $_SESSION['pelanggan_nama'] = $u['nama'];
                $_SESSION['pelanggan_email'] = $u['email'];

                if (
                    password_needs_rehash(
                        $u['password'],
                        PASSWORD_DEFAULT
                    )
                ) {
                    $pdo->prepare(
                        'UPDATE pelanggan SET password = ? WHERE id = ?'
                    )->execute([
                        password_hash($password, PASSWORD_DEFAULT),
                        (int)$u['id']
                    ]);
                }

                redirect($next);
            }
        } else {
            throttle_fail($pdo, $kunci);
            $error = 'Email atau password salah.';
        }
    }
}

$page = ['title' => 'Masuk', 'noindex' => true];

require ROOT_PATH . '/includes/store_header.php';
?>

<div class="auth">
  <div class="auth-side">
    <img src="assets/img/logo.webp" alt="<?= e($APP['nama']) ?>">
    <p><?= e($APP['tagline']) ?>. Masuk untuk memantau pesanan dan mengumpulkan poin.</p>
  </div>

  <div class="auth-main">
    <div class="auth-box">
      <h1>Selamat datang kembali</h1>
      <p>Masuk ke akunmu untuk melanjutkan belanja.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="field">
          <label for="email">Email</label>
          <input class="input" id="email" type="email" name="email"
                 value="<?= e($email) ?>"
                 autocomplete="email" required autofocus>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" type="password"
                 name="password" autocomplete="current-password" required>
        </div>

        <button class="btn btn-primary btn-block btn-lg" type="submit">
          Masuk
        </button>
      </form>

      <div class="auth-alt">
        Belum punya akun?
        <a href="register.php?next=<?= urlencode($next) ?>"><b>Daftar sekarang</b></a>
      </div>
    </div>
  </div>
</div>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
