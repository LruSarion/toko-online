
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$ROOT = '../';
$next = safe_next(qs('next', post('next')), 'index.php');

if (pelanggan_id()) {
    redirect($next);
}

$f = [
    'nama' => '',
    'email' => '',
    'no_hp' => '',
    'alamat' => ''
];

$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }

    $f['email'] = strtolower(trim($f['email']));

    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if ($f['nama'] === '') {
        $err['nama'] = 'Nama wajib diisi.';
    }

    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Masukkan email yang valid.';
    }

    if (
        $f['no_hp'] !== '' &&
        strlen(preg_replace('/\D/', '', $f['no_hp'])) < 9
    ) {
        $err['no_hp'] = 'Nomor HP tidak valid.';
    }

    if (strlen($pw) < 8) {
        $err['password'] = 'Password minimal 8 karakter.';
    } elseif ($pw !== $pw2) {
        $err['password2'] = 'Konfirmasi password tidak sama.';
    }

    if (
        !$err &&
        scalar(
            $pdo,
            'SELECT id FROM pelanggan WHERE email = ?',
            [$f['email']]
        )
    ) {
        $err['email'] = 'Email sudah terdaftar. Silakan masuk.';
    }

    if (!$err) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $transactionStarted = false;

        try {
            // Muat konfigurasi email dan PHPMailer.
            $mailConfig = require ROOT_PATH . '/config/mail.php';

            require_once ROOT_PATH . '/includes/phpmailer/src/Exception.php';
            require_once ROOT_PATH . '/includes/phpmailer/src/PHPMailer.php';
            require_once ROOT_PATH . '/includes/phpmailer/src/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Konfigurasi SMTP Gmail.
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure =
                \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)$mailConfig['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;

            $mail->setFrom(
                $mailConfig['from_email'],
                $mailConfig['from_name']
            );

            $mail->addAddress($f['email'], $f['nama']);

            $verificationUrl = rtrim(origin(), '/')
                . '/verify-email.php?token='
                . urlencode($token);

            $mail->isHTML(true);
            $mail->Subject = 'Verifikasi Email Tokkoku Laku';

            $mail->Body =
                '<div style="font-family:Arial,sans-serif;line-height:1.6">'
                . '<h2>Selamat datang di Tokkoku Laku!</h2>'
                . '<p>Halo ' . e($f['nama']) . ',</p>'
                . '<p>Terima kasih sudah mendaftar. '
                . 'Klik tombol berikut untuk memverifikasi email kamu.</p>'
                . '<p><a href="' . e($verificationUrl) . '" '
                . 'style="display:inline-block;padding:12px 20px;'
                . 'background:#2563eb;color:#ffffff;text-decoration:none;'
                . 'border-radius:6px">Verifikasi Email</a></p>'
                . '<p>Tautan ini berlaku selama 24 jam dan hanya bisa '
                . 'digunakan satu kali.</p>'
                . '<p>Jika kamu tidak merasa mendaftar, abaikan email ini.</p>'
                . '<p>Salam,<br>Tokkoku Laku</p>'
                . '</div>';

            $mail->AltBody =
                "Halo {$f['nama']},\n\n"
                . "Verifikasi email Tokkoku Laku melalui tautan berikut:\n"
                . $verificationUrl . "\n\n"
                . "Tautan berlaku selama 24 jam dan hanya bisa digunakan "
                . "satu kali.\n\n"
                . "Jika kamu tidak merasa mendaftar, abaikan email ini.";

            // Simpan akun dan token verifikasi.
            $pdo->beginTransaction();
            $transactionStarted = true;

            $st = $pdo->prepare(
                'INSERT INTO pelanggan
                (
                    nama,
                    email,
                    password,
                    no_hp,
                    alamat,
                    email_verification_token,
                    email_verification_expires_at
                )
                VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
            );

            $st->execute([
                $f['nama'],
                $f['email'],
                password_hash($pw, PASSWORD_DEFAULT),
                $f['no_hp'] ?: null,
                $f['alamat'] ?: null,
                $tokenHash
            ]);

            // Kirim email verifikasi.
            $mail->send();

            $pdo->commit();
            $transactionStarted = false;

            flash(
                'success',
                'Pendaftaran berhasil! Silakan periksa email kamu '
                . 'dan klik tautan verifikasi sebelum masuk.'
            );

            redirect('login.php?next=' . urlencode($next));

        } catch (\Throwable $e) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Catat error di log server tanpa menampilkannya ke pengunjung.
            error_log(
                'Registrasi/verifikasi email gagal: '
                . $e->getMessage()
            );

            if (
                $e instanceof PDOException &&
                $e->getCode() === '23000'
            ) {
                $err['email'] =
                    'Email sudah terdaftar. Silakan masuk.';
            } else {
                $err['umum'] =
                    'Pendaftaran atau pengiriman email belum berhasil. '
                    . 'Silakan coba lagi nanti.';
            }
        }
    }
}

