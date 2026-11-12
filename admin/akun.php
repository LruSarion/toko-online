<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../'; $A = '';
require_admin();
$st = $pdo->prepare('SELECT * FROM admin WHERE id = ?');
$st->execute([admin_id()]);
$a = $st->fetch();
if (!$a) { redirect('logout.php'); }

$err = []; $log = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    if (post('action') === 'password') {
        $lama = (string)($_POST['lama'] ?? ''); $baru = (string)($_POST['baru'] ?? ''); $baru2 = (string)($_POST['baru2'] ?? '');
        if (!admin_cek_password($pdo, $a, $lama)) { $err['lama'] = 'Password saat ini salah.'; }
        elseif (strlen($baru) < 10) { $err['baru'] = 'Password baru minimal 10 karakter.'; }
        elseif ($baru === $a['username'] || $baru === $lama) { $err['baru'] = 'Gunakan password yang berbeda dan tidak mudah ditebak.'; }
        elseif ($baru !== $baru2) { $err['baru2'] = 'Konfirmasi password tidak sama.'; }
        if (!$err) {
            $pdo->prepare('UPDATE admin SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $a['id']]);
            session_regenerate_id(true);
            flash('success', 'Password admin berhasil diganti.');
            redirect('akun.php');
        }
    } elseif (post('action') === 'migrasi') {
        $log = jalankan_migrasi($pdo);
    }
}
$versi = (string)scalar($pdo, "SELECT nilai FROM pengaturan WHERE kunci = 'schema_version'");
$fe = function (string $k) use ($err) { return isset($err[$k]) ? '<div class="err">' . e($err[$k]) . '</div>' : ''; };
$adm = ['title' => 'Akun & sistem', 'nav' => 'akun'];
require __DIR__ . '/includes/layout_top.php';
?>
<div class="fgrid">
  <section class="card">
    <h2>Ganti password admin</h2>
    <form method="post" data-once>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field <?= isset($err['lama']) ? 'has-err' : '' ?>"><label for="lama">Password saat ini</label><input class="input" id="lama" type="password" name="lama" autocomplete="current-password" required><?= $fe('lama') ?></div>
      <div class="field <?= isset($err['baru']) ? 'has-err' : '' ?>"><label for="baru">Password baru</label><input class="input" id="baru" type="password" name="baru" autocomplete="new-password" required><?= $fe('baru') ?><div class="hint">Minimal 10 karakter. Gunakan kombinasi huruf, angka, dan simbol.</div></div>
      <div class="field <?= isset($err['baru2']) ? 'has-err' : '' ?>"><label for="baru2">Ulangi password baru</label><input class="input" id="baru2" type="password" name="baru2" autocomplete="new-password" required><?= $fe('baru2') ?></div>
      <button class="btn btn-primary">Simpan password</button>
    </form>
  </section>
  <section class="card">
    <h2>Sistem</h2>
    <dl class="kv admin">
      <dt>Login sebagai</dt><dd><?= e($a['username']) ?></dd>
      <dt>Versi skema DB</dt><dd><?= e($versi ?: '-') ?> <span class="muted">(terbaru: <?= (int)SCHEMA_VERSION ?>)</span></dd>
      <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
      <dt>GD / WebP</dt><dd><?= function_exists('imagewebp') ? 'Tersedia (thumbnail otomatis aktif)' : 'Tidak tersedia (gambar asli dipakai apa adanya)' ?></dd>
    </dl>
    <form method="post" style="margin-top:18px"><?= csrf_field() ?><input type="hidden" name="action" value="migrasi"><button class="btn btn-ghost btn-sm">Jalankan ulang migrasi database</button></form>
    <p class="muted" style="font-size:12.5px;margin:8px 0 0">Migrasi hanya menambah tabel/kolom yang belum ada dan aman diulang.</p>
    <?php if ($log !== null): ?><div class="log" style="margin-top:14px"><?= e(implode("\n", $log) ?: 'Tidak ada perubahan.') ?></div><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
