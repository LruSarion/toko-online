<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$ROOT = '../../'; $A = '../';
require_admin('../login.php');

$id = (int)qs('id');
$muat = function () use ($pdo, $id) { $s = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?'); $s->execute([$id]); return $s->fetch(); };
$pl = $muat();
if (!$pl) { flash('error', 'Pelanggan tidak ditemukan.'); redirect('index.php'); }

$err = []; $form = ['nama' => $pl['nama'], 'email' => $pl['email'], 'no_hp' => (string)$pl['no_hp'], 'alamat' => (string)$pl['alamat']];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = post('action');
    if ($aksi === 'profil') {
        $form = ['nama' => post('nama'), 'email' => mb_strtolower(post('email')), 'no_hp' => post('no_hp'), 'alamat' => post('alamat')];
        if ($form['nama'] === '' || mb_strlen($form['nama']) > 100) { $err['nama'] = 'Nama wajib diisi (maks. 100 karakter).'; }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 100) { $err['email'] = 'Format email tidak valid.'; }
        elseif ((int)scalar($pdo, 'SELECT COUNT(*) FROM pelanggan WHERE email = ? AND id <> ?', [$form['email'], $id]) > 0) { $err['email'] = 'Email sudah dipakai pelanggan lain.'; }
        if ($form['no_hp'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $form['no_hp'])) { $err['no_hp'] = 'No. HP tidak valid (8–20 digit).'; }
        if (mb_strlen($form['alamat']) > 1000) { $err['alamat'] = 'Alamat terlalu panjang.'; }
        if (!$err) {
            $pdo->prepare('UPDATE pelanggan SET nama = ?, email = ?, no_hp = ?, alamat = ? WHERE id = ?')
                ->execute([$form['nama'], $form['email'], $form['no_hp'] ?: null, $form['alamat'] ?: null, $id]);
            flash('success', 'Data pelanggan diperbarui.');
            redirect('edit.php?id=' . $id);
        }
    } elseif ($aksi === 'poin') {
        $delta = (int)post('delta'); $ket = mb_substr(post('keterangan'), 0, 200);
        if ($delta === 0 || abs($delta) > 1000000) { flash('error', 'Isi jumlah poin (positif untuk menambah, negatif untuk mengurangi).'); }
        elseif ($ket === '') { flash('error', 'Alasan koreksi poin wajib diisi.'); }
        else {
            $pdo->beginTransaction();
            try {
                $now = (int)scalar($pdo, 'SELECT poin FROM pelanggan WHERE id = ? FOR UPDATE', [$id]);
                if ($now + $delta < 0) { throw new AppError('Poin tidak boleh menjadi negatif (saldo saat ini ' . $now . ').'); }
                $pdo->prepare('UPDATE pelanggan SET poin = poin + ? WHERE id = ?')->execute([$delta, $id]);
                $pdo->prepare("INSERT INTO riwayat_poin (pelanggan_id, jumlah, tipe, keterangan, referensi) VALUES (?, ?, 'koreksi', ?, ?)")
                    ->execute([$id, $delta, $ket, 'ADMIN-' . admin_id() . '-' . time()]);
                $pdo->commit();
                flash('success', 'Poin dikoreksi ' . ($delta > 0 ? '+' : '') . $delta . '.');
            } catch (AppError $ex) { $pdo->rollBack(); flash('error', $ex->getMessage()); }
            catch (Throwable $ex) { if ($pdo->inTransaction()) { $pdo->rollBack(); } error_log('koreksi poin: ' . $ex->getMessage()); flash('error', 'Koreksi poin gagal. Coba lagi.'); }
        }
        redirect('edit.php?id=' . $id);
    } elseif ($aksi === 'password') {
        $baru = (string)($_POST['baru'] ?? '');
        if (strlen($baru) < 8) { flash('error', 'Password baru minimal 8 karakter.'); }
        else { $pdo->prepare('UPDATE pelanggan SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $id]); flash('success', 'Password pelanggan direset. Sampaikan password baru kepada pelanggan.'); }
        redirect('edit.php?id=' . $id);
    }
}
$pl = $muat();
$st = $pdo->prepare('SELECT * FROM pesanan WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 10');
$st->execute([$id]); $pesanan = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM riwayat_poin WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 10');
$st->execute([$id]); $poinLog = $st->fetchAll();
$fe = function (string $k) use ($err) { return isset($err[$k]) ? '<div class="err">' . e($err[$k]) . '</div>' : ''; };
$cls = function (string $k) use ($err) { return 'field' . (isset($err[$k]) ? ' has-err' : ''); };

$adm = ['title' => 'Pelanggan: ' . $pl['nama'], 'nav' => 'pelanggan'];
require __DIR__ . '/../includes/layout_top.php';
?>
<p style="margin:0 0 18px"><a href="index.php" class="muted">&larr; Semua pelanggan</a></p>
<div class="fgrid">
  <div class="stack">
    <section class="card">
      <h2>Data pelanggan</h2>
      <form method="post" data-once>
        <?= csrf_field() ?><input type="hidden" name="action" value="profil">
        <div class="<?= $cls('nama') ?>"><label for="nama">Nama</label><input class="input" id="nama" name="nama" maxlength="100" value="<?= e($form['nama']) ?>" required><?= $fe('nama') ?></div>
        <div class="grid2">
          <div class="<?= $cls('email') ?>"><label for="email">Email</label><input class="input" id="email" type="email" name="email" maxlength="100" value="<?= e($form['email']) ?>" required><?= $fe('email') ?></div>
          <div class="<?= $cls('no_hp') ?>"><label for="no_hp">No. HP</label><input class="input" id="no_hp" name="no_hp" value="<?= e($form['no_hp']) ?>"><?= $fe('no_hp') ?></div>
        </div>
        <div class="<?= $cls('alamat') ?>"><label for="alamat">Alamat</label><textarea class="textarea" id="alamat" name="alamat" rows="3"><?= e($form['alamat']) ?></textarea><?= $fe('alamat') ?></div>
        <button class="btn btn-primary">Simpan perubahan</button>
      </form>
    </section>
    <section class="card">
      <h2>Pesanan terakhir</h2>
      <?php if ($pesanan): ?>
      <div class="scroll-x"><table class="tbl"><thead><tr><th>Kode</th><th>Total</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($pesanan as $o): ?><tr><td><a href="../pesanan/detail.php?id=<?= (int)$o['id'] ?>"><b><?= e(kode_pesanan($o['id'], $o['tanggal_pesanan'])) ?></b></a><br><small class="muted"><?= tgl($o['tanggal_pesanan']) ?></small></td><td><?= rupiah($o['total_harga']) ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php else: ?><p class="muted" style="margin:0">Belum ada pesanan.</p><?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <h2>Poin</h2>
      <p style="font:600 34px/1 var(--serif);margin:0 0 14px"><?= (int)$pl['poin'] ?> <span class="muted" style="font:500 14px var(--font)">poin</span></p>
      <form method="post" data-once>
        <?= csrf_field() ?><input type="hidden" name="action" value="poin">
        <div class="field"><label for="delta">Koreksi poin</label><input class="input" id="delta" name="delta" inputmode="numeric" placeholder="mis. 20 atau -10" required></div>
        <div class="field"><label for="ket">Alasan</label><input class="input" id="ket" name="keterangan" maxlength="200" placeholder="mis. Kompensasi keterlambatan" required></div>
        <button class="btn btn-ghost btn-block">Terapkan koreksi</button>
      </form>
      <?php if ($poinLog): ?><div style="margin-top:18px"><b style="font-size:13px">Riwayat terbaru</b>
        <?php foreach ($poinLog as $l): ?><div class="rowi" style="padding:8px 0"><div class="grow"><b style="font-weight:500;white-space:normal"><?= e($l['keterangan']) ?></b><small><?= tgl($l['created_at'], true) ?></small></div><span class="badge <?= (int)$l['jumlah'] >= 0 ? 'b-ok' : 'b-bad' ?>"><?= (int)$l['jumlah'] > 0 ? '+' : '' ?><?= (int)$l['jumlah'] ?></span></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="card">
      <h2>Reset password</h2>
      <form method="post" data-confirm="Reset password pelanggan ini?" data-once>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field"><label for="baru">Password baru</label><input class="input" id="baru" type="text" name="baru" minlength="8" autocomplete="off" required><div class="hint">Minimal 8 karakter. Sampaikan ke pelanggan secara pribadi.</div></div>
        <button class="btn btn-ghost btn-block">Reset password</button>
      </form>
    </section>
    <section class="card">
      <h2>Hapus akun</h2>
      <p class="muted" style="font-size:13.5px;margin-top:0">Akun, riwayat poin, dan chat dihapus. Pesanan lama tetap tersimpan sebagai pesanan tamu.</p>
      <form method="post" action="delete.php" data-confirm="Hapus akun <?= e($pl['nama']) ?> secara permanen?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-danger btn-block"><?= icon('trash', 16) ?> Hapus pelanggan</button>
      </form>
    </section>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