$fe = function (string $k) use ($err) {
    return isset($err[$k])
        ? '<div class="err">' . e($err[$k]) . '</div>'
        : '';
};

$page = ['title' => 'Daftar akun', 'noindex' => true];

require ROOT_PATH . '/includes/store_header.php';
?>

<div class="auth">
  <div class="auth-side">
    <img src="assets/img/logo.webp" alt="<?= e($APP['nama']) ?>">
    <p>Buat akun gratis: checkout lebih cepat, pantau pesanan, dan kumpulkan poin di setiap belanja.</p>
  </div>

  <div class="auth-main">
    <div class="auth-box">
      <h1>Buat akun baru</h1>
      <p>Hanya butuh satu menit.</p>

      <?php if (isset($err['umum'])): ?>
        <div class="alert alert-error"><?= e($err['umum']) ?></div>
      <?php endif; ?>

      <form method="post" novalidate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="field <?= isset($err['nama']) ? 'has-err' : '' ?>">
          <label for="nama">Nama lengkap</label>
          <input class="input" id="nama" name="nama"
                 value="<?= e($f['nama']) ?>"
                 autocomplete="name" required>
          <?= $fe('nama') ?>
        </div>

        <div class="field <?= isset($err['email']) ? 'has-err' : '' ?>">
          <label for="email">Email</label>
          <input class="input" id="email" type="email" name="email"
                 value="<?= e($f['email']) ?>"
                 autocomplete="email" required>
          <?= $fe('email') ?>
        </div>

        <div class="field <?= isset($err['no_hp']) ? 'has-err' : '' ?>">
          <label for="no_hp">Nomor HP <span class="muted">(opsional)</span></label>
          <input class="input" id="no_hp" name="no_hp"
                 value="<?= e($f['no_hp']) ?>"
                 inputmode="tel" autocomplete="tel">
          <?= $fe('no_hp') ?>
        </div>

        <div class="grid2">
          <div class="field <?= isset($err['password']) ? 'has-err' : '' ?>">
            <label for="password">Password</label>
            <input class="input" id="password" type="password"
                   name="password" autocomplete="new-password" required>
            <?= $fe('password') ?>
            <div class="hint">Minimal 8 karakter.</div>
          </div>

          <div class="field <?= isset($err['password2']) ? 'has-err' : '' ?>">
            <label for="password2">Ulangi password</label>
            <input class="input" id="password2" type="password"
                   name="password2" autocomplete="new-password" required>
            <?= $fe('password2') ?>
          </div>
        </div>

        <div class="field">
          <label for="alamat">Alamat <span class="muted">(opsional, untuk mempercepat checkout)</span></label>
          <textarea class="textarea" id="alamat" name="alamat"
                    style="min-height:70px"><?= e($f['alamat']) ?></textarea>
        </div>

        <button class="btn btn-primary btn-block btn-lg" type="submit">
          Daftar
        </button>
      </form>

      <div class="auth-alt">
        Sudah punya akun?
        <a href="login.php?next=<?= urlencode($next) ?>"><b>Masuk</b></a>
      </div>
    </div>
  </div>
</div>

<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
