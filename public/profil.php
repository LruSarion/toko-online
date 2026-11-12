<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$ROOT = '../';
require_login();
$st = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?');
$st->execute([pelanggan_id()]);
$u = $st->fetch();
if (!$u) { $_SESSION = []; redirect('login.php'); }

$err = []; $errPw = [];
$f = ['nama' => $u['nama'], 'no_hp' => (string)$u['no_hp'], 'alamat' => (string)$u['alamat']];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    if (post('action') === 'profil') {
        foreach ($f as $k => $_) { $f[$k] = post($k); }
        if ($f['nama'] === '') { $err['nama'] = 'Nama wajib diisi.'; }
        if ($f['no_hp'] !== '' && strlen(preg_replace('/\D/', '', $f['no_hp'])) < 9) { $err['no_hp'] = 'Nomor HP tidak valid.'; }
        if (!$err) {
            $pdo->prepare('UPDATE pelanggan SET nama = ?, no_hp = ?, alamat = ? WHERE id = ?')->execute([$f['nama'], $f['no_hp'] ?: null, $f['alamat'] ?: null, $u['id']]);
            $_SESSION['pelanggan_nama'] = $f['nama'];
            flash('success', 'Profil berhasil diperbarui.');
            redirect('profil.php');
        }
    } elseif (post('action') === 'password') {
        $lama = (string)($_POST['lama'] ?? ''); $baru = (string)($_POST['baru'] ?? ''); $baru2 = (string)($_POST['baru2'] ?? '');
        if (!password_verify($lama, $u['password'])) { $errPw['lama'] = 'Password saat ini salah.'; }
        elseif (strlen($baru) < 8) { $errPw['baru'] = 'Password baru minimal 8 karakter.'; }
        elseif ($baru !== $baru2) { $errPw['baru2'] = 'Konfirmasi password tidak sama.'; }
        if (!$errPw) {
            $pdo->prepare('UPDATE pelanggan SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('success', 'Password berhasil diganti.');
            redirect('profil.php');
        }
    }
}
$st = $pdo->prepare('SELECT * FROM riwayat_poin WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 15');
$st->execute([$u['id']]);
$riwayatPoin = $st->fetchAll();
$jmlPesanan = (int)scalar($pdo, "SELECT COUNT(*) FROM pesanan WHERE pelanggan_id = ? AND status <> 'Dibatalkan'", [$u['id']]);
$fe = function (array $e, string $k) { return isset($e[$k]) ? '<div class="err">' . e($e[$k]) . '</div>' : ''; };

$page = ['title' => 'Profil saya', 'noindex' => true];
require ROOT_PATH . '/includes/store_header.php';
?>
<div class="wrap">
  <div class="page-h"><h1>Profil saya</h1></div>
  <div class="prof">
    <aside class="card pcard-me">
      <div class="avatar"><?= e(mb_strtoupper(mb_substr($u['nama'], 0, 1))) ?></div>
      <b style="font-size:17px"><?= e($u['nama']) ?></b>
      <div class="muted" style="font-size:13.5px"><?= e($u['email']) ?></div>
      <div class="muted" style="font-size:12.5px;margin-top:4px">Bergabung <?= tgl($u['created_at']) ?> · <?= $jmlPesanan ?> pesanan</div>
      <div class="poin-card"><small>Saldo poin</small><b><?= number_format((int)$u['poin'], 0, ',', '.') ?></b><small>Setiap Rp10.000 belanja = 1 poin</small></div>
      <div class="sidelinks">
        <a href="riwayat.php"><?= icon('package', 18) ?> Pesanan saya</a>
        <a href="chat.php"><?= icon('chat', 18) ?> Chat admin</a>
        <a href="logout.php"><?= icon('logout', 18) ?> Keluar</a>
      </div>
    </aside>

    <div class="stack">
      <section class="card">
        <h2>Data diri</h2>
        <form method="post" data-once>
          <?= csrf_field() ?><input type="hidden" name="action" value="profil">
          <div class="grid2">
            <div class="field <?= isset($err['nama']) ? 'has-err' : '' ?>"><label for="nama">Nama lengkap</label><input class="input" id="nama" name="nama" value="<?= e($f['nama']) ?>" required><?= $fe($err, 'nama') ?></div>
            <div class="field <?= isset($err['no_hp']) ? 'has-err' : '' ?>"><label for="no_hp">Nomor HP</label><input class="input" id="no_hp" name="no_hp" value="<?= e($f['no_hp']) ?>" inputmode="tel"><?= $fe($err, 'no_hp') ?></div>
          </div>
          <div class="field"><label>Email</label><input class="input" value="<?= e($u['email']) ?>" disabled><div class="hint">Email tidak dapat diubah. Hubungi admin bila perlu.</div></div>
          <div class="field"><label for="alamat">Alamat utama</label><textarea class="textarea" id="alamat" name="alamat"><?= e($f['alamat']) ?></textarea></div>
          <button class="btn btn-primary">Simpan perubahan</button>
        </form>
      </section>

      <section class="card">
        <h2>Ganti password</h2>
        <form method="post" data-once>
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <div class="field <?= isset($errPw['lama']) ? 'has-err' : '' ?>"><label for="lama">Password saat ini</label><input class="input" id="lama" type="password" name="lama" autocomplete="current-password" required><?= $fe($errPw, 'lama') ?></div>
          <div class="grid2">
            <div class="field <?= isset($errPw['baru']) ? 'has-err' : '' ?>"><label for="baru">Password baru</label><input class="input" id="baru" type="password" name="baru" autocomplete="new-password" required><?= $fe($errPw, 'baru') ?></div>
            <div class="field <?= isset($errPw['baru2']) ? 'has-err' : '' ?>"><label for="baru2">Ulangi password baru</label><input class="input" id="baru2" type="password" name="baru2" autocomplete="new-password" required><?= $fe($errPw, 'baru2') ?></div>
          </div>
          <button class="btn btn-ghost">Perbarui password</button>
        </form>
      </section>

      <section class="card">
        <h2>Riwayat poin</h2>
        <?php if ($riwayatPoin): ?>
        <div class="scroll-x"><table class="tbl">
          <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="right">Poin</th></tr></thead>
          <tbody>
          <?php foreach ($riwayatPoin as $r): ?>
            <tr><td><?= tgl($r['created_at']) ?></td><td><?= e($r['keterangan']) ?></td>
                <td class="right <?= $r['tipe'] === 'keluar' ? 'minus' : 'plus' ?>"><?= $r['tipe'] === 'keluar' ? '−' : '+' ?><?= number_format((int)$r['jumlah'], 0, ',', '.') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php else: ?><p class="muted" style="margin:0">Belum ada poin. Poin masuk otomatis saat pesananmu berstatus Selesai.</p><?php endif; ?>
      </section>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/store_footer.php'; ?>
